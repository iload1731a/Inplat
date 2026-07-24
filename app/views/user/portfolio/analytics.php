<?php declare(strict_types=1); ?>
<?php
$bestPositions   = is_array($bestPositions   ?? null) ? $bestPositions   : [];
$worstPositions  = is_array($worstPositions  ?? null) ? $worstPositions  : [];
$monthlySummary  = is_array($monthlySummary  ?? null) ? $monthlySummary  : [];
$activityHeatmap = is_array($activityHeatmap ?? null) ? $activityHeatmap : [];
$streakStats     = is_array($streakStats     ?? null) ? $streakStats     : [];
$positionPnl     = is_array($positionPnl     ?? null) ? $positionPnl     : [];
$tradingSummary  = is_array($tradingSummary  ?? null) ? $tradingSummary  : [];
$winRate         = (float)($winRate          ?? 0);
$profitFactor    = (float)($profitFactor     ?? 0);

$monthLabels = json_encode(array_column($monthlySummary, 'month'));
$monthVols   = json_encode(array_map('floatval', array_column($monthlySummary, 'volume')));
$monthFees   = json_encode(array_map('floatval', array_column($monthlySummary, 'fees')));
require app_path('app/views/user/_nav.php');
?>

<!-- Performance KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        [$winRate . '%',                                       'Win Rate',           'warning', 'fa-trophy'],
        [$profitFactor,                                        'Profit Factor',      'success', 'fa-times-circle'],
        [(int)($streakStats['max_win_streak']  ?? 0),          'Max Win Streak',     'success', 'fa-fire'],
        [(int)($streakStats['max_loss_streak'] ?? 0),          'Max Loss Streak',    'danger',  'fa-skull'],
        [(int)($positionPnl['winning_trades']  ?? 0),          'Winning Trades',     'success', 'fa-check-circle'],
        [(int)($positionPnl['losing_trades']   ?? 0),          'Losing Trades',      'danger',  'fa-times-circle'],
        [number_format((float)($tradingSummary['total_volume'] ?? 0), 2), 'Total Volume', 'info', 'fa-chart-bar'],
        [number_format((float)($tradingSummary['total_fees']   ?? 0), 6), 'Total Fees',   'secondary', 'fa-tag'],
    ];
    foreach ($kpis as [$val, $label, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-xl text-<?= $color ?> mb-2 d-block"></i>
            <div class="h5 fw-bold"><?= e((string)$val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Monthly Summary Chart -->
<?php if (!empty($monthlySummary)): ?>
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-calendar-alt me-2 text-info"></i>Monthly Trading Summary</h5>
    <div id="monthlyChart"></div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Best Positions -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-star me-2 text-success"></i>Best Trades</h5>
            <?php if (!empty($bestPositions)): ?>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>Pair</th><th>Side</th><th>Leverage</th><th>Realized PnL</th><th>Closed</th></tr></thead>
                    <tbody>
                    <?php foreach ($bestPositions as $pos): ?>
                        <?php $pnl = (float)($pos['realized_pnl'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold small"><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                            <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                            <td><span class="badge bg-secondary"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                            <td class="font-monospace fw-semibold text-success small">+<?= number_format($pnl, 4) ?></td>
                            <td class="small"><?= ($pos['closed_at'] ?? null) ? e(date('M d', strtotime((string)$pos['closed_at']))) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-secondary small mb-0">No closed positions yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Worst Positions -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-skull me-2 text-danger"></i>Worst Trades</h5>
            <?php if (!empty($worstPositions)): ?>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>Pair</th><th>Side</th><th>Leverage</th><th>Realized PnL</th><th>Closed</th></tr></thead>
                    <tbody>
                    <?php foreach ($worstPositions as $pos): ?>
                        <?php $pnl = (float)($pos['realized_pnl'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold small"><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                            <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                            <td><span class="badge bg-secondary"><?= number_format((float)($pos['leverage'] ?? 1), 0) ?>x</span></td>
                            <td class="font-monospace fw-semibold text-danger small"><?= number_format($pnl, 4) ?></td>
                            <td class="small"><?= ($pos['closed_at'] ?? null) ? e(date('M d', strtotime((string)$pos['closed_at']))) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-secondary small mb-0">No closed positions yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="glass rounded-4 p-4">
    <div class="row g-2">
        <div class="col-6 col-md-3"><a href="/user/portfolio" class="btn btn-outline-info btn-sm w-100"><i class="fas fa-th me-1"></i>Portfolio Overview</a></div>
        <div class="col-6 col-md-3"><a href="/user/positions" class="btn btn-outline-warning btn-sm w-100"><i class="fas fa-layer-group me-1"></i>Positions</a></div>
        <div class="col-6 col-md-3"><a href="/user/trades" class="btn btn-outline-success btn-sm w-100"><i class="fas fa-receipt me-1"></i>Trades</a></div>
        <div class="col-6 col-md-3"><a href="/user/orders" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-list me-1"></i>Orders</a></div>
    </div>
</div>

<script>
const monthLabels = <?= $monthLabels ?: '[]' ?>;
const monthVols   = <?= $monthVols ?: '[]' ?>;
const monthFees   = <?= $monthFees ?: '[]' ?>;

if (monthLabels.length > 0) {
    new ApexCharts(document.getElementById('monthlyChart'), {
        series: [
            { name: 'Volume', type: 'bar',  data: monthVols },
            { name: 'Fees',   type: 'line', data: monthFees },
        ],
        chart: { height: 250, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: monthLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: [
            { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0) } },
            { opposite: true, labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(2) } }
        ],
        theme: { mode: 'dark' },
        colors: ['#38bdf8', '#f59e0b'],
        plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
        stroke: { width: [0, 2] },
        legend: { labels: { colors: '#94a3b8' } },
    }).render();
}
</script>
