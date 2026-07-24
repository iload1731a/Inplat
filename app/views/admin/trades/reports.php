<?php declare(strict_types=1); ?>
<?php
$stats      = is_array($stats      ?? null) ? $stats      : [];
$dailyVolume = is_array($dailyVolume ?? null) ? $dailyVolume : [];
$byPair     = is_array($byPair     ?? null) ? $byPair     : [];
$topTraders = is_array($topTraders  ?? null) ? $topTraders  : [];
$feeRevenue = is_array($feeRevenue  ?? null) ? $feeRevenue  : [];

$dayLabels  = json_encode(array_column($dailyVolume, 'day'));
$dayVols    = json_encode(array_map('floatval', array_column($dailyVolume, 'volume')));
$dayFees    = json_encode(array_map('floatval', array_column($feeRevenue, 'fees')));
$pairNames  = json_encode(array_column($byPair, 'symbol'));
$pairVols   = json_encode(array_map('floatval', array_column($byPair, 'volume')));
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trade Reports</h1>
        <p class="text-secondary mb-0">Platform-wide trade analytics and fee revenue.</p>
    </div>
    <a href="/admin/trades" class="btn btn-sm btn-outline-secondary">← Back to Trades</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        [number_format((int)($stats['total_trades'] ?? 0)),                'Total Trades',   'info'],
        [number_format((float)($stats['total_volume'] ?? 0), 2),           'Total Volume',   'success'],
        [number_format((float)($stats['total_fees'] ?? 0), 4),             'Total Fees',     'warning'],
        [number_format((float)($stats['avg_trade_value'] ?? 0), 2),        'Avg Trade',      'secondary'],
        [number_format((int)($stats['trades_today'] ?? 0)),                'Trades Today',   'primary'],
        [number_format((float)($stats['volume_today'] ?? 0), 2),           'Volume Today',   'info'],
    ];
    foreach ($kpis as [$val, $label, $color]):
    ?>
    <div class="col-6 col-md-2">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($label) ?></div>
            <div class="h5 mb-0 text-<?= $color ?>"><?= e($val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Daily Volume -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Daily Trading Volume (30 days)</h5>
            <div id="dailyVolumeChart"></div>
        </div>
    </div>
    <!-- Volume by Pair -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Volume by Pair</h5>
            <div id="pairChart"></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Fee Revenue -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-tag me-2 text-yellow"></i>Daily Fee Revenue</h5>
            <div id="feeChart"></div>
        </div>
    </div>
    <!-- Top Traders -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-users me-2 text-success"></i>Top Traders by Volume</h5>
            <?php if (!empty($topTraders)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>User</th><th class="text-end">Trades</th><th class="text-end">Volume</th></tr></thead>
                    <tbody>
                    <?php foreach ($topTraders as $i => $trader): ?>
                        <tr>
                            <td class="text-secondary"><?= $i + 1 ?></td>
                            <td>
                                <div class="small fw-semibold"><?= e((string)($trader['username'] ?? '-')) ?></div>
                                <div class="small text-secondary"><?= e((string)($trader['email'] ?? '')) ?></div>
                            </td>
                            <td class="text-end small"><?= number_format((int)($trader['trade_count'] ?? 0)) ?></td>
                            <td class="text-end font-monospace small text-success"><?= number_format((float)($trader['volume'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-secondary small mb-0">No trade data yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pair Breakdown Table -->
<div class="glass rounded-4 p-4">
    <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Volume Breakdown by Pair</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>Symbol</th><th>Market</th><th class="text-end">Trades</th><th class="text-end">Volume</th><th class="text-end">Fees</th></tr></thead>
            <tbody>
            <?php foreach ($byPair as $pair): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($pair['symbol'] ?? '-')) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($pair['market_type'] ?? '-')) ?></span></td>
                    <td class="text-end"><?= number_format((int)($pair['trade_count'] ?? 0)) ?></td>
                    <td class="text-end font-monospace text-success"><?= number_format((float)($pair['volume'] ?? 0), 2) ?></td>
                    <td class="text-end font-monospace text-warning"><?= number_format((float)($pair['fees'] ?? 0), 6) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const dayLabels = <?= $dayLabels ?: '[]' ?>;
const dayVols   = <?= $dayVols ?: '[]' ?>;
const dayFees   = <?= $dayFees ?: '[]' ?>;
const pairNames = <?= $pairNames ?: '[]' ?>;
const pairVols  = <?= $pairVols ?: '[]' ?>;

if (dayLabels.length > 0) {
    new ApexCharts(document.getElementById('dailyVolumeChart'), {
        series: [{ name: 'Volume', data: dayVols }],
        chart: { type: 'area', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: dayLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0) } },
        theme: { mode: 'dark' },
        colors: ['#38bdf8'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();

    new ApexCharts(document.getElementById('feeChart'), {
        series: [{ name: 'Fees', data: dayFees }],
        chart: { type: 'bar', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: dayLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(4) } },
        theme: { mode: 'dark' },
        colors: ['#f59e0b'],
        plotOptions: { bar: { borderRadius: 2, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

if (pairNames.length > 0) {
    new ApexCharts(document.getElementById('pairChart'), {
        series: pairVols,
        chart: { type: 'donut', height: 220, background: 'transparent' },
        labels: pairNames,
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();
}
</script>
