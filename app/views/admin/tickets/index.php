<?php declare(strict_types=1); ?>
<?php
$kpi        = is_array($kpi       ?? null) ? $kpi       : [];
$daily      = is_array($daily     ?? null) ? $daily     : [];
$categories = is_array($categories ?? null) ? $categories : [];
$priorities = is_array($priorities ?? null) ? $priorities : [];
$agents     = is_array($agents    ?? null) ? $agents    : [];
$csat       = is_array($csat      ?? null) ? $csat      : [];
$recent     = is_array($recent    ?? null) ? $recent    : [];

$totalTickets  = (int)($kpi['total_tickets']   ?? 0);
$openTickets   = (int)($kpi['open_tickets']    ?? 0);
$resolvedCount = (int)($kpi['resolved_tickets'] ?? 0);
$urgentOpen    = (int)($kpi['urgent_open']     ?? 0);
$unassigned    = (int)($kpi['unassigned_count'] ?? 0);
$waitingUser   = (int)($kpi['waiting_on_user'] ?? 0);
$todayNew      = (int)($kpi['today_new']       ?? 0);
$avgResHrs     = round((float)($kpi['avg_resolution_hrs'] ?? 0), 1);
$csatAvg       = round((float)($csat['avg_rating']   ?? 0), 2);
$csatTotal     = (int)($csat['total_ratings'] ?? 0);
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Tickets',       $totalTickets,  'primary', 'fa-ticket'],
        ['Open',                $openTickets,   'warning', 'fa-folder-open'],
        ['Resolved',            $resolvedCount, 'success', 'fa-check-circle'],
        ['Urgent Open',         $urgentOpen,    'danger',  'fa-fire'],
        ['Unassigned',          $unassigned,    'info',    'fa-user-clock'],
        ['Waiting on User',     $waitingUser,   'secondary','fa-hourglass-half'],
        ['New Today',           $todayNew,      'primary', 'fa-calendar-day'],
        ['Avg Resolution (hrs)',$avgResHrs,     'info',    'fa-clock'],
    ];
    foreach ($cards as [$label, $value, $color, $icon]):
    ?>
    <div class="col-xl-3 col-md-4 col-6">
        <div class="glass rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(var(--bs-<?= $color ?>-rgb),.15)">
                    <i class="fas <?= $icon ?> text-<?= $color ?>"></i>
                </div>
                <div>
                    <div class="h4 mb-0 fw-bold"><?= is_float($value) ? number_format($value, 1) : number_format((int)$value) ?></div>
                    <div class="small text-secondary"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- CHARTS ROW -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>30-Day Ticket Volume</h6>
            <div id="volumeChart"></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>By Category</h6>
            <div id="catChart"></div>
        </div>
    </div>
</div>

<!-- BOTTOM ROW -->
<div class="row g-4 mb-4">
    <!-- CSAT -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-star me-2 text-warning"></i>Customer Satisfaction</h6>
            <div class="text-center mb-3">
                <div class="display-5 fw-bold text-warning"><?= number_format($csatAvg, 2) ?><span class="small text-secondary">/5</span></div>
                <div class="small text-secondary"><?= number_format($csatTotal) ?> ratings</div>
            </div>
            <?php if ($csatAvg > 0): ?>
            <div class="progress" style="height:8px">
                <div class="progress-bar bg-warning" style="width:<?= min(100, $csatAvg / 5 * 100) ?>%"></div>
            </div>
            <?php endif; ?>
            <div class="mt-3">
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span>Positive (4–5 ★)</span>
                    <span><?= number_format((int)($csat['positive'] ?? 0)) ?></span>
                </div>
                <div class="d-flex justify-content-between small text-secondary">
                    <span>Negative (1–2 ★)</span>
                    <span><?= number_format((int)($csat['negative'] ?? 0)) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Performance -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-user-tie me-2 text-info"></i>Agent Performance</h6>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Agent</th><th>Handled</th><th>Resolved</th><th>Avg Hours</th></tr></thead>
                    <tbody>
                    <?php foreach ($agents as $a): ?>
                        <tr>
                            <td><?= e((string)($a['agent_name'] ?? '-')) ?></td>
                            <td><?= (int)($a['total_handled'] ?? 0) ?></td>
                            <td><span class="badge bg-success"><?= (int)($a['resolved'] ?? 0) ?></span></td>
                            <td><?= $a['avg_hrs'] !== null ? round((float)$a['avg_hrs'], 1) . 'h' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($agents === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary">No agent data yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- RECENT TICKETS -->
<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Recent Tickets</h6>
        <a href="/admin/tickets/list" class="btn btn-outline-light btn-sm">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Subject</th><th>User</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <?php
                $pri = (string)($row['priority'] ?? 'medium');
                $pc  = match($pri){ 'urgent' => 'danger','high' => 'warning','medium' => 'info', default => 'secondary' };
                $st  = (string)($row['status'] ?? 'open');
                $sc  = match($st){ 'resolved','closed' => 'success','in_progress' => 'primary','waiting_on_user' => 'info', default => 'warning' };
                ?>
                <tr>
                    <td><span class="small text-secondary"><?= e((string)($row['ticket_number'] ?? '#')) ?></span></td>
                    <td style="max-width:200px"><div class="text-truncate"><?= e((string)($row['subject'] ?? '')) ?></div></td>
                    <td class="small"><?= e((string)($row['username'] ?? '-')) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($row['category'] ?? '-')) ?></span></td>
                    <td><span class="badge bg-<?= $pc ?>"><?= e($pri) ?></span></td>
                    <td><span class="badge bg-<?= $sc ?>"><?= e(str_replace('_',' ',$st)) ?></span></td>
                    <td class="small"><?= e((string)($row['assigned_name'] ?? 'Unassigned')) ?></td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($row['updated_at'] ?? 'now')))) ?></td>
                    <td><a href="/admin/tickets/detail?id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-xs btn-outline-info"><i class="fas fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recent === []): ?>
                <tr><td colspan="9" class="text-center text-secondary py-3">No tickets yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
// 30-day volume chart
(function(){
    const raw = <?= json_encode($daily) ?: '[]' ?>;
    const dates = raw.map(r => r.date_label);
    const newT  = raw.map(r => parseInt(r.new_tickets)||0);
    new ApexCharts(document.getElementById('volumeChart'), {
        chart: { type:'bar', height:240, background:'transparent', toolbar:{show:false} },
        theme: { mode:'dark' },
        series: [{ name:'New', data: newT }],
        xaxis: { categories: dates, labels:{ rotate:-45, style:{fontSize:'10px'} } },
        colors: ['#6366f1'],
        dataLabels:{ enabled:false },
        grid:{ borderColor:'rgba(148,163,184,.1)' },
    }).render();
})();

// Category donut
(function(){
    const raw = <?= json_encode($categories) ?: '[]' ?>;
    if (!raw.length) return;
    new ApexCharts(document.getElementById('catChart'), {
        chart: { type:'donut', height:240, background:'transparent' },
        theme: { mode:'dark' },
        series: raw.map(r => parseInt(r.total)||0),
        labels: raw.map(r => r.category_name || r.category || 'Other'),
        legend: { position:'bottom', fontSize:'11px' },
        dataLabels:{ enabled:false },
    }).render();
})();
</script>
