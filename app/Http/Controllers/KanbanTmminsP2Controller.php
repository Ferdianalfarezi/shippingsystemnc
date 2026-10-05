<?php

namespace App\Http\Controllers;

use App\Models\KanbanTmminsP2;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Picqer\Barcode\BarcodeGeneratorPNG;

class KanbanTmminsP2Controller extends Controller
{
    /**
     * Mapping kolom baris D1 (TXT TMMIN P2 / *_KBN.txt), index mulai dari 0, dipisah TAB.
     * Index 0 = "D1".
     * Kalau ada kolom yang ternyata tertukar, cukup ubah angkanya di sini.
     */
    private const D1 = [
        'manifest_no'      => 1,
        'supplier_code'    => 2,
        'supplier_plant'   => 3,
        'customer_address' => 4,   // SM1 / AP2 / SB1 ... (boleh kosong) -> 5007-1-SM1
        'dock_code'        => 7,
        'order_no'         => 8,
        'part_no'          => 11,
        'part_address'     => 12,
        'unique_no'        => 15,
        'pcs'              => 16,
        'total_qty'        => 17,
        'kanban_count'     => 18,
        'supplier'         => 22,
        'part_name'        => 23,
        'departure_time'   => 25,
        'out_time'         => 26,
        'plo'              => 28,
        'route'            => 30,
        'cycle'            => 31,
        'arrival_time'     => 36,
        'conveyance_no'    => 62,   // O / H / A ... (boleh kosong)
        'dock'             => 83,
        'line_seq'         => 101,  // nomor baris D1, dipakai D2 untuk referensi
    ];

    /**
     * Mapping kolom baris D2 (1 baris D2 = 1 lembar kanban).
     * Contoh: D2  5260908771  9  1  K5ULO6  KBN5260908771      61503BZ2200000090001
     */
    private const D2 = [
        'manifest_no' => 1,
        'line_seq'    => 2,
        'kanban_seq'  => 3,
        'keterangan'  => 4,
        'qr_code'     => 5,
    ];

    private const D1_MIN_COLUMNS = 84;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $kanbantmmins = KanbanTmminsP2::all();
        $latestUploadInfo = KanbanTmminsP2::getLatestUploadInfo();

        return view('kanbantmminsp2.index', compact('kanbantmmins', 'latestUploadInfo'));
    }

    public function indexByDn()
    {
        $kanbantmmins = KanbanTmminsP2::all();
        $latestUploadInfo = KanbanTmminsP2::getLatestUploadInfo();

        return view('kanbantmminsp2.index-by-dn', compact('kanbantmmins', 'latestUploadInfo'));
    }

    /**
     * Delete all records by manifest_no (group delete)
     */
    public function destroyGroup($manifest_no)
    {
        try {
            $deletedCount = KanbanTmminsP2::where('manifest_no', $manifest_no)->count();
            KanbanTmminsP2::where('manifest_no', $manifest_no)->delete();

            Log::info("[P2] Successfully deleted {$deletedCount} records for manifest_no: {$manifest_no}");

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} data dengan Manifest No {$manifest_no} berhasil dihapus!"
            ]);
        } catch (\Exception $e) {
            Log::error('[P2] Error deleting kanban group: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import TXT file (format TMMIN P2 *_KBN.txt)
     *
     * Struktur file:
     *   H1 -> header
     *   D1 -> data part per manifest
     *   D2 -> 1 baris = 1 lembar kanban (keterangan + QR), selalu berada di bawah D1-nya
     *
     * Record dibuat per baris D2. D1 tanpa D2 (part tanpa order) otomatis tidak masuk.
     * File di-parse dulu sampai selesai, baru tabel di-truncate,
     * supaya data lama tidak hilang kalau file-nya salah format.
     */
    public function importTxt(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:txt|max:5000',
        ]);

        $uploadedBy = 'System';
        if (Auth::check()) {
            $user = Auth::user();
            $uploadedBy = $user->name ?? $user->email ?? 'System';
        }

        try {
            $lines = file($request->file('file')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $now = now();
            $records = [];
            $addressCache = [];

            $currentBase = null;      // data D1 yang sedang aktif
            $currentManifest = null;
            $currentLineSeq = null;
            $currentExpected = 0;     // jumlah kanban menurut D1
            $currentD2Count = 0;      // jumlah D2 yang benar-benar ada

            $countD1 = 0;
            $orphanD2 = 0;
            $mismatch = 0;

            // Cek jumlah D2 vs jumlah kanban di D1 sebelumnya (hanya untuk log)
            $checkCount = function () use (&$currentBase, &$currentExpected, &$currentD2Count, &$mismatch) {
                if ($currentBase !== null && $currentD2Count > 0 && $currentD2Count !== $currentExpected) {
                    $mismatch++;
                    Log::warning("[P2] Jumlah D2 tidak sama dengan jumlah kanban D1. Manifest {$currentBase['manifest_no']}, Part {$currentBase['part_no']}, D1={$currentExpected}, D2={$currentD2Count}");
                }
            };

            foreach ($lines as $line) {
                $c = explode("\t", rtrim($line, "\r\n"));
                $type = trim($c[0] ?? '');

                if ($type === 'D1') {
                    $checkCount();

                    if (count($c) < self::D1_MIN_COLUMNS || trim($c[self::D1['manifest_no']] ?? '') === '') {
                        $currentBase = null;
                        continue;
                    }

                    $countD1++;
                    $currentBase = $this->buildBaseFromD1($c, $addressCache, $now, $uploadedBy);
                    $currentManifest = $currentBase['manifest_no'];
                    $currentLineSeq = trim($c[self::D1['line_seq']] ?? '');
                    $currentExpected = (int) trim($c[self::D1['kanban_count']] ?? 0);
                    $currentD2Count = 0;

                } elseif ($type === 'D2') {
                    $d2Manifest = trim($c[self::D2['manifest_no']] ?? '');
                    $d2LineSeq = trim($c[self::D2['line_seq']] ?? '');

                    // D2 harus milik D1 di atasnya (manifest sama, nomor baris sama kalau ada)
                    $belongs = $currentBase !== null
                        && $d2Manifest === $currentManifest
                        && ($currentLineSeq === '' || $d2LineSeq === '' || $d2LineSeq === $currentLineSeq);

                    if (!$belongs) {
                        $orphanD2++;
                        continue;
                    }

                    $currentD2Count++;

                    $keterangan = trim($c[self::D2['keterangan']] ?? '');
                    $qrCode = rtrim($c[self::D2['qr_code']] ?? '');

                    $records[] = array_merge($currentBase, [
                        'keterangan' => $keterangan !== '' ? $keterangan : null,
                        'qr_code'    => $qrCode !== '' ? $qrCode : 'NO-QR',
                    ]);
                }
                // H1 / baris lain diabaikan
            }

            $checkCount();

            if (empty($records)) {
                Log::warning("[P2] Import: tidak ada kanban valid. D1={$countD1}, orphanD2={$orphanD2}");

                return $this->importResponse($request, false, 'Import Gagal!',
                    'Tidak ada data kanban (baris D2) yang bisa diimport. Pastikan file adalah TXT TMMIN P2 (*_KBN.txt). Data lama tidak dihapus.');
            }

            // Parsing sukses -> baru hapus data lama & insert
            $deletedCount = KanbanTmminsP2::count();
            KanbanTmminsP2::truncate();

            foreach (array_chunk($records, 200) as $chunk) {
                KanbanTmminsP2::insert($chunk);
            }

            $importedCount = count($records);
            Log::info("[P2] Import selesai. deleted={$deletedCount}, imported={$importedCount}, D1={$countD1}, orphanD2={$orphanD2}, mismatch={$mismatch}");

            $text = "{$deletedCount} data lama dihapus dan {$importedCount} kanban baru berhasil diimport!";
            if ($mismatch > 0 || $orphanD2 > 0) {
                $text .= " (Perhatian: {$mismatch} part jumlah kanban tidak sesuai, {$orphanD2} baris D2 tanpa D1 — cek log)";
            }

            return $this->importResponse($request, true, 'Import Berhasil!', $text);

        } catch (\Exception $e) {
            Log::error("[P2] Import failed: " . $e->getMessage());
            Log::error("[P2] Stack trace: " . $e->getTraceAsString());

            return $this->importResponse($request, false, 'Import Gagal!', 'Import gagal: ' . $e->getMessage());
        }
    }

    /**
     * Susun data dasar dari 1 baris D1 (dipakai bersama oleh semua D2 di bawahnya)
     */
    private function buildBaseFromD1(array $c, array &$addressCache, $now, $uploadedBy)
    {
        $rawPartNo = strtoupper(preg_replace('/\s+/', '', $c[self::D1['part_no']] ?? ''));
        $partNo    = $this->formatPartNo($rawPartNo);
        $uniqueNo  = trim($c[self::D1['unique_no']] ?? '');
        $pcs       = (int) trim($c[self::D1['pcs']] ?? 0);

        // Lookup address (cache per part_no supaya tidak query berulang)
        if (!isset($addressCache[$partNo])) {
            $addressCache[$partNo] = $this->getAddressFromTable($partNo);
        }
        $addressData = $addressCache[$partNo];
        $finalPcs = ($addressData['qty'] > 0) ? $addressData['qty'] : $pcs;

        $plo = trim($c[self::D1['plo']] ?? '');
        $plo = preg_match('/^\d{1,2}$/', $plo) ? (int) $plo : null;

        $conveyance = trim($c[self::D1['conveyance_no']] ?? '');

        return [
            'manifest_no'      => trim($c[self::D1['manifest_no']] ?? ''),
            'keterangan'       => null,
            'qr_code'          => null,
            'departure_time'   => $this->parseDateTime($c[self::D1['departure_time']] ?? null),
            'arrival_time'     => $this->parseDateTime($c[self::D1['arrival_time']] ?? null),
            'out_time'         => $this->parseDateTime($c[self::D1['out_time']] ?? null),
            'dock_code'        => trim($c[self::D1['dock_code']] ?? ''),
            'part_address'     => $this->formatPartAddress($c[self::D1['part_address']] ?? ''),
            'part_no'          => $partNo,
            'order_no'         => trim($c[self::D1['order_no']] ?? ''),
            'unique_no'        => $uniqueNo !== '' ? $uniqueNo : 'UNKNOWN',
            'pcs'              => $finalPcs,
            'route'            => trim($c[self::D1['route']] ?? ''),
            'part_name'        => preg_replace('/\s+/', ' ', trim($c[self::D1['part_name']] ?? '')),
            'supplier'         => preg_replace('/\s+/', ' ', trim($c[self::D1['supplier']] ?? '')),
            'supplier_code'    => trim($c[self::D1['supplier_code']] ?? '') . '-' . trim($c[self::D1['supplier_plant']] ?? '1'),
            'customer_address' => strtoupper(trim($c[self::D1['customer_address']] ?? '')),
            'dock'             => trim($c[self::D1['dock']] ?? ''),
            'cycle'            => trim($c[self::D1['cycle']] ?? ''),
            'address'          => strtoupper($addressData['address']),
            'plo'              => $plo,
            'conveyance_no'    => $conveyance !== '' ? substr($conveyance, 0, 25) : null,
            'last_upload_at'   => $now,
            'uploaded_by'      => $uploadedBy,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    /**
     * Response import: JSON untuk AJAX, redirect + sweet_alert untuk form biasa.
     */
    private function importResponse(Request $request, bool $success, string $title, string $text)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'title'   => $title,
                'message' => $text,
            ], $success ? 200 : 422);
        }

        return redirect()->route('kanbantmminsp2.index')->with([
            'sweet_alert' => [
                'type' => $success ? 'success' : 'error',
                'title' => $title,
                'text' => $text,
                'showConfirmButton' => true,
                'timer' => $success ? 5000 : null,
            ]
        ]);
    }

    /**
     * 538210D01000 -> 53821-0D010-00
     */
    private function formatPartNo($raw)
    {
        if (strlen($raw) === 12) {
            return substr($raw, 0, 5) . '-' . substr($raw, 5, 5) . '-' . substr($raw, 10, 2);
        }
        return $raw !== '' ? $raw : 'UNKNOWN';
    }

    /**
     * "DST2 -M107" / "BUILD- OUT" -> "DST2 - M107" / "BUILD - OUT"
     */
    private function formatPartAddress($value)
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));
        if ($value === '') {
            return 'UNKNOWN';
        }
        return preg_replace('/\s*-\s*/', ' - ', $value);
    }

    /**
     * "2026-10-01 14:43:00.000" -> "2026-10-01 14:43:00"
     * Kosong / 1900-01-01 -> null
     */
    private function parseDateTime($value)
    {
        $clean = trim(preg_replace('/\.\d{1,6}$/', '', (string) $value));
        if ($clean === '') {
            return null;
        }

        $ts = strtotime($clean);
        if ($ts === false || $ts < strtotime('1990-01-01')) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Get address from addresses table
     */
    private function getAddressFromTable($part_no)
    {
        $address = 'No Address';
        $qty = 0;

        if ($part_no !== 'UNKNOWN') {
            try {
                $standardizedPartNo = $this->standardizePartNo($part_no);
                $cleanPartNo = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $standardizedPartNo));

                $corePartNo = null;
                if (strpos($cleanPartNo, '-') !== false) {
                    $parts = explode('-', $cleanPartNo);
                    if (count($parts) >= 2 && strlen($parts[0]) == 5 && strlen($parts[1]) >= 5) {
                        $corePartNo = $parts[0] . '-' . substr($parts[1], 0, 5);
                    }
                } else if (strlen($cleanPartNo) >= 10) {
                    $corePartNo = substr($cleanPartNo, 0, 5) . '-' . substr($cleanPartNo, 5, 5);
                }

                $addressRecord = Address::where('part_no', $standardizedPartNo)->first();

                if ($addressRecord) {
                    $address = $addressRecord->rack_no;
                    $qty = $addressRecord->qty_kbn ?? 0;
                } else if ($corePartNo) {
                    $addressRecord = Address::where('part_no', $corePartNo)->first();

                    if (!$addressRecord) {
                        $addressRecord = Address::where('part_no', 'LIKE', $corePartNo . '%')->first();
                    }

                    if ($addressRecord) {
                        $address = $addressRecord->rack_no;
                        $qty = $addressRecord->qty_kbn ?? 0;
                    }
                }

                if ($address === 'No Address') {
                    $noHyphenPartNo = str_replace('-', '', $standardizedPartNo);
                    $addressRecord = Address::whereRaw("REPLACE(part_no, '-', '') = ?", [$noHyphenPartNo])->first();

                    if ($addressRecord) {
                        $address = $addressRecord->rack_no;
                        $qty = $addressRecord->qty_kbn ?? 0;
                    }
                }

            } catch (\Exception $e) {
                Log::error("[P2] Error finding address for part_no " . $part_no . ": " . $e->getMessage());
            }
        }

        return ['address' => $address ?? 'No Address', 'qty' => $qty];
    }

    /**
     * Standardize part_no format
     */
    private function standardizePartNo($partNo)
    {
        $partNo = preg_replace('/[^\w\-]/', '', $partNo);
        $partNo = strtoupper($partNo);

        if (!str_contains($partNo, '-') && strlen($partNo) === 12) {
            if (preg_match('/^(\d{5})(\w{5})(\d{2})$/', $partNo, $matches)) {
                $partNo = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            } else if (preg_match('/^(\d{5})([A-Z]{2})(\d{5})$/', $partNo, $matches)) {
                $partNo = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            }
        }

        $partNo = preg_replace('/\-{2,}/', '-', $partNo);
        $partNo = trim($partNo, '-');

        return $partNo;
    }

    /**
     * Delete a record
     */
    public function destroy($id)
    {
        try {
            $kanbantmmins = KanbanTmminsP2::findOrFail($id);
            $kanbantmmins->delete();

            return redirect()->route('kanbantmminsp2.index')
                ->with('success', 'Kanban berhasil dihapus!');
        } catch (\Exception $e) {
            Log::error('[P2] Error deleting kanban: ' . $e->getMessage());

            return redirect()->route('kanbantmminsp2.index')
                ->with('error', 'Terjadi kesalahan saat menghapus: ' . $e->getMessage());
        }
    }

    /**
     * Print single record
     */
    public function print($id)
    {
        $note = KanbanTmminsP2::findOrFail($id);
        $itemsToProcess = collect([$note]);
        return view('kanbantmminsp2.print', compact('itemsToProcess', 'note'));
    }

    /**
     * Sorting key: DESC manifest, DESC address
     */
    private function sortKey($item)
    {
        $manifestNo = $item->manifest_no ?? '';
        $address = $item->address ?? 'No Address';

        $reversedManifestNo = str_pad((999999999999 - (int) $manifestNo), 12, '0', STR_PAD_LEFT);

        $reversedAddress = '';
        for ($i = 0; $i < strlen($address); $i++) {
            $char = strtolower($address[$i]);
            if (ctype_alpha($char)) {
                $reversedAddress .= chr(ord('z') - ord($char) + ord('a'));
            } elseif (ctype_digit($char)) {
                $reversedAddress .= (string) (9 - (int) $char);
            } else {
                $reversedAddress .= $char;
            }
        }

        return $reversedManifestNo . '|' . $reversedAddress;
    }

    /**
     * Pisah Plant 1 / Plant 2, urutkan, generate barcode -> data siap untuk view printall
     */
    private function buildPrintData($allData, $plantFilter)
    {
        $generator = new BarcodeGeneratorPNG();
        $sortFunction = fn($item) => $this->sortKey($item);

        // Plant 1: address NOT starting with 'K'
        $plant1Items = $allData->filter(function ($item) {
            return !str_starts_with(strtoupper(trim($item->address ?? '')), 'K');
        })->sortBy($sortFunction)->values();

        // Plant 2: address starting with 'K'
        $plant2Items = $allData->filter(function ($item) {
            return str_starts_with(strtoupper(trim($item->address ?? '')), 'K');
        })->sortBy($sortFunction)->values();

        $orderedItems = collect();
        $separatorAfterIndex = -1;
        $showPlantSeparator = false;

        if ($plantFilter === '1') {
            $orderedItems = $plant1Items;
        } elseif ($plantFilter === '2') {
            $orderedItems = $plant2Items;
        } else {
            $orderedItems = $orderedItems->merge($plant1Items);

            if ($plant1Items->count() > 0 && $plant2Items->count() > 0) {
                $showPlantSeparator = true;
                $separatorAfterIndex = $plant1Items->count() - 1;
            }

            $orderedItems = $orderedItems->merge($plant2Items);
        }

        $orderedItems = $orderedItems->values();

        foreach ($orderedItems as $item) {
            try {
                $addressToUse = $item->address;
                if (empty($addressToUse) || $addressToUse === 'No Address') {
                    $addressToUse = $item->part_address ?? $item->dock_code . '-' . $item->unique_no;
                }

                $item->barcode_image = base64_encode($generator->getBarcode($addressToUse, $generator::TYPE_CODE_128));
                $item->barcode_text = $addressToUse;
            } catch (\Exception $e) {
                $item->barcode_image = null;
                $item->barcode_text = $item->address ?? $item->dock_code . '-' . $item->id;
            }
        }

        $groupedData = collect();
        foreach ($orderedItems->groupBy('dock_code') as $dockCode => $items) {
            $groupedData->put($dockCode, $items->values());
        }

        return compact(
            'orderedItems',
            'groupedData',
            'showPlantSeparator',
            'separatorAfterIndex',
            'plant1Items',
            'plant2Items'
        );
    }

    /**
     * Print all records by dock codes (Plant 1 dulu, lalu Plant 2)
     */
    public function printAll(Request $request)
    {
        try {
            $selectedDockCodes = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('dock_codes')))));

            if (empty($selectedDockCodes)) {
                return redirect()->back()->with('error', 'No dock codes provided');
            }

            $plantFilter = $request->input('plant', 'all');

            $allData = KanbanTmminsP2::whereIn('dock_code', $selectedDockCodes)->get();

            if ($allData->isEmpty()) {
                $allData = KanbanTmminsP2::where(function ($query) use ($selectedDockCodes) {
                    foreach ($selectedDockCodes as $dockCode) {
                        $query->orWhereRaw('LOWER(TRIM(dock_code)) = ?', [strtolower(trim($dockCode))]);
                    }
                })->get();
            }

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected dock codes');
            }

            $data = $this->buildPrintData($allData, $plantFilter);

            if ($data['orderedItems']->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected plant filter');
            }

            return view('kanbantmminsp2.printall', array_merge($data, compact('selectedDockCodes', 'plantFilter')));

        } catch (\Exception $e) {
            Log::error('[P2] Print All Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Print group by manifest_no
     */
    public function printGroup(Request $request)
    {
        $manifestNo = $request->input('manifest_no');

        if (empty($manifestNo)) {
            return redirect()->back()->with('error', 'Manifest number is required');
        }

        $kanbantmmins = KanbanTmminsP2::where('manifest_no', $manifestNo)->get();

        if ($kanbantmmins->isEmpty()) {
            return redirect()->back()->with('error', 'No records found');
        }

        return view('kanbantmminsp2.print', compact('kanbantmmins'));
    }

    public function printSelected(Request $request)
    {
        try {
            $selectedIds = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('ids')))));

            if (empty($selectedIds)) {
                return redirect()->back()->with('error', 'No IDs provided');
            }

            $plantFilter = $request->input('plant', 'all');

            $allData = KanbanTmminsP2::whereIn('id', $selectedIds)->get();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected IDs');
            }

            $data = $this->buildPrintData($allData, $plantFilter);

            if ($data['orderedItems']->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected plant filter');
            }

            $selectedDockCodes = $data['orderedItems']->pluck('dock_code')->unique()->toArray();

            return view('kanbantmminsp2.printall', array_merge($data, compact('selectedDockCodes', 'plantFilter')));

        } catch (\Exception $e) {
            Log::error('[P2] Print Selected Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Hitung jumlah Plant 1 / Plant 2
     */
    private function countPlants($items)
    {
        $plant2 = $items->filter(fn($item) => str_starts_with(strtoupper(trim($item->address ?? '')), 'K'))->count();

        return [
            'plant1' => $items->count() - $plant2,
            'plant2' => $plant2,
        ];
    }

    /**
     * Get plant counts for selected IDs (for Print Selected modal)
     */
    public function getPlantCountsByIds(Request $request)
    {
        try {
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json(['plant1' => 0, 'plant2' => 0]);
            }

            return response()->json($this->countPlants(KanbanTmminsP2::whereIn('id', $ids)->get(['id', 'address'])));

        } catch (\Exception $e) {
            return response()->json(['plant1' => 0, 'plant2' => 0]);
        }
    }

    public function getPlantCounts(Request $request)
    {
        try {
            $dockCodes = $request->input('dock_codes', []);

            if (empty($dockCodes)) {
                return response()->json(['plant1' => 0, 'plant2' => 0]);
            }

            return response()->json($this->countPlants(KanbanTmminsP2::whereIn('dock_code', $dockCodes)->get(['id', 'address'])));

        } catch (\Exception $e) {
            return response()->json(['plant1' => 0, 'plant2' => 0]);
        }
    }
}