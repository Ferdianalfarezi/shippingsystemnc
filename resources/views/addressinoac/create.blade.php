<div class="modal fade" id="createAddressInoacModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="createAddressInoacForm">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Tambah Data INOAC Address</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Part No</label>
                        <input type="text" name="part_no" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Kode INOAC (yang ada di QR, mis. HIN-01-004)</label>
                        <input type="text" name="inoac_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Customer Code</label>
                        <input type="text" name="customer_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Part Name</label>
                        <input type="text" name="part_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Rack No</label>
                        <input type="text" name="rack_no" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>