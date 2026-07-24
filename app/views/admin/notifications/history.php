<?php declare(strict_types=1); ?>
<?php
$list    = is_array($list    ?? null) ? $list    : [];
$rows    = is_array($list['rows'] ?? null) ? $list['rows'] : [];
$filters = is_array($filters ?? null) ? $filters : [];
$total      = (int)($list['total']       ?? 0);
$totalPages = (int)($list['total_pages'] ?? 1);
$currentPage= (int)($list['page']        ?? 1);
$perPage    = (int)($list['per_page']    ?? 50);

$statusColors = [
    'sent'    => 'success',
    'failed'  => 'danger',
    'pending' => 'warning',
    'skipped' => 'secondary',
];
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- FILTER BAR -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="get" action="/admin/notifications/history" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small text-secondary">User ID</label>
            <input type="number" class="form-control form-control-sm" name="user_id"
                   value="<?= e((string)($filters['user_id'] ?? '')) ?>" placeholder="User ID">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary">Channel</label>
            <select class="form-select form-select-sm" name="channel">
                <option value="">All</option>
                <?php foreach (['in_app','email','sms','push'] as $ch): ?>
                <option value="<?= e($ch) ?>" <?= ($filters['channel'] ?? '') === $ch ? 'selected' : '' ?>>
                    <?= e(ucfirst($ch)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All</option>
                <?php foreach (['sent','failed','pending','skipped'] as $st): ?>
                <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>>
                    <?= e(ucfirst($st)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary">Type</label>
            <input type="text" class="form-control form-control-sm" name="type"
                   value="<?= e((string)($filters['type'] ?? '')) ?>" placeholder="e.g. order_filled">
        </div>
        <div class="col-md-1">
            <label class="form-label small text-secondary">From</label>
            <input type="date" class="form-control form-control-sm" name="date_from"
                   value="<?= e((string)($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label small text-secondary">To</label>
            <input type="date" class="form-control form-control-sm" name="date_to"
                   value="<?= e((string)($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary btn-sm" type="submit">
                <i class="fas fa-search me-1"></i>Filter
            </button>
            <a href="/admin/notifications/history" class="btn btn-outline-secondary btn-sm">Reset</a>
            <a href="/admin/notifications/export?<?= http_build_query($filters) ?>" class="btn btn-outline-success btn-sm">
                <i class="fas fa-download me-1"></i>CSV
            </a>
        </div>
    </form>
</div>

<!-- LOG TABLE -->
<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Notification Dispatch Log</h6>
        <span class="text-secondary small"><?= number_format($total) ?> records</span>
    </div>

    <?php if ($rows === []): ?>
    <div class="text-center py-5 text-secondary">
        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
        <div>No log entries match your filters.</div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Error</th>
                    <th>Sent At</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $status = (string)($r['status'] ?? 'pending');
                $color  = $statusColors[$status] ?? 'secondary';
            ?>
            <tr>
                <td class="text-secondary"><?= (int)$r['id'] ?></td>
                <td>
                    <div class="fw-medium"><?= e((string)($r['username'] ?? '')) ?></div>
                    <div class="text-secondary" style="font-size:.7rem"><?= e((string)($r['user_email'] ?? '')) ?></div>
                </td>
                <td class="fw-medium"><?= e(mb_substr((string)($r['title'] ?? ''), 0, 60)) ?></td>
                <td><span class="badge text-bg-secondary"><?= e((string)($r['type'] ?? '')) ?></span></td>
                <td><span class="badge text-bg-info"><?= e((string)($r['channel'] ?? '')) ?></span></td>
                <td><span class="badge text-bg-<?= $color ?>"><?= e(ucfirst($status)) ?></span></td>
                <td class="text-danger small">
                    <?= !empty($r['error_message']) ? e(mb_substr((string)$r['error_message'], 0, 50)) : '—' ?>
                </td>
                <td class="text-secondary"><?= !empty($r['sent_at']) ? e(date('M d H:i', strtotime((string)$r['sent_at']))) : '—' ?></td>
                <td class="text-secondary"><?= e(date('M d H:i', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
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
                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $currentPage - 1])) ?>">«</a>
            </li>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $currentPage + 1])) ?>">»</a>
            </li>
            <?php endif; ?>
        </ul>
        <div class="text-center small text-secondary mt-1">
            Page <?= $currentPage ?> of <?= $totalPages ?> · <?= number_format($total) ?> records
        </div>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
