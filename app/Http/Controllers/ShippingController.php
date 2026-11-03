<?php

namespace App\Http\Controllers;

use App\Models\Shipping;
use App\Models\Preparation;
use App\Models\Delivery;
use App\Models\Milkrun;
use App\Models\ShippingMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShippingController extends Controller
{
    /**
     * Get mirrored preparations (yang delivery_time <= 4 jam dari sekarang)
     * Data ini belum di-scan, hanya ditampilkan di halaman shipping
     * Status mirror: PLANNING (belum lewat) atau DELAY (sudah lewat)
     */
    private function getMirroredPreparations()
    {
        $now = Carbon::now();
        $fourHoursLater = $now->copy()->addHours(4);
        
        // Ambil preparations yang delivery datetime <= 4 jam dari sekarang
        // DAN belum ada di tabel shippings (berdasarkan no_dn)
        return Preparation::whereRaw(
            "CONCAT(delivery_date, ' ', delivery_time) <= ?",
            [$fourHoursLater->format('Y-m-d H:i:s')]
        )
        ->whereNotIn('no_dn', function($query) {
            $query->select('no_dn')->from('shippings')->whereNull('deleted_at');
        })
        ->get()
        ->map(function ($prep) {
            // Hitung status untuk mirror: PLANNING atau DELAY
            $deliveryDateTime = Carbon::parse($prep->delivery_date->format('Y-m-d') . ' ' . $prep->delivery_time);
            $now = Carbon::now();
            
            if ($now->greaterThan($deliveryDateTime)) {
                // Sudah lewat delivery time = DELAY
                $status = 'delay';
                $statusLabel = 'DELAY';
                $statusBadge = 'bg-danger';
            } else {
                // Belum lewat = PLANNING (tidak masuk summary card)
                $status = 'planning';
                $statusLabel = 'PLANNING';
                $statusBadge = 'bg-secondary';
            }
            
            // Return sebagai object dengan struktur mirip Shipping
            return (object) [
                'id' => $prep->id,
                'route' => $prep->route,
                'logistic_partners' => $prep->logistic_partners,
                'no_dn' => $prep->no_dn,
                'customers' => $prep->customers,
                'dock' => $prep->dock,
                'delivery_date' => $prep->delivery_date,
                'delivery_time' => $prep->delivery_time,
                'arrival' => null,
                'cycle' => $prep->cycle,
                'address' => null, // Belum ada address
                'status' => $status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'time_info' => $now->greaterThan($deliveryDateTime) 
                    ? 'Terlambat ' . $now->diffForHumans($deliveryDateTime, true)
                    : 'Sisa ' . $now->diffForHumans($deliveryDateTime, true),
                'is_mirror' => true, // Flag untuk identifikasi data mirror
                'pulling_date' => $prep->pulling_date,
                'pulling_time' => $prep->pulling_time,
                'scan_to_shipping' => null,
                'moved_by' => null,
            ];
        });
    }

    /**
     * Calculate status for shipping item
     */
    private function calculateShippingStatus($item)
    {
        if ($item->arrival !== null) {
            return [
                'status' => 'on_loading',
                'status_label' => 'ON LOADING',
                'status_badge' => 'bg-primary',
            ];
        }
        
        $deliveryDateTime = Carbon::parse(
            (is_string($item->delivery_date) ? $item->delivery_date : $item->delivery_date->format('Y-m-d')) 
            . ' ' . $item->delivery_time
        );
        $now = Carbon::now();
        
        if ($now->greaterThan($deliveryDateTime)) {
            return [
                'status' => 'delay',
                'status_label' => 'DELAY',
                'status_badge' => 'bg-danger',
            ];
        } else {
            return [
                'status' => 'normal',
                'status_label' => 'NORMAL',
                'status_badge' => 'bg-success',
            ];
        }
    }

    /**
     * Display shipping list
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search');
        $statusFilter = $request->get('status');
        
        // Get real shippings
        $shippingsQuery = Shipping::query();
        
        if ($search) {
            $shippingsQuery->where(function($q) use ($search) {
                $q->where('route', 'like', "%{$search}%")
                  ->orWhere('logistic_partners', 'like', "%{$search}%")
                  ->orWhere('no_dn', 'like', "%{$search}%")
                  ->orWhere('customers', 'like', "%{$search}%")
                  ->orWhere('dock', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }
        
        $realShippings = $shippingsQuery->get()->map(function ($ship) {
            $statusInfo = $this->calculateShippingStatus($ship);
            $ship->status = $statusInfo['status'];
            $ship->status_label = $statusInfo['status_label'];
            $ship->status_badge = $statusInfo['status_badge'];
            $ship->is_mirror = false;
            return $ship;
        });
        
        // Get mirrored preparations
        $mirroredPreparations = $this->getMirroredPreparations();
        
        // Filter mirrored by search
        if ($search) {
            $mirroredPreparations = $mirroredPreparations->filter(function ($item) use ($search) {
                return str_contains(strtolower($item->route), strtolower($search))
                    || str_contains(strtolower($item->logistic_partners), strtolower($search))
                    || str_contains(strtolower($item->no_dn), strtolower($search))
                    || str_contains(strtolower($item->customers), strtolower($search))
                    || str_contains(strtolower($item->dock), strtolower($search));
            });
        }
        
        // Combine both collections
        $combined = $realShippings->concat($mirroredPreparations);
        
        // Apply status filter
        if ($statusFilter && $statusFilter !== 'all') {
            $combined = $combined->filter(function ($item) use ($statusFilter) {
                return $item->status === $statusFilter;
            });
        }
        
        // Sort by delivery datetime ASC
        $combined = $combined->sortBy(function ($item) {
            $date = is_string($item->delivery_date) ? $item->delivery_date : $item->delivery_date->format('Y-m-d');
            return $date . ' ' . $item->delivery_time;
        })->values();
        
        // Calculate statistics from combined data - PLANNING tidak masuk summary
        $totalAll = $combined->count();
        $totalNormal = $combined->where('status', 'normal')->count();
        $totalDelay = $combined->where('status', 'delay')->count();
        $totalOnLoading = $combined->where('status', 'on_loading')->count();
        
        // Pagination
        if ($perPage === 'all') {
            $shippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $combined,
                $combined->count(),
                $combined->count(),
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $perPageInt = (int)$perPage;
            $currentPageItems = $combined->slice(($currentPage - 1) * $perPageInt, $perPageInt)->values();
            
            $shippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentPageItems,
                $combined->count(),
                $perPageInt,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }
        
        // Get recent scan untuk display
        $recentScan = Delivery::whereNotNull('scan_to_delivery')
            ->orderBy('scan_to_delivery', 'desc')
            ->first();
        
        return view('shippings.index', compact(
            'shippings', 
            'totalAll', 
            'totalNormal', 
            'totalDelay',
            'totalOnLoading',
            'recentScan'
        ));
    }

    /**
     * Display shipping list in reverse view (grouped by route & cycle)
     */
    public function indexReverse(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search');
        
        // Get real shippings
        $shippingsQuery = Shipping::query();
        
        if ($search) {
            $shippingsQuery->where(function($q) use ($search) {
                $q->where('route', 'like', "%{$search}%")
                  ->orWhere('logistic_partners', 'like', "%{$search}%")
                  ->orWhere('customers', 'like', "%{$search}%")
                  ->orWhere('dock', 'like', "%{$search}%");
            });
        }
        
        $realShippings = $shippingsQuery->get()->map(function ($ship) {
            $ship->is_mirror = false;
            return $ship;
        });
        
        // Get mirrored preparations
        $mirroredPreparations = $this->getMirroredPreparations();
        
        // Filter mirrored by search
        if ($search) {
            $mirroredPreparations = $mirroredPreparations->filter(function ($item) use ($search) {
                return str_contains(strtolower($item->route), strtolower($search))
                    || str_contains(strtolower($item->logistic_partners), strtolower($search))
                    || str_contains(strtolower($item->customers), strtolower($search))
                    || str_contains(strtolower($item->dock), strtolower($search));
            });
        }
        
        // Combine both collections
        $allShippings = $realShippings->concat($mirroredPreparations);
        
        // Group by route and cycle
        $grouped = $allShippings->groupBy(function($item) {
            return $item->route . '|' . $item->cycle;
        })->map(function($group) {
            $firstItem = $group->first();
            
            // Check if any item has arrival (real shipping with arrival)
            $hasArrival = $group->contains(function ($item) {
                return $item->arrival !== null;
            });
            
            // Check if all items are mirror
            $allMirror = $group->every(function ($item) {
                return $item->is_mirror ?? false;
            });
            
            // Calculate status for the group
            if ($hasArrival) {
                $statusLabel = 'ON LOADING';
                $statusBadge = 'bg-primary';
                $status = 'on_loading';
            } elseif ($allMirror) {
                // Semua data adalah mirror - gunakan PLANNING atau DELAY
                $deliveryDate = is_string($firstItem->delivery_date) 
                    ? $firstItem->delivery_date 
                    : $firstItem->delivery_date->format('Y-m-d');
                $deliveryDateTime = Carbon::parse($deliveryDate . ' ' . $firstItem->delivery_time);
                $now = Carbon::now();
                
                if ($now->greaterThan($deliveryDateTime)) {
                    $statusLabel = 'DELAY';
                    $statusBadge = 'bg-danger';
                    $status = 'delay';
                } else {
                    $statusLabel = 'PLANNING';
                    $statusBadge = 'bg-secondary';
                    $status = 'planning';
                }
            } else {
                // Ada data real shipping - gunakan logic normal
                $deliveryDate = is_string($firstItem->delivery_date) 
                    ? $firstItem->delivery_date 
                    : $firstItem->delivery_date->format('Y-m-d');
                $deliveryDateTime = Carbon::parse($deliveryDate . ' ' . $firstItem->delivery_time);
                $now = Carbon::now();
                
                if ($now->greaterThan($deliveryDateTime)) {
                    $statusLabel = 'DELAY';
                    $statusBadge = 'bg-danger';
                    $status = 'delay';
                } else {
                    $statusLabel = 'NORMAL';
                    $statusBadge = 'bg-success';
                    $status = 'normal';
                }
            }
            
            // Get arrival from first item that has it
            $arrivalItem = $group->first(function ($item) {
                return $item->arrival !== null;
            });
            
            $deliveryDate = is_string($firstItem->delivery_date) 
                ? $firstItem->delivery_date 
                : $firstItem->delivery_date->format('Y-m-d');
            
            return [
                'route' => $firstItem->route,
                'logistic_partners' => $group->pluck('logistic_partners')->unique()->filter()->implode(', '),
                'cycle' => $firstItem->cycle,
                'customers' => $group->pluck('customers')->unique()->filter()->implode(', '),
                'dock' => $group->pluck('dock')->unique()->filter()->implode(', '),
                'delivery_date' => Carbon::parse($deliveryDate)->format('d-m-y'),
                'delivery_time' => date('H:i:s', strtotime($firstItem->delivery_time)),
                'arrival' => $arrivalItem && $arrivalItem->arrival 
                    ? (is_string($arrivalItem->arrival) ? $arrivalItem->arrival : $arrivalItem->arrival->format('d-m-y H:i')) 
                    : null,
                'address' => $group->pluck('address')->filter()->unique()->implode(', ') ?: null,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'no_dns' => $group->pluck('no_dn')->toArray(),
                'dn_count' => $group->count(),
                'delivery_datetime' => Carbon::parse($deliveryDate . ' ' . $firstItem->delivery_time),
                'is_all_mirror' => $allMirror,
                'has_mirror' => $group->contains(function ($item) {
                    return $item->is_mirror ?? false;
                }),
            ];
        })->sortBy('delivery_datetime')->values();
        
        // Calculate statistics
        $totalNormal = $grouped->where('status', 'normal')->count();
        $totalDelay = $grouped->where('status', 'delay')->count();
        $totalOnLoading = $grouped->where('status', 'on_loading')->count();
        
        // Pagination manual
        if ($perPage === 'all') {
            $groupedShippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $grouped,
                $grouped->count(),
                $grouped->count(),
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $perPageInt = (int)$perPage;
            $currentPageItems = $grouped->slice(($currentPage - 1) * $perPageInt, $perPageInt)->values();
            
            $groupedShippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentPageItems,
                $grouped->count(),
                $perPageInt,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }
        
        return view('shippings.index-reverse', compact(
            'groupedShippings',
            'totalNormal',
            'totalDelay',
            'totalOnLoading'
        ));
    }

    /**
     * Move preparation to Shipping. Address ditentukan OTOMATIS
     * berdasarkan Matrix Shipping (customers + dock + cycle),
     * default "Shipping 10" kalau kombinasi tidak ditemukan.
     */
    public function moveFromPreparation(Request $request)
    {
        $validated = $request->validate([
            'preparation_id' => 'required|exists:preparations,id',
        ]);

        DB::beginTransaction();
        
        try {
            // Get preparation data
            $preparation = Preparation::findOrFail($validated['preparation_id']);
            
            // Check if no_dn already exists in shippings
            $existingShipping = Shipping::where('no_dn', $preparation->no_dn)->first();
            if ($existingShipping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data dengan No DN ini sudah ada di Shipping!'
                ], 422);
            }
            
            // Tentukan address otomatis dari Matrix Shipping
            $address = $this->determineShippingAddress($preparation);
            
            // Calculate initial status - normal atau delay (belum scan)
            $deliveryDateTime = Carbon::parse($preparation->delivery_date->format('Y-m-d') . ' ' . $preparation->delivery_time);
            $now = Carbon::now();
            
            if ($now->greaterThan($deliveryDateTime)) {
                $status = 'delay';
            } else {
                $status = 'normal';
            }
            
            // Get user name dengan multiple fallback
            $movedBy = 'System';
            if (auth()->check()) {
                $user = auth()->user();
                $movedBy = $user->name ?? $user->email ?? 'User#' . $user->id;
            }
            
            // Create shipping record - arrival NULL (belum di-scan)
            $shipping = Shipping::create([
                'route' => $preparation->route,
                'logistic_partners' => $preparation->logistic_partners,
                'no_dn' => $preparation->no_dn,
                'customers' => $preparation->customers,
                'dock' => $preparation->dock,
                'delivery_date' => $preparation->delivery_date,
                'delivery_time' => $preparation->delivery_time,
                'arrival' => null, // Belum di-scan
                'cycle' => $preparation->cycle,
                'address' => $address,
                'status' => $status,
                'scan_to_shipping' => Carbon::now(),
                'moved_by' => $movedBy,
                'pulling_date' => $preparation->pulling_date,
                'pulling_time' => $preparation->pulling_time,
            ]);
            
            // Delete preparation permanently
            $preparation->forceDelete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dipindahkan ke ' . $address . '!',
                'data' => $shipping
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tentukan address Shipping otomatis dari Matrix Shipping
     * (customers + dock + cycle). Case-insensitive, sensitif spasi.
     * Default "Shipping 10" kalau kombinasi ga ketemu.
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

    public function moveToDelivery(Request $request)
    {
        $validated = $request->validate([
            'shipping_id' => 'required|exists:shippings,id',
        ]);

        DB::beginTransaction();
        
        try {
            $shipping = Shipping::findOrFail($validated['shipping_id']);
            
            // Check if no_dn already exists in deliveries
            $existingDelivery = Delivery::where('no_dn', $shipping->no_dn)->first();
            if ($existingDelivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data dengan No DN ini sudah ada di Delivery!'
                ], 422);
            }
            
            // Get user name
            $movedBy = 'System';
            if (auth()->check()) {
                $user = auth()->user();
                $movedBy = $user->name ?? $user->email ?? 'User#' . $user->id;
            }
            
            $now = Carbon::now();
            
            // Create delivery record
            $delivery = Delivery::create([
                'route' => $shipping->route,
                'logistic_partners' => $shipping->logistic_partners,
                'no_dn' => $shipping->no_dn,
                'customers' => $shipping->customers,
                'dock' => $shipping->dock,
                'cycle' => $shipping->cycle,
                'address' => $shipping->address,
                'status' => 'pending',
                'scan_to_delivery' => $now,
                'moved_by' => $movedBy,
                'pulling_date' => $shipping->pulling_date,
                'pulling_time' => $shipping->pulling_time,
                'delivery_date' => $shipping->delivery_date,
                'delivery_time' => $shipping->delivery_time,
                'scan_to_shipping' => $shipping->scan_to_shipping,
                'arrival' => $shipping->arrival,
            ]);
            
            // Create/update milkrun jika arrival sudah terisi
            if ($shipping->arrival) {
                $this->createOrUpdateMilkrun($shipping, $movedBy, $now);
            }
            
            // Delete shipping permanently
            $shipping->forceDelete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dipindahkan ke Delivery!',
                'data' => $delivery
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move shipping to delivery by scanning DN
     */
    public function scanToDelivery(Request $request)
    {
        $validated = $request->validate([
            'no_dn' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        
        try {
            // Find shipping by no_dn
            $shipping = Shipping::where('no_dn', $validated['no_dn'])->first();
            
            if (!$shipping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data shipping dengan DN "' . $validated['no_dn'] . '" tidak ditemukan!'
                ], 404);
            }
            
            // Check if no_dn already exists in deliveries
            $existingDelivery = Delivery::where('no_dn', $shipping->no_dn)->first();
            if ($existingDelivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data dengan No DN ini sudah ada di Delivery!'
                ], 422);
            }
            
            // Get user name
            $movedBy = 'System';
            if (auth()->check()) {
                $user = auth()->user();
                $movedBy = $user->name ?? $user->email ?? 'User#' . $user->id;
            }
            
            $now = Carbon::now();
            
            // Create delivery record
            $delivery = Delivery::create([
                'route' => $shipping->route,
                'logistic_partners' => $shipping->logistic_partners,
                'no_dn' => $shipping->no_dn,
                'customers' => $shipping->customers,
                'dock' => $shipping->dock,
                'cycle' => $shipping->cycle,
                'address' => $shipping->address,
                'status' => 'pending',
                'scan_to_delivery' => $now,
                'moved_by' => $movedBy,
                'pulling_date' => $shipping->pulling_date,
                'pulling_time' => $shipping->pulling_time,
                'delivery_date' => $shipping->delivery_date,
                'delivery_time' => $shipping->delivery_time,
                'scan_to_shipping' => $shipping->scan_to_shipping,
                'arrival' => $shipping->arrival,
            ]);
            
            // Create/update milkrun jika arrival sudah terisi
            if ($shipping->arrival) {
                $this->createOrUpdateMilkrun($shipping, $movedBy, $now);
            }
            
            // Delete shipping permanently
            $shipping->forceDelete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dipindahkan ke Delivery!',
                'data' => [
                    'no_dn' => $delivery->no_dn,
                    'route' => $delivery->route,
                    'customers' => $delivery->customers,
                    'dock' => $delivery->dock,
                    'cycle' => $delivery->cycle,
                    'scan_time' => $delivery->scan_to_delivery->format('d-m-Y H:i:s'),
                    'moved_by' => $movedBy,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move multiple shippings to delivery by route and cycle
     */
    public function moveToDeliveryByRoute(Request $request)
    {
        $validated = $request->validate([
            'route' => 'required|string|max:255',
            'cycle' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        
        try {
            // Build query
            $query = Shipping::where('route', $validated['route']);
            
            // Filter by cycle jika ada
            if (isset($validated['cycle']) && $validated['cycle'] !== null) {
                $query->where('cycle', $validated['cycle']);
            }
            
            $shippings = $query->get();
            
            if ($shippings->isEmpty()) {
                $cycleInfo = isset($validated['cycle']) ? " cycle {$validated['cycle']}" : "";
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data shipping dengan route "' . $validated['route'] . '"' . $cycleInfo
                ], 404);
            }
            
            // Get user name
            $movedBy = 'System';
            if (auth()->check()) {
                $user = auth()->user();
                $movedBy = $user->name ?? $user->email ?? 'User#' . $user->id;
            }
            
            $now = Carbon::now();
            $count = 0;
            $skipped = 0;
            
            // Pisahkan shipping yang sudah ada arrival dan yang belum
            $shippingsWithArrival = collect();
            $shippingsWithoutArrival = collect();
            
            foreach ($shippings as $shipping) {
                // Check if already exists
                if (Delivery::where('no_dn', $shipping->no_dn)->exists()) {
                    $skipped++;
                    continue;
                }
                
                Delivery::create([
                    'route' => $shipping->route,
                    'logistic_partners' => $shipping->logistic_partners,
                    'no_dn' => $shipping->no_dn,
                    'customers' => $shipping->customers,
                    'dock' => $shipping->dock,
                    'cycle' => $shipping->cycle,
                    'address' => $shipping->address,
                    'status' => 'pending',
                    'scan_to_delivery' => $now,
                    'moved_by' => $movedBy,
                    'pulling_date' => $shipping->pulling_date,
                    'pulling_time' => $shipping->pulling_time,
                    'delivery_date' => $shipping->delivery_date,
                    'delivery_time' => $shipping->delivery_time,
                    'scan_to_shipping' => $shipping->scan_to_shipping,
                    'arrival' => $shipping->arrival,
                ]);
                
                // Pisahkan berdasarkan arrival
                if ($shipping->arrival) {
                    $shippingsWithArrival->push($shipping);
                } else {
                    $shippingsWithoutArrival->push($shipping);
                }
                
                $shipping->forceDelete();
                $count++;
            }
            
            // Create milkrun untuk shipping yang sudah ada arrival
            if ($shippingsWithArrival->isNotEmpty()) {
                $this->createMilkrunFromGroup($shippingsWithArrival, $movedBy, $now);
            }
            
            DB::commit();
            
            $cycleInfo = isset($validated['cycle']) ? " Cycle {$validated['cycle']}" : "";
            $message = "Berhasil memindahkan {$count} data dengan route \"{$validated['route']}\"{$cycleInfo} ke Delivery";
            if ($skipped > 0) {
                $message .= " ({$skipped} data dilewati karena sudah ada)";
            }
            if ($shippingsWithoutArrival->isNotEmpty()) {
                $message .= " • {$shippingsWithoutArrival->count()} DN belum masuk milkrun (arrival belum terisi)";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'moved_count' => $count,
                'skipped_count' => $skipped,
                'milkrun_created' => $shippingsWithArrival->isNotEmpty()
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or update milkrun record from single shipping
     */
    private function createOrUpdateMilkrun(Shipping $shipping, string $movedBy, Carbon $scanTime = null): Milkrun
    {
        $departureTime = $scanTime ?? Carbon::now();
        
        $milkrun = Milkrun::where('route', $shipping->route)
            ->where('cycle', $shipping->cycle)
            ->where('delivery_date', $shipping->delivery_date)
            ->first();
        
        if ($milkrun) {
            $noDns = $milkrun->no_dns ?? [];
            if (!in_array($shipping->no_dn, $noDns)) {
                $noDns[] = $shipping->no_dn;
            }
            
            $existingCustomers = array_filter(explode(', ', $milkrun->customers));
            $newCustomers = array_filter(explode(', ', $shipping->customers));
            $allCustomers = array_unique(array_merge($existingCustomers, $newCustomers));
            
            $existingDocks = array_filter(explode(', ', $milkrun->dock ?? ''));
            $newDocks = array_filter(explode(', ', $shipping->dock));
            $allDocks = array_unique(array_merge($existingDocks, $newDocks));
            
            $milkrun->update([
                'customers' => implode(', ', $allCustomers),
                'dock' => implode(', ', $allDocks),
                'no_dns' => $noDns,
                'dn_count' => count($noDns),
                'departure' => $departureTime,
            ]);
        } else {
            $milkrun = Milkrun::create([
                'customers' => $shipping->customers,
                'route' => $shipping->route,
                'logistic_partners' => $shipping->logistic_partners,
                'cycle' => $shipping->cycle,
                'dock' => $shipping->dock,
                'delivery_date' => $shipping->delivery_date,
                'delivery_time' => $shipping->delivery_time,
                'arrival' => $shipping->arrival,
                'departure' => $departureTime,
                'status' => 'pending',
                'dn_count' => 1,
                'no_dns' => [$shipping->no_dn],
                'address' => $shipping->address,
                'moved_by' => $movedBy,
            ]);
            
            $milkrun->status = $milkrun->calculateStatus();
            $milkrun->save();
        }
        
        return $milkrun;
    }

    /**
     * Create milkrun from group of shippings
     */
    private function createMilkrunFromGroup($shippings, string $movedBy, Carbon $scanTime = null): Milkrun
    {
        $firstShipping = $shippings->first();
        $departureTime = $scanTime ?? Carbon::now();
        
        $milkrun = Milkrun::where('route', $firstShipping->route)
            ->where('cycle', $firstShipping->cycle)
            ->where('delivery_date', $firstShipping->delivery_date)
            ->first();
        
        $allCustomers = $shippings->pluck('customers')->flatMap(function($c) {
            return array_filter(explode(', ', $c));
        })->unique()->values()->toArray();
        
        $allDocks = $shippings->pluck('dock')->flatMap(function($d) {
            return array_filter(explode(', ', $d));
        })->unique()->values()->toArray();
        
        $allLps = $shippings->pluck('logistic_partners')->flatMap(function($lp) {
            return array_filter(explode(', ', $lp));
        })->unique()->values()->toArray();
        
        $allDns = $shippings->pluck('no_dn')->toArray();
        
        if ($milkrun) {
            $existingDns = $milkrun->no_dns ?? [];
            $mergedDns = array_unique(array_merge($existingDns, $allDns));
            
            $existingCustomers = array_filter(explode(', ', $milkrun->customers));
            $mergedCustomers = array_unique(array_merge($existingCustomers, $allCustomers));
            
            $existingDocks = array_filter(explode(', ', $milkrun->dock ?? ''));
            $mergedDocks = array_unique(array_merge($existingDocks, $allDocks));
            
            $milkrun->update([
                'customers' => implode(', ', $mergedCustomers),
                'dock' => implode(', ', $mergedDocks),
                'no_dns' => $mergedDns,
                'dn_count' => count($mergedDns),
                'departure' => $departureTime,
            ]);
        } else {
            $milkrun = Milkrun::create([
                'customers' => implode(', ', $allCustomers),
                'route' => $firstShipping->route,
                'logistic_partners' => implode(', ', $allLps),
                'cycle' => $firstShipping->cycle,
                'dock' => implode(', ', $allDocks),
                'delivery_date' => $firstShipping->delivery_date,
                'delivery_time' => $firstShipping->delivery_time,
                'arrival' => $firstShipping->arrival,
                'departure' => $departureTime,
                'status' => 'pending',
                'dn_count' => count($allDns),
                'no_dns' => $allDns,
                'address' => $shippings->pluck('address')->unique()->filter()->implode(', '),
                'moved_by' => $movedBy,
            ]);
            
            $milkrun->status = $milkrun->calculateStatus();
            $milkrun->save();
        }
        
        return $milkrun;
    }

    /**
     * Find shipping by DN number
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
        
        $shipping = Shipping::where('no_dn', $noDn)->first();
        
        if (!$shipping) {
            return response()->json([
                'success' => false,
                'message' => 'Data shipping dengan DN ' . $noDn . ' tidak ditemukan',
                'data' => null
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Data ditemukan',
            'data' => [
                'id' => $shipping->id,
                'no_dn' => $shipping->no_dn,
                'route' => $shipping->route,
                'logistic_partners' => $shipping->logistic_partners,
                'customers' => $shipping->customers,
                'dock' => $shipping->dock,
                'cycle' => $shipping->cycle,
                'address' => $shipping->address,
                'delivery_date' => $shipping->delivery_date->format('Y-m-d'),
                'delivery_time' => $shipping->delivery_time,
            ]
        ]);
    }

    /**
     * Get shipping data for edit
     */
    public function edit(Shipping $shipping)
    {
        if (request()->ajax()) {
            return response()->json([
                'id' => $shipping->id,
                'route' => $shipping->route,
                'logistic_partners' => $shipping->logistic_partners,
                'no_dn' => $shipping->no_dn,
                'customers' => $shipping->customers,
                'dock' => $shipping->dock,
                'delivery_date' => $shipping->delivery_date->format('Y-m-d'),
                'delivery_time' => date('H:i', strtotime($shipping->delivery_time)),
                'cycle' => $shipping->cycle,
                'address' => $shipping->address,
            ]);
        }
        
        return view('shippings.edit', compact('shipping'));
    }

    /**
     * Update shipping data
     */
    public function update(Request $request, Shipping $shipping)
    {
        $validated = $request->validate([
            'route' => 'required|string|max:255',
            'logistic_partners' => 'required|string|max:255',
            'no_dn' => 'required|string|max:255|unique:shippings,no_dn,' . $shipping->id,
            'customers' => 'required|string|max:255',
            'dock' => 'required|string|max:255',
            'delivery_date' => 'required|date',
            'delivery_time' => 'required',
            'cycle' => 'required|integer|min:1',
            'address' => 'required|string|max:50',
        ]);

        try {
            // Nilai lama, buat menyamakan dashboard (grup prep monitoring) setelah diedit
            $oldNoDn = $shipping->no_dn;
            $before = $shipping->only(['customers', 'route', 'dock', 'cycle', 'delivery_time', 'address']);
            $before['delivery_date'] = optional($shipping->delivery_date)->format('Y-m-d');

            DB::transaction(function () use ($shipping, $validated, $oldNoDn, $before) {
                $shipping->update($validated);
                app(\App\Services\PrepMonitoringSync::class)->afterDnEdited($oldNoDn, $before, $validated);
            });

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data shipping berhasil diupdate',
                    'data' => $shipping
                ]);
            }
            
            return redirect()
                ->route('shippings.index')
                ->with('success', 'Data shipping berhasil diupdate!');
        } catch (\Exception $e) {
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

    /**
     * Delete shipping
     */
    public function destroy(Shipping $shipping)
    {
        try {
            $shipping->forceDelete();
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data shipping berhasil dihapus!'
                ]);
            }
            
            return redirect()
                ->route('shippings.index')
                ->with('success', 'Data shipping berhasil dihapus!');
        } catch (\Exception $e) {
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

    /**
     * Delete all shippings
     */
    public function deleteAll()
    {
        try {
            $count = Shipping::count();
            Shipping::withTrashed()->forceDelete();
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$count} data shipping"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Checking LP Page - untuk scan route dan update arrival
     */
    public function checkingLp()
    {
        $shippings = Shipping::whereNull('arrival')
            ->orderBy('route')
            ->orderByRaw("CONCAT(delivery_date, ' ', delivery_time) ASC")
            ->get();
        
        $availableRoutes = Shipping::whereNull('arrival')
            ->select('route')
            ->distinct()
            ->pluck('route');
        
        return view('shippings.checking-lp', compact('shippings', 'availableRoutes'));
    }

    /**
     * Check route - cek apakah route punya multiple cycles
     */
    public function checkRoute(Request $request)
    {
        $validated = $request->validate([
            'route' => 'required|string|max:255',
        ]);

        try {
            $shippings = Shipping::where('route', $validated['route'])
                ->whereNull('arrival')
                ->get();
            
            if ($shippings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data shipping dengan route "' . $validated['route'] . '" yang belum di-scan!'
                ], 404);
            }
            
            $cycles = $shippings->pluck('cycle')->unique()->sort()->values();
            
            return response()->json([
                'success' => true,
                'route' => $validated['route'],
                'cycles' => $cycles,
                'has_multiple_cycles' => $cycles->count() > 1,
                'total_data' => $shippings->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal check route: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Scan route - update arrival untuk shipping dengan route dan cycle tertentu
     */
    public function scanRoute(Request $request)
    {
        $validated = $request->validate([
            'route' => 'required|string|max:255',
            'cycle' => 'nullable|integer',
        ]);

        try {
            $query = Shipping::where('route', $validated['route'])
                ->whereNull('arrival');
            
            if (isset($validated['cycle']) && $validated['cycle'] !== null) {
                $query->where('cycle', $validated['cycle']);
            }
            
            $shippings = $query->get();
            
            if ($shippings->isEmpty()) {
                $cycleInfo = isset($validated['cycle']) ? " cycle {$validated['cycle']}" : "";
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data shipping dengan route "' . $validated['route'] . '"' . $cycleInfo . ' yang belum di-scan!'
                ], 404);
            }
            
            $now = Carbon::now();
            $count = 0;
            
            foreach ($shippings as $shipping) {
                $shipping->arrival = $now;
                // Status akan otomatis dihitung ulang
                $shipping->status = $shipping->calculateStatus();
                $shipping->save();
                $count++;
            }
            
            $cycleInfo = isset($validated['cycle']) ? " Cycle {$validated['cycle']}" : "";
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil scan {$count} data dengan route \"{$validated['route']}\"{$cycleInfo}",
                'scanned_count' => $count,
                'arrival_time' => $now->format('d-m-Y H:i:s')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal scan route: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shippings by route untuk display di checking LP
     */
    public function getByRoute(Request $request)
    {
        $route = $request->get('route');
        
        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route tidak boleh kosong'
            ], 422);
        }
        
        $shippings = Shipping::where('route', $route)
            ->orderByRaw("CONCAT(delivery_date, ' ', delivery_time) ASC")
            ->get()
            ->map(function ($shipping) {
                return [
                    'id' => $shipping->id,
                    'route' => $shipping->route,
                    'logistic_partners' => $shipping->logistic_partners,
                    'no_dn' => $shipping->no_dn,
                    'customers' => $shipping->customers,
                    'dock' => $shipping->dock,
                    'delivery_date' => $shipping->delivery_date->format('d-m-y'),
                    'delivery_time' => date('H:i:s', strtotime($shipping->delivery_time)),
                    'cycle' => $shipping->cycle,
                    'address' => $shipping->address,
                    'arrival' => $shipping->arrival ? $shipping->arrival->format('d-m-y H:i') : null,
                    'status' => $shipping->status_label,
                    'status_badge' => $shipping->status_badge,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $shippings
        ]);
    }

    public function andon(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        
        // Get real shippings
        $realShippings = Shipping::all()->map(function ($ship) {
            $statusInfo = $this->calculateShippingStatus($ship);
            $ship->status = $statusInfo['status'];
            $ship->status_label = $statusInfo['status_label'];
            $ship->status_badge = $statusInfo['status_badge'];
            $ship->is_mirror = false;
            return $ship;
        });
        
        // Get mirrored preparations
        $mirroredPreparations = $this->getMirroredPreparations();
        
        // Combine both collections
        $combined = $realShippings->concat($mirroredPreparations);
        
        // Sort by delivery datetime ASC
        $combined = $combined->sortBy(function ($item) {
            $date = is_string($item->delivery_date) ? $item->delivery_date : $item->delivery_date->format('Y-m-d');
            return $date . ' ' . $item->delivery_time;
        })->values();
        
        // Calculate statistics from combined data - PLANNING tidak masuk summary
        $totalAll = $combined->count();
        $totalNormal = $combined->where('status', 'normal')->count();
        $totalDelay = $combined->where('status', 'delay')->count();
        $totalOnLoading = $combined->where('status', 'on_loading')->count();
        
        // Pagination
        if ($perPage === 'all') {
            $shippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $combined,
                $combined->count(),
                $combined->count(),
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $perPageInt = (int)$perPage;
            $currentPageItems = $combined->slice(($currentPage - 1) * $perPageInt, $perPageInt)->values();
            
            $shippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentPageItems,
                $combined->count(),
                $perPageInt,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }
        
        $recentScan = \App\Models\Delivery::whereNotNull('scan_to_delivery')
            ->orderBy('scan_to_delivery', 'desc')
            ->first();
        
        return view('andon.shippings', compact(
            'shippings', 
            'totalNormal', 
            'totalDelay', 
            'totalOnLoading',
            'totalAll',
            'recentScan'
        ));
    }

    public function andonReverse(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        
        // Get real shippings
        $realShippings = Shipping::all()->map(function ($ship) {
            $ship->is_mirror = false;
            return $ship;
        });
        
        // Get mirrored preparations
        $mirroredPreparations = $this->getMirroredPreparations();
        
        // Combine both collections
        $allShippings = $realShippings->concat($mirroredPreparations);
        
        // Group by route and cycle
        $grouped = $allShippings->groupBy(function($item) {
            return $item->route . '|' . $item->cycle;
        })->map(function($group) {
            $firstItem = $group->first();
            
            // Check if any item has arrival
            $hasArrival = $group->contains(function ($item) {
                return $item->arrival !== null;
            });
            
            // Check if all items are mirror
            $allMirror = $group->every(function ($item) {
                return $item->is_mirror ?? false;
            });
            
            // Calculate status
            if ($hasArrival) {
                $statusLabel = 'ON LOADING';
                $statusBadge = 'bg-primary';
                $status = 'on_loading';
            } elseif ($allMirror) {
                $deliveryDate = is_string($firstItem->delivery_date) 
                    ? $firstItem->delivery_date 
                    : $firstItem->delivery_date->format('Y-m-d');
                $deliveryDateTime = Carbon::parse($deliveryDate . ' ' . $firstItem->delivery_time);
                $now = Carbon::now();
                
                if ($now->greaterThan($deliveryDateTime)) {
                    $statusLabel = 'DELAY';
                    $statusBadge = 'bg-danger';
                    $status = 'delay';
                } else {
                    $statusLabel = 'PLANNING';
                    $statusBadge = 'bg-secondary';
                    $status = 'planning';
                }
            } else {
                $deliveryDate = is_string($firstItem->delivery_date) 
                    ? $firstItem->delivery_date 
                    : $firstItem->delivery_date->format('Y-m-d');
                $deliveryDateTime = Carbon::parse($deliveryDate . ' ' . $firstItem->delivery_time);
                $now = Carbon::now();
                
                if ($now->greaterThan($deliveryDateTime)) {
                    $statusLabel = 'DELAY';
                    $statusBadge = 'bg-danger';
                    $status = 'delay';
                } else {
                    $statusLabel = 'NORMAL';
                    $statusBadge = 'bg-success';
                    $status = 'normal';
                }
            }
            
            $arrivalItem = $group->first(function ($item) {
                return $item->arrival !== null;
            });
            
            $deliveryDate = is_string($firstItem->delivery_date) 
                ? $firstItem->delivery_date 
                : $firstItem->delivery_date->format('Y-m-d');
            
            return [
                'route' => $firstItem->route,
                'logistic_partners' => $group->pluck('logistic_partners')->unique()->filter()->implode(', '),
                'cycle' => $firstItem->cycle,
                'customers' => $group->pluck('customers')->unique()->filter()->implode(', '),
                'dock' => $group->pluck('dock')->unique()->filter()->implode(', '),
                'delivery_date' => Carbon::parse($deliveryDate)->format('d-m-y'),
                'delivery_time' => date('H:i:s', strtotime($firstItem->delivery_time)),
                'arrival' => $arrivalItem && $arrivalItem->arrival 
                    ? (is_string($arrivalItem->arrival) ? $arrivalItem->arrival : $arrivalItem->arrival->format('d-m-y H:i')) 
                    : null,
                'address' => $group->pluck('address')->filter()->unique()->implode(', ') ?: null,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'no_dns' => $group->pluck('no_dn')->toArray(),
                'dn_count' => $group->count(),
                'delivery_datetime' => Carbon::parse($deliveryDate . ' ' . $firstItem->delivery_time),
            ];
        })->sortBy('delivery_datetime')->values();
        
        // Statistics
        $totalNormal = $grouped->where('status', 'normal')->count();
        $totalDelay = $grouped->where('status', 'delay')->count();
        $totalOnLoading = $grouped->where('status', 'on_loading')->count();
        
        // Pagination manual
        if ($perPage === 'all') {
            $groupedShippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $grouped,
                $grouped->count(),
                $grouped->count(),
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $perPageInt = (int)$perPage;
            $currentPageItems = $grouped->slice(($currentPage - 1) * $perPageInt, $perPageInt)->values();
            
            $groupedShippings = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentPageItems,
                $grouped->count(),
                $perPageInt,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }
        
        $recentScan = \App\Models\Delivery::whereNotNull('scan_to_delivery')
            ->orderBy('scan_to_delivery', 'desc')
            ->first();
        
        return view('andon.shippings-group', compact(
            'groupedShippings',
            'totalNormal',
            'totalDelay',
            'totalOnLoading',
            'recentScan'
        ));
    }
}