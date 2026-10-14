@extends('layouts.app')

@section('title', 'Kanban INOAC')
@section('page-title', 'KANBAN INOAC')
@section('body-class', 'kanban-inoac-split-page')

@section('content')

    <div class="row justify-content-center mt-3">
        <div class="col-lg-6 col-md-8">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">

                    <div class="d-flex align-items-center mb-1">
                        <div class="bg-primary bg-opacity-10 p-2 rounded me-2">
                            <i class="bi bi-file-earmark-pdf text-primary fs-5"></i>
                        </div>
                        <h5 class="mb-0 fw-bold">Split Kanban INOAC</h5>
                    </div>
                    

                    <form id="splitForm" action="{{ route('kanban-inoac-split.process') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-secondary">File PDF Kanban INOAC (bisa pilih lebih dari 1)</label>
                            <input type="file" name="pdf[]" accept="application/pdf" required multiple class="form-control">
                            <div id="selectedFilesInfo" class="small text-muted mt-1"></div>
                        </div>


                        <button type="submit" id="submitBtn" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                            <span id="submitText">Split &amp; Preview</span>
                            <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </form>

                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-10 p-2 rounded me-2">
                                <i class="bi bi-clock-history text-secondary fs-5"></i>
                            </div>
                            <h6 class="mb-0 fw-bold">Riwayat Split Terakhir</h6>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" onclick="loadRecentSplits()" title="Refresh">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>

                    <div id="recentList" class="list-group list-group-flush">
                        <div class="text-center text-muted small py-3" id="recentEmpty">
                            <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                            Belum ada riwayat split
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Preview -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="height: 90vh;">

                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold mb-0 text-dark">
                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i> Preview Hasil Split
                    </h6>
                    <button type="button" class="btn-close" onclick="closePdfModal()"></button>
                </div>

                <div id="filterBar" class="d-none px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-center gap-2">
                    <label class="small fw-semibold text-secondary mb-0">No. DN:</label>
                    <select id="dnFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Semua DN</option>
                    </select>

                    <label class="small fw-semibold text-secondary mb-0 ms-2">Plant:</label>
                    <select id="plantFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Semua Plant</option>
                    </select>

                    <label class="small fw-semibold text-secondary mb-0 ms-2">Cari Part No / Rack:</label>
                    <div class="position-relative">
                        <input type="text" id="partSearch" class="form-control form-control-sm ps-4"
                               style="width: 220px;" placeholder="mis. HIN-01-004"
                               autocomplete="off">
                        <i class="bi bi-search position-absolute text-muted" style="left: 8px; top: 6px; font-size: 0.75rem;"></i>
                    </div>
                    <button type="button" id="clearSearchBtn" class="btn btn-sm btn-light d-none" title="Bersihin pencarian">
                        <i class="bi bi-x-lg"></i>
                    </button>

                    <span id="filterCount" class="small text-muted"></span>
                    <span id="filterLoading" class="d-none small text-muted">
                        <span class="spinner-border spinner-border-sm"></span> Memproses filter...
                    </span>
                </div>

                <div class="modal-body p-0 bg-light flex-grow-1">
                    <iframe id="pdfFrame" class="w-100 h-100 border-0"></iframe>
                </div>

                <div class="modal-footer py-2 d-flex justify-content-between">
                    <div id="resultInfo" class="small text-muted"></div>
                    <div class="d-flex gap-2">
                        <a id="downloadLink" href="#" download="hasil-split-inoac.pdf" class="btn btn-light btn-sm">
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                        <button onclick="printPdf()" class="btn btn-primary btn-sm">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script>
    const form = document.getElementById('splitForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');
    const pdfFrame = document.getElementById('pdfFrame');
    const downloadLink = document.getElementById('downloadLink');
    const resultInfo = document.getElementById('resultInfo');
    const filterBar = document.getElementById('filterBar');
    const dnFilter = document.getElementById('dnFilter');
    const plantFilter = document.getElementById('plantFilter');
    const partSearch = document.getElementById('partSearch');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const filterCount = document.getElementById('filterCount');
    const filterLoading = document.getElementById('filterLoading');
    const fileInput = form.querySelector('input[name="pdf[]"]');
    const selectedFilesInfo = document.getElementById('selectedFilesInfo');

    const pdfModalEl = document.getElementById('pdfModal');
    const pdfModal = new bootstrap.Modal(pdfModalEl);

    const labelsMetaUrlTemplate = "{{ route('kanban-inoac-split.labels-meta', ['token' => 'TOKEN_PLACEHOLDER']) }}";
    const downloadUrlTemplate = "{{ route('kanban-inoac-split.download', ['token' => 'TOKEN_PLACEHOLDER']) }}";
    const recentUrl = "{{ route('kanban-inoac-split.recent') }}";

    const recentList = document.getElementById('recentList');
    const recentEmpty = document.getElementById('recentEmpty');

    let currentBlobUrl = null;
    let originalPdfBytes = null;
    let labelsMeta = [];

    fileInput.addEventListener('change', function () {
        const n = fileInput.files.length;
        selectedFilesInfo.textContent = n > 0 ? `${n} file dipilih` : '';
    });

    function debounce(fn, delay) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), delay);
        };
    }

    function normalizeForSearch(str) {
        return (str || '').toString().toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        submitText.textContent = isLoading ? 'Memproses...' : 'Split & Preview';
        submitSpinner.classList.toggle('d-none', !isLoading);
    }

    function showAlert(message) {
        Swal.fire({ title: 'Gagal!', text: message, icon: 'error', confirmButtonColor: '#dc2626' });
    }

    function closePdfModal() {
        pdfModal.hide();
    }

    pdfModalEl.addEventListener('hidden.bs.modal', function () {
        pdfFrame.src = 'about:blank';
        if (currentBlobUrl) {
            URL.revokeObjectURL(currentBlobUrl);
            currentBlobUrl = null;
        }
        originalPdfBytes = null;
        labelsMeta = [];
        dnFilter.innerHTML = '<option value="">Semua DN</option>';
        plantFilter.innerHTML = '<option value="">Semua Plant</option>';
        partSearch.value = '';
        clearSearchBtn.classList.add('d-none');
        filterBar.classList.add('d-none');
        filterCount.textContent = '';
    });

    function printPdf() {
        pdfFrame.contentWindow.focus();
        pdfFrame.contentWindow.print();
    }

    function setBlobToFrame(bytes, filename) {
        if (currentBlobUrl) {
            URL.revokeObjectURL(currentBlobUrl);
        }
        const blob = new Blob([bytes], { type: 'application/pdf' });
        currentBlobUrl = URL.createObjectURL(blob);
        pdfFrame.src = currentBlobUrl;
        downloadLink.href = currentBlobUrl;
        downloadLink.download = filename;
    }

    async function loadFiltersMeta(metaToken) {
        dnFilter.innerHTML = '<option value="">Semua DN</option>';
        plantFilter.innerHTML = '<option value="">Semua Plant</option>';
        partSearch.value = '';
        partSearch.disabled = true;
        partSearch.placeholder = 'Memuat metadata...';
        clearSearchBtn.classList.add('d-none');

        if (!metaToken) {
            filterBar.classList.add('d-none');
            return;
        }

        try {
            const url = labelsMetaUrlTemplate.replace('TOKEN_PLACEHOLDER', metaToken);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('gagal ambil metadata');

            const data = await res.json();
            labelsMeta = data.labels || [];

            if (labelsMeta.length === 0) {
                filterBar.classList.add('d-none');
                return;
            }

            const dnCounts = {};
            labelsMeta.forEach(l => {
                const sj = l.dn_no || '(gak terbaca)';
                dnCounts[sj] = (dnCounts[sj] || 0) + 1;
            });
            Object.keys(dnCounts).sort().forEach(sj => {
                const opt = document.createElement('option');
                opt.value = sj;
                opt.textContent = `${sj} (${dnCounts[sj]})`;
                dnFilter.appendChild(opt);
            });

            const plantCounts = {};
            labelsMeta.forEach(l => {
                const plant = l.plant || '(Belum Match Rack)';
                plantCounts[plant] = (plantCounts[plant] || 0) + 1;
            });
            const plantOrder = ['Plant 1', 'Plant 2', '(Belum Match Rack)'];
            plantOrder.filter(p => plantCounts[p]).forEach(plant => {
                const opt = document.createElement('option');
                opt.value = plant;
                opt.textContent = `${plant} (${plantCounts[plant]})`;
                plantFilter.appendChild(opt);
            });

            filterBar.classList.remove('d-none');
            partSearch.disabled = false;
            partSearch.placeholder = 'mis. HIN-01-004';
        } catch (err) {
            console.error('Gagal load metadata filter:', err);
            filterBar.classList.add('d-none');
        }
    }

    async function applyFilters() {
        if (!originalPdfBytes) return;

        const selectedDn = dnFilter.value;
        const selectedPlant = plantFilter.value;
        const rawSearchTerm = partSearch.value.trim();
        const searchTerm = normalizeForSearch(rawSearchTerm);

        clearSearchBtn.classList.toggle('d-none', rawSearchTerm === '');

        if (!selectedDn && !selectedPlant && !searchTerm) {
            setBlobToFrame(originalPdfBytes, 'hasil-split-inoac.pdf');
            filterCount.textContent = '';
            return;
        }

        if (searchTerm && labelsMeta.length === 0) {
            filterCount.textContent = 'Metadata masih dimuat, coba lagi sebentar...';
            return;
        }

        filterLoading.classList.remove('d-none');
        try {
            const matchingPages = labelsMeta
                .filter(l => {
                    const sjOk = !selectedDn || (l.dn_no || '(gak terbaca)') === selectedDn;
                    const plantOk = !selectedPlant || (l.plant || '(Belum Match Rack)') === selectedPlant;
                    const searchOk = !searchTerm
                        || normalizeForSearch(l.part_no).includes(searchTerm)
                        || normalizeForSearch(l.part_no_raw).includes(searchTerm)
                        || normalizeForSearch(l.rack_no).includes(searchTerm);
                    return sjOk && plantOk && searchOk;
                })
                .map(l => l.output_page - 1);

            if (matchingPages.length === 0) {
                filterCount.textContent = 'Gak ada label yang cocok sama filter ini.';
                filterLoading.classList.add('d-none');
                return;
            }

            const { PDFDocument } = PDFLib;
            const srcDoc = await PDFDocument.load(originalPdfBytes);
            const newDoc = await PDFDocument.create();
            const copiedPages = await newDoc.copyPages(srcDoc, matchingPages);
            copiedPages.forEach(p => newDoc.addPage(p));

            const filteredBytes = await newDoc.save();
            const namePart = [selectedDn, selectedPlant, searchTerm].filter(Boolean).join('_').replace(/[^A-Za-z0-9_\-]/g, '_');
            setBlobToFrame(filteredBytes, `hasil-split-inoac_${namePart || 'filter'}.pdf`);

            filterCount.textContent = `Menampilkan ${matchingPages.length} label`;
        } catch (err) {
            console.error(err);
            showAlert('Gagal filter PDF: ' + err.message);
        } finally {
            filterLoading.classList.add('d-none');
        }
    }

    dnFilter.addEventListener('change', applyFilters);
    plantFilter.addEventListener('change', applyFilters);
    partSearch.addEventListener('input', debounce(applyFilters, 300));
    clearSearchBtn.addEventListener('click', () => {
        partSearch.value = '';
        clearSearchBtn.classList.add('d-none');
        applyFilters();
    });

    function badgeClass(unmatched) {
        return unmatched > 0 ? 'bg-warning text-dark' : 'bg-success';
    }

    async function loadRecentSplits() {
        try {
            const res = await fetch(recentUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('gagal ambil riwayat');
            const data = await res.json();
            const items = data.items || [];

            recentList.querySelectorAll('.recent-item').forEach(el => el.remove());

            if (items.length === 0) {
                recentEmpty.classList.remove('d-none');
                return;
            }
            recentEmpty.classList.add('d-none');

            items.forEach(item => {
                const row = document.createElement('div');
                row.className = 'list-group-item recent-item px-0 py-2 d-flex align-items-center justify-content-between';
                row.innerHTML = `
                    <div class="me-2" style="min-width:0;">
                        <div class="fw-semibold small text-truncate" style="max-width: 220px;" title="${item.filename}">
                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>${item.filename}
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            ${item.created_at} · ${item.total} label
                            <span class="badge ${badgeClass(item.unmatched)} ms-1">${item.matched} matched${item.unmatched > 0 ? ' / ' + item.unmatched + ' unmatched' : ''}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0 btn-view-recent" onclick="openRecentSplit('${item.token}', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                `;
                recentList.appendChild(row);
            });
        } catch (err) {
            console.error('Gagal load riwayat split:', err);
        }
    }

    async function openRecentSplit(token, btnEl) {
        const icon = btnEl ? btnEl.querySelector('i') : null;
        const originalIconClass = icon ? icon.className : null;

        if (btnEl) {
            btnEl.disabled = true;
            icon.className = 'spinner-border spinner-border-sm';
        }

        try {
            const url = downloadUrlTemplate.replace('TOKEN_PLACEHOLDER', token);
            const response = await fetch(url, { headers: { 'Accept': 'application/pdf' } });
            if (!response.ok) throw new Error('File hasil split sudah tidak ada.');

            const matched = response.headers.get('X-Matched');
            const unmatched = response.headers.get('X-Unmatched');
            const total = response.headers.get('X-Total-Labels');

            const blob = await response.blob();
            originalPdfBytes = await blob.arrayBuffer();
            setBlobToFrame(originalPdfBytes, 'hasil-split-inoac.pdf');

            resultInfo.textContent = total !== null
                ? `Total: ${total} label · Matched: ${matched} · Unmatched: ${unmatched}`
                : '';

            pdfModal.show();
            await loadFiltersMeta(token);
        } catch (err) {
            showAlert(err.message || 'Gagal membuka riwayat split.');
        } finally {
            if (btnEl) {
                btnEl.disabled = false;
                icon.className = originalIconClass;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', loadRecentSplits);

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        setLoading(true);

        try {
            const formData = new FormData(form);

            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/pdf, application/json' },
            });

            const contentType = response.headers.get('Content-Type') || '';

            if (!response.ok) {
                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    const msg = data.errors
                        ? Object.values(data.errors).flat().join(', ')
                        : (data.message || 'Gagal memproses PDF.');
                    throw new Error(msg);
                }
                throw new Error('Gagal memproses PDF (status ' + response.status + ').');
            }

            if (!contentType.includes('application/pdf')) {
                throw new Error('Response bukan PDF, cek controller-nya.');
            }

            const matched = response.headers.get('X-Matched');
            const unmatched = response.headers.get('X-Unmatched');
            const total = response.headers.get('X-Total-Labels');
            const noText = response.headers.get('X-Unmatched-No-Text');
            const noMaster = response.headers.get('X-Unmatched-No-Master');
            const noRack = response.headers.get('X-Unmatched-No-Rack');
            const metaToken = response.headers.get('X-Meta-Token');

            const blob = await response.blob();
            originalPdfBytes = await blob.arrayBuffer();

            setBlobToFrame(originalPdfBytes, 'hasil-split-inoac.pdf');

            if (total !== null) {
                let info = `Total: ${total} label · Matched: ${matched} · Unmatched: ${unmatched}`;
                if (unmatched > 0) {
                    info += ` (QR gagal dibaca: ${noText} · Gak ada di master: ${noMaster} · Rack kosong: ${noRack})`;
                }
                resultInfo.textContent = info;
            } else {
                resultInfo.textContent = '';
            }

            pdfModal.show();

            await loadFiltersMeta(metaToken);

            loadRecentSplits();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan, coba lagi.');
        } finally {
            setLoading(false);
        }
    });
</script>
@endpush