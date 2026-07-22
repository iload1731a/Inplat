<?php declare(strict_types=1);
$profile   = is_array($profile ?? null)   ? $profile   : null;
$documents = is_array($documents ?? null) ? $documents : [];
$auditLog  = is_array($auditLog ?? null)  ? $auditLog  : [];
$risk      = is_array($risk ?? null)      ? $risk      : [];
$csrf      = \App\Libraries\Csrf::token();

if ($profile !== null) {
    $ks = (string)($profile['kyc_status'] ?? 'unverified');
    $kc = match($ks) { 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary' };
    $rl = (string)($risk['risk_level'] ?? 'low');
    $rc = match($rl) { 'critical' => 'danger', 'high' => 'warning', 'medium' => 'info', default => 'success' };
}
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="/admin/kyc" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <h1 class="h4 mb-0">User KYC Profile</h1>
    <?php if ($profile): ?>
    <span class="badge bg-<?= $kc ?> ms-2"><?= e(ucfirst($ks)) ?></span>
    <span class="badge bg-<?= $rc ?> ms-1">Risk: <?= e(ucfirst($rl)) ?></span>
    <?php endif; ?>
</div>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e((string)$error) ?></div>
<?php elseif ($profile === null): ?>
<div class="alert alert-warning">User not found.</div>
<?php else: ?>

<div class="row g-4 mb-4">
    <!-- User Summary -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <div class="text-center mb-3">
                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-2"
                     style="width:64px;height:64px;font-size:1.6rem">
                    <?= strtoupper(substr((string)($profile['username'] ?? 'U'), 0, 1)) ?>
                </div>
                <div class="fw-bold"><?= e((string)($profile['username'] ?? '')) ?></div>
                <div class="text-secondary small"><?= e((string)($profile['email'] ?? '')) ?></div>
            </div>

            <dl class="row small mb-3">
                <dt class="col-5 text-secondary">KYC Status</dt>
                <dd class="col-7"><span class="badge bg-<?= $kc ?>"><?= e(ucfirst($ks)) ?></span></dd>

                <dt class="col-5 text-secondary">KYC Level</dt>
                <dd class="col-7"><?= (int)($profile['kyc_level'] ?? 0) ?></dd>

                <dt class="col-5 text-secondary">Documents</dt>
                <dd class="col-7"><?= count($documents) ?></dd>

                <dt class="col-5 text-secondary">Registered</dt>
                <dd class="col-7"><?= e(date('M d, Y', strtotime((string)($profile['registered_at'] ?? 'now')))) ?></dd>

                <dt class="col-5 text-secondary">Account</dt>
                <dd class="col-7"><span class="badge bg-<?= (bool)($profile['is_active'] ?? false) ? 'success' : 'danger' ?>"><?= (bool)($profile['is_active'] ?? false) ? 'Active' : 'Inactive' ?></span></dd>
            </dl>

            <a href="/admin/management/users?search=<?= urlencode((string)($profile['username'] ?? '')) ?>" class="btn btn-sm btn-outline-secondary w-100 mb-2">
                <i class="fas fa-user-cog me-1"></i>Manage User Account
            </a>
        </div>

        <!-- Risk Assessment -->
        <div class="glass rounded-4 p-4 mt-3">
            <div class="fw-semibold mb-3"><i class="fas fa-shield-virus me-2 text-<?= $rc ?>"></i>Risk Assessment</div>

            <div class="text-center mb-3">
                <div class="display-5 fw-bold text-<?= $rc ?>"><?= (int)($risk['risk_score'] ?? 0) ?></div>
                <div class="text-secondary small">Risk Score (0-100)</div>
                <span class="badge bg-<?= $rc ?> mt-1"><?= e(ucfirst($rl)) ?> Risk</span>
            </div>

            <?php if (!empty($risk['last_assessed_at'])): ?>
            <div class="text-secondary small text-center mb-2">Last assessed: <?= e(date('M d, Y', strtotime((string)$risk['last_assessed_at']))) ?></div>
            <?php endif; ?>

            <?php if (!empty($risk['notes'])): ?>
            <div class="alert alert-<?= $rc ?> small mb-3"><?= e((string)$risk['notes']) ?></div>
            <?php endif; ?>

            <form id="riskForm">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$profile['id'] ?>">
                <div class="mb-2">
                    <label class="form-label small">Score (0-100)</label>
                    <input type="number" name="risk_score" min="0" max="100" class="form-control form-control-sm"
                           value="<?= (int)($risk['risk_score'] ?? 0) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Notes</label>
                    <textarea class="form-control form-control-sm" name="notes" rows="2"
                              placeholder="Risk assessment notes…"><?= e((string)($risk['notes'] ?? '')) ?></textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-save me-1"></i>Update Assessment</button>
            </form>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="fw-semibold"><i class="fas fa-folder-open me-2"></i>KYC Documents</div>
                <span class="badge bg-secondary"><?= count($documents) ?> total</span>
            </div>

            <?php if (empty($documents)): ?>
            <div class="text-center text-secondary py-4"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>No documents submitted.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0 small">
                    <thead class="text-secondary">
                        <tr><th>Type</th><th>Doc #</th><th>Country</th><th>Expiry</th><th>Status</th><th>Submitted</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $doc):
                        $ds = (string)($doc['status'] ?? 'pending');
                        $dc = match($ds) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
                    ?>
                        <tr>
                            <td><?= e(ucwords(str_replace('_',' ',(string)($doc['document_type']??'-')))) ?></td>
                            <td class="font-monospace"><?= e((string)($doc['document_number']??'-')) ?></td>
                            <td><?= e((string)($doc['issue_country']??'-')) ?></td>
                            <td><?php
                                $exp = (string)($doc['expiry_date'] ?? '');
                                $expired = $exp && strtotime($exp) < time();
                                echo '<span class="' . ($expired ? 'text-danger' : '') . '">' . e($exp ?: '-') . '</span>';
                                if ($expired) echo ' <span class="badge bg-danger" style="font-size:.6rem">Exp</span>';
                            ?></td>
                            <td><span class="badge bg-<?= $dc ?>"><?= e(ucfirst($ds)) ?></span></td>
                            <td class="text-secondary"><?= e(date('M d, Y', strtotime((string)($doc['created_at']??'now')))) ?></td>
                            <td class="text-end">
                                <a href="/admin/kyc/detail?id=<?= (int)$doc['id'] ?>" class="btn btn-xs btn-outline-secondary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Re-request Section -->
        <div class="glass rounded-4 p-3 mt-3">
            <div class="fw-semibold small mb-2"><i class="fas fa-redo me-2 text-warning"></i>Re-Request Document</div>
            <form id="reRequestForm" class="row g-2">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$profile['id'] ?>">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="document_type">
                        <?php foreach (['passport'=>'Passport','national_id'=>'National ID','drivers_license'=>"Driver's License",'proof_of_address'=>'Proof of Address','selfie'=>'Selfie','corporate_doc'=>'Corporate Doc','other'=>'Other'] as $v => $l): ?>
                        <option value="<?= e($v) ?>"><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Reason for re-request…" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-warning w-100">Send</button>
                </div>
            </form>
        </div>

        <!-- Audit Timeline -->
        <?php if (!empty($auditLog)): ?>
        <div class="glass rounded-4 p-3 mt-3">
            <div class="fw-semibold small mb-2"><i class="fas fa-history me-2 text-secondary"></i>Audit Trail</div>
            <div class="table-responsive">
                <table class="table table-dark table-sm small mb-0">
                    <thead class="text-secondary"><tr><th>Date</th><th>Action</th><th>Document</th><th>Status Change</th><th>Actor</th></tr></thead>
                    <tbody>
                    <?php foreach ($auditLog as $entry): ?>
                    <tr>
                        <td class="text-secondary"><?= e(date('M d, H:i', strtotime((string)($entry['created_at']??'now')))) ?></td>
                        <td><?= e(ucwords(str_replace('_',' ',(string)($entry['action']??'')))) ?></td>
                        <td><?= e(ucwords(str_replace('_',' ',(string)($entry['document_type']??'-')))) ?></td>
                        <td>
                            <?php if (!empty($entry['old_status']) || !empty($entry['new_status'])): ?>
                            <span class="text-secondary"><?= e((string)($entry['old_status']??'')) ?></span>
                            <i class="fas fa-arrow-right mx-1 text-secondary" style="font-size:.65rem"></i>
                            <?php $ns2 = (string)($entry['new_status']??'');
                                  $nc2 = match($ns2) { 'approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning', default => 'secondary' };
                            ?>
                            <span class="text-<?= $nc2 ?>"><?= e($ns2) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-secondary"><?= e((string)($entry['actor_name'] ?? ucfirst((string)($entry['actor_type']??'')))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
document.getElementById('riskForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({ url: '/admin/kyc/risk', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) { Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false }).then(() => r.ok && location.reload()); },
        error(xhr)  { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
});

document.getElementById('reRequestForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({ url: '/admin/kyc/re-request', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) { Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false }); },
        error(xhr)  { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
});
</script>
