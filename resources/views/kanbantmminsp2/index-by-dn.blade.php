@extends('layouts.app')

@section('title', 'Data Kanban TMMIN P2')
@section('page-title', 'KANBAN TMMIN P2')
@section('body-class', 'kanbantmmins-page')

@section('content')
    @php
        $uniqueDockCodes = $kanbantmmins->pluck('dock_code')->unique()->filter()->sort();
        // Group by manifest_no
        $groupedByManifest = $kanbantmmins->groupBy('manifest_no');
    @endphp

    <!-- Stats Badges dan Dropdown di kanan -->
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 mt-3">

        <!-- Left Side - Upload Info -->
        <div class="d-flex align-items-center gap-2 ms-3">
            @if($latestUploadInfo)
                <strong>Last Upload:</strong> {{ $latestUploadInfo->last_upload_at->format('d M Y H:i:s') }}
                by <strong>{{ $latestUploadInfo->uploaded_by }}</strong>
            @else
                <span class="text-muted"><i class="bi bi-info-circle"></i> Belum ada data yang diimport</span>
            @endif
        </div>

        <!-- Right Side - Search & Menu -->
        <div class="d-flex align-items-center gap-2">

            <!-- View Toggle Buttons -->
            <div class="btn-group" role="group" aria-label="View toggle">
                <a href="{{ route('kanbantmminsp2.index') }}" class="btn btn-outline-secondary" title="View All Items">
                    <i class="bi bi-list-ul"></i> All Items
                </a>
                <a href="{{ route('kanbantmminsp2.indexByDn') }}" class="btn btn-secondary active" title="View By Delivery Note">
                    <i class="bi bi-collection"></i> By DN
                </a>
            </div>

            <!-- Print Selected Button (Hidden by default) -->
            <button class="btn btn-success d-none" id="printSelectedBtn">
                <i class="bi bi-printer-fill me-1"></i> Print Selected (<span id="selectedCount">0</span>)
            </button>

            <!-- Print All Button -->
            <button class="btn btn-primary" id="printAllBtn">
                <i class="bi bi-printer me-1"></i> Print All
            </button>

            <!-- Dock Filter Dropdown -->
            <select class="form-select" id="dockFilterSelect" style="width: 130px;">
                <option value="all">All Dock</option>
                @foreach($uniqueDockCodes as $dockCode)
                    <option value="{{ $dockCode }}">{{ $dockCode }}</option>
                @endforeach
            </select>

            <!-- Search Bar -->
            <div class="input-group" style="width: 280px;">
                <input type="text" class="form-control" id="searchInput" placeholder="Cari Manifest No, Part No..." value="">
                <button class="btn btn-secondary" type="button" id="searchButton">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            <!-- Per Page Selector -->
            <select class="form-select" id="perPageSelect" style="width: 85px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="all">All</option>
            </select>

            <!-- Dropdown Menu Button -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2">
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-0 m-0" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
                            <i class="bi bi-three-dots-vertical fs-4"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuButton">
                            <li>
                                <a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#importTxtModal">
                                    <i class="bi bi-file-earmark-text text-primary me-2"></i> Import TXT
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Total Data Badge -->
            <div class="bg-primary card border-0 shadow-sm me-2">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-box-seam text-white fs-5"></i>
                        </div>
                        <div>
                            <small class="text-white d-block fw-bold me-3" style="font-size: 0.7rem;">Total DN</small>
                            <h5 class="mb-0 fw-bold text-white">{{ $groupedByManifest->count() }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="table-responsive p-0 mt-0">
        <table class="table table-compact w-100 mt-1" id="kanbantmminsTable">
            <thead>
                <tr class="fs-6">
                    <th style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th>Manifest No (DN)</th>
                    <th>Dock Code</th>
                    <th>Route</th>
                    <th>Supplier</th>
                    <th>Total Part No</th>
                    <th>Total PCS</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                @forelse($groupedByManifest as $manifestNo => $items)
                    @php
                        $firstItem = $items->first();
                        $totalPcs = $items->sum('pcs');
                        $uniquePartNoCount = $items->pluck('part_no')->unique()->count();
                        $routes = $items->pluck('route')->unique()->filter()->implode(', ');
                        $suppliers = $items->pluck('supplier')->unique()->filter()->first() ?? '-';
                    @endphp
                    <tr class="fs-6 kanban-row"
                        data-dock="{{ $firstItem->dock_code }}"
                        data-manifest="{{ $manifestNo }}"
                        data-ids="{{ $items->pluck('id')->implode(',') }}">
                        <td>
                            <input type="checkbox" class="form-check-input row-select"
                                   value="{{ $items->pluck('id')->implode(',') }}"
                                   data-dock="{{ $firstItem->dock_code }}"
                                   data-manifest="{{ $manifestNo }}">
                        </td>
                        <td><strong>{{ $manifestNo }}</strong></td>
                        <td>
                            @foreach($items->pluck('dock_code')->unique() as $dc)
                                <span class="badge bg-white text-dark fs-6">{{ $dc }}</span>
                            @endforeach
                        </td>
                        <td>{{ $routes ?: '-' }}</td>
                        <td>{{ Str::limit($suppliers, 25) }}</td>
                        <td>
                            <span class="badge bg-info text-dark">{{ $uniquePartNoCount }} part</span>
                        </td>
                        <td><strong>{{ $totalPcs }}</strong></td>
                        <td>
                            <div class="d-flex justify-content-center p-1" style="gap: 0;">
                                <!-- List Items Button -->
                                <button type="button"
                                        class="btn btn-info btn-sm btn-action-square list-items-btn"
                                        style="border-radius: 6px 0 0 6px; margin: 0;"
                                        title="View Items"
                                        data-bs-toggle="modal"
                                        data-bs-target="#itemsModal"
                                        data-manifest="{{ $manifestNo }}"
                                        data-items="{{ $items->values()->toJson() }}">
                                    <i class="bi bi-list-ul"></i>
                                </button>

                                <!-- Print Group Button -->
                                <a href="{{ route('kanbantmminsp2.printgroup', ['manifest_no' => $manifestNo]) }}"
                                   class="btn btn-success btn-sm btn-action-square"
                                   style="border-radius: 0; margin: 0;"
                                   title="Print Group"
                                   target="_blank">
                                    <i class="bi bi-printer-fill"></i>
                                </a>

                                <!-- Delete Group Button -->
                                <form action="{{ route('kanbantmminsp2.destroyGroup', ['manifest_no' => $manifestNo]) }}"
                                      method="POST"
                                      class="d-inline delete-group-form"
                                      style="margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-danger btn-sm btn-action-square"
                                            style="border-radius: 0 6px 6px 0; margin: 0;"
                                            title="Hapus Group">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="mt-3 empty-row">
                        <td colspan="8" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Belum ada data kanban</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Info & Controls -->
    <div class="d-flex justify-content-between align-items-center mt-3 me-3" id="paginationContainer">
        <div class="text-muted" id="paginationInfo">
            Showing <span id="showingFrom">1</span> to <span id="showingTo">10</span> of <span id="totalFiltered">{{ $groupedByManifest->count() }}</span> entries
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0" id="paginationNav"></ul>
        </nav>
    </div>

    <!-- Items List Modal -->
    <div class="modal fade" id="itemsModal" tabindex="-1" aria-labelledby="itemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="itemsModalLabel">
                        <i class="bi bi-list-ul me-2"></i>Items for Manifest: <span id="modalManifestNo"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="itemsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Part No</th>
                                    <th>Part Name</th>
                                    <th>Dock</th>
                                    <th>Address</th>
                                    <th>PCS</th>
                                    <th>Route</th>
                                    <th>Unique No</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <span class="text-muted">
                            Total: <strong id="modalTotalPartNo">0</strong> unique part,
                            <strong id="modalTotalItems">0</strong> items,
                            <strong id="modalTotalPcs">0</strong> PCS
                        </span>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-success" id="printGroupBtn" target="_blank">
                        <i class="bi bi-printer me-1"></i> Print All Items
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Import TXT Modal -->
    <div class="modal fade" id="importTxtModal" tabindex="-1" aria-labelledby="importTxtModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="importTxtModalLabel">
                        <i class="bi bi-file-earmark-text me-2 text-dark"></i>Import TXT File (P2)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('kanbantmminsp2.import') }}" method="POST" enctype="multipart/form-data" id="importTxtForm">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Perhatian!</strong> Import akan menghapus semua data Kanban TMMIN P2 yang ada dan menggantinya dengan data baru.
                        </div>
                        <div class="mb-3">
                            <label for="txtFile" class="form-label text-dark">Pilih File TXT</label>
                            <input type="file" class="form-control" id="txtFile" name="file" accept=".txt" required>
                            <div class="form-text">Format file: *_KBN.txt (max 5MB)</div>
                        </div>
                        <div class="progress d-none" id="importProgress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="importButton">
                            <i class="bi bi-upload me-2"></i>Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Print All Modal -->
    <div class="modal fade" id="printAllModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark"><i class="bi bi-printer me-2 text-dark"></i>Print All - Select Dock Codes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-dark">
                    <p>Pilih dock code yang ingin di-print:</p>
                    <div id="printAllDockSelection" class="d-flex flex-wrap gap-2">
                        @foreach($uniqueDockCodes as $dockCode)
                        <div class="form-check">
                            <input class="form-check-input print-all-dock-check" type="checkbox" value="{{ $dockCode }}" id="printAll_{{ $dockCode }}" checked>
                            <label class="form-check-label" for="printAll_{{ $dockCode }}">{{ $dockCode }}</label>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-primary" id="selectAllPrintDocks">Select All</button>
                        <button class="btn btn-sm btn-secondary" id="deselectAllPrintDocks">Deselect All</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="confirmPrintAllBtn">
                        <i class="bi bi-printer me-2"></i>Print All
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

@push('scripts')
<script>
    $(document).ready(function() {
        // URL dasar P2
        const PRINT_ITEM_URL  = '{{ url("kanbantmminsp2") }}';                 // + '/{id}/print'
        const PRINT_GROUP_URL = '{{ route("kanbantmminsp2.printgroup") }}';     // + '?manifest_no=...'

        // Pagination variables
        let currentPage = 1;
        let perPage = 10;
        let allRows = [];
        let filteredRows = [];

        function initRows() {
            allRows = $('.kanban-row').not('.empty-row').toArray();
            filteredRows = [...allRows];
        }

        initRows();

        function applyPagination() {
            $(allRows).hide();

            if (perPage === 'all') {
                $(filteredRows).show();
                updatePaginationInfo(1, filteredRows.length, filteredRows.length);
                renderPagination(1, 1);
            } else {
                const totalPages = Math.ceil(filteredRows.length / perPage);
                if (currentPage > totalPages) currentPage = totalPages || 1;

                const start = (currentPage - 1) * perPage;
                const end = start + perPage;

                filteredRows.slice(start, end).forEach(row => $(row).show());

                updatePaginationInfo(start + 1, Math.min(end, filteredRows.length), filteredRows.length);
                renderPagination(currentPage, totalPages);
            }
        }

        function updatePaginationInfo(from, to, total) {
            if (total === 0) {
                $('#paginationInfo').html('No entries found');
            } else {
                $('#showingFrom').text(from);
                $('#showingTo').text(to);
                $('#totalFiltered').text(total);
            }
        }

        function renderPagination(current, total) {
            let html = '';

            if (total <= 1) {
                $('#paginationNav').html('');
                return;
            }

            html += `<li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">«</a>
            </li>`;

            let startPage = Math.max(1, current - 2);
            let endPage = Math.min(total, current + 2);

            if (startPage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
                if (startPage > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }

            if (endPage < total) {
                if (endPage < total - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${total}">${total}</a></li>`;
            }

            html += `<li class="page-item ${current === total ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current + 1}">»</a>
            </li>`;

            $('#paginationNav').html(html);
        }

        $(document).on('click', '#paginationNav .page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && !$(this).parent().hasClass('disabled')) {
                currentPage = page;
                applyPagination();
            }
        });

        $('#perPageSelect').on('change', function() {
            const val = $(this).val();
            perPage = val === 'all' ? 'all' : parseInt(val);
            currentPage = 1;
            applyPagination();
        });

        function filterTable() {
            const search = $('#searchInput').val().toLowerCase();
            const selectedDock = $('#dockFilterSelect').val();

            filteredRows = allRows.filter(function(row) {
                const $row = $(row);
                const dock = String($row.data('dock'));
                const text = $row.text().toLowerCase();

                const matchesSearch = search === '' || text.includes(search);
                const matchesDock = selectedDock === 'all' || dock === selectedDock;

                return matchesSearch && matchesDock;
            });

            currentPage = 1;
            applyPagination();

            $('#selectAll').prop('checked', false);
            updateSelectedCount();
        }

        $('#searchButton').on('click', filterTable);
        $('#searchInput').on('keypress', function(e) {
            if (e.which === 13) filterTable();
        });
        $('#searchInput').on('input', filterTable);
        $('#dockFilterSelect').on('change', filterTable);

        applyPagination();

        // ========== CHECKBOX & PRINT SELECTED ==========

        function updateSelectedCount() {
            let totalIds = 0;
            $('.row-select:checked').each(function() {
                totalIds += $(this).val().split(',').length;
            });
            $('#selectedCount').text(totalIds);

            if (totalIds > 0) {
                $('#printSelectedBtn').removeClass('d-none');
            } else {
                $('#printSelectedBtn').addClass('d-none');
            }
        }

        $('#selectAll').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.kanban-row:visible .row-select').prop('checked', isChecked);
            updateSelectedCount();
        });

        $(document).on('change', '.row-select', function() {
            updateSelectedCount();

            const totalVisible = $('.kanban-row:visible .row-select').length;
            const checkedVisible = $('.kanban-row:visible .row-select:checked').length;
            $('#selectAll').prop('checked', totalVisible > 0 && totalVisible === checkedVisible);
        });

        $('#printSelectedBtn').on('click', function() {
            let selectedIds = [];
            $('.row-select:checked').each(function() {
                selectedIds = selectedIds.concat($(this).val().split(','));
            });

            if (selectedIds.length === 0) {
                Swal.fire({ title: 'Error!', text: 'Tidak ada data yang dipilih', icon: 'error' });
                return;
            }

            const url = '{{ route("kanbantmminsp2.printselected") }}?ids=' + selectedIds.join(',');
            window.open(url, '_blank');
        });

        // ========== ITEMS MODAL ==========

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        function displayAddress(address) {
            if (!address || String(address).toUpperCase() === 'NO ADDRESS') return '-';
            return address;
        }

        $(document).on('click', '.list-items-btn', function() {
            const manifestNo = $(this).data('manifest');
            const items = $(this).data('items');

            $('#modalManifestNo').text(manifestNo);
            $('#printGroupBtn').attr('href', PRINT_GROUP_URL + '?manifest_no=' + encodeURIComponent(manifestNo));

            let html = '';
            let totalPcs = 0;
            let uniquePartNos = new Set();

            if (items && items.length > 0) {
                items.forEach(function(item, index) {
                    totalPcs += parseInt(item.pcs) || 0;
                    if (item.part_no) {
                        uniquePartNos.add(item.part_no);
                    }
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td><strong>${escapeHtml(item.part_no || '-')}</strong></td>
                            <td>${escapeHtml((item.part_name || '-').substring(0, 30))}</td>
                            <td><span class="badge bg-secondary">${escapeHtml(item.dock_code || '-')}</span></td>
                            <td><strong>${escapeHtml(displayAddress(item.address))}</strong></td>
                            <td>${item.pcs || 0}</td>
                            <td>${escapeHtml(item.route || '-')}</td>
                            <td>${escapeHtml(item.unique_no || '-')}</td>
                            <td>
                                <a href="${PRINT_ITEM_URL}/${item.id}/print" class="btn btn-success btn-sm" target="_blank" title="Print">
                                    <i class="bi bi-printer-fill"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                });

                $('#modalTotalPartNo').text(uniquePartNos.size);
                $('#modalTotalItems').text(items.length);
                $('#modalTotalPcs').text(totalPcs);
            } else {
                html = '<tr><td colspan="9" class="text-center">No items found</td></tr>';
                $('#modalTotalPartNo').text(0);
                $('#modalTotalItems').text(0);
                $('#modalTotalPcs').text(0);
            }

            $('#itemsTableBody').html(html);
        });

        // ========== PRINT ALL ==========

        $('#printAllBtn').on('click', function() {
            $('#printAllModal').modal('show');
        });

        $('#selectAllPrintDocks').on('click', function() {
            $('.print-all-dock-check').prop('checked', true);
        });

        $('#deselectAllPrintDocks').on('click', function() {
            $('.print-all-dock-check').prop('checked', false);
        });

        $('#confirmPrintAllBtn').on('click', function() {
            const selectedDocks = $('.print-all-dock-check:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedDocks.length === 0) {
                Swal.fire({ title: 'Error!', text: 'Pilih minimal satu dock code', icon: 'error' });
                return;
            }

            const url = '{{ route("kanbantmminsp2.printall") }}?dock_codes=' + selectedDocks.join(',');
            window.open(url, '_blank');
            $('#printAllModal').modal('hide');
        });

        // ========== DELETE GROUP ==========
        $('.delete-group-form').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const url = form.attr('action');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Semua data dalam group ini akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus Semua!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => { Swal.showLoading(); }
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
                                text: xhr.responseJSON?.message || 'Terjadi kesalahan',
                                icon: 'error',
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    });
                }
            });
        });

        // ========== IMPORT ==========
        $('#importTxtForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const fileInput = $('#txtFile')[0];

            if (!fileInput.files.length) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Silakan pilih file TXT terlebih dahulu',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }

            Swal.fire({
                title: 'Import Data?',
                text: 'Semua data Kanban TMMIN P2 akan dihapus dan diganti dengan data baru!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Import!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#importProgress').removeClass('d-none');
                    $('#importButton').prop('disabled', true);

                    $.ajax({
                        url: '{{ route("kanbantmminsp2.import") }}',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            $('#importProgress').addClass('d-none');
                            $('#importButton').prop('disabled', false);
                            $('#importTxtModal').modal('hide');

                            Swal.fire({
                                title: 'Berhasil!',
                                text: response.message || 'Data berhasil diimport.',
                                icon: 'success',
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                window.location.reload();
                            });
                        },
                        error: function(xhr) {
                            $('#importProgress').addClass('d-none');
                            $('#importButton').prop('disabled', false);

                            Swal.fire({
                                title: 'Gagal!',
                                text: xhr.responseJSON?.message || 'Terjadi kesalahan saat import',
                                icon: 'error',
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    });
                }
            });
        });

        @if(session('sweet_alert'))
            Swal.fire({
                icon: '{{ session("sweet_alert.type") }}',
                title: '{{ session("sweet_alert.title") }}',
                text: '{{ session("sweet_alert.text") }}',
                showConfirmButton: {{ session("sweet_alert.showConfirmButton") ? 'true' : 'false' }},
                timer: {{ session("sweet_alert.timer") ?? 'null' }}
            });
        @endif
    });
</script>
@endpush