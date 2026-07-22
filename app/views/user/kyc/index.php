<?php declare(strict_types=1); ?>
<?php
$kycStatus    = is_array($kycStatus ?? null)    ? $kycStatus    : [];
$documents    = is_array($documents ?? null)    ? $documents    : [];
$requirements = is_array($requirements ?? null) ? $requirements : [];
$approvedTypes= is_array($approvedTypes ?? null) ? $approvedTypes : [];
$auditLog     = is_array($auditLog ?? null)     ? $auditLog     : [];
$status       = (string)($kycStatus['kyc_status'] ?? 'unverified');
$kycLevel     = (int)($kycStatus['kyc_level']    ?? 0);
$csrf         = \App\Libraries\Csrf::token();

$bannerConfig = match($status) {
    'approved'   => ['success', 'fa-check-circle', 'KYC Verified',   'Your identity is fully verified. All platform features are unlocked.'],
    'pending'    => ['warning', 'fa-clock',         'Under Review',   'Your documents are under review. This typically takes 1-3 business days.'],
    'rejected'   => ['danger',  'fa-times-circle',  'KYC Rejected',   'One or more documents were rejected. Please re-upload with correct documents.'],
    default      => ['secondary','fa-info-circle',  'KYC Required',   'Complete identity verification to unlock full platform features and higher withdrawal limits.'],
};
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<!-- Status Banner -->
<div class="alert alert-<?= $bannerConfig[0] ?> d-flex align-items-center gap-3 mb-4">
    <i class="fas <?= $bannerConfig[1] ?> fs-3 flex-shrink-0"></i>
    <div class="flex-grow-1">
        <div class="fw-semibold"><?= e($bannerConfig[2]) ?> — Level <?= $kycLevel ?></div>
        <div class="small"><?= e($bannerConfig[3]) ?></div>
    </div>
    <?php if ($status === 'approved'): ?>
    <span class="badge bg-success fs-6 flex-shrink-0"><i class="fas fa-shield-alt me-1"></i>Verified</span>
    <?php endif; ?>
</div>

<!-- KYC Level Progress -->
<div class="row g-3 mb-4">
    <?php
    $levels = [
        [1, 'Level 1 – Email Verified', 'fa-envelope', 'Email & account creation', $kycLevel >= 1],
        [2, 'Level 2 – Identity',       'fa-id-card',  'Passport / National ID / Driver\'s License', $kycLevel >= 2],
        [3, 'Level 3 – Address',        'fa-home',     'Utility bill / Bank statement / Corporate docs', $kycLevel >= 3],
    ];
    foreach ($levels as [$lvl, $lvlName, $icon, $desc, $done]):
        $color = $done ? 'success' : ($kycLevel === $lvl - 1 ? 'warning' : 'secondary');
    ?>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3 text-center h-100 border border-<?= $color ?> border-opacity-25">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2 d-block"></i>
            <div class="fw-semibold small"><?= e($lvlName) ?></div>
            <div class="text-secondary" style="font-size:.75rem"><?= e($desc) ?></div>
            <div class="mt-2">
                <span class="badge bg-<?= $color ?>">
                    <?php if ($done): ?><i class="fas fa-check me-1"></i>Complete
                    <?php elseif ($kycLevel === $lvl - 1): ?><i class="fas fa-arrow-up me-1"></i>Next Step
                    <?php else: ?><i class="fas fa-lock me-1"></i>Locked
                    <?php endif; ?>
                </span>
            </div>
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
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Document Type <span class="text-danger">*</span></label>
                    <select name="document_type" class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Type —</option>
                        <?php foreach ([
                            'passport'         => 'Passport',
                            'national_id'      => 'National ID',
                            'drivers_license'  => "Driver's License",
                            'proof_of_address' => 'Proof of Address',
                            'selfie'           => 'Selfie with ID',
                            'corporate_doc'    => 'Corporate Document',
                            'other'            => 'Other',
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
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label text-secondary small">Issue Country</label>
                        <input type="text" name="issue_country" maxlength="2"
                               class="form-control bg-transparent text-light border-secondary text-uppercase"
                               placeholder="US">
                    </div>
                    <div class="col-6">
                        <label class="form-label text-secondary small">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control bg-transparent text-light border-secondary">
                    </div>
                </div>
                <!-- File Drop Zone -->
                <div class="mb-3">
                    <label class="form-label text-secondary small">Document File <span class="text-danger">*</span></label>
                    <div class="border border-secondary border-2 rounded-3 p-3 text-center position-relative"
                         id="dropZone" style="border-style:dashed!important;cursor:pointer">
                        <i class="fas fa-cloud-upload-alt fa-2x text-secondary mb-2 d-block"></i>
                        <div class="text-secondary small">Drag & drop or click to upload</div>
                        <div class="text-muted" style="font-size:.7rem">JPG, PNG, PDF — max 10 MB</div>
                        <input type="file" name="document_file" id="docFile" class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required style="cursor:pointer">
                        <div id="fileNameDisplay" class="mt-2 text-info small fw-semibold"></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-info w-100 fw-semibold">
                    <i class="fas fa-paper-plane me-2"></i>Submit Document
                </button>
            </form>
        </div>

        <?php if (!empty($requirements)): ?>
        <!-- Requirements Checklist -->
        <div class="glass rounded-4 p-3 mt-3">
            <div class="fw-semibold small mb-2"><i class="fas fa-list-check me-2 text-warning"></i>Required Documents by Level</div>
            <?php
            $byLevel = [];
            foreach ($requirements as $r) {
                $byLevel[(int)$r['kyc_level']][] = $r;
            }
            foreach ($byLevel as $lvl => $reqs):
            ?>
            <div class="mb-2">
                <div class="text-secondary small fw-semibold mb-1">Level <?= $lvl ?></div>
                <?php foreach ($reqs as $req):
                    $isDone = in_array($req['document_type'], $approvedTypes, true);
                ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas fa-<?= $isDone ? 'check-circle text-success' : 'circle text-secondary' ?> small"></i>
                    <span class="small <?= $isDone ? '' : 'text-secondary' ?>"><?= e($req['display_name']) ?></span>
                    <?php if (!(bool)$req['is_required']): ?>
                    <span class="badge bg-secondary" style="font-size:.6rem">Optional</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Documents List -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Submitted Documents</h5>
                <span class="badge bg-secondary"><?= count($documents) ?> documents</span>
            </div>

            <?php if ($documents === []): ?>
                <div class="text-center text-secondary py-5">
                    <i class="fas fa-folder-open fa-3x mb-3 d-block opacity-40"></i>
                    <p class="mb-1">No documents submitted yet.</p>
                    <small>Use the form on the left to submit your first document.</small>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="color:inherit">
                    <thead class="text-secondary small">
                        <tr>
                            <th>Type</th>
                            <th>Doc #</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $doc):
                        $ds = (string)($doc['status'] ?? 'pending');
                        $dc = match($ds) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
                        $typeLabel = ucwords(str_replace('_', ' ', (string)($doc['document_type'] ?? '-')));
                    ?>
                        <tr>
                            <td>
                                <span class="small fw-semibold"><?= e($typeLabel) ?></span>
                                <?php if (!empty($doc['issue_country'])): ?>
                                <div class="text-secondary" style="font-size:.7rem"><?= e((string)$doc['issue_country']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace small text-secondary"><?= e((string)($doc['document_number'] ?? '-')) ?></td>
                            <td>
                                <span class="badge bg-<?= $dc ?>"><?= e(ucfirst($ds)) ?></span>
                                <?php if ($ds === 'rejected' && !empty($doc['review_notes'])): ?>
                                <div class="text-danger" style="font-size:.7rem" title="<?= e((string)$doc['review_notes']) ?>">
                                    <?= e(mb_strimwidth((string)$doc['review_notes'], 0, 40, '…')) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?= e(date('M d, Y', strtotime((string)($doc['created_at'] ?? 'now')))) ?></td>
                            <td class="text-end">
                                <a href="/user/kyc/document?id=<?= (int)$doc['id'] ?>" class="btn btn-xs btn-outline-secondary me-1">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($ds === 'pending'): ?>
                                <button class="btn btn-xs btn-outline-danger btn-delete-doc"
                                        data-id="<?= (int)$doc['id'] ?>"
                                        data-type="<?= e($typeLabel) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Audit Timeline -->
        <?php if (!empty($auditLog)): ?>
        <div class="glass rounded-4 p-3 mt-3">
            <div class="fw-semibold small mb-2"><i class="fas fa-history me-2 text-info"></i>Activity History</div>
            <?php foreach (array_slice($auditLog, 0, 8) as $entry): ?>
            <div class="d-flex align-items-start gap-2 mb-2">
                <i class="fas fa-circle-dot text-secondary mt-1" style="font-size:.55rem"></i>
                <div>
                    <div class="small">
                        <strong><?= e(ucwords(str_replace('_', ' ', (string)($entry['action'] ?? '')))) ?></strong>
                        <?php if (!empty($entry['document_type'])): ?>
                        — <?= e(ucwords(str_replace('_', ' ', (string)$entry['document_type']))) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($entry['notes'])): ?>
                    <div class="text-secondary" style="font-size:.72rem"><?= e((string)$entry['notes']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted" style="font-size:.68rem"><?= e((string)($entry['created_at'] ?? '')) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// File name display
document.getElementById('docFile').addEventListener('change', function () {
    const fn = this.files[0]?.name || '';
    document.getElementById('fileNameDisplay').textContent = fn;
});

// Submit form
document.getElementById('kycForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd  = new FormData(this);
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading…';

    $.ajax({
        url: '/user/kyc/submit', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Document';
            if (r.ok) {
                Swal.fire({ icon: 'success', title: 'Submitted!', text: r.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message });
            }
        },
        error(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Document';
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Upload failed' });
        }
    });
});

// Delete pending document
document.querySelectorAll('.btn-delete-doc').forEach(btn => {
    btn.addEventListener('click', function () {
        const docId = this.dataset.id;
        const type  = this.dataset.type;
        Swal.fire({
            icon: 'warning', title: 'Remove Document?',
            text: `Remove your pending ${type} document?`,
            showCancelButton: true, confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, remove it'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post('/user/kyc/delete', { _token: '<?= e(\App\Libraries\Csrf::token()) ?>', document_id: docId }, r => {
                if (r.ok) {
                    Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', text: r.message });
                }
            }, 'json').fail(xhr => Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }));
        });
    });
});
</script>
