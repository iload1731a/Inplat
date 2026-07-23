<?php declare(strict_types=1); ?>
<?php
$history     = is_array($history     ?? null) ? $history     : [];
$historyRows = is_array($history['rows'] ?? null) ? $history['rows'] : [];
$totalPages  = (int)($history['total_pages'] ?? 1);
$currentPage = (int)($history['page']        ?? 1);
$totalRows   = (int)($history['total']        ?? 0);
$unreadCount = (int)($unreadCount ?? 0);
$typeFilter  = (string)($typeFilter  ?? '');
$readFilter  = (string)($readFilter  ?? '');

$typeOptions = [
    ''                     => 'All Types',
    'order_filled'         => 'Order Filled',
    'order_cancelled'      => 'Order Cancelled',
    'deposit_credited'     => 'Deposit',
    'withdrawal_processed' => 'Withdrawal',
    'security_alert'       => 'Security Alert',
    'login_alert'          => 'Login Alert',
    'new_device'           => 'New Device',
    'kyc_update'           => 'KYC',
    'trade'                => 'Trade',
    'price_alert'          => 'Price Alert',
    'signal'               => 'Signal',
    'announcement'         => 'Announcement',
    'admin_notice'         => 'Admin Notice',
    'support'              => 'Support',
];
?>
<?php require app_path('app/views/user/_nav.php'); ?>

<!-- FILTER BAR -->
<div class="glass rounded-4 p-3 mb-3">
    <form method="get" action="/user/notifications/history" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small text-secondary">Type</label>
            <select class="form-select form-select-sm" name="type">
                <?php foreach ($typeOptions as $val => $lbl): ?>
                <option value="<?= e((string)$val) ?>" <?= $typeFilter === (string)$val ? 'selected' : '' ?>>
                    <?= e($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-secondary">Status</label>
            <select class="form-select form-select-sm" name="read">
                <option value="" <?= $readFilter === '' ? 'selected' : '' ?>>All</option>
                <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                <option value="read"   <?= $readFilter === 'read'   ? 'selected' : '' ?>>Read Only</option>
            </select>
        </div>
        <div class="col-md-3">
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
                <a href="/user/notifications/history" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
        <div class="col-md-3 text-end">
            <span class="small text-secondary"><?= number_format($totalRows) ?> notifications</span>
        </div>
    </form>
</div>

<!-- LIST -->
<div class="glass rounded-4 p-3">
    <?php if ($historyRows === []): ?>
    <div class="text-center py-5 text-secondary">
        <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
        <div>No notifications match your filters.</div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle small">
            <thead class="table-dark">
                <tr>
                    <th style="width:40px"></th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Channel</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $typeIcons = [
                'order_filled' => 'fa-check-circle text-success',
                'order_cancelled' => 'fa-times-circle text-warning',
                'deposit_credited' => 'fa-arrow-down text-success',
                'withdrawal_processed' => 'fa-arrow-up text-warning',
                'security_alert' => 'fa-shield-alt text-danger',
                'login_alert' => 'fa-sign-in-alt text-warning',
                'new_device' => 'fa-laptop text-danger',
                'kyc_update' => 'fa-id-card text-info',
                'trade' => 'fa-exchange-alt text-info',
                'price_alert' => 'fa-bell text-warning',
                'signal' => 'fa-broadcast-tower text-primary',
                'announcement' => 'fa-bullhorn text-primary',
                'admin_notice' => 'fa-info-circle text-info',
                'support' => 'fa-headset text-secondary',
            ];
            foreach ($historyRows as $n):
                $isUnread = !(bool)($n['is_read'] ?? false);
                $type     = (string)($n['type'] ?? '');
                $icon     = $typeIcons[$type] ?? 'fa-bell text-secondary';
            ?>
            <tr id="notif-<?= (int)$n['id'] ?>" class="<?= $isUnread ? 'table-active' : '' ?>">
                <td class="text-center">
                    <i class="fas <?= $icon ?>"></i>
                </td>
                <td>
                    <?php if ($isUnread): ?><span class="badge bg-primary me-1" style="font-size:.6rem">NEW</span><?php endif; ?>
                    <span class="fw-medium"><?= e((string)($n['title'] ?? '')) ?></span>
                    <div class="text-secondary small"><?= e(mb_substr((string)($n['message'] ?? ''), 0, 80)) ?><?= strlen((string)($n['message'] ?? '')) > 80 ? '…' : '' ?></div>
                </td>
                <td><span class="badge text-bg-secondary"><?= e($type) ?></span></td>
                <td><?= e((string)($n['channel'] ?? 'in_app')) ?></td>
                <td class="text-secondary">
                    <?= e(date('M d, Y', strtotime((string)($n['created_at'] ?? 'now')))) ?><br>
                    <span style="font-size:.7rem"><?= e(date('H:i', strtotime((string)($n['created_at'] ?? 'now')))) ?></span>
                </td>
                <td>
                    <?php if ($isUnread): ?>
                    <span class="badge bg-warning text-dark">Unread</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Read</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <?php if ($isUnread): ?>
                        <button class="btn btn-xs btn-outline-info btn-mark-read" data-id="<?= (int)$n['id'] ?>" title="Mark read">
                            <i class="fas fa-check fa-xs"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (!empty($n['action_url'])): ?>
                        <a href="<?= e((string)$n['action_url']) ?>" class="btn btn-xs btn-outline-secondary" title="View">
                            <i class="fas fa-link fa-xs"></i>
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-xs btn-outline-danger btn-delete-notif" data-id="<?= (int)$n['id'] ?>" title="Delete">
                            <i class="fas fa-trash fa-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php if ($currentPage > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?type=<?= e($typeFilter) ?>&read=<?= e($readFilter) ?>&page=<?= $currentPage - 1 ?>">«</a>
            </li>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?type=<?= e($typeFilter) ?>&read=<?= e($readFilter) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?type=<?= e($typeFilter) ?>&read=<?= e($readFilter) ?>&page=<?= $currentPage + 1 ?>">»</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const csrfToken = '<?= e(\App\Libraries\Csrf::token()) ?>';

$(document).on('click', '.btn-mark-read', function () {
    const id = $(this).data('id');
    $.post('/user/notifications/read', { _token: csrfToken, id }, res => {
        if (!res.ok) return;
        const row = document.getElementById('notif-' + id);
        if (row) {
            row.classList.remove('table-active');
            row.querySelector('.badge.bg-primary')?.remove();
            row.querySelector('.badge.bg-warning')?.replaceWith(Object.assign(document.createElement('span'), {
                className: 'badge bg-secondary', textContent: 'Read'
            }));
            $(this).remove();
        }
    });
});

$(document).on('click', '.btn-delete-notif', function () {
    const id = $(this).data('id');
    if (!confirm('Delete this notification?')) return;
    $.post('/user/notifications/delete', { _token: csrfToken, id }, res => {
        if (res.ok) document.getElementById('notif-' + id)?.remove();
    });
});
</script>
