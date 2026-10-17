<?php

namespace App\Http\Controllers;

use App\Models\Preparation;
use App\Models\PrepMonitoring;
use App\Models\Shipping;
use App\Models\ShippingMatrix;
use Illuminate\Http\Request;
use App\Models\Milkrun;
use App\Models\Delivery;
use App\Models\History;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrepMonitoringController extends Controller
{
    public function index(Request $request)
    {
        // Gak ada filter tanggal lagi: yang tampil cuma yang BELUM diambil milkrun.
        $rows = $this->pendingRows();

        $totalOnProgress = $rows->where('status', 'in_progress')->count();
        $totalClosed     = $rows->where('status', 'ok')->count();

        return view('prep-monitoring.index', compact('rows', 'totalOnProgress', 'totalClosed'));
    }

    /**
     * Semua row monitoring yang BELUM diambil milkrun, sudah ditempel status Delivery.
     * Row hilang dari tabel begitu SEMUA DN-nya sudah diambil, yaitu:
     * - masuk tabel milkruns (no_dns), ATAU
     * - sudah scan to delivery (ada di Delivery / History). Milkrun cuma dibikin
     *   kalau shipping-nya punya arrival, jadi banyak DN yang udah jalan tapi
     *   gak pernah tercatat di milkruns.
     */
    private function pendingRows()
    {
        $rows = PrepMonitoring::orderBy('created_at', 'desc')->get();

        $lookup = $this->buildDeliveryLookup(
            $rows->pluck('scanned_dns')->filter()->flatten()->unique()->values()->all()
        );

        $taken = $this->buildMilkrunDnSet($rows);
        foreach ($lookup as $dn => $info) {
            if ($info['scanned_at']) {
                $taken[$dn] = true;
            }
        }

        $rows = $rows->filter(function ($row) use ($taken) {
            $dns = $row->scanned_dns ?? [];
            if (empty($dns)) {
                return true;
            }
            foreach ($dns as $dn) {
                if (!isset($taken[$dn])) {
                    return true; // masih ada DN yang belum diambil milkrun
                }
            }
            return false;
        })->values();

        $this->attachDeliveryStatus($rows, $lookup);

        return $rows;
    }

    /**
     * Set [no_dn => true] dari DN yang sudah masuk tabel milkruns.
     * Dipersempit pakai delivery_date (mulai 3 hari sebelum group_date tertua)
     * biar gak scan seluruh tabel milkruns.
     */
    private function buildMilkrunDnSet($rows): array
    {
        $oldest = $rows->pluck('group_date')->filter()->min();
        if (!$oldest) {
            return [];
        }

        $set = [];
        $noDnsList = Milkrun::query()->toBase()
            ->where('delivery_date', '>=', Carbon::parse($oldest)->subDays(3)->format('Y-m-d'))
            ->whereNotNull('no_dns')
            ->pluck('no_dns');

        foreach ($noDnsList as $json) {
            foreach ((json_decode((string) $json, true) ?: []) as $dn) {
                $set[$dn] = true;
            }
        }

        return $set;
    }

    /**
     * Cek DN sebelum diproses.
     * - Kalau kombinasi (customers+cycle+dock+tanggal) SUDAH ada & masih in_progress -> langsung proses, ga perlu modal.
     * - Kalau BELUM ada -> munculin modal pilih PR, sekalian preview kbn, skid & shipping address (matrix).
     *
     * Dipakai baik oleh jalur Prep Monitoring (scan biasa, matrix) maupun jalur Preparation
     * (scan/panah Move to Shipping, manual). Untuk jalur Preparation, front-end cukup
     * mengabaikan `shipping_address` hasil matrix dan menampilkan dropdown Shipping manual.
     */
    public function checkDn(Request $request)
    {
        $noDn = $request->get('no_dn');

        if (!$noDn) {
            return response()->json(['success' => false, 'message' => 'No DN tidak boleh kosong'], 400);
        }

        $preparation = Preparation::where('no_dn', $noDn)->first();

        if (!$preparation) {
            return response()->json([
                'success' => false,
                'message' => 'Data preparation dengan DN ' . $noDn . ' tidak ditemukan',
            ]);
        }

        // DN yang udah pernah kescan sebelumnya (row manapun) ga boleh discan lagi
        if (PrepMonitoring::whereJsonContains('scanned_dns', $preparation->no_dn)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'DN ' . $preparation->no_dn . ' sudah pernah discan sebelumnya',
            ]);
        }

        $groupDate = $preparation->delivery_date->format('Y-m-d');

        $existing = PrepMonitoring::where('customers', $preparation->customers)
            ->where('cycle', $preparation->cycle)
            ->where('dock', $preparation->dock)
            ->where('group_date', $groupDate)
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'needs_pr_selection' => false,
                'data' => [
                    'no_dn'            => $preparation->no_dn,
                    'customers'        => $preparation->customers,
                    'cycle'            => $preparation->cycle,
                    'dock'             => $preparation->dock,
                    'route'            => $preparation->route,
                    'pr_number'        => $existing->pr_number,
                    'shipping_address' => $existing->shipping_address,
                    'progress'         => $existing->pr_count . '/' . $existing->skid,
                ],
            ]);
        }

        $skid = Preparation::where('customers', $preparation->customers)
            ->where('cycle', $preparation->cycle)
            ->where('dock', $preparation->dock)
            ->whereDate('delivery_date', $groupDate)
            ->count();

        $kbn = Preparation::where('customers', $preparation->customers)
            ->where('cycle', $preparation->cycle)
            ->where('dock', $preparation->dock)
            ->whereDate('delivery_date', $groupDate)
            ->sum('qty_kbn');

        $shippingAddress = $this->determineShippingAddress($preparation);

        return response()->json([
            'success' => true,
            'needs_pr_selection' => true,
            'data' => [
                'no_dn'            => $preparation->no_dn,
                'customers'        => $preparation->customers,
                'cycle'            => $preparation->cycle,
                'dock'             => $preparation->dock,
                'route'            => $preparation->route,
                'skid'             => max($skid, 1),
                'kbn'              => $kbn,
                'shipping_address' => $shippingAddress, // saran dari matrix (dipakai jalur monitoring; jalur preparation abaikan ini)
            ],
        ]);
    }

    /**
     * Proses scan (JALUR PREP MONITORING - TIDAK BERUBAH).
     * Bikin row baru (kalau pr_number dikirim, buat kombinasi baru)
     * atau nambah counter row existing yang masih in_progress.
     * Begitu pr_count == skid -> status jadi 'ok' & bulk-move ke Shipping (address dari Matrix).
     */
    public function scan(Request $request)
    {
        $validated = $request->validate([
            'no_dn'     => 'required|string|max:255',
            'pr_number' => 'nullable|integer|min:1|max:5', // 5 = AB (Abnormal)
        ]);

        DB::beginTransaction();

        try {
            $preparation = Preparation::where('no_dn', $validated['no_dn'])->first();

            if (!$preparation) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data preparation dengan DN ' . $validated['no_dn'] . ' tidak ditemukan',
                ], 404);
            }

            if (PrepMonitoring::whereJsonContains('scanned_dns', $preparation->no_dn)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'DN ' . $preparation->no_dn . ' sudah pernah discan sebelumnya',
                ], 422);
            }

            $groupDate = $preparation->delivery_date->format('Y-m-d');

            $monitoring = PrepMonitoring::where('customers', $preparation->customers)
                ->where('cycle', $preparation->cycle)
                ->where('dock', $preparation->dock)
                ->where('group_date', $groupDate)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();

            if (!$monitoring) {
                if (empty($validated['pr_number'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Pilih Preparation (PR) terlebih dahulu untuk kombinasi baru ini',
                    ], 422);
                }

                $skid = Preparation::where('customers', $preparation->customers)
                    ->where('cycle', $preparation->cycle)
                    ->where('dock', $preparation->dock)
                    ->whereDate('delivery_date', $groupDate)
                    ->count();

                $kbn = Preparation::where('customers', $preparation->customers)
                    ->where('cycle', $preparation->cycle)
                    ->where('dock', $preparation->dock)
                    ->whereDate('delivery_date', $groupDate)
                    ->sum('qty_kbn');

                $shippingAddress = $this->determineShippingAddress($preparation);

                $monitoring = PrepMonitoring::create([
                    'customers'        => $preparation->customers,
                    'cycle'            => $preparation->cycle,
                    'dock'             => $preparation->dock,
                    'route'            => $preparation->route,
                    'kbn'              => $kbn,
                    'skid'             => max($skid, 1),
                    'pr_number'        => $validated['pr_number'],
                    'pr_count'         => 1,
                    'shipping_address' => $shippingAddress,
                    'ship_count'       => 1,
                    'status'           => 'in_progress',
                    'scanned_dns'      => [$preparation->no_dn],
                    'group_date'       => $groupDate,
                    'start_prep_at'    => Carbon::now(),
                    'etd_time'         => $preparation->delivery_time,
                ]);
            } else {
                $scannedDns   = $monitoring->scanned_dns ?? [];
                $scannedDns[] = $preparation->no_dn;

                $routes = array_filter(array_unique(array_merge(
                    array_map('trim', explode(',', $monitoring->route ?? '')),
                    [$preparation->route]
                )));

                $monitoring->scanned_dns = $scannedDns;
                $monitoring->route       = implode(', ', $routes);
                $monitoring->pr_count   += 1;
                $monitoring->ship_count += 1;
                $monitoring->save();
            }

            $isComplete = $monitoring->pr_count >= $monitoring->skid;

            if ($isComplete && $monitoring->status !== 'ok') {
                $monitoring->status        = 'ok';
                $monitoring->start_ship_at = Carbon::now();
                $monitoring->save();

                $this->bulkMoveToShipping($monitoring);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isComplete
                    ? 'DN ' . $preparation->no_dn . ' discan. ' . $monitoring->pr_label . ' SELESAI (' . $monitoring->skid . '/' . $monitoring->skid . ') & sudah dipindah ke Shipping!'
                    : 'DN ' . $preparation->no_dn . ' discan. Progress ' . $monitoring->pr_label . ': ' . $monitoring->pr_count . '/' . $monitoring->skid,
                'data' => $monitoring->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Proses scan (JALUR PREPARATION - BARU).
     * DN langsung dipindah ke Shipping saat itu juga (gak nunggu skid penuh),
     * dengan shipping_address MANUAL (dari modal), bukan hasil Matrix.
     * Baris PrepMonitoring tetap dibuat/di-update supaya progress (mis. 1/4) ikut kelihatan
     * di Shipping Area Monitoring, dan otomatis ditutup ('ok') begitu skid penuh.
     */
    public function scanDirect(Request $request)
    {
        $validated = $request->validate([
            'no_dn'            => 'required|string|max:255',
            'pr_number'        => 'nullable|integer|min:1|max:5', // 5 = AB (Abnormal)
            'shipping_address' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $preparation = Preparation::where('no_dn', $validated['no_dn'])->first();

            if (!$preparation) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data preparation dengan DN ' . $validated['no_dn'] . ' tidak ditemukan',
                ], 404);
            }

            if (PrepMonitoring::whereJsonContains('scanned_dns', $preparation->no_dn)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'DN ' . $preparation->no_dn . ' sudah pernah discan sebelumnya',
                ], 422);
            }

            $groupDate = $preparation->delivery_date->format('Y-m-d');

            $monitoring = PrepMonitoring::where('customers', $preparation->customers)
                ->where('cycle', $preparation->cycle)
                ->where('dock', $preparation->dock)
                ->where('group_date', $groupDate)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();

            if (!$monitoring) {
                if (empty($validated['pr_number']) || empty($validated['shipping_address'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Pilih Preparation (PR) dan Shipping terlebih dahulu untuk kombinasi baru ini',
                    ], 422);
                }

                $skid = Preparation::where('customers', $preparation->customers)
                    ->where('cycle', $preparation->cycle)
                    ->where('dock', $preparation->dock)
                    ->whereDate('delivery_date', $groupDate)
                    ->count();

                $kbn = Preparation::where('customers', $preparation->customers)
                    ->where('cycle', $preparation->cycle)
                    ->where('dock', $preparation->dock)
                    ->whereDate('delivery_date', $groupDate)
                    ->sum('qty_kbn');

                $monitoring = PrepMonitoring::create([
                    'customers'        => $preparation->customers,
                    'cycle'            => $preparation->cycle,
                    'dock'             => $preparation->dock,
                    'route'            => $preparation->route,
                    'kbn'              => $kbn,
                    'skid'             => max($skid, 1),
                    'pr_number'        => $validated['pr_number'],
                    'pr_count'         => 1,
                    'shipping_address' => $validated['shipping_address'], // manual, bukan matrix
                    'ship_count'       => 1,
                    'status'           => 'in_progress',
                    'scanned_dns'      => [$preparation->no_dn],
                    'group_date'       => $groupDate,
                    'start_prep_at'    => Carbon::now(),
                    'etd_time'         => $preparation->delivery_time,
                ]);
            } else {
                $scannedDns   = $monitoring->scanned_dns ?? [];
                $scannedDns[] = $preparation->no_dn;

                $routes = array_filter(array_unique(array_merge(
                    array_map('trim', explode(',', $monitoring->route ?? '')),
                    [$preparation->route]
                )));

                $monitoring->scanned_dns = $scannedDns;
                $monitoring->route       = implode(', ', $routes);
                $monitoring->pr_count   += 1;
                $monitoring->ship_count += 1;
                $monitoring->save();
            }

            // Beda dari scan() biasa: DN ini langsung dipindah ke Shipping sekarang juga,
            // pakai shipping_address yang tersimpan di row (baru diisi manual atau reuse yang lama).
            $this->moveSingleToShipping($preparation, $monitoring->shipping_address);

            $isComplete = $monitoring->pr_count >= $monitoring->skid;

            if ($isComplete && $monitoring->status !== 'ok') {
                $monitoring->status        = 'ok';
                $monitoring->start_ship_at = Carbon::now();
                $monitoring->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isComplete
                    ? 'DN ' . $preparation->no_dn . ' dipindahkan ke ' . $monitoring->shipping_address . '. ' . $monitoring->pr_label . ' SELESAI (' . $monitoring->skid . '/' . $monitoring->skid . ')!'
                    : 'DN ' . $preparation->no_dn . ' dipindahkan ke ' . $monitoring->shipping_address . '. Progress ' . $monitoring->pr_label . ': ' . $monitoring->pr_count . '/' . $monitoring->skid,
                'data' => $monitoring->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pindahkan semua DN yang sudah terkumpul di grup ini ke tabel Shipping (JALUR MONITORING),
     * lalu hapus permanen dari preparations. Aman dipanggil walau sebagian DN sudah dipindah
     * duluan lewat jalur Preparation (moveSingleToShipping sudah cek duplikat).
     */
    private function bulkMoveToShipping(PrepMonitoring $monitoring): void
    {
        $noDns = $monitoring->scanned_dns ?? [];

        $preparations = Preparation::whereIn('no_dn', $noDns)->get();

        foreach ($preparations as $preparation) {
            $this->moveSingleToShipping($preparation, $monitoring->shipping_address);
        }
    }

    /**
     * Pindahkan SATU preparation ke Shipping. Dipakai baik oleh bulkMoveToShipping (jalur monitoring)
     * maupun scanDirect (jalur preparation). Aman dipanggil dobel (cek exists dulu).
     */
    private function moveSingleToShipping(Preparation $preparation, string $shippingAddress): void
    {
        if (Shipping::where('no_dn', $preparation->no_dn)->exists()) {
            return;
        }

        $movedBy = 'System';
        if (auth()->check()) {
            $user = auth()->user();
            $movedBy = $user->name ?? $user->email ?? 'User#' . $user->id;
        }

        $deliveryDateTime = Carbon::parse($preparation->delivery_date->format('Y-m-d') . ' ' . $preparation->delivery_time);
        $status = Carbon::now()->greaterThan($deliveryDateTime) ? 'delay' : 'normal';

        Shipping::create([
            'route'              => $preparation->route,
            'logistic_partners'  => $preparation->logistic_partners,
            'no_dn'              => $preparation->no_dn,
            'customers'          => $preparation->customers,
            'dock'               => $preparation->dock,
            'delivery_date'      => $preparation->delivery_date,
            'delivery_time'      => $preparation->delivery_time,
            'arrival'            => null,
            'cycle'              => $preparation->cycle,
            'address'            => $shippingAddress,
            'status'             => $status,
            'scan_to_shipping'   => Carbon::now(),
            'moved_by'           => $movedBy,
            'pulling_date'       => $preparation->pulling_date,
            'pulling_time'       => $preparation->pulling_time,
        ]);

        $preparation->forceDelete();
    }

    /**
     * Tentukan address Shipping otomatis dari Matrix Shipping (customers + dock + cycle).
     * Dipakai jalur monitoring. Default "Shipping 10" kalau kombinasi ga ketemu.
     */
    private function determineShippingAddress(Preparation $preparation): string
    {
        $customers = trim($preparation->customers);
        $dock      = trim($preparation->dock);
        $cycle     = trim((string) $preparation->cycle);

        $matrix = ShippingMatrix::whereRaw('LOWER(customers) = ?', [mb_strtolower($customers)])
            ->whereRaw('LOWER(dock) = ?', [mb_strtolower($dock)])
            ->where('cycle', $cycle)
            ->first();

        return $matrix ? $matrix->address : 'Shipping 10';
    }

    public function deleteAll()
    {
        try {
            $count = PrepMonitoring::count();
            PrepMonitoring::truncate();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$count} data monitoring",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** Lebih cepat dari jadwal lebih dari ini (menit) = Advance */
    private const DELIVERY_ADVANCE_MINUTES = 15;

    /**
     * Status kolom Delivery per row, TANPA tabel milkruns. Acuannya:
     * - Target = delivery_date + delivery_time dari DN.
     * - Scan   = scan_to_delivery (DN sudah dipindah ke Delivery / History).
     *
     * Sudah scan semua DN:
     *   scan > target                 -> delay
     *   scan < target - 15 menit      -> advance
     *   selain itu                    -> on_time
     * Belum scan (semua):
     *   sekarang > target             -> delay
     *   selain itu                    -> pending
     *
     * Hasil ditempel ke row: delivery_status, delivery_label, delivery_target.
     */
    private function attachDeliveryStatus($rows, ?array $lookup = null): void
    {
        if ($lookup === null) {
            $dns = $rows->pluck('scanned_dns')->filter()->flatten()->unique()->values()->all();
            $lookup = $this->buildDeliveryLookup($dns);
        }
        $now = Carbon::now();

        foreach ($rows as $row) {
            $targets = [];
            $scans = [];
            $allScanned = true;

            foreach (($row->scanned_dns ?? []) as $dn) {
                if (!isset($lookup[$dn])) {
                    continue;
                }
                if ($lookup[$dn]['target']) {
                    $targets[] = $lookup[$dn]['target'];
                }
                if ($lookup[$dn]['scanned_at']) {
                    $scans[] = $lookup[$dn]['scanned_at'];
                } else {
                    $allScanned = false;
                }
            }

            $target = empty($targets) ? null : min($targets);
            $status = 'pending';

            if ($target) {
                if ($allScanned && !empty($scans)) {
                    $scannedAt = max($scans);
                    if ($scannedAt->greaterThan($target)) {
                        $status = 'delay';
                    } elseif ($scannedAt->lessThan($target->copy()->subMinutes(self::DELIVERY_ADVANCE_MINUTES))) {
                        $status = 'advance';
                    } else {
                        $status = 'on_time';
                    }
                } elseif ($now->greaterThan($target)) {
                    $status = 'delay';
                }
            }

            $row->delivery_status = $status;
            $row->delivery_target = $target;
            $row->delivery_label = match ($status) {
                'delay'   => 'Delay',
                'advance' => 'Advance',
                'on_time' => 'On Time',
                default   => '-',
            };
        }
    }

    /**
     * Lookup [no_dn => ['target' => ?Carbon, 'scanned_at' => ?Carbon]] dari semua tahap DN.
     * History/Delivery = sudah scan to delivery; Shipping/Preparation = belum.
     */
    private function buildDeliveryLookup(array $dns): array
    {
        if (empty($dns)) {
            return [];
        }

        $map = [];
        $sources = [
            [History::class, true],
            [Delivery::class, true],
            [Shipping::class, false],
            [Preparation::class, false],
        ];

        foreach ($sources as [$model, $hasScan]) {
            $remaining = array_values(array_diff($dns, array_keys($map)));
            if (empty($remaining)) {
                break;
            }

            $columns = ['no_dn', 'delivery_date', 'delivery_time'];
            if ($hasScan) {
                $columns[] = 'scan_to_delivery';
            }

            foreach (array_chunk($remaining, 500) as $chunk) {
                // toBase(): skip model event (Shipping auto-save status di event retrieved)
                foreach ($model::query()->toBase()->whereIn('no_dn', $chunk)->get($columns) as $r) {
                    if (isset($map[$r->no_dn])) {
                        continue;
                    }

                    $target = null;
                    if ($r->delivery_date) {
                        try {
                            $target = Carbon::parse(substr((string) $r->delivery_date, 0, 10) . ' ' . ($r->delivery_time ?: '00:00:00'));
                        } catch (\Throwable $e) {
                            $target = null;
                        }
                    }

                    $map[$r->no_dn] = [
                        'target'     => $target,
                        'scanned_at' => ($hasScan && $r->scan_to_delivery) ? Carbon::parse($r->scan_to_delivery) : null,
                    ];
                }
            }
        }

        return $map;
    }

    public function andon()
    {
        $rows = $this->pendingRows();

        $totalOnProgress = $rows->where('status', 'in_progress')->count();
        $totalClosed     = $rows->where('status', 'ok')->count();

        $recentCompleted = PrepMonitoring::where('status', 'ok')
            ->orderBy('start_ship_at', 'desc')
            ->first();

        return view('andon.prep-monitoring', compact('rows', 'totalOnProgress', 'totalClosed', 'recentCompleted'));
    }
}