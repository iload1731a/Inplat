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
            <label class="form-label small text-secondary mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:140px">
                <option value="">All</option>
                <?php foreach (['pending','approved','rejected','paid'] as $s): ?>
                <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small text-secondary mb-1">Search</label>
            <input type="text" name="search" value="<?= e((string)($filters['search'] ?? '')) ?>"
                   class="form-control form-control-sm bg-transparent text-light border-secondary"
                   placeholder="Username / email" style="width:180px">
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
        <a href="/admin/affiliate/payouts" class="btn btn-sm btn-outline-secondary">Reset</a>
        <a href="/admin/affiliate/payouts/export?<?= http_build_query(array_filter($filters)) ?>"
           class="btn btn-sm btn-outline-success ms-auto"><i class="fas fa-download me-1"></i>CSV</a>
    </form>
</div>

<!-- Bulk Actions -->
<form method="post" action="/admin/affiliate/payouts/bulk" id="bulkPayoutForm">
    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

<div class="glass rounded-4 p-3 mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-xs btn-outline-secondary" id="btnSelAll">Select All</button>
        <select name="status" class="form-select form-select-sm bg-transparent text-light border-secondary" style="width:140px" id="bulkStatus">
            <option value="approved">Approve</option>
            <option value="paid">Mark Paid</option>
            <option value="rejected">Reject</option>
        </select>
        <button type="submit" class="btn btn-xs btn-outline-warning"
                onclick="return confirm('Apply bulk action to selected payouts?')">
            Apply to Selected
        </button>
        <span class="text-secondary small ms-auto"><?= number_format($total) ?> payout(s)</span>
    </div>
</div>

<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-user">
            <thead>
                <tr>
                    <th><input type="checkbox" id="chkAll" class="form-check-input"></th>
                    <th>#</th><th>User</th><th>Amount</th><th>Currency</th>
                    <th>Wallet</th><th>Network</th><th>Status</th><th>Submitted</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?php if (in_array((string)($r['status'] ?? ''), ['pending','approved'], true)): ?>
                        <input type="checkbox" name="ids[]" value="<?= (int)($r['id'] ?? 0) ?>" class="form-check-input row-chk">
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= (int)($r['id'] ?? 0) ?></td>
                    <td>
                        <div class="small fw-semibold"><?= e((string)($r['username'] ?? '—')) ?></div>
                        <div class="text-secondary" style="font-size:.7rem"><?= e((string)($r['email'] ?? '')) ?></div>
                    </td>
                    <td class="font-monospace small text-warning"><?= number_format((float)($r['amount'] ?? 0), 6) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($r['currency_code'] ?? '-')) ?></span></td>
                    <td class="font-monospace" style="font-size:.7rem;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= e((string)($r['wallet_address'] ?? '')) ?>">
                        <?= e(substr((string)($r['wallet_address'] ?? '—'), 0, 14)) ?>...
                    </td>
                    <td class="small"><?= e((string)($r['network'] ?? '—')) ?></td>
                    <td>
                        <?php $ps = (string)($r['status'] ?? 'pending'); ?>
                        <span class="badge bg-<?= match($ps) { 'paid' => 'success', 'approved' => 'info', 'rejected' => 'danger', default => 'warning' } ?>">
                            <?= e($ps) ?>
                        </span>
                    </td>
                    <td class="small text-secondary"><?= e(date('M d, Y', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
                    <td>
                        <?php if (in_array($ps, ['pending','approved'], true)): ?>
                        <button type="button" class="btn btn-xs btn-outline-info"
                                data-bs-toggle="modal" data-bs-target="#processModal"
                                data-payout-id="<?= (int)($r['id'] ?? 0) ?>"
                                data-username="<?= e((string)($r['username'] ?? '')) ?>"
                                data-amount="<?= number_format((float)($r['amount'] ?? 0), 6) ?> <?= e((string)($r['currency_code'] ?? '')) ?>">
                            Process
                        </button>
                        <?php else: ?>
                        <span class="text-secondary small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">No payouts found.</td></tr>
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
</div>
</form>

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h6 class="modal-title">Process Payout</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/affiliate/payouts/process">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="payout_id" id="modalPayoutId">
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="text-secondary small mb-2">User: <strong id="modalUsername"></strong></div>
                        <div class="text-secondary small">Amount: <strong id="modalAmount" class="text-warning"></strong></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Action</label>
                        <select name="status" class="form-select bg-transparent text-light border-secondary">
                            <option value="approved">Approve</option>
                            <option value="paid">Mark as Paid</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-secondary">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control bg-transparent text-light border-secondary"
                                  rows="3" maxlength="500" placeholder="Optional notes (visible to admin only)"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning">Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#processModal').on('show.bs.modal', function (e) {
    const btn = $(e.relatedTarget);
    $('#modalPayoutId').val(btn.data('payout-id'));
    $('#modalUsername').text(btn.data('username'));
    $('#modalAmount').text(btn.data('amount'));
});
$('#chkAll').on('change', function () {
    $('.row-chk').prop('checked', this.checked);
});
$('#btnSelAll').on('click', function () {
    $('.row-chk').prop('checked', true);
    $('#chkAll').prop('checked', true);
});
</script>
