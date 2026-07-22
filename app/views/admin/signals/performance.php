<?php declare(strict_types=1);
$providers   = (array)($providers ?? []);
$performance = (array)($performance ?? []);
$daily       = (array)($daily ?? []);
$top         = (array)($top ?? []);
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- 90-day chart -->
<div class="glass rounded-3 p-3 mb-4">
    <div class="fw-semibold mb-3"><i class="fas fa-chart-area me-2 text-info"></i>90-Day Signal Results</div>
    <div id="perfChart"></div>
</div>

<!-- Top performers table -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="glass rounded-3 p-3">
            <div class="fw-semibold mb-3"><i class="fas fa-trophy me-2 text-warning"></i>Provider Performance Leaderboard (30d)</div>
            <div class="table-responsive">
                <table class="table table-user mb-0" id="perfTable">
                    <thead>
                        <tr>
                            <th>Provider</th><th>Type</th>
                            <th>30d Signals</th><th>Win Rate</th><th>Avg Profit</th><th>Avg Loss</th>
                            <th>Avg R:R</th><th>Total Return</th><th>Subscribers</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top as $p):
                        $wr  = (float)$p['win_rate'];
                        $wrc = $wr >= 60 ? 'success' : ($wr >= 40 ? 'warning' : 'danger');
                        $ret = (float)$p['total_return_pct'];
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($p['name']) ?></td>
                        <td class="small text-secondary"><?= e($p['slug']) ?></td>
                        <td><?= (int)$p['total_signals'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-<?= $wrc ?> fw-semibold"><?= number_format($wr, 1) ?>%</span>
                                <div class="progress flex-grow-1" style="height:4px;min-width:40px">
                                    <div class="progress-bar bg-<?= $wrc ?>" style="width:<?= min(100, $wr) ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-success"><?= number_format((float)$p['avg_profit_pct'], 2) ?>%</td>
                        <td class="text-danger">0.00%</td>
                        <td>—</td>
                        <td class="<?= $ret >= 0 ? 'text-profit' : 'text-loss' ?> fw-semibold">
                            <?= $ret >= 0 ? '+' : '' ?><?= number_format($ret, 2) ?>%
                        </td>
                        <td><?= (int)$p['subscribers'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Per-provider detailed performance -->
<?php foreach ($providers as $p):
    $pid = (int)$p['id'];
    $p30 = $performance[$pid]['30d'] ?? [];
    $p90 = $performance[$pid]['90d'] ?? [];
    $pAll= $performance[$pid]['all'] ?? [];
    if (empty($p30) && empty($p90) && empty($pAll)) continue;
?>
<div class="glass rounded-3 p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fw-semibold"><?= e($p['name']) ?> <span class="text-secondary small"><?= e($p['provider_type']) ?></span></div>
        <a href="/admin/signals/list?provider_id=<?= $pid ?>" class="btn btn-xs btn-outline-info">View Signals</a>
    </div>
    <div class="row g-3">
        <?php
        $periodData = ['30d' => $p30, '90d' => $p90, 'all' => $pAll];
        foreach ($periodData as $period => $perf):
            if (empty($perf)) continue;
            $wr = (float)($perf['win_rate'] ?? 0);
            $wrc = $wr >= 60 ? 'success' : ($wr >= 40 ? 'warning' : 'danger');
        ?>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3">
                <div class="text-secondary small fw-semibold mb-2"><?= strtoupper($period) ?></div>
                <div class="row g-2 text-center">
                    <?php
                    $cells = [
                        ['Win Rate', number_format($wr,1).'%', "text-$wrc"],
                        ['Signals',  $perf['total_signals'] ?? 0, ''],
                        ['Hit TP',   $perf['hit_tp_count'] ?? 0, 'text-success'],
                        ['Hit SL',   $perf['hit_sl_count'] ?? 0, 'text-danger'],
                        ['Avg Profit', isset($perf['avg_profit_pct']) ? '+'.number_format((float)$perf['avg_profit_pct'],2).'%' : '—', 'text-success'],
                        ['Total Return', isset($perf['total_return_pct']) ? number_format((float)$perf['total_return_pct'],2).'%' : '—', (($perf['total_return_pct'] ?? 0) >= 0 ? 'text-success' : 'text-danger')],
                    ];
                    foreach ($cells as [$label, $val, $col]):
                    ?>
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.62rem"><?= $label ?></div>
                        <div class="small fw-semibold <?= $col ?>"><?= $val ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    const daily  = <?= json_encode(array_values($daily)) ?>;
    if (!daily.length) return;
    const labels = daily.map(d => d.day);
    const total  = daily.map(d => parseInt(d.total) || 0);
    const won    = daily.map(d => parseInt(d.won)   || 0);
    const lost   = daily.map(d => parseInt(d.lost)  || 0);

    new ApexCharts(document.getElementById('perfChart'), {
        chart: { type: 'bar', height: 260, stacked: true, background: 'transparent', toolbar: { show: false } },
        theme: { mode: 'dark' },
        series: [
            { name: 'Hit TP', data: won },
            { name: 'Hit SL', data: lost },
            { name: 'Other',  data: daily.map((_,i) => Math.max(0, total[i] - won[i] - lost[i])) },
        ],
        colors: ['#34d399', '#f87171', '#94a3b8'],
        xaxis:  { categories: labels, labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        yaxis:  { labels: { style: { colors: '#94a3b8' } } },
        legend: { labels: { colors: '#e2e8f0' } },
        grid:   { borderColor: 'rgba(148,163,184,.1)' },
        tooltip: { theme: 'dark' },
    }).render();
})();

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('perfTable')) {
        new DataTable('#perfTable', {pageLength: 25, order: [[3, 'desc']]});
    }
});
</script>
