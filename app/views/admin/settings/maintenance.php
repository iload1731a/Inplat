<?php declare(strict_types=1); ?>
<?php
$maintenance_mode = (bool)($maintenance_mode ?? false);
$settings         = is_array($settings ?? null) ? $settings : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-wrench me-2 text-warning"></i>Maintenance Mode</h1>
        <p class="text-secondary mb-0">Take the platform offline for maintenance. Users will see a maintenance page.</p>
    </div>
</div>

<!-- Status Banner -->
<div class="alert <?= $maintenance_mode ? 'alert-danger' : 'alert-success' ?> d-flex align-items-center gap-3 mb-4">
    <i class="fas <?= $maintenance_mode ? 'fa-exclamation-triangle' : 'fa-check-circle' ?> fa-2x"></i>
    <div>
        <strong><?= $maintenance_mode ? 'Maintenance Mode is ACTIVE' : 'Platform is Running Normally' ?></strong><br>
        <span class="small">
            <?= $maintenance_mode
                ? 'The platform is currently in maintenance mode. Users cannot access the application.'
                : 'The platform is online and fully operational.' ?>
        </span>
    </div>
    <div class="ms-auto">
        <form method="post" action="/admin/settings/maintenance" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="maintenance_mode" value="<?= $maintenance_mode ? 'false' : 'true' ?>">
            <button class="btn <?= $maintenance_mode ? 'btn-success' : 'btn-danger' ?>">
                <i class="fas <?= $maintenance_mode ? 'fa-power-off' : 'fa-wrench' ?> me-1"></i>
                <?= $maintenance_mode ? 'Disable Maintenance' : 'Enable Maintenance' ?>
            </button>
        </form>
    </div>
</div>

<!-- Configuration Form -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-4"><i class="fas fa-cog me-2 text-secondary"></i>Maintenance Page Configuration</h6>

            <form method="post" action="/admin/settings/maintenance" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="maintenance_mode" value="<?= $maintenance_mode ? 'true' : 'false' ?>">

                <div class="mb-3">
                    <label class="form-label small text-secondary">Maintenance Message</label>
                    <textarea name="maintenance_message" class="form-control bg-transparent text-light border-secondary" rows="3"
                              placeholder="We're performing scheduled maintenance. We'll be back shortly."><?= e($s('maintenance_message')) ?></textarea>
                    <div class="form-text text-secondary">Shown to users on the maintenance page.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Estimated Downtime</label>
                    <input type="text" name="maintenance_eta" value="<?= e($s('maintenance_eta')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="100"
                           placeholder="e.g. Approximately 2 hours, or 2024-01-15 14:00 UTC">
                </div>

                <button type="submit" class="btn btn-primary">Save Maintenance Message</button>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Important Notes</h6>
            <ul class="list-unstyled small text-secondary">
                <li class="mb-2"><i class="fas fa-exclamation-circle text-warning me-1"></i>Admin panel remains accessible during maintenance.</li>
                <li class="mb-2"><i class="fas fa-exclamation-circle text-warning me-1"></i>Active trades and open orders are not affected.</li>
                <li class="mb-2"><i class="fas fa-check-circle text-success me-1"></i>API endpoints continue to function for system processes.</li>
                <li class="mb-2"><i class="fas fa-clock text-info me-1"></i>Communicate planned maintenance via announcements first.</li>
                <li><i class="fas fa-bell text-primary me-1"></i>Consider sending a notification to users before enabling.</li>
            </ul>
            <a href="/admin/notifications" class="btn btn-sm btn-outline-primary w-100 mt-2">
                <i class="fas fa-bell me-1"></i>Send Maintenance Notification
            </a>
        </div>
    </div>
</div>
