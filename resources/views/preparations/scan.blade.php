@extends('layouts.mobile')

@section('title', 'Scan Preparation')
@section('page-title', 'SCAN PREPARATION')
@section('body-class', 'scan-mobile-page')

@section('content')
<div class="container-fluid px-3 py-3">
    
    <!-- Scan Input Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <i class="bi bi-qr-code-scan text-dark" style="font-size: 3rem;"></i>
                <h4 class="mt-2  fw-bold">Scan DN Barcode</h4>
                <small class="text-muted">Scan atau ketik nomor DN untuk masuk ke Monitoring Preparation</small>
            </div>
            
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-dark text-white">
                    <i class="bi bi-upc-scan"></i>
                </span>
                <input type="text" 
                       class="form-control form-control-lg text-center fw-bold" 
                       id="scanDnInput" 
                       placeholder="Scan DN disini..." 
                       autocomplete="off"
                       autofocus
                       style="font-size: 1.5rem; letter-spacing: 1px;">
            </div>
            
            <!-- Status Indicator -->
            <div id="scanStatus" class="text-center mt-3 d-none">
                <div class="spinner-border spinner-border-sm text-dark me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="text-muted">Mencari DN...</span>
            </div>
        </div>
    </div>


    <!-- Recent Scans -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-clock-history me-2"></i>
                    <strong>Data scan terakhir</strong>
                </div>
                <span class="badge bg-light text-dark">{{ $recentScans->count() }} terakhir</span>
            </div>
        </div>
        <div class="card-body p-0">
            @forelse($recentScans as $scan)
                <div class="border-bottom p-3 {{ $loop->first ? 'bg-light' : '' }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1 fw-bold text-dark">{{ $scan->no_dn }}</h5>
                            <div class="text-muted small">
                                <span class="me-2"><i class="bi bi-signpost-split me-1"></i>{{ $scan->route }}</span>
                                <span class="me-2"><i class="bi bi-building me-1"></i>{{ $scan->dock }}</span>
                                <span><i class="bi bi-arrow-repeat me-1"></i>Cycle {{ $scan->cycle }}</span>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-geo-alt me-1"></i>{{ $scan->address }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">{{ $scan->scan_to_shipping->format('H:i') }}</div>
                            <small class="text-muted">{{ $scan->scan_to_shipping->format('d/m/Y') }}</small>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-person-fill"></i> {{ $scan->moved_by ?? 'System' }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-2 mb-0">Belum ada scan hari ini</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Logout Button -->
    <div class="mt-4 mb-3">
        <form id="logoutForm" action="{{ route('logout') }}" method="POST">
            @csrf
        </form>
        <button type="button" class="btn btn-danger w-100 py-3" id="btnLogout">
            <i class="bi bi-box-arrow-right me-2"></i>
            <strong>Logout</strong>
        </button>
    </div>

</div>

