@extends('layouts.app')

@section('title', 'Schedule Cycle Kanban TMMIN')
@section('page-title', 'SCHEDULE CYCLE TMMIN')
@section('body-class', 'address-page')

@section('content')
    <style>
        /* Paksa teks modal jadi hitam (tema dark bikin teks putih di atas modal putih) */
        #createScheduleModal .modal-content,
        #editScheduleModal .modal-content {
            color: #000 !important;
        }

        #createScheduleModal .modal-title,
        #createScheduleModal .form-label,
        #createScheduleModal .form-text,
        #editScheduleModal .modal-title,
        #editScheduleModal .form-label,
        #editScheduleModal .form-text {
            color: #000 !important;
        }

        #createScheduleModal .form-control,
        #createScheduleModal .form-select,
        #editScheduleModal .form-control,
        #editScheduleModal .form-select {
            color: #000 !important;
            background-color: #fff !important;
        }

        #createScheduleModal .form-select option,
        #editScheduleModal .form-select option {
            color: #000;
            background-color: #fff;
        }

        #createScheduleModal .form-control::placeholder,
        #editScheduleModal .form-control::placeholder {
            color: #6c757d !important;
        }
    </style>

    @php
        // Printer virtual yang memunculkan dialog "Save as" — bikin auto print macet, jadi disembunyikan
        $excludedPrinters = ['Microsoft Print to PDF', 'OneNote (Desktop)', 'Microsoft XPS Document Writer', 'Fax'];

        $printerOptions = collect($printers)
            ->reject(fn ($p) => in_array($p, $excludedPrinters, true))
            ->values()
            ->all();

        $hasPrinterList = count($printerOptions) > 0;
        $nowTime = now()->format('H:i:s');
    @endphp

    <!-- Toolbar kanan -->
    <div class="d-flex justify-content-end align-items-center gap-2 mb-3 mt-3">

        <!-- Kembali ke Kanban TMMIN -->
        <a href="{{ route('kanbantmmins.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kanban TMMIN
        </a>

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
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
                                    <i class="bi bi-plus-circle me-2"></i> Tambah Jadwal
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Total Cycle Badge -->
            <div class="bg-primary card border-0 shadow-sm me-3">
                <div class="card-body p-1">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-clock-history text-white fs-5"></i>
                        </div>
                        <div>
                            <small class="text-white d-block fw-bold me-3" style="font-size: 0.7rem;">Total Jadwal</small>
                            <h5 class="mb-0 fw-bold text-white">{{ $schedules->count() }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($schedules->contains(fn ($s) => empty($s->printer_name)))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Ada cycle yang belum diset printer-nya. Cycle tersebut <strong>tidak akan di-print otomatis</strong> sampai printer diisi.
        </div>
    @endif

    <!-- Tabel Schedule -->
    <div class="table-responsive p-0 mt-0">
        <table class="table table-compact w-100 mt-1" id="scheduleTable">
            <thead>
                <tr class="fs-6">
                    <th>Route</th>
                    <th>Dock</th>
                    <th>Cycle</th>
                    <th>Jam Keluar</th>
                    <th>Printer</th>
                    <th>Status Hari Ini</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $schedule)
                    <tr class="fs-5">
                        <td>
                            @if ($schedule->route)
                                <strong>{{ $schedule->route }}</strong>
                            @else
                                <span class="badge bg-secondary">Semua route</span>
                            @endif
                        </td>
                        <td>
                            @if ($schedule->dock_code)
                                <strong>{{ $schedule->dock_code }}</strong>
                            @else
                                <span class="badge bg-secondary">Semua dock</span>
                            @endif
                        </td>
                        <td><strong>{{ $schedule->cycle }}</strong></td>
                        <td><strong>{{ substr($schedule->jam_keluar, 0, 5) }}</strong></td>
                        <td>
                            @if (!empty($schedule->printer_name))
                                <i class="bi bi-printer me-1"></i> {{ $schedule->printer_name }}
                            @else
                                <span class="badge bg-warning text-dark">Belum diset</span>
                            @endif
                        </td>
                        <td>
                            @if ($schedule->jam_keluar <= $nowTime)
                                <span class="badge bg-secondary">Sudah lewat</span>
                            @else
                                <span class="badge bg-success">Menunggu</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-center p-1" style="gap: 0;">
                                <button type="button"
                                        class="btn btn-warning btn-sm btn-action-square btn-edit-schedule"
                                        style="border-radius: 6px 0 0 6px; margin: 0;"
                                        title="Edit"
                                        data-id="{{ $schedule->id }}"
                                        data-route="{{ $schedule->route }}"
                                        data-dock="{{ $schedule->dock_code }}"
                                        data-cycle="{{ $schedule->cycle }}"
                                        data-jam="{{ substr($schedule->jam_keluar, 0, 5) }}"
                                        data-printer="{{ $schedule->printer_name }}">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>

                                <form action="{{ route('kbntmmin-schedule.destroy', $schedule->id) }}" method="POST" class="d-inline delete-schedule-form" style="margin: 0;" data-label="{{ $schedule->label }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm btn-action-square" style="border-radius: 0 6px 6px 0; margin: 0;" title="Hapus">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="mt-3">
                        <td colspan="7" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Belum ada jadwal</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Riwayat Auto Print -->
    <h6 class="fw-bold mt-4 mb-2">
        <i class="bi bi-printer me-1"></i> Riwayat Auto Print (50 terakhir)
    </h6>
    <div class="table-responsive p-0 mt-0">
        <table class="table table-compact w-100 mt-1" id="logTable">
            <thead>
                <tr class="fs-6">
                    <th>Manifest No</th>
                    <th>Route</th>
                    <th>Dock</th>
                    <th>Cycle</th>
                    <th>Printer</th>
                    <th>Status</th>
                    <th>Waktu</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="fs-5">
                        <td><strong>{{ $log->manifest_no }}</strong></td>
                        <td>{{ $logManifests[$log->manifest_no]->route ?? '-' }}</td>
                        <td>{{ $logManifests[$log->manifest_no]->dock_code ?? '-' }}</td>
                        <td>{{ $log->cycle }}</td>
                        <td>{{ $log->printer_name ?? '-' }}</td>
                        <td>
                            @if ($log->status === 'failed')
                                <span class="badge bg-danger" title="{{ $log->error_message }}" style="cursor: help;">
                                    <i class="bi bi-x-circle me-1"></i> Gagal
                                </span>
                                <div class="small text-danger mt-1" style="max-width: 350px; white-space: normal;">
                                    {{ \Illuminate\Support\Str::limit($log->error_message, 120) }}
                                </div>
                            @else
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i> Printed
                                </span>
                            @endif
                        </td>
                        <td>{{ optional($log->printed_at)->format('d-m-Y H:i:s') }}</td>
                        <td>
                            <div class="d-flex justify-content-center p-1">
                                <form action="{{ route('kbntmmin-schedule.destroy-log', $log->id) }}" method="POST" class="d-inline reprint-form" style="margin: 0;" data-manifest="{{ $log->manifest_no }}" data-cycle="{{ $log->cycle }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-info btn-sm text-white" title="Print Ulang">
                                        <i class="bi bi-arrow-repeat me-1"></i> Print Ulang
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="mt-3">
                        <td colspan="8" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Belum ada riwayat auto print</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Tambah Cycle -->
    <div class="modal fade" id="createScheduleModal" tabindex="-1" aria-labelledby="createScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-dark">
                <form action="{{ route('kbntmmin-schedule.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="createScheduleModalLabel">
                            <i class="bi bi-plus-circle me-2"></i> Tambah Jadwal
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Route</label>
                            <input type="text" name="route" class="form-control text-uppercase" list="routeSuggestions" placeholder="contoh: RC13 (kosongkan = semua route)" value="{{ old('route') }}" autocomplete="off">
                            <div class="form-text">Kosongkan kalau jadwal ini berlaku untuk semua route.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Dock</label>
                            <input type="text" name="dock_code" class="form-control text-uppercase" list="dockSuggestions" placeholder="contoh: 4P (kosongkan = semua dock)" value="{{ old('dock_code') }}" autocomplete="off">
                            <div class="form-text">Kosongkan kalau jadwal ini berlaku untuk semua dock di cycle tersebut.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cycle</label>
                            <input type="text" name="cycle" class="form-control" placeholder="contoh: 1" value="{{ old('cycle') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Jam Keluar</label>
                            <input type="time" name="jam_keluar" class="form-control" value="{{ old('jam_keluar') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Printer</label>
                            <input type="text" name="printer_name" class="form-control" list="printerSuggestions" placeholder="Ketik nama printer persis seperti di PC yang nge-print" value="{{ old('printer_name') }}" autocomplete="off" required>
                            <div class="form-text">Nama printer di PC yang menjalankan auto print agent (bukan printer server).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Saran nama printer: printer yang sudah dipakai di schedule + printer yang ter-install di server --}}
    <datalist id="printerSuggestions">
        @foreach (collect($schedules->pluck('printer_name'))->merge($printerOptions)->filter()->unique() as $printer)
            <option value="{{ $printer }}"></option>
        @endforeach
    </datalist>

    {{-- Saran route: route yang ada di data kanban TMMIN + yang sudah dipakai di schedule --}}
    <datalist id="routeSuggestions">
        @foreach ($routeOptions as $route)
            <option value="{{ $route }}"></option>
        @endforeach
    </datalist>

    {{-- Saran dock: dock yang ada di data kanban TMMIN + yang sudah dipakai di schedule --}}
    <datalist id="dockSuggestions">
        @foreach ($dockOptions as $dock)
            <option value="{{ $dock }}"></option>
        @endforeach
    </datalist>

    <!-- Modal Edit Cycle -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-dark">
                <form id="editScheduleForm" action="#" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editScheduleModalLabel">
                            <i class="bi bi-pencil-fill me-2"></i> Edit Jadwal
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Route</label>
                            <input type="text" name="route" id="edit_route" class="form-control text-uppercase" list="routeSuggestions" placeholder="contoh: RC13 (kosongkan = semua route)" autocomplete="off">
                            <div class="form-text">Kosongkan kalau jadwal ini berlaku untuk semua route.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Dock</label>
                            <input type="text" name="dock_code" id="edit_dock_code" class="form-control text-uppercase" list="dockSuggestions" placeholder="contoh: 4P (kosongkan = semua dock)" autocomplete="off">
                            <div class="form-text">Kosongkan kalau jadwal ini berlaku untuk semua dock di cycle tersebut.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cycle</label>
                            <input type="text" name="cycle" id="edit_cycle" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Jam Keluar</label>
                            <input type="time" name="jam_keluar" id="edit_jam_keluar" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Printer</label>
                            <input type="text" name="printer_name" id="edit_printer_name" class="form-control" list="printerSuggestions" placeholder="Ketik nama printer persis seperti di PC yang nge-print" autocomplete="off" required>
                            <div class="form-text">Nama printer di PC yang menjalankan auto print agent (bukan printer server).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save me-1"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

@push('scripts')
<script>
    $(document).ready(function() {

        // Notifikasi hasil simpan/hapus dari controller
        @if (session('sweet_alert'))
            Swal.fire(@json(session('sweet_alert')));
        @endif

        // Buka modal edit
        $('.btn-edit-schedule').on('click', function() {
            const id = $(this).attr('data-id');
            const printer = $(this).attr('data-printer') || '';
            const $printerField = $('#edit_printer_name');

            $('#editScheduleForm').attr('action', '{{ url('kbntmmin-schedule') }}/' + id);
            $('#edit_route').val($(this).attr('data-route') || '');
            $('#edit_dock_code').val($(this).attr('data-dock') || '');
            $('#edit_cycle').val($(this).attr('data-cycle'));
            $('#edit_jam_keluar').val($(this).attr('data-jam'));

            if ($printerField.is('select')) {
                // Hapus opsi "tidak terdeteksi" sisa dari edit sebelumnya
                $printerField.find('option[data-missing="1"]').remove();

                // Printer tersimpan tapi tidak ada di daftar Windows → tetap tampilkan supaya tidak hilang
                const exists = $printerField.find('option').filter(function() {
                    return this.value === printer;
                }).length > 0;

                if (printer && !exists) {
                    $printerField.append(
                        $('<option>', { value: printer, text: printer + ' (tidak terdeteksi)' }).attr('data-missing', '1')
                    );
                }
            }

            $printerField.val(printer);

            $('#editScheduleModal').modal('show');
        });

        // Konfirmasi hapus cycle
        $('.delete-schedule-form').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            const label = $(this).data('label');

            Swal.fire({
                title: 'Hapus jadwal ' + label + '?',
                text: 'Jadwal ini tidak akan di-print otomatis lagi.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Konfirmasi print ulang
        $('.reprint-form').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            const manifest = $(this).data('manifest');
            const cycle = $(this).data('cycle');

            Swal.fire({
                title: 'Print ulang?',
                text: 'Manifest ' + manifest + ' cycle ' + cycle + ' akan di-print ulang otomatis dalam 1 menit.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0dcaf0',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Print Ulang',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush