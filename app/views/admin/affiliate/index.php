<?php declare(strict_types=1); ?>
<?php
$kpis          = is_array($kpis          ?? null) ? $kpis          : [];
$daily30       = is_array($daily30       ?? null) ? $daily30       : [];
$byType        = is_array($byType        ?? null) ? $byType        : [];
$topAffiliates = is_array($topAffiliates ?? null) ? $topAffiliates : [];
$tiers         = is_array($tiers         ?? null) ? $tiers         : [];

$dailyLabels = json_encode(array_column($daily30, 'day'));
$dailyAmts   = json_encode(array_map('floatval', array_column($daily30, 'total_amount')));
$typeLabels  = json_encode(array_column($byType,  'commission_type'));
$typeAmts    = json_encode(array_map('floatval', array_column($byType,  'total_amount')));
require app_path('app/views/admin/_nav.php');
?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Affiliates',      (int)($kpis['total_affiliates']         ?? 0), 'info',    'fa-users'],
        ['Total Referrals',       (int)($kpis['total_referrals']          ?? 0), 'primary', 'fa-user-plus'],
        ['Qualified',             (int)($kpis['qualified_referrals']      ?? 0), 'success', 'fa-user-check'],
        ['Commissions Generated', number_format((float)($kpis['total_commission_generated'] ?? 0), 2), 'warning', 'fa-coins'],
        ['Pending Commission',    number_format((float)($kpis['pending_commission'] ?? 0), 2), 'secondary', 'fa-clock'],
        ['Paid Commission',       number_format((float)($kpis['paid_commission']    ?? 0), 2), 'success',   'fa-check-circle'],
        ['Pending Payouts',       (int)($kpis['pending_payouts']          ?? 0), 'danger',  'fa-money-bill-wave'],
        ['Total Paid Out',        number_format((float)($kpis['total_paid_out']     ?? 0), 2), 'info',    'fa-arrow-circle-up'],
        ['New Referrals (30d)',   (int)($kpis['new_referrals_30d']        ?? 0), 'primary', 'fa-calendar'],
        ['Commission (30d)',      number_format((float)($kpis['commission_30d']     ?? 0), 2), 'warning', 'fa-chart-bar'],
        ['Commissions (24h)',     (int)($kpis['commissions_24h']          ?? 0), 'info',    'fa-clock'],
        ['Active Referrals',      (int)($kpis['active_referrals']         ?? 0), 'success', 'fa-bolt'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="glass rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-3 d-flex align-items-center justify-content-center"
                     style="width:38px;height:38px;background:rgba(var(--bs-<?= $color ?>-rgb),.15)">
                    <i class="fas <?= $icon ?> text-<?= $color ?>" style="font-size:.9rem"></i>
                </div>
                <div>
                    <div class="fw-bold small"><?= is_int($val) ? number_format($val) : e((string)$val) ?></div>
                    <div class="text-secondary" style="font-size:.7rem"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2 text-warning"></i>30-Day Commission Volume</h6>
                <div class="d-flex gap-2">
                    <a href="/admin/affiliate/commissions" class="btn btn-xs btn-outline-secondary">All Commissions</a>
                    <a href="/admin/affiliate/reports" class="btn btn-xs btn-outline-warning">Reports</a>
                </div>
            </div>
            <div id="dailyChart" style="min-height:220px"></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-info"></i>By Type</h6>
            <div id="typeChart" style="min-height:220px"></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Top Affiliates -->
    <div class="col-xl-7">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Top Affiliates</h6>
                <a href="/admin/affiliate/affiliates" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>#</th><th>Username</th><th>Referrals</th><th>Qualified</th><th>Total Earned</th></tr></thead>
                    <tbody>
                    <?php foreach ($topAffiliates as $i => $aff): ?>
                        <tr>
                            <td class="small text-secondary"><?= $i + 1 ?></td>
                            <td><?= e((string)$aff['username']) ?></td>
                            <td><?= number_format((int)$aff['total_referrals']) ?></td>
                            <td><?= number_format((int)$aff['qualified']) ?></td>
                            <td class="font-monospace small text-warning"><?= number_format((float)$aff['total_earned'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($topAffiliates === []): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-3">No data yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Commission Tiers -->
    <div class="col-xl-5">
        <div class="glass rounded-4 p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-layer-group me-2 text-success"></i>Commission Tiers</h6>
                <a href="/admin/affiliate/tiers" class="btn btn-xs btn-outline-success">Edit</a>
            </div>
            <?php if ($tiers === []): ?>
            <div class="text-secondary small text-center py-3">No tiers configured.</div>
            <?php else: ?>
            <?php foreach ($tiers as $tier): ?>
            <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary border-opacity-25">
                <div>
                    <span class="badge bg-info me-2">L<?= (int)$tier['level'] ?></span>
                    <?= e((string)$tier['label']) ?>
                    <?php if (!(int)$tier['is_active']): ?><span class="badge bg-secondary ms-1">Inactive</span><?php endif; ?>
                </div>
                <div class="text-warning fw-bold"><?= number_format((float)$tier['commission_rate'], 2) ?>%</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Nav -->
<div class="glass rounded-4 p-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/affiliate/affiliates"  class="btn btn-sm btn-outline-info"><i class="fas fa-users me-1"></i>Affiliates</a>
        <a href="/admin/affiliate/commissions" class="btn btn-sm btn-outline-warning"><i class="fas fa-coins me-1"></i>Commissions</a>
        <a href="/admin/affiliate/payouts"     class="btn btn-sm btn-outline-danger"><i class="fas fa-money-bill-wave me-1"></i>Payouts</a>
        <a href="/admin/affiliate/tiers"       class="btn btn-sm btn-outline-success"><i class="fas fa-layer-group me-1"></i>Tiers</a>
        <a href="/admin/affiliate/settings"    class="btn btn-sm btn-outline-secondary"><i class="fas fa-cog me-1"></i>Settings</a>
        <a href="/admin/affiliate/reports"     class="btn btn-sm btn-outline-primary"><i class="fas fa-chart-area me-1"></i>Reports</a>
    </div>
</div>

<script>
const dailyLabels = <?= $dailyLabels ?: '[]' ?>;
const dailyAmts   = <?= $dailyAmts   ?: '[]' ?>;
if (dailyLabels.length > 0) {
    new ApexCharts(document.getElementById('dailyChart'), {
        series: [{ name: 'Commission', data: dailyAmts }],
        chart: { type: 'bar', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: dailyLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        theme: { mode: 'dark' }, colors: ['#fbbf24'],
        dataLabels: { enabled: false }, grid: { borderColor: 'rgba(148,163,184,0.1)' },
        tooltip: { theme: 'dark' },
    }).render();
}
const typeLabels = <?= $typeLabels ?: '[]' ?>;
const typeAmts   = <?= $typeAmts   ?: '[]' ?>;
if (typeLabels.length > 0) {
    new ApexCharts(document.getElementById('typeChart'), {
        series: typeAmts,
        labels: typeLabels,
        chart: { type: 'donut', height: 220, background: 'transparent' },
        theme: { mode: 'dark' },
        legend: { labels: { colors: '#94a3b8' } },
        dataLabels: { style: { colors: ['#fff'] } },
        tooltip: { theme: 'dark' },
    }).render();
}
</script>
