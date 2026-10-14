<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbntmminSchedule extends Model
{
    protected $table = 'kbntmmin_schedule';

    protected $fillable = [
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
}