<?php declare(strict_types=1); ?>
<?php
$rows        = is_array($rows        ?? null) ? $rows        : [];
$total       = (int)($total          ?? 0);
$page        = (int)($page           ?? 1);
$totalPages  = (int)($total_pages    ?? 1);
$perPage     = (int)($per_page       ?? 25);
$statusFilter= (string)($statusFilter ?? '');
require app_path('app/views/user/_nav.php');
?>

<!-- Filter Bar -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small text-secondary mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:160px">
                <option value="">All</option>
                <?php foreach (['pending','qualified','active'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-info">Filter</button>
        <a href="/user/referral/referrals" class="btn btn-sm btn-outline-secondary">Reset</a>
        <span class="ms-auto text-secondary small align-self-center"><?= number_format($total) ?> referral(s)</span>
    </form>
</div>

<!-- Referrals Table -->
<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-user">
            <thead>
                <tr>
                    <th>#</th><th>Username</th><th>Email</th><th>Level</th>
                    <th>Status</th><th>Commissions</th><th>Total Earned</th><th>Joined</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($r['id'] ?? 0) ?></td>
                    <td><?= e((string)($r['referred_username'] ?? '—')) ?></td>
                    <td class="small text-secondary"><?= e((string)($r['referred_email'] ?? '—')) ?></td>
                    <td><span class="badge bg-secondary">L<?= (int)($r['level'] ?? 1) ?></span></td>
                    <td>
                        <?php
                        $rs = (string)($r['status'] ?? 'pending');
                        $rc = match($rs) { 'qualified' => 'success', 'active' => 'info', default => 'warning' };
                        ?>
                        <span class="badge bg-<?= $rc ?>"><?= e($rs) ?></span>
                    </td>
                    <td><?= number_format((int)($r['paid_commissions'] ?? 0)) ?></td>
                    <td class="font-monospace small text-warning"><?= number_format((float)($r['total_earned'] ?? 0), 6) ?></td>
                    <td class="small"><?= e(date('M d, Y', strtotime((string)($r['user_joined_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="8" class="text-center text-secondary py-4">No referrals found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
            <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link bg-transparent border-secondary text-light"
                   href="?status=<?= urlencode($statusFilter) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
