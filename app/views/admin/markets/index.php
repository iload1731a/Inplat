<?php declare(strict_types=1); ?>
<?php
$stats      = is_array($stats ?? null)      ? $stats      : [];
$topPairs   = is_array($topPairs ?? null)   ? $topPairs   : [];
$gainers    = is_array($gainers ?? null)    ? $gainers    : [];
$losers     = is_array($losers ?? null)     ? $losers     : [];
$volChart   = is_array($volChart ?? null)   ? $volChart   : [];
$typeVols   = is_array($typeVols ?? null)   ? $typeVols   : [];
$providers  = is_array($providers ?? null)  ? $providers  : [];
$syncStats  = is_array($syncStats ?? null)  ? $syncStats  : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Markets Overview</h1>
        <p class="text-secondary mb-0">Real-time view of all trading markets, price feeds and provider health.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/markets/pairs" class="btn btn-outline-info btn-sm"><i class="fas fa-list me-1"></i>Pairs</a>
        <a href="/admin/markets/providers" class="btn btn-outline-warning btn-sm"><i class="fas fa-server me-1"></i>Providers</a>
        <a href="/admin/markets/data-sync" class="btn btn-outline-primary btn-sm"><i class="fas fa-plug me-1"></i>Data Sync</a>
        <a href="/admin/markets/statistics" class="btn btn-outline-success btn-sm"><i class="fas fa-chart-bar me-1"></i>Statistics</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['Total Pairs',       $stats['total_pairs']       ?? 0, 'text-info',    'fa-exchange-alt'],
        ['Active Pairs',      $stats['active_pairs']      ?? 0, 'text-success', 'fa-circle-check'],
        ['Trading Enabled',   $stats['trading_pairs']     ?? 0, 'text-primary', 'fa-play-circle'],
        ['Spot Markets',      $stats['spot_pairs']        ?? 0, 'text-info',    'fa-chart-line'],
        ['Futures',           $stats['futures_pairs']     ?? 0, 'text-warning', 'fa-layer-group'],
        ['Currencies',        $stats['total_currencies']  ?? 0, 'text-secondary','fa-coins'],
        ['24H Volume',        '$' . number_format((float)($stats['total_volume_24h'] ?? 0), 0), 'text-success', 'fa-dollar-sign'],
        ['Providers Active',  count(array_filter($providers, fn($p) => (int)($p['is_active'] ?? 0))), 'text-purple', 'fa-server'],
    ];
    ?>
    <?php foreach ($kpis as [$label, $val, $cls, $icon]): ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="fas <?= e($icon) ?> <?= e($cls) ?> fa-fw"></i>
                <span class="small text-secondary"><?= e($label) ?></span>
            </div>
            <div class="h4 mb-0 <?= e($cls) ?>"><?= is_numeric($val) ? number_format((float)$val) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- 30-Day Volume Chart -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">30-Day Trading Volume</h2>
            <div id="volumeChart" style="height:240px"></div>
        </div>
    </div>
    <!-- Sync Health -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Price Feed Health (24H)</h2>
            <?php
            $successes   = (int)($syncStats['successes']   ?? 0);
            $failures    = (int)($syncStats['failures']    ?? 0);
            $rateLimited = (int)($syncStats['rate_limited'] ?? 0);
            $total       = max(1, $successes + $failures + $rateLimited);
            $successPct  = round($successes / $total * 100, 1);
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-secondary">Success Rate</span>
                    <span class="<?= $successPct >= 95 ? 'text-success' : ($successPct >= 80 ? 'text-warning' : 'text-danger') ?>"><?= $successPct ?>%</span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar bg-success" style="width:<?= $successPct ?>%"></div>
                </div>
            </div>
            <?php
            $rows = [
                ['Successes',   $successes,   'text-success'],
                ['Failures',    $failures,    'text-danger'],
                ['Rate Limited',$rateLimited, 'text-warning'],
                ['WS Events',   (int)($syncStats['ws_events'] ?? 0), 'text-info'],
                ['Avg Response',(int)($syncStats['avg_response_ms'] ?? 0) . 'ms', 'text-secondary'],
            ];
            ?>
            <div class="list-group list-group-flush">
            <?php foreach ($rows as [$lbl, $val, $cls]): ?>
                <div class="list-group-item bg-transparent border-secondary px-0 py-1 d-flex justify-content-between">
                    <span class="small text-secondary"><?= e($lbl) ?></span>
                    <span class="small fw-semibold <?= e($cls) ?>"><?= is_numeric($val) ? number_format((int)$val) : e((string)$val) ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <a href="/admin/markets/sync-logs" class="btn btn-xs btn-outline-info w-100 mt-2">View Sync Logs</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Top Pairs by Volume -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">Top Markets by Volume</h2>
                <a href="/admin/markets/pairs" class="btn btn-xs btn-outline-light">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover table-sm align-middle mb-0">
                    <thead><tr>
                        <th>Pair</th><th>Type</th><th>Price</th><th>24H Change</th><th>24H Volume</th><th>High</th><th>Low</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($topPairs as $p): ?>
                        <?php $chg = (float)($p['change_24h_percent'] ?? 0); ?>
                        <tr>
                            <td><a href="/admin/markets/pair/detail?id=<?= (int)$p['id'] ?>" class="fw-semibold text-info text-decoration-none"><?= e((string)$p['symbol']) ?></a></td>
                            <td><span class="badge text-bg-<?= ['spot'=>'info','margin'=>'warning','futures'=>'danger'][$p['market_type'] ?? 'spot'] ?? 'secondary' ?>"><?= e(ucfirst((string)$p['market_type'])) ?></span></td>
                            <td><?= number_format((float)($p['last_price'] ?? 0), (int)($p['price_precision'] ?? 2)) ?></td>
                            <td class="<?= $chg >= 0 ? 'text-success' : 'text-danger' ?>"><?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%</td>
                            <td><?= number_format((float)($p['volume_24h'] ?? 0), 2) ?></td>
                            <td class="text-secondary"><?= number_format((float)($p['high_24h'] ?? 0), (int)($p['price_precision'] ?? 2)) ?></td>
                            <td class="text-secondary"><?= number_format((float)($p['low_24h'] ?? 0), (int)($p['price_precision'] ?? 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$topPairs): ?>
                        <tr><td colspan="7" class="text-center text-secondary">No ticker data yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Gainers & Losers -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 text-success mb-3"><i class="fas fa-arrow-trend-up me-1"></i>Top Gainers</h2>
            <?php foreach ($gainers as $g): ?>
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary">
                <span class="small fw-semibold"><?= e((string)$g['symbol']) ?></span>
                <span class="small text-success">+<?= number_format((float)($g['change_24h_percent'] ?? 0), 2) ?>%</span>
            </div>
            <?php endforeach; ?>
            <?php if (!$gainers): ?><p class="text-secondary small mb-0">No gainers data.</p><?php endif; ?>
        </div>
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-danger mb-3"><i class="fas fa-arrow-trend-down me-1"></i>Top Losers</h2>
            <?php foreach ($losers as $l): ?>
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary">
                <span class="small fw-semibold"><?= e((string)$l['symbol']) ?></span>
                <span class="small text-danger"><?= number_format((float)($l['change_24h_percent'] ?? 0), 2) ?>%</span>
            </div>
            <?php endforeach; ?>
            <?php if (!$losers): ?><p class="text-secondary small mb-0">No losers data.</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Price Data Providers Status -->
<div class="glass rounded-4 p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0">Price Data Providers</h2>
        <a href="/admin/markets/providers" class="btn btn-xs btn-outline-warning">Manage Providers</a>
    </div>
    <div class="row g-3">
    <?php foreach ($providers as $prov): ?>
        <?php $isActive = (int)($prov['is_active'] ?? 0); ?>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold"><?= e((string)$prov['name']) ?></div>
                        <div class="text-secondary small"><?= e((string)$prov['provider_code']) ?></div>
                    </div>
                    <span class="badge text-bg-<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                </div>
                <div class="row g-1 small">
                    <div class="col-6"><span class="text-secondary">Type:</span> <?= e((string)$prov['provider_type']) ?></div>
                    <div class="col-6"><span class="text-secondary">Priority:</span> <?= (int)$prov['priority'] ?></div>
                    <div class="col-6"><span class="text-secondary">Subscriptions:</span> <?= (int)($prov['active_subscriptions'] ?? 0) ?></div>
                    <div class="col-6"><span class="text-secondary">Mappings:</span> <?= (int)($prov['mapped_assets'] ?? 0) ?></div>
                    <div class="col-12"><span class="text-secondary">Health:</span>
                        <span class="badge text-bg-<?= ['healthy'=>'success','degraded'=>'warning','down'=>'danger','unknown'=>'secondary'][$prov['last_health_status'] ?? 'unknown'] ?? 'secondary' ?>"><?= e(ucfirst((string)($prov['last_health_status'] ?? 'unknown'))) ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$providers): ?><div class="col-12"><p class="text-secondary mb-0">No providers configured.</p></div><?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const volData = <?= json_encode(array_values($volChart)) ?>;
    const labels  = volData.map(r => r.day);
    const series  = volData.map(r => parseFloat(r.volume));

    if (typeof ApexCharts !== 'undefined' && labels.length) {
        new ApexCharts(document.getElementById('volumeChart'), {
            chart:  { type: 'bar', height: 240, background: 'transparent', toolbar: { show: false } },
            theme:  { mode: 'dark' },
            series: [{ name: 'Volume', data: series }],
            xaxis:  { categories: labels, labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
            yaxis:  { labels: { style: { colors: '#94a3b8' }, formatter: v => '$' + (v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v.toLocaleString()) } },
            colors: ['#38bdf8'],
            grid:   { borderColor: 'rgba(148,163,184,0.1)' },
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 3 } },
        }).render();
    } else if (!labels.length) {
        document.getElementById('volumeChart').innerHTML = '<p class="text-secondary text-center pt-4">No volume data yet.</p>';
    }
});
</script>
