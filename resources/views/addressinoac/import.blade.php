<div class="modal fade" id="importInoacModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="importInoacForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Import Excel INOAC Address</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small text-secondary">File Excel (.xlsx)</label>
                    <input type="file" name="excel" id="excelInoacFile" accept=".xlsx,.xls" required class="form-control">
                    <div class="small text-muted mt-1">Baris 1 judul, header di baris 2: <code>CUSTOMER_CODE</code>, <code>PART_NO</code>, <code>RACK_NO</code>, <code>PART_NAME</code>. Kode INOAC diambil dari kurung di PART_NAME, mis. <code>(RET-00-B01A)</code></div>
                    <div id="importInoacProgress" class="d-none mt-2">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span class="small text-muted ms-1">Memproses import...</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="importInoacButton" class="btn btn-success">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>