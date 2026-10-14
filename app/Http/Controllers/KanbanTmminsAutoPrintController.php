<?php

namespace App\Http\Controllers;

use App\Models\KanbanTmmins;
use App\Models\KbntmminSchedule;
use App\Models\KbntmminPrintLog;
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

        $dueSchedules = KbntmminSchedule::where('printer_name', $printer)
            ->where('jam_keluar', '<=', $nowTime)
            ->orderBy('jam_keluar')
            ->get();

        $nextSchedule = KbntmminSchedule::where('printer_name', $printer)
            ->where('jam_keluar', '>', $nowTime)
            ->orderBy('jam_keluar')
            ->first();

        if ($nextSchedule) {
            $response['next'] = [
                'cycle' => $nextSchedule->cycle,
                'jam_keluar' => substr($nextSchedule->jam_keluar, 0, 5),
            ];
        }

        if ($dueSchedules->isEmpty()) {
            return response()->json($response);
        }

        $printedKeys = KbntmminPrintLog::all(['manifest_no', 'cycle'])
            ->mapWithKeys(fn ($log) => [$log->manifest_no . '|' . $log->cycle => true])
            ->all();

        $kanbans = KanbanTmmins::all(['id', 'manifest_no', 'cycle']);

        foreach ($dueSchedules as $schedule) {
            $cycle = KbntmminSchedule::normalizeCycle($schedule->cycle);

            $pending = $kanbans->filter(function ($kanban) use ($cycle, $printedKeys) {
                return KbntmminSchedule::normalizeCycle($kanban->cycle) === $cycle
                    && !isset($printedKeys[$kanban->manifest_no . '|' . $cycle]);
            });

            if ($pending->isNotEmpty()) {
                return response()->json(array_merge($response, [
                    'due' => true,
                    'cycle' => $cycle,
                    'jam_keluar' => substr($schedule->jam_keluar, 0, 5),
                    'manifest_nos' => $pending->pluck('manifest_no')->unique()->values(),
                    'ids' => $pending->pluck('id')->values(),
                ]));
            }
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
        ]);

        $printRequest = new Request([
            'ids' => $request->input('ids'),
            'plant' => 'all',
        ]);

        return app(KanbanTmminsController::class)->printSelected($printRequest);
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
        ]);

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
                    'printed_by' => 'KIOSK',
                ]
            );
        }

        Log::info("Auto print (kiosk) cycle {$cycle} → {$printer}: " . count($data['manifest_nos']) . " manifest ditandai printed");

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