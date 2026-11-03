<?php

namespace App\Services;

use App\Models\Preparation;
use App\Models\PrepMonitoring;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Samakan dashboard (prep_monitorings) setelah satu DN diedit di halaman Preparation / Shipping.
 *
 * Aturan: satu grup dashboard = satu nilai. Kalau customer/route/dock/cycle/tanggal delivery
 * atau alamat shipping satu DN diubah, semua DN lain di grup yang sama ikut diubah
 * (di preparations & shippings), begitu juga baris dashboard-nya.
 */
class PrepMonitoringSync
{
    /** Field pembentuk grup yang ikut diterapkan ke seluruh DN grup. */
    private const GROUP_FIELDS = ['customers', 'route', 'dock', 'cycle', 'delivery_date'];

    /**
     * @param  string  $oldNoDn  No DN sebelum diedit
     * @param  array   $before   nilai sebelum diedit (delivery_date sudah format Y-m-d)
     * @param  array   $after    nilai hasil validasi form edit
     */
    public function afterDnEdited(string $oldNoDn, array $before, array $after): void
    {
        $monitoring = PrepMonitoring::whereJsonContains('scanned_dns', $oldNoDn)->first();
        if (!$monitoring) {
            return; // DN belum pernah discan → belum ada di dashboard
        }

        $newNoDn = (string) ($after['no_dn'] ?? $oldNoDn);
        $dns = $monitoring->scanned_dns ?? [];

        // No DN diganti → ganti juga di dashboard supaya tidak jadi DN "yatim"
        if ($newNoDn !== $oldNoDn) {
            $dns = array_map(fn ($dn) => $dn === $oldNoDn ? $newNoDn : $dn, $dns);
            $monitoring->scanned_dns = $dns;
        }

        $otherDns = array_values(array_diff($dns, [$newNoDn]));

        // Field grup yang berubah → terapkan ke DN lain di grup + baris dashboard
        $changed = [];
        foreach (self::GROUP_FIELDS as $field) {
            if (array_key_exists($field, $after) && $this->differs($before[$field] ?? null, $after[$field], $field)) {
                $changed[$field] = $field === 'delivery_date'
                    ? Carbon::parse($after[$field])->format('Y-m-d')
                    : $after[$field];
            }
        }

        if ($changed) {
            // Satu grup = DN yang sudah discan + DN lain di Preparation dengan kombinasi lama yang sama
            // (customers + route + dock + cycle + tanggal), termasuk yang belum discan.
            $groupDns = $otherDns;
            if (!empty($before['delivery_date'])) {
                $groupDns = array_values(array_unique(array_merge($groupDns, Preparation::query()
                    ->where('customers', $before['customers'] ?? null)
                    ->where('route', $before['route'] ?? null)
                    ->where('dock', $before['dock'] ?? null)
                    ->where('cycle', $before['cycle'] ?? null)
                    ->whereDate('delivery_date', $before['delivery_date'])
                    ->where('no_dn', '!=', $newNoDn)
                    ->pluck('no_dn')
                    ->all())));
            }

            if ($groupDns) {
                Preparation::whereIn('no_dn', $groupDns)->update($changed);
                DB::table('shippings')->whereIn('no_dn', $groupDns)->update($changed + ['updated_at' => now()]);
            }
        }

        foreach (['customers', 'route', 'dock', 'cycle'] as $field) {
            if (isset($changed[$field])) {
                $monitoring->{$field} = $changed[$field];
            }
        }
        if (isset($changed['delivery_date'])) {
            $monitoring->group_date = $changed['delivery_date'];
        }

        // Jam delivery = ETD di dashboard
        if (array_key_exists('delivery_time', $after) && $this->differs($before['delivery_time'] ?? null, $after['delivery_time'], 'delivery_time')) {
            $monitoring->etd_time = $after['delivery_time'];
        }

        // Alamat shipping (cuma dari halaman Shipping) → satu grup satu shipping
        if (array_key_exists('address', $after) && $this->differs($before['address'] ?? null, $after['address'], 'address')) {
            if ($otherDns) {
                DB::table('shippings')->whereIn('no_dn', $otherDns)->update(['address' => $after['address'], 'updated_at' => now()]);
            }
            $monitoring->shipping_address = $after['address'];
        }

        if ($monitoring->isDirty()) {
            $monitoring->save();
        }
    }

    private function differs($old, $new, string $field): bool
    {
        if ($field === 'delivery_time') {
            return substr((string) $old, 0, 5) !== substr((string) $new, 0, 5);
        }

        return trim((string) $old) !== trim((string) $new);
    }
}
