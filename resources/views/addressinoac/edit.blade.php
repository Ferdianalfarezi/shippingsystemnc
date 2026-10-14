<div class="modal fade" id="editAddressInoacModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAddressInoacForm">
                @csrf
                <input type="hidden" id="edit_id_inoac">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Edit Data INOAC Address</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Part No</label>
                        <input type="text" name="part_no" id="edit_part_no_inoac" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Kode INOAC (yang ada di QR, mis. HIN-01-004)</label>
                        <input type="text" name="inoac_code" id="edit_inoac_code_inoac" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Customer Code</label>
                        <input type="text" name="customer_code" id="edit_customer_code_inoac" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Part Name</label>
                        <input type="text" name="part_name" id="edit_part_name_inoac" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Rack No</label>
                        <input type="text" name="rack_no" id="edit_rack_no_inoac" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>