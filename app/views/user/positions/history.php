<?php declare(strict_types=1); ?>
<?php
$positions = is_array($positions ?? null) ? $positions : [];
$stats     = is_array($stats     ?? null) ? $stats     : [];
$filters   = is_array($filters   ?? null) ? $filters   : [];
require app_path('app/views/user/_nav.php');
?>

<!-- Filter Bar -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/user/positions/history">
        <div class="col-6 col-md-2">
            <input class="form-control form-control-sm" type="text" name="pair" placeholder="Pair" value="<?= e((string)($filters['pair'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">All Status</option>
                <option value="open"       <?= ($filters['status'] ?? '') === 'open'       ? 'selected' : '' ?>>Open</option>
                <option value="closed"     <?= ($filters['status'] ?? '') === 'closed'     ? 'selected' : '' ?>>Closed</option>
                <option value="liquidated" <?= ($filters['status'] ?? '') === 'liquidated' ? 'selected' : '' ?>>Liquidated</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="side">
                <option value="">All Sides</option>
                <option value="long"  <?= ($filters['side'] ?? '') === 'long'  ? 'selected' : '' ?>>Long</option>
                <option value="short" <?= ($filters['side'] ?? '') === 'short' ? 'selected' : '' ?>>Short</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
            <a class="btn btn-sm btn-outline-secondary" href="/user/positions/history">⟳</a>
        </div>
    </form>
</div>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Position History
            <span class="badge bg-secondary ms-1"><?= number_format(count($positions)) ?></span>
        </h5>
        <div class="d-flex gap-2">
            <a href="/user/positions" class="btn btn-sm btn-outline-info"><i class="fas fa-layer-group me-1"></i>Open Positions</a>
            <a href="/user/positions/analytics" class="btn btn-sm btn-outline-warning"><i class="fas fa-chart-mixed me-1"></i>Analytics</a>
        </div>
    </div>
    <div class="table-responsive">
        <table id="posHistoryTable" class="table table-user table-sm">
            <thead>
                <tr><th>Pair</th><th>Side</th><th>Status</th><th>Entry Price</th><th>Qty</th><th>Leverage</th><th>Margin</th><th>Realized PnL</th><th>Opened</th><th>Closed</th></tr>
            </thead>
            <tbody>
            <?php foreach ($positions as $pos): ?>
                <?php $rpnl = (float)($pos['realized_pnl'] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                    <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                    <td><?php
                        $sc = match($pos['status'] ?? '') { 'open' => 'info', 'closed' => 'secondary', 'liquidated' => 'danger', default => 'warning' };
                    ?><span class="badge bg-<?= $sc ?>"><?= e((string)($pos['status'] ?? '-')) ?></span></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                    <td><span class="badge bg-secondary"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['margin_used'] ?? 0), 4) ?></td>
                    <td class="font-monospace fw-semibold <?= $rpnl >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($rpnl >= 0 ? '+' : '') . number_format($rpnl, 4) ?>
                    </td>
                    <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($pos['opened_at'] ?? 'now')))) ?></td>
                    <td class="small"><?= ($pos['closed_at'] ?? null) ? e(date('Y-m-d H:i', strtotime((string)$pos['closed_at']))) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#posHistoryTable').DataTable({
    order: [[8,'desc']],
    pageLength: 25,
    dom: '<"d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2"<"d-flex align-items-center gap-2"lB>f>rtip',
    buttons: ['excel','csv','pdf','print']
});
</script>
