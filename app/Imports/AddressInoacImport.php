<?php

namespace App\Imports;

use App\Models\AddressInoac;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AddressInoacImport implements ToCollection, WithHeadingRow
{
    public int $saved = 0;
    public int $skipped = 0;

    /** part_no yang muncul lebih dari 1x di excel (baris terakhir yang dipakai) */
    public array $duplicates = [];

    /**
     * Sama kayak excel FJI/NTC: baris 1 cuma judul ("Master Part List"),
     * header asli (NO, CUSTOMER_CODE, PART_NO, RACK_NO, PART_NAME) di baris 2.
     */
    public function headingRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        $seen = [];

        foreach ($rows as $row) {
            $partNo = trim((string) ($row['part_no'] ?? ''));
            $customerCode = trim((string) ($row['customer_code'] ?? ''));
            $partName = trim((string) ($row['part_name'] ?? ''));
            $rackNo = trim((string) ($row['rack_no'] ?? ''));

            if ($partNo === '' || ($rackNo !== '' && str_starts_with($rackNo, '='))) {
                $this->skipped++;
                continue;
            }

            if (isset($seen[$partNo])) {
                $this->duplicates[$partNo] = true;
            }
            $seen[$partNo] = true;

            $values = [
                'customer_code' => $customerCode !== '' ? $customerCode : null,
                'part_name' => ($partName !== '' && $partName !== '-') ? $partName : null,
                'rack_no' => $rackNo !== '' ? $rackNo : null,
            ];

            // Kode INOAC (yang ada di QR kanban) ditulis di dalam kurung di
            // PART_NAME, mis. "HOLD RETAINER CTR (RET-00-B01A)". Kalau gak ada,
            // inoac_code yang udah diisi manual jangan ketimpa.
            if (preg_match('/\(\s*([A-Z0-9]{2,5}-[A-Z0-9]{2}-[A-Z0-9]+)\s*\)/i', $partName, $m)) {
                $values['inoac_code'] = strtoupper($m[1]);
            }

            AddressInoac::updateOrCreate(['part_no' => $partNo], $values);
            $this->saved++;
        }
    }
}
