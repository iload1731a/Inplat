<?php declare(strict_types=1); ?>
<?php
$stats    = is_array($stats ?? null)    ? $stats    : [];
$topPairs = is_array($topPairs ?? null) ? $topPairs : [];
$gainers  = is_array($gainers ?? null)  ? $gainers  : [];
$losers   = is_array($losers ?? null)   ? $losers   : [];
$volChart = is_array($volChart ?? null) ? $volChart : [];
$typeVols = is_array($typeVols ?? null) ? $typeVols : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Market Statistics</h1>
        <p class="text-secondary mb-0">Comprehensive analytics for all markets and trading activity.</p>
    </div>
    <a href="/admin/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Overview</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['Total Pairs',      $stats['total_pairs'] ?? 0,      'text-info',    'fa-exchange-alt'],
        ['Active Pairs',     $stats['active_pairs'] ?? 0,     'text-success', 'fa-circle-check'],
        ['24H Volume',       '$' . number_format((float)($stats['total_volume_24h'] ?? 0), 0), 'text-warning', 'fa-dollar-sign'],
        ['Pairs w/ Tickers', $stats['pairs_with_tickers'] ?? 0,'text-info',   'fa-chart-line'],
        ['Spot Pairs',       $stats['spot_pairs'] ?? 0,       'text-primary', 'fa-chart-line'],
        ['Futures Pairs',    $stats['futures_pairs'] ?? 0,    'text-warning', 'fa-layer-group'],
        ['Total Currencies', $stats['total_currencies'] ?? 0, 'text-secondary','fa-coins'],
        ['Crypto',           $stats['crypto_count'] ?? 0,     'text-info',    'fa-bitcoin-sign'],
    ];
    ?>
    <?php foreach ($kpis as [$label, $val, $cls, $icon]): ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                <i class="fas <?= e($icon) ?> <?= e($cls) ?> small"></i>
                <span class="small text-secondary"><?= e($label) ?></span>
            </div>
            <div class="h4 mb-0 <?= e($cls) ?>"><?= is_numeric($val) ? number_format((float)$val, 0) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- 30-Day Volume Bar -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">30-Day Trading Volume</h2>
            <div id="volumeChart" style="height:250px"></div>
        </div>
    </div>
    <!-- Market Type Pie -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Volume by Market Type</h2>
            <div id="typeVolChart" style="height:250px"></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Gainers -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-success mb-3"><i class="fas fa-arrow-trend-up me-1"></i>Top 10 Gainers</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Pair</th><th>Price</th><th>24H %</th></tr></thead>
                    <tbody>
                    <?php foreach ($gainers as $g): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string)$g['symbol']) ?></td>
                            <td class="small"><?= number_format((float)($g['last_price'] ?? 0), 4) ?></td>
                            <td class="small text-success">+<?= number_format((float)($g['change_24h_percent'] ?? 0), 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$gainers): ?><tr><td colspan="3" class="text-secondary text-center">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Top Losers -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-danger mb-3"><i class="fas fa-arrow-trend-down me-1"></i>Top 10 Losers</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Pair</th><th>Price</th><th>24H %</th></tr></thead>
                    <tbody>
                    <?php foreach ($losers as $l): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string)$l['symbol']) ?></td>
                            <td class="small"><?= number_format((float)($l['last_price'] ?? 0), 4) ?></td>
                            <td class="small text-danger"><?= number_format((float)($l['change_24h_percent'] ?? 0), 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$losers): ?><tr><td colspan="3" class="text-secondary text-center">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Top By Volume -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-info mb-3"><i class="fas fa-fire me-1"></i>Top 20 by Volume</h2>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>#</th><th>Pair</th><th>Volume</th><th>%</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($topPairs, 0, 20) as $i => $p): ?>
                        <?php $chg = (float)($p['change_24h_percent'] ?? 0); ?>
                        <tr>
                            <td class="text-secondary small"><?= $i+1 ?></td>
                            <td class="small fw-semibold"><?= e((string)$p['symbol']) ?></td>
                            <td class="small"><?= number_format((float)($p['volume_24h'] ?? 0), 0) ?></td>
                            <td class="small <?= $chg >= 0 ? 'text-success' : 'text-danger' ?>"><?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$topPairs): ?><tr><td colspan="4" class="text-secondary text-center">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Volume bar chart
    const volData = <?= json_encode(array_values($volChart)) ?>;
    if (typeof ApexCharts !== 'undefined') {
        if (volData.length) {
            new ApexCharts(document.getElementById('volumeChart'), {
                chart:  { type: 'bar', height: 250, background: 'transparent', toolbar: { show: false } },
                theme:  { mode: 'dark' },
                series: [{ name: 'Volume', data: volData.map(r => parseFloat(r.volume)) }],
                xaxis:  { categories: volData.map(r => r.day), labels: { style: { colors: '#94a3b8', fontSize: '10px' }, rotate: -30 } },
                yaxis:  { labels: { style: { colors: '#94a3b8' }, formatter: v => '$' + (v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v.toLocaleString()) } },
                colors: ['#38bdf8'],
                grid:   { borderColor: 'rgba(148,163,184,0.1)' },
                dataLabels: { enabled: false },
                plotOptions: { bar: { borderRadius: 3 } },
            }).render();
        } else {
            document.getElementById('volumeChart').innerHTML = '<p class="text-secondary text-center pt-4">No volume data yet.</p>';
        }

        // Type volume donut
        const typeData = <?= json_encode(array_values($typeVols)) ?>;
        if (typeData.length) {
            new ApexCharts(document.getElementById('typeVolChart'), {
                chart:  { type: 'donut', height: 250, background: 'transparent' },
                theme:  { mode: 'dark' },
                series: typeData.map(r => parseFloat(r.volume_24h)),
                labels: typeData.map(r => r.market_type.charAt(0).toUpperCase() + r.market_type.slice(1)),
                colors: ['#38bdf8','#f59e0b','#f87171'],
                legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
                dataLabels: { enabled: true, style: { colors: ['#fff'] } },
            }).render();
        } else {
            document.getElementById('typeVolChart').innerHTML = '<p class="text-secondary text-center pt-4">No volume data yet.</p>';
        }
    }
});
</script>
