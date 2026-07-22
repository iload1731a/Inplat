<?php declare(strict_types=1); ?>
<?php
$queue    = is_array($queue ?? null) ? $queue : [];
$kycStats = is_array($kycStats ?? null) ? $kycStats : [];
$filters  = is_array($filters ?? null) ? $filters : [];
$csrf     = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">KYC Verification</h1>
        <p class="text-secondary mb-0">Review identity documents and manage KYC status.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total Docs</div><div class="h4 mb-0"><?= number_format((int)($kycStats['total_documents'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Pending Review</div><div class="h4 mb-0 text-warning"><?= number_format((int)($kycStats['pending_review'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Approved</div><div class="h4 mb-0 text-success"><?= number_format((int)($kycStats['approved'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Rejected</div><div class="h4 mb-0 text-danger"><?= number_format((int)($kycStats['rejected'] ?? 0)) ?></div></div></div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/kyc">
        <div class="col-lg-4"><input class="form-control" type="text" name="search" placeholder="Search by username, email or document number" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <?php foreach (['pending','approved','rejected','expired'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="document_type">
                <option value="">All Doc Types</option>
                <?php foreach (['passport','national_id','driver_license','utility_bill','bank_statement'] as $dt): ?>
                    <option value="<?= e($dt) ?>" <?= (($filters['document_type'] ?? '') === $dt) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$dt))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-4 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="/admin/kyc">Reset</a>
        </div>
    </form>
</div>

<!-- KYC Queue -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>ID</th><th>User</th><th>KYC Status</th><th>Document Type</th><th>Doc Number</th><th>Submitted</th><th>Status</th><th>Reviewer</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($queue as $doc): ?>
                <tr>
                    <td class="text-secondary small"><?= (int)($doc['id'] ?? 0) ?></td>
                    <td>
                        <div class="fw-semibold small"><?= e((string)($doc['username'] ?? '-')) ?></div>
                        <div class="text-secondary small"><?= e((string)($doc['email'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge text-bg-<?= ['approved'=>'success','pending'=>'warning','rejected'=>'danger','unverified'=>'secondary'][$doc['kyc_status'] ?? ''] ?? 'secondary' ?>"><?= e(ucfirst((string)($doc['kyc_status'] ?? '-'))) ?></span></td>
                    <td><?= e(ucwords(str_replace('_',' ', (string)($doc['document_type'] ?? '-')))) ?></td>
                    <td class="small text-secondary"><?= e((string)($doc['document_number'] ?? '-')) ?></td>
                    <td class="text-secondary small"><?= e((string)($doc['submitted_at'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= ['approved'=>'success','pending'=>'warning','rejected'=>'danger'][$doc['status'] ?? ''] ?? 'secondary' ?>"><?= e(ucfirst((string)($doc['status'] ?? '-'))) ?></span></td>
                    <td class="small"><?= e((string)($doc['reviewer_name'] ?? '-')) ?></td>
                    <td class="text-end">
                        <?php if (($doc['status'] ?? '') === 'pending'): ?>
                            <button class="btn btn-xs btn-outline-success me-1" onclick="openKycReview(<?= (int)$doc['id'] ?>, '<?= e((string)$doc['username']) ?>', 'approved')">Approve</button>
                            <button class="btn btn-xs btn-outline-danger" onclick="openKycReview(<?= (int)$doc['id'] ?>, '<?= e((string)$doc['username']) ?>', 'rejected')">Reject</button>
                        <?php else: ?>
                            <span class="text-secondary small">Reviewed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($queue === []): ?>
                <tr><td colspan="9" class="text-center text-secondary">No KYC documents found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- KYC Review Modal -->
<div class="modal fade" id="kycReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/kyc/review" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="document_id" id="kycDocId">
            <input type="hidden" name="status" id="kycStatus">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="kycReviewTitle">Review KYC Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">User: <strong id="kycUsername"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Reviewer Notes</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="Optional notes..."></textarea>
                </div>
                <div id="kycLevelGroup">
                    <label class="form-label">Grant KYC Level</label>
                    <select class="form-select" name="kyc_level">
                        <option value="1">Level 1 – Basic</option>
                        <option value="2">Level 2 – Intermediate</option>
                        <option value="3">Level 3 – Full</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="kycSubmitBtn" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openKycReview(docId, username, status) {
    document.getElementById('kycDocId').value = docId;
    document.getElementById('kycStatus').value = status;
    document.getElementById('kycUsername').textContent = username;
    document.getElementById('kycReviewTitle').textContent = (status === 'approved' ? 'Approve' : 'Reject') + ' KYC Document';
    document.getElementById('kycSubmitBtn').textContent = status === 'approved' ? 'Approve' : 'Reject';
    document.getElementById('kycSubmitBtn').className = 'btn btn-' + (status === 'approved' ? 'success' : 'danger');
    document.getElementById('kycLevelGroup').style.display = status === 'approved' ? '' : 'none';
    new bootstrap.Modal(document.getElementById('kycReviewModal')).show();
}
</script>
