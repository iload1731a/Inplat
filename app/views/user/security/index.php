<?php declare(strict_types=1); ?>
<?php
$loginHistory     = is_array($loginHistory     ?? null) ? $loginHistory     : [];
$activeSessions   = is_array($activeSessions   ?? null) ? $activeSessions   : [];
$twoFactorData    = is_array($twoFactorData    ?? null) ? $twoFactorData    : [];
$securitySettings = is_array($securitySettings ?? null) ? $securitySettings : [];
$is2faEnabled     = (bool)($twoFactorData['two_factor_enabled'] ?? false);
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Change Password -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 h-100">
            <h5 class="mb-3"><i class="fas fa-lock me-2 text-warning"></i>Change Password</h5>
            <form id="passwordForm" data-ajax="true" action="/user/security/password" method="POST">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Current Password</label>
                    <input type="password" name="current_password" class="form-control bg-transparent text-light border-secondary" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">New Password</label>
                    <input type="password" name="new_password" class="form-control bg-transparent text-light border-secondary" minlength="8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control bg-transparent text-light border-secondary" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i>Update Password</button>
            </form>
        </div>
    </div>

    <!-- 2FA -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 h-100">
            <h5 class="mb-3"><i class="fas fa-mobile-alt me-2 text-info"></i>Two-Factor Authentication</h5>
            <?php if ($is2faEnabled): ?>
                <div class="alert alert-success d-flex align-items-center gap-2">
                    <i class="fas fa-shield-halved fs-5"></i>
                    <div><strong>2FA is enabled.</strong> Your account is protected.</div>
                </div>
                <form id="disable2faForm" data-ajax="true" action="/user/security/2fa/disable" method="POST">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Confirm Password to Disable</label>
                        <input type="password" name="password" class="form-control bg-transparent text-light border-secondary" required>
                    </div>
                    <button type="submit" class="btn btn-outline-danger" id="disable2faBtn">
                        <i class="fas fa-unlock me-1"></i>Disable 2FA
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-triangle fs-5"></i>
                    <div>2FA is not enabled. Enable it to secure your account.</div>
                </div>
                <button class="btn btn-info mb-3" id="btn2faSetup">
                    <i class="fas fa-shield-halved me-1"></i>Setup 2FA
                </button>
                <div id="twoFactorSetup" class="d-none">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Secret Key</label>
                        <div class="input-group">
                            <input type="text" id="tfSecret" class="form-control bg-transparent text-light border-secondary font-monospace" readonly>
                            <button class="btn btn-outline-secondary" id="btnCopySecret"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="form-text text-secondary">Add this key to your authenticator app (Google Authenticator, Authy, etc.)</div>
                    </div>
                    <form id="enable2faForm" data-ajax="true" action="/user/security/2fa/enable" method="POST">
                        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                        <input type="hidden" name="secret" id="tfSecretInput">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Enter 6-digit code from your app</label>
                            <input type="text" name="code" class="form-control bg-transparent text-light border-secondary font-monospace"
                                   maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Verify & Enable</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Security Notifications -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4 h-100">
            <h5 class="mb-3"><i class="fas fa-bell me-2 text-primary"></i>Security Notifications</h5>
            <form id="secSettingsForm" data-ajax="true" action="/user/security/settings" method="POST">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="login_notification_enabled" id="loginNotif"
                           <?= ($securitySettings['login_notification_enabled'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="loginNotif">Login notifications</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="withdrawal_notification_enabled" id="withdrawNotif"
                           <?= ($securitySettings['withdrawal_notification_enabled'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="withdrawNotif">Withdrawal notifications</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="trade_notification_enabled" id="tradeNotif"
                           <?= ($securitySettings['trade_notification_enabled'] ?? 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tradeNotif">Trade notifications</label>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-save me-1"></i>Save</button>
            </form>
        </div>
    </div>

    <!-- Active Sessions -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-laptop me-2 text-success"></i>Active Sessions
                <span class="badge bg-success ms-1"><?= count($activeSessions) ?></span>
            </h5>
            <div class="table-responsive">
                <table class="table table-user table-sm align-middle mb-0">
                    <thead><tr><th>IP Address</th><th>Device</th><th>Expires</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($activeSessions as $sess): ?>
                        <tr>
                            <td class="font-monospace small"><?= e((string)($sess['ip_address'] ?? '-')) ?></td>
                            <td class="small text-secondary text-truncate" style="max-width:200px"><?= e(substr((string)($sess['user_agent'] ?? '-'), 0, 50)) ?></td>
                            <td class="small"><?= e(date('M d, H:i', strtotime((string)($sess['expires_at'] ?? 'now')))) ?></td>
                            <td>
                                 <button class="btn btn-xs btn-outline-danger btn-revoke-session" data-session-id="<?= (int)$sess['id'] ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($activeSessions === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary">No active sessions</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Login History -->
    <div class="col-12">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2 text-secondary"></i>Login History</h5>
            <div class="table-responsive">
                <table id="loginHistoryTable" class="table table-user table-sm">
                    <thead><tr><th>Time</th><th>IP Address</th><th>User Agent</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($loginHistory as $row): ?>
                        <tr>
                            <td class="small"><?= e(date('Y-m-d H:i:s', strtotime((string)($row['created_at'] ?? 'now')))) ?></td>
                            <td class="font-monospace small"><?= e((string)($row['ip_address'] ?? '-')) ?></td>
                            <td class="small text-secondary text-truncate" style="max-width:250px"><?= e(substr((string)($row['user_agent'] ?? '-'), 0, 60)) ?></td>
                            <td>
                                <?php
                                $st = (string)($row['status'] ?? 'success');
                                $cls = $st === 'success' ? 'success' : 'danger';
                                ?>
                                <span class="badge bg-<?= $cls ?>"><?= e($st) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#loginHistoryTable').DataTable({ order: [[0,'desc']], pageLength: 15 });

$(document).on('click', '.btn-revoke-session', function () {
    const sessionId = $(this).data('session-id');
    Swal.fire({ title: 'Revoke session?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444' })
        .then(r => {
            if (!r.isConfirmed) return;
            $.post('/user/security/sessions/revoke', { _token: csrfToken, session_id: sessionId }, res => {
                if (res.ok) location.reload();
                else Swal.fire({ icon: 'error', text: res.message });
            }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
        });
});

$('#btn2faSetup').on('click', function () {
    $.get('/user/security/2fa/generate', function (r) {
        if (r.ok) {
            $('#tfSecret').val(r.data.secret);
            $('#tfSecretInput').val(r.data.secret);
            $('#twoFactorSetup').removeClass('d-none');
            $('#btn2faSetup').addClass('d-none');
        }
    });
});

$('#btnCopySecret').on('click', function () {
    const v = $('#tfSecret').val();
    navigator.clipboard.writeText(v).then(() => Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false }));
});

$('#disable2faBtn').closest('form').on('submit', function (e) {
    if (!confirm('Disable 2FA?')) { e.preventDefault(); }
});
</script>
