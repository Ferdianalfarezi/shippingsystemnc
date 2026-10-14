<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbntmminPrintLog extends Model
{
    protected $table = 'kbntmmin_print_logs';

    protected $fillable = [
        'cycle',
        'manifest_no',
        'printer_name',
        'status',
        'error_message',
        'printed_at',
        'printed_by',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];
}