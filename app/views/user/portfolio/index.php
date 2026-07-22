<?php declare(strict_types=1); ?>
<?php
$walletBalances = is_array($walletBalances ?? null) ? $walletBalances : [];
$tradingSummary = is_array($tradingSummary ?? null) ? $tradingSummary : [];
$positionPnl    = is_array($positionPnl    ?? null) ? $positionPnl    : [];
$orderSummary   = is_array($orderSummary   ?? null) ? $orderSummary   : [];
$dailyPnl       = is_array($dailyPnl       ?? null) ? $dailyPnl       : [];
$dailyVolume    = is_array($dailyVolume    ?? null) ? $dailyVolume    : [];
$volumeByPair   = is_array($volumeByPair   ?? null) ? $volumeByPair   : [];
$dwSummary      = is_array($dwSummary      ?? null) ? $dwSummary      : [];
$winRate        = (float)($winRate         ?? 0);
$totalPnl       = (float)($totalPnl        ?? 0);

$pnlDays    = json_encode(array_column($dailyPnl, 'day'));
$pnlVals    = json_encode(array_map('floatval', array_column($dailyPnl, 'pnl')));
$volDays    = json_encode(array_column($dailyVolume, 'day'));
$volVals    = json_encode(array_map('floatval', array_column($dailyVolume, 'volume')));
$pairLabels = json_encode(array_column($volumeByPair, 'symbol'));
$pairVols   = json_encode(array_map('floatval', array_column($volumeByPair, 'volume')));
require app_path('app/views/user/_nav.php');
?>

<!-- KPI Cards Row 1 -->
<div class="row g-3 mb-4">
    <?php
    $rpnl = (float)($positionPnl['total_realized_pnl']   ?? 0);
    $upnl = (float)($positionPnl['total_unrealized_pnl'] ?? 0);
    $vol  = (float)($tradingSummary['total_volume']       ?? 0);
    $fees = (float)($tradingSummary['total_fees']         ?? 0);
    $dep  = (float)($dwSummary['total_deposited']         ?? 0);
    $wd   = (float)($dwSummary['total_withdrawn']         ?? 0);
    $kpis = [
        [number_format($totalPnl, 4), 'Total PnL',          $totalPnl >= 0 ? 'success' : 'danger', 'fa-trophy'],
        [number_format($rpnl, 4),      'Realized PnL',       $rpnl >= 0 ? 'success' : 'danger',     'fa-coins'],
        [number_format($upnl, 4),      'Unrealized PnL',     $upnl >= 0 ? 'success' : 'danger',     'fa-chart-line'],
        [$winRate . '%',               'Win Rate',            'warning',                              'fa-percent'],
        [number_format($vol, 2),       'Total Volume',        'info',                                 'fa-chart-bar'],
        [number_format($fees, 6),      'Total Fees Paid',     'secondary',                            'fa-tag'],
        [number_format($dep, 2),       'Total Deposited',     'success',                              'fa-arrow-down'],
        [number_format($wd, 2),        'Total Withdrawn',     'secondary',                            'fa-arrow-up'],
    ];
    foreach ($kpis as [$val, $label, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-xl text-<?= $color ?> mb-2 d-block"></i>
            <div class="h5 fw-bold"><?= e($val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quick Stats Row 2 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Total Trades</div>
            <div class="h4 fw-bold text-info"><?= number_format((int)($tradingSummary['total_trades'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Open Positions</div>
            <div class="h4 fw-bold text-warning"><?= number_format((int)($positionPnl['open_positions'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Open Orders</div>
            <div class="h4 fw-bold text-primary"><?= number_format((int)($orderSummary['open_orders'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Unique Pairs</div>
            <div class="h4 fw-bold text-light"><?= number_format((int)($tradingSummary['unique_pairs'] ?? 0)) ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Daily PnL Chart -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-success"></i>Daily PnL (30 days)</h5>
            <div id="pnlChart"></div>
        </div>
    </div>
    <!-- Daily Volume Chart -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Daily Trading Volume (30 days)</h5>
            <div id="volumeChart"></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Wallet Balances -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-wallet me-2 text-warning"></i>Wallet Balances</h5>
                <a href="/user/wallet" class="btn btn-sm btn-outline-secondary">View Wallet</a>
            </div>
            <?php if (!empty($walletBalances)): ?>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>Asset</th><th>Type</th><th class="text-end">Available</th><th class="text-end">Locked</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($walletBalances as $bal): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($bal['code'] ?? '-')) ?></td>
                            <td><span class="badge bg-secondary small"><?= e((string)($bal['type'] ?? '-')) ?></span></td>
                            <td class="text-end font-monospace small"><?= number_format((float)($bal['available'] ?? 0), 6) ?></td>
                            <td class="text-end font-monospace small text-warning"><?= number_format((float)($bal['locked'] ?? 0), 6) ?></td>
                            <td class="text-end font-monospace small fw-semibold"><?= number_format((float)($bal['total'] ?? 0), 6) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-secondary small mb-0">No wallet balances found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Volume by Pair Donut + Quick Links -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Volume by Pair</h5>
            <div id="pairChart"></div>
        </div>
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-link me-2 text-secondary"></i>Quick Links</h6>
            <div class="row g-2">
                <div class="col-6"><a href="/user/orders" class="btn btn-outline-info btn-sm w-100"><i class="fas fa-list me-1"></i>Orders</a></div>
                <div class="col-6"><a href="/user/positions" class="btn btn-outline-warning btn-sm w-100"><i class="fas fa-layer-group me-1"></i>Positions</a></div>
                <div class="col-6"><a href="/user/trades" class="btn btn-outline-success btn-sm w-100"><i class="fas fa-receipt me-1"></i>Trades</a></div>
                <div class="col-6"><a href="/user/portfolio/analytics" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-chart-mixed me-1"></i>Analytics</a></div>
            </div>
        </div>
    </div>
</div>

<script>
const pnlDays   = <?= $pnlDays ?: '[]' ?>;
const pnlVals   = <?= $pnlVals ?: '[]' ?>;
const volDays   = <?= $volDays ?: '[]' ?>;
const volVals   = <?= $volVals ?: '[]' ?>;
const pairLabels = <?= $pairLabels ?: '[]' ?>;
const pairVols  = <?= $pairVols ?: '[]' ?>;

if (pnlDays.length > 0) {
    new ApexCharts(document.getElementById('pnlChart'), {
        series: [{ name: 'PnL', data: pnlVals }],
        chart: { type: 'area', height: 200, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: pnlDays, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(2) } },
        theme: { mode: 'dark' },
        colors: ['#34d399'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

if (volDays.length > 0) {
    new ApexCharts(document.getElementById('volumeChart'), {
        series: [{ name: 'Volume', data: volVals }],
        chart: { type: 'bar', height: 200, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: volDays, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0) } },
        theme: { mode: 'dark' },
        colors: ['#38bdf8'],
        plotOptions: { bar: { borderRadius: 2, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

if (pairLabels.length > 0) {
    new ApexCharts(document.getElementById('pairChart'), {
        series: pairVols,
        chart: { type: 'donut', height: 200, background: 'transparent' },
        labels: pairLabels,
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();
}
</script>
