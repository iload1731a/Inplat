<?php declare(strict_types=1);
$summary      = is_array($summary ?? null)       ? $summary       : [];
$daily        = is_array($daily ?? null)         ? $daily         : [];
$typeBreakdown= is_array($typeBreakdown ?? null)  ? $typeBreakdown : [];
$highRisk     = is_array($highRisk ?? null)      ? $highRisk      : [];
$dateFrom     = (string)($dateFrom ?? date('Y-m-01'));
$dateTo       = (string)($dateTo   ?? date('Y-m-d'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-shield-alt me-2 text-success"></i>KYC Compliance Report</h1>
        <p class="text-secondary mb-0">AML/KYC compliance overview and analytics.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/kyc" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <a href="/admin/kyc/export?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-sm btn-outline-success">
            <i class="fas fa-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Date Filter -->
<div class="glass rounded-3 p-3 mb-4">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-md-3">
            <label class="form-label small text-secondary">Date From</label>
            <input type="date" class="form-control" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small text-secondary">Date To</label>
            <input type="date" class="form-control" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit">Apply</button>
        </div>
        <div class="col-md-4 text-end text-secondary small align-self-end">
            Report period: <strong><?= e($dateFrom) ?> — <?= e($dateTo) ?></strong>
        </div>
    </form>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <?php
    $total_sub   = (int)($summary['total_submissions'] ?? 0);
    $approved_s  = (int)($summary['approved'] ?? 0);
    $rejected_s  = (int)($summary['rejected'] ?? 0);
    $pending_s   = (int)($summary['pending']  ?? 0);
    $uniqueUsers = (int)($summary['unique_users'] ?? 0);
    $avgHours    = round((float)($summary['avg_review_hours'] ?? 0), 1);
    $approvalRate= $total_sub > 0 ? round($approved_s / $total_sub * 100, 1) : 0;

    $cards = [
        ['Total Submissions', $total_sub,     'fa-upload',       '#38bdf8'],
        ['Approved',          $approved_s,    'fa-check-circle', '#34d399'],
        ['Rejected',          $rejected_s,    'fa-times-circle', '#f87171'],
        ['Pending',           $pending_s,     'fa-clock',        '#f59e0b'],
        ['Unique Users',      $uniqueUsers,   'fa-users',        '#a78bfa'],
        ['Approval Rate',     $approvalRate.'%', 'fa-percentage','#4ade80'],
        ['Avg Review Time',   $avgHours.'h',  'fa-stopwatch',    '#e879f9'],
        ['High Risk Users',   count($highRisk),'fa-exclamation-triangle','#fb923c'],
    ];
    foreach ($cards as [$label, $val, $icon, $color]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-3 p-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="fas <?= $icon ?> small" style="color:<?= $color ?>"></i>
                <span class="text-secondary small"><?= $label ?></span>
            </div>
            <div class="fw-bold fs-5"><?= is_numeric($val) ? number_format((float)$val) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Daily trend chart -->
    <div class="col-lg-8">
        <div class="glass rounded-3 p-3">
            <div class="fw-semibold mb-3"><i class="fas fa-chart-line me-2 text-info"></i>Daily Submission Trend</div>
            <div id="complianceChart"></div>
        </div>
    </div>

    <!-- Document type breakdown -->
    <div class="col-lg-4">
        <div class="glass rounded-3 p-3 h-100">
            <div class="fw-semibold mb-3"><i class="fas fa-list me-2 text-warning"></i>Document Type Breakdown</div>
            <?php if (empty($typeBreakdown)): ?>
            <div class="text-secondary small text-center py-3">No data.</div>
            <?php else: ?>
            <?php foreach ($typeBreakdown as $tb):
                $tbTotal = (int)($tb['total'] ?? 0);
                $tbApproved = (int)($tb['approved'] ?? 0);
                $tbPct = $tbTotal > 0 ? round($tbApproved / $tbTotal * 100) : 0;
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= e(ucwords(str_replace('_',' ',(string)$tb['document_type']))) ?></span>
                    <span class="text-secondary"><?= $tbTotal ?> | <?= $tbPct ?>% approved</span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar bg-success" style="width:<?= $tbPct ?>%"></div>
                    <?php $rejPct = $tbTotal > 0 ? round((int)$tb['rejected'] / $tbTotal * 100) : 0; ?>
                    <div class="progress-bar bg-danger" style="width:<?= $rejPct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- High Risk Users Table -->
<?php if (!empty($highRisk)): ?>
<div class="glass rounded-3 p-3 mb-4">
    <div class="fw-semibold mb-3"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>High-Risk Users</div>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle small mb-0">
            <thead class="text-secondary">
                <tr><th>User</th><th>Email</th><th>KYC Status</th><th>Risk Score</th><th>Risk Level</th><th>Last Assessed</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($highRisk as $u):
                $rl2 = (string)($u['risk_level'] ?? 'low');
                $rc2 = match($rl2) { 'critical' => 'danger', 'high' => 'warning', default => 'info' };
            ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($u['username'] ?? '-')) ?></td>
                    <td class="text-secondary"><?= e((string)($u['email'] ?? '')) ?></td>
                    <td><span class="badge bg-<?= ['approved'=>'success','pending'=>'warning','rejected'=>'danger'][$u['kyc_status']??''] ?? 'secondary' ?>"><?= e(ucfirst((string)($u['kyc_status']??'-'))) ?></span></td>
                    <td>
                        <div class="progress" style="height:6px;width:60px">
                            <div class="progress-bar bg-<?= $rc2 ?>" style="width:<?= (int)($u['risk_score']??0) ?>%"></div>
                        </div>
                        <span class="text-<?= $rc2 ?> small"><?= (int)($u['risk_score']??0) ?>/100</span>
                    </td>
                    <td><span class="badge bg-<?= $rc2 ?>"><?= e(ucfirst($rl2)) ?></span></td>
                    <td class="text-secondary"><?= e(date('M d, Y', strtotime((string)($u['last_assessed_at']??'now')))) ?></td>
                    <td class="text-end">
                        <a href="/admin/kyc/user?user_id=<?= (int)$u['user_id'] ?>" class="btn btn-xs btn-outline-secondary">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const daily = <?= json_encode(array_values($daily)) ?>;
if (daily.length && typeof ApexCharts !== 'undefined') {
    new ApexCharts(document.getElementById('complianceChart'), {
        chart: { type: 'area', height: 260, background: 'transparent', toolbar: { show: false } },
        series: [
            { name: 'Submitted', data: daily.map(d => ({ x: d.day, y: parseInt(d.total||0) })) },
            { name: 'Approved',  data: daily.map(d => ({ x: d.day, y: parseInt(d.approved||0) })) },
            { name: 'Rejected',  data: daily.map(d => ({ x: d.day, y: parseInt(d.rejected||0) })) },
        ],
        colors: ['#38bdf8','#34d399','#f87171'],
        xaxis: { type: 'datetime', labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        legend: { labels: { colors: '#cbd5e1' } },
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.2, opacityTo: 0 } },
        grid: { borderColor: '#334155' },
        tooltip: { theme: 'dark' },
    }).render();
}
</script>
