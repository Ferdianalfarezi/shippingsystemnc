<?php

namespace App\Services;

use App\Models\KanbanTmmins;
use App\Models\KbntmminPrintLog;
use App\Models\KbntmminSchedule;
use Illuminate\Support\Collection;

/**
 * Pasangkan data kanban TMMIN ke master schedule auto print.
 *
 * Schedule berlaku per dock + cycle. Schedule tanpa dock berlaku untuk semua
 * dock di cycle tsb, kecuali dock yang punya schedule sendiri.
 * Dipakai oleh command kanban:tmmin-auto-print, endpoint /auto-print/check
 * (agent/kiosk), dan reset log saat jam schedule diedit.
 */
class KanbanTmminAutoPrintPlanner
{
    /**
     * @param  Collection<int, KbntmminSchedule>  $schedules  schedule yang mau diproses
     * @param  Collection<int, KbntmminSchedule>|null  $allSchedules  semua schedule (untuk menentukan dock mana
     *                                                                yang sudah punya schedule sendiri); default = $schedules
     * @return Collection<int, array{schedule: KbntmminSchedule, kanbans: Collection, pending: Collection}>
     *         keyed by schedule id, urutan sama dengan $schedules
     */
    public function plan(Collection $schedules, ?Collection $allSchedules = null): Collection
    {
        $allSchedules ??= $schedules;

        // Index semua schedule: "DOCK|CYCLE" untuk yang spesifik dock, "CYCLE" untuk semua dock
        $exact = [];
        $anyDock = [];
        foreach ($allSchedules as $schedule) {
            $cycle = KbntmminSchedule::normalizeCycle($schedule->cycle);
            $dock = KbntmminSchedule::normalizeDock($schedule->dock_code);

            if ($dock !== null) {
                $exact[$dock . '|' . $cycle] = $schedule->id;
            } else {
                $anyDock[$cycle] = $schedule->id;
            }
        }

        $result = [];
        foreach ($schedules as $schedule) {
            $result[$schedule->id] = ['schedule' => $schedule, 'kanbans' => [], 'pending' => []];
        }

        // Manifest+cycle yang sudah pernah diproses (printed maupun failed) tidak di-print lagi
        $loggedKeys = KbntmminPrintLog::all(['manifest_no', 'cycle'])
            ->mapWithKeys(fn ($log) => [$log->manifest_no . '|' . $log->cycle => true])
            ->all();

        foreach (KanbanTmmins::all(['id', 'manifest_no', 'cycle', 'dock_code']) as $kanban) {
            $cycle = KbntmminSchedule::normalizeCycle($kanban->cycle);
            $dock = KbntmminSchedule::normalizeDock($kanban->dock_code);

            $scheduleId = $exact[$dock . '|' . $cycle] ?? $anyDock[$cycle] ?? null;
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
}
