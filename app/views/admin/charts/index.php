<?php declare(strict_types=1); ?>
<?php
$kpis        = is_array($kpis ?? null)         ? $kpis         : [];
$dailyVolume = is_array($daily_volume ?? null)  ? $daily_volume : [];
$topMovers   = is_array($top_movers ?? null)    ? $top_movers   : [];
$marketTypes = is_array($market_types ?? null)  ? $market_types : [];
$heatmap     = is_array($heatmap ?? null)       ? $heatmap      : [];
$error       = (string)($error ?? '');
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if ($error !== ''): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="alert alert-info small">
    <strong>Go live quickly:</strong>
    1) Configure provider API keys in <a href="/admin/markets/providers" class="alert-link">Price Data Providers</a>,
    2) map symbols in <a href="/admin/markets/mappings" class="alert-link">Asset Mappings</a>,
    3) enable subscriptions in <a href="/admin/markets/feed" class="alert-link">Price Feed</a>.
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <?php
    $kpiCards = [
        ['Active Pairs',   number_format((int)($kpis['active_pairs'] ?? 0)),          'fas fa-exchange-alt', 'text-info'],
        ['24H Volume',     '$' . number_format((float)($kpis['volume_24h'] ?? 0), 0), 'fas fa-chart-bar',    'text-success'],
        ['Trades (24H)',   number_format((int)($kpis['trades_24h'] ?? 0)),             'fas fa-bolt',         'text-warning'],
        ['Notional (24H)', '$' . number_format((float)($kpis['notional_24h'] ?? 0), 0),'fas fa-dollar-sign', 'text-primary'],
        ['Total Candles',  number_format((int)($kpis['total_candles'] ?? 0)),          'fas fa-database',     'text-secondary'],
    ];
    ?>
    <?php foreach ($kpiCards as [$label, $value, $icon, $cls]): ?>
    <div class="col-sm-6 col-lg-auto flex-grow-1">
        <div class="glass rounded-3 p-3 text-center">
            <div class="mb-1 <?= htmlspecialchars($cls, ENT_QUOTES) ?>"><i class="<?= htmlspecialchars($icon, ENT_QUOTES) ?> fa-lg"></i></div>
            <div class="h5 mb-0 fw-bold"><?= htmlspecialchars($value, ENT_QUOTES) ?></div>
            <div class="small text-secondary"><?= htmlspecialchars($label, ENT_QUOTES) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quick Links -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="/admin/charts/volatility" class="btn btn-sm btn-outline-warning"><i class="fas fa-fire me-1"></i>Volatility Analysis</a>
    <a href="/admin/charts/correlation" class="btn btn-sm btn-outline-info"><i class="fas fa-project-diagram me-1"></i>Correlation Matrix</a>
    <a href="/admin/charts/heatmap" class="btn btn-sm btn-outline-success"><i class="fas fa-th me-1"></i>Market Heatmap</a>
    <a href="/charts" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-external-link-alt me-1"></i>Advanced Charts</a>
</div>

