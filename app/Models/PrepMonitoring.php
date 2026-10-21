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

    /**
     * Satu grup preparation = customers + route + dock + cycle + tanggal delivery.
     */
    public static function groupPreparations(Preparation $preparation)
    {
        return Preparation::where('customers', $preparation->customers)
            ->where('route', $preparation->route)
            ->where('dock', $preparation->dock)
            ->where('cycle', $preparation->cycle)
            ->whereDate('delivery_date', $preparation->delivery_date->format('Y-m-d'));
    }

    /** Row in_progress untuk grup DN ini (kunci sama dengan groupPreparations). */
    public static function findInProgress(Preparation $preparation, bool $lock = false): ?self
    {
        $query = self::where('customers', $preparation->customers)
            ->where('route', $preparation->route)
            ->where('dock', $preparation->dock)
            ->where('cycle', $preparation->cycle)
            ->where('group_date', $preparation->delivery_date->format('Y-m-d'))
            ->where('status', 'in_progress');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Hitung ulang skid (& kbn) grup in_progress:
     * skid = DN yang sudah discan + DN grup yang masih di Preparation & belum discan.
     * Membetulkan row yang skid-nya dihitung sebelum route ikut jadi kunci grup (mis. 4/9 → 4/4).
     */
    public function syncSkid(): void
    {
        if ($this->status !== 'in_progress' || !$this->group_date) {
            return;
        }

        $group = Preparation::where('customers', $this->customers)
            ->where('route', $this->route)
            ->where('dock', $this->dock)
            ->where('cycle', $this->cycle)
            ->whereDate('delivery_date', $this->group_date->format('Y-m-d'));

        $scanned = $this->scanned_dns ?? [];
        $remaining = (clone $group)->whereNotIn('no_dn', $scanned)->count();
        $this->skid = max(count($scanned) + $remaining, 1);

        // kbn cuma bisa dihitung ulang kalau semua DN yang sudah discan masih ada di Preparation
        if ((clone $group)->whereIn('no_dn', $scanned)->count() === count($scanned)) {
            $this->kbn = (clone $group)->sum('qty_kbn');
        }

        if ($this->isDirty(['skid', 'kbn'])) {
            $this->save();
        }
    }
}