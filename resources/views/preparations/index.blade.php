@extends('layouts.app')

@section('title', 'Data Preparation')
@section('page-title', 'PREPARATIONS MONITORING')
@section('body-class', 'preparation-page')

@section('content')
    <!-- Stats Badges dan Dropdown di kanan -->
    <div class="d-flex justify-content-end align-items-center gap-2 mb-3 mt-3">
        
        <!-- Scan DN Input -->
        <div class="input-group" style="width: 280px;">
            <span class="input-group-text bg-white text-white">
                <i class="bi bi-qr-code-scan text-dark"></i>
            </span>
            <input type="text" class="form-control" id="scanDnInput" placeholder="Scan DN to Shipping..." autofocus>
        </div>

        @if(auth()->user()->role === 'superadmin')
            <!-- Delete All Button -->
            <div class="card border-0 shadow-sm p-1 bg-danger">
                <button type="button" class="btn btn-danger" id="deleteAllButton" title="Hapus Semua Data">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </div>
        @endif
        
        <!-- Show By Dropdown -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-1">
                <select class="form-select form-select-sm border-0" id="perPageSelect" style="width: auto;">
                    <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="500" {{ request('per_page') == 500 ? 'selected' : '' }}>500</option>
                    <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="input-group" style="width: 300px;">
            <input type="text" class="form-control" id="searchInput" placeholder="Cari Route, LP, DN, Customer..." value="{{ request('search') }}">
            <button class="btn btn-secondary" type="button" id="searchButton">
                <i class="bi bi-search"></i>
            </button>
        </div>

        <!-- Menu & Badges -->
        <div class="d-flex align-items-center gap-2">
            <!-- Dropdown Menu Button -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2">
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-0 m-0" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
                            <i class="bi bi-three-dots-vertical fs-4"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuButton">
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createPreparationModal">
                                    <i class="bi bi-plus-circle me-2"></i> Tambah Data
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#lpConfigModal">
                                    <i class="bi bi-gear-fill me-2"></i> Konfigurasi LP
                                </a>
                                {{-- Konfigurasi Lead Time ADM dihapus — diganti Matrix Pulling --}}
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#pullingMatrixModal">
                                    <i class="bi bi-table me-2"></i> Matrix Pulling
                                </a>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shippingMatrixModal">
                                    <i class="bi bi-signpost-split me-2"></i> Matrix Shipping
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item">
                                        <i class="bi bi-upload me-2"></i> Import Options :
                                    </a>
                                    <a class="dropdown-item text-success" href="#" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                        <i class="bi bi-file-earmark-excel text-success me-1 ms-4"></i> Excel
                                    </a>
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importTmminModal">
                                        <i class="bi bi-file-earmark-text text-dark me-1 ms-4"></i> TMMIN
                                    </a>
                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#importAdmModal">
                                        <i class="bi bi-file-earmark-spreadsheet text-danger me-1 ms-4"></i> ADM
                                    </a>
                                </li>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- On Time Badge -->
            <div class="bg-success card border-0 shadow-sm">
                <div class="card-body p-1">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-check-circle text-white  fs-5"></i>
                        </div>
                        <div>
                            <small class="text-white d-block fw-bold me-3" style="font-size: 0.7rem;">Open</small>
                            <h5 class="mb-0 fw-bold text-white">{{ $totalOnTime }}</h5>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Delay Badge -->
            <div class="bg-danger card border-0 shadow-sm me-3">
                <div class="card-body p-1">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-exclamation-triangle text-white fs-5"></i>
                        </div>
                        <div>
                            <small class="text-white d-block fw-bold me-3" style="font-size: 0.7rem;">Delay</small>
                            <h5 class="mb-0 fw-bold text-white">{{ $totalDelay }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>  

     @if($recentScan)
    <div class="card border-0 shadow-sm mb-3 ms-3 me-3"
     style="border-radius:0; background-color:#000000; outline:2px solid #ffffff;">

        <div class="card-body py-1 px-4">
            <div class="d-flex align-items-center justify-content-center gap-3">

                <!-- Icon & Label -->
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <i class="bi bi-arrow-right-circle-fill text-white fs-6"></i>
                    </div>
                    <small class="text-white fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">RECENT SCAN</small>
                </div>

                <!-- Vertical Divider -->
                <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                <!-- No DN -->
                <div class="d-flex align-items-center gap-2">
                    <small class="text-white" style="font-size: 1rem;">No DN:</small>
                    <strong class="text-white">{{ $recentScan->no_dn }}</strong>
                </div>

                <!-- Vertical Divider -->
                <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                <!-- Route -->
                <div class="d-flex align-items-center gap-2">
                    <small class="text-white" style="font-size: 1rem;">Route:</small>
                    <span class="fw-semibold text-white">{{ $recentScan->route }}</span>
                </div>

                <!-- Vertical Divider -->
                <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                <!-- Dock -->
                <div class="d-flex align-items-center gap-2">
                    <small class="text-white" style="font-size: 1rem;">Dock:</small>
                    <span class="fw-semibold text-white">{{ $recentScan->dock }}</span>
                </div>

                <!-- Vertical Divider -->
                <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                <!-- Cycle -->
                <div class="d-flex align-items-center gap-2">
                    <small class="text-white" style="font-size: 1rem;">Cycle:</small>
                    <span class="fw-semibold text-white">{{ $recentScan->cycle }}</span>
                </div>

                <!-- Vertical Divider -->
                <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                <!-- Customer -->
                <div class="d-flex align-items-center gap-2">
                    <small class="text-white" style="font-size: 1rem;">Customer:</small>
                    <span class="fw-semibold text-white">{{ $recentScan->customers }}</span>
                </div>

                <!-- Vertical Divider -->
                <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                <!-- Moved By -->
                <div class="d-flex align-items-center gap-2">
                    <small class="text-white" style="font-size: 1rem;">Scan by:</small>
                    <span class="fw-semibold text-white">
                        <i class="bi bi-person-fill"></i> {{ $recentScan->moved_by ?? 'System' }}
                    </span>
                </div>

                <!-- Timestamp -->
                <div class="d-flex align-items-center gap-2">
                    <span class="text-white fw-bold">
                        <i class="bi bi-clock-fill"></i> {{ $recentScan->scan_to_shipping->format('H:i:s') }}
                    </span>
                    <span class="text-white fw-bold">{{ $recentScan->scan_to_shipping->format('d/m/Y') }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="table-responsive p-0 mt-0">
        <table class="table table-compact w-100 mt-1" id="preparationsTable">
            <thead>
                <tr class="fs-5">
                    <th>Route</th>
                    <th>LP</th>
                    <th>No DN</th>
                    <th>Cust</th>
                    <th>Dock</th>
                    <th>Delv Date</th>
                    <th>Delv Time</th>
                    <th>Cyc</th>
                    <th>Qty Kbn</th>
                    <th>Pull Date</th>
                    <th>Finish Pulling</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($preparations as $index => $prep)
                    <tr class="fs-4 {{ $prep->status === 'delay' ? 'table-danger-subtle' : '' }}">
                        <td><strong>{{ $prep->route }}</strong></td>
                        <td>{{ $prep->logistic_partners }}</td>
                        <td>{{ $prep->no_dn }}</td>
                        <td>{{ $prep->customers }}</td>
                        <td><strong>{{ $prep->dock }}</strong></td>
                        <td>{{ $prep->delivery_date->format('d-m-y') }}</td>
                        <td>{{ date('H:i:s', strtotime($prep->delivery_time)) }}</td>
                        <td><strong>{{ $prep->cycle }}</strong></td>
                        <td>{{ $prep->qty_kbn ?? '-' }}</td>
                        <td>{{ $prep->pulling_date->format('d-m-y') }}</td>
                        <td>{{ date('H:i:s', strtotime($prep->pulling_time)) }}</td>
                        <td>
                            <span class="badge {{ $prep->status_badge }} fw-bold px-3 py-2 mb-1 mt-1 ms-1 me-1
                                {{ $prep->status === 'delay' ? 'badge-delay' : '' }}"
                                title="{{ $prep->status === 'delay' ? 'Terlambat ' . $prep->delay_duration : 'On Time' }}">
                                {{ $prep->status_label }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center" style="gap: 0;">
                                <button onclick="openEditModal({{ $prep->id }})" class="btn btn-warning btn-sm btn-action-square" style="border-radius: 6px 0 0 6px; margin: 0;" title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                
                                <form action="{{ route('preparations.destroy', $prep->id) }}" method="POST" class="d-inline delete-form" style="margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm btn-action-square" style="border-radius: 0; margin: 0;" title="Hapus">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>

                                <a href="javascript:void(0)" onclick="showQrCode('{{ $prep->no_dn }}')" class="btn btn-secondary btn-sm btn-action-square" style="border-radius: 0; margin: 0;" title="QR Code">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                                
                                <a href="javascript:void(0)" onclick="openMoveToShippingModal({{ $prep->id }})" class="btn btn-primary btn-sm btn-action-square" style="border-radius: 0 6px 6px 0; margin: 0;" title="Move to Shipping">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="mt-3">
                        <td colspan="12" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Belum ada data preparation</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrapper ">
        {{ $preparations->links() }}
    </div>

    <!-- Modal Pilih PR & Shipping (kombinasi baru via halaman Preparation) -->
    <div class="modal fade" id="selectPrShippingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Pilih PR & Shipping</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="selectPrShippingInfo" class="mb-3 small text-muted"></div>

                    <label class="form-label fw-bold">Preparation (PR)</label>
                    <div class="row g-2 mb-3">
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-shipping-pr-btn" data-pr="1">PR 1</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-shipping-pr-btn" data-pr="2">PR 2</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-shipping-pr-btn" data-pr="3">PR 3</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-shipping-pr-btn" data-pr="4">PR 4</button></div>
                        <div class="col-12"><button type="button" class="btn btn-outline-primary w-100 pr-shipping-pr-btn" data-pr="5">AB (Abnormal)</button></div>
                    </div>

                    <label class="form-label fw-bold">Shipping</label>
                    <select class="form-select mb-3" id="shippingAddressSelect">
                        <option value="">-- Pilih Shipping --</option>
                        <optgroup label="Shipping">
                            @for($i = 1; $i <= 13; $i++)
                                <option value="Shipping {{ $i }}">Shipping {{ $i }}</option>
                            @endfor
                        </optgroup>
                        <optgroup label="Shipping Export">
                            @for($i = 1; $i <= 3; $i++)
                                <option value="Shipping Export {{ $i }}">Shipping Export {{ $i }}</option>
                            @endfor
                        </optgroup>
                    </select>

                    <button type="button" class="btn btn-success w-100" id="confirmPrShippingBtn">
                        <i class="bi bi-check-circle me-1"></i> Konfirmasi & Pindahkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('preparations.create')
    @include('preparations.edit')
    @include('preparations.import')
    @include('preparations.import-tmmin')
    @include('preparations.lp-config')
    @include('preparations.import-adm')
    {{-- adm-lead-time-config dihapus, diganti pulling-matrix-config --}}
    @include('preparations.pulling-matrix-config')
    @include('preparations.matrix-shipping')

    
@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
@push('scripts')
<script>
    // ==================== SCAN DN TO SHIPPING (langsung, address manual) ====================
    // Scope GLOBAL (di luar $(document).ready) supaya bisa dipanggil juga dari
    // openMoveToShippingModal() yang dipicu onclick panah di kolom Actions.
    let scanTimeout;
    let pendingNoDn = null;
    let selectedPrNumber = null;

    // withConfirm = true dipakai buat klik panah "Move to Shipping" di Actions
    // (biar ga langsung eksekusi tanpa konfirmasi kalau kombinasinya udah ada)
    function checkDnForShipping(noDn, withConfirm) {
        $.ajax({
            url: '{{ route("prep-monitoring.check-dn") }}',
            type: 'GET',
            data: { no_dn: noDn },
            success: function(response) {
                if (!response.success) {
                    Swal.fire({
                        title: 'Gagal!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#dc2626',
                        timer: 3000,
                        timerProgressBar: true
                    });
                    $('#scanDnInput').val('').focus();
                    return;
                }

                if (response.needs_pr_selection) {
                    pendingNoDn = noDn;
                    const d = response.data;
                    $('#selectPrShippingInfo').html(`
                        <strong>DN:</strong> ${d.no_dn}<br>
                        <strong>Customer:</strong> ${d.customers} | <strong>Cycle:</strong> ${d.cycle} | <strong>Dock:</strong> ${d.dock}<br>
                        <strong>Kbn:</strong> ${d.kbn} &nbsp;|&nbsp; <strong>Skid (target):</strong> ${d.skid}
                    `);
                    new bootstrap.Modal(document.getElementById('selectPrShippingModal')).show();
                    return;
                }

                const d = response.data;

                if (withConfirm) {
                    Swal.fire({
                        title: 'Pindahkan ke Shipping?',
                        html: `
                            <div class="text-start">
                                <small><strong>DN:</strong> ${d.no_dn}</small><br>
                                <small><strong>Route:</strong> ${d.route} | <strong>Dock:</strong> ${d.dock} | <strong>Cycle:</strong> ${d.cycle}</small>
                            </div>
                            <div class="text-muted small mt-2">Tujuan: <strong>${d.shipping_address}</strong> (Progress saat ini: ${d.progress})</div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Pindahkan!',
                        cancelButtonText: 'Batal',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeScanDirect(noDn, null, null);
                        } else {
                            $('#scanDnInput').val('').focus();
                        }
                    });
                } else {
                    executeScanDirect(noDn, null, null);
                }
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error!',
                    text: xhr.responseJSON?.message || 'Terjadi kesalahan saat mencari DN',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
                $('#scanDnInput').val('').focus();
            }
        });
    }

    function executeScanDirect(noDn, prNumber, shippingAddress) {
        $.ajax({
            url: '{{ route("prep-monitoring.scan-direct") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                no_dn: noDn,
                pr_number: prNumber,
                shipping_address: shippingAddress
            },
            success: function(response) {
                $('#scanDnInput').val('');
                Swal.fire({
                    title: 'Berhasil!',
                    html: response.message,
                    icon: 'success',
                    confirmButtonColor: '#059669',
                    timer: 2500,
                    timerProgressBar: true
                }).then(() => {
                    window.location.reload();
                });
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Gagal!',
                    text: xhr.responseJSON?.message || 'Terjadi kesalahan saat memindahkan data',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
                $('#scanDnInput').focus();
            }
        });
    }
    // ==================== END SCAN DN TO SHIPPING (global functions) ====================

    $(document).ready(function() {

        $('#scanDnInput').on('input', function() {
            clearTimeout(scanTimeout);
            const noDn = $(this).val().trim();

            if (noDn.length > 0) {
                // Delay 500ms untuk menunggu scanner selesai input
                scanTimeout = setTimeout(function() {
                    checkDnForShipping(noDn, false);
                }, 500);
            }
        });

        // Handle Enter key pada scan input
        $('#scanDnInput').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(scanTimeout);
                const noDn = $(this).val().trim();
                if (noDn.length > 0) {
                    checkDnForShipping(noDn, false);
                }
            }
        });

        $(document).on('click', '.pr-shipping-pr-btn', function() {
            selectedPrNumber = $(this).data('pr');
            $('.pr-shipping-pr-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
        });

        $('#confirmPrShippingBtn').on('click', function() {
            const shippingAddress = $('#shippingAddressSelect').val();

            if (!selectedPrNumber) {
                Swal.fire({ title: 'Pilih PR dulu!', icon: 'warning', confirmButtonColor: '#dc2626', timer: 1800, timerProgressBar: true });
                return;
            }
            if (!shippingAddress) {
                Swal.fire({ title: 'Pilih Shipping dulu!', icon: 'warning', confirmButtonColor: '#dc2626', timer: 1800, timerProgressBar: true });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('selectPrShippingModal')).hide();
            executeScanDirect(pendingNoDn, selectedPrNumber, shippingAddress);
        });

        $('#selectPrShippingModal').on('hidden.bs.modal', function() {
            pendingNoDn = null;
            selectedPrNumber = null;
            $('#shippingAddressSelect').val('');
            $('.pr-shipping-pr-btn').removeClass('btn-primary').addClass('btn-outline-primary');
        });

        // Delete confirmation dengan SweetAlert
        $('.delete-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const url = form.attr('action');
            
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data ini akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'DELETE'
                        },
                        success: function(response) {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: response.message || 'Data berhasil dihapus',
                                icon: 'success',
                                confirmButtonColor: '#059669'
                            }).then(() => {
                                window.location.reload();
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: 'Gagal!',
                                text: xhr.responseJSON?.message || 'Terjadi kesalahan saat menghapus data',
                                icon: 'error',
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    });
                }
            });
        });

        // Delete All Button
        $('#deleteAllButton').on('click', function() {
            Swal.fire({
                title: 'PERINGATAN!',
                text: "Apakah Anda yakin ingin menghapus SEMUA data preparation? Tindakan ini tidak dapat dibatalkan!",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus Semua!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                input: 'text',
                inputPlaceholder: 'Ketik "HAPUS SEMUA" untuk konfirmasi',
                inputValidator: (value) => {
                    if (value !== 'HAPUS SEMUA') {
                        return 'Anda harus mengetik "HAPUS SEMUA" untuk melanjutkan!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus Semua Data...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: '{{ route("preparations.deleteAll") }}',
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonColor: '#059669'
                            }).then(() => {
                                window.location.reload();
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: 'Gagal!',
                                text: 'Terjadi kesalahan saat menghapus data',
                                icon: 'error',
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    });
                }
            });
        });

        // Handle Import Excel Form
        $('#importExcelForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const fileInput = $('#excelFile')[0];
            
            if (!fileInput.files.length) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Silakan pilih file Excel terlebih dahulu',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            $('#importProgress').removeClass('d-none');
            $('#importButton').prop('disabled', true);
            
            $.ajax({
                url: '{{ route("preparations.import") }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#importProgress').addClass('d-none');
                    $('#importButton').prop('disabled', false);
                    
                    if (response.status === 'duplicates_found') {
                        let duplicateList = '<ul class="text-start">';
                        response.duplicates.forEach(function(dup) {
                            duplicateList += `<li>${dup.table}: ${dup.count} data</li>`;
                        });
                        duplicateList += '</ul>';
                        
                        Swal.fire({
                            title: 'Data Duplikat Ditemukan!',
                            html: response.message + duplicateList + '<br><strong>Apakah Anda ingin melanjutkan import tanpa data duplikat?</strong>',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#059669',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Ya, Lanjutkan!',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                formData.append('force_import', '1');
                                $('#importProgress').removeClass('d-none');
                                $('#importButton').prop('disabled', true);
                                
                                $.ajax({
                                    url: '{{ route("preparations.import") }}',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    success: function(response) {
                                        $('#importProgress').addClass('d-none');
                                        $('#importButton').prop('disabled', false);
                                        if (response.status === 'success') {
                                            Swal.fire({
                                                title: 'Berhasil!',
                                                text: response.message,
                                                icon: 'success',
                                                confirmButtonColor: '#059669'
                                            }).then(() => {
                                                $('#importExcelModal').modal('hide');
                                                window.location.reload();
                                            });
                                        }
                                    },
                                    error: function(xhr) {
                                        $('#importProgress').addClass('d-none');
                                        $('#importButton').prop('disabled', false);
                                        Swal.fire({
                                            title: 'Gagal!',
                                            text: xhr.responseJSON?.message || 'Terjadi kesalahan saat mengimpor data',
                                            icon: 'error',
                                            confirmButtonColor: '#dc2626'
                                        });
                                    }
                                });
                            }
                        });
                    } else if (response.status === 'success') {
                        Swal.fire({
                            title: 'Berhasil!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#059669'
                        }).then(() => {
                            $('#importExcelModal').modal('hide');
                            window.location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    $('#importProgress').addClass('d-none');
                    $('#importButton').prop('disabled', false);
                    Swal.fire({
                        title: 'Gagal!',
                        text: xhr.responseJSON?.message || 'Terjadi kesalahan saat mengimpor data',
                        icon: 'error',
                        confirmButtonColor: '#dc2626'
                    });
                }
            });
        });

        $('#importExcelModal').on('hidden.bs.modal', function () {
            $('#importExcelForm')[0].reset();
            $('#importProgress').addClass('d-none');
            $('#importButton').prop('disabled', false);
        });

        // Handle Search Button Click
        $('#searchButton').on('click', function() {
            performSearch();
        });

        $('#searchInput').on('keypress', function(e) {
            if (e.which === 13) performSearch();
        });

        $('#perPageSelect').on('change', function() {
            updateUrl($(this).val(), $('#searchInput').val());
        });

        function performSearch() {
            updateUrl($('#perPageSelect').val(), $('#searchInput').val());
        }

        function updateUrl(perPage, search) {
            const url = new URL(window.location.href);
            if (perPage && perPage !== '50') {
                url.searchParams.set('per_page', perPage);
            } else {
                url.searchParams.delete('per_page');
            }
            if (search && search.trim() !== '') {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }
    });

    // Handle Import TMMIN TXT Form
    $('#importTmminForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const fileInput = $('#tmminFile')[0];
        
        if (!fileInput.files.length) {
            Swal.fire({ title: 'Error!', text: 'Silakan pilih file TXT terlebih dahulu', icon: 'error', confirmButtonColor: '#dc2626' });
            return;
        }
        
        $('#importTmminProgress').removeClass('d-none');
        $('#importTmminButton').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("preparations.import-tmmin") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#importTmminProgress').addClass('d-none');
                $('#importTmminButton').prop('disabled', false);
                
                if (response.status === 'duplicates_found') {
                    let duplicateList = '<ul class="text-start">';
                    response.duplicates.forEach(function(dup) { duplicateList += `<li>${dup.table}: ${dup.count} data</li>`; });
                    duplicateList += '</ul>';
                    
                    Swal.fire({
                        title: 'Data Duplikat Ditemukan!',
                        html: response.message + duplicateList + '<br><strong>Apakah Anda ingin melanjutkan import tanpa data duplikat?</strong>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#059669',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Lanjutkan!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formData.append('force_import', '1');
                            $('#importTmminProgress').removeClass('d-none');
                            $('#importTmminButton').prop('disabled', true);
                            
                            $.ajax({
                                url: '{{ route("preparations.import-tmmin") }}',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    $('#importTmminProgress').addClass('d-none');
                                    $('#importTmminButton').prop('disabled', false);
                                    if (response.status === 'success') {
                                        Swal.fire({ title: 'Berhasil!', text: response.message, icon: 'success', confirmButtonColor: '#059669' })
                                            .then(() => { $('#importTmminModal').modal('hide'); window.location.reload(); });
                                    }
                                },
                                error: function(xhr) {
                                    $('#importTmminProgress').addClass('d-none');
                                    $('#importTmminButton').prop('disabled', false);
                                    Swal.fire({ title: 'Gagal!', text: xhr.responseJSON?.message || 'Terjadi kesalahan', icon: 'error', confirmButtonColor: '#dc2626' });
                                }
                            });
                        }
                    });
                } else if (response.status === 'success') {
                    Swal.fire({ title: 'Berhasil!', text: response.message, icon: 'success', confirmButtonColor: '#059669' })
                        .then(() => { $('#importTmminModal').modal('hide'); window.location.reload(); });
                }
            },
            error: function(xhr) {
                $('#importTmminProgress').addClass('d-none');
                $('#importTmminButton').prop('disabled', false);
                Swal.fire({ title: 'Gagal!', text: xhr.responseJSON?.message || 'Terjadi kesalahan', icon: 'error', confirmButtonColor: '#dc2626' });
            }
        });
    });

    $('#importTmminModal').on('hidden.bs.modal', function () {
        $('#importTmminForm')[0].reset();
        $('#importTmminProgress').addClass('d-none');
        $('#importTmminButton').prop('disabled', false);
    });

    // Handle Import ADM Form
    $('#importAdmForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const fileInput = $('#admFile')[0];
        
        if (!fileInput.files.length) {
            Swal.fire({ title: 'Error!', text: 'Silakan pilih file Excel terlebih dahulu', icon: 'error', confirmButtonColor: '#dc2626' });
            return;
        }
        
        $('#importAdmProgress').removeClass('d-none');
        $('#importAdmButton').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("preparations.import-adm") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#importAdmProgress').addClass('d-none');
                $('#importAdmButton').prop('disabled', false);
                
                if (response.status === 'duplicates_found') {
                    Swal.fire({
                        title: 'Data Duplikat Ditemukan!',
                        html: response.message + '<br><strong>Jika lanjut data yang duplikat akan diabaikan</strong>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#059669',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Lanjutkan!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formData.append('force_import', '1');
                            $('#importAdmProgress').removeClass('d-none');
                            $('#importAdmButton').prop('disabled', true);
                            
                            $.ajax({
                                url: '{{ route("preparations.import-adm") }}',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    $('#importAdmProgress').addClass('d-none');
                                    $('#importAdmButton').prop('disabled', false);
                                    if (response.status === 'success') {
                                        Swal.fire({ title: 'Berhasil!', text: response.message, icon: 'success', confirmButtonColor: '#059669' })
                                            .then(() => { $('#importAdmModal').modal('hide'); window.location.reload(); });
                                    }
                                },
                                error: function(xhr) {
                                    $('#importAdmProgress').addClass('d-none');
                                    $('#importAdmButton').prop('disabled', false);
                                    Swal.fire({ title: 'Gagal!', text: xhr.responseJSON?.message || 'Terjadi kesalahan', icon: 'error', confirmButtonColor: '#dc2626' });
                                }
                            });
                        }
                    });
                } else if (response.status === 'success') {
                    Swal.fire({ title: 'Berhasil!', text: response.message, icon: 'success', confirmButtonColor: '#059669' })
                        .then(() => { $('#importAdmModal').modal('hide'); window.location.reload(); });
                }
            },
            error: function(xhr) {
                $('#importAdmProgress').addClass('d-none');
                $('#importAdmButton').prop('disabled', false);
                Swal.fire({ title: 'Gagal!', text: xhr.responseJSON?.message || 'Terjadi kesalahan', icon: 'error', confirmButtonColor: '#dc2626' });
            }
        });
    });

    $('#importAdmModal').on('hidden.bs.modal', function () {
        $('#importAdmForm')[0].reset();
        $('#importAdmProgress').addClass('d-none');
        $('#importAdmButton').prop('disabled', false);
    });

    // QR Code Generator
    function showQrCode(noDn) {
        Swal.fire({
            title: 'QR Code DN',
            html: `
                <div class="text-center">
                    <div id="qrcode" class="d-flex justify-content-center mb-3"></div>
                    <p class="fw-bold fs-4 mb-0 mt-3">${noDn}</p>
                </div>
            `,
            showCloseButton: true,
            showConfirmButton: true,
            confirmButtonText: '<i class="bi bi-printer"></i> Print',
            confirmButtonColor: '#6c757d',
            width: 350,
            didOpen: () => {
                new QRCode(document.getElementById("qrcode"), {
                    text: noDn,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const printWindow = window.open('', '_blank');
                const qrImage = document.querySelector('#qrcode img').src;
                printWindow.document.write(`
                    <html>
                    <head><title>QR Code - ${noDn}</title></head>
                    <body style="text-align: center; padding: 20px;">
                        <img src="${qrImage}" style="width: 200px; height: 200px;">
                        <p style="font-size: 24px; font-weight: bold; margin-top: 15px;">${noDn}</p>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        });
    }

    // Open Move to Shipping (lewat panah Actions)
    function openMoveToShippingModal(preparationId) {
        $.ajax({
            url: `/preparations/${preparationId}/edit`,
            type: 'GET',
            success: function(preparation) {
                checkDnForShipping(preparation.no_dn, true); // true = pakai konfirmasi dulu
            },
            error: function() {
                Swal.fire({ title: 'Error!', text: 'Gagal mengambil data preparation', icon: 'error', confirmButtonColor: '#dc2626' });
            }
        });
    }
</script>
@endpush