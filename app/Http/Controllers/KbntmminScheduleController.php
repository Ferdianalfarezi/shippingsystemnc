<?php

namespace App\Http\Controllers;

use App\Models\KbntmminSchedule;
use App\Models\KbntmminPrintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;

class KbntmminScheduleController extends Controller
{
    public function index()
    {
        $schedules = KbntmminSchedule::orderBy('jam_keluar')->get();
        $logs = KbntmminPrintLog::orderByDesc('printed_at')->limit(50)->get();
        $printers = $this->getInstalledPrinters();

        return view('kbntmmin_schedule.index', compact('schedules', 'logs', 'printers'));
    }

    public function store(Request $request)
    {
        $request->merge(['cycle' => KbntmminSchedule::normalizeCycle($request->input('cycle'))]);

        $data = $request->validate([
            'cycle' => 'required|string|max:10|unique:kbntmmin_schedule,cycle',
            'jam_keluar' => 'required|date_format:H:i',
            'printer_name' => 'required|string|max:255',
        ], [
            'cycle.unique' => 'Cycle ini sudah ada di master.',
            'printer_name.required' => 'Printer wajib dipilih.',
        ]);

        $data['printer_name'] = trim($data['printer_name']);

        KbntmminSchedule::create($data);

        return redirect()->route('kbntmmin-schedule.index')->with([
            'sweet_alert' => [
                'type' => 'success',
                'icon' => 'success',
                'title' => 'Berhasil!',
                'text' => "Cycle {$data['cycle']} jam {$data['jam_keluar']} → {$data['printer_name']} berhasil ditambahkan.",
                'showConfirmButton' => true,
                'timer' => 3000,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = KbntmminSchedule::findOrFail($id);

        $request->merge(['cycle' => KbntmminSchedule::normalizeCycle($request->input('cycle'))]);

        $data = $request->validate([
            'cycle' => [
                'required', 'string', 'max:10',
                Rule::unique('kbntmmin_schedule', 'cycle')->ignore($schedule->id),
            ],
            'jam_keluar' => 'required|date_format:H:i',
            'printer_name' => 'required|string|max:255',
        ], [
            'cycle.unique' => 'Cycle ini sudah ada di master.',
            'printer_name.required' => 'Printer wajib dipilih.',
        ]);

        $data['printer_name'] = trim($data['printer_name']);

        $oldCycle = KbntmminSchedule::normalizeCycle($schedule->cycle);
        $jamChanged = substr((string) $schedule->jam_keluar, 0, 5) !== $data['jam_keluar'];

        $schedule->update($data);

        // Jam keluar diubah → log print cycle ini hari ini dihapus,
        // supaya scheduler print ulang di jam yang baru.
        $text = "Cycle {$data['cycle']} berhasil diupdate.";
        if ($jamChanged) {
            $reset = KbntmminPrintLog::whereIn('cycle', array_unique([$oldCycle, $data['cycle']]))
                ->whereDate('printed_at', today())
                ->delete();

            if ($reset > 0) {
                $text .= " Cycle ini akan di-print ulang jam {$data['jam_keluar']}.";
            }
        }

        return redirect()->route('kbntmmin-schedule.index')->with([
            'sweet_alert' => [
                'type' => 'success',
                'icon' => 'success',
                'title' => 'Berhasil!',
                'text' => $text,
                'showConfirmButton' => true,
                'timer' => 3000,
            ],
        ]);
    }

    public function destroy($id)
    {
        $schedule = KbntmminSchedule::findOrFail($id);
        $cycle = $schedule->cycle;
        $schedule->delete();

        return redirect()->route('kbntmmin-schedule.index')->with([
            'sweet_alert' => [
                'type' => 'success',
                'icon' => 'success',
                'title' => 'Dihapus!',
                'text' => "Cycle {$cycle} berhasil dihapus.",
                'showConfirmButton' => true,
                'timer' => 3000,
            ],
        ]);
    }

    /**
     * Hapus log print => manifest+cycle ini akan di-print ulang otomatis
     * oleh scheduler di menit berikutnya (selama datanya masih ada di
     * kanban_tmmins dan jam keluarnya sudah lewat).
     */
    public function destroyLog($id)
    {
        $log = KbntmminPrintLog::findOrFail($id);
        $info = "Manifest {$log->manifest_no} cycle {$log->cycle}";
        $log->delete();

        return redirect()->route('kbntmmin-schedule.index')->with([
            'sweet_alert' => [
                'type' => 'success',
                'icon' => 'success',
                'title' => 'Masuk antrian print ulang',
                'text' => "{$info} akan di-print ulang otomatis dalam 1 menit.",
                'showConfirmButton' => true,
                'timer' => 4000,
            ],
        ]);
    }

    /**
     * Ambil daftar printer yang ter-install di Windows (untuk saran dropdown).
     * Kalau gagal (misal bukan Windows), return array kosong — input tetap bisa diketik manual.
     */
    private function getInstalledPrinters(): array
    {
        try {
            $result = Process::timeout(15)->run([
                'powershell',
                '-NoProfile',
                '-Command',
                'Get-Printer | Select-Object -ExpandProperty Name',
            ]);

            if ($result->failed()) {
                return [];
            }

            return collect(preg_split('/\r\n|\r|\n/', $result->output()))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}