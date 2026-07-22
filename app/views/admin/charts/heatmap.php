<?php declare(strict_types=1); ?>
<?php
$heatmap    = is_array($heatmap ?? null) ? $heatmap : [];
$marketType = (string)($marketType ?? '');
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Header + Filter -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold mb-0"><i class="fas fa-th me-2 text-warning"></i>Market Heatmap</h2>
        <div class="text-secondary small">Bubble size = 24H volume · Colour = 24H % change</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php foreach (['' => 'All', 'spot' => 'Spot', 'margin' => 'Margin', 'futures' => 'Futures'] as $val => $label): ?>
        <a href="/admin/charts/heatmap<?= $val !== '' ? '?market_type=' . urlencode($val) : '' ?>"
           class="btn btn-xs <?= $val === $marketType ? 'btn-warning' : 'btn-outline-secondary' ?>">
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </a>
        <?php endforeach; ?>
        <a href="/admin/charts" class="btn btn-xs btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<!-- Heatmap Grid -->
<div class="glass rounded-3 p-4 mb-4">
    <div class="d-flex flex-wrap gap-2" id="heatmapGrid">
        <?php if (empty($heatmap)): ?>
        <div class="text-secondary w-100 text-center py-5"><i class="fas fa-chart-area fa-2x mb-2 d-block"></i>No market data available</div>
        <?php else: ?>
        <?php foreach ($heatmap as $item):
            $chg   = (float)($item['change_24h_percent'] ?? 0);
            $vol   = (float)($item['volume_24h'] ?? 0);
            $abs   = min(abs($chg) / 15 * 100, 100);
            $r     = $chg >= 0 ? 34  : 239;
            $g     = $chg >= 0 ? 197 : 68;
            $b     = $chg >= 0 ? 94  : 68;
            $alpha = 0.12 + $abs / 100 * 0.75;
            $style = "background:rgba({$r},{$g},{$b},{$alpha});border:1px solid rgba({$r},{$g},{$b},.55)";
        ?>
        <a href="/charts?pair=<?= urlencode((string)$item['symbol']) ?>"
           class="text-decoration-none rounded-2 d-flex flex-column align-items-center justify-content-center"
           style="<?= $style ?>;padding:.65rem .8rem;min-width:85px;cursor:pointer;transition:transform .15s"
           title="<?= htmlspecialchars((string)$item['symbol'], ENT_QUOTES) ?> · Vol: <?= number_format($vol, 0) ?>"
           onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform=''">
            <div style="font-size:.8rem;font-weight:700;color:#f1f5f9;margin-bottom:.15rem"><?= htmlspecialchars((string)$item['base_code'], ENT_QUOTES) ?></div>
            <div style="font-size:.72rem;color:<?= $chg >= 0 ? '#4ade80' : '#f87171' ?>;font-weight:600">
                <?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%
            </div>
            <div style="font-size:.62rem;color:#64748b;margin-top:.1rem"><?= htmlspecialchars((string)$item['symbol'], ENT_QUOTES) ?></div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bubble Chart (ApexCharts) -->
<?php if (!empty($heatmap)): ?>
<div class="glass rounded-3 p-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-bar me-1 text-info"></i>Volume vs Change Scatter</h6>
        <span class="text-secondary small">Bubble size = volume, Y-axis = % change</span>
    </div>
    <div id="bubbleChart"></div>
</div>

<script>
const hmData = <?= json_encode(array_map(fn($item) => [
    'symbol'  => $item['symbol'],
    'change'  => round((float)($item['change_24h_percent'] ?? 0), 4),
    'volume'  => round((float)($item['volume_24h'] ?? 0), 2),
    'price'   => round((float)($item['last_price'] ?? 0), 6),
], array_slice($heatmap, 0, 60))) ?>;

const gainers = hmData.filter(d => d.change >= 0);
const losers  = hmData.filter(d => d.change <  0);

function toScatter(arr) {
    return arr.map(d => ({ x: d.symbol, y: d.change, z: Math.max(d.volume, 1) }));
}

new ApexCharts(document.getElementById('bubbleChart'), {
    series: [
        { name: 'Gainers', data: toScatter(gainers) },
        { name: 'Losers',  data: toScatter(losers)  },
    ],
    chart: { type: 'bubble', height: 340, background: 'transparent', foreColor: '#94a3b8',
             toolbar: { show: false }, animations: { enabled: false } },
    colors: ['#22c55e', '#ef4444'],
    xaxis: { type: 'category', labels: { rotate: -45, style: { colors: '#64748b', fontSize: '10px' } } },
    yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => (v >= 0 ? '+' : '') + v?.toFixed?.(2) + '%' } },
    fill: { opacity: 0.7 },
    tooltip: {
        theme: 'dark',
        custom({ series, seriesIndex, dataPointIndex, w }) {
            const d   = w.config.series[seriesIndex].data[dataPointIndex];
            const chg = (d.y >= 0 ? '+' : '') + d.y.toFixed(2) + '%';
            return `<div class="p-2 bg-dark border border-secondary rounded" style="font-size:.78rem">
                <b>${d.x}</b><br>Change: <b>${chg}</b><br>Volume: <b>${d.z.toLocaleString()}</b>
            </div>`;
        },
    },
    grid: { borderColor: '#1e293b', strokeDashArray: 4 },
    legend: { show: true, position: 'top', labels: { colors: '#94a3b8' } },
}).render();
</script>
<?php endif; ?>
