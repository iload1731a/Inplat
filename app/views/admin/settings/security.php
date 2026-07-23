<?php declare(strict_types=1); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-shield-alt me-2 text-danger"></i>Security Configuration</h1>
        <p class="text-secondary mb-0">Password policy, 2FA enforcement, session lifetime, brute force protection, CORS.</p>
    </div>
</div>

<form method="post" action="/admin/settings/security" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <!-- Password Policy -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-key me-2 text-secondary"></i>Password Policy</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Minimum Password Length</label>
                    <input type="number" name="password_min_length" value="<?= e($s('password_min_length', '8')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="6" max="64">
                </div>

                <?php
                $pwdToggles = [
                    ['password_require_upper',   'Require Uppercase Letter',     'At least one A-Z character.'],
                    ['password_require_number',  'Require Number',               'At least one digit 0-9.'],
                    ['password_require_special', 'Require Special Character',    'At least one !@#$%^&* etc.'],
                ];
                foreach ($pwdToggles as [$key, $label, $desc]):
                ?>
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle mb-2">
                    <div>
                        <div class="fw-semibold"><?= e($label) ?></div>
                        <div class="small text-secondary"><?= e($desc) ?></div>
                    </div>
                    <div class="form-check form-switch mb-0 ms-3">
                        <input type="hidden" name="<?= e($key) ?>" value="false">
                        <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="true"
                               <?= $s($key, 'false') === 'true' ? 'checked' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Authentication Settings -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-user-lock me-2 text-secondary"></i>Authentication & Sessions</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Session Lifetime (minutes)</label>
                    <input type="number" name="session_lifetime_minutes" value="<?= e($s('session_lifetime_minutes', '120')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="5" max="10080">
                    <div class="form-text text-secondary">User session expiration. 120 = 2 hours, 1440 = 24 hours.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Max Login Attempts Before Lockout</label>
                    <input type="number" name="max_login_attempts" value="<?= e($s('max_login_attempts', '5')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="1" max="50">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Lockout Duration (minutes)</label>
                    <input type="number" name="brute_force_lockout_mins" value="<?= e($s('brute_force_lockout_mins', '30')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="1" max="1440">
                </div>

                <?php
                $authToggles = [
                    ['two_factor_required',       'Require 2FA for All Users',    'Force all users to enable two-factor authentication.'],
                    ['two_factor_admin_required',  'Require 2FA for Admins',       'Force all admin accounts to use 2FA.'],
                    ['ip_whitelist_enabled',       'IP Whitelist for Admin Panel',  'Only allow admin access from whitelisted IP addresses.'],
                ];
                foreach ($authToggles as [$key, $label, $desc]):
                ?>
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle mb-2">
                    <div>
                        <div class="fw-semibold"><?= e($label) ?></div>
                        <div class="small text-secondary"><?= e($desc) ?></div>
                    </div>
                    <div class="form-check form-switch mb-0 ms-3">
                        <input type="hidden" name="<?= e($key) ?>" value="false">
                        <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="true"
                               <?= $s($key, 'false') === 'true' ? 'checked' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CORS -->
        <div class="col-lg-12">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-3"><i class="fas fa-network-wired me-2 text-secondary"></i>CORS Configuration</h6>
                <div class="mb-0">
                    <label class="form-label small text-secondary">Allowed Origins</label>
                    <input type="text" name="cors_allowed_origins" value="<?= e($s('cors_allowed_origins', '*')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="* or https://domain1.com,https://domain2.com">
                    <div class="form-text text-secondary">
                        Use <code>*</code> to allow all origins (development only).
                        For production, specify comma-separated exact origins.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-danger px-4" type="submit"><i class="fas fa-shield-alt me-1"></i>Save Security Configuration</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>
