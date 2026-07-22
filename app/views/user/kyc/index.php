<?php declare(strict_types=1); ?>
<?php
$kycStatus = is_array($kycStatus ?? null) ? $kycStatus : [];
$documents = is_array($documents ?? null) ? $documents : [];
$status    = (string)($kycStatus['kyc_status'] ?? 'unverified');
$kycLevel  = (int)($kycStatus['kyc_level']  ?? 0);
require app_path('app/views/user/_nav.php');
?>

<!-- KYC Status Banner -->
<?php
$bannerConfig = match($status) {
    'verified'   => ['success', 'fa-check-circle', 'KYC Verified', 'Your identity has been successfully verified.'],
    'pending'    => ['warning', 'fa-clock',        'KYC Pending',  'Your documents are under review. This typically takes 1-3 business days.'],
    'rejected'   => ['danger',  'fa-times-circle', 'KYC Rejected', 'Your KYC was rejected. Please resubmit with correct documents.'],
    default      => ['secondary','fa-info-circle', 'KYC Required', 'Complete identity verification to unlock all platform features.'],
};
?>
<div class="alert alert-<?= $bannerConfig[0] ?> d-flex align-items-center gap-3 mb-4">
    <i class="fas <?= $bannerConfig[1] ?> fs-3"></i>
    <div>
        <strong><?= e($bannerConfig[2]) ?></strong> — Level <?= $kycLevel ?>
        <div class="small"><?= e($bannerConfig[3]) ?></div>
    </div>
</div>

<!-- KYC Levels -->
<div class="row g-3 mb-4">
    <?php
    $levels = [
        ['Level 1', 'Email Verification', 'fa-envelope', $kycLevel >= 1 ? 'success' : 'secondary', 'Basic account access'],
        ['Level 2', 'Identity Document', 'fa-id-card',   $kycLevel >= 2 ? 'success' : 'secondary', 'Passport, National ID, Driver\'s License'],
        ['Level 3', 'Proof of Address',  'fa-home',      $kycLevel >= 3 ? 'success' : 'secondary', 'Utility bill, bank statement'],
    ];
    foreach ($levels as [$lvlTitle, $lvlName, $icon, $color, $desc]):
    ?>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3 text-center h-100">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2"></i>
            <div class="fw-semibold"><?= e($lvlTitle) ?>: <?= e($lvlName) ?></div>
            <div class="text-secondary small"><?= e($desc) ?></div>
            <div class="mt-2"><span class="badge bg-<?= $color ?>"><?= $color === 'success' ? 'Complete' : 'Incomplete' ?></span></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Upload Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-upload me-2 text-info"></i>Submit Document</h5>
            <form id="kycForm" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Document Type</label>
                    <select name="document_type" class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Type —</option>
                        <?php foreach ([
                            'passport'        => 'Passport',
                            'national_id'     => 'National ID',
                            'drivers_license' => "Driver's License",
                            'proof_of_address'=> 'Proof of Address',
                            'selfie'          => 'Selfie with ID',
                            'corporate_doc'   => 'Corporate Document',
                            'other'           => 'Other',
                        ] as $v => $l): ?>
                        <option value="<?= e($v) ?>"><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Document Number <span class="text-secondary">(optional)</span></label>
                    <input type="text" name="document_number" class="form-control bg-transparent text-light border-secondary"
                           placeholder="Passport or ID number">
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Document File</label>
                    <div class="border border-secondary border-dashed rounded-3 p-3 text-center" id="dropZone">
                        <i class="fas fa-cloud-upload-alt fa-2x text-secondary mb-2"></i>
                        <div class="text-secondary small">Drag & drop or click to upload</div>
                        <div class="text-secondary" style="font-size:.75rem">JPG, PNG, PDF — max 10MB</div>
                        <input type="file" name="document_file" id="docFile" class="d-none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
                        <label for="docFile" class="btn btn-outline-secondary btn-sm mt-2">Browse File</label>
                        <div id="fileNameDisplay" class="mt-2 text-info small"></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-info w-100"><i class="fas fa-paper-plane me-1"></i>Submit Document</button>
            </form>
        </div>
    </div>

    <!-- Documents List -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-folder-open me-2"></i>Submitted Documents</h5>
            <?php if ($documents === []): ?>
                <div class="text-center text-secondary py-4">
                    <i class="fas fa-folder-open fa-3x mb-3 d-block"></i>
                    No documents submitted yet.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-user table-sm">
                    <thead><tr><th>Type</th><th>Doc Number</th><th>Status</th><th>Submitted</th><th>Review Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td class="small"><?= e(ucwords(str_replace('_', ' ', (string)($doc['document_type'] ?? '-')))) ?></td>
                            <td class="font-monospace small"><?= e((string)($doc['document_number'] ?? '-')) ?></td>
                            <td>
                                <?php
                                $ds = (string)($doc['status'] ?? 'pending');
                                $dc = match($ds) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $dc ?>"><?= e($ds) ?></span>
                            </td>
                            <td class="small"><?= e(date('M d, Y', strtotime((string)($doc['created_at'] ?? 'now')))) ?></td>
                            <td class="small text-secondary"><?= e(substr((string)($doc['review_notes'] ?? ''), 0, 50)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('docFile').addEventListener('change', function () {
    document.getElementById('fileNameDisplay').textContent = this.files[0]?.name || '';
});

document.getElementById('kycForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';

    $.ajax({
        url: '/user/kyc/submit',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success(r) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Document';
            if (r.ok) {
                Swal.fire({ icon: 'success', title: 'Submitted!', text: r.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message });
            }
        },
        error(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Document';
            const p = xhr.responseJSON || {};
            Swal.fire({ icon: 'error', title: 'Error', text: p.message || 'Upload failed' });
        }
    });
});
</script>
