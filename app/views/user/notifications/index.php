<?php declare(strict_types=1); ?>
<?php
use App\Services\NotificationsService;

$stats30d      = is_array($stats30d      ?? null) ? $stats30d      : [];
$unreadByType  = is_array($unreadByType  ?? null) ? $unreadByType  : [];
$announcements = is_array($announcements ?? null) ? $announcements : [];
$history       = is_array($history       ?? null) ? $history       : [];
$historyRows   = is_array($history['rows'] ?? null) ? $history['rows'] : [];
$totalPages    = (int)($history['total_pages'] ?? 1);
$currentPage   = (int)($history['page']        ?? 1);
$totalRows     = (int)($history['total']        ?? 0);

$unreadCount   = (int)($unreadCount   ?? 0);
$unreadAnnounce= (int)($unreadAnnounce ?? 0);
$tab           = (string)($tab         ?? 'all');
$readFilter    = (string)($readFilter   ?? 'all');

$total30d    = (int)($stats30d['total']          ?? 0);
$unread30d   = (int)($stats30d['unread']         ?? 0);
$orderCount  = (int)($stats30d['order_count']    ?? 0);
$financeCount= (int)($stats30d['finance_count']  ?? 0);
$secCount    = (int)($stats30d['security_count'] ?? 0);
$sysCount    = (int)($stats30d['system_count']   ?? 0);

$notifTypes = [
    'order_filled'        => ['label' => 'Order Filled',        'icon' => 'fa-check-circle',          'color' => 'success'],
    'order_cancelled'     => ['label' => 'Order Cancelled',     'icon' => 'fa-times-circle',          'color' => 'warning'],
    'deposit_credited'    => ['label' => 'Deposit',             'icon' => 'fa-arrow-down',            'color' => 'success'],
    'withdrawal_processed'=> ['label' => 'Withdrawal',          'icon' => 'fa-arrow-up',              'color' => 'warning'],
    'withdrawal_failed'   => ['label' => 'Withdrawal Failed',   'icon' => 'fa-times',                 'color' => 'danger'],
    'security_alert'      => ['label' => 'Security',            'icon' => 'fa-shield-alt',            'color' => 'danger'],
    'login_alert'         => ['label' => 'Login Alert',         'icon' => 'fa-sign-in-alt',           'color' => 'warning'],
    'new_device'          => ['label' => 'New Device',          'icon' => 'fa-laptop',                'color' => 'danger'],
    'password_changed'    => ['label' => 'Password Changed',    'icon' => 'fa-key',                   'color' => 'warning'],
    'kyc_update'          => ['label' => 'KYC',                 'icon' => 'fa-id-card',               'color' => 'info'],
    'trade'               => ['label' => 'Trade',               'icon' => 'fa-exchange-alt',          'color' => 'info'],
    'price_alert'         => ['label' => 'Price Alert',         'icon' => 'fa-bell',                  'color' => 'warning'],
    'signal'              => ['label' => 'Signal',              'icon' => 'fa-broadcast-tower',       'color' => 'primary'],
    'liquidation'         => ['label' => 'Liquidation',         'icon' => 'fa-exclamation-triangle',  'color' => 'danger'],
    'announcement'        => ['label' => 'Announcement',        'icon' => 'fa-bullhorn',              'color' => 'primary'],
    'support'             => ['label' => 'Support',             'icon' => 'fa-headset',               'color' => 'secondary'],
    'admin_notice'        => ['label' => 'Admin Notice',        'icon' => 'fa-info-circle',           'color' => 'info'],
];

function notifIcon(string $type, array $map): string {
    return $map[$type]['icon']  ?? 'fa-bell';
}
function notifColor(string $type, array $map): string {
    return $map[$type]['color'] ?? 'secondary';
}
?>
<?php require app_path('app/views/user/_nav.php'); ?>

