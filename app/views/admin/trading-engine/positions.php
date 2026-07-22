<?php declare(strict_types=1); ?>
<?php
$positions   = is_array($positions   ?? null) ? $positions   : [];
$engineStats = is_array($engineStats ?? null) ? $engineStats : [];
$filters     = is_array($filters     ?? null) ? $filters     : [];
$csrfToken   = (string)($csrfToken   ?? \App\Libraries\Csrf::token());
$statusFilter = (string)($filters['status'] ?? 'open');
$symbolFilter = (string)($filters['symbol'] ?? '');
?>
<?php include __DIR__ . '/../_nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-chart-area me-2 text-info"></i>Positions Overview</h1>
        <p class="text-secondary mb-0">All user positions across spot, margin and futures markets.</p>
    </div>
    <a href="/admin/trading-engine" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Engine Dashboard</a>
</div>

<!-- Filters -->
<form method="get" action="/admin/trading-engine/positions" class="glass rounded-4 p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <?php foreach (['open', 'closed', 'liquidated'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $st === $statusFilter ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Symbol</label>
            <input type="text" name="symbol" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= e($symbolFilter) ?>" placeholder="BTC/USDT">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-info btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
        </div>
        <div class="col-md-2">
            <a href="/admin/trading-engine/positions" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </div>
</form>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary small">Showing</div>
            <div class="h5 fw-bold"><?= count($positions) ?> positions</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary small">Total Open</div>
            <div class="h5 fw-bold"><?= number_format((int)($engineStats['open_positions'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary small">Liq. Candidates</div>
            <div class="h5 fw-bold text-danger"><?= number_format((int)($engineStats['liq_candidates'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary small">Insurance Fund</div>
            <div class="h5 fw-bold text-info"><?= number_format((float)($engineStats['insurance_fund'] ?? 0), 4) ?></div>
        </div>
    </div>
</div>

<!-- Positions Table -->
<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-dark table-hover" id="positionsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Pair</th>
                    <th>Side</th>
                    <th>Entry Price</th>
                    <th>Current Price</th>
                    <th>Qty</th>
                    <th>Leverage</th>
                    <th>Margin Used</th>
                    <th>Liq. Price</th>
                    <th>Unreal. PnL</th>
                    <th>Real. PnL</th>
                    <th>Status</th>
                    <th>Opened</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($positions as $pos):
                $upnl  = (float)($pos['unrealized_pnl'] ?? 0);
                $rpnl  = (float)($pos['realized_pnl']   ?? 0);
                $stat  = (string)($pos['status'] ?? 'open');
                $statBadge = match($stat) {
                    'open'        => 'info',
                    'closed'      => 'success',
                    'liquidated'  => 'danger',
                    default       => 'secondary',
                };
            ?>
                <tr>
                    <td><?= (int)($pos['id'] ?? 0) ?></td>
                    <td>
                        <div><?= e((string)($pos['username'] ?? '-')) ?></div>
                        <small class="text-secondary"><?= e((string)($pos['email'] ?? '-')) ?></small>
                    </td>
                    <td class="fw-semibold"><?= e((string)($pos['symbol'] ?? '-')) ?></td>
                    <td class="<?= ($pos['position_side'] ?? '') === 'long' ? 'text-success' : 'text-danger' ?> fw-bold">
                        <?= e(strtoupper((string)($pos['position_side'] ?? ''))) ?>
                    </td>
                    <td><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($pos['current_price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($pos['leverage'] ?? 1), 2) ?>x</td>
                    <td><?= number_format((float)($pos['margin_used'] ?? 0), 6) ?></td>
                    <td class="text-danger"><?= $pos['liquidation_price'] ? number_format((float)$pos['liquidation_price'], 6) : '—' ?></td>
                    <td class="<?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 6) ?></td>
                    <td class="<?= $rpnl >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($rpnl >= 0 ? '+' : '') . number_format($rpnl, 6) ?></td>
                    <td><span class="badge text-bg-<?= e($statBadge) ?>"><?= e($stat) ?></span></td>
                    <td class="text-secondary small"><?= e((string)($pos['opened_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($positions === []): ?>
                <tr><td colspan="14" class="text-secondary text-center py-4">No positions found matching the filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof $.fn !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        $('#positionsTable').DataTable({ order: [[0,'desc']], pageLength: 25, responsive: true });
    }
});
</script>
