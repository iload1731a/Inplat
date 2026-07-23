<?php declare(strict_types=1); ?>
<?php
$kpis            = is_array($kpis             ?? null) ? $kpis             : [];
$daily30         = is_array($daily30          ?? null) ? $daily30          : [];
$channelBreakdown= is_array($channelBreakdown ?? null) ? $channelBreakdown : [];
$typeBreakdown   = is_array($typeBreakdown    ?? null) ? $typeBreakdown    : [];
$topRecipients   = is_array($topRecipients    ?? null) ? $topRecipients    : [];
$recent          = is_array($recent           ?? null) ? $recent           : [];
$broadcasts      = is_array($broadcasts       ?? null) ? $broadcasts       : [];
$broadcastRows   = is_array($broadcasts['rows'] ?? null) ? $broadcasts['rows'] : [];

$totalSent        = (int)($kpis['total_sent']         ?? 0);
$totalUnread      = (int)($kpis['total_unread']        ?? 0);
$sent24h          = (int)($kpis['sent_24h']            ?? 0);
$sent7d           = (int)($kpis['sent_7d']             ?? 0);
$logSent          = (int)($kpis['log_sent']            ?? 0);
$logFailed        = (int)($kpis['log_failed']          ?? 0);
$broadcasts30d    = (int)($kpis['broadcasts_30d']      ?? 0);
$broadcastRecip   = (int)($kpis['broadcast_recipients']?? 0);
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Sent',         $totalSent,      'primary',   'fa-paper-plane'],
        ['Total Unread',       $totalUnread,    'warning',   'fa-envelope'],
        ['Sent Last 24h',      $sent24h,        'info',      'fa-clock'],
        ['Sent Last 7d',       $sent7d,         'success',   'fa-calendar-week'],
        ['Email Delivered',    $logSent,        'success',   'fa-check-double'],
        ['Email Failed',       $logFailed,      'danger',    'fa-times-circle'],
        ['Broadcasts (30d)',   $broadcasts30d,  'primary',   'fa-broadcast-tower'],
        ['Broadcast Recipients',$broadcastRecip,'info',      'fa-users'],
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
                    <div class="h4 mb-0 fw-bold"><?= number_format((int)$value) ?></div>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>30-Day Notification Volume</h6>
                <div class="d-flex gap-2">
                    <a href="/admin/notifications/history" class="btn btn-xs btn-outline-secondary">Log</a>
                    <a href="/admin/notifications/broadcast" class="btn btn-xs btn-outline-primary">Broadcast</a>
                </div>
            </div>
            <div id="volumeChart" style="min-height:200px"></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>By Channel</h6>
            <div id="channelChart" style="min-height:200px"></div>
        </div>
    </div>
</div>

