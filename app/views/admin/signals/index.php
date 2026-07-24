<?php declare(strict_types=1);
$kpis           = (array)($kpis ?? []);
$daily          = (array)($daily ?? []);
$topProviders   = (array)($topProviders ?? []);
$recentActivity = (array)($recentActivity ?? []);
$alertStats     = (array)($alertStats ?? []);
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Signals',       $kpis['total_signals']      ?? 0,  'fa-chart-bar',      '#38bdf8'],
        ['Active Signals',      $kpis['active_signals']     ?? 0,  'fa-broadcast-tower','#34d399'],
        ['Platform Win Rate',   number_format((float)($kpis['platform_win_rate'] ?? 0), 1) . '%', 'fa-trophy', '#f59e0b'],
        ['Signal Providers',    $kpis['provider_count']     ?? 0,  'fa-satellite-dish', '#a78bfa'],
        ['Active Alerts',       $kpis['active_alerts']      ?? 0,  'fa-bell',           '#fb923c'],
        ['Alerts Triggered 24h',$kpis['triggered_24h']      ?? 0,  'fa-bolt',           '#e879f9'],
        ['Active Rules',        $kpis['active_rules']       ?? 0,  'fa-robot',          '#22d3ee'],
        ['Rule Executions',     $kpis['total_executions']   ?? 0,  'fa-play-circle',    '#4ade80'],
    ];
    foreach ($cards as [$label, $val, $icon, $color]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-3 p-3">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fas <?= $icon ?>" style="color:<?= $color ?>"></i>
                <span class="text-secondary small"><?= $label ?></span>
            </div>
            <div class="fw-bold fs-4"><?= $val ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Chart + Top Providers -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-3 p-3 h-100">
            <div class="fw-semibold mb-3"><i class="fas fa-chart-area me-2 text-info"></i>30-Day Signal Volume & Win/Loss</div>
            <div id="signalVolumeChart"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-3 p-3 h-100">
            <div class="fw-semibold mb-3"><i class="fas fa-trophy me-2 text-warning"></i>Top Providers (30d Win Rate)</div>
            <?php if (empty($topProviders)): ?>
            <div class="text-center text-secondary py-4 small">No provider data yet.</div>
            <?php else: ?>
            <?php foreach ($topProviders as $p):
                $wr    = (float)$p['win_rate'];
                $wrCol = $wr >= 60 ? 'success' : ($wr >= 40 ? 'warning' : 'danger');
            ?>
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between">
                        <span class="small fw-semibold text-truncate"><?= e($p['name']) ?></span>
                        <span class="small text-<?= $wrCol ?> fw-bold"><?= number_format($wr, 1) ?>%</span>
                    </div>
                    <div class="progress mt-1" style="height:4px">
                        <div class="progress-bar bg-<?= $wrCol ?>" style="width:<?= min(100, $wr) ?>%"></div>
                    </div>
                    <div class="text-secondary mt-1" style="font-size:.65rem">
                        <?= (int)$p['subscribers'] ?> subs · <?= (int)$p['total_signals'] ?> signals
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <div class="mt-3">
                <a href="/admin/signals/providers" class="btn btn-xs btn-outline-secondary w-100">Manage Providers</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick actions row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="/admin/signals/list" class="glass rounded-3 p-3 d-flex align-items-center gap-3 text-decoration-none text-light">
            <i class="fas fa-list text-info fa-lg"></i>
            <div><div class="fw-semibold small">All Signals</div><div class="text-secondary" style="font-size:.75rem">Browse and manage</div></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/admin/signals/providers" class="glass rounded-3 p-3 d-flex align-items-center gap-3 text-decoration-none text-light">
            <i class="fas fa-satellite-dish text-warning fa-lg"></i>
            <div><div class="fw-semibold small">Providers</div><div class="text-secondary" style="font-size:.75rem">Manage signal sources</div></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/admin/signals/alerts" class="glass rounded-3 p-3 d-flex align-items-center gap-3 text-decoration-none text-light">
            <i class="fas fa-bell text-danger fa-lg"></i>
            <div><div class="fw-semibold small">Price Alerts</div><div class="text-secondary" style="font-size:.75rem"><?= (int)($alertStats['active_alerts'] ?? 0) ?> active</div></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/admin/signals/performance" class="glass rounded-3 p-3 d-flex align-items-center gap-3 text-decoration-none text-light">
            <i class="fas fa-chart-pie text-success fa-lg"></i>
            <div><div class="fw-semibold small">Performance</div><div class="text-secondary" style="font-size:.75rem">Win rates & returns</div></div>
        </a>
    </div>
</div>

<!-- Recent Activity -->
<div class="glass rounded-3">
    <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
        <div class="fw-semibold"><i class="fas fa-history me-2 text-secondary"></i>Recent Signal Activity</div>
        <a href="/admin/signals/list" class="btn btn-xs btn-outline-secondary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-user mb-0">
            <thead>
                <tr><th>Pair</th><th>Type</th><th>Provider</th><th>Status</th><th>Result</th><th>Published</th></tr>
            </thead>
            <tbody>
            <?php if (empty($recentActivity)): ?>
            <tr><td colspan="6" class="text-center text-secondary py-3">No signals yet.</td></tr>
            <?php else: ?>
            <?php foreach ($recentActivity as $sig):
                $tc = match($sig['signal_type']) {
                    'buy','close_short'  => 'success',
                    'sell','close_long'  => 'danger',
                    'hold'               => 'warning',
                    default              => 'secondary',
                };
                $sc = match($sig['status']) {
                    'hit_tp' => 'success', 'hit_sl' => 'danger', 'active' => 'info', default => 'secondary'
                };
            ?>
            <tr>
                <td class="fw-semibold"><?= e($sig['pair_symbol'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $tc ?>"><?= e($sig['signal_type']) ?></span></td>
                <td><?= e($sig['provider_name']) ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= e($sig['status']) ?></span></td>
                <td>
                    <?php if ($sig['profit_pct'] !== null): ?>
                    <span class="<?= $sig['profit_pct'] >= 0 ? 'text-profit' : 'text-loss' ?> fw-semibold small">
                        <?= $sig['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$sig['profit_pct'], 2) ?>%
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small text-secondary"><?= e(date('M d H:i', strtotime($sig['published_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    const daily  = <?= json_encode(array_values($daily)) ?>;
    const labels = daily.map(d => d.day);
    const total  = daily.map(d => parseInt(d.total)  || 0);
    const won    = daily.map(d => parseInt(d.won)    || 0);
    const lost   = daily.map(d => parseInt(d.lost)   || 0);

    new ApexCharts(document.getElementById('signalVolumeChart'), {
        chart: { type: 'bar', height: 280, stacked: true, background: 'transparent',
                 toolbar: { show: false }, animations: { enabled: false } },
        theme: { mode: 'dark' },
        series: [
            { name: 'Hit TP', data: won },
            { name: 'Hit SL', data: lost },
            { name: 'Other',  data: daily.map((d,i) => Math.max(0, total[i] - won[i] - lost[i])) },
        ],
        colors: ['#34d399', '#f87171', '#94a3b8'],
        xaxis:  { categories: labels, labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        yaxis:  { labels: { style: { colors: '#94a3b8' } } },
        legend: { labels: { colors: '#e2e8f0' } },
        grid:   { borderColor: 'rgba(148,163,184,.1)' },
        tooltip: { theme: 'dark' },
    }).render();
})();
</script>
