<?php declare(strict_types=1); ?>
<?php
$stats        = is_array($stats        ?? null) ? $stats        : [];
$pnlSeries    = is_array($pnlSeries    ?? null) ? $pnlSeries    : [];
$pnlByPair    = is_array($pnlByPair    ?? null) ? $pnlByPair    : [];
$monthlyPnl   = is_array($monthlyPnl   ?? null) ? $monthlyPnl   : [];
$liquidations = is_array($liquidations ?? null) ? $liquidations : [];
$winRate      = (float)($winRate       ?? 0);

$totalClosed  = (int)($stats['closed_positions'] ?? 0) + (int)($stats['liquidated_positions'] ?? 0);
$winRateRaw   = $winRate;
$lossRate     = $totalClosed > 0 ? round(100 - $winRateRaw, 1) : 0;

$pnlLabels   = json_encode(array_column($pnlSeries, 'day'));
$pnlValues   = json_encode(array_map('floatval', array_column($pnlSeries, 'pnl')));
$pairLabels  = json_encode(array_column($pnlByPair, 'symbol'));
$pairTotals  = json_encode(array_map('floatval', array_column($pnlByPair, 'total_pnl')));
$monthLabels = json_encode(array_column($monthlyPnl, 'month'));
$monthPnlArr = json_encode(array_map('floatval', array_column($monthlyPnl, 'pnl')));
$monthWins   = json_encode(array_map('intval', array_column($monthlyPnl, 'wins')));
require app_path('app/views/user/_nav.php');
?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $rpnl = (float)($stats['total_realized_pnl'] ?? 0);
    $upnl = (float)($stats['total_unrealized_pnl'] ?? 0);
    $best = (float)($stats['best_trade_pnl'] ?? 0);
    $worst = (float)($stats['worst_trade_pnl'] ?? 0);
    $cards = [
        [$winRateRaw . '%',      'Win Rate',           'warning', 'fa-trophy'],
        [number_format($rpnl, 4), 'Realized PnL',      $rpnl >= 0 ? 'success' : 'danger',  'fa-coins'],
        [number_format($upnl, 4), 'Unrealized PnL',    $upnl >= 0 ? 'success' : 'danger',  'fa-chart-line'],
        [number_format($best, 4), 'Best Trade',         'success', 'fa-star'],
        [number_format($worst, 4), 'Worst Trade',       'danger',  'fa-skull'],
        [(int)($stats['winning_positions'] ?? 0), 'Winning', 'success', 'fa-check'],
        [(int)($stats['losing_positions'] ?? 0),  'Losing',  'danger',  'fa-times'],
        [(int)($stats['liquidated_positions'] ?? 0), 'Liquidated', 'danger', 'fa-fire'],
    ];
    foreach ($cards as [$val, $label, $color, $icon]):
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

<div class="row g-4 mb-4">
    <!-- PnL Line Chart -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-success"></i>Daily Realized PnL (30 days)</h5>
            <div id="pnlChart"></div>
        </div>
    </div>
    <!-- Win/Loss Donut -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Win / Loss Ratio</h5>
            <div id="winLossChart"></div>
            <div class="row g-2 mt-2 text-center">
                <div class="col-6">
                    <div class="small text-success fw-semibold"><?= $winRateRaw ?>%</div>
                    <div class="small text-secondary">Win Rate</div>
                </div>
                <div class="col-6">
                    <div class="small text-danger fw-semibold"><?= $lossRate ?>%</div>
                    <div class="small text-secondary">Loss Rate</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Monthly PnL Bar -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-calendar-alt me-2 text-info"></i>Monthly PnL</h5>
            <div id="monthlyPnlChart"></div>
        </div>
    </div>
    <!-- PnL by Pair -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>PnL by Pair</h5>
            <?php if (!empty($pnlByPair)): ?>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>Pair</th><th class="text-end">Positions</th><th class="text-end">Wins</th><th class="text-end">Losses</th><th class="text-end">Total PnL</th></tr></thead>
                    <tbody>
                    <?php foreach ($pnlByPair as $pair): ?>
                        <?php $pnl = (float)($pair['total_pnl'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold small"><?= e((string)($pair['symbol'] ?? '-')) ?></td>
                            <td class="text-end small"><?= (int)($pair['position_count'] ?? 0) ?></td>
                            <td class="text-end small text-success"><?= (int)($pair['wins'] ?? 0) ?></td>
                            <td class="text-end small text-danger"><?= (int)($pair['losses'] ?? 0) ?></td>
                            <td class="text-end font-monospace small fw-semibold <?= $pnl >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($pnl >= 0 ? '+' : '') . number_format($pnl, 4) ?>
                            </td>
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

<script>
const pnlLabels   = <?= $pnlLabels ?: '[]' ?>;
const pnlValues   = <?= $pnlValues ?: '[]' ?>;
const monthLabels = <?= $monthLabels ?: '[]' ?>;
const monthPnl    = <?= $monthPnlArr ?: '[]' ?>;
const winRate     = <?= $winRateRaw ?>;
const lossRate    = <?= $lossRate ?>;

if (pnlLabels.length > 0) {
    new ApexCharts(document.getElementById('pnlChart'), {
        series: [{ name: 'Realized PnL', data: pnlValues }],
        chart: { type: 'area', height: 250, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: pnlLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(2) } },
        theme: { mode: 'dark' },
        colors: ['#34d399'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

if (winRate > 0 || lossRate > 0) {
    new ApexCharts(document.getElementById('winLossChart'), {
        series: [winRate, lossRate],
        chart: { type: 'donut', height: 200, background: 'transparent' },
        labels: ['Wins', 'Losses'],
        colors: ['#34d399', '#ef4444'],
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        plotOptions: { pie: { donut: { size: '70%' } } },
    }).render();
}

if (monthLabels.length > 0) {
    const mColors = monthPnl.map(v => v >= 0 ? '#34d399' : '#ef4444');
    new ApexCharts(document.getElementById('monthlyPnlChart'), {
        series: [{ name: 'Monthly PnL', data: monthPnl }],
        chart: { type: 'bar', height: 200, background: 'transparent', toolbar: { show: false } },
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
</script>
