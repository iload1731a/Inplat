<?php declare(strict_types=1); ?>
<?php
$rows       = is_array($rows    ?? null) ? $rows    : [];
$total      = (int)($total      ?? 0);
$page       = (int)($page       ?? 1);
$totalPages = (int)($total_pages?? 1);
$filters    = is_array($filters ?? null) ? $filters : [];
require app_path('app/views/admin/_nav.php');
?>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small text-secondary mb-1">Search</label>
            <input type="text" name="search" value="<?= e((string)($filters['search'] ?? '')) ?>"
                   class="form-control form-control-sm bg-transparent text-light border-secondary"
                   placeholder="Username / email / code" style="width:200px">
        </div>
        <div>
            <label class="form-label small text-secondary mb-1">From</label>
            <input type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>"
                   class="form-control form-control-sm bg-transparent text-light border-secondary">
        </div>
        <div>
            <label class="form-label small text-secondary mb-1">To</label>
            <input type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>"
                   class="form-control form-control-sm bg-transparent text-light border-secondary">
        </div>
        <button class="btn btn-sm btn-outline-info">Filter</button>
        <a href="/admin/affiliate/affiliates" class="btn btn-sm btn-outline-secondary">Reset</a>
        <span class="ms-auto text-secondary small align-self-center"><?= number_format($total) ?> affiliates</span>
    </form>
</div>

<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-user">
            <thead>
                <tr>
                    <th>ID</th><th>Username</th><th>Email</th><th>Ref Code</th>
                    <th>Referrals</th><th>Qualified</th><th>Commission</th><th>Pending Payouts</th><th>Joined</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small"><?= (int)($r['id'] ?? 0) ?></td>
                    <td><?= e((string)($r['username'] ?? '—')) ?></td>
                    <td class="small text-secondary"><?= e((string)($r['email'] ?? '—')) ?></td>
                    <td><span class="badge bg-info font-monospace"><?= e((string)($r['referral_code'] ?? '—')) ?></span></td>
                    <td><?= number_format((int)($r['total_referrals'] ?? 0)) ?></td>
                    <td><?= number_format((int)($r['qualified'] ?? 0)) ?></td>
                    <td class="font-monospace small text-warning"><?= number_format((float)($r['total_commission'] ?? 0), 2) ?></td>
                    <td>
                        <?php if ((int)($r['pending_payouts'] ?? 0) > 0): ?>
                        <a href="/admin/affiliate/payouts?search=<?= urlencode((string)($r['username'] ?? '')) ?>"
                           class="badge bg-danger text-decoration-none"><?= (int)$r['pending_payouts'] ?> pending</a>
                        <?php else: ?>
                        <span class="text-secondary small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary"><?= e(date('M d, Y', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center text-secondary py-4">No affiliates found.</td></tr>
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
                   href="?search=<?= urlencode((string)($filters['search'] ?? '')) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