<!-- Main Charts Row -->
<div class="row g-4 mb-4">

    <!-- Daily Volume Chart -->
    <div class="col-lg-8">
        <div class="glass rounded-3 p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-bar me-1 text-info"></i>Daily Trading Volume (30 Days)</h6>
                <div class="d-flex gap-1">
                    <a href="/admin/charts/export?pair=BTCUSDT&interval=1d&limit=365" class="btn btn-xs btn-outline-secondary" title="Export BTCUSDT CSV"><i class="fas fa-download me-1"></i>CSV</a>
                </div>
            </div>
            <div id="volumeChart"></div>
        </div>
    </div>

    <!-- Volume by Market Type -->
    <div class="col-lg-4">
        <div class="glass rounded-3 p-3">
            <h6 class="mb-3 fw-semibold"><i class="fas fa-chart-pie me-1 text-warning"></i>Volume by Market Type</h6>
            <div id="mktTypeChart"></div>
            <div class="mt-3">
                <?php foreach ($marketTypes as $mt): ?>
                <div class="d-flex justify-content-between py-1 border-bottom border-secondary">
                    <span class="text-secondary small text-capitalize"><?= htmlspecialchars((string)$mt['market_type'], ENT_QUOTES) ?></span>
                    <span class="text-light small fw-semibold">$<?= number_format((float)$mt['total_volume'], 0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Movers + Market Heatmap Preview -->
<div class="row g-4 mb-4">

    <!-- Top Movers -->
    <div class="col-lg-6">
        <div class="glass rounded-3 p-3">
            <h6 class="mb-3 fw-semibold"><i class="fas fa-sort-amount-up me-1 text-success"></i>Top Movers (24H)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-dark table-hover mb-0" style="font-size:.82rem">
                    <thead>
                        <tr class="text-secondary">
                            <th>Pair</th><th>Price</th><th>Change</th><th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($topMovers, 0, 15) as $m):
                            $chg = (float)($m['change_24h_percent'] ?? 0);
                        ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= htmlspecialchars((string)$m['symbol'], ENT_QUOTES) ?>
                                <span class="badge <?= in_array($m['market_type'] ?? '', ['futures','margin'], true) ? 'text-bg-danger' : 'text-bg-secondary' ?> ms-1" style="font-size:.65rem"><?= htmlspecialchars(ucfirst((string)$m['market_type']), ENT_QUOTES) ?></span>
                            </td>
                            <td><?= number_format((float)$m['last_price'], 4) ?></td>
                            <td class="<?= $chg >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold"><?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%</td>
                            <td class="text-secondary"><?= number_format((float)$m['volume_24h'], 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Market Heatmap Preview -->
    <div class="col-lg-6">
        <div class="glass rounded-3 p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-th me-1 text-warning"></i>Market Heatmap Preview</h6>
                <a href="/admin/charts/heatmap" class="btn btn-xs btn-outline-secondary">Full Heatmap</a>
            </div>
            <div id="heatmapPreview" class="d-flex flex-wrap gap-1">
                <?php foreach (array_slice($heatmap, 0, 30) as $item):
                    $chg  = (float)($item['change_24h_percent'] ?? 0);
                    $abs  = min(abs($chg) / 10 * 100, 100);
                    $r    = $chg >= 0 ? 34  : 239;
                    $g    = $chg >= 0 ? 197 : 68;
                    $b    = $chg >= 0 ? 94  : 68;
                    $alpha = 0.15 + $abs / 100 * 0.7;
                    $style = "background:rgba({$r},{$g},{$b},{$alpha});border:1px solid rgba({$r},{$g},{$b},.5)";
                ?>
                <a href="/charts?pair=<?= urlencode((string)$item['symbol']) ?>"
                   class="text-decoration-none rounded-2 text-center"
                   style="<?= $style ?>;padding:.35rem .5rem;min-width:70px;cursor:pointer"
                   title="<?= htmlspecialchars((string)$item['symbol'], ENT_QUOTES) ?>">
                    <div style="font-size:.72rem;font-weight:600;color:#e2e8f0"><?= htmlspecialchars((string)$item['base_code'], ENT_QUOTES) ?></div>
                    <div style="font-size:.68rem;color:<?= $chg >= 0 ? '#22c55e' : '#ef4444' ?>">
                        <?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Pair Chart -->
<div class="glass rounded-3 p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-line me-1 text-info"></i>Pair Chart Explorer</h6>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <select id="adminPairSelect" class="form-select form-select-sm bg-dark text-light border-secondary" style="width:160px"
                    onchange="loadAdminChart()">
                <?php foreach ($heatmap as $item): ?>
                <option value="<?= htmlspecialchars((string)$item['symbol'], ENT_QUOTES) ?>"><?= htmlspecialchars((string)$item['symbol'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="adminIvSelect" class="form-select form-select-sm bg-dark text-light border-secondary" style="width:90px"
                    onchange="loadAdminChart()">
                <?php foreach (['1h','4h','1d','1w'] as $iv): ?>
                <option value="<?= $iv ?>" <?= $iv === '1d' ? 'selected' : '' ?>><?= $iv ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-info" onclick="loadAdminChart()"><i class="fas fa-sync-alt me-1"></i>Load</button>
            <a id="exportBtn" href="#" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>Export CSV</a>
        </div>
    </div>
    <div id="adminChart" style="min-height:300px"></div>
</div>

<script>
// ──────────────────────────────────────────────────────────
// 1. Volume bar chart
// ──────────────────────────────────────────────────────────
const volData  = <?= json_encode(array_map(fn($r) => ['x' => $r['date'], 'y' => (float)($r['notional'] ?? 0)], $dailyVolume)) ?>;
const tradeData = <?= json_encode(array_map(fn($r) => ['x' => $r['date'], 'y' => (int)($r['trades'] ?? 0)], $dailyVolume)) ?>;

new ApexCharts(document.getElementById('volumeChart'), {
    series: [
        { name: 'Notional Volume ($)', type: 'bar', data: volData },
        { name: 'Trade Count', type: 'line', data: tradeData },
    ],
    chart: { height: 240, background: 'transparent', foreColor: '#94a3b8', toolbar: { show: false }, animations: { enabled: false } },
    plotOptions: { bar: { columnWidth: '75%', colors: { ranges: [{ from: 0, to: 1e18, color: '#38bdf8' }] } } },
    stroke: { width: [0, 2] },
    colors: ['#38bdf8', '#f59e0b'],
    xaxis: { type: 'datetime', labels: { style: { colors: '#64748b' } } },
    yaxis: [
        { labels: { style: { colors: '#94a3b8' }, formatter: v => '$' + (v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v.toFixed(0)) } },
        { opposite: true, labels: { style: { colors: '#f59e0b' }, formatter: v => v.toFixed(0) } },
    ],
    grid: { borderColor: '#1e293b', strokeDashArray: 4 },
    tooltip: { theme: 'dark', x: { format: 'dd MMM yyyy' } },
    legend: { show: true, position: 'top', labels: { colors: '#94a3b8' } },
}).render();

// ──────────────────────────────────────────────────────────
// 2. Market type donut
// ──────────────────────────────────────────────────────────
const mtData   = <?= json_encode($marketTypes) ?>;
const mtLabels = mtData.map(r => r.market_type);
const mtVols   = mtData.map(r => parseFloat(r.total_volume || 0));

if (mtVols.length) {
    new ApexCharts(document.getElementById('mktTypeChart'), {
        series: mtVols,
        labels: mtLabels,
        chart: { type: 'donut', height: 200, background: 'transparent', foreColor: '#94a3b8' },
        colors: ['#38bdf8', '#f59e0b', '#a78bfa', '#22c55e', '#ef4444'],
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        tooltip: { theme: 'dark', y: { formatter: v => '$' + v.toFixed(0) } },
    }).render();
}

// ──────────────────────────────────────────────────────────
// 3. Admin pair chart explorer
// ──────────────────────────────────────────────────────────
let adminChart = null;

function loadAdminChart() {
    const pair = document.getElementById('adminPairSelect').value;
    const iv   = document.getElementById('adminIvSelect').value;
    const url  = `/admin/charts/data?pair=${encodeURIComponent(pair)}&interval=${iv}&limit=300`;
    document.getElementById('exportBtn').href = `/admin/charts/export?pair=${encodeURIComponent(pair)}&interval=${iv}&limit=365`;

    fetch(url).then(r => r.json()).then(json => {
        if (!json.ok || !json.candles?.length) return;
        const series = [
            {
                name: pair + ' Candles',
                type: 'candlestick',
                data: json.candles.map(c => ({
                    x: c.time,
                    y: [parseFloat(c.open), parseFloat(c.high), parseFloat(c.low), parseFloat(c.close)],
                })),
            },
            {
                name: 'Volume',
                type: 'bar',
                data: json.candles.map(c => ({ x: c.time, y: parseFloat(c.volume) })),
            },
        ];
        const opts = {
            series,
            chart: { height: 300, background: 'transparent', foreColor: '#94a3b8', animations: { enabled: false },
                     toolbar: { tools: { download: true, zoom: true, pan: true, reset: true, zoomin: true, zoomout: true } } },
            plotOptions: { candlestick: { colors: { upward: '#22c55e', downward: '#ef4444' } } },
            xaxis: { type: 'datetime', labels: { style: { colors: '#64748b' }, datetimeUTC: false } },
            yaxis: [
                { labels: { style: { colors: '#94a3b8' }, formatter: v => v?.toFixed?.(4) ?? v } },
                { opposite: true, show: false },
            ],
            grid: { borderColor: '#1e293b', strokeDashArray: 4 },
            tooltip: { theme: 'dark', x: { format: 'dd MMM yyyy HH:mm' } },
        };
        if (adminChart) {
            adminChart.updateOptions(opts, true, false);
        } else {
            adminChart = new ApexCharts(document.getElementById('adminChart'), opts);
            adminChart.render();
        }
    }).catch(e => console.error(e));
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminPairSelect').options.length) loadAdminChart();
});
</script>
