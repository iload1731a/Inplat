<?php declare(strict_types=1); ?>
<?php
$daily          = is_array($daily           ?? null) ? $daily           : [];
$resolutionDist = is_array($resolution_dist ?? null) ? $resolution_dist : [];
$csatTrend      = is_array($csat_trend      ?? null) ? $csat_trend      : [];
$categoryVolume = is_array($category_volume ?? null) ? $category_volume : [];
$slaBreaches    = is_array($sla_breaches    ?? null) ? $sla_breaches    : [];
$days           = (int)($days ?? 30);
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1"><i class="fas fa-chart-line me-2 text-info"></i>Ticket Analytics</h5>
        <span class="text-secondary small">Last <?= $days ?> days</span>
    </div>
    <form method="get" action="/admin/tickets/analytics" class="d-flex gap-2">
        <?php foreach ([7,14,30,60,90] as $d): ?>
            <a href="?days=<?= $d ?>" class="btn btn-xs <?= $days === $d ? 'btn-info' : 'btn-outline-secondary' ?>">
                <?= $d ?>D
            </a>
        <?php endforeach; ?>
    </form>
</div>

<!-- Volume + CSAT Row -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Daily Ticket Volume</h6>
            <div id="volumeChart"></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-star me-2 text-warning"></i>CSAT Trend</h6>
            <div id="csatChart"></div>
        </div>
    </div>
</div>

<!-- Resolution Time + Category Volume -->
<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-clock me-2 text-success"></i>Resolution Time</h6>
            <?php
            $rd    = $resolutionDist;
            $total = array_sum([(int)($rd['lt4h']??0),(int)($rd['h4_24']??0),(int)($rd['h24_72']??0),(int)($rd['gt72h']??0)]);
            $buckets = [
                ['< 4 hrs',    (int)($rd['lt4h']   ?? 0), 'success'],
                ['4–24 hrs',   (int)($rd['h4_24']  ?? 0), 'info'],
                ['1–3 days',   (int)($rd['h24_72'] ?? 0), 'warning'],
                ['> 3 days',   (int)($rd['gt72h']  ?? 0), 'danger'],
            ];
            foreach ($buckets as [$label, $count, $color]):
                $pct = $total > 0 ? round($count / $total * 100) : 0;
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= e($label) ?></span>
                    <span><?= number_format($count) ?> <span class="text-secondary">(<?= $pct ?>%)</span></span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar bg-<?= e($color) ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Tickets by Category</h6>
            <div id="catChart"></div>
        </div>
    </div>
</div>

<!-- SLA BREACHES -->
<?php if ($slaBreaches !== []): ?>
<div class="glass rounded-4 p-4">
    <h6 class="mb-3">
        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>SLA Breaches
        <span class="badge bg-danger ms-1"><?= count($slaBreaches) ?></span>
    </h6>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead><tr><th>Ticket</th><th>User</th><th>Subject</th><th>Priority</th><th>Age</th><th>SLA</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($slaBreaches as $sb): ?>
                <?php
                $pri = (string)($sb['priority'] ?? 'medium');
                $pc  = match($pri){'urgent'=>'danger','high'=>'warning','medium'=>'info',default=>'secondary'};
                $ageH = (int)($sb['age_hrs'] ?? 0);
                $slaH = (int)($sb['sla_hours'] ?? 24);
                $breach = $ageH - $slaH;
                ?>
                <tr>
                    <td class="small"><?= e((string)($sb['ticket_number'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($sb['username'] ?? '-')) ?></td>
                    <td class="small text-truncate" style="max-width:180px"><?= e((string)($sb['subject'] ?? '')) ?></td>
                    <td><span class="badge bg-<?= $pc ?>"><?= e($pri) ?></span></td>
                    <td class="small text-danger"><?= $ageH ?>h old</td>
                    <td class="small">SLA: <?= $slaH ?>h <span class="text-danger">(+<?= $breach ?>h breach)</span></td>
                    <td>
                        <a href="/admin/tickets/detail?id=<?= (int)($sb['id'] ?? 0) ?>" class="btn btn-xs btn-outline-danger">
                            <i class="fas fa-fire me-1"></i>Handle
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
// Volume chart
(function(){
    const raw  = <?= json_encode($daily, JSON_THROW_ON_ERROR) ?>;
    const days  = raw.map(r => r.day);
    const newT  = raw.map(r => parseInt(r.new_tickets)||0);
    const urgent = raw.map(r => parseInt(r.urgent_count)||0);
    new ApexCharts(document.getElementById('volumeChart'), {
        chart: { type:'bar', height:250, background:'transparent', toolbar:{show:false}, stacked:false },
        theme: { mode:'dark' },
        series: [
            { name:'New Tickets', data: newT },
            { name:'Urgent',      data: urgent },
        ],
        xaxis: { categories: days, labels:{ rotate:-45, style:{fontSize:'10px'} } },
        colors: ['#6366f1','#ef4444'],
        dataLabels:{ enabled:false },
        grid:{ borderColor:'rgba(148,163,184,.1)' },
    }).render();
})();

// CSAT trend line
(function(){
    const raw  = <?= json_encode($csatTrend, JSON_THROW_ON_ERROR) ?>;
    if (!raw.length) { document.getElementById('csatChart').innerHTML = '<div class="text-secondary text-center py-4">No CSAT data yet</div>'; return; }
    new ApexCharts(document.getElementById('csatChart'), {
        chart: { type:'line', height:200, background:'transparent', toolbar:{show:false} },
        theme: { mode:'dark' },
        series: [{ name:'Avg Rating', data: raw.map(r => parseFloat(r.avg_rating||0).toFixed(2)) }],
        xaxis:  { categories: raw.map(r => r.day), labels:{ rotate:-45, style:{fontSize:'9px'} } },
        yaxis:  { min:1, max:5 },
        colors: ['#f59e0b'],
        stroke: { curve:'smooth', width:2 },
        markers:{ size:3 },
        dataLabels:{ enabled:false },
        grid:{ borderColor:'rgba(148,163,184,.1)' },
    }).render();
})();

// Category donut
(function(){
    const raw = <?= json_encode($categoryVolume, JSON_THROW_ON_ERROR) ?>;
    if (!raw.length) return;
    new ApexCharts(document.getElementById('catChart'), {
        chart: { type:'bar', height:220, background:'transparent', toolbar:{show:false} },
        theme: { mode:'dark' },
        series: [{ name:'Tickets', data: raw.map(r => parseInt(r.cnt)||0) }],
        xaxis:  { categories: raw.map(r => r.label) },
        colors: ['#38bdf8'],
        dataLabels:{ enabled:false },
        plotOptions:{ bar:{ borderRadius:4, horizontal:false } },
        grid:{ borderColor:'rgba(148,163,184,.1)' },
    }).render();
})();
</script>
