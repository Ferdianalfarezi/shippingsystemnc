<?php

namespace App\Http\Controllers;

use App\Models\Preparation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PreparationController extends Controller
{
    public function index(Request $request)
{
    $perPage = $request->get('per_page', 50);
    $search = $request->get('search');
    
    // Query dasar
    $query = Preparation::query();
    
    // Jika ada pencarian
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('route', 'like', "%{$search}%")
              ->orWhere('logistic_partners', 'like', "%{$search}%")
              ->orWhere('no_dn', 'like', "%{$search}%")
              ->orWhere('customers', 'like', "%{$search}%")
              ->orWhere('dock', 'like', "%{$search}%")
              ->orWhere('cycle', 'like', "%{$search}%");
        });
    }
    
    // Order by pulling datetime (yang paling dekat/lewat dulu di atas)
    $query->orderByRaw("CONCAT(pulling_date, ' ', pulling_time) ASC");
    
    // Pagination atau all
    if ($perPage === 'all') {
        $preparations = $query->get();
        $preparations = new \Illuminate\Pagination\LengthAwarePaginator(
            $preparations,
            $preparations->count(),
            $preparations->count(),
            1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    } else {
        $preparations = $query->paginate((int)$perPage)->withQueryString();
    }
    
    // Hitung statistik
    $totalAll = Preparation::count();
    $now = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
        $totalDelay = Preparation::whereRaw(
            "CONCAT(pulling_date, ' ', pulling_time) < ?",
            [$now]
        )->count();
    $totalOnTime = $totalAll - $totalDelay;
    
    // PERBAIKAN: Get recent scan dengan fresh() untuk force reload
    $recentScan = \App\Models\Shipping::whereNotNull('scan_to_shipping')
        ->orderBy('scan_to_shipping', 'desc')
        ->first();
    
    // DEBUG: Log recent scan data
    if ($recentScan) {
        \Log::info('Recent Scan Data:', [
            'no_dn' => $recentScan->no_dn,
            'moved_by' => $recentScan->moved_by,
            'scan_time' => $recentScan->scan_to_shipping
        ]);
    }

    // // Tambahin sebelum return view
    // $testPrep = Preparation::first();
    // dd([
    //     'pulling_date_raw' => $testPrep->getAttributes()['pulling_date'],
    //     'pulling_date_cast' => $testPrep->pulling_date,
    //     'pulling_time' => $testPrep->pulling_time,
    //     'pulling_datetime_parsed' => Carbon::parse($testPrep->pulling_date->format('Y-m-d') . ' ' . $testPrep->pulling_time)->toDateTimeString(),
    //     'now' => Carbon::now()->toDateTimeString(),
    //     'is_delay' => Carbon::now()->greaterThan(Carbon::parse($testPrep->pulling_date->format('Y-m-d') . ' ' . $testPrep->pulling_time)),
    // ]);
    
    // DN yang sudah di-prep tapi grupnya belum selesai (shipping belum dipilih) → [no_dn => "PR 1 · 2/4"]
    $inProgressDns = [];
    foreach (\App\Models\PrepMonitoring::where('status', 'in_progress')->get() as $monitoring) {
        $monitoring->syncSkid(); // betulkan skid grup lama (sebelum route jadi kunci grup)

        $label = $monitoring->pr_label . ' · ' . $monitoring->pr_count . '/' . $monitoring->skid;
        if ($monitoring->pr_count >= $monitoring->skid && empty($monitoring->shipping_address)) {
            $label .= ' · pilih shipping';
        }

        foreach ($monitoring->scanned_dns ?? [] as $dn) {
            $inProgressDns[$dn] = $label;
        }
    }

    return view('preparations.index', compact('preparations', 'totalDelay', 'totalOnTime', 'totalAll', 'recentScan', 'inProgressDns'));
}

    public function create()
    {
        return view('preparations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'route' => 'required|string|max:255',
            'logistic_partners' => 'required|string|max:255',
            'no_dn' => 'required|string|max:255|unique:preparations,no_dn',
            'customers' => 'required|string|max:255',
            'dock' => 'required|string|max:255',
            'delivery_date' => 'required|date',
            'delivery_time' => 'required',
            'cycle' => 'required|integer|min:1',
            'pulling_date' => 'required|date',
            'pulling_time' => 'required',
        ]);

        try {
            $preparation = Preparation::create($validated);
            
            // Jika request AJAX, return JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data preparation berhasil ditambahkan',
                    'data' => $preparation
                ]);
            }
            
            return redirect()
                ->route('preparations.index')
                ->with('success', 'Data preparation berhasil ditambahkan!');
        } catch (\Exception $e) {
            // Jika request AJAX, return JSON error
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambahkan data: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan data: ' . $e->getMessage());
        }
    }

    public function show(Preparation $preparation)
    {
        return view('preparations.show', compact('preparation'));
    }

    public function edit(Preparation $preparation)
    {
        // Jika request AJAX, return JSON
        if (request()->ajax()) {
            return response()->json([
                'id' => $preparation->id,
                'route' => $preparation->route,
                'logistic_partners' => $preparation->logistic_partners,
                'no_dn' => $preparation->no_dn,
                'customers' => $preparation->customers,
                'dock' => $preparation->dock,
                'delivery_date' => $preparation->delivery_date->format('Y-m-d'),
                'delivery_time' => date('H:i', strtotime($preparation->delivery_time)),
                'cycle' => $preparation->cycle,
                'pulling_date' => $preparation->pulling_date->format('Y-m-d'),
                'pulling_time' => date('H:i', strtotime($preparation->pulling_time)),
            ]);
        }
        
        return view('preparations.edit', compact('preparation'));
    }

    public function update(Request $request, Preparation $preparation)
    {
        $validated = $request->validate([
            'route' => 'required|string|max:255',
            'logistic_partners' => 'required|string|max:255',
            'no_dn' => 'required|string|max:255|unique:preparations,no_dn,' . $preparation->id,
            'customers' => 'required|string|max:255',
            'dock' => 'required|string|max:255',
            'delivery_date' => 'required|date',
            'delivery_time' => 'required',
            'cycle' => 'required|integer|min:1',
            'pulling_date' => 'required|date',
            'pulling_time' => 'required',
        ]);

        try {
            $preparation->update($validated);
            
            // Jika request AJAX, return JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data preparation berhasil diupdate',
                    'data' => $preparation
                ]);
            }
            
            return redirect()
                ->route('preparations.index')
                ->with('success', 'Data preparation berhasil diupdate!');
        } catch (\Exception $e) {
            // Jika request AJAX, return JSON error
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengupdate data: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal mengupdate data: ' . $e->getMessage());
        }
    }

    public function destroy(Preparation $preparation)
    {
        \Log::info('Delete request received for ID: ' . $preparation->id);
        
        try {
            // GANTI delete() dengan forceDelete() untuk hapus permanen
            $deleted = $preparation->forceDelete();
            
            \Log::info('Force delete result: ' . ($deleted ? 'success' : 'failed'));
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data preparation berhasil dihapus!'
                ]);
            }
            
            return redirect()
                ->route('preparations.index')
                ->with('success', 'Data preparation berhasil dihapus!');
        } catch (\Exception $e) {
            \Log::error('Delete error: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus data: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()
                ->back()
                ->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    public function deleteAll()
    {
        try {
            // Hitung hanya data yang belum di-soft delete
            $count = Preparation::count();
            
            // GANTI truncate() dengan withTrashed()->forceDelete()
            // karena truncate() tidak bisa dipakai dengan soft deletes
            Preparation::withTrashed()->forceDelete();
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$count} data preparation"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find preparation by DN number (untuk fitur scan)
     */
    public function findByDn(Request $request)
    {
        $noDn = $request->get('no_dn');
        
        if (!$noDn) {
            return response()->json([
                'success' => false,
                'message' => 'No DN tidak boleh kosong'
            ], 400);
        }
        
        $preparation = Preparation::where('no_dn', $noDn)->first();
        
        if (!$preparation) {
            return response()->json([
                'success' => false,
                'message' => 'Data preparation dengan DN ' . $noDn . ' tidak ditemukan',
                'data' => null
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Data ditemukan',
            'data' => [
                'id' => $preparation->id,
                'no_dn' => $preparation->no_dn,
                'route' => $preparation->route,
                'logistic_partners' => $preparation->logistic_partners,
                'customers' => $preparation->customers,
                'dock' => $preparation->dock,
                'cycle' => $preparation->cycle,
                'delivery_date' => $preparation->delivery_date->format('Y-m-d'),
                'delivery_time' => $preparation->delivery_time,
                'pulling_date' => $preparation->pulling_date->format('Y-m-d'),
                'pulling_time' => $preparation->pulling_time,
            ]
        ]);
    }

    public function andon(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        
        // Query dasar - sama seperti index tapi tanpa search
        $query = Preparation::query();
        
        // Order by pulling datetime (yang paling dekat/lewat dulu di atas)
        $query->orderByRaw("CONCAT(pulling_date, ' ', pulling_time) ASC");
        
        // Pagination atau all
        if ($perPage === 'all') {
            $preparations = $query->get();
            $preparations = new \Illuminate\Pagination\LengthAwarePaginator(
                $preparations,
                $preparations->count(),
                $preparations->count(),
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $preparations = $query->paginate((int)$perPage)->withQueryString();
        }
        
        // Hitung statistik
        $totalAll = Preparation::count();
        $now = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
        $totalDelay = Preparation::whereRaw(
            "CONCAT(pulling_date, ' ', pulling_time) < ?",
            [$now]
        )->count();
        $totalOnTime = $totalAll - $totalDelay;
        
        // Get recent scan dengan fresh() untuk force reload
        $recentScan = \App\Models\Shipping::whereNotNull('scan_to_shipping')
            ->orderBy('scan_to_shipping', 'desc')
            ->first();
        
        // Return view andon
        return view('andon.preparations', compact('preparations', 'totalDelay', 'totalOnTime', 'totalAll', 'recentScan'));
    }

    public function scan()
    {
        // Total counts
        $totalPreparations = Preparation::count();
        $totalShippings = \App\Models\Shipping::count();
        
        // Get recent scans (10 terakhir)
        $recentScans = \App\Models\Shipping::whereNotNull('scan_to_shipping')
            ->orderBy('scan_to_shipping', 'desc')
            ->limit(5)
            ->get();
        
        return view('preparations.scan', compact('totalPreparations', 'totalShippings', 'recentScans'));
    }
}