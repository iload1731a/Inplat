<?php declare(strict_types=1);
$doc      = is_array($doc ?? null)      ? $doc      : null;
$auditLog = is_array($auditLog ?? null) ? $auditLog : [];
$csrf     = \App\Libraries\Csrf::token();

if ($doc !== null) {
    $ds  = (string)($doc['status'] ?? 'pending');
    $dc  = match($ds) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
    $typeLabel = ucwords(str_replace('_', ' ', (string)($doc['document_type'] ?? '-')));
    $fileUrl   = (string)($doc['file_url'] ?? '');
    $isPdf     = str_ends_with(strtolower($fileUrl), '.pdf');
}
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="/admin/kyc" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Queue</a>
    <h1 class="h4 mb-0">Document Detail</h1>
    <?php if ($doc): ?><span class="badge bg-<?= $dc ?> ms-auto"><?= e(ucfirst($ds)) ?></span><?php endif; ?>
</div>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e((string)$error) ?></div>
<?php elseif ($doc === null): ?>
<div class="alert alert-warning">Document not found.</div>
<?php else: ?>

<div class="row g-4">
    <!-- File Preview -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-3">
            <div class="fw-semibold mb-3"><i class="fas fa-file-image me-2 text-info"></i>Document File</div>
            <?php if ($fileUrl !== ''): ?>
            <?php if ($isPdf): ?>
            <div class="ratio" style="--bs-aspect-ratio:130%">
                <iframe src="<?= e($fileUrl) ?>" style="border:0;border-radius:.5rem"></iframe>
            </div>
            <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2 w-100"><i class="fas fa-external-link-alt me-1"></i>Open PDF</a>
            <?php else: ?>
            <img src="<?= e($fileUrl) ?>" alt="Document" class="img-fluid rounded-3 w-100" style="max-height:500px;object-fit:contain;background:#111">
            <a href="<?= e($fileUrl) ?>" target="_blank" download class="btn btn-sm btn-outline-secondary mt-2 w-100"><i class="fas fa-download me-1"></i>Download Image</a>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center text-secondary py-5"><i class="fas fa-ban fa-3x mb-2 d-block"></i>No file on record.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info + Actions -->
    <div class="col-lg-5">
        <!-- Document Details -->
        <div class="glass rounded-4 p-4 mb-3">
            <div class="fw-semibold mb-3"><i class="fas fa-info-circle me-2"></i>Document Information</div>
            <dl class="row small mb-0">
                <dt class="col-5 text-secondary">User</dt>
                <dd class="col-7">
                    <a href="/admin/kyc/user?user_id=<?= (int)$doc['user_id'] ?>" class="text-light">
                        <?= e((string)($doc['username'] ?? '')) ?>
                    </a>
                </dd>

                <dt class="col-5 text-secondary">Email</dt>
                <dd class="col-7 text-secondary"><?= e((string)($doc['email'] ?? '')) ?></dd>

                <dt class="col-5 text-secondary">Current KYC</dt>
                <dd class="col-7">
                    <?php $ks = (string)($doc['kyc_status'] ?? 'unverified');
                          $kc = match($ks) { 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary' };
                    ?>
                    <span class="badge bg-<?= $kc ?>"><?= e(ucfirst($ks)) ?></span>
                    <span class="text-secondary ms-1">Level <?= (int)($doc['kyc_level'] ?? 0) ?></span>
                </dd>

                <dt class="col-5 text-secondary">Doc Type</dt>
                <dd class="col-7"><?= e($typeLabel) ?></dd>

                <dt class="col-5 text-secondary">Doc Number</dt>
                <dd class="col-7 font-monospace"><?= e((string)($doc['document_number'] ?? '—')) ?></dd>

                <dt class="col-5 text-secondary">Country</dt>
                <dd class="col-7"><?= e((string)($doc['issue_country'] ?? '—')) ?></dd>

                <dt class="col-5 text-secondary">Issue Date</dt>
                <dd class="col-7"><?= e((string)($doc['issue_date'] ?? '—')) ?></dd>

                <dt class="col-5 text-secondary">Expiry Date</dt>
                <dd class="col-7">
                    <?php $exp = (string)($doc['expiry_date'] ?? '');
                          $expired = $exp && strtotime($exp) < time();
                    ?>
                    <span class="<?= $expired ? 'text-danger' : '' ?>"><?= e($exp ?: '—') ?></span>
                    <?= $expired ? '<span class="badge bg-danger ms-1">Expired</span>' : '' ?>
                </dd>

                <dt class="col-5 text-secondary">Submitted</dt>
                <dd class="col-7"><?= e(date('M d, Y H:i', strtotime((string)($doc['created_at'] ?? 'now')))) ?></dd>

                <dt class="col-5 text-secondary">Status</dt>
                <dd class="col-7"><span class="badge bg-<?= $dc ?>"><?= e(ucfirst($ds)) ?></span></dd>

                <?php if (!empty($doc['reviewer_name'])): ?>
                <dt class="col-5 text-secondary">Reviewed By</dt>
                <dd class="col-7"><?= e((string)$doc['reviewer_name']) ?></dd>
                <?php endif; ?>

                <?php if (!empty($doc['reviewed_at'])): ?>
                <dt class="col-5 text-secondary">Reviewed At</dt>
                <dd class="col-7"><?= e(date('M d, Y H:i', strtotime((string)$doc['reviewed_at']))) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (!empty($doc['review_notes'])): ?>
            <div class="alert alert-<?= $dc ?> mt-3 small mb-0">
                <strong>Notes:</strong> <?= e((string)$doc['review_notes']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Review Actions -->
        <?php if ($ds === 'pending'): ?>
        <div class="glass rounded-4 p-3 mb-3">
            <div class="fw-semibold mb-2"><i class="fas fa-gavel me-2 text-warning"></i>Review Decision</div>
            <form id="reviewForm">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                <input type="hidden" name="status" id="reviewStatus">
                <div class="mb-2">
                    <textarea class="form-control form-control-sm" name="notes" rows="2" id="reviewNotes" placeholder="Reviewer notes…"></textarea>
                </div>
                <div class="mb-3" id="levelGroup">
                    <label class="form-label small mb-1">Grant KYC Level</label>
                    <select class="form-select form-select-sm" name="kyc_level">
                        <option value="1">Level 1 – Basic</option>
                        <option value="2">Level 2 – Intermediate</option>
                        <option value="3">Level 3 – Full</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success flex-grow-1" onclick="submitReview('approved')"><i class="fas fa-check me-1"></i>Approve</button>
                    <button type="button" class="btn btn-danger flex-grow-1" onclick="submitReview('rejected')"><i class="fas fa-times me-1"></i>Reject</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Re-request -->
        <div class="glass rounded-4 p-3">
            <div class="fw-semibold mb-2 small"><i class="fas fa-redo me-2 text-info"></i>Request Re-Submission</div>
            <form id="reRequestForm">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$doc['user_id'] ?>">
                <select class="form-select form-select-sm mb-2" name="document_type">
                    <?php foreach (['passport'=>'Passport','national_id'=>'National ID','drivers_license'=>"Driver's License",'proof_of_address'=>'Proof of Address','selfie'=>'Selfie','corporate_doc'=>'Corporate Doc','other'=>'Other'] as $v => $l): ?>
                    <option value="<?= e($v) ?>" <?= $v === ($doc['document_type'] ?? '') ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea class="form-control form-control-sm mb-2" name="notes" rows="2" placeholder="Reason for re-request…"></textarea>
                <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-paper-plane me-1"></i>Send Re-Request</button>
            </form>
        </div>
    </div>
</div>

<!-- Audit Timeline -->
<?php if (!empty($auditLog)): ?>
<div class="glass rounded-4 p-4 mt-4">
    <div class="fw-semibold mb-3"><i class="fas fa-history me-2 text-secondary"></i>KYC Audit History for <?= e((string)($doc['username'] ?? '')) ?></div>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0 small">
            <thead class="text-secondary"><tr><th>Date</th><th>Action</th><th>Document</th><th>Old</th><th>New</th><th>Actor</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($auditLog as $entry): ?>
            <tr>
                <td class="text-secondary"><?= e((string)($entry['created_at'] ?? '')) ?></td>
                <td><strong><?= e(ucwords(str_replace('_',' ',(string)($entry['action']??'')))) ?></strong></td>
                <td><?= e(ucwords(str_replace('_',' ',(string)($entry['document_type']??'-')))) ?></td>
                <td><span class="badge bg-secondary"><?= e((string)($entry['old_status']??'-')) ?></span></td>
                <td>
                    <?php $ns = (string)($entry['new_status'] ?? '');
                          $nc = match($ns) { 'approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning', default => 'secondary' };
                    ?>
                    <span class="badge bg-<?= $nc ?>"><?= e($ns ?: '-') ?></span>
                </td>
                <td><?= e((string)($entry['actor_name'] ?? ucfirst((string)($entry['actor_type']??'')))) ?></td>
                <td class="text-secondary"><?= e(mb_strimwidth((string)($entry['notes']??''), 0, 60, '…')) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function submitReview(status) {
    document.getElementById('reviewStatus').value = status;
    const fd = new FormData(document.getElementById('reviewForm'));
    if (status === 'rejected') document.getElementById('levelGroup').style.display = 'none';
    $.ajax({ url: '/admin/kyc/review', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) {
            Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false })
                .then(() => r.ok && (location.href = r.redirect || '/admin/kyc'));
        },
        error(xhr) { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
}

document.getElementById('reRequestForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({ url: '/admin/kyc/re-request', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) {
            Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false });
        },
        error(xhr) { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
});
</script>
