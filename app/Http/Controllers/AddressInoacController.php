<?php

namespace App\Http\Controllers;

use App\Imports\AddressInoacImport;
use App\Models\AddressInoac;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AddressInoacController extends Controller
{
    public function index(Request $request)
    {
        $query = AddressInoac::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('part_no', 'like', "%{$search}%")
                    ->orWhere('inoac_code', 'like', "%{$search}%")
                    ->orWhere('part_name', 'like', "%{$search}%")
                    ->orWhere('rack_no', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 50);
        $addressInoacs = $perPage === 'all'
            ? $query->paginate(max($query->count(), 1))
            : $query->paginate((int) $perPage);

        return view('addressinoac.index', compact('addressInoacs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_no' => 'required|string|max:255|unique:addressinoac,part_no',
            'inoac_code' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:255',
            'part_name' => 'nullable|string|max:255',
            'rack_no' => 'nullable|string|max:255',
        ]);

        AddressInoac::create($validated);

        return response()->json(['message' => 'Data INOAC Address berhasil ditambahkan.']);
    }

    public function edit(AddressInoac $addressInoac)
    {
        return response()->json($addressInoac);
    }

    public function update(Request $request, AddressInoac $addressInoac)
    {
        $validated = $request->validate([
            'part_no' => 'required|string|max:255|unique:addressinoac,part_no,' . $addressInoac->id,
            'inoac_code' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:255',
            'part_name' => 'nullable|string|max:255',
            'rack_no' => 'nullable|string|max:255',
        ]);

        $addressInoac->update($validated);

        return response()->json(['message' => 'Data INOAC Address berhasil diupdate.']);
    }

    public function destroy(AddressInoac $addressInoac)
    {
        $addressInoac->delete();

        return response()->json(['message' => 'Data INOAC Address berhasil dihapus.']);
    }

    public function deleteAll()
    {
        AddressInoac::query()->delete();

        return response()->json(['message' => 'Semua data INOAC Address berhasil dihapus.']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel' => 'required|file|mimes:xlsx,xls',
        ]);

        $import = new AddressInoacImport();
        Excel::import($import, $request->file('excel'));

        if ($import->saved === 0) {
            return response()->json([
                'message' => 'Tidak ada data yang ke-import. Pastikan header (PART_NO, RACK_NO, PART_NAME) ada di baris 2.',
            ], 422);
        }

        $message = "Import berhasil: {$import->saved} baris disimpan";
        if ($import->skipped > 0) {
            $message .= ", {$import->skipped} baris di-skip (part no kosong)";
        }
        if (!empty($import->duplicates)) {
            $message .= '. Part no dobel (rack terakhir yang dipakai): ' . implode(', ', array_keys($import->duplicates));
        }

        return response()->json(['message' => $message . '.']);
    }
}
