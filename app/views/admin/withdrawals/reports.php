<?php declare(strict_types=1); ?>
<?php
$stats              = is_array($stats              ?? null) ? $stats              : [];
$daily_volume       = is_array($daily_volume       ?? null) ? $daily_volume       : [];
$monthly_report     = is_array($monthly_report     ?? null) ? $monthly_report     : [];
$currency_breakdown = is_array($currency_breakdown ?? null) ? $currency_breakdown : [];

// Prepare chart data
$dailyLabels  = json_encode(array_column($daily_volume, 'day'));
$dailyCounts  = json_encode(array_map('intval', array_column($daily_volume, 'cnt')));
$dailyAmounts = json_encode(array_map('floatval', array_column($daily_volume, 'completed_amount')));

$monthlyLabels    = json_encode(array_column($monthly_report, 'ym'));
$monthlyCounts    = json_encode(array_map('intval', array_column($monthly_report, 'total')));
$monthlyCompleted = json_encode(array_map('floatval', array_column($monthly_report, 'completed_amount')));
$monthlyFees      = json_encode(array_map('floatval', array_column($monthly_report, 'fee_revenue')));

$cbLabels  = json_encode(array_column($currency_breakdown, 'code'));
$cbAmounts = json_encode(array_map('floatval', array_column($currency_breakdown, 'completed_amount')));
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-chart-bar me-2 text-info"></i>Withdrawal Reports</h1>
        <p class="text-secondary mb-0">Analytics, trends, and performance metrics for withdrawals.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/withdrawals" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>All Withdrawals</a>
        <a href="/admin/withdrawals/export" class="btn btn-outline-success btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label'=>'Total Withdrawals',   'val'=>number_format((int)($stats['total']       ?? 0)),                       'icon'=>'fa-list',         'color'=>'secondary'],
        ['label'=>'Pending',             'val'=>number_format((int)($stats['pending']      ?? 0)),                       'icon'=>'fa-clock',        'color'=>'warning'],
        ['label'=>'Completed',           'val'=>number_format((int)($stats['completed']    ?? 0)),                       'icon'=>'fa-check-circle', 'color'=>'success'],
        ['label'=>'Rejected',            'val'=>number_format((int)($stats['rejected']     ?? 0)),                       'icon'=>'fa-times-circle', 'color'=>'danger'],
        ['label'=>'Manual Review Queue', 'val'=>number_format((int)($stats['manual_pending']?? 0)),                      'icon'=>'fa-eye',          'color'=>'warning'],
        ['label'=>'Completed Volume',    'val'=>number_format((float)($stats['total_completed_amount'] ?? 0), 4),        'icon'=>'fa-coins',        'color'=>'info'],
        ['label'=>'Total Fee Revenue',   'val'=>number_format((float)($stats['total_fee_revenue']      ?? 0), 4),        'icon'=>'fa-dollar-sign',  'color'=>'success'],
        ['label'=>'Today Volume',        'val'=>number_format((float)($stats['today_amount']           ?? 0), 4),        'icon'=>'fa-calendar-day', 'color'=>'primary'],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 d-flex align-items-center gap-3">
            <div class="text-<?= e($k['color']) ?>" style="font-size:1.8rem"><i class="fas <?= e($k['icon']) ?>"></i></div>
            <div>
                <div class="small text-secondary"><?= e($k['label']) ?></div>
                <div class="h5 mb-0 fw-bold"><?= e($k['val']) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row 1 -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-info"></i>Daily Volume (Last 30 Days)</h5>
            <div id="dailyVolumeChart" style="height:280px"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Volume by Currency</h5>
            <div id="currencyPieChart" style="height:280px"></div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-success"></i>Monthly Count &amp; Volume</h5>
            <div id="monthlyCountChart" style="height:280px"></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-dollar-sign me-2 text-info"></i>Monthly Fee Revenue</h5>
            <div id="monthlyFeeChart" style="height:280px"></div>
        </div>
    </div>
</div>

<!-- Status breakdown table -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-table me-2 text-secondary"></i>Monthly Breakdown</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="monthlyTable">
            <thead>
                <tr>
                    <th>Month</th><th>Total</th><th>Completed</th><th>Rejected</th>
                    <th>Completed Amount</th><th>Fee Revenue</th><th>Completion Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($monthly_report) as $mr): ?>
            <tr>
                <td class="fw-semibold"><?= e((string)$mr['ym']) ?></td>
                <td><?= number_format((int)$mr['total']) ?></td>
                <td class="text-success"><?= number_format((int)$mr['completed']) ?></td>
                <td class="text-danger"><?= number_format((int)$mr['rejected']) ?></td>
                <td class="font-monospace"><?= number_format((float)$mr['completed_amount'], 4) ?></td>
                <td class="font-monospace text-success"><?= number_format((float)$mr['fee_revenue'], 4) ?></td>
                <td>
                    <?php
                    $rate = (int)$mr['total'] > 0
                        ? round((int)$mr['completed'] / (int)$mr['total'] * 100, 1)
                        : 0;
                    ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:6px">
                            <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                        </div>
                        <span class="small"><?= $rate ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Currency breakdown table -->
<div class="glass rounded-4 p-4">
    <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Volume by Currency</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>Currency</th><th>Type</th><th>Count</th><th>Completed Volume</th></tr>
            </thead>
            <tbody>
            <?php foreach ($currency_breakdown as $cb): ?>
            <tr>
                <td><span class="badge bg-warning text-dark"><?= e((string)$cb['code']) ?></span></td>
                <td><span class="badge bg-secondary"><?= e(ucfirst((string)$cb['type'])) ?></span></td>
                <td><?= number_format((int)$cb['cnt']) ?></td>
                <td class="font-monospace"><?= number_format((float)$cb['completed_amount'], 8) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const dailyLabels  = <?= $dailyLabels ?>;
const dailyCounts  = <?= $dailyCounts ?>;
const dailyAmounts = <?= $dailyAmounts ?>;
const monthlyLabels    = <?= $monthlyLabels ?>;
const monthlyCounts    = <?= $monthlyCounts ?>;
const monthlyCompleted = <?= $monthlyCompleted ?>;
const monthlyFees      = <?= $monthlyFees ?>;
const cbLabels  = <?= $cbLabels ?>;
const cbAmounts = <?= $cbAmounts ?>;

// Daily volume chart
new ApexCharts(document.getElementById('dailyVolumeChart'), {
    series: [
        { name:'Count',            data: dailyCounts,  type:'bar'  },
        { name:'Completed Volume', data: dailyAmounts, type:'line' }
    ],
    chart: { type:'line', height:280, background:'transparent', toolbar:{ show:false } },
    stroke: { curve:'smooth', width:[0,3] },
    plotOptions: { bar:{ columnWidth:'60%' } },
    colors: ['#3b82f6','#22c55e'],
    xaxis: { categories: dailyLabels, labels:{ style:{colors:'#9ca3af'}, rotate:-45 } },
    yaxis: [{ title:{ text:'Count', style:{color:'#9ca3af'} }, labels:{style:{colors:'#9ca3af'}} },
            { opposite:true, title:{ text:'Volume', style:{color:'#9ca3af'} }, labels:{style:{colors:'#9ca3af'}} }],
    legend: { labels:{ colors:'#9ca3af' } },
    tooltip: { theme:'dark' },
    grid: { borderColor:'#374151' },
    theme: { mode:'dark' }
}).render();

// Currency pie chart
new ApexCharts(document.getElementById('currencyPieChart'), {
    series: cbAmounts,
    chart: { type:'donut', height:280, background:'transparent' },
    labels: cbLabels,
    colors: ['#f59e0b','#3b82f6','#22c55e','#ef4444','#8b5cf6','#14b8a6','#f97316','#ec4899'],
    legend: { labels:{ colors:'#9ca3af' }, position:'bottom' },
    tooltip: { theme:'dark' },
    theme: { mode:'dark' }
}).render();

// Monthly count chart
new ApexCharts(document.getElementById('monthlyCountChart'), {
    series: [
        { name:'Total',     data: monthlyCounts    },
        { name:'Completed', data: monthlyCompleted }
    ],
    chart: { type:'bar', height:280, background:'transparent', toolbar:{ show:false } },
    plotOptions: { bar:{ borderRadius:4, columnWidth:'60%', dataLabels:{ position:'top' } } },
    colors: ['#3b82f6','#22c55e'],
    xaxis: { categories: monthlyLabels, labels:{ style:{colors:'#9ca3af'} } },
    yaxis: { labels:{ style:{colors:'#9ca3af'} } },
    legend: { labels:{ colors:'#9ca3af' } },
    tooltip: { theme:'dark' },
    grid: { borderColor:'#374151' },
    theme: { mode:'dark' }
}).render();

// Monthly fee chart
new ApexCharts(document.getElementById('monthlyFeeChart'), {
    series: [{ name:'Fee Revenue', data: monthlyFees }],
    chart: { type:'area', height:280, background:'transparent', toolbar:{ show:false } },
    stroke: { curve:'smooth', width:3 },
    fill: { type:'gradient', gradient:{ shadeIntensity:1, opacityFrom:0.4, opacityTo:0.0 } },
    colors: ['#22c55e'],
    xaxis: { categories: monthlyLabels, labels:{ style:{colors:'#9ca3af'} } },
    yaxis: { labels:{ style:{colors:'#9ca3af'} } },
    tooltip: { theme:'dark' },
    grid: { borderColor:'#374151' },
    theme: { mode:'dark' }
}).render();

$('#monthlyTable').DataTable({ order:[[0,'desc']], pageLength:12, dom:'t' });
</script>