<!-- BOTTOM ROW -->
<div class="row g-4 mb-4">
    <!-- TYPE BREAKDOWN -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-tags me-2 text-info"></i>Top Notification Types</h6>
            </div>
            <?php if ($typeBreakdown === []): ?>
            <div class="text-center py-4 text-secondary small">No data yet.</div>
            <?php else: ?>
            <?php
            $maxTypeCount = (int)max(array_column($typeBreakdown, 'cnt'));
            foreach ($typeBreakdown as $tb):
                $pct = $maxTypeCount > 0 ? round((int)$tb['cnt'] / $maxTypeCount * 100) : 0;
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-secondary"><?= e((string)$tb['type']) ?></span>
                    <span class="fw-semibold"><?= number_format((int)$tb['cnt']) ?></span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar bg-info" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOP RECIPIENTS -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-users me-2 text-success"></i>Top Recipients (30d)</h6>
            <?php if ($topRecipients === []): ?>
            <div class="text-center py-4 text-secondary small">No data yet.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle small">
                    <thead><tr><th>User</th><th class="text-end">Count</th></tr></thead>
                    <tbody>
                    <?php foreach ($topRecipients as $tr): ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= e((string)($tr['username'] ?? '')) ?></div>
                            <div class="text-secondary" style="font-size:.72rem"><?= e((string)($tr['user_email'] ?? '')) ?></div>
                        </td>
                        <td class="text-end fw-semibold"><?= number_format((int)$tr['cnt']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-history me-2 text-warning"></i>Recent Notifications</h6>
                <a href="/admin/notifications/history" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <?php if ($recent === []): ?>
            <div class="text-center py-4 text-secondary small">No recent notifications.</div>
            <?php else: ?>
            <div class="overflow-auto" style="max-height:280px">
            <?php foreach ($recent as $n): ?>
            <div class="d-flex align-items-start gap-2 mb-2 pb-2 border-bottom border-opacity-25">
                <i class="fas fa-bell text-secondary mt-1 flex-shrink-0 small"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="small fw-medium text-truncate"><?= e((string)($n['title'] ?? '')) ?></div>
                    <div class="text-secondary" style="font-size:.7rem">
                        <?= e((string)($n['username'] ?? '')) ?> ·
                        <span class="badge text-bg-secondary" style="font-size:.6rem"><?= e((string)($n['channel'] ?? '')) ?></span>
                        · <?= e(date('H:i', strtotime((string)($n['created_at'] ?? 'now')))) ?>
                    </div>
                </div>
                <?php if (!(bool)($n['is_read'] ?? false)): ?>
                <span class="badge bg-warning text-dark flex-shrink-0" style="font-size:.6rem">Unread</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- RECENT BROADCASTS -->
<?php if ($broadcastRows !== []): ?>
<div class="glass rounded-4 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-broadcast-tower me-2 text-primary"></i>Recent Broadcasts</h6>
        <a href="/admin/notifications/broadcast" class="btn btn-xs btn-outline-primary">New Broadcast</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Audience</th>
                    <th>Channel</th>
                    <th>Recipients</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Status</th>
                    <th>Sent By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($broadcastRows as $bc):
                $bcStatus = (string)($bc['status'] ?? 'pending');
                $bcColor  = ['pending' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'][$bcStatus] ?? 'secondary';
            ?>
            <tr>
                <td class="fw-medium"><?= e((string)($bc['title'] ?? '')) ?></td>
                <td><?= e((string)($bc['audience'] ?? '')) ?></td>
                <td><span class="badge text-bg-info"><?= e((string)($bc['channel'] ?? '')) ?></span></td>
                <td><?= number_format((int)($bc['recipient_count'] ?? 0)) ?></td>
                <td class="text-success"><?= number_format((int)($bc['sent_count'] ?? 0)) ?></td>
                <td class="text-danger"><?= number_format((int)($bc['failed_count'] ?? 0)) ?></td>
                <td><span class="badge text-bg-<?= $bcColor ?>"><?= e(ucfirst($bcStatus)) ?></span></td>
                <td><?= e((string)($bc['admin_username'] ?? '-')) ?></td>
                <td class="text-secondary"><?= e(date('M d, Y H:i', strtotime((string)($bc['created_at'] ?? 'now')))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- QUICK LINKS -->
<div class="row g-3">
    <?php
    $quickLinks = [
        ['/admin/notifications/history',       'fa-history',         'primary',   'Notification Log',    'View full dispatch history and delivery status'],
        ['/admin/notifications/broadcast',     'fa-broadcast-tower', 'warning',   'Send Broadcast',      'Send notifications to targeted user groups'],
        ['/admin/notifications/announcements', 'fa-bullhorn',        'success',   'Announcements',       'Manage platform announcements and banners'],
        ['/admin/notifications/templates',     'fa-envelope',        'info',      'Email Templates',     'Edit HTML email templates for all notification types'],
    ];
    foreach ($quickLinks as [$href, $icon, $color, $lbl, $desc]):
    ?>
    <div class="col-xl-3 col-md-6">
        <a href="<?= e($href) ?>" class="text-decoration-none">
            <div class="glass rounded-4 p-3 h-100 d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:44px;height:44px;background:rgba(var(--bs-<?= $color ?>-rgb),.15)">
                    <i class="fas <?= $icon ?> text-<?= $color ?>"></i>
                </div>
                <div>
                    <div class="fw-semibold"><?= e($lbl) ?></div>
                    <div class="text-secondary small"><?= e($desc) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    // 30-day volume chart
    const daily = <?= json_encode(array_values($daily30 ?? []), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
    const days  = daily.map(r => r.day ?? '');
    const cnts  = daily.map(r => parseInt(r.cnt ?? 0));

    new ApexCharts(document.getElementById('volumeChart'), {
        chart:  { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
        series: [{ name: 'Notifications', data: cnts }],
        xaxis:  { categories: days, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis:  { labels: { style: { colors: '#94a3b8' } } },
        colors: ['#38bdf8'],
        theme:  { mode: 'dark' },
        grid:   { borderColor: 'rgba(148,163,184,0.1)' },
        dataLabels: { enabled: false },
    }).render();

    // Channel donut chart
    const ch   = <?= json_encode(array_values($channelBreakdown ?? []), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
    const chlbl = ch.map(r => r.channel ?? '');
    const chval = ch.map(r => parseInt(r.cnt ?? 0));

    new ApexCharts(document.getElementById('channelChart'), {
        chart:  { type: 'donut', height: 200, background: 'transparent' },
        series: chval,
        labels: chlbl,
        colors: ['#38bdf8','#a78bfa','#4ade80','#f97316'],
        theme:  { mode: 'dark' },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        dataLabels: { style: { colors: ['#fff'] } },
    }).render();
})();
</script>
