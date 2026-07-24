<?php declare(strict_types=1); ?>
<?php
$positions = is_array($positions ?? null) ? $positions : [];
$posStats  = is_array($posStats  ?? null) ? $posStats  : [];
$filters   = is_array($filters   ?? null) ? $filters   : [];
$csrf      = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Positions Management</h1>
        <p class="text-secondary mb-0">Monitor all open and closed positions across the platform.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/positions/risk" class="btn btn-sm btn-outline-danger"><i class="fas fa-exclamation-triangle me-1"></i>Risk</a>
        <a href="/admin/positions/export?<?= http_build_query($filters) ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>Export CSV</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $sCards = [
        ['Total',       (int)($posStats['total_positions']     ?? 0), 'info'],
        ['Open',        (int)($posStats['open_positions']      ?? 0), 'warning'],
        ['Closed',      (int)($posStats['closed_positions']    ?? 0), 'success'],
        ['Liquidated',  (int)($posStats['liquidated_positions']?? 0), 'danger'],
        ['Unreal. PnL', number_format((float)($posStats['total_unrealized_pnl'] ?? 0), 2), 'info'],
        ['Total Margin',number_format((float)($posStats['total_margin'] ?? 0), 2),          'secondary'],
        ['Today',       (int)($posStats['opened_today']        ?? 0), 'primary'],
    ];
    foreach ($sCards as [$label, $val, $color]):
    ?>
    <div class="col-6 col-md-2">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($label) ?></div>
            <div class="h5 mb-0 text-<?= $color ?>"><?= is_int($val) ? number_format($val) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/positions">
        <div class="col-lg-2"><input class="form-control" type="text" name="search" placeholder="Search user, symbol" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <option value="open"       <?= ($filters['status'] ?? '') === 'open'       ? 'selected' : '' ?>>Open</option>
                <option value="closed"     <?= ($filters['status'] ?? '') === 'closed'     ? 'selected' : '' ?>>Closed</option>
                <option value="liquidated" <?= ($filters['status'] ?? '') === 'liquidated' ? 'selected' : '' ?>>Liquidated</option>
            </select>
        </div>
        <div class="col-lg-1">
            <select class="form-select" name="side">
                <option value="">All Sides</option>
                <option value="long"  <?= ($filters['side'] ?? '') === 'long'  ? 'selected' : '' ?>>Long</option>
                <option value="short" <?= ($filters['side'] ?? '') === 'short' ? 'selected' : '' ?>>Short</option>
            </select>
        </div>
        <div class="col-lg-2"><input class="form-control" type="text" name="symbol" placeholder="Symbol" value="<?= e((string)($filters['symbol'] ?? '')) ?>"></div>
        <div class="col-lg-1"><input class="form-control" type="number" name="user_id" placeholder="User ID" value="<?= (int)($filters['user_id'] ?? 0) ?: '' ?>"></div>
        <div class="col-lg-2">
            <input class="form-control" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-lg-1">
            <input class="form-control" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-primary w-100" type="submit">Go</button>
            <a class="btn btn-outline-light" href="/admin/positions">⟳</a>
        </div>
    </form>
</div>

<!-- Positions Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>ID</th><th>Symbol</th><th>User</th><th>Side</th><th>Status</th><th>Entry</th><th>Qty</th><th>Leverage</th><th>Liq. Price</th><th>Margin</th><th>Unreal. PnL</th><th>Opened</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($positions as $pos): ?>
                <?php
                $sc = match($pos['status'] ?? '') { 'open' => 'info', 'closed' => 'secondary', 'liquidated' => 'danger', default => 'warning' };
                $upnl = (float)($pos['unrealized_pnl'] ?? 0);
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($pos['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($pos['symbol'] ?? '-')) ?><br><span class="badge bg-secondary small"><?= e((string)($pos['market_type'] ?? '')) ?></span></td>
                    <td>
                        <div class="small fw-semibold"><?= e((string)($pos['username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($pos['email'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge text-bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                    <td><span class="badge text-bg-<?= $sc ?>"><?= e((string)($pos['status'] ?? '-')) ?></span></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['quantity'] ?? 0), 8) ?></td>
                    <td><span class="badge bg-secondary"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                    <td class="font-monospace small text-warning"><?= ($pos['liquidation_price'] ?? null) ? number_format((float)$pos['liquidation_price'], 6) : '-' ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['margin_used'] ?? 0), 4) ?></td>
                    <td class="font-monospace fw-semibold <?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 4) ?>
                    </td>
                    <td class="small text-secondary"><?= e((string)($pos['opened_at'] ?? '-')) ?></td>
                    <td class="text-end">
                        <?php if (($pos['status'] ?? '') === 'open'): ?>
                            <button class="btn btn-xs btn-outline-danger"
                                onclick="forceClosePos(<?= (int)$pos['id'] ?>)">Force Close</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($positions === []): ?>
                <tr><td colspan="13" class="text-center text-secondary">No positions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function forceClosePos(posId) {
    Swal.fire({
        title: 'Force Close Position #' + posId + '?',
        text: 'This will immediately close the position.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Force Close'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/admin/positions/force-close', {
            _token: '<?= e($csrf) ?>',
            position_id: posId
        }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Closed', timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', text: res.message });
            }
        }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
    });
}
</script>
