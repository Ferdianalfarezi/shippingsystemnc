<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbntmminSchedule extends Model
{
    protected $table = 'kbntmmin_schedule';

    protected $fillable = [
        'route',
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

    /**
     * Samakan format route (" rc13" → "RC13"). Kosong → null (= semua route).
     */
    public static function normalizeRoute($route): ?string
    {
        $clean = strtoupper(trim((string) $route));

        return $clean === '' ? null : $clean;
    }

    /**
     * Label untuk log/pesan, mis. "Route RC13 dock 4P cycle 4",
     * "Dock 4B cycle 4 (semua route)", atau "cycle 4 (semua route & dock)".
     */
    public function getLabelAttribute(): string
    {
        $parts = [];
        if ($this->route) {
            $parts[] = "Route {$this->route}";
        }
        if ($this->dock_code) {
            $parts[] = ($parts ? 'dock' : 'Dock') . " {$this->dock_code}";
        }
        $parts[] = 'cycle ' . self::normalizeCycle($this->cycle);

        $semua = array_filter([
            $this->route ? null : 'route',
            $this->dock_code ? null : 'dock',
        ]);

        return implode(' ', $parts) . ($semua ? ' (semua ' . implode(' & ', $semua) . ')' : '');
    }
}