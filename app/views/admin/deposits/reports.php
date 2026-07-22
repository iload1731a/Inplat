<?php declare(strict_types=1); ?>
<?php
$stats             = is_array($stats             ?? null) ? $stats             : [];
$daily_volume      = is_array($daily_volume      ?? null) ? $daily_volume      : [];
$currency_breakdown= is_array($currency_breakdown?? null) ? $currency_breakdown: [];
$monthly_report    = is_array($monthly_report    ?? null) ? $monthly_report    : [];
$top_depositors    = is_array($top_depositors    ?? null) ? $top_depositors    : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/deposits" class="text-secondary text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Back to Deposits
        </a>
        <h1 class="h3 mb-0 mt-1"><i class="fas fa-chart-line me-2 text-info"></i>Deposit Reports</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/deposits/export" class="btn btn-outline-success btn-sm">
            <i class="fas fa-download me-1"></i>Export CSV
        </a>
        <a href="/admin/deposits/gateways" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-cogs me-1"></i>Gateways
        </a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <?php
    $items = [
        ['Total', (int)($stats['total'] ?? 0), 'secondary'],
        ['Credited', (int)($stats['credited'] ?? 0), 'success'],
        ['Pending', (int)($stats['pending'] ?? 0), 'warning'],
        ['Failed/Flagged', ((int)($stats['failed'] ?? 0) + (int)($stats['flagged'] ?? 0)), 'danger'],
    ];
    foreach ($items as [$lbl, $val, $col]):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($lbl) ?></div>
            <div class="h4 fw-bold text-<?= $col ?> mb-0"><?= number_format($val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-sm-6 col-lg-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Total Credited Volume</div>
            <div class="h5 fw-bold text-success mb-0 font-monospace">
                <?= number_format((float)($stats['total_credited_amount'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Today Volume</div>
            <div class="h5 fw-bold text-info mb-0 font-monospace">
                <?= number_format((float)($stats['today_amount'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Today Credited</div>
            <div class="h5 fw-bold text-light mb-0 font-monospace">
                <?= number_format((float)($stats['today_credited'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Daily volume chart -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3 fw-bold"><i class="fas fa-chart-area me-2 text-success"></i>Daily Deposit Volume (30 days)</h6>
            <div id="dailyVolumeChart"></div>
        </div>
    </div>
    <!-- Currency pie chart -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3 fw-bold"><i class="fas fa-chart-pie me-2 text-info"></i>By Currency</h6>
            <div id="currencyPieChart"></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Monthly bar chart -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Monthly Deposits (12 months)</h6>
            <div id="monthlyBarChart"></div>
        </div>
    </div>
    <!-- Top depositors -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3 fw-bold"><i class="fas fa-trophy me-2 text-warning"></i>Top Depositors</h6>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>User</th><th>Count</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_depositors as $i => $d): ?>
                    <tr>
                        <td class="text-secondary small"><?= $i + 1 ?></td>
                        <td>
                            <div class="small fw-semibold"><?= e((string)($d['username'] ?? '-')) ?></div>
                            <div class="text-secondary" style="font-size:.7rem"><?= e((string)($d['email'] ?? '')) ?></div>
                        </td>
                        <td><span class="badge bg-secondary"><?= (int)($d['deposit_count'] ?? 0) ?></span></td>
                        <td class="text-end font-monospace small text-success">
                            <?= number_format((float)($d['total_amount'] ?? 0), 4) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_depositors)): ?>
                    <tr><td colspan="4" class="text-center text-secondary py-3">No data yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Currency breakdown table -->
<div class="glass rounded-4 p-4">
    <h6 class="mb-3 fw-bold"><i class="fas fa-coins me-2 text-warning"></i>Deposits by Currency</h6>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>Currency</th><th>Type</th><th>Deposit Count</th><th>Credited Count</th><th class="text-end">Total Volume</th></tr>
            </thead>
            <tbody>
            <?php foreach ($currency_breakdown as $row): ?>
            <tr>
                <td><span class="badge bg-info"><?= e((string)($row['code'] ?? '-')) ?></span> <?= e((string)($row['name'] ?? '')) ?></td>
                <td><span class="badge bg-secondary"><?= e(strtoupper((string)($row['type'] ?? ''))) ?></span></td>
                <td><?= number_format((int)($row['deposit_count'] ?? 0)) ?></td>
                <td><span class="badge bg-success"><?= number_format((int)($row['credited_count'] ?? 0)) ?></span></td>
                <td class="text-end font-monospace text-success"><?= number_format((float)($row['total_amount'] ?? 0), 4) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($currency_breakdown)): ?>
            <tr><td colspan="5" class="text-center text-secondary py-3">No deposit data yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    // Daily volume chart
    const dailyLabels  = <?= json_encode(array_column($daily_volume, 'day')) ?>;
    const dailyAmounts = <?= json_encode(array_map(fn($r) => round((float)($r['total_amount'] ?? 0), 4), $daily_volume)) ?>;
    const dailyCounts  = <?= json_encode(array_map(fn($r) => (int)($r['total_count'] ?? 0), $daily_volume)) ?>;

    new ApexCharts(document.getElementById('dailyVolumeChart'), {
        series: [
            { name: 'Volume', type: 'area', data: dailyAmounts },
            { name: 'Count',  type: 'line', data: dailyCounts  },
        ],
        chart:  { type: 'line', height: 280, toolbar: { show: false }, background: 'transparent' },
        stroke: { curve: 'smooth', width: [2, 2] },
        fill:   { type: ['gradient', 'solid'], gradient: { shade: 'dark', opacityFrom: 0.6, opacityTo: 0.0 } },
        colors: ['#00e396', '#008ffb'],
        xaxis:  { categories: dailyLabels, labels: { style: { colors: '#aaa' } } },
        yaxis:  [
            { title: { text: 'Volume', style: { color: '#00e396' } }, labels: { style: { colors: '#aaa' } } },
            { opposite: true, title: { text: 'Count', style: { color: '#008ffb' } }, labels: { style: { colors: '#aaa' } } },
        ],
        tooltip: { theme: 'dark' },
        grid:    { borderColor: '#333' },
        theme:   { mode: 'dark' },
    }).render();

    // Currency pie chart
    const pieLabels  = <?= json_encode(array_column($currency_breakdown, 'code')) ?>;
    const pieAmounts = <?= json_encode(array_map(fn($r) => round((float)($r['total_amount'] ?? 0), 4), $currency_breakdown)) ?>;
    new ApexCharts(document.getElementById('currencyPieChart'), {
        series: pieAmounts,
        labels: pieLabels,
        chart:  { type: 'donut', height: 280, background: 'transparent' },
        legend: { labels: { colors: '#aaa' } },
        tooltip: { theme: 'dark' },
        theme:   { mode: 'dark' },
        dataLabels: { style: { fontSize: '11px' } },
    }).render();

    // Monthly bar chart
    const monthLabels  = <?= json_encode(array_column($monthly_report, 'month')) ?>;
    const monthAmounts = <?= json_encode(array_map(fn($r) => round((float)($r['total_amount'] ?? 0), 4), $monthly_report)) ?>;
    const monthCounts  = <?= json_encode(array_map(fn($r) => (int)($r['total_count'] ?? 0), $monthly_report)) ?>;
    new ApexCharts(document.getElementById('monthlyBarChart'), {
        series: [
            { name: 'Volume', data: monthAmounts },
            { name: 'Count',  data: monthCounts  },
        ],
        chart:  { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
        colors: ['#00e396', '#feb019'],
        xaxis:  { categories: monthLabels, labels: { style: { colors: '#aaa' } } },
        yaxis:  { labels: { style: { colors: '#aaa' } } },
        tooltip: { theme: 'dark' },
        grid:    { borderColor: '#333' },
        theme:   { mode: 'dark' },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    }).render();
})();
</script>
