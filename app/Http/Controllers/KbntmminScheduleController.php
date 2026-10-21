<?php

namespace App\Http\Controllers;

use App\Models\KanbanTmmins;
use App\Models\KanbanTmminsP2;
use App\Models\KbntmminSchedule;
use App\Models\KbntmminPrintLog;
use App\Services\KanbanTmminAutoPrintPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;

class KbntmminScheduleController extends Controller
{
    public function index()
    {
        $schedules = KbntmminSchedule::orderBy('jam_keluar')->orderBy('route')->orderBy('dock_code')->get();
        $logs = KbntmminPrintLog::orderByDesc('printed_at')->limit(50)->get();
        $printers = $this->getInstalledPrinters();

        // Route & dock tiap manifest di riwayat (1 manifest = 1 route & 1 dock), dari P1 & P2
        $logManifestNos = $logs->pluck('manifest_no')->unique();
        $logManifests = KanbanTmmins::whereIn('manifest_no', $logManifestNos)->get(['manifest_no', 'route', 'dock_code'])
            ->toBase()
            ->merge(KanbanTmminsP2::whereIn('manifest_no', $logManifestNos)->get(['manifest_no', 'route', 'dock_code']))
            ->keyBy('manifest_no');

        // Jumlah kanban (P1 + P2) yang cocok ke tiap jadwal → kelihatan kalau datanya memang kosong
        $scheduleKanbans = app(KanbanTmminAutoPrintPlanner::class)->plan($schedules)
            ->map(fn ($row) => [
                'p1' => $row['kanbans']->where('source', 'p1')->count(),
                'p2' => $row['kanbans']->where('source', 'p2')->count(),
            ]);

        // Saran route & dock di form: yang ada di data kanban P1 & P2 + yang sudah dipakai di schedule
        $routeOptions = KanbanTmmins::query()->distinct()->pluck('route')
            ->merge(KanbanTmminsP2::query()->distinct()->pluck('route'))
            ->merge($schedules->pluck('route'))
            ->map(fn ($route) => KbntmminSchedule::normalizeRoute($route))
            ->filter()->unique()->sort()->values();

        $dockOptions = KanbanTmmins::query()->distinct()->pluck('dock_code')
            ->merge(KanbanTmminsP2::query()->distinct()->pluck('dock_code'))
            ->merge($schedules->pluck('dock_code'))
            ->map(fn ($dock) => KbntmminSchedule::normalizeDock($dock))
            ->filter()->unique()->sort()->values();

        return view('kbntmmin_schedule.index', compact('schedules', 'logs', 'printers', 'logManifests', 'scheduleKanbans', 'routeOptions', 'dockOptions'));
    }

    public function store(Request $request)
    {
        $data = $this->validateSchedule($request);

        $schedule = KbntmminSchedule::create($data);

        return redirect()->route('kbntmmin-schedule.index')->with([
            'sweet_alert' => [
                'type' => 'success',
                'icon' => 'success',
                'title' => 'Berhasil!',
                'text' => ucfirst($schedule->label) . " jam {$data['jam_keluar']} → {$data['printer_name']} berhasil ditambahkan.",
                'showConfirmButton' => true,
                'timer' => 3000,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = KbntmminSchedule::findOrFail($id);

        $data = $this->validateSchedule($request, $schedule);

        $jamChanged = substr((string) $schedule->jam_keluar, 0, 5) !== $data['jam_keluar'];

        $schedule->update($data);

        // Jam keluar diubah → log print hari ini untuk kanban yang ikut schedule ini dihapus,
        // supaya scheduler print ulang di jam yang baru. Dock lain di cycle yang sama tidak kena.
        $text = ucfirst($schedule->label) . ' berhasil diupdate.';
        if ($jamChanged) {
            $plan = app(KanbanTmminAutoPrintPlanner::class)
                ->plan(collect([$schedule]), KbntmminSchedule::all())
                ->first();

            $manifestNos = $plan['kanbans']->pluck('manifest_no')->unique();

            if ($manifestNos->isNotEmpty()) {
                KbntmminPrintLog::where('cycle', KbntmminSchedule::normalizeCycle($schedule->cycle))
                    ->whereIn('manifest_no', $manifestNos)
                    ->whereDate('printed_at', today())
                    ->delete();
            }

            $jam = $data['jam_keluar'];
            if ($schedule->isDue()) {
                $text .= " Akan keluar dalam 1 menit.";
            } elseif ($jam > now()->format('H:i')) {
                $text .= " Akan keluar jam {$jam}.";
            } else {
                $window = (int) config('services.auto_print.window_minutes', 10);
                $text .= " Jam {$jam} sudah lewat lebih dari {$window} menit, jadi tidak keluar hari ini (besok keluar jam {$jam}).";
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
        $label = ucfirst($schedule->label);
        $schedule->delete();

        return redirect()->route('kbntmmin-schedule.index')->with([
            'sweet_alert' => [
                'type' => 'success',
                'icon' => 'success',
                'title' => 'Dihapus!',
                'text' => "{$label} berhasil dihapus.",
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
    /**
     * Validasi form schedule. Kombinasi route + dock + cycle harus unik;
     * route/dock kosong = berlaku untuk semua route/dock di cycle tsb.
     */
    private function validateSchedule(Request $request, ?KbntmminSchedule $ignore = null): array
    {
        $route = KbntmminSchedule::normalizeRoute($request->input('route'));
        $dock = KbntmminSchedule::normalizeDock($request->input('dock_code'));

        $request->merge([
            'cycle' => KbntmminSchedule::normalizeCycle($request->input('cycle')),
            'route' => $route,
            'dock_code' => $dock,
        ]);

        $unique = Rule::unique('kbntmmin_schedule', 'cycle')
            ->where(fn ($query) => $route === null ? $query->whereNull('route') : $query->where('route', $route))
            ->where(fn ($query) => $dock === null ? $query->whereNull('dock_code') : $query->where('dock_code', $dock));

        if ($ignore) {
            $unique->ignore($ignore->id);
        }

        $candidate = new KbntmminSchedule([
            'route' => $route,
            'dock_code' => $dock,
            'cycle' => $request->input('cycle'),
        ]);

        $data = $request->validate([
            'route' => 'nullable|string|max:20',
            'dock_code' => 'nullable|string|max:10',
            'cycle' => ['required', 'string', 'max:10', $unique],
            'jam_keluar' => 'required|date_format:H:i',
            'printer_name' => 'required|string|max:255',
        ], [
            'cycle.unique' => 'Jadwal ' . $candidate->label . ' sudah ada di master.',
            'printer_name.required' => 'Printer wajib dipilih.',
        ]);

        $data['printer_name'] = trim($data['printer_name']);

        return $data;
    }

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