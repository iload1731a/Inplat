<?php declare(strict_types=1); ?>
<?php
$posStats     = is_array($posStats     ?? null) ? $posStats     : [];
$riskExposure = is_array($riskExposure ?? null) ? $riskExposure : [];
$atRisk       = is_array($atRisk       ?? null) ? $atRisk       : [];
$liquidations = is_array($liquidations ?? null) ? $liquidations : [];
$csrf         = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Risk & Exposure</h1>
        <p class="text-secondary mb-0">Live open position risk and liquidation monitoring.</p>
    </div>
    <a href="/admin/positions" class="btn btn-sm btn-outline-secondary">← All Positions</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Risk KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        [(int)($posStats['open_positions']   ?? 0), 'Open Positions',    'warning'],
        [(int)($posStats['liquidated_positions'] ?? 0), 'Liquidated',    'danger'],
        [number_format((float)($posStats['total_unrealized_pnl'] ?? 0), 2), 'Total Unreal. PnL', (float)($posStats['total_unrealized_pnl'] ?? 0) >= 0 ? 'success' : 'danger'],
        [number_format((float)($posStats['total_margin'] ?? 0), 2), 'Total Margin Locked', 'secondary'],
    ];
    foreach ($kpis as [$val, $label, $color]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($label) ?></div>
            <div class="h4 mb-0 text-<?= $color ?>"><?= is_int($val) ? number_format($val) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Risk Exposure by Pair -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Risk Exposure by Pair</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>Symbol</th><th>Side</th><th>Positions</th><th>Total Margin</th><th>Notional Value</th><th>Unrealized PnL</th></tr>
            </thead>
            <tbody>
            <?php foreach ($riskExposure as $exp): ?>
                <?php $upnl = (float)($exp['total_unrealized_pnl'] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($exp['symbol'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= ($exp['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($exp['side'] ?? '-'))) ?></span></td>
                    <td><?= number_format((int)($exp['position_count'] ?? 0)) ?></td>
                    <td class="font-monospace"><?= number_format((float)($exp['total_margin'] ?? 0), 4) ?></td>
                    <td class="font-monospace text-info"><?= number_format((float)($exp['notional_value'] ?? 0), 2) ?></td>
                    <td class="font-monospace fw-semibold <?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 4) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($riskExposure === []): ?>
                <tr><td colspan="6" class="text-center text-secondary">No open positions.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- At-Risk Positions -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-fire me-2 text-danger"></i>Positions Near Liquidation</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>ID</th><th>User</th><th>Symbol</th><th>Side</th><th>Leverage</th><th>Entry Price</th><th>Liq. Price</th><th>Margin</th><th>Unreal. PnL</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($atRisk as $pos): ?>
                <?php $upnl = (float)($pos['unrealized_pnl'] ?? 0); ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($pos['id'] ?? 0) ?></td>
                    <td class="small">
                        <div class="fw-semibold"><?= e((string)($pos['username'] ?? '-')) ?></div>
                        <div class="text-secondary"><?= e((string)($pos['email'] ?? '')) ?></div>
                    </td>
                    <td class="fw-semibold"><?= e((string)($pos['symbol'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                    <td><span class="badge bg-<?= (int)($pos['leverage'] ?? 1) >= 10 ? 'danger' : 'secondary' ?>"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small text-warning"><?= ($pos['liquidation_price'] ?? null) ? number_format((float)$pos['liquidation_price'], 6) : '-' ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['margin_used'] ?? 0), 4) ?></td>
                    <td class="font-monospace fw-semibold <?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 4) ?>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-outline-danger"
                            onclick="forceClosePos(<?= (int)$pos['id'] ?>)">Force Close</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($atRisk === []): ?>
                <tr><td colspan="10" class="text-center text-secondary">No at-risk positions.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Liquidation History -->
<?php if (!empty($liquidations)): ?>
<div class="glass rounded-4 p-4">
    <h5 class="mb-3"><i class="fas fa-skull me-2 text-danger"></i>Recent Liquidations</h5>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Symbol</th><th>User</th><th>Liq. Price</th><th>Qty</th><th>Loss</th><th>Insurance</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($liquidations as $liq): ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($liq['id'] ?? 0) ?></td>
                    <td class="fw-semibold small"><?= e((string)($liq['symbol'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($liq['username'] ?? '-')) ?></td>
                    <td class="font-monospace small text-danger"><?= number_format((float)($liq['liquidation_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($liq['quantity_liquidated'] ?? 0), 6) ?></td>
                    <td class="font-monospace small text-danger"><?= number_format((float)($liq['loss_amount'] ?? 0), 4) ?></td>
                    <td class="font-monospace small text-info"><?= number_format((float)($liq['insurance_fund_covered'] ?? 0), 4) ?></td>
                    <td class="small text-secondary"><?= e((string)($liq['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function forceClosePos(posId) {
    Swal.fire({
        title: 'Force Close Position #' + posId + '?',
        text: 'This will immediately close the position at market price.',
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
