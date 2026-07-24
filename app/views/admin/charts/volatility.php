<?php declare(strict_types=1); ?>
<?php
$scores = is_array($scores ?? null) ? $scores : [];
$days   = (int)($days ?? 30);
$error  = (string)($error ?? '');
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if ($error !== ''): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<!-- Header + Filter -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold mb-0"><i class="fas fa-fire me-2 text-warning"></i>Volatility Analysis</h2>
        <div class="text-secondary small">H-L range / close price ratio — last <?= $days ?> days</div>
    </div>
    <form class="d-flex gap-2 align-items-center">
        <label class="text-secondary small mb-0">Period:</label>
        <?php foreach ([7, 14, 30, 60, 90] as $d): ?>
        <a href="/admin/charts/volatility?days=<?= $d ?>" class="btn btn-xs <?= $d === $days ? 'btn-warning' : 'btn-outline-secondary' ?>"><?= $d ?>d</a>
        <?php endforeach; ?>
        <a href="/admin/charts" class="btn btn-xs btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </form>
</div>

<!-- Volatility Bar Chart (top 20) -->
<div class="glass rounded-3 p-4 mb-4">
    <h6 class="mb-3 fw-semibold text-secondary">Top 20 Most Volatile Pairs</h6>
    <div id="volBarChart"></div>
</div>

<!-- Volatility Score Table -->
<div class="glass rounded-3 p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-table me-1 text-secondary"></i>All Pairs Volatility Scores</h6>
        <span class="badge text-bg-secondary"><?= count($scores) ?> pairs</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-dark table-hover mb-0" id="volTable" style="font-size:.82rem">
            <thead>
                <tr class="text-secondary">
                    <th>#</th>
                    <th>Pair</th>
                    <th>Type</th>
                    <th>Volatility Score</th>
                    <th>Avg H/L Ratio</th>
                    <th>Period Low</th>
                    <th>Period High</th>
                    <th>Total Volume</th>
                    <th>Candles</th>
                    <th>Chart</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scores as $rank => $row):
                    $score = (float)($row['volatility_score'] ?? 0);
                    $hlVol = (float)($row['avg_hl_volatility'] ?? 0) * 100;
                    $barColour = $score >= 70 ? '#ef4444' : ($score >= 40 ? '#f59e0b' : '#22c55e');
                ?>
                <tr>
                    <td class="text-secondary"><?= $rank + 1 ?></td>
                    <td>
                        <a href="/charts?pair=<?= urlencode((string)$row['symbol']) ?>" class="text-info fw-semibold text-decoration-none">
                            <?= htmlspecialchars((string)$row['symbol'], ENT_QUOTES) ?>
                        </a>
                    </td>
                    <td><span class="badge text-bg-<?= ['spot'=>'secondary','margin'=>'warning','futures'=>'danger'][$row['market_type'] ?? ''] ?? 'secondary' ?>"><?= htmlspecialchars(ucfirst((string)$row['market_type']), ENT_QUOTES) ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:90px;background:#1e293b;border-radius:3px;height:8px;overflow:hidden">
                                <div style="width:<?= min(100, $score) ?>%;height:8px;background:<?= $barColour ?>;border-radius:3px;transition:width .3s"></div>
                            </div>
                            <span style="color:<?= $barColour ?>;font-weight:600"><?= number_format($score, 1) ?></span>
                        </div>
                    </td>
                    <td><?= number_format($hlVol, 3) ?>%</td>
                    <td class="text-danger"><?= number_format((float)$row['period_low'], 4) ?></td>
                    <td class="text-success"><?= number_format((float)$row['period_high'], 4) ?></td>
                    <td class="text-secondary"><?= number_format((float)$row['total_volume'], 2) ?></td>
                    <td class="text-secondary"><?= (int)$row['candle_count'] ?></td>
                    <td>
                        <a href="/charts?pair=<?= urlencode((string)$row['symbol']) ?>" class="btn btn-xs btn-outline-secondary">
                            <i class="fas fa-chart-line"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const volScores = <?= json_encode(
    array_map(fn($r) => [
        'symbol' => $r['symbol'],
        'score'  => round((float)$r['volatility_score'], 2),
        'type'   => $r['market_type'],
    ], array_slice($scores, 0, 20))
) ?>;

if (volScores.length) {
    new ApexCharts(document.getElementById('volBarChart'), {
        series: [{ name: 'Volatility Score', data: volScores.map(r => r.score) }],
        chart: { type: 'bar', height: 260, background: 'transparent', foreColor: '#94a3b8', toolbar: { show: false }, animations: { enabled: false } },
        plotOptions: { bar: {
            horizontal: true,
            barHeight: '75%',
            distributed: true,
            colors: { ranges: [
                { from: 70, to: 100, color: '#ef4444' },
                { from: 40, to: 70,  color: '#f59e0b' },
                { from: 0,  to: 40,  color: '#22c55e' },
            ]},
        }},
        xaxis: { categories: volScores.map(r => r.symbol), labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#1e293b', strokeDashArray: 4 },
        tooltip: {
            theme: 'dark',
            y: { formatter: v => v.toFixed(1) + ' / 100' },
        },
        legend: { show: false },
    }).render();
}
</script>
