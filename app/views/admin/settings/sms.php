<?php declare(strict_types=1); ?>
<?php
$smsConfigs = is_array($smsConfigs ?? null) ? $smsConfigs : [];
$csrf       = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-sms me-2 text-success"></i>SMS Configuration</h1>
        <p class="text-secondary mb-0">Configure SMS gateway providers for OTP and notification delivery.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSmsModal">
        <i class="fas fa-plus me-1"></i>Add SMS Profile
    </button>
</div>

<?php if ($smsConfigs === []): ?>
    <div class="glass rounded-4 p-5 text-center">
        <i class="fas fa-sms fa-3x text-secondary mb-3"></i>
        <h5>No SMS gateways configured</h5>
        <p class="text-secondary">Add a provider to enable SMS OTP verification and notifications.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSmsModal">
            <i class="fas fa-plus me-1"></i>Add SMS Gateway
        </button>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($smsConfigs as $sms): ?>
        <?php $isDefault = (int)($sms['is_default'] ?? 0) === 1; ?>
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4 h-100 <?= $isDefault ? 'border border-success' : '' ?>">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="fw-semibold fs-5"><?= e((string)($sms['name'] ?? '-')) ?></div>
                        <div class="text-secondary small">
                            <span class="badge bg-secondary me-1"><?= e(ucfirst((string)($sms['provider'] ?? ''))) ?></span>
                            <?php if (($sms['from_number'] ?? '') !== ''): ?>
                                From: <code><?= e((string)$sms['from_number']) ?></code>
                            <?php elseif (($sms['sender_id'] ?? '') !== ''): ?>
                                Sender ID: <code><?= e((string)$sms['sender_id']) ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <?php if ($isDefault): ?><span class="badge bg-success">Default</span><?php endif; ?>
                        <span class="badge <?= (int)($sms['is_active'] ?? 0) ? 'bg-success' : 'bg-secondary' ?>">
                            <?= (int)($sms['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>

                <div class="small text-secondary mb-3">
                    Max: <?= (int)($sms['max_per_minute'] ?? 30) ?>/min
                </div>

                <div class="d-flex gap-2">
                    <?php if (!$isDefault): ?>
                        <form method="post" action="/admin/settings/sms/default" data-ajax="true" class="d-inline">
                            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="sms_id" value="<?= (int)$sms['id'] ?>">
                            <button class="btn btn-xs btn-outline-success">Set Default</button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-xs btn-outline-light" onclick='openEditSms(<?= e(json_encode($sms)) ?>)'>Edit</button>
                    <?php if (!$isDefault): ?>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteSms(<?= (int)$sms['id'] ?>, '<?= e((string)$sms['name']) ?>')">Delete</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create SMS Modal -->
<div class="modal fade" id="createSmsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add SMS Gateway</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/sms/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="modal-body">
                    <?php include __DIR__ . '/_sms_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Gateway</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit SMS Modal -->
<div class="modal fade" id="editSmsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit SMS Gateway</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/sms/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" id="editSmsId">
                <div class="modal-body">
                    <?php include __DIR__ . '/_sms_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditSms(sms) {
    const m = document.getElementById('editSmsModal');
    document.getElementById('editSmsId').value = sms.id;
    ['name','account_sid','from_number','sender_id','api_endpoint','max_per_minute'].forEach(f => {
        const el = m.querySelector('[name="' + f + '"]');
        if (el && sms[f] != null) el.value = sms[f];
    });
    const provEl = m.querySelector('[name="provider"]');
    if (provEl && sms.provider) provEl.value = sms.provider;
    const activeEl = m.querySelector('[name="is_active"]');
    if (activeEl) activeEl.checked = parseInt(sms.is_active) === 1;
    new bootstrap.Modal(m).show();
}
function deleteSms(id, name) {
    if (!confirm('Delete SMS gateway "' + name + '"?')) return;
    $.ajax({
        url: '/admin/settings/sms/delete',
        method: 'POST',
        data: { _token: '<?= e($csrf) ?>', sms_id: id },
        success: r => { alert(r.message); if (r.redirect) location.href = r.redirect; },
        error: x => alert((x.responseJSON || {}).message || 'Error'),
    });
}
</script>
