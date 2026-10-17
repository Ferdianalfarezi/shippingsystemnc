<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbntmminSchedule extends Model
{
    protected $table = 'kbntmmin_schedule';

    protected $fillable = [
        'dock_code',
        'cycle',
        'jam_keluar',
        'printer_name',
    ];

    /**
     * Samakan format cycle supaya "01", " 1", dan "1" dianggap sama.
     * Cycle non-angka (misal "A") cukup di-trim + uppercase.
     */
    public static function normalizeCycle($cycle): string
    {
        $clean = strtoupper(trim((string) $cycle));

        if ($clean === '') {
            return '';
        }

        if (ctype_digit($clean)) {
            return (string) (int) $clean;
        }

        return $clean;
    }

    /**
     * Samakan format dock_code (" 4p" → "4P"). Kosong → null (= semua dock).
     */
    public static function normalizeDock($dock): ?string
    {
        $clean = strtoupper(trim((string) $dock));

        return $clean === '' ? null : $clean;
    }

    /** Label untuk log/pesan, mis. "Dock 4P cycle 4" atau "cycle 4 (semua dock)". */
    public function getLabelAttribute(): string
    {
        $cycle = self::normalizeCycle($this->cycle);

        return $this->dock_code
            ? "Dock {$this->dock_code} cycle {$cycle}"
            : "cycle {$cycle} (semua dock)";
    }
}