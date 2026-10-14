<?php

namespace App\Http\Controllers;

use App\Models\KanbanInoac;
use App\Services\KanbanInoacRackSplitter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KanbanInoacSplitController extends Controller
{
    public function index()
    {
        return view('kanbansplitinoac.index');
    }

    public function process(Request $request)
    {
        $movedSourcePaths = [];

        try {
            $request->validate([
                'pdf' => 'required|array|min:1',
                'pdf.*' => 'file|mimes:pdf|max:20480',
            ]);

            $files = $request->file('pdf');

            $sourceDir = storage_path('app/kanban/inoac/source');
            if (!is_dir($sourceDir) && !mkdir($sourceDir, 0777, true) && !is_dir($sourceDir)) {
                throw new \RuntimeException("Gagal bikin folder source: {$sourceDir}");
            }

            $sources = [];
            $originalUploadNames = [];

            foreach ($files as $file) {
                $originalUploadName = $file->getClientOriginalName();
                $originalName = pathinfo($originalUploadName, PATHINFO_FILENAME);
                $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $originalName);

                $sourceFileName = $safeName . '_' . uniqid() . '.pdf';
                $sourceFullPath = $sourceDir . '/' . $sourceFileName;

                $file->move($sourceDir, $sourceFileName);

                $movedSourcePaths[] = $sourceFullPath;
                $originalUploadNames[] = $originalUploadName;

                $sources[] = [
                    'path' => $sourceFullPath,
                    'original_filename' => $originalUploadName,
                ];
            }

            $outputName = 'kanban_inoac_gabungan_' . now()->format('Ymd_His') . '.pdf';
            $outputDir = storage_path('app/kanban/inoac/output');
            if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
                throw new \RuntimeException("Gagal bikin folder output: {$outputDir}");
            }
            $outputFullPath = $outputDir . '/' . $outputName;

            $splitter = new KanbanInoacRackSplitter();
            $result = $splitter->splitMultiple($sources, $outputFullPath);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('KANBAN INOAC SPLIT ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            foreach ($movedSourcePaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            return response()->json([
                'message' => 'Gagal memproses PDF: ' . $e->getMessage()
                    . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ], 500);
        }

        foreach ($movedSourcePaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $metaToken = Str::random(24);

        $historyDir = storage_path('app/kanban/inoac/history');
        if (!is_dir($historyDir) && !mkdir($historyDir, 0777, true) && !is_dir($historyDir)) {
            throw new \RuntimeException("Gagal bikin folder history: {$historyDir}");
        }
        $historyRelativePath = 'kanban/inoac/history/' . $metaToken . '.pdf';
        copy($outputFullPath, storage_path('app/' . $historyRelativePath));

        $combinedOriginalName = implode(', ', $originalUploadNames);

        KanbanInoac::create([
            'token' => $metaToken,
            'original_filename' => $combinedOriginalName,
            'file_path' => $historyRelativePath,
            'total_labels' => $result['total_labels'] ?? 0,
            'matched' => $result['matched'] ?? 0,
            'unmatched' => $result['unmatched'] ?? 0,
            'unmatched_no_text' => $result['unmatched_no_text'] ?? 0,
            'unmatched_no_extract' => $result['unmatched_no_extract'] ?? 0,
            'unmatched_no_master' => $result['unmatched_no_master'] ?? 0,
            'unmatched_no_rack' => $result['unmatched_no_rack'] ?? 0,
            'labels_meta' => $result['labels'] ?? [],
        ]);

        return response()->file($outputFullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $outputName . '"',
            'X-Total-Labels' => $result['total_labels'] ?? 0,
            'X-Matched' => $result['matched'] ?? 0,
            'X-Unmatched' => $result['unmatched'] ?? 0,
            'X-Unmatched-No-Text' => $result['unmatched_no_text'] ?? 0,
            'X-Unmatched-No-Extract' => $result['unmatched_no_extract'] ?? 0,
            'X-Unmatched-No-Master' => $result['unmatched_no_master'] ?? 0,
            'X-Unmatched-No-Rack' => $result['unmatched_no_rack'] ?? 0,
            'X-Meta-Token' => $metaToken,
        ])->deleteFileAfterSend(true);
    }

    public function labelsMeta(string $token)
    {
        $split = KanbanInoac::where('token', $token)->first();

        if (!$split) {
            return response()->json([
                'message' => 'Metadata gak ketemu, coba split ulang PDF-nya.',
            ], 404);
        }

        return response()->json(['labels' => $split->labels_meta ?? []]);
    }

    public function recent()
    {
        $items = KanbanInoac::latest()
            ->take(3)
            ->get([
                'token', 'original_filename', 'total_labels',
                'matched', 'unmatched', 'created_at',
            ])
            ->map(fn ($item) => [
                'token' => $item->token,
                'filename' => $item->original_filename,
                'total' => $item->total_labels,
                'matched' => $item->matched,
                'unmatched' => $item->unmatched,
                'created_at' => $item->created_at->diffForHumans(),
            ]);

        return response()->json(['items' => $items]);
    }

    public function download(string $token)
    {
        $split = KanbanInoac::where('token', $token)->first();

        if (!$split) {
            abort(404, 'Riwayat split tidak ditemukan.');
        }

        $fullPath = storage_path('app/' . $split->file_path);

        if (!is_file($fullPath)) {
            abort(404, 'File hasil split sudah tidak ada di server.');
        }

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $split->original_filename . '"',
            'X-Total-Labels' => $split->total_labels,
            'X-Matched' => $split->matched,
            'X-Unmatched' => $split->unmatched,
            'X-Meta-Token' => $split->token,
        ]);
    }
}