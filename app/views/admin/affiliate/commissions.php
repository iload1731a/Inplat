<?php declare(strict_types=1); ?>
<?php
$rows       = is_array($rows    ?? null) ? $rows    : [];
$total      = (int)($total      ?? 0);
$page       = (int)($page       ?? 1);
$totalPages = (int)($total_pages?? 1);
$filters    = is_array($filters ?? null) ? $filters : [];
require app_path('app/views/admin/_nav.php');
?>

<!-- Filters + Bulk Actions -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3" id="filterForm">
        <div>
            <label class="form-label small text-secondary mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:130px">
                <option value="">All</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid"    <?= ($filters['status'] ?? '') === 'paid'    ? 'selected' : '' ?>>Paid</option>
            </select>
        </div>
        <div>
            <label class="form-label small text-secondary mb-1">Type</label>
            <select name="type" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:150px">
                <option value="">All Types</option>
                <option value="trade"        <?= ($filters['type'] ?? '') === 'trade'        ? 'selected' : '' ?>>Trade</option>
                <option value="signup_bonus" <?= ($filters['type'] ?? '') === 'signup_bonus' ? 'selected' : '' ?>>Signup Bonus</option>
                <option value="reward"       <?= ($filters['type'] ?? '') === 'reward'       ? 'selected' : '' ?>>Reward</option>
                <option value="manual"       <?= ($filters['type'] ?? '') === 'manual'       ? 'selected' : '' ?>>Manual</option>
            </select>
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
        <a href="/admin/affiliate/commissions" class="btn btn-sm btn-outline-secondary">Reset</a>
        <a href="/admin/affiliate/commissions/export?<?= http_build_query(array_filter($filters)) ?>"
           class="btn btn-sm btn-outline-success ms-auto"><i class="fas fa-download me-1"></i>Export CSV</a>
    </form>

    <!-- Bulk Mark Paid -->
    <form method="post" action="/admin/affiliate/commissions/mark-paid" id="bulkForm">
        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnSelectAll">Select All</button>
            <button type="submit" class="btn btn-xs btn-outline-success"
                    onclick="return confirm('Mark selected commissions as paid?')">
                <i class="fas fa-check me-1"></i>Mark Selected Paid
            </button>
            <span class="text-secondary small ms-auto"><?= number_format($total) ?> record(s)</span>
        </div>
</div>

<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-user">
            <thead>
                <tr>
                    <th><input type="checkbox" id="chkAll" class="form-check-input"></th>
                    <th>#</th><th>Referrer</th><th>From</th><th>Amount</th><th>Currency</th>
                    <th>Type</th><th>Level</th><th>Rate</th><th>Status</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?php if ((string)($r['status'] ?? '') === 'pending'): ?>
                        <input type="checkbox" name="ids[]" value="<?= (int)($r['id'] ?? 0) ?>" class="form-check-input row-chk">
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= (int)($r['id'] ?? 0) ?></td>
                    <td class="small"><?= e((string)($r['referrer_username'] ?? '—')) ?></td>
                    <td class="small"><?= e((string)($r['from_username'] ?? '—')) ?></td>
                    <td class="font-monospace small text-warning"><?= number_format((float)($r['amount'] ?? 0), 8) ?></td>
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
                <tr><td colspan="11" class="text-center text-secondary py-4">No commissions found.</td></tr>
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
                   href="?<?= http_build_query(array_merge(array_filter($filters), ['page' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    </form>
</div>

<script>
$('#chkAll').on('change', function () {
    $('.row-chk').prop('checked', this.checked);
});
$('#btnSelectAll').on('click', function () {
    $('.row-chk').prop('checked', true);
    $('#chkAll').prop('checked', true);
});
</script>
