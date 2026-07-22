<?php declare(strict_types=1); ?>
<?php
$notifications = is_array($notifications ?? null) ? $notifications : [];
$unreadCount   = (int)($unreadCount ?? 0);
require app_path('app/views/user/_nav.php');
?>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">
            <i class="fas fa-bell me-2 text-warning"></i>Notifications
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadCount ?> unread</span>
            <?php endif; ?>
        </h5>
        <?php if ($notifications !== []): ?>
        <button class="btn btn-sm btn-outline-secondary" id="markAllBtn">
            <i class="fas fa-check-double me-1"></i>Mark All Read
        </button>
        <?php endif; ?>
    </div>

    <?php if ($notifications === []): ?>
        <div class="text-center py-5 text-secondary">
            <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
            <div>No notifications yet.</div>
        </div>
    <?php else: ?>
        <div id="notifList">
        <?php foreach ($notifications as $notif): ?>
            <?php $isUnread = !(bool)($notif['is_read'] ?? false); ?>
            <div class="notif-item d-flex align-items-start gap-3 p-3 rounded-3 mb-2 <?= $isUnread ? 'border border-info border-opacity-50' : '' ?>"
                 style="background:<?= $isUnread ? 'rgba(56,189,248,.07)' : 'rgba(255,255,255,.02)' ?>;"
                 id="notif-<?= (int)$notif['id'] ?>">
                <div class="flex-shrink-0 mt-1">
                    <?php
                    $notifIcon = match((string)($notif['type'] ?? '')) {
                        'deposit'    => 'fa-arrow-down text-success',
                        'withdrawal' => 'fa-arrow-up text-warning',
                        'trade'      => 'fa-chart-line text-info',
                        'security'   => 'fa-shield-halved text-danger',
                        'kyc'        => 'fa-id-card text-purple',
                        default      => 'fa-bell text-secondary',
                    };
                    ?>
                    <i class="fas <?= $notifIcon ?> fa-lg"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small"><?= e((string)($notif['title'] ?? '')) ?></div>
                    <div class="text-secondary small"><?= e((string)($notif['message'] ?? '')) ?></div>
                    <div class="text-secondary mt-1" style="font-size:.72rem">
                        <i class="fas fa-clock me-1"></i>
                        <?= e(date('M d, Y H:i', strtotime((string)($notif['created_at'] ?? 'now')))) ?>
                    </div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                    <?php if ($isUnread): ?>
                    <button class="btn btn-xs btn-outline-info btn-mark-read" data-notif-id="<?= (int)$notif['id'] ?>" title="Mark as read">
                        <i class="fas fa-check"></i>
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($notif['action_url'])): ?>
                    <a href="<?= e((string)$notif['action_url']) ?>" class="btn btn-xs btn-outline-secondary" title="View">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-xs btn-outline-danger btn-delete-notif" data-notif-id="<?= (int)$notif['id'] ?>" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).on('click', '.btn-mark-read', function () {
    const id = $(this).data('notif-id');
    $.post('/user/notifications/read', { _token: csrfToken, id }, res => {
        if (res.ok) {
            const el = document.getElementById('notif-' + id);
            if (el) {
                el.style.background = 'rgba(255,255,255,.02)';
                el.classList.remove('border', 'border-info', 'border-opacity-50');
                el.querySelector('.btn-mark-read')?.remove();
            }
        }
    });
});

$(document).on('click', '.btn-delete-notif', function () {
    const id = $(this).data('notif-id');
    $.post('/user/notifications/delete', { _token: csrfToken, id }, res => {
        if (res.ok) document.getElementById('notif-' + id)?.remove();
    });
});

$('#markAllBtn').on('click', function () {
    $.post('/user/notifications/read-all', { _token: csrfToken }, res => {
        if (res.ok) {
            Swal.fire({ icon: 'success', title: 'Done', timer: 1000, showConfirmButton: false })
                .then(() => location.reload());
        }
    });
});
</script>
