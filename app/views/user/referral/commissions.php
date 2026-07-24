<?php declare(strict_types=1); ?>
<?php
$rows        = is_array($rows        ?? null) ? $rows        : [];
$total       = (int)($total          ?? 0);
$page        = (int)($page           ?? 1);
$totalPages  = (int)($total_pages    ?? 1);
$statusFilter= (string)($statusFilter ?? '');
$typeFilter  = (string)($typeFilter   ?? '');
require app_path('app/views/user/_nav.php');
?>

<!-- Filter Bar -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small text-secondary mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:140px">
                <option value="">All Status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid"    <?= $statusFilter === 'paid'    ? 'selected' : '' ?>>Paid</option>
            </select>
        </div>
        <div>
            <label class="form-label small text-secondary mb-1">Type</label>
            <select name="type" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:160px">
                <option value="">All Types</option>
                <option value="trade"        <?= $typeFilter === 'trade'        ? 'selected' : '' ?>>Trade</option>
                <option value="signup_bonus" <?= $typeFilter === 'signup_bonus' ? 'selected' : '' ?>>Signup Bonus</option>
                <option value="reward"       <?= $typeFilter === 'reward'       ? 'selected' : '' ?>>Reward</option>
                <option value="manual"       <?= $typeFilter === 'manual'       ? 'selected' : '' ?>>Manual</option>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-info">Filter</button>
        <a href="/user/referral/commissions" class="btn btn-sm btn-outline-secondary">Reset</a>
        <span class="ms-auto text-secondary small align-self-center"><?= number_format($total) ?> records</span>
    </form>
</div>

<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-user">
            <thead>
                <tr>
                    <th>#</th><th>From User</th><th>Amount</th><th>Currency</th>
                    <th>Type</th><th>Level</th><th>Rate</th><th>Status</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small"><?= (int)($r['id'] ?? 0) ?></td>
                    <td><?= e((string)($r['from_user'] ?? '—')) ?></td>
                    <td class="font-monospace text-warning small"><?= number_format((float)($r['amount'] ?? 0), 8) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($r['currency_code'] ?? '-')) ?></span></td>
                    <td class="small"><?= e((string)($r['commission_type'] ?? '—')) ?></td>
                    <td><span class="badge bg-secondary">L<?= (int)($r['level'] ?? 1) ?></span></td>
                    <td class="small"><?= number_format((float)($r['commission_rate'] ?? 0), 2) ?>%</td>
                    <td>
                        <?php $cs = (string)($r['status'] ?? 'pending'); ?>
                        <span class="badge bg-<?= $cs === 'paid' ? 'success' : 'warning' ?>"><?= e($cs) ?></span>
                    </td>
                    <td class="small text-secondary"><?= e(date('M d, Y', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center text-secondary py-4">No commissions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
            <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link bg-transparent border-secondary text-light"
                   href="?status=<?= urlencode($statusFilter) ?>&type=<?= urlencode($typeFilter) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
