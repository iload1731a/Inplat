<?php declare(strict_types=1); ?>
<?php
$candidates   = is_array($candidates   ?? null) ? $candidates   : [];
$engineStats  = is_array($engineStats  ?? null) ? $engineStats  : [];
$allPositions = is_array($allPositions ?? null) ? $allPositions : [];
$csrfToken    = (string)($csrfToken    ?? \App\Libraries\Csrf::token());
?>
<?php include __DIR__ . '/../_nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-shield-alt me-2 text-warning"></i>Risk Monitor</h1>
        <p class="text-secondary mb-0">Positions near liquidation threshold and active risk management tools.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/trading-engine" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Engine Dashboard</a>
        <a href="/admin/trading-engine/positions" class="btn btn-outline-info btn-sm"><i class="fas fa-list me-1"></i>All Positions</a>
    </div>
</div>

<!-- Risk KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="glass rounded-4 p-3 border border-danger border-opacity-25">
            <div class="text-secondary small mb-1">Liquidation Candidates</div>
            <div class="h4 fw-bold text-danger"><?= count($candidates) ?></div>
            <small class="text-secondary">Within 5% of liquidation price</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary small mb-1">Total Open Positions</div>
            <div class="h4 fw-bold"><?= number_format((int)($engineStats['open_positions'] ?? 0)) ?></div>
            <small class="text-secondary">All market types</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary small mb-1">Insurance Fund</div>
            <div class="h4 fw-bold text-info"><?= number_format((float)($engineStats['insurance_fund'] ?? 0), 4) ?></div>
            <small class="text-secondary">Available to cover losses</small>
        </div>
    </div>
</div>

<!-- Liquidation Candidates -->
<?php if (!empty($candidates)): ?>
<div class="glass rounded-4 p-4 mb-4">
    <h2 class="h6 mb-3"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Liquidation Candidates</h2>
    <div class="alert alert-warning py-2 mb-3">
        <i class="fas fa-info-circle me-1"></i>
        These positions are within 5% of their liquidation price. Use Force Liquidate to close a position immediately.
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover">
            <thead>
                <tr>
                    <th>ID</th><th>User</th><th>Pair</th><th>Side</th><th>Entry Price</th>
                    <th>Current Price</th><th>Liq. Price</th><th>Leverage</th><th>Margin</th>
                    <th>Unreal. PnL</th><th>Opened</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $pos):
                $upnl = (float)($pos['unrealized_pnl'] ?? 0);
            ?>
                <tr class="table-danger">
                    <td><?= (int)($pos['id'] ?? 0) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e((string)($pos['username'] ?? '-')) ?></div>
                        <small class="text-secondary"><?= e((string)($pos['email'] ?? '-')) ?></small>
                    </td>
                    <td class="fw-semibold"><?= e((string)($pos['symbol'] ?? '-')) ?></td>
                    <td class="<?= ($pos['position_side'] ?? '') === 'long' ? 'text-success' : 'text-danger' ?> fw-bold">
                        <?= e(strtoupper((string)($pos['position_side'] ?? ''))) ?>
                    </td>
                    <td><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td><?= $pos['current_price'] ? number_format((float)$pos['current_price'], 6) : '—' ?></td>
                    <td class="text-danger fw-bold"><?= $pos['liquidation_price'] ? number_format((float)$pos['liquidation_price'], 6) : '—' ?></td>
                    <td><?= number_format((float)($pos['leverage'] ?? 1), 2) ?>x</td>
                    <td><?= number_format((float)($pos['margin_used'] ?? 0), 4) ?></td>
                    <td class="<?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 6) ?></td>
                    <td class="text-secondary small"><?= e((string)($pos['opened_at'] ?? '-')) ?></td>
                    <td>
                        <button class="btn btn-sm btn-danger"
                            onclick="showLiquidateModal(<?= (int)($pos['id'] ?? 0) ?>, '<?= e((string)($pos['symbol'] ?? '')) ?>', <?= number_format((float)($pos['liquidation_price'] ?? 0), 6, '.', '') ?>)">
                            <i class="fas fa-bolt me-1"></i>Force Liquidate
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="glass rounded-4 p-4 mb-4">
    <div class="text-center py-4">
        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
        <h5 class="text-success">No Liquidation Risk</h5>
        <p class="text-secondary mb-0">All positions are at safe margin levels.</p>
    </div>
</div>
<?php endif; ?>

<!-- All Open Positions -->
<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><i class="fas fa-table me-2"></i>All Open Positions</h2>
        <span class="badge text-bg-secondary"><?= count($allPositions) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-sm" id="allPositionsTable">
            <thead>
                <tr><th>ID</th><th>User</th><th>Pair</th><th>Side</th><th>Entry</th><th>Current</th><th>Qty</th><th>Leverage</th><th>Liq. Price</th><th>Unreal. PnL</th><th>Opened</th></tr>
            </thead>
            <tbody>
            <?php foreach ($allPositions as $pos):
                $upnl = (float)($pos['unrealized_pnl'] ?? 0);
            ?>
                <tr>
                    <td><?= (int)($pos['id'] ?? 0) ?></td>
                    <td><?= e((string)($pos['username'] ?? '-')) ?></td>
                    <td class="fw-semibold"><?= e((string)($pos['symbol'] ?? '-')) ?></td>
                    <td class="<?= ($pos['position_side'] ?? '') === 'long' ? 'text-success' : 'text-danger' ?>"><?= e(strtoupper((string)($pos['position_side'] ?? ''))) ?></td>
                    <td><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($pos['current_price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($pos['leverage'] ?? 1), 2) ?>x</td>
                    <td><?= $pos['liquidation_price'] ? number_format((float)$pos['liquidation_price'], 6) : '—' ?></td>
                    <td class="<?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 6) ?></td>
                    <td class="text-secondary small"><?= e((string)($pos['opened_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($allPositions === []): ?>
                <tr><td colspan="11" class="text-secondary text-center py-3">No open positions</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Force Liquidate Modal -->
<div class="modal fade" id="liquidateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-danger">
            <div class="modal-header border-danger">
                <h5 class="modal-title text-danger"><i class="fas fa-bolt me-2"></i>Force Liquidate Position</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong>Warning:</strong> This will immediately close the position and apply any loss to the insurance fund.
                </div>
                <div class="mb-3">
                    <label class="form-label">Position ID: <strong id="liqPositionId"></strong></label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Symbol: <strong id="liqSymbol"></strong></label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Liquidation Price</label>
                    <input type="number" step="any" min="0" class="form-control bg-dark text-light border-danger" id="liqPrice" placeholder="Enter liquidation price">
                    <div class="form-text text-secondary">Suggested: <span id="liqSuggestedPrice"></span></div>
                </div>
            </div>
            <div class="modal-footer border-danger">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitLiquidate()">
                    <i class="fas fa-bolt me-1"></i>Force Liquidate
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function showLiquidateModal(positionId, symbol, suggestedPrice) {
    document.getElementById('liqPositionId').textContent   = positionId;
    document.getElementById('liqSymbol').textContent       = symbol;
    document.getElementById('liqPrice').value              = suggestedPrice;
    document.getElementById('liqSuggestedPrice').textContent = suggestedPrice;
    document.getElementById('liqPositionId').dataset.pid  = positionId;
    new bootstrap.Modal(document.getElementById('liquidateModal')).show();
}

async function submitLiquidate() {
    const posId = document.getElementById('liqPositionId').dataset.pid;
    const price = document.getElementById('liqPrice').value;

    if (!price || parseFloat(price) <= 0) { alert('Enter a valid liquidation price'); return; }
    if (!confirm('Confirm force liquidation of position #' + posId + ' at ' + price + '?')) return;

    const res = await fetch('/admin/trading-engine/liquidate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ _token: CSRF, position_id: posId, price: price })
    }).then(r => r.json());

    if (res.ok) { alert(res.message || 'Position liquidated'); location.reload(); }
    else alert(res.message || 'Error');
}

// DataTables for all positions
document.addEventListener('DOMContentLoaded', () => {
    if (typeof $.fn !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        $('#allPositionsTable').DataTable({ order: [[0,'desc']], pageLength: 25, responsive: true });
    }
});
</script>
