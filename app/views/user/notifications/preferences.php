<?php declare(strict_types=1); ?>
<?php
use App\Services\NotificationsService;
$preferences = is_array($preferences ?? null) ? $preferences : [];
$saved       = !empty($_GET['saved']);
$errorMsg    = trim((string)($_GET['error'] ?? ''));
?>
<?php require app_path('app/views/user/_nav.php'); ?>

<?php if ($saved): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>Notification preferences saved.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= e($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="glass rounded-4 p-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h5 class="mb-1"><i class="fas fa-sliders-h me-2 text-info"></i>Notification Preferences</h5>
            <p class="text-secondary small mb-0">Choose which channels receive each type of notification.</p>
        </div>
        <a href="/user/notifications" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>

    <form action="/user/notifications/preferences" method="post">
        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

        <!-- LEGEND -->
        <div class="d-flex gap-4 mb-3 small text-secondary flex-wrap">
            <span><i class="fas fa-mobile-alt me-1 text-info"></i>In-App</span>
            <span><i class="fas fa-envelope me-1 text-primary"></i>Email</span>
            <span><i class="fas fa-bell me-1 text-warning"></i>Push</span>
            <span><i class="fas fa-sms me-1 text-success"></i>SMS</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Notification Type</th>
                        <th class="text-center">In-App</th>
                        <th class="text-center">Email</th>
                        <th class="text-center">Push</th>
                        <th class="text-center">SMS</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($preferences as $cat => $pref): ?>
                <tr>
                    <td>
                        <i class="fas <?= e((string)($pref['icon'] ?? 'fa-bell')) ?> me-2 text-secondary"></i>
                        <?= e((string)($pref['label'] ?? $cat)) ?>
                    </td>
                    <td class="text-center">
                        <div class="form-check d-inline-block m-0">
                            <input class="form-check-input" type="checkbox" name="in_app_<?= e($cat) ?>"
                                   id="ia_<?= e($cat) ?>"
                                   <?= (bool)($pref['notify_in_app'] ?? true) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check d-inline-block m-0">
                            <input class="form-check-input" type="checkbox" name="email_<?= e($cat) ?>"
                                   id="em_<?= e($cat) ?>"
                                   <?= (bool)($pref['notify_email'] ?? true) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check d-inline-block m-0">
                            <input class="form-check-input" type="checkbox" name="push_<?= e($cat) ?>"
                                   id="pu_<?= e($cat) ?>"
                                   <?= (bool)($pref['notify_push'] ?? false) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check d-inline-block m-0">
                            <input class="form-check-input" type="checkbox" name="sms_<?= e($cat) ?>"
                                   id="sm_<?= e($cat) ?>"
                                   <?= (bool)($pref['notify_sms'] ?? false) ? 'checked' : '' ?>>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- BULK TOGGLES -->
        <div class="d-flex gap-2 mt-3 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="enableAllEmail">
                <i class="fas fa-envelope me-1"></i>Enable All Email
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="disableAllEmail">
                <i class="fas fa-envelope-open me-1"></i>Disable All Email
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="enableAllInApp">
                <i class="fas fa-mobile-alt me-1"></i>Enable All In-App
            </button>
            <div class="ms-auto">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i>Save Preferences
                </button>
            </div>
        </div>
    </form>
</div>

<!-- PUSH NOTIFICATION PERMISSIONS -->
<div class="glass rounded-4 p-4 mt-3">
    <h6 class="mb-3"><i class="fas fa-bell me-2 text-warning"></i>Browser Push Notifications</h6>
    <p class="text-secondary small mb-3">
        Enable browser push notifications to receive real-time alerts even when you're not on this page.
    </p>
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-outline-warning btn-sm" id="requestPushBtn">
            <i class="fas fa-bell me-1"></i>Enable Browser Notifications
        </button>
        <span class="small text-secondary" id="pushStatus"></span>
    </div>
</div>

<script>
$('#enableAllEmail').on('click', function () {
    $('input[name^="email_"]').prop('checked', true);
});
$('#disableAllEmail').on('click', function () {
    $('input[name^="email_"]').prop('checked', false);
});
$('#enableAllInApp').on('click', function () {
    $('input[name^="in_app_"]').prop('checked', true);
});

// Browser push permission request
$('#requestPushBtn').on('click', function () {
    if (!('Notification' in window)) {
        $('#pushStatus').text('Browser push not supported.');
        return;
    }
    Notification.requestPermission().then(perm => {
        if (perm === 'granted') {
            $('#pushStatus').html('<i class="fas fa-check text-success me-1"></i>Push notifications enabled.');
        } else {
            $('#pushStatus').html('<i class="fas fa-times text-danger me-1"></i>Permission denied.');
        }
    });
});

// Check current state on page load
if ('Notification' in window && Notification.permission === 'granted') {
    $('#pushStatus').html('<i class="fas fa-check text-success me-1"></i>Push notifications are enabled.');
} else if ('Notification' in window && Notification.permission === 'denied') {
    $('#pushStatus').html('<i class="fas fa-times text-danger me-1"></i>Push permission denied in browser settings.');
}
</script>
