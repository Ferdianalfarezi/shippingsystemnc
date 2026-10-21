<?php

namespace App\Services;

use App\Models\KanbanTmmins;
use App\Models\KanbanTmminsP2;
use App\Models\KbntmminPrintLog;
use App\Models\KbntmminSchedule;
use Illuminate\Support\Collection;

/**
 * Pasangkan data kanban TMMIN (P1: kanbantmmins + P2: kanban_tmmins_p2) ke master schedule auto print.
 * Tiap kanban diberi atribut `source` ('p1' / 'p2') karena id kedua tabel bisa sama,
 * jadi pemakai hasil plan() harus memisahkan cetakan per source.
 *
 * Schedule berlaku per route + dock + cycle. Route/dock yang dikosongkan berlaku
 * untuk semua route/dock di cycle tsb. Kalau 1 kanban cocok ke beberapa schedule,
 * dipakai yang paling spesifik: route+dock > route saja > dock saja > cycle saja.
 * Dipakai oleh command kanban:tmmin-auto-print, endpoint /auto-print/check
 * (agent/kiosk), dan reset log saat jam schedule diedit.
 */
class KanbanTmminAutoPrintPlanner
{
    /**
     * @param  Collection<int, KbntmminSchedule>  $schedules  schedule yang mau diproses
     * @param  Collection<int, KbntmminSchedule>|null  $allSchedules  semua schedule (untuk menentukan route/dock mana
     *                                                                yang sudah punya schedule sendiri); default = $schedules
     * @return Collection<int, array{schedule: KbntmminSchedule, kanbans: Collection, pending: Collection}>
     *         keyed by schedule id, urutan sama dengan $schedules
     */
    public function plan(Collection $schedules, ?Collection $allSchedules = null): Collection
    {
        $allSchedules ??= $schedules;

        // Index semua schedule dengan key "ROUTE|DOCK|CYCLE"; route/dock kosong ditulis "*"
        $index = [];
        foreach ($allSchedules as $schedule) {
            $key = $this->key(
                KbntmminSchedule::normalizeRoute($schedule->route),
                KbntmminSchedule::normalizeDock($schedule->dock_code),
                KbntmminSchedule::normalizeCycle($schedule->cycle)
            );
            $index[$key] = $schedule->id;
        }

        $result = [];
        foreach ($schedules as $schedule) {
            $result[$schedule->id] = ['schedule' => $schedule, 'kanbans' => [], 'pending' => []];
        }

        // Manifest+cycle yang sudah diproses HARI INI (printed maupun failed) tidak di-print lagi.
        // Riwayat hari sebelumnya diabaikan → tiap hari di jam schedule tetap keluar lagi.
        $loggedKeys = KbntmminPrintLog::whereDate('printed_at', today())
            ->get(['manifest_no', 'cycle'])
            ->mapWithKeys(fn ($log) => [$log->manifest_no . '|' . $log->cycle => true])
            ->all();

        foreach ($this->allKanbans() as $kanban) {
            $cycle = KbntmminSchedule::normalizeCycle($kanban->cycle);
            $dock = KbntmminSchedule::normalizeDock($kanban->dock_code);
            $route = KbntmminSchedule::normalizeRoute($kanban->route);

            // Yang paling spesifik menang
            $scheduleId = $index[$this->key($route, $dock, $cycle)]
                ?? $index[$this->key($route, null, $cycle)]
                ?? $index[$this->key(null, $dock, $cycle)]
                ?? $index[$this->key(null, null, $cycle)]
                ?? null;

            if ($scheduleId === null || !isset($result[$scheduleId])) {
                continue;
            }

            $result[$scheduleId]['kanbans'][] = $kanban;
            if (!isset($loggedKeys[$kanban->manifest_no . '|' . $cycle])) {
                $result[$scheduleId]['pending'][] = $kanban;
            }
        }

        return collect($result)->map(fn ($row) => [
            'schedule' => $row['schedule'],
            'kanbans' => collect($row['kanbans']),
            'pending' => collect($row['pending']),
        ]);
    }

    /** Kanban P1 + P2, masing-masing ditandai source-nya. */
    private function allKanbans(): Collection
    {
        $columns = ['id', 'manifest_no', 'cycle', 'dock_code', 'route'];

        $p1 = KanbanTmmins::all($columns)->each(fn ($kanban) => $kanban->setAttribute('source', 'p1'));
        $p2 = KanbanTmminsP2::all($columns)->each(fn ($kanban) => $kanban->setAttribute('source', 'p2'));

        return $p1->toBase()->merge($p2->toBase());
    }

    private function key(?string $route, ?string $dock, string $cycle): string
    {
        return ($route ?? '*') . '|' . ($dock ?? '*') . '|' . $cycle;
    }
}