<!-- STATS ROW -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total (30d)',    $total30d,    'primary',   'fa-bell'],
        ['Unread',         $unreadCount, 'warning',   'fa-envelope'],
        ['Trading',        $orderCount,  'success',   'fa-chart-line'],
        ['Finance',        $financeCount,'info',      'fa-dollar-sign'],
        ['Security',       $secCount,    'danger',    'fa-shield-alt'],
        ['System',         $sysCount,    'secondary', 'fa-cog'],
    ];
    foreach ($statCards as [$lbl, $val, $col, $ico]):
    ?>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="glass rounded-4 p-3 text-center h-100">
            <i class="fas <?= $ico ?> text-<?= $col ?> mb-2 fa-lg"></i>
            <div class="h5 mb-0 fw-bold"><?= number_format((int)$val) ?></div>
            <div class="small text-secondary"><?= e($lbl) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- TABS + ACTIONS BAR -->
<div class="glass rounded-4 p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <ul class="nav nav-pills gap-1 mb-0" id="notifTabs">
            <?php
            $tabs = [
                ['all',       'All',            $unreadCount,    'fa-bell'],
                ['trading',   'Trading',        (int)($unreadByType['order_filled'] ?? 0), 'fa-chart-line'],
                ['finance',   'Finance',        (int)(($unreadByType['deposit_credited'] ?? 0) + ($unreadByType['withdrawal_processed'] ?? 0) + ($unreadByType['withdrawal_failed'] ?? 0)), 'fa-wallet'],
                ['security',  'Security',       (int)(($unreadByType['security_alert'] ?? 0) + ($unreadByType['login_alert'] ?? 0)), 'fa-shield-alt'],
                ['system',    'System',         (int)(($unreadByType['announcement'] ?? 0) + ($unreadByType['admin_notice'] ?? 0) + ($unreadByType['kyc_update'] ?? 0)), 'fa-cog'],
            ];
            foreach ($tabs as [$tabKey, $tabLabel, $tabUnread, $tabIcon]):
                $active = $tab === $tabKey;
            ?>
            <li class="nav-item">
                <a class="nav-link py-1 px-3 <?= $active ? 'active' : '' ?> position-relative"
                   href="/user/notifications?tab=<?= e($tabKey) ?>&read=<?= e($readFilter) ?>">
                    <i class="fas <?= $tabIcon ?> me-1"></i><?= e($tabLabel) ?>
                    <?php if ($tabUnread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">
                        <?= $tabUnread > 99 ? '99+' : $tabUnread ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="d-flex gap-2 flex-wrap">
            <div class="btn-group btn-group-sm">
                <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $rf => $rl): ?>
                <a href="/user/notifications?tab=<?= e($tab) ?>&read=<?= e($rf) ?>"
                   class="btn btn-outline-secondary <?= $readFilter === $rf ? 'active' : '' ?>"><?= e($rl) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($unreadCount > 0): ?>
            <button class="btn btn-sm btn-outline-info" id="markAllBtn">
                <i class="fas fa-check-double me-1"></i>Mark All Read
            </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-danger" id="deleteReadBtn">
                <i class="fas fa-trash me-1"></i>Delete Read
            </button>
            <a href="/user/notifications/preferences" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-cog me-1"></i>Preferences
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- NOTIFICATION LIST -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-3">
            <?php if ($historyRows === []): ?>
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
                <div class="fw-semibold">No notifications<?= $readFilter === 'unread' ? ' to catch up on' : '' ?></div>
                <div class="small mt-1">
                    <?= $readFilter === 'unread' ? "You're all caught up!" : 'Notifications will appear here when you have activity.' ?>
                </div>
            </div>
            <?php else: ?>
            <div id="notifList">
            <?php foreach ($historyRows as $notif):
                $isUnread = !(bool)($notif['is_read'] ?? false);
                $type     = (string)($notif['type'] ?? 'admin_notice');
                $icon     = notifIcon($type, $notifTypes);
                $color    = notifColor($type, $notifTypes);
            ?>
            <div class="notif-item d-flex align-items-start gap-3 p-3 rounded-3 mb-2"
                 style="background:<?= $isUnread ? 'rgba(56,189,248,.07)' : 'rgba(255,255,255,.02)' ?>;
                        <?= $isUnread ? 'border-left:3px solid #38bdf8;' : '' ?>"
                 id="notif-<?= (int)$notif['id'] ?>">
                <div class="flex-shrink-0 mt-1">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:36px;height:36px;background:rgba(var(--bs-<?= $color ?>-rgb),.15)">
                        <i class="fas <?= $icon ?> text-<?= $color ?> small"></i>
                    </div>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php if ($isUnread): ?>
                        <span class="badge bg-primary" style="font-size:.6rem">NEW</span>
                        <?php endif; ?>
                        <span class="fw-semibold small"><?= e((string)($notif['title'] ?? '')) ?></span>
                    </div>
                    <div class="text-secondary small"><?= e((string)($notif['message'] ?? '')) ?></div>
                    <div class="d-flex gap-3 mt-1" style="font-size:.72rem">
                        <span class="text-secondary">
                            <i class="fas fa-clock me-1"></i>
                            <?= e(date('M d, Y H:i', strtotime((string)($notif['created_at'] ?? 'now')))) ?>
                        </span>
                        <span class="badge text-bg-secondary"><?= e($type) ?></span>
                    </div>
                </div>
                <div class="d-flex flex-column gap-1 flex-shrink-0">
                    <?php if ($isUnread): ?>
                    <button class="btn btn-xs btn-outline-info btn-mark-read" data-id="<?= (int)$notif['id'] ?>" title="Mark read">
                        <i class="fas fa-check fa-xs"></i>
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($notif['action_url'])): ?>
                    <a href="<?= e((string)$notif['action_url']) ?>" class="btn btn-xs btn-outline-secondary" title="View">
                        <i class="fas fa-external-link-alt fa-xs"></i>
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-xs btn-outline-danger btn-delete-notif" data-id="<?= (int)$notif['id'] ?>" title="Delete">
                        <i class="fas fa-trash fa-xs"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?tab=<?= e($tab) ?>&read=<?= e($readFilter) ?>&page=<?= $currentPage - 1 ?>">«</a>
                    </li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?tab=<?= e($tab) ?>&read=<?= e($readFilter) ?>&page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?tab=<?= e($tab) ?>&read=<?= e($readFilter) ?>&page=<?= $currentPage + 1 ?>">»</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="text-center small text-secondary mt-1">
                    Page <?= $currentPage ?> of <?= $totalPages ?> · <?= number_format($totalRows) ?> total
                </div>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- SIDEBAR: ANNOUNCEMENTS + QUICK ACTIONS -->
    <div class="col-xl-4">
        <!-- ANNOUNCEMENTS -->
        <div class="glass rounded-4 p-3 mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0">
                    <i class="fas fa-bullhorn me-2 text-warning"></i>Announcements
                    <?php if ($unreadAnnounce > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unreadAnnounce ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <?php if ($announcements === []): ?>
            <div class="text-center py-3 text-secondary small">No announcements.</div>
            <?php else: ?>
            <?php foreach ($announcements as $ann):
                $catColors = ['maintenance' => 'warning', 'new_listing' => 'success', 'delisting' => 'danger',
                              'promotion' => 'primary', 'security' => 'danger', 'general' => 'info'];
                $annColor  = $catColors[(string)($ann['category'] ?? 'general')] ?? 'info';
            ?>
            <div class="d-flex align-items-start gap-2 mb-3 pb-3 border-bottom border-opacity-25 ann-item"
                 data-id="<?= (int)$ann['id'] ?>">
                <?php if ((bool)($ann['is_pinned'] ?? false)): ?>
                <i class="fas fa-thumbtack text-warning mt-1 flex-shrink-0"></i>
                <?php else: ?>
                <i class="fas fa-circle text-<?= $annColor ?> fa-xs mt-2 flex-shrink-0"></i>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="small fw-semibold"><?= e((string)($ann['title'] ?? '')) ?></div>
                    <div class="text-secondary" style="font-size:.72rem">
                        <span class="badge text-bg-<?= $annColor ?> me-1"><?= e(ucfirst((string)($ann['category'] ?? ''))) ?></span>
                        <?= !empty($ann['published_at']) ? e(date('M d', strtotime((string)$ann['published_at']))) : '' ?>
                    </div>
                </div>
                <button class="btn btn-xs btn-outline-secondary ann-read-btn" data-id="<?= (int)$ann['id'] ?>" title="Mark read">
                    <i class="fas fa-times fa-xs"></i>
                </button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- QUICK LINKS -->
        <div class="glass rounded-4 p-3">
            <h6 class="mb-3"><i class="fas fa-bolt me-2 text-info"></i>Quick Actions</h6>
            <div class="d-grid gap-2">
                <a href="/user/notifications/history" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="fas fa-history me-2"></i>Full Notification History
                </a>
                <a href="/user/notifications/preferences" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="fas fa-sliders-h me-2"></i>Notification Preferences
                </a>
                <a href="/user/security" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="fas fa-shield-alt me-2"></i>Security Settings
                </a>
                <a href="/user/signals" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="fas fa-bell me-2"></i>Price Alerts & Signals
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= e(\App\Libraries\Csrf::token()) ?>';

// Mark single read
$(document).on('click', '.btn-mark-read', function () {
    const id = $(this).data('id');
    $.post('/user/notifications/read', { _token: csrfToken, id }, res => {
        if (!res.ok) return;
        const el = document.getElementById('notif-' + id);
        if (el) {
            el.style.background = 'rgba(255,255,255,.02)';
            el.style.borderLeft = '';
            el.querySelector('.btn-mark-read')?.remove();
            el.querySelector('.badge.bg-primary')?.remove();
        }
        updateBadge(res.unread_count ?? 0);
    });
});

// Delete single
$(document).on('click', '.btn-delete-notif', function () {
    const id = $(this).data('id');
    if (!confirm('Delete this notification?')) return;
    $.post('/user/notifications/delete', { _token: csrfToken, id }, res => {
        if (res.ok) document.getElementById('notif-' + id)?.remove();
    });
});

// Mark all read
$('#markAllBtn').on('click', function () {
    $.post('/user/notifications/read-all', { _token: csrfToken }, res => {
        if (res.ok) {
            Swal.fire({ icon: 'success', title: 'Done', text: 'All marked as read.', timer: 1200, showConfirmButton: false })
                .then(() => location.reload());
        }
    });
});

// Delete all read
$('#deleteReadBtn').on('click', function () {
    Swal.fire({
        title: 'Delete all read notifications?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#ef4444',
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/user/notifications/delete-read', { _token: csrfToken }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Cleared', text: res.message, timer: 1200, showConfirmButton: false })
                    .then(() => location.reload());
            }
        });
    });
});

// Dismiss announcement
$(document).on('click', '.ann-read-btn', function () {
    const id = $(this).data('id');
    $.post('/user/notifications/announcement-read', { _token: csrfToken, id }, res => {
        if (res.ok) $(this).closest('.ann-item').fadeOut(300, function() { $(this).remove(); });
    });
});

// Update navbar badge
function updateBadge(count) {
    const badge = document.querySelector('#notifBadge');
    if (!badge) return;
    if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.style.display = ''; }
    else { badge.style.display = 'none'; }
}

// Real-time badge poll every 30s
const _notifPollId = setInterval(() => {
    $.getJSON('/user/notifications/poll', res => {
        if (res.ok) updateBadge(res.unread_count);
    });
}, 30000);
window.addEventListener('beforeunload', () => clearInterval(_notifPollId));
</script>
