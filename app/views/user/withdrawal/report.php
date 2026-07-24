<?php declare(strict_types=1); ?>
<?php
$stats          = is_array($stats          ?? null) ? $stats          : [];
$monthly_totals = is_array($monthly_totals ?? null) ? $monthly_totals : [];

$monthlyLabels  = json_encode(array_column($monthly_totals, 'ym'));
$monthlyCounts  = json_encode(array_map('intval',   array_column($monthly_totals, 'cnt')));
$monthlyAmounts = json_encode(array_map('floatval', array_column($monthly_totals, 'total_amount')));

require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="fas fa-chart-bar me-2 text-info"></i>Withdrawal Report</h1>
    <div class="d-flex gap-2">
        <a href="/user/withdrawal" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Withdrawals</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label'=>'Total Requests',   'key'=>'total',           'color'=>'secondary', 'icon'=>'fa-list'],
        ['label'=>'Pending',          'key'=>'pending',         'color'=>'warning',   'icon'=>'fa-clock'],
        ['label'=>'Processing',       'key'=>'processing',      'color'=>'info',      'icon'=>'fa-spinner'],
        ['label'=>'Completed',        'key'=>'completed',       'color'=>'success',   'icon'=>'fa-check-circle'],
        ['label'=>'Rejected',         'key'=>'rejected',        'color'=>'danger',    'icon'=>'fa-times-circle'],
        ['label'=>'Cancelled',        'key'=>'cancelled',       'color'=>'secondary', 'icon'=>'fa-ban'],
        ['label'=>'Total Withdrawn',  'key'=>'total_withdrawn', 'color'=>'light',     'icon'=>'fa-coins',     'is_amount'=>true],
        ['label'=>'Total Fees Paid',  'key'=>'total_fees',      'color'=>'danger',    'icon'=>'fa-dollar-sign','is_amount'=>true],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 d-flex align-items-center gap-3">
            <div class="text-<?= e($k['color']) ?>" style="font-size:1.6rem"><i class="fas <?= e($k['icon']) ?>"></i></div>
            <div>
                <div class="small text-secondary"><?= e($k['label']) ?></div>
                <div class="h5 mb-0 fw-bold">
                    <?php if (!empty($k['is_amount'])): ?>
                        <?= number_format((float)($stats[$k['key']] ?? 0), 4) ?>
                    <?php else: ?>
                        <?= number_format((int)($stats[$k['key']] ?? 0)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status distribution -->
<?php
$total = (int)($stats['total'] ?? 0);
$statuses = [
    'completed'  => ['color'=>'#22c55e', 'label'=>'Completed'],
    'pending'    => ['color'=>'#f59e0b', 'label'=>'Pending'],
    'processing' => ['color'=>'#3b82f6', 'label'=>'Processing'],
    'rejected'   => ['color'=>'#ef4444', 'label'=>'Rejected'],
    'cancelled'  => ['color'=>'#6b7280', 'label'=>'Cancelled'],
];
?>
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4 h-100">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Status Distribution</h5>
            <div id="statusPieChart" style="height:260px"></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4 h-100">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Monthly Activity (Last 12 Months)</h5>
            <div id="monthlyChart" style="height:260px"></div>
        </div>
    </div>
</div>

<!-- Status breakdown progress bars -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-tasks me-2 text-secondary"></i>Status Overview</h5>
    <?php foreach ($statuses as $statusKey => $meta): ?>
    <?php $count = (int)($stats[$statusKey] ?? 0); $pct = $total > 0 ? round($count / $total * 100, 1) : 0; ?>
    <div class="mb-3">
        <div class="d-flex justify-content-between small mb-1">
            <span style="color:<?= e($meta['color']) ?>"><?= e($meta['label']) ?></span>
            <span class="text-secondary"><?= number_format($count) ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress" style="height:8px">
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= e($meta['color']) ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Monthly table -->
<?php if (!empty($monthly_totals)): ?>
<div class="glass rounded-4 p-4">
    <h5 class="mb-3"><i class="fas fa-table me-2 text-secondary"></i>Monthly Breakdown</h5>
    <div class="table-responsive">
        <table class="table table-user table-sm mb-0">
            <thead>
                <tr><th>Month</th><th>Count</th><th>Total Amount</th></tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($monthly_totals) as $mt): ?>
            <tr>
                <td class="fw-semibold"><?= e((string)$mt['ym']) ?></td>
                <td><?= number_format((int)$mt['cnt']) ?></td>
                <td class="font-monospace"><?= number_format((float)$mt['total_amount'], 8) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const statuses     = <?= json_encode(array_keys($statuses)) ?>;
const statusCounts = <?= json_encode(array_map(fn($s) => (int)($stats[$s] ?? 0), array_keys($statuses))) ?>;
const statusColors = <?= json_encode(array_column($statuses, 'color')) ?>;
const statusLabels = <?= json_encode(array_column($statuses, 'label')) ?>;
const monthlyLabels  = <?= $monthlyLabels ?>;
const monthlyCounts  = <?= $monthlyCounts ?>;
const monthlyAmounts = <?= $monthlyAmounts ?>;

new ApexCharts(document.getElementById('statusPieChart'), {
    series: statusCounts,
    chart: { type:'donut', height:260, background:'transparent' },
    labels: statusLabels,
    colors: statusColors,
    legend: { labels:{ colors:'#9ca3af' }, position:'bottom' },
    tooltip: { theme:'dark' },
    theme: { mode:'dark' }
}).render();

new ApexCharts(document.getElementById('monthlyChart'), {
    series: [
        { name:'Count',  data: monthlyCounts,  type:'bar'  },
        { name:'Volume', data: monthlyAmounts, type:'line' }
    ],
    chart: { type:'line', height:260, background:'transparent', toolbar:{ show:false } },
    stroke: { curve:'smooth', width:[0,3] },
    colors: ['#3b82f6','#22c55e'],
    xaxis: { categories: monthlyLabels, labels:{ style:{colors:'#9ca3af'} } },
    yaxis: [{ labels:{style:{colors:'#9ca3af'}} }, { opposite:true, labels:{style:{colors:'#9ca3af'}} }],
    legend: { labels:{ colors:'#9ca3af' } },
    tooltip: { theme:'dark' },
    grid: { borderColor:'#374151' },
    theme: { mode:'dark' }
}).render();
</script>
