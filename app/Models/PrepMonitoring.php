<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepMonitoring extends Model
{
    // pr_number 1-4 = PR 1..4, 5 = AB (Abnormal)
    public const PR_ABNORMAL = 5;

    protected $fillable = [
        'customers', 'cycle', 'dock', 'route', 'kbn', 'skid',
        'pr_number', 'pr_count', 'shipping_address', 'ship_count',
        'status', 'scanned_dns', 'group_date', 'start_prep_at', 'start_ship_at', 'etd_time',
    ];

    protected $casts = [
        'scanned_dns'   => 'array',
        'group_date'    => 'date',
        'start_prep_at' => 'datetime',
        'start_ship_at' => 'datetime',
    ];

    /** Label PR untuk ditampilkan: "PR 1".."PR 4" atau "AB". */
    public function getPrLabelAttribute(): string
    {
        return (int) $this->pr_number === self::PR_ABNORMAL ? 'AB' : 'PR ' . $this->pr_number;
    }
}