<?php declare(strict_types=1); ?>
<?php
$openPositions   = is_array($openPositions   ?? null) ? $openPositions   : [];
$closedPositions = is_array($closedPositions ?? null) ? $closedPositions : [];
$stats           = is_array($stats           ?? null) ? $stats           : [];
$pnlSeries       = is_array($pnlSeries       ?? null) ? $pnlSeries       : [];
$pnlByPair       = is_array($pnlByPair       ?? null) ? $pnlByPair       : [];
$monthlyPnl      = is_array($monthlyPnl      ?? null) ? $monthlyPnl      : [];
$liquidations    = is_array($liquidations    ?? null) ? $liquidations    : [];
$winRate         = (float)($winRate          ?? 0);
$csrf            = \App\Libraries\Csrf::token();

$totalClosed = (int)($stats['closed_positions'] ?? 0) + (int)($stats['liquidated_positions'] ?? 0);

$pnlLabels    = json_encode(array_column($pnlSeries, 'day'));
$pnlValues    = json_encode(array_map('floatval', array_column($pnlSeries, 'pnl')));
$pairLabels   = json_encode(array_column($pnlByPair, 'symbol'));
$pairPnl      = json_encode(array_map('floatval', array_column($pnlByPair, 'total_pnl')));
$monthLabels  = json_encode(array_column($monthlyPnl, 'month'));
$monthPnl     = json_encode(array_map('floatval', array_column($monthlyPnl, 'pnl')));
require app_path('app/views/user/_nav.php');
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php
    $totalRpnl   = (float)($stats['total_realized_pnl']   ?? 0);
    $totalUpnl   = (float)($stats['total_unrealized_pnl'] ?? 0);
    $bestPnl     = (float)($stats['best_trade_pnl']       ?? 0);
    $worstPnl    = (float)($stats['worst_trade_pnl']      ?? 0);
    $cards = [
        [(int)($stats['open_positions']  ?? 0), 'Open Positions',   'info',    'fa-layer-group'],
        [$winRate . '%',                          'Win Rate',          'warning', 'fa-trophy'],
        [number_format($totalRpnl, 4),            'Realized PnL',      $totalRpnl >= 0 ? 'success' : 'danger', 'fa-coins'],
        [number_format($totalUpnl, 4),            'Unrealized PnL',    $totalUpnl >= 0 ? 'success' : 'danger', 'fa-chart-line'],
        [(int)($stats['winning_positions'] ?? 0), 'Winning Trades',    'success', 'fa-check-circle'],
        [(int)($stats['losing_positions']  ?? 0), 'Losing Trades',     'danger',  'fa-times-circle'],
        [number_format($bestPnl, 4),              'Best Trade PnL',    'success', 'fa-star'],
        [(int)($stats['liquidated_positions'] ?? 0), 'Liquidations',   'danger',  'fa-fire'],
    ];
    foreach ($cards as [$val, $label, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-xl text-<?= $color ?> mb-2 d-block"></i>
            <div class="h5 fw-bold <?= in_array($label, ['Realized PnL','Unrealized PnL','Best Trade PnL']) ? 'text-' . $color : '' ?>"><?= e((string)$val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- PnL Over Time -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-success"></i>Realized PnL (30 days)</h5>
            <div id="pnlChart"></div>
        </div>
    </div>
    <!-- PnL by Pair -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>PnL by Pair</h5>
            <div id="pairPnlChart"></div>
        </div>
    </div>
</div>

<!-- Monthly PnL -->
<?php if (!empty($monthlyPnl)): ?>
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-calendar me-2 text-info"></i>Monthly PnL</h5>
    <div id="monthlyPnlChart"></div>
</div>
<?php endif; ?>

<!-- Open Positions -->
<div class="glass rounded-4 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-layer-group me-2 text-info"></i>Open Positions
            <span class="badge bg-info ms-1"><?= count($openPositions) ?></span>
        </h5>
        <div class="d-flex gap-2">
            <a href="/user/positions/history" class="btn btn-sm btn-outline-secondary"><i class="fas fa-history me-1"></i>History</a>
            <a href="/user/positions/analytics" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-mixed me-1"></i>Analytics</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-user table-sm">
            <thead>
                <tr><th>Pair</th><th>Side</th><th>Entry Price</th><th>Qty</th><th>Leverage</th><th>Liq. Price</th><th>Margin</th><th>Unreal. PnL</th><th>Opened</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($openPositions as $pos): ?>
                <?php $upnl = (float)($pos['unrealized_pnl'] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($pos['pair_symbol'] ?? '-')) ?><br><span class="badge bg-secondary small"><?= e((string)($pos['market_type'] ?? '')) ?></span></td>
                    <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                    <td><span class="badge bg-secondary"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                    <td class="font-monospace small text-warning"><?= ($pos['liquidation_price'] ?? null) ? number_format((float)$pos['liquidation_price'], 6) : '-' ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['margin_used'] ?? 0), 4) ?></td>
                    <td class="font-monospace fw-semibold <?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 4) ?>
                    </td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($pos['opened_at'] ?? 'now')))) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-xs btn-outline-warning btn-add-margin"
                                data-pos-id="<?= (int)$pos['id'] ?>">+Margin</button>
                            <button class="btn btn-xs btn-outline-danger btn-close-position"
                                data-position-id="<?= (int)$pos['id'] ?>">Close</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($openPositions === []): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">
                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>No open positions
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Closed Positions -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-history me-2 text-secondary"></i>Recent Closed Positions</h5>
    <div class="table-responsive">
        <table id="closedPositionsTable" class="table table-user table-sm">
            <thead>
                <tr><th>Pair</th><th>Side</th><th>Status</th><th>Entry</th><th>Qty</th><th>Leverage</th><th>Realized PnL</th><th>Opened</th><th>Closed</th></tr>
            </thead>
            <tbody>
            <?php foreach ($closedPositions as $pos): ?>
                <?php $rpnl = (float)($pos['realized_pnl'] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                    <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                    <td><?php
                        $sc = match($pos['status'] ?? '') { 'closed' => 'secondary', 'liquidated' => 'danger', default => 'warning' };
                    ?><span class="badge bg-<?= $sc ?>"><?= e((string)($pos['status'] ?? '-')) ?></span></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                    <td><span class="badge bg-secondary"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                    <td class="font-monospace fw-semibold <?= $rpnl >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($rpnl >= 0 ? '+' : '') . number_format($rpnl, 4) ?>
                    </td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($pos['opened_at'] ?? 'now')))) ?></td>
                    <td class="small"><?= ($pos['closed_at'] ?? null) ? e(date('M d H:i', strtotime((string)$pos['closed_at']))) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Liquidation History -->
<?php if (!empty($liquidations)): ?>
<div class="glass rounded-4 p-4">
    <h5 class="mb-3"><i class="fas fa-fire me-2 text-danger"></i>Liquidation History</h5>
    <div class="table-responsive">
        <table class="table table-user table-sm">
            <thead>
                <tr><th>Pair</th><th>Liq. Price</th><th>Qty Liquidated</th><th>Loss Amount</th><th>Insurance Covered</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($liquidations as $liq): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($liq['pair_symbol'] ?? '-')) ?></td>
                    <td class="font-monospace small text-danger"><?= number_format((float)($liq['liquidation_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($liq['quantity_liquidated'] ?? 0), 6) ?></td>
                    <td class="font-monospace small text-danger"><?= number_format((float)($liq['loss_amount'] ?? 0), 4) ?></td>
                    <td class="font-monospace small text-info"><?= number_format((float)($liq['insurance_fund_covered'] ?? 0), 4) ?></td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($liq['created_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Add Margin Modal -->
<div class="modal fade" id="addMarginModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add Margin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small">Amount to Add</label>
                <input type="number" class="form-control" id="marginAmount" min="0.01" step="0.01" placeholder="0.00">
                <div class="form-text text-secondary">Adding margin reduces liquidation risk.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="btnConfirmMargin">Add Margin</button>
            </div>
        </div>
    </div>
</div>

<script>
$('#closedPositionsTable').DataTable({ order: [[8,'desc']], pageLength: 20 });

// Charts
const pnlLabels   = <?= $pnlLabels ?: '[]' ?>;
const pnlValues   = <?= $pnlValues ?: '[]' ?>;
const pairLabels  = <?= $pairLabels ?: '[]' ?>;
const pairPnl     = <?= $pairPnl ?: '[]' ?>;
const monthLabels = <?= $monthLabels ?: '[]' ?>;
const monthPnl    = <?= $monthPnl ?: '[]' ?>;

if (pnlLabels.length > 0) {
    new ApexCharts(document.getElementById('pnlChart'), {
        series: [{ name: 'PnL', data: pnlValues }],
        chart: { type: 'area', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: pnlLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(2) } },
        theme: { mode: 'dark' },
        colors: ['#34d399'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

if (pairLabels.length > 0) {
    const colors = pairPnl.map(v => v >= 0 ? '#34d399' : '#ef4444');
    new ApexCharts(document.getElementById('pairPnlChart'), {
        series: [{ name: 'PnL', data: pairPnl }],
        chart: { type: 'bar', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: pairLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(2) } },
        theme: { mode: 'dark' },
        colors: colors,
        plotOptions: { bar: { borderRadius: 3, distributed: true } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
        legend: { show: false },
    }).render();
}

if (monthLabels.length > 0) {
    const mColors = monthPnl.map(v => v >= 0 ? '#34d399' : '#ef4444');
    new ApexCharts(document.getElementById('monthlyPnlChart'), {
        series: [{ name: 'Monthly PnL', data: monthPnl }],
        chart: { type: 'bar', height: 160, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: monthLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(2) } },
        theme: { mode: 'dark' },
        colors: mColors,
        plotOptions: { bar: { borderRadius: 3, distributed: true } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
        legend: { show: false },
    }).render();
}

// Close Position
$(document).on('click', '.btn-close-position', function () {
    const posId = $(this).data('position-id');
    Swal.fire({
        title: 'Close Position #' + posId + '?',
        text: 'This will close your position at market price.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Close Position'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/user/positions/close', { _token: csrfToken, position_id: posId }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Closed', timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', text: res.message });
            }
        }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
    });
});

// Add Margin
let currentPosId = null;
$(document).on('click', '.btn-add-margin', function () {
    currentPosId = $(this).data('pos-id');
    $('#marginAmount').val('');
    new bootstrap.Modal(document.getElementById('addMarginModal')).show();
});

$('#btnConfirmMargin').on('click', function () {
    const amount = parseFloat($('#marginAmount').val());
    if (!amount || amount <= 0) { Swal.fire({ icon: 'warning', text: 'Enter a valid amount.' }); return; }
    $.post('/user/positions/add-margin', { _token: csrfToken, position_id: currentPosId, amount: amount }, res => {
        bootstrap.Modal.getInstance(document.getElementById('addMarginModal')).hide();
        if (res.ok) {
            Swal.fire({ icon: 'success', title: 'Margin Added', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', text: res.message });
        }
    }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
});
</script>