<!-- PR Selection Modal (Full Screen for Mobile) -->
<div class="modal fade" id="prModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="bi bi-box-seam me-2"></i>Pilih Preparation (PR)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- DN Info -->
                <div class="alert alert-light border text-center mb-4">
                    <small class="text-muted d-block">No DN</small>
                    <h4 class="mb-1 fw-bold text-dark" id="modalDnNumber">-</h4>
                    <small id="modalDnInfo" class="text-muted d-block">-</small>
                    <hr class="my-2">
                    <small id="modalDnMeta" class="text-muted d-block">-</small>
                </div>
                
                <!-- PR Buttons -->
                <div class="row g-2 mb-2">
                    <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 py-4 pr-btn" data-pr="1"><strong>PR 1</strong></button></div>
                    <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 py-4 pr-btn" data-pr="2"><strong>PR 2</strong></button></div>
                </div>
                <div class="row g-2">
                    <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 py-4 pr-btn" data-pr="3"><strong>PR 3</strong></button></div>
                    <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 py-4 pr-btn" data-pr="4"><strong>PR 4</strong></button></div>
                </div>
                <div class="row g-2 mt-0">
                    <div class="col-12"><button type="button" class="btn btn-outline-danger w-100 py-4 pr-btn" data-pr="5"><strong>AB (Abnormal)</strong></button></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100 py-2" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>Batal
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Mobile optimized styles */
    .scan-mobile-page {
        background-color: #f5f5f5;
    }
    
    #scanDnInput {
        height: 60px;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
    }
    
    #scanDnInput:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }
    
    .pr-btn {
        font-size: 1.4rem;
        transition: all 0.2s ease;
    }
    
    .pr-btn:active {
        transform: scale(0.95);
    }
    
    .pr-btn:hover {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }
    
    /* Success animation */
    @keyframes successPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .scan-success {
        animation: successPulse 0.3s ease;
    }
    
    /* Recent scan highlight */
    .recent-highlight {
        border-left: 4px solid #000000 !important;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    
    let scanTimeout;
    let pendingNoDn = null;
    const prModal = new bootstrap.Modal(document.getElementById('prModal'));
    
    // Auto focus on load
    $('#scanDnInput').focus();
    
    // Re-focus after modal closes
    $('#prModal').on('hidden.bs.modal', function() {
        $('#scanDnInput').val('').focus();
        pendingNoDn = null;
    });
    
    // Handle scan input
    $('#scanDnInput').on('input', function() {
        clearTimeout(scanTimeout);
        const noDn = $(this).val().trim();
        
        if (noDn.length > 0) {
            // Delay 500ms untuk menunggu scanner selesai input
            scanTimeout = setTimeout(function() {
                checkDn(noDn);
            }, 500);
        }
    });
    
    // Handle Enter key
    $('#scanDnInput').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            clearTimeout(scanTimeout);
            const noDn = $(this).val().trim();
            if (noDn.length > 0) {
                checkDn(noDn);
            }
        }
    });
    
    // Cek DN: nentuin perlu modal PR atau langsung diproses
    function checkDn(noDn) {
        $('#scanStatus').removeClass('d-none');
        $('#scanDnInput').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("prep-monitoring.check-dn") }}',
            type: 'GET',
            data: { no_dn: noDn },
            success: function(response) {
                $('#scanStatus').addClass('d-none');
                $('#scanDnInput').prop('disabled', false);
                
                if (!response.success) {
                    showError('Gagal', response.message);
                    $('#scanDnInput').val('').focus();
                    return;
                }
                
                if (response.needs_pr_selection) {
                    pendingNoDn = noDn;
                    showPrModal(response.data);
                } else {
                    // Kombinasi udah jalan, langsung diproses tanpa modal
                    executeScan(noDn, null);
                }
            },
            error: function(xhr) {
                $('#scanStatus').addClass('d-none');
                $('#scanDnInput').prop('disabled', false);
                
                showError('Error', xhr.responseJSON?.message || 'Terjadi kesalahan');
                $('#scanDnInput').val('').focus();
            }
        });
    }
    
    // Show PR selection modal
    function showPrModal(data) {
        $('#modalDnNumber').text(data.no_dn);
        $('#modalDnInfo').html(`
            <span class="me-2"><strong>Route:</strong> ${data.route}</span>
            <span class="me-2"><strong>Dock:</strong> ${data.dock}</span>
            <span><strong>Cycle:</strong> ${data.cycle}</span>
        `);
        $('#modalDnMeta').html(`
            <strong>Kbn:</strong> ${data.kbn} &nbsp;|&nbsp;
            <strong>Skid (target):</strong> ${data.skid} &nbsp;|&nbsp;
            <strong>Address:</strong> ${data.shipping_address}
        `);
        prModal.show();
    }
    
    // Handle PR button click
    $('.pr-btn').on('click', function() {
        if (!pendingNoDn) return;
        
        const prNumber = $(this).data('pr');
        const btn = $(this);
        const originalHtml = btn.html();

        $('.pr-btn').prop('disabled', true);
        btn.html('<span class="spinner-border spinner-border-sm"></span>');
        
        executeScan(pendingNoDn, prNumber, function() {
            prModal.hide();
        }, function() {
            // restore button kalau gagal
            $('.pr-btn').prop('disabled', false);
            btn.html(originalHtml);
        });
    });
    
    // Eksekusi scan (bikin row baru / nambah progress row existing)
    function executeScan(noDn, prNumber, onSuccessCallback, onErrorCallback) {
        $.ajax({
            url: '{{ route("prep-monitoring.scan") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                no_dn: noDn,
                pr_number: prNumber
            },
            success: function(response) {
                if (onSuccessCallback) onSuccessCallback();
                showSuccess(noDn, response.message);
            },
            error: function(xhr) {
                if (onErrorCallback) onErrorCallback();
                showError('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan saat memproses scan');
            }
        });
    }
    
    // Show success message
    function showSuccess(noDn, message) {
        Swal.fire({
            title: 'Berhasil!',
            html: `<div class="text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3 mb-1">${noDn}</h4>
                <p class="text-muted mb-0">${message}</p>
            </div>`,
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        }).then(() => {
            window.location.reload();
        });
    }
    
    // Show error message
    function showError(title, message) {
        Swal.fire({
            title: title,
            html: message,
            icon: 'error',
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'OK'
        }).then(() => {
            $('#scanDnInput').focus();
        });
    }
    
    // Prevent screen sleep on mobile (if supported)
    if ('wakeLock' in navigator) {
        navigator.wakeLock.request('screen').catch(err => {
            console.log('Wake Lock error:', err);
        });
    }
    
    // Logout confirmation
    $('#btnLogout').on('click', function() {
        Swal.fire({
            title: 'Konfirmasi Logout',
            text: 'Apakah Anda yakin ingin keluar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#logoutForm').submit();
            }
        });
    });
});
</script>
@endpush