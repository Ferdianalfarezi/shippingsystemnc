@extends('layouts.app')

@section('title', 'Monitoring Preparation & Delivery')
@section('page-title', 'SHIPPING AREA MONITORING')
@section('body-class', 'prep-monitoring-page')

@section('content')
    <style>
        .pm-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
            margin: 1rem 0;
        }

        .pm-filter-group {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: #14171c;
            border: 1px solid #262b33;
            border-radius: 10px;
            padding: .4rem .5rem;
        }

        .pm-date-input {
            background: #1b1f26;
            border: 1px solid #2f3540;
            color: #e8eaed;
            border-radius: 7px;
            padding: .35rem .6rem;
            font-size: .85rem;
            width: 150px;
            color-scheme: dark;
        }

        .pm-date-input:focus {
            outline: none;
            border-color: #5b8cff;
            box-shadow: 0 0 0 3px rgba(91, 140, 255, .18);
        }

        .pm-sep {
            color: #6b7280;
            font-size: .75rem;
        }

        .pm-btn-filter {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: #3b6df0;
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: .85rem;
            padding: .4rem .9rem;
            border-radius: 7px;
            white-space: nowrap;
        }

        .pm-btn-filter:hover {
            background: #345fd8;
            color: #fff;
        }

        .pm-btn-reset {
            display: inline-flex;
            align-items: center;
            background: transparent;
            border: 1px solid #2f3540;
            color: #9aa2b1;
            font-size: .8rem;
            padding: .38rem .8rem;
            border-radius: 7px;
            text-decoration: none;
            white-space: nowrap;
        }

        .pm-btn-reset:hover {
            border-color: #5b8cff;
            color: #dfe4ee;
        }

        .pm-date-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: #131c16;
            border: 1px solid #2a4a33;
            color: #8fd39a;
            font-size: .82rem;
            font-weight: 600;
            padding: .4rem .8rem;
            border-radius: 20px;
            white-space: nowrap;
        }

        .pm-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }
    </style>

    <div class="pm-toolbar">

        <!-- Tanpa filter tanggal: tabel cuma isi data yang belum diambil milkrun -->
        <div class="ms-3 small text-muted">
            <i class="bi bi-truck"></i> Menampilkan data yang belum diambil milkrun
        </div>

        <!-- Scan + Badge -->
        <div class="pm-actions">

            <!-- Scan DN Input (dibiarin default/putih) -->
            <div class="input-group" style="width: 280px;">
                <span class="input-group-text bg-white text-white">
                    <i class="bi bi-qr-code-scan text-dark"></i>
                </span>
                <input type="text" class="form-control" id="monitoringScanInput" placeholder="Scan DN..." autofocus>
            </div>

            @if(auth()->user()->role === 'superadmin')
                <div class="card border-0 shadow-sm p-1 bg-danger">
                    <button type="button" class="btn btn-danger" id="deleteAllMonitoringButton" title="Hapus Semua Data">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
            @endif

            <!-- On Progress Badge -->
            <div class="bg-warning card border-0 shadow-sm" id="badgeOnProgressBox">
                <div class="card-body p-1">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-arrow-repeat text-dark fs-5"></i>
                        </div>
                        <div>
                            <small class="text-dark d-block fw-bold me-3" style="font-size: 0.7rem;">On Progress</small>
                            <h5 class="mb-0 fw-bold text-dark">{{ $totalOnProgress }}</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Closed Badge -->
            <div class="bg-success card border-0 shadow-sm me-3" id="badgeClosedBox">
                <div class="card-body p-1">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-check-circle text-white fs-5"></i>
                        </div>
                        <div>
                            <small class="text-white d-block fw-bold me-3" style="font-size: 0.7rem;">Closed</small>
                            <h5 class="mb-0 fw-bold text-white">{{ $totalClosed }}</h5>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="table-responsive p-0 mt-0">
        <style>
            /* Belang hitam / abu-abu supaya gampang dibaca kalau datanya banyak */
            #monitoringTable tbody tr > td {
                background-color: #000000 !important;
            }

            #monitoringTable tbody tr.row-stripe > td {
                background-color: #3a3a3a !important;
            }

            #monitoringTable tbody tr:hover > td {
                background-color: #5a5a5a !important;
            }

            #monitoringTable tbody tr.row-delay > td {
                background-color: #dc3545 !important;
                color: #fff !important;
                font-weight: bold;
            }

            /* Kotak Pr yang preparation-nya sudah lengkap (mis. 4 / 4) */
            #monitoringTable tbody tr > td.pr-ok {
                background-color: #198754 !important;
                color: #fff !important;
                font-weight: bold;
            }
        </style>
        <table class="table table-bordered table-compact w-100 mt-1 text-center align-middle" id="monitoringTable">
            <thead>
                <tr class="fs-6">
                    <th rowspan="2">Customer</th>
                    <th rowspan="2">Cycle</th>
                    <th rowspan="2">Dock</th>
                    <th rowspan="2">Route</th>
                    <th colspan="2">QTY</th>
                    <th colspan="2">Time Start</th>
                    <th rowspan="2">ETD</th>
                    <th colspan="22">Status</th>
                </tr>
                <tr class="fs-6">
                    <th>Kbn</th>
                    <th>Skid</th>
                    <th>Prep</th>
                    <th>Ship</th>
                    <th>Pr 1</th>
                    <th>Pr 2</th>
                    <th>Pr 3</th>
                    <th>Pr 4</th>
                    <th style="border-right: 5px solid #ff0000 !important;" title="Abnormal">AB</th>
                    <th style="border-left: 5px solid #ff0000 !important;">S-1</th>
                    <th>S-2</th>
                    <th>S-3</th>
                    <th>S-4</th>
                    <th>S-5</th>
                    <th>S-6</th>
                    <th>S-7</th>
                    <th>S-8</th>
                    <th>S-9</th>
                    <th>S-10</th>
                    <th>S-11</th>
                    <th>S-12</th>
                    <th>S-13</th>
                    <th style="border-left: 5px solid #000 !important;">EX-1</th>
                    <th>EX-2</th>
                    <th>EX-3</th>
                    <th>Delivery</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        // Kolom S-1..S-13 = "Shipping N", EX-1..EX-3 = "Shipping Export N".
                        // Cuma keisi teksnya kalau shipping_address-nya persis salah satu dari itu.
                        $shipKey = null;
                        if (preg_match('/^Shipping (Export )?(\d+)$/i', trim((string) $row->shipping_address), $m)) {
                            $n = (int) $m[2];
                            $isExport = $m[1] !== '';
                            if ($isExport && $n >= 1 && $n <= 3) $shipKey = 'EX-' . $n;
                            if (!$isExport && $n >= 1 && $n <= 13) $shipKey = 'S-' . $n;
                        }
                        // Section Pr & Shipping polos (teks hitam). Baris baru merah kalau Delivery = Delay.
                        // Kalau preparation sudah ok, cuma kotak Pr yang aktif yang hijau.
                        // Pr: "OK" kalau sudah lengkap, selain itu progress (mis. 2 / 4).
                        // Shipping: selalu progress (mis. 4 / 4).
                        $prOk = $row->status === 'ok';
                        $prText = $prOk ? 'OK' : $row->pr_count . ' / ' . $row->skid;
                        $shipText = ($row->ship_count ?? $row->pr_count) . ' / ' . $row->skid;
                        $shipClass = '';
                    @endphp
                    <tr class="fs-6 {{ $loop->even ? 'row-stripe' : '' }} {{ $row->delivery_status === 'delay' ? 'row-delay' : '' }}">
                        <td>{{ $row->customers }}</td>
                        <td>{{ $row->cycle }}</td>
                        <td>{{ $row->dock }}</td>
                        <td>{{ $row->route }}</td>
                        <td>{{ $row->kbn ?? '-' }}</td>
                        <td>{{ $row->skid }}</td>
                        <td>{{ $row->start_prep_at ? $row->start_prep_at->format('H:i') : '-' }}</td>
                        <td>{{ $row->start_ship_at ? $row->start_ship_at->format('H:i') : '-' }}</td>
                        <td>{{ $row->etd_time ? date('H:i', strtotime($row->etd_time)) : '-' }}</td>

                        @for($i = 1; $i <= 5; $i++) {{-- 5 = AB (Abnormal) --}}
                            <td class="{{ $prOk && $row->pr_number == $i ? 'pr-ok' : '' }}" @if($i === 5) style="border-right: 4px solid #ff0000 !important;" @endif>
                                {{ $row->pr_number == $i ? $prText : '-' }}
                            </td>
                        @endfor

                        {{-- Seluruh section Shipping diwarnai sama (bukan cuma kolom aktif) --}}
                        @foreach(array_merge(array_map(fn ($i) => 'S-' . $i, range(1, 13)), ['EX-1', 'EX-2', 'EX-3']) as $key)
                            <td class="{{ $shipClass }}" @if($key === 'S-1' || $key === 'EX-1') style="border-left: 4px solid #000 !important;" @endif>
                                {{ $shipKey === $key ? $shipText : '-' }}
                            </td>
                        @endforeach

                        <td class="fw-bold" title="{{ $row->delivery_target ? 'Delivery: ' . $row->delivery_target->format('d/m H:i') : '' }}">
                            {{ $row->delivery_label }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="31" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Semua data sudah diambil milkrun</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Pilih PR (cuma muncul buat kombinasi baru) -->
    <div class="modal fade" id="selectPrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Pilih Preparation (PR)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="selectPrInfo" class="mb-3 small text-muted"></div>
                    <div class="row g-2">
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-select-btn" data-pr="1">PR 1</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-select-btn" data-pr="2">PR 2</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-select-btn" data-pr="3">PR 3</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-primary w-100 pr-select-btn" data-pr="4">PR 4</button></div>
                        <div class="col-12"><button type="button" class="btn btn-outline-danger w-100 pr-select-btn" data-pr="5">AB (Abnormal)</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {

    let pendingNoDn = null;
    let scanTimeout;

    $('#monitoringScanInput').on('input', function () {
        clearTimeout(scanTimeout);
        const noDn = $(this).val().trim();
        if (noDn.length > 0) {
            scanTimeout = setTimeout(function () { checkDn(noDn); }, 500);
        }
    });

    $('#monitoringScanInput').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            clearTimeout(scanTimeout);
            const noDn = $(this).val().trim();
            if (noDn.length > 0) checkDn(noDn);
        }
    });

    function checkDn(noDn) {
        $.ajax({
            url: '{{ route("prep-monitoring.check-dn") }}',
            type: 'GET',
            data: { no_dn: noDn },
            success: function (response) {
                $('#monitoringScanInput').val('');

                if (!response.success) {
                    Swal.fire({
                        title: 'Gagal!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#dc2626',
                        timer: 2500,
                        timerProgressBar: true,
                    });
                    $('#monitoringScanInput').focus();
                    return;
                }

                if (response.needs_pr_selection) {
                    pendingNoDn = noDn;
                    const d = response.data;
                    $('#selectPrInfo').html(`
                        <strong>DN:</strong> ${d.no_dn}<br>
                        <strong>Customer:</strong> ${d.customers} | <strong>Cycle:</strong> ${d.cycle} | <strong>Dock:</strong> ${d.dock}<br>
                        <strong>Kbn:</strong> ${d.kbn} &nbsp;|&nbsp; <strong>Skid (target):</strong> ${d.skid} &nbsp;|&nbsp; <strong>Shipping Address:</strong> ${d.shipping_address}
                    `);
                    new bootstrap.Modal(document.getElementById('selectPrModal')).show();
                } else {
                    executeScan(noDn, null);
                }
            },
            error: function (xhr) {
                Swal.fire({
                    title: 'Error!',
                    text: xhr.responseJSON?.message || 'Terjadi kesalahan',
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                });
                $('#monitoringScanInput').focus();
            }
        });
    }

    $('.pr-select-btn').on('click', function () {
        const prNumber = $(this).data('pr');
        bootstrap.Modal.getInstance(document.getElementById('selectPrModal')).hide();
        executeScan(pendingNoDn, prNumber);
        pendingNoDn = null;
    });

    function executeScan(noDn, prNumber) {
        $.ajax({
            url: '{{ route("prep-monitoring.scan") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                no_dn: noDn,
                pr_number: prNumber,
            },
            success: function (response) {
                Swal.fire({
                    title: 'Berhasil!',
                    text: response.message,
                    icon: 'success',
                    confirmButtonColor: '#059669',
                    timer: 2000,
                    timerProgressBar: true,
                }).then(() => window.location.reload());
            },
            error: function (xhr) {
                Swal.fire({
                    title: 'Gagal!',
                    text: xhr.responseJSON?.message || 'Terjadi kesalahan saat memproses scan',
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                });
                $('#monitoringScanInput').focus();
            }
        });
    }

    $('#deleteAllMonitoringButton').on('click', function () {
        Swal.fire({
            title: 'PERINGATAN!',
            text: 'Hapus SEMUA data monitoring? Tindakan ini tidak dapat dibatalkan!',
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("prep-monitoring.delete-all") }}',
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        Swal.fire({ title: 'Berhasil!', text: response.message, icon: 'success' })
                            .then(() => window.location.reload());
                    },
                    error: function () {
                        Swal.fire({ title: 'Gagal!', text: 'Terjadi kesalahan', icon: 'error' });
                    }
                });
            }
        });
    });

});
</script>
@endpush