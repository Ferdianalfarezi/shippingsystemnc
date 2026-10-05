<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanTmminsP2 extends Model
{
    protected $table = 'kanban_tmmins_p2';

    protected $fillable = [
        'qr_code',
        'manifest_no',
        'keterangan',
        'departure_time',
        'arrival_time',
        'dock_code',
        'part_address',
        'part_no',
        'order_no',
        'unique_no',
        'pcs',
        'route',
        'part_name',
        'supplier',
        'supplier_code',
        'customer_address',
        'dock',
        'cycle',
        'address',
        'plo',
        'out_time',
        'conveyance_no',
        'last_upload_at',
        'uploaded_by',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time'   => 'datetime',
        'out_time'       => 'datetime',
        'last_upload_at' => 'datetime',
    ];

    public static function getLatestUploadInfo()
    {
        return self::select('last_upload_at', 'uploaded_by')
            ->whereNotNull('last_upload_at')
            ->orderByDesc('last_upload_at')
            ->first();
    }
}