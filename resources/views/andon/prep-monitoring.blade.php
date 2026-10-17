@extends('layouts.andon')

@section('title', 'Andon - Shipping Area Monitoring')
@section('page-title', 'SHIPPING AREA MONITORING')
@section('body-class', 'andon-page')

@section('content')
    <!-- Stats Badges -->
    <div class="d-flex justify-content-end align-items-center gap-2 mb-3 mt-3">

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

    <!-- Recent Completed Display -->
    @if($recentCompleted)
    <div id="recentCompletedBox">
        <div class="card border-0 shadow-sm mb-3 ms-3 me-3"
            style="border-radius:0; background-color:#000000; outline:2px solid #ffffff;">
            <div class="card-body py-1 px-4">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <div>
                            <i class="bi bi-check-circle-fill text-white fs-6"></i>
                        </div>
                        <small class="text-white fw-semibold" style="font-size: 0.7rem;">RECENT COMPLETED</small>
                    </div>

                    <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                    <div class="d-flex align-items-center gap-2">
                        <small class="text-white" style="font-size: 1rem;">Customer:</small>
                        <strong class="text-white">{{ $recentCompleted->customers }}</strong>
                    </div>

                    <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                    <div class="d-flex align-items-center gap-2">
                        <small class="text-white" style="font-size: 1rem;">Cycle:</small>
                        <span class="fw-semibold text-white">{{ $recentCompleted->cycle }}</span>
                    </div>

                    <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                    <div class="d-flex align-items-center gap-2">
                        <small class="text-white" style="font-size: 1rem;">Dock:</small>
                        <span class="fw-semibold text-white">{{ $recentCompleted->dock }}</span>
                    </div>

                    <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                    <div class="d-flex align-items-center gap-2">
                        <small class="text-white" style="font-size: 1rem;">Route:</small>
                        <span class="fw-semibold text-white">{{ $recentCompleted->route }}</span>
                    </div>

                    <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                    <div class="d-flex align-items-center gap-2">
                        <small class="text-white" style="font-size: 1rem;">PR:</small>
                        <span class="fw-semibold text-white">{{ $recentCompleted->pr_number == \App\Models\PrepMonitoring::PR_ABNORMAL ? 'AB' : $recentCompleted->pr_number }} ({{ $recentCompleted->skid }}/{{ $recentCompleted->skid }})</span>
                    </div>

                    <div class="vr" style="height: 30px; opacity: 0.2;"></div>

                    <div class="d-flex align-items-center gap-2">
                        <small class="text-white" style="font-size: 1rem;">Ship To:</small>
                        <span class="fw-semibold text-white">{{ $recentCompleted->shipping_address }}</span>
                    </div>

                    @if($recentCompleted->start_ship_at)
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-white fw-bold">
                            <i class="bi bi-clock-fill"></i> {{ $recentCompleted->start_ship_at->format('H:i:s') }}
                        </span>
                        <span class="text-white fw-bold">{{ $recentCompleted->start_ship_at->format('d/m/Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Table -->
    <div class="table-responsive p-0 mt-0">
        <style>
            #monitoringTable tr.row-delay > td {
                background-color: #dc3545 !important;
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
                        $progressText = $row->status === 'ok' ? 'OK' : ($row->pr_count . ' / ' . $row->skid);

                        // Section Pr & Shipping polos (teks hitam). Baris baru merah kalau Delivery = Delay.
                        $prClass = '';
                        $shipClass = '';
                    @endphp
                    <tr class="fs-6 {{ $row->delivery_status === 'delay' ? 'row-delay' : '' }}">
                        <td>{{ $row->customers }}</td>
                        <td>{{ $row->cycle }}</td>
                        <td>{{ $row->dock }}</td>
                        <td>{{ $row->route }}</td>
                        <td>{{ $row->kbn ?? '-' }}</td>
                        <td>{{ $row->skid }}</td>
                        <td>{{ $row->start_prep_at ? $row->start_prep_at->format('H:i') : '-' }}</td>
                        <td>{{ $row->start_ship_at ? $row->start_ship_at->format('H:i') : '-' }}</td>
                        <td>{{ $row->etd_time ? date('H:i', strtotime($row->etd_time)) : '-' }}</td>

                        {{-- Seluruh section Pr diwarnai sama (bukan cuma kolom aktif) --}}
                        @for($i = 1; $i <= 5; $i++) {{-- 5 = AB (Abnormal) --}}
                            <td class="{{ $prClass }}" @if($i === 5) style="border-right: 4px solid #ff0000 !important;" @endif>
                                {{ $row->pr_number == $i ? $progressText : '-' }}
                            </td>
                        @endfor

                        {{-- Seluruh section Shipping diwarnai sama (bukan cuma kolom aktif) --}}
                        @foreach(array_merge(array_map(fn ($i) => 'S-' . $i, range(1, 13)), ['EX-1', 'EX-2', 'EX-3']) as $key)
                            <td class="{{ $shipClass }}" @if($key === 'S-1' || $key === 'EX-1') style="border-left: 4px solid #000 !important;" @endif>
                                {{ $shipKey === $key ? $progressText : '-' }}
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
@endsection

@push('scripts')
<script>
$(document).ready(function () {

    const REFRESH_INTERVAL = 3000; // 3 detik
    let countdownSeconds = REFRESH_INTERVAL / 1000;

    function startCountdown() {
        countdownSeconds = REFRESH_INTERVAL / 1000;
        $('#countdown').text(countdownSeconds);

        const timer = setInterval(() => {
            countdownSeconds--;
            $('#countdown').text(countdownSeconds);

            if (countdownSeconds <= 0 || document.hidden) {
                clearInterval(timer);
            }
        }, 1000);
    }

    startCountdown();

    // Ajax refresh table & badges only
    setInterval(() => {

        if (!document.hidden) {

            $("#monitoringTable").load(location.href + " #monitoringTable>*");
            $("#recentCompletedBox").load(location.href + " #recentCompletedBox>*");
            $("#badgeOnProgressBox").load(location.href + " #badgeOnProgressBox>*");
            $("#badgeClosedBox").load(location.href + " #badgeClosedBox>*");

        }

        startCountdown();

    }, REFRESH_INTERVAL);

});
</script>
@endpush