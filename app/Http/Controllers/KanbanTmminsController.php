<?php

namespace App\Http\Controllers;

use App\Models\KanbanTmmins;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Picqer\Barcode\BarcodeGeneratorPNG;

class KanbanTmminsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $kanbantmmins = KanbanTmmins::all();
        $latestUploadInfo = KanbanTmmins::getLatestUploadInfo();
        
        return view('kanbantmmins.index', compact('kanbantmmins', 'latestUploadInfo'));
    }

    public function indexByDn()
    {
        $kanbantmmins = KanbanTmmins::all();
        $latestUploadInfo = KanbanTmmins::getLatestUploadInfo();
        
        return view('kanbantmmins.index-by-dn', compact('kanbantmmins', 'latestUploadInfo'));
    }

    /**
     * Delete all records by manifest_no (group delete)
     */
    public function destroyGroup($manifest_no)
    {
        try {
            $deletedCount = KanbanTmmins::where('manifest_no', $manifest_no)->count();
            KanbanTmmins::where('manifest_no', $manifest_no)->delete();
            
            Log::info("Successfully deleted {$deletedCount} records for manifest_no: {$manifest_no}");
            
            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} data dengan Manifest No {$manifest_no} berhasil dihapus!"
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting kanban group: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import TXT file
     */
    public function importTxt(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:txt|max:5000',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $uploadedBy = 'System';
        if (Auth::check()) {
            $user = Auth::user();
            $uploadedBy = $user->name ?? $user->email ?? 'System';
        }

        try {
            Log::info("Starting data cleanup - deleting all existing KanbanTmmins records");
            $deletedCount = KanbanTmmins::count();
            KanbanTmmins::truncate();
            Log::info("Successfully deleted {$deletedCount} existing records");
            
            $importedCount = 0;
            $lastValidRoute = null;
            $enableDetailedDebug = true;

            // First pass: identify which D1 entries have D2 entries
            $d1WithD2 = [];
            $currentD1Index = null;
            $currentManifestNo = null;

            foreach ($lines as $index => $line) {
                $columns = preg_split('/\t+|\s{4,}/', trim($line));
                if (isset($columns[0])) {
                    if ($columns[0] === 'D1') {
                        $currentD1Index = $index;
                        $currentManifestNo = $columns[1] ?? null;
                    } elseif ($columns[0] === 'D2' && $currentManifestNo !== null) {
                        $manifestNoD2 = $columns[1] ?? null;
                        if ($manifestNoD2 === $currentManifestNo) {
                            $d1WithD2[$currentD1Index] = $currentManifestNo;
                        }
                    }
                }
            }

            // Group data into logical units
            $dataGroups = [];
            $currentD1 = null;
            $currentManifestNo = null;
            $currentD1Index = null;

            foreach ($lines as $index => $line) {
                $columns = preg_split('/\t+/', $line);
                if (isset($columns[0])) {
                    if ($columns[0] === 'D1') {
                        $currentD1Index = $index;
                        $currentD1 = $columns;
                        $currentManifestNo = $columns[1] ?? null;
                        
                        if (isset($d1WithD2[$currentD1Index])) {
                            if (!isset($dataGroups[$currentManifestNo])) {
                                $dataGroups[$currentManifestNo] = [];
                            }
                            $dataGroups[$currentManifestNo][] = [
                                'D1' => $currentD1,
                                'D2' => []
                            ];
                        }
                    } elseif ($columns[0] === 'D2' && $currentManifestNo !== null) {
                        $manifestNoD2 = $columns[1] ?? null;
                        
                        if ($manifestNoD2 === $currentManifestNo && isset($d1WithD2[$currentD1Index])) {
                            if (!empty($dataGroups[$currentManifestNo])) {
                                $lastGroupIndex = count($dataGroups[$currentManifestNo]) - 1;
                                $dataGroups[$currentManifestNo][$lastGroupIndex]['D2'][] = $columns;
                            }
                        }
                    }
                }
            }

            // Process each group
            foreach ($dataGroups as $manifestNo => $groups) {
                foreach ($groups as $group) {
                    $d1 = $group['D1'];
                    $d2Entries = $group['D2'];
                    
                    if (empty($d2Entries)) {
                        Log::warning("D1 entry for manifest_no: " . $manifestNo . " has no D2 entries, skipping...");
                        continue;
                    }

                    // Extract part_address
                    $part_address = $this->extractPartAddress($d1);
                    
                    // Extract order_no
                    $order_no = $this->extractOrderNo($d1, $d2Entries, $manifestNo);
                    
                    // Extract part_no
                    $part_no = $this->extractPartNo($d1, $d2Entries);
                    
                    // Process each D2 entry
                    foreach ($d2Entries as $d2) {
                        $keterangan = isset($d2[4]) ? trim($d2[4]) : 'UNKNOWN';
                        $qr_code = isset($d2[5]) ? $d2[5] : 'UNKNOWN';

                        $supplier = $this->extractSupplier($d1);
                        $supplier_code = $this->extractSupplierCode($d1, $manifestNo);
                        $customer_address = $this->extractCustomerAddress($d1);
                        $dockCode = $this->extractDockCode($d1);

                        $times = $this->extractTimes($d1, $dockCode);
                        $departure_time = $times['departure_time'];
                        $arrival_time = $times['arrival_time'];
                        $out_time = $times['out_time'];

                        $route = $this->extractRoute($d1, $lastValidRoute);
                        if ($route !== 'UNKNOWN') {
                            $lastValidRoute = $route;
                        }

                        $pcs = $this->extractPcs($d1);
                        $part_name = $this->extractPartName($d1);
                        $dock = $this->extractDock($d1, $dockCode);
                        $unique_no = $this->extractUniqueNo($d1, $manifestNo);
                        $plo = $this->extractPlo($d1, $dockCode, $route);
                        
                        $addressData = $this->getAddressFromTable($part_no);
                        $address = $addressData['address'];
                        $finalPcs = ($addressData['qty'] > 0) ? $addressData['qty'] : $pcs;

                        $cycle = $this->extractCycle($d1, $route);
                        $conveyance_no = $this->extractConveyanceNo($d1);

                        Log::info("Creating record - Part No: {$part_no}, Address: {$address}");
                        
                        KanbanTmmins::create([
                            'qr_code' => $qr_code,
                            'manifest_no' => $manifestNo,
                            'keterangan' => $keterangan,
                            'departure_time' => $departure_time,
                            'arrival_time' => $arrival_time,
                            'dock_code' => $dockCode,
                            'part_address' => $part_address,
                            'part_no' => $part_no,
                            'order_no' => $order_no,
                            'unique_no' => $unique_no,
                            'pcs' => $finalPcs,
                            'route' => $route,
                            'part_name' => $part_name,
                            'supplier' => $supplier,
                            'supplier_code' => $supplier_code,
                            'customer_address' => $customer_address,
                            'dock' => $dock,
                            'cycle' => $cycle,
                            'address' => strtoupper($address),
                            'plo' => $plo,
                            'out_time' => $out_time,
                            'conveyance_no' => $conveyance_no,
                            'last_upload_at' => now(),
                            'uploaded_by' => $uploadedBy
                        ]);

                        $importedCount++;
                    }
                }
            }

            DB::commit();
            
            Log::info("Import completed successfully. Total imported: {$importedCount} records");
            
            return redirect()->route('kanbantmmins.index')->with([
                'sweet_alert' => [
                    'type' => 'success',
                    'title' => 'Import Berhasil!',
                    'text' => "{$deletedCount} data lama dihapus dan {$importedCount} data baru berhasil diimport!",
                    'showConfirmButton' => true,
                    'timer' => 5000
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Import failed: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return redirect()->route('kanbantmmins.index')->with([
                'sweet_alert' => [
                    'type' => 'error',
                    'title' => 'Import Gagal!',
                    'text' => 'Import gagal: ' . $e->getMessage(),
                    'showConfirmButton' => true
                ]
            ]);
        }
    }

    /**
     * Cari part_address DAN index kolom tempat nilainya ketemu di $d1.
     * Logic pencarian (4 tahap fallback) SAMA PERSIS dengan versi sebelumnya —
     * cuma sekarang index-nya ikut dikembalikan, supaya bisa dipakai lagi oleh
     * extractUniqueNo() untuk ambil "3 kolom setelah part_address".
     */
    private function findPartAddress($d1)
    {
        $part_address = 'UNKNOWN';
        $foundIndex = null;

        foreach ($d1 as $index => $value) {
            if (preg_match('/^[A-Z0-9]{12}$/', trim($value))) {
                if (isset($d1[$index + 1])) {
                    $candidate = trim($d1[$index + 1]);
                    if (strpos($candidate, '-') !== false) {
                        $part_address = preg_replace('/\s*-\s*/', ' - ', $candidate);
                        $foundIndex = $index + 1;
                        break;
                    }
                }
            }
        }

        if ($part_address === 'UNKNOWN') {
            $addressPatterns = ['/[A-Z][0-9]-[0-9]+/', '/[A-Z][0-9]-[0-9]+-[A-Z]/', '/UB[0-9][\s\-]+[0-9]+/'];
            foreach ($d1 as $index => $value) {
                $cleanValue = trim($value);
                foreach ($addressPatterns as $pattern) {
                    if (preg_match($pattern, $cleanValue)) {
                        $part_address = $cleanValue;
                        $foundIndex = $index;
                        break 2;
                    }
                }
            }
        }

        if ($part_address === 'UNKNOWN') {
            foreach ($d1 as $index => $value) {
                if (trim($value) === 'UB1') {
                    for ($i = 1; $i <= 3; $i++) {
                        if (isset($d1[$index + $i]) && strpos(trim($d1[$index + $i]), '-') !== false) {
                            $part_address = trim($d1[$index + $i]);
                            $foundIndex = $index + $i;
                            break 2;
                        }
                    }
                }
            }
        }

        if ($part_address === 'UNKNOWN') {
            $addressColumns = [11, 12, 13];
            foreach ($addressColumns as $col) {
                if (isset($d1[$col]) && strpos(trim($d1[$col]), '-') !== false) {
                    $part_address = trim($d1[$col]);
                    $foundIndex = $col;
                    break;
                }
            }
        }

        return ['value' => $part_address, 'index' => $foundIndex];
    }

    /**
     * Extract part_address from D1
     */
    private function extractPartAddress($d1)
    {
        return $this->findPartAddress($d1)['value'];
    }

    /**
     * Extract order_no from D1
     */
    private function extractOrderNo($d1, $d2Entries, $manifestNo)
    {
        // Dynamic year check - 2 tahun ke belakang + tahun sekarang + 1 tahun ke depan
        $currentYear = (int)date('Y');
        $validYears = [
            (string)($currentYear - 2), // 2 tahun lalu
            (string)($currentYear - 1), // tahun lalu
            (string)$currentYear,        // tahun sekarang
            (string)($currentYear + 1),  // tahun depan
        ];

        // Helper function to check if value is valid order_no
        $isValidOrderNo = function($value) use ($validYears) {
            $clean = trim((string)$value);
            if (strlen($clean) !== 10 || !ctype_digit($clean)) {
                return false;
            }
            $yearPrefix = substr($clean, 0, 4);
            return in_array($yearPrefix, $validYears);
        };

        // 1. Check column 8 first (standard position)
        if (isset($d1[8]) && $isValidOrderNo($d1[8])) {
            return trim($d1[8]);
        }

        // 2. Check from D2 QR code
        if (!empty($d2Entries) && isset($d2Entries[0][5])) {
            $qrCode = trim($d2Entries[0][5]);
            if (strlen($qrCode) >= 22) {
                $possibleOrderNo = substr($qrCode, 12, 10);
                if ($isValidOrderNo($possibleOrderNo)) {
                    return $possibleOrderNo;
                }
            }
        }

        // 3. Fallback: scan all D1 columns
        foreach ($d1 as $value) {
            if ($isValidOrderNo($value)) {
                return trim($value);
            }
        }

        // 4. Fallback: extract from manifest_no
        if (!empty($manifestNo) && strlen($manifestNo) >= 10) {
            $len = strlen($manifestNo) - 9;
            for ($i = 0; $i < $len; $i++) {
                $segment = substr($manifestNo, $i, 10);
                if ($isValidOrderNo($segment)) {
                    return $segment;
                }
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Extract part_no from D1
     */
    private function extractPartNo($d1, $d2Entries)
    {
        $part_no = 'UNKNOWN';
        
        $commonPositions = [9, 10, 11, 12];
        foreach ($commonPositions as $pos) {
            if (isset($d1[$pos])) {
                $raw_part = trim(preg_replace('/\s+/', '', $d1[$pos]));
                if (strlen($raw_part) === 12) {
                    if (
                        (ctype_digit(substr($raw_part, 0, 5)) && preg_match('/[A-Z]/', substr($raw_part, 5, 5)) && substr($raw_part, 10, 2) === '00') ||
                        preg_match('/^\d{5}[A-Z]{2}\d{5}$/', $raw_part) ||
                        (ctype_digit($raw_part) && substr($raw_part, 10, 2) === '00') ||
                        (preg_match('/^[0-9A-Z]{10}00$/', $raw_part) && substr($raw_part, 10, 2) === '00')
                    ) {
                        $part1 = substr($raw_part, 0, 5);
                        $part2 = substr($raw_part, 5, 5);
                        $part3 = substr($raw_part, 10, 2);
                        $part_no = "$part1-$part2-$part3";
                        break;
                    }
                }
            }
        }
        
        if ($part_no === 'UNKNOWN') {
            foreach ($d1 as $index => $value) {
                $raw_part = trim(preg_replace('/\s+/', '', $value));
                if (strlen($raw_part) === 12) {
                    if (
                        (ctype_digit(substr($raw_part, 0, 5)) && preg_match('/[A-Z]/', substr($raw_part, 5, 5)) && substr($raw_part, 10, 2) === '00') ||
                        preg_match('/^\d{5}[A-Z]{2}\d{5}$/', $raw_part)
                    ) {
                        $part1 = substr($raw_part, 0, 5);
                        $part2 = substr($raw_part, 5, 5);
                        $part3 = substr($raw_part, 10, 2);
                        $part_no = "$part1-$part2-$part3";
                        break;
                    }
                }
            }
        }
        
        if ($part_no === 'UNKNOWN' && !empty($d2Entries) && isset($d2Entries[0][5])) {
            $qrCode = trim($d2Entries[0][5]);
            if (strlen($qrCode) >= 30) {
                $possiblePositions = [22, 23, 24, 25];
                foreach ($possiblePositions as $pos) {
                    if ($pos + 12 <= strlen($qrCode)) {
                        $possiblePart = substr($qrCode, $pos, 12);
                        if (
                            (ctype_digit(substr($possiblePart, 0, 5)) && preg_match('/[A-Z]/', substr($possiblePart, 5, 5)) && substr($possiblePart, 10, 2) === '00') ||
                            preg_match('/^\d{5}[A-Z]{2}\d{5}$/', $possiblePart)
                        ) {
                            $part1 = substr($possiblePart, 0, 5);
                            $part2 = substr($possiblePart, 5, 5);
                            $part3 = substr($possiblePart, 10, 2);
                            $part_no = "$part1-$part2-$part3";
                            break;
                        }
                    }
                }
            }
        }
        
        return $part_no;
    }

    /**
     * Extract supplier from D1
     */
    private function extractSupplier($d1)
    {
        $supplier = 'UNKNOWN';
        
        foreach ($d1 as $index => $value) {
            $current = trim($value);
            $next = isset($d1[$index + 1]) ? trim($d1[$index + 1]) : '';
            
            if (strlen($current) >= 3 &&
                strlen($current) <= 50 &&
                str_word_count($current) >= 1 &&
                !preg_match('/^\d/', $current) &&
                !preg_match('/^[0-9\-\s]+$/', $current) &&
                strlen($next) >= 5 &&
                (strpos(strtoupper($next), 'RETAINER') !== false || 
                strpos(strtoupper($next), 'DOOR') !== false ||
                strpos(strtoupper($next), 'STAY') !== false ||
                strpos(strtoupper($next), 'CLAMP') !== false ||
                strpos(strtoupper($next), 'BRACKET') !== false ||
                strpos(strtoupper($next), 'GASKET') !== false ||
                strpos(strtoupper($next), 'SEAL') !== false ||
                strpos(strtoupper($next), 'BRACE') !== false ||
                strpos(strtoupper($next), 'DEFLECTOR') !== false ||
                strpos(strtoupper($next), 'INSULATOR') !== false ||
                preg_match('/[A-Z]{2,}\s+[A-Z]{2,}/', $next))) {
                
                $supplier = $current;
                break;
            }
        }
        
        if ($supplier !== 'UNKNOWN') {
            $supplier = preg_replace('/\s+/', ' ', trim($supplier));
            $supplier = str_replace(['Pt ', 'Cv ', 'Ud '], ['PT ', 'CV ', 'UD '], $supplier);
        }
        
        return $supplier;
    }

    /**
     * Extract supplier_code from D1
     */
    private function extractSupplierCode($d1, $manifestNo)
    {
        $supplierCodePrefix = 'UNKNOWN';

        if (isset($d1[2]) && !empty(trim($d1[2]))) {
            $candidate = trim($d1[2]);
            if (preg_match('/^[A-Z0-9]{4,6}$/', $candidate)) {
                $supplierCodePrefix = $candidate;
            }
        }

        if ($supplierCodePrefix === 'UNKNOWN') {
            foreach ($d1 as $index => $value) {
                $cleanValue = trim($value);
                if ($index > 1 && $index < 10 && preg_match('/^[A-Z0-9]{4,6}$/', $cleanValue)) {
                    $supplierCodePrefix = $cleanValue;
                    break;
                }
            }
        }

        if ($supplierCodePrefix === 'UNKNOWN' && strlen($manifestNo) >= 4) {
            $supplierCodePrefix = strtoupper(substr($manifestNo, 0, 4));
        }

        return $supplierCodePrefix . '-1';
    }

    /**
     * Extract customer_address from D1
     */
    private function extractCustomerAddress($d1)
    {
        $customer_address = '';

        if (isset($d1[4]) && is_string($d1[4])) {
            $value = trim($d1[4]);
            
            if ($value === '807D' || preg_match('/^\d+[A-Z]$/', $value)) {
                $customer_address = '';
            } elseif (!empty($value) && preg_match('/^[A-Z0-9]{2,4}$/', $value) && $value !== '807D') {
                $customer_address = $value;
            }
        }

        return $customer_address;
    }

    /**
     * Extract dock_code from D1
     */
    private function extractDockCode($d1)
    {
        $dockCode = trim($d1[6] ?? 'UNKNOWN');
        if (strlen($dockCode) == 1 || $dockCode === 'UNKNOWN') {
            $fullLine = implode("\t", $d1);
            if (preg_match('/\b([0-9][A-Z0-9])\b/', $fullLine, $matches)) {
                $dockCode = $matches[1];
            }
        }
        return $dockCode;
    }

    /**
     * Cek apakah sebuah value valid sebagai datetime "asli" dari data sumber
     * (bukan hasil parsing gagal). Batas bawah 1990 dan batas atas +2 tahun
     * dari sekarang dipakai supaya angka yang kebetulan ke-parse jadi tanggal
     * ekstrem (misal salah baca part number) ikut ditolak.
     */
    private function isValidDateTime($value)
    {
        if ($value === null) return false;
        $clean = trim((string) $value);
        if ($clean === '') return false;
        $ts = strtotime($clean);
        return ($ts !== false && $ts > strtotime('1990-01-01') && $ts < strtotime('+2 years'));
    }

    /**
     * Cari kolom yang berisi datetime valid di sekitar posisi yang diharapkan.
     *
     * Dipakai karena jumlah/urutan kolom di file TXT sumber tidak selalu
     * konsisten antar baris (field kosong yang membuat kolom-kolom setelahnya
     * geser). Daripada langsung fallback ke now() saat posisi yang diharapkan
     * ternyata bukan tanggal, method ini scan kolom-kolom di sekitarnya
     * (radius kiri-kanan) untuk menemukan value yang memang berpola datetime,
     * lalu sebagai upaya terakhir scan seluruh baris.
     *
     * $excludeIndexes dipakai supaya kolom yang sudah dipakai untuk field
     * waktu lain (misal sudah dipakai untuk departure_time) tidak dipakai
     * ulang untuk arrival_time/out_time pada baris yang sama.
     *
     * Return: ['value' => string|null, 'index' => int|null]
     */
    private function findNearbyDateTime($d1, $expectedIndex, $radius = 5, $excludeIndexes = [])
    {
        $clean = function ($raw) {
            return trim(preg_replace('/\.\d{1,6}$/', '', (string) $raw));
        };

        // 1. Cek dulu posisi yang diharapkan
        if (isset($d1[$expectedIndex]) && !in_array($expectedIndex, $excludeIndexes, true)) {
            $candidate = $clean($d1[$expectedIndex]);
            if ($this->isValidDateTime($candidate)) {
                return ['value' => $candidate, 'index' => $expectedIndex];
            }
        }

        // 2. Scan kolom kiri-kanan di sekitar posisi yang diharapkan,
        //    makin dekat ke posisi yang diharapkan makin diprioritaskan
        for ($offset = 1; $offset <= $radius; $offset++) {
            foreach ([$expectedIndex + $offset, $expectedIndex - $offset] as $idx) {
                if ($idx < 0 || in_array($idx, $excludeIndexes, true)) continue;
                if (!isset($d1[$idx])) continue;

                $candidate = $clean($d1[$idx]);
                if ($this->isValidDateTime($candidate)) {
                    return ['value' => $candidate, 'index' => $idx];
                }
            }
        }

        // 3. Last resort: scan seluruh baris cari value pertama yang berpola datetime
        foreach ($d1 as $idx => $value) {
            if (in_array($idx, $excludeIndexes, true)) continue;
            $candidate = $clean($value);
            if ($this->isValidDateTime($candidate)) {
                return ['value' => $candidate, 'index' => $idx];
            }
        }

        return ['value' => null, 'index' => null];
    }

    /**
     * Extract times based on dock_code.
     *
     * Tidak lagi fallback ke now() begitu saja saat kolom yang diharapkan
     * kosong/tidak valid — kolom di sekitarnya di-scan dulu (findNearbyDateTime)
     * karena data waktu biasanya tetap ada di baris tersebut, cuma posisinya
     * geser. now() hanya dipakai kalau benar-benar tidak ada satupun kolom
     * berpola datetime yang ditemukan di seluruh baris.
     */
    private function extractTimes($d1, $dockCode)
    {
        $departure_time = now();
        $arrival_time = now();
        $out_time = now();

        if (strtoupper($dockCode) === '4P') {
            $dep = $this->findNearbyDateTime($d1, 19);
            $departure_time = $dep['value']
                ? date('Y-m-d H:i:s', strtotime($dep['value']))
                : now();

            $arr = $this->findNearbyDateTime($d1, 20, 5, array_filter([$dep['index']], fn($v) => $v !== null));
            $arrival_time = $arr['value']
                ? date('Y-m-d H:i:s', strtotime($arr['value']))
                : date('Y-m-d H:i:s', strtotime($departure_time . ' +4 hours'));

            $out_time = null;

        } elseif (strtoupper($dockCode) === '43') {
            $dep = $this->findNearbyDateTime($d1, 19);
            $departure_time = $dep['value']
                ? date('Y-m-d H:i:s', strtotime($dep['value']))
                : now();

            $arr = $this->findNearbyDateTime($d1, 30, 5, array_filter([$dep['index']], fn($v) => $v !== null));
            $arrival_time = $arr['value']
                ? date('Y-m-d H:i:s', strtotime($arr['value']))
                : date('Y-m-d H:i:s', strtotime($departure_time . ' +4 hours'));

            $out = $this->findNearbyDateTime($d1, 20, 5, array_filter([$dep['index'], $arr['index']], fn($v) => $v !== null));
            $out_time = $out['value'] ? date('Y-m-d H:i:s', strtotime($out['value'])) : null;

        } else {
            $dep = $this->findNearbyDateTime($d1, 20);
            $departure_time = $dep['value']
                ? date('Y-m-d H:i:s', strtotime($dep['value']))
                : now();

            $arr = $this->findNearbyDateTime($d1, 31, 5, array_filter([$dep['index']], fn($v) => $v !== null));
            $arrival_time = $arr['value']
                ? date('Y-m-d H:i:s', strtotime($arr['value']))
                : date('Y-m-d H:i:s', strtotime($departure_time . ' +4 hours'));

            $out = $this->findNearbyDateTime($d1, 21, 5, array_filter([$dep['index'], $arr['index']], fn($v) => $v !== null));
            $out_time = $out['value'] ? date('Y-m-d H:i:s', strtotime($out['value'])) : null;
        }

        return [
            'departure_time' => $departure_time,
            'arrival_time' => $arrival_time,
            'out_time' => $out_time
        ];
    }

    /**
     * Extract route from D1
     */
    private function extractRoute($d1, $lastValidRoute)
    {
        $route = 'UNKNOWN';
        $routePattern = '/^RC\d+$/i';
        
        $startSearchCol = min(40, count($d1) - 1);
        for ($i = $startSearchCol; $i >= 20; $i--) {
            if (isset($d1[$i]) && preg_match($routePattern, trim($d1[$i]))) {
                $route = trim($d1[$i]);
                break;
            }
        }
        
        if ($route === 'UNKNOWN') {
            $rcColumns = [32, 30, 28, 26];
            foreach ($rcColumns as $col) {
                if (isset($d1[$col]) && preg_match($routePattern, trim($d1[$col]))) {
                    $route = trim($d1[$col]);
                    break;
                }
            }
        }
        
        if ($route === 'UNKNOWN') {
            $generalRoutePattern = '/^[A-Z0-9]{2,4}$/';
            for ($i = $startSearchCol; $i >= 20; $i--) {
                $value = isset($d1[$i]) ? trim($d1[$i]) : '';
                if (!empty($value) && 
                    preg_match($generalRoutePattern, $value) && 
                    !preg_match('/^\d+$/', $value) &&
                    $value !== '5007') {
                    $route = $value;
                    break;
                }
            }
        }
        
        if ($route === 'UNKNOWN') {
            $route = $lastValidRoute ?? 'UNKNOWN';
        }
        
        return $route;
    }

    /**
     * Extract pcs from D1
     */
    private function extractPcs($d1)
    {
        $pcs = 0;
        if (isset($d1[13]) && is_numeric($d1[13]) && $d1[13] >= 1 && $d1[13] <= 9999) {
            $pcs = (int) $d1[13];
        }
        return $pcs;
    }

    /**
     * Extract part_name from D1
     */
    private function extractPartName($d1)
    {
        $part_name = trim($d1[18] ?? '');
        if ($part_name === '' || $part_name === 'D') {
            $part_name = trim($d1[17] ?? '');
        }
        if ($part_name === '' || $part_name === 'D') {
            $part_name = trim($d1[19] ?? 'UNKNOWN');
        }
        return $part_name;
    }

    /**
     * Extract dock from D1
     */
    private function extractDock($d1, $dockCode)
    {
        $dock = 'UNKNOWN';
        $knownDocks = [
            'KARAWANG', 'SUNTÉR', 'SUNTER', 'JAKARTA', 'BEKASI', 
            'TANGERANG', 'CIKAMPEK', 'PURWAKARTA', 'CIKARANG', 
            'DELTAMAS', 'MM2100', 'EJIP', 'JABABEKA', 'HYUNDAI', 
            'SUZUKI', 'MITSUBISHI', 'PLANT', 'FACTORY'
        ];

        if (isset($d1[63]) && !empty(trim($d1[63]))) {
            $valueUpper = strtoupper(trim($d1[63]));
            foreach ($knownDocks as $knownDock) {
                if (strpos($valueUpper, $knownDock) !== false) {
                    $dock = trim($d1[63]);
                    break;
                }
            }
        }

        if ($dock === 'UNKNOWN') {
            foreach ($d1 as $index => $value) {
                if ($index == 22) continue;
                $valueUpper = strtoupper(trim($value));
                foreach ($knownDocks as $knownDock) {
                    if (strpos($valueUpper, $knownDock) !== false) {
                        $dock = trim($value);
                        break 2;
                    }
                }
            }
        }

        if ($dock === 'UNKNOWN' && !empty($dockCode) && $dockCode !== 'UNKNOWN') {
            $dock = 'DOCK-' . $dockCode;
        } elseif ($dock === 'UNKNOWN') {
            $dock = '';
        }

        return $dock;
    }

    /**
     * Extract unique_no.
     *
     * Diambil dari posisi 3 kolom SETELAH kolom tempat part_address ditemukan
     * (pakai index yang sama persis dengan yang dipakai extractPartAddress(),
     * lewat helper findPartAddress()). Posisi ini terbukti konsisten untuk
     * kedua format file (TMMIN/KRW: hasilnya mis. "163D"; ADM/TSTP: mis. "T029").
     */
    private function extractUniqueNo($d1, $manifestNo)
    {
        $unique_no = 'UNKNOWN';

        $partAddressIndex = $this->findPartAddress($d1)['index'];

        if ($partAddressIndex !== null) {
            $targetIndex = $partAddressIndex + 3;
            if (isset($d1[$targetIndex])) {
                $cleanValue = trim($d1[$targetIndex]);
                if ($cleanValue !== '') {
                    $unique_no = $cleanValue;
                }
            }
        }

        return $unique_no;
    }

    /**
     * Extract plo from D1
     */
    private function extractPlo($d1, $dockCode, $route)
    {
        $plo = null;

        if (strtolower(trim($dockCode)) === '4p') {
            return null;
        }

        if ($route !== 'UNKNOWN') {
            $routeColumnIndex = -1;
            foreach ($d1 as $index => $value) {
                if (trim($value) === $route) {
                    $routeColumnIndex = $index;
                    break;
                }
            }
            
            if ($routeColumnIndex !== -1 && $routeColumnIndex >= 2) {
                $ploColumnIndex = $routeColumnIndex - 2;
                
                if (isset($d1[$ploColumnIndex])) {
                    $candidate = trim($d1[$ploColumnIndex]);
                    if (preg_match('/^[0-9]{1,2}$/', $candidate) && intval($candidate) >= 0 && intval($candidate) <= 99) {
                        $plo = (int) $candidate;
                    }
                }
            }
        }

        if ($plo === null) {
            $standardPloColumns = [30, 31, 32];
            foreach ($standardPloColumns as $col) {
                if (isset($d1[$col])) {
                    $candidate = trim($d1[$col]);
                    if (preg_match('/^[0-9]{1,2}$/', $candidate) && intval($candidate) >= 0 && intval($candidate) <= 99) {
                        $plo = (int) $candidate;
                        break;
                    }
                }
            }
        }

        if ($plo === null) {
            $plo = 2;
        }

        return $plo;
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
                    $firstPart = substr($cleanPartNo, 0, 5);
                    $secondPart = substr($cleanPartNo, 5, 5);
                    $corePartNo = $firstPart . '-' . $secondPart;
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
                Log::error("Error finding address for part_no " . $part_no . ": " . $e->getMessage());
            }
        }

        return ['address' => $address, 'qty' => $qty];
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
     * Extract cycle from D1
     */
    private function extractCycle($d1, $route)
    {
        if ($route === 'UNKNOWN') {
            return 'UNKNOWN';
        }

        $routeIndex = null;
        foreach ($d1 as $index => $value) {
            if (trim($value) === $route) {
                $routeIndex = $index;
                break;
            }
        }

        if ($routeIndex === null || !isset($d1[$routeIndex + 1])) {
            return 'UNKNOWN';
        }

        $cycle = trim($d1[$routeIndex + 1]);

        return $cycle !== '' ? $cycle : 'UNKNOWN';
    }

    /**
     * Extract conveyance_no from D1
     */
    private function extractConveyanceNo($d1)
    {
        $conveyance_no = null;
        
        $checkColumns = [41, 42, 43];
        foreach ($checkColumns as $col) {
            if (isset($d1[$col])) {
                $candidate = trim($d1[$col]);
                if (!empty($candidate)) {
                    $isDateLike = preg_match(
                        '/^\d{4}[-\/]\d{2}[-\/]\d{2}(?:\s+\d{2}:\d{2}(?::\d{2})?(?:\.\d{1,6})?)?$/',
                        $candidate
                    );
                    if (!$isDateLike) {
                        $conveyance_no = substr($candidate, 0, 25);
                        break;
                    }
                }
            }
        }
        
        return $conveyance_no;
    }

    /**
     * Delete a record
     */
    public function destroy($id)
    {
        try {
            $kanbantmmins = KanbanTmmins::findOrFail($id);
            $kanbantmmins->delete();
            
            return redirect()->route('kanbantmmins.index')
                ->with('success', 'Kanban berhasil dihapus!');
        } catch (\Exception $e) {
            Log::error('Error deleting kanban: ' . $e->getMessage());
            
            return redirect()->route('kanbantmmins.index')
                ->with('error', 'Terjadi kesalahan saat menghapus: ' . $e->getMessage());
        }
    }

    /**
     * Print single record
     */
    public function print($id)
    {
        $note = KanbanTmmins::findOrFail($id);
        $itemsToProcess = collect([$note]);
        return view('kanbantmmins.print', compact('itemsToProcess', 'note'));
    }

    /**
     * Print all records by dock codes
     * 
     * FIXED VERSION - Properly orders by Plant (Plant 1 first, then Plant 2)
     * and correctly sets separator page between plants
     */
    public function printAll(Request $request)
    {
        try {
            $generator = new BarcodeGeneratorPNG();
            
            if (!$request->has('dock_codes') || empty($request->input('dock_codes'))) {
                return redirect()->back()->with('error', 'No dock codes provided');
            }
            
            $dockCodesString = $request->input('dock_codes');
            $selectedDockCodes = explode(',', $dockCodesString);
            $selectedDockCodes = array_filter(array_map('trim', $selectedDockCodes));
            
            if (empty($selectedDockCodes)) {
                return redirect()->back()->with('error', 'No valid dock codes found');
            }
            
            // Get plant filter parameter
            $plantFilter = $request->input('plant', 'all'); // all, 1, 2
            
            $allData = KanbanTmmins::whereIn('dock_code', $selectedDockCodes)
                                 ->orderByDesc('manifest_no')
                                 ->orderByDesc('address')
                                 ->orderBy('dock_code')
                                 ->orderBy('created_at')
                                 ->get();
            
            if ($allData->isEmpty()) {
                $allData = KanbanTmmins::where(function($query) use ($selectedDockCodes) {
                    foreach ($selectedDockCodes as $dockCode) {
                        $query->orWhereRaw('LOWER(TRIM(dock_code)) = ?', [strtolower(trim($dockCode))]);
                    }
                })
                ->orderByDesc('manifest_no')
                ->orderByDesc('address')
                ->orderBy('dock_code')
                ->orderBy('created_at')
                ->get();
            }
            
            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected dock codes');
            }
            
            // Sorting function - DESC manifest, DESC address
            $sortFunction = function ($item) {
                $manifestNo = $item->manifest_no ?? '';
                $address = $item->address ?? 'No Address';
                
                // Reverse for DESC sort
                $reversedManifestNo = str_pad((999999999999 - (int)$manifestNo), 12, '0', STR_PAD_LEFT);
                
                $reversedAddress = '';
                for ($i = 0; $i < strlen($address); $i++) {
                    $char = strtolower($address[$i]);
                    if (ctype_alpha($char)) {
                        $reversedChar = chr(ord('z') - ord($char) + ord('a'));
                        $reversedAddress .= $reversedChar;
                    } elseif (ctype_digit($char)) {
                        $reversedChar = (string)(9 - (int)$char);
                        $reversedAddress .= $reversedChar;
                    } else {
                        $reversedAddress .= $char;
                    }
                }
                
                return $reversedManifestNo . '|' . $reversedAddress;
            };
            
            // Separate Plant 1 and Plant 2
            // Plant 1: address NOT starting with 'K'
            $plant1Items = $allData->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return !str_starts_with($address, 'K');
            })->sortBy($sortFunction)->values();
            
            // Plant 2: address starting with 'K'
            $plant2Items = $allData->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return str_starts_with($address, 'K');
            })->sortBy($sortFunction)->values();
            
            // Build final ordered collection based on plant filter
            $orderedItems = collect();
            $separatorAfterIndex = -1;
            $showPlantSeparator = false;
            
            if ($plantFilter === '1') {
                // Only Plant 1
                $orderedItems = $plant1Items;
            } elseif ($plantFilter === '2') {
                // Only Plant 2
                $orderedItems = $plant2Items;
            } else {
                // All - Plant 1 first, then Plant 2
                if ($plant1Items->count() > 0) {
                    $orderedItems = $orderedItems->merge($plant1Items);
                }
                
                // Check if we need separator (only when both plants have data)
                if ($plant1Items->count() > 0 && $plant2Items->count() > 0) {
                    $showPlantSeparator = true;
                    $separatorAfterIndex = $plant1Items->count() - 1; // Index of last Plant 1 item
                }
                
                if ($plant2Items->count() > 0) {
                    $orderedItems = $orderedItems->merge($plant2Items);
                }
            }
            
            $orderedItems = $orderedItems->values();
            
            if ($orderedItems->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected plant filter');
            }
            
            // Generate barcodes for ordered items
            foreach ($orderedItems as $item) {
                try {
                    $addressToUse = $item->address;
                    if (empty($addressToUse) || $addressToUse === 'No Address') {
                        $addressToUse = $item->part_address ?? $item->dock_code . '-' . $item->unique_no;
                    }
                    
                    $barcodeData = base64_encode($generator->getBarcode(
                        $addressToUse,
                        $generator::TYPE_CODE_128
                    ));
                    $item->barcode_image = $barcodeData;
                    $item->barcode_text = $addressToUse;
                } catch (\Exception $e) {
                    $item->barcode_image = null;
                    $item->barcode_text = $item->address ?? $item->dock_code . '-' . $item->id;
                }
            }
            
            // For backward compatibility, also create groupedData (but this won't be used for ordering)
            $groupedData = collect();
            foreach ($orderedItems->groupBy('dock_code') as $dockCode => $items) {
                $groupedData->put($dockCode, $items->values());
            }
            
            // Pass all necessary data to view
            // IMPORTANT: orderedItems is the main data source, already sorted by Plant
            return view('kanbantmmins.printall', compact(
                'orderedItems',
                'groupedData', 
                'selectedDockCodes', 
                'plantFilter', 
                'showPlantSeparator',
                'separatorAfterIndex',
                'plant1Items',
                'plant2Items'
            ));
            
        } catch (\Exception $e) {
            Log::error('Print All Error: ' . $e->getMessage());
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
        
        $kanbantmmins = KanbanTmmins::where('manifest_no', $manifestNo)->get();
        
        if ($kanbantmmins->isEmpty()) {
            return redirect()->back()->with('error', 'No records found');
        }
        
        return view('kanbantmmins.print-group', compact('kanbantmmins'));
    }

    public function printSelected(Request $request)
    {
        try {
            $generator = new BarcodeGeneratorPNG();
            
            if (!$request->has('ids') || empty($request->input('ids'))) {
                return redirect()->back()->with('error', 'No IDs provided');
            }
            
            $idsString = $request->input('ids');
            $selectedIds = explode(',', $idsString);
            $selectedIds = array_filter(array_map('trim', $selectedIds));
            
            if (empty($selectedIds)) {
                return redirect()->back()->with('error', 'No valid IDs found');
            }
            
            // Get plant filter parameter (sama seperti printAll)
            $plantFilter = $request->input('plant', 'all');
            
            $allData = KanbanTmmins::whereIn('id', $selectedIds)->get();
            
            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected IDs');
            }
            
            // Sorting function - DESC manifest, DESC address (sama seperti printAll)
            $sortFunction = function ($item) {
                $manifestNo = $item->manifest_no ?? '';
                $address = $item->address ?? 'No Address';
                
                $reversedManifestNo = str_pad((999999999999 - (int)$manifestNo), 12, '0', STR_PAD_LEFT);
                
                $reversedAddress = '';
                for ($i = 0; $i < strlen($address); $i++) {
                    $char = strtolower($address[$i]);
                    if (ctype_alpha($char)) {
                        $reversedChar = chr(ord('z') - ord($char) + ord('a'));
                        $reversedAddress .= $reversedChar;
                    } elseif (ctype_digit($char)) {
                        $reversedChar = (string)(9 - (int)$char);
                        $reversedAddress .= $reversedChar;
                    } else {
                        $reversedAddress .= $char;
                    }
                }
                
                return $reversedManifestNo . '|' . $reversedAddress;
            };
            
            // Separate Plant 1 and Plant 2
            $plant1Items = $allData->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return !str_starts_with($address, 'K');
            })->sortBy($sortFunction)->values();
            
            $plant2Items = $allData->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return str_starts_with($address, 'K');
            })->sortBy($sortFunction)->values();
            
            // Build final ordered collection based on plant filter
            $orderedItems = collect();
            $separatorAfterIndex = -1;
            $showPlantSeparator = false;
            
            if ($plantFilter === '1') {
                $orderedItems = $plant1Items;
            } elseif ($plantFilter === '2') {
                $orderedItems = $plant2Items;
            } else {
                if ($plant1Items->count() > 0) {
                    $orderedItems = $orderedItems->merge($plant1Items);
                }
                
                if ($plant1Items->count() > 0 && $plant2Items->count() > 0) {
                    $showPlantSeparator = true;
                    $separatorAfterIndex = $plant1Items->count() - 1;
                }
                
                if ($plant2Items->count() > 0) {
                    $orderedItems = $orderedItems->merge($plant2Items);
                }
            }
            
            $orderedItems = $orderedItems->values();
            
            if ($orderedItems->isEmpty()) {
                return redirect()->back()->with('error', 'No data found for selected plant filter');
            }
            
            // Generate barcodes
            foreach ($orderedItems as $item) {
                try {
                    $addressToUse = $item->address;
                    if (empty($addressToUse) || $addressToUse === 'No Address') {
                        $addressToUse = $item->part_address ?? $item->dock_code . '-' . $item->unique_no;
                    }
                    
                    $barcodeData = base64_encode($generator->getBarcode(
                        $addressToUse,
                        $generator::TYPE_CODE_128
                    ));
                    $item->barcode_image = $barcodeData;
                    $item->barcode_text = $addressToUse;
                } catch (\Exception $e) {
                    $item->barcode_image = null;
                    $item->barcode_text = $item->address ?? $item->dock_code . '-' . $item->id;
                }
            }
            
            // Get unique dock codes
            $selectedDockCodes = $orderedItems->pluck('dock_code')->unique()->toArray();
            
            // Group by dock_code
            $groupedData = collect();
            foreach ($orderedItems->groupBy('dock_code') as $dockCode => $items) {
                $groupedData->put($dockCode, $items->values());
            }
            
            return view('kanbantmmins.printall', compact(
                'orderedItems',
                'groupedData', 
                'selectedDockCodes', 
                'plantFilter',
                'showPlantSeparator',
                'separatorAfterIndex',
                'plant1Items',
                'plant2Items'
            ));
            
        } catch (\Exception $e) {
            Log::error('Print Selected Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
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
            
            $items = KanbanTmmins::whereIn('id', $ids)->get();
            
            $plant1Count = $items->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return !str_starts_with($address, 'K');
            })->count();
            
            $plant2Count = $items->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return str_starts_with($address, 'K');
            })->count();
            
            return response()->json([
                'plant1' => $plant1Count,
                'plant2' => $plant2Count
            ]);
            
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
            
            $items = KanbanTmmins::whereIn('dock_code', $dockCodes)->get();
            
            $plant1Count = $items->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return !str_starts_with($address, 'K');
            })->count();
            
            $plant2Count = $items->filter(function($item) {
                $address = strtoupper(trim($item->address ?? ''));
                return str_starts_with($address, 'K');
            })->count();
            
            return response()->json([
                'plant1' => $plant1Count,
                'plant2' => $plant2Count
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['plant1' => 0, 'plant2' => 0]);
        }
    }
}