<?php declare(strict_types=1);
$doc = is_array($doc ?? null) ? $doc : [];
$ds  = (string)($doc['status'] ?? 'pending');
$dc  = match($ds) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
$typeLabel = ucwords(str_replace('_', ' ', (string)($doc['document_type'] ?? '-')));
$fileUrl   = (string)($doc['file_url'] ?? '');
$isPdf     = str_ends_with(strtolower($fileUrl), '.pdf');
?>
<?php require app_path('app/views/user/_nav.php'); ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="/user/kyc" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <h2 class="h5 mb-0">Document Detail</h2>
    <span class="badge bg-<?= $dc ?> ms-auto"><?= e(ucfirst($ds)) ?></span>
</div>

<?php if (empty($doc)): ?>
<div class="alert alert-danger">Document not found.</div>
<?php else: ?>
<div class="row g-4">
    <!-- Document Preview -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-3">
            <div class="fw-semibold mb-3"><i class="fas fa-file-image me-2 text-info"></i>Document Preview</div>
            <?php if ($fileUrl !== ''): ?>
            <?php if ($isPdf): ?>
            <div class="ratio" style="--bs-aspect-ratio: 130%">
                <iframe src="<?= e($fileUrl) ?>" class="rounded-3" style="border:0"></iframe>
            </div>
            <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2 w-100">
                <i class="fas fa-external-link-alt me-1"></i>Open PDF
            </a>
            <?php else: ?>
            <img src="<?= e($fileUrl) ?>" alt="Document" class="img-fluid rounded-3 w-100" style="max-height:480px;object-fit:contain">
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center text-secondary py-4"><i class="fas fa-file fa-3x mb-2 d-block"></i>No file available.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Info -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <div class="fw-semibold mb-3"><i class="fas fa-info-circle me-2"></i>Document Information</div>

            <dl class="row small mb-0">
                <dt class="col-5 text-secondary">Type</dt>
                <dd class="col-7"><?= e($typeLabel) ?></dd>

                <dt class="col-5 text-secondary">Document #</dt>
                <dd class="col-7 font-monospace"><?= e((string)($doc['document_number'] ?? '—')) ?></dd>

                <dt class="col-5 text-secondary">Issued Country</dt>
                <dd class="col-7"><?= e((string)($doc['issue_country'] ?? '—')) ?></dd>

                <dt class="col-5 text-secondary">Issue Date</dt>
                <dd class="col-7"><?= e((string)($doc['issue_date'] ?? '—')) ?></dd>

                <dt class="col-5 text-secondary">Expiry Date</dt>
                <dd class="col-7">
                    <?php
                    $exp = (string)($doc['expiry_date'] ?? '');
                    if ($exp && $exp !== '—') {
                        $isExpired = strtotime($exp) < time();
                        echo '<span class="' . ($isExpired ? 'text-danger' : '') . '">' . e($exp) . '</span>';
                        if ($isExpired) echo ' <span class="badge bg-danger">Expired</span>';
                    } else {
                        echo '—';
                    }
                    ?>
                </dd>

                <dt class="col-5 text-secondary">Status</dt>
                <dd class="col-7"><span class="badge bg-<?= $dc ?>"><?= e(ucfirst($ds)) ?></span></dd>

                <dt class="col-5 text-secondary">Submitted</dt>
                <dd class="col-7"><?= e(date('M d, Y H:i', strtotime((string)($doc['created_at'] ?? 'now')))) ?></dd>

                <?php if (!empty($doc['reviewed_at'])): ?>
                <dt class="col-5 text-secondary">Reviewed</dt>
                <dd class="col-7"><?= e(date('M d, Y H:i', strtotime((string)$doc['reviewed_at']))) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (!empty($doc['review_notes'])): ?>
            <div class="alert alert-<?= $dc ?> mt-3 small mb-0">
                <strong><i class="fas fa-comment-alt me-1"></i>Reviewer Notes:</strong><br>
                <?= e((string)$doc['review_notes']) ?>
            </div>
            <?php endif; ?>

            <?php if ($ds === 'rejected'): ?>
            <a href="/user/kyc" class="btn btn-warning w-100 mt-3">
                <i class="fas fa-redo me-2"></i>Resubmit New Document
            </a>
            <?php endif; ?>

            <?php if ($ds === 'pending'): ?>
            <button class="btn btn-outline-danger w-100 mt-3 btn-delete-this"
                    data-id="<?= (int)$doc['id'] ?>">
                <i class="fas fa-trash me-2"></i>Remove This Document
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelector('.btn-delete-this')?.addEventListener('click', function () {
    const docId = this.dataset.id;
    Swal.fire({
        icon: 'warning', title: 'Remove Document?',
        text: 'This will permanently delete the uploaded document.',
        showCancelButton: true, confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, remove it'
    }).then(res => {
        if (!res.isConfirmed) return;
        $.post('/user/kyc/delete', { _token: '<?= e(\App\Libraries\Csrf::token()) ?>', document_id: docId }, r => {
            if (r.ok) {
                Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false })
                    .then(() => location.href = '/user/kyc');
            } else {
                Swal.fire({ icon: 'error', text: r.message });
            }
        }, 'json').fail(xhr => Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }));
    });
});
</script>
