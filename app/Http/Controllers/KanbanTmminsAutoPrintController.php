<?php

namespace App\Http\Controllers;

use App\Models\KbntmminSchedule;
use App\Models\KbntmminPrintLog;
use App\Services\KanbanTmminAutoPrintPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KanbanTmminsAutoPrintController extends Controller
{
    /**
     * Halaman monitor yang dibuka terus di jendela kiosk.
     * Wajib ada parameter ?printer=NAMA PRINTER (sama persis dengan printer_name di schedule).
     */
    public function monitor(Request $request)
    {
        $this->authorizeToken($request);

        $printer = trim((string) $request->input('printer'));

        return view('kanbantmmins.auto-print-monitor', compact('printer'));
    }

    /**
     * Dipanggil kiosk tiap X detik.
     * Hanya mengembalikan cycle yang printer_name-nya sama dengan printer kiosk ini,
     * sudah waktunya keluar, dan masih punya manifest yang belum pernah di-print.
     */
    public function check(Request $request)
    {
        $this->authorizeToken($request);

        $printer = trim((string) $request->input('printer'));
        $nowTime = now()->format('H:i:s');

        $response = [
            'due' => false,
            'server_time' => $nowTime,
            'printer' => $printer,
            'next' => null,
        ];

        if ($printer === '') {
            $response['error'] = 'Parameter printer belum diisi di URL kiosk.';
            return response()->json($response);
        }

        $allSchedules = KbntmminSchedule::orderBy('jam_keluar')->get();
        $printerSchedules = $allSchedules->filter(fn ($schedule) => trim((string) $schedule->printer_name) === $printer);

        $nextSchedule = $printerSchedules->first(fn ($schedule) => $schedule->jam_keluar > $nowTime);
        if ($nextSchedule) {
            $response['next'] = [
                'cycle' => $nextSchedule->cycle,
                'route' => $nextSchedule->route,
                'dock_code' => $nextSchedule->dock_code,
                'label' => $nextSchedule->label,
                'jam_keluar' => substr($nextSchedule->jam_keluar, 0, 5),
            ];
        }

        $dueSchedules = $printerSchedules->filter(fn ($schedule) => $schedule->isDue());
        if ($dueSchedules->isEmpty()) {
            return response()->json($response);
        }

        // Kanban dipasangkan ke schedule per dock + cycle (lihat KanbanTmminAutoPrintPlanner)
        $plan = app(KanbanTmminAutoPrintPlanner::class)->plan($dueSchedules, $allSchedules);

        foreach ($plan as $row) {
            if ($row['pending']->isEmpty()) {
                continue;
            }

            $schedule = $row['schedule'];

            // P1 & P2 dikirim satu per satu (id tabelnya beda); agent harus kirim balik `source` ke /print
            $source = $row['pending']->contains('source', 'p1') ? 'p1' : 'p2';
            $pending = $row['pending']->where('source', $source);

            return response()->json(array_merge($response, [
                'due' => true,
                'cycle' => KbntmminSchedule::normalizeCycle($schedule->cycle),
                'route' => $schedule->route,
                'dock_code' => $schedule->dock_code,
                'label' => $schedule->label . ($source === 'p2' ? ' P2' : ''),
                'source' => $source,
                'jam_keluar' => substr($schedule->jam_keluar, 0, 5),
                'manifest_nos' => $pending->pluck('manifest_no')->unique()->values(),
                'ids' => $pending->pluck('id')->values(),
            ]));
        }

        return response()->json($response);
    }

    /**
     * Halaman print untuk iframe kiosk.
     * Pakai ulang printSelected() supaya sorting, separator plant, dan
     * barcode sama persis dengan print manual.
     */
    public function print(Request $request)
    {
        $this->authorizeToken($request);

        $request->validate([
            'ids' => 'required|string',
            'source' => 'nullable|in:p1,p2',
        ]);

        $printRequest = new Request([
            'ids' => $request->input('ids'),
            'plant' => 'all',
        ]);

        // source=p2 → id dari tabel kanban_tmmins_p2, pakai halaman print P2
        $response = $request->input('source') === 'p2'
            ? app(KanbanTmminsP2Controller::class)->printSelected($printRequest)
            : app(KanbanTmminsController::class)->printSelected($printRequest);

        // Mode PDF (dipakai command kanban:tmmin-auto-print): paksa ukuran halaman
        // supaya PDF pas dengan kanban, bukan Letter. Kiosk tidak kirim pdf=1.
        $pageSize = config('services.auto_print.page_size');
        if ($request->boolean('pdf') && $pageSize && $response instanceof \Illuminate\Contracts\View\View) {
            $style = '<style>@page { size: ' . e($pageSize) . '; margin: 0; }</style>';
            return response(str_replace('</head>', $style . '</head>', $response->render()));
        }

        return $response;
    }

    /**
     * Dipanggil kiosk setelah print dikirim ke printer.
     */
    public function markPrinted(Request $request)
    {
        $this->authorizeToken($request);

        $data = $request->validate([
            'cycle' => 'required|string|max:10',
            'printer' => 'required|string|max:255',
            'manifest_nos' => 'required|array|min:1',
            'manifest_nos.*' => 'required|string|max:50',
            'source' => 'nullable|string|in:KIOSK,AGENT',
        ]);

        $source = $data['source'] ?? 'KIOSK';

        $cycle = KbntmminSchedule::normalizeCycle($data['cycle']);
        $printer = trim($data['printer']);

        foreach ($data['manifest_nos'] as $manifestNo) {
            KbntmminPrintLog::updateOrCreate(
                ['manifest_no' => $manifestNo, 'cycle' => $cycle],
                [
                    'printer_name' => $printer,
                    'status' => 'printed',
                    'error_message' => null,
                    'printed_at' => now(),
                    'printed_by' => $source,
                ]
            );
        }

        Log::info("Auto print ({$source}) cycle {$cycle} → {$printer}: " . count($data['manifest_nos']) . " manifest ditandai printed");

        return response()->json(['success' => true]);
    }

    private function authorizeToken(Request $request): void
    {
        $expected = config('services.auto_print.token');

        if (empty($expected) || !hash_equals($expected, (string) $request->input('token'))) {
            abort(403, 'Token auto print tidak valid');
        }
    }
}