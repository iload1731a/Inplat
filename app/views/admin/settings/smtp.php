<?php declare(strict_types=1); ?>
<?php
$smtpConfigs = is_array($smtpConfigs ?? null) ? $smtpConfigs : [];
$csrf        = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-envelope me-2 text-warning"></i>SMTP Configuration</h1>
        <p class="text-secondary mb-0">Configure outgoing email server profiles and test delivery.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSmtpModal">
        <i class="fas fa-plus me-1"></i>Add SMTP Profile
    </button>
</div>

<?php if ($smtpConfigs === []): ?>
    <div class="glass rounded-4 p-5 text-center">
        <i class="fas fa-envelope fa-3x text-secondary mb-3"></i>
        <h5>No SMTP profiles configured</h5>
        <p class="text-secondary">Add an SMTP profile to enable email sending for notifications, KYC, and user communications.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSmtpModal">
            <i class="fas fa-plus me-1"></i>Add First SMTP Profile
        </button>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($smtpConfigs as $smtp): ?>
        <?php $isDefault = (int)($smtp['is_default'] ?? 0) === 1; ?>
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4 h-100 <?= $isDefault ? 'border border-warning' : '' ?>">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="fw-semibold fs-5"><?= e((string)($smtp['name'] ?? '-')) ?></div>
                        <div class="text-secondary small"><?= e((string)($smtp['host'] ?? '')) ?>:<?= (int)($smtp['port'] ?? 587) ?> · <?= e(strtoupper((string)($smtp['encryption'] ?? ''))) ?></div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <?php if ($isDefault): ?>
                            <span class="badge bg-warning text-dark">Default</span>
                        <?php endif; ?>
                        <span class="badge <?= (int)($smtp['is_active'] ?? 0) ? 'bg-success' : 'bg-secondary' ?>">
                            <?= (int)($smtp['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>

                <div class="row g-2 small text-secondary mb-3">
                    <div class="col-6"><i class="fas fa-user me-1"></i>User: <code><?= e((string)($smtp['username'] ?? '')) ?></code></div>
                    <div class="col-6"><i class="fas fa-paper-plane me-1"></i>From: <code><?= e((string)($smtp['from_email'] ?? '')) ?></code></div>
                    <div class="col-6"><i class="fas fa-user-tag me-1"></i>Name: <?= e((string)($smtp['from_name'] ?? '')) ?></div>
                    <div class="col-6"><i class="fas fa-tachometer-alt me-1"></i><?= (int)($smtp['max_per_minute'] ?? 60) ?>/min</div>
                </div>

                <!-- Test Result -->
                <?php if ($smtp['last_tested_at'] ?? null): ?>
                    <div class="alert py-2 mb-3 <?= (int)($smtp['last_test_ok'] ?? 0) ? 'alert-success' : 'alert-danger' ?> small">
                        <i class="fas <?= (int)($smtp['last_test_ok'] ?? 0) ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                        Last test: <?= e((string)($smtp['last_tested_at'] ?? '')) ?>
                        <?php if ((int)($smtp['last_test_ok'] ?? 0) === 0 && ($smtp['last_test_error'] ?? '') !== ''): ?>
                            <br><small><?= e((string)$smtp['last_test_error']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Test Email Form -->
                <form method="post" action="/admin/settings/smtp/test" id="smtpTest<?= (int)$smtp['id'] ?>"
                      class="d-flex gap-2 mb-3" onsubmit="return testSmtp(event, <?= (int)$smtp['id'] ?>)">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="smtp_id" value="<?= (int)$smtp['id'] ?>">
                    <input type="email" name="test_email" class="form-control form-control-sm bg-transparent text-light border-secondary"
                           placeholder="Test recipient email" required>
                    <button class="btn btn-sm btn-outline-info flex-shrink-0">Send Test</button>
                </form>

                <div class="d-flex gap-2">
                    <?php if (!$isDefault): ?>
                        <form method="post" action="/admin/settings/smtp/default" data-ajax="true" class="d-inline">
                            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="smtp_id" value="<?= (int)$smtp['id'] ?>">
                            <button class="btn btn-xs btn-outline-warning">Set Default</button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-xs btn-outline-light" onclick='openEditSmtp(<?= e(json_encode($smtp)) ?>)'>Edit</button>
                    <?php if (!$isDefault): ?>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteSmtp(<?= (int)$smtp['id'] ?>, '<?= e((string)$smtp['name']) ?>')">Delete</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create SMTP Modal -->
<div class="modal fade" id="createSmtpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add SMTP Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/smtp/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="modal-body">
                    <?php include __DIR__ . '/_smtp_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit SMTP Modal -->
<div class="modal fade" id="editSmtpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit SMTP Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/smtp/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" id="editSmtpId">
                <div class="modal-body">
                    <?php include __DIR__ . '/_smtp_form.php'; ?>
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
function openEditSmtp(smtp) {
    const m = document.getElementById('editSmtpModal');
    document.getElementById('editSmtpId').value = smtp.id;
    ['name','host','port','username','from_email','from_name','reply_to','max_per_minute'].forEach(f => {
        const el = m.querySelector('[name="' + f + '"]');
        if (el && smtp[f] != null) el.value = smtp[f];
    });
    const encEl = m.querySelector('[name="encryption"]');
    if (encEl && smtp.encryption) encEl.value = smtp.encryption;
    const activeEl = m.querySelector('[name="is_active"]');
    if (activeEl) activeEl.checked = parseInt(smtp.is_active) === 1;
    new bootstrap.Modal(m).show();
}

function deleteSmtp(id, name) {
    if (!confirm('Delete SMTP profile "' + name + '"?')) return;
    $.ajax({
        url: '/admin/settings/smtp/delete',
        method: 'POST',
        data: { _token: '<?= e($csrf) ?>', smtp_id: id },
        success: r => { alert(r.message); if (r.redirect) location.href = r.redirect; },
        error: x => alert((x.responseJSON || {}).message || 'Error'),
    });
}

function testSmtp(event, id) {
    event.preventDefault();
    const form = event.target;
    const email = form.querySelector('[name="test_email"]').value;
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    $.ajax({
        url: '/admin/settings/smtp/test',
        method: 'POST',
        data: { _token: '<?= e($csrf) ?>', smtp_id: id, test_email: email },
        success: r => { alert((r.ok ? '✓ ' : '✗ ') + r.message); location.reload(); },
        error: x => alert((x.responseJSON || {}).message || 'Test failed'),
        complete: () => { btn.disabled = false; btn.textContent = 'Send Test'; },
    });
    return false;
}
</script>
