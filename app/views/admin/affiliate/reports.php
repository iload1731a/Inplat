<?php declare(strict_types=1); ?>
<?php
$monthly       = is_array($monthly       ?? null) ? $monthly       : [];
$growth        = is_array($growth        ?? null) ? $growth        : [];
$byCurrency    = is_array($byCurrency    ?? null) ? $byCurrency    : [];
$byType        = is_array($byType        ?? null) ? $byType        : [];
$topAffiliates = is_array($topAffiliates ?? null) ? $topAffiliates : [];

$monthLabels = json_encode(array_column($monthly, 'month'));
$monthAmts   = json_encode(array_map('floatval', array_column($monthly, 'total_amount')));
$monthCounts = json_encode(array_map('intval', array_column($monthly, 'commission_count')));
$growthDays  = json_encode(array_column($growth, 'day'));
$growthCnts  = json_encode(array_map('intval', array_column($growth, 'new_referrals')));
$currLabels  = json_encode(array_column($byCurrency, 'currency_code'));
$currAmts    = json_encode(array_map('floatval', array_column($byCurrency, 'total_amount')));
require app_path('app/views/admin/_nav.php');
?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $totalComm  = array_sum(array_column($monthly, 'total_amount'));
    $totalRef   = array_sum(array_column($growth, 'new_referrals'));
    $topEarner  = !empty($topAffiliates) ? ($topAffiliates[0]['username'] ?? '—') : '—';
    $topAmount  = !empty($topAffiliates) ? number_format((float)($topAffiliates[0]['total_earned'] ?? 0), 2) : '0.00';
    $cards = [
        ['Total Commission (12m)', number_format($totalComm, 2), 'warning', 'fa-coins'],
        ['New Referrals (90d)',     number_format($totalRef),     'info',    'fa-user-plus'],
        ['Top Earner',              $topEarner,                   'success', 'fa-trophy'],
        ['Top Earner Amount',       $topAmount,                   'warning', 'fa-star'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-md-3">
        <div class="glass rounded-4 p-4 text-center">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= e($val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Monthly Commission Chart -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-chart-bar me-2 text-warning"></i>Monthly Commission (12 months)</h6>
            <div id="monthlyChart" style="min-height:240px"></div>
        </div>
    </div>
    <!-- By Currency -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-info"></i>By Currency</h6>
            <div id="currencyChart" style="min-height:240px"></div>
        </div>
    </div>
</div>

<!-- Referral Growth -->
<div class="glass rounded-4 p-4 mb-4">
    <h6 class="mb-3"><i class="fas fa-chart-area me-2 text-success"></i>Referral Growth (90 days)</h6>
    <div id="growthChart" style="min-height:200px"></div>
</div>

<!-- Currency Breakdown Table -->
<?php if ($byCurrency !== []): ?>
<div class="glass rounded-4 p-4 mb-4">
    <h6 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Commission by Currency</h6>
    <div class="table-responsive">
        <table class="table table-user">
            <thead><tr><th>Currency</th><th>Count</th><th>Total</th><th>Paid</th><th>Pending</th></tr></thead>
            <tbody>
            <?php foreach ($byCurrency as $bc): ?>
            <tr>
                <td><span class="badge bg-secondary"><?= e((string)$bc['currency_code']) ?></span></td>
                <td><?= number_format((int)$bc['cnt']) ?></td>
                <td class="font-monospace text-warning small"><?= number_format((float)$bc['total_amount'], 4) ?></td>
                <td class="font-monospace text-success small"><?= number_format((float)$bc['paid_amount'], 4) ?></td>
                <td class="font-monospace text-secondary small"><?= number_format((float)$bc['total_amount'] - (float)$bc['paid_amount'], 4) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Top Affiliates Table -->
<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Top 20 Affiliates</h6>
        <a href="/admin/affiliate/commissions/export" class="btn btn-xs btn-outline-success">
            <i class="fas fa-download me-1"></i>Export Commissions
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-user">
            <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Referrals</th><th>Qualified</th><th>Total Earned</th></tr></thead>
            <tbody>
            <?php foreach ($topAffiliates as $i => $aff): ?>
            <tr>
                <td class="text-secondary small"><?= $i + 1 ?></td>
                <td><?= e((string)$aff['username']) ?></td>
                <td class="small text-secondary"><?= e((string)$aff['email']) ?></td>
                <td><?= number_format((int)$aff['total_referrals']) ?></td>
                <td><?= number_format((int)$aff['qualified']) ?></td>
                <td class="font-monospace text-warning small"><?= number_format((float)$aff['total_earned'], 4) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($topAffiliates === []): ?>
                <tr><td colspan="6" class="text-center text-secondary py-3">No data yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const monthLabels = <?= $monthLabels ?: '[]' ?>;
const monthAmts   = <?= $monthAmts   ?: '[]' ?>;
const monthCounts = <?= $monthCounts ?: '[]' ?>;
if (monthLabels.length > 0) {
    new ApexCharts(document.getElementById('monthlyChart'), {
        series: [
            { name: 'Commission Amount', data: monthAmts, type: 'bar' },
            { name: 'Count',             data: monthCounts, type: 'line' },
        ],
        chart: { height: 240, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: monthLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: [
            { labels: { style: { colors: '#94a3b8' } } },
            { opposite: true, labels: { style: { colors: '#94a3b8' } } },
        ],
        theme: { mode: 'dark' }, colors: ['#fbbf24', '#38bdf8'],
        dataLabels: { enabled: false }, grid: { borderColor: 'rgba(148,163,184,0.1)' },
        stroke: { curve: 'smooth', width: [0, 2] }, tooltip: { theme: 'dark' },
    }).render();
}

const growthDays = <?= $growthDays ?: '[]' ?>;
const growthCnts = <?= $growthCnts ?: '[]' ?>;
if (growthDays.length > 0) {
    new ApexCharts(document.getElementById('growthChart'), {
        series: [{ name: 'New Referrals', data: growthCnts }],
        chart: { type: 'area', height: 200, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: growthDays, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        theme: { mode: 'dark' }, colors: ['#22c55e'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 }, dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' }, tooltip: { theme: 'dark' },
    }).render();
}

const currLabels = <?= $currLabels ?: '[]' ?>;
const currAmts   = <?= $currAmts   ?: '[]' ?>;
if (currLabels.length > 0) {
    new ApexCharts(document.getElementById('currencyChart'), {
        series: currAmts,
        labels: currLabels,
        chart: { type: 'donut', height: 240, background: 'transparent' },
        theme: { mode: 'dark' },
        legend: { labels: { colors: '#94a3b8' } },
        dataLabels: { style: { colors: ['#fff'] } },
        tooltip: { theme: 'dark' },
    }).render();
}
</script>
