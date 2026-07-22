<?php declare(strict_types=1); ?>
<?php
$symbols = is_array($symbols ?? null) ? $symbols : [];
$matrix  = is_array($matrix  ?? null) ? $matrix  : [];
$days    = (int)($days ?? 30);
$error   = (string)($error ?? '');
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if ($error !== ''): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<!-- Header + Filter -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold mb-0"><i class="fas fa-project-diagram me-2 text-info"></i>Correlation Matrix</h2>
        <div class="text-secondary small">Pearson correlation of daily log returns — last <?= $days ?> days</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php foreach ([14, 30, 60, 90] as $d): ?>
        <a href="/admin/charts/correlation?days=<?= $d ?>" class="btn btn-xs <?= $d === $days ? 'btn-info' : 'btn-outline-secondary' ?>"><?= $d ?>d</a>
        <?php endforeach; ?>
        <a href="/admin/charts" class="btn btn-xs btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<?php if (empty($symbols)): ?>
<div class="glass rounded-3 p-5 text-center text-secondary">
    <i class="fas fa-database fa-2x mb-3"></i>
    <p>Insufficient candlestick data for correlation calculation. Requires at least 5 daily candles per pair.</p>
</div>
<?php else: ?>

<!-- Legend -->
<div class="d-flex gap-4 mb-3 align-items-center flex-wrap">
    <span class="small text-secondary">Correlation scale:</span>
    <?php
    $legend = [
        [-1.0, -0.7, '#dc2626', 'Strong negative'],
        [-0.7, -0.3, '#f97316', 'Moderate negative'],
        [-0.3,  0.3, '#64748b', 'Weak / no correlation'],
        [ 0.3,  0.7, '#16a34a', 'Moderate positive'],
        [ 0.7,  1.0, '#15803d', 'Strong positive'],
    ];
    ?>
    <?php foreach ($legend as [$lo, $hi, $col, $label]): ?>
    <span style="font-size:.75rem"><span style="display:inline-block;width:12px;height:12px;background:<?= $col ?>;border-radius:2px;margin-right:.3rem"></span><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
    <?php endforeach; ?>
</div>

<!-- Heatmap Table -->
<div class="glass rounded-3 p-3 overflow-auto mb-4">
    <table class="table table-bordered table-dark mb-0" style="font-size:.72rem;min-width:<?= max(600, count($symbols) * 70) ?>px">
        <thead>
            <tr>
                <th style="min-width:90px;background:#0f172a"></th>
                <?php foreach ($symbols as $sym): ?>
                <th class="text-center" style="background:#0f172a;font-size:.68rem;color:#94a3b8;padding:.3rem .25rem;white-space:nowrap">
                    <?= htmlspecialchars($sym, ENT_QUOTES) ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($symbols as $rowSym): ?>
            <tr>
                <td class="fw-semibold text-info" style="font-size:.7rem;white-space:nowrap;background:#0f172a;padding:.3rem .5rem">
                    <?= htmlspecialchars($rowSym, ENT_QUOTES) ?>
                </td>
                <?php foreach ($symbols as $colSym):
                    $val = $matrix[$rowSym][$colSym] ?? null;
                    if ($rowSym === $colSym) {
                        $bg    = 'rgba(99,102,241,.4)';
                        $color = '#e2e8f0';
                        $label = '1.00';
                    } elseif ($val === null) {
                        $bg    = 'rgba(100,116,139,.1)';
                        $color = '#475569';
                        $label = 'N/A';
                    } else {
                        $v = (float)$val;
                        if ($v >= 0.7)       { $bg = 'rgba(21,128,61,.55)';  $color = '#4ade80'; }
                        elseif ($v >= 0.3)   { $bg = 'rgba(22,163,74,.3)';   $color = '#86efac'; }
                        elseif ($v >= -0.3)  { $bg = 'rgba(100,116,139,.2)'; $color = '#94a3b8'; }
                        elseif ($v >= -0.7)  { $bg = 'rgba(249,115,22,.3)';  $color = '#fdba74'; }
                        else                 { $bg = 'rgba(220,38,38,.5)';   $color = '#fca5a5'; }
                        $label = number_format($v, 2);
                    }
                ?>
                <td class="text-center" style="background:<?= $bg ?>;color:<?= $color ?>;font-weight:600;padding:.3rem .25rem;border-color:#1e293b">
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ApexCharts Heatmap (top 10 x top 10) -->
<?php if (count($symbols) >= 2): ?>
<div class="glass rounded-3 p-3">
    <h6 class="mb-3 fw-semibold text-secondary">Correlation Heatmap</h6>
    <div id="corrHeatmap"></div>
</div>
<?php endif; ?>

<script>
const corrSymbols = <?= json_encode(array_values($symbols)) ?>;
const corrMatrix  = <?= json_encode($matrix) ?>;

if (corrSymbols.length >= 2) {
    const topSyms   = corrSymbols.slice(0, 10);
    const apexSeries = topSyms.map(rowSym => ({
        name: rowSym,
        data: topSyms.map(colSym => {
            const v = corrMatrix[rowSym]?.[colSym];
            return { x: colSym, y: v !== undefined && v !== null ? parseFloat(v) : null };
        }),
    }));

    new ApexCharts(document.getElementById('corrHeatmap'), {
        series: apexSeries,
        chart: { type: 'heatmap', height: 380, background: 'transparent', foreColor: '#94a3b8', toolbar: { show: false }, animations: { enabled: false } },
        plotOptions: {
            heatmap: {
                shadeIntensity: 0.6,
                colorScale: {
                    ranges: [
                        { from: -1.01, to: -0.7,  color: '#dc2626', name: 'Strong -ve' },
                        { from: -0.7,  to: -0.3,  color: '#f97316', name: 'Moderate -ve' },
                        { from: -0.3,  to:  0.3,  color: '#475569', name: 'Weak' },
                        { from:  0.3,  to:  0.7,  color: '#16a34a', name: 'Moderate +ve' },
                        { from:  0.7,  to:  1.01, color: '#15803d', name: 'Strong +ve' },
                    ],
                },
            },
        },
        dataLabels: { enabled: true, style: { colors: ['#e2e8f0'], fontSize: '11px' }, formatter: v => v !== null ? parseFloat(v).toFixed(2) : '' },
        xaxis: { labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        grid: { borderColor: '#1e293b' },
        tooltip: { theme: 'dark', y: { formatter: v => v !== null ? parseFloat(v).toFixed(4) : 'N/A' } },
        legend: { position: 'top', labels: { colors: '#94a3b8' } },
    }).render();
}
</script>

<?php endif; ?>
