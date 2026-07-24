<?php declare(strict_types=1); ?>
<?php
$orderStats = is_array($orderStats  ?? null) ? $orderStats  : [];
$dailyVol   = is_array($dailyVol    ?? null) ? $dailyVol    : [];
$byPair     = is_array($byPair      ?? null) ? $byPair      : [];
$byType     = is_array($byType      ?? null) ? $byType      : [];
$byStatus   = is_array($byStatus    ?? null) ? $byStatus    : [];

$dayLabels    = json_encode(array_column($dailyVol, 'day'));
$dayOrders    = json_encode(array_map('intval',   array_column($dailyVol, 'order_count')));
$dayVolume    = json_encode(array_map('floatval', array_column($dailyVol, 'volume')));
$typeLabels   = json_encode(array_column($byType, 'type_name'));
$typeCounts   = json_encode(array_map('intval',   array_column($byType, 'order_count')));
$statusLabels = json_encode(array_column($byStatus, 'status'));
$statusCounts = json_encode(array_map('intval',    array_column($byStatus, 'order_count')));
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Order Reports</h1>
        <p class="text-secondary mb-0">Platform-wide order analytics and breakdowns.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/orders" class="btn btn-sm btn-outline-secondary">← Back to Orders</a>
        <a href="/admin/orders/export" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>Export CSV</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        [number_format((int)($orderStats['total_orders']   ?? 0)),  'Total Orders',    'info'],
        [number_format((int)($orderStats['open_orders']    ?? 0)),  'Open',            'warning'],
        [number_format((int)($orderStats['filled_orders']  ?? 0)),  'Filled',          'success'],
        [number_format((int)($orderStats['cancelled_orders'] ?? 0)),'Cancelled',       'secondary'],
        [number_format((float)($orderStats['total_volume'] ?? 0), 2), 'Total Volume',  'info'],
        [number_format((int)($orderStats['orders_today']   ?? 0)),  'Orders Today',    'primary'],
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
    <!-- Daily Orders + Volume -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-info"></i>Daily Orders & Volume (30 days)</h5>
            <div id="dailyOrderChart"></div>
        </div>
    </div>
    <!-- Status Donut -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-secondary"></i>Orders by Status</h5>
            <div id="statusChart"></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Type Donut -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Orders by Type</h5>
            <div id="typeChart"></div>
        </div>
    </div>
    <!-- By Pair Table -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Orders by Pair</h5>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead class="sticky-top bg-dark"><tr><th>Symbol</th><th class="text-end">Orders</th><th class="text-end">Volume</th><th class="text-end">Buy</th><th class="text-end">Sell</th></tr></thead>
                    <tbody>
                    <?php foreach ($byPair as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($row['symbol'] ?? '-')) ?></td>
                            <td class="text-end"><?= number_format((int)($row['order_count'] ?? 0)) ?></td>
                            <td class="text-end font-monospace text-success"><?= number_format((float)($row['volume'] ?? 0), 2) ?></td>
                            <td class="text-end text-success small"><?= number_format((int)($row['buy_count'] ?? 0)) ?></td>
                            <td class="text-end text-danger small"><?= number_format((int)($row['sell_count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const dayLabels    = <?= $dayLabels    ?: '[]' ?>;
const dayOrders    = <?= $dayOrders    ?: '[]' ?>;
const dayVolume    = <?= $dayVolume    ?: '[]' ?>;
const typeLabels   = <?= $typeLabels   ?: '[]' ?>;
const typeCounts   = <?= $typeCounts   ?: '[]' ?>;
const statusLabels = <?= $statusLabels ?: '[]' ?>;
const statusCounts = <?= $statusCounts ?: '[]' ?>;

if (dayLabels.length > 0) {
    new ApexCharts(document.getElementById('dailyOrderChart'), {
        series: [
            { name: 'Orders', data: dayOrders },
            { name: 'Volume', data: dayVolume }
        ],
        chart: { type: 'line', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: dayLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: [
            { title: { text: 'Orders', style: { color: '#38bdf8' } }, labels: { style: { colors: '#94a3b8' } } },
            { opposite: true, title: { text: 'Volume', style: { color: '#22c55e' } }, labels: { style: { colors: '#94a3b8' } } }
        ],
        theme: { mode: 'dark' },
        colors: ['#38bdf8', '#22c55e'],
        stroke: { curve: 'smooth', width: [2, 2] },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

if (statusLabels.length > 0) {
    new ApexCharts(document.getElementById('statusChart'), {
        series: statusCounts,
        chart: { type: 'donut', height: 220, background: 'transparent' },
        labels: statusLabels,
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();
}

if (typeLabels.length > 0) {
    new ApexCharts(document.getElementById('typeChart'), {
        series: typeCounts,
        chart: { type: 'donut', height: 220, background: 'transparent' },
        labels: typeLabels,
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();
}
</script>
