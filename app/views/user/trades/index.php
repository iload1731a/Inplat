<?php declare(strict_types=1); ?>
<?php
$trades       = is_array($trades       ?? null) ? $trades       : [];
$stats        = is_array($stats        ?? null) ? $stats        : [];
$volumeByPair = is_array($volumeByPair ?? null) ? $volumeByPair : [];
$dailyVolume  = is_array($dailyVolume  ?? null) ? $dailyVolume  : [];
$monthlyStats = is_array($monthlyStats ?? null) ? $monthlyStats : [];
$filters      = is_array($filters      ?? null) ? $filters      : [];

$pairLabels   = json_encode(array_column($volumeByPair, 'symbol'));
$pairVolumes  = json_encode(array_map('floatval', array_column($volumeByPair, 'volume')));
$dayLabels    = json_encode(array_column($dailyVolume, 'day'));
$dayVolumes   = json_encode(array_map('floatval', array_column($dailyVolume, 'volume')));
$dayTrades    = json_encode(array_map('intval', array_column($dailyVolume, 'trade_count')));
require app_path('app/views/user/_nav.php');
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Trades',  number_format((int)($stats['total_trades'] ?? 0)),                       'info',    'fa-receipt'],
        ['Total Volume',  number_format((float)($stats['total_volume'] ?? 0), 2),                  'success', 'fa-chart-line'],
        ['Total Fees',    number_format((float)($stats['total_fees'] ?? 0), 8),                    'warning', 'fa-percentage'],
        ['Largest Trade', number_format((float)($stats['largest_trade'] ?? 0), 2),                 'primary', 'fa-star'],
        ['Avg Trade',     number_format((float)($stats['avg_trade_value'] ?? 0), 2),               'secondary','fa-balance-scale'],
        ['Today',         number_format((int)($stats['trades_today'] ?? 0)),                       'light',   'fa-calendar-day'],
        ['7 Days',        number_format((int)($stats['trades_7d'] ?? 0)),                          'light',   'fa-calendar-week'],
        ['Pairs Traded',  number_format(count($volumeByPair)),                                     'info',    'fa-coins'],
    ];
    foreach ($statCards as [$label, $val, $color, $icon]):
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

<!-- Filter Bar -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/user/trades">
        <div class="col-6 col-md-3">
            <input class="form-control form-control-sm" type="text" name="pair" placeholder="Pair (e.g. BTC/USDT)" value="<?= e((string)($filters['pair'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="side">
                <option value="">All Sides</option>
                <option value="buy"  <?= ($filters['side'] ?? '') === 'buy'  ? 'selected' : '' ?>>Buy</option>
                <option value="sell" <?= ($filters['side'] ?? '') === 'sell' ? 'selected' : '' ?>>Sell</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-3 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
            <a class="btn btn-sm btn-outline-secondary" href="/user/trades">⟳</a>
            <a class="btn btn-sm btn-outline-success" href="/user/trades/export?<?= http_build_query($filters) ?>">
                <i class="fas fa-download"></i>
            </a>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <!-- Daily Volume Chart -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Daily Trading Volume (30 days)</h5>
            <div id="dailyVolumeChart"></div>
        </div>
    </div>
    <!-- Volume by Pair Donut -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Volume by Pair</h5>
            <div id="volumeChart"></div>
        </div>
    </div>
</div>

<!-- Trade History Table -->
<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Trade History
            <span class="badge bg-secondary ms-1"><?= number_format(count($trades)) ?></span>
        </h5>
        <a href="/trading" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-candlestick me-1"></i>Trading</a>
    </div>
    <div class="table-responsive">
        <table id="tradesTable" class="table table-user table-sm">
            <thead>
                <tr><th>#</th><th>Pair</th><th>Side</th><th>Price</th><th>Quantity</th><th>Quote Amount</th><th>Fee</th><th>Maker Side</th><th>Executed</th></tr>
            </thead>
            <tbody>
            <?php foreach ($trades as $trade): ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($trade['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($trade['pair_symbol'] ?? '-')) ?></td>
                    <td><span class="badge bg-<?= ($trade['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>">
                        <?= e(strtoupper((string)($trade['side'] ?? '-'))) ?>
                    </span></td>
                    <td class="font-monospace small"><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($trade['quantity'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($trade['quote_amount'] ?? 0), 4) ?></td>
                    <td class="font-monospace small text-warning"><?= number_format((float)($trade['fee'] ?? 0), 8) ?></td>
                    <td class="small text-secondary"><?= e((string)($trade['maker_side'] ?? '-')) ?></td>
                    <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($trade['executed_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#tradesTable').DataTable({
    order: [[0,'desc']],
    pageLength: 25,
    dom: '<"d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2"<"d-flex align-items-center gap-2"lB>f>rtip',
    buttons: ['excel','csv','pdf','print']
});

const pairLabels  = <?= $pairLabels ?: '[]' ?>;
const pairVolumes = <?= $pairVolumes ?: '[]' ?>;
const dayLabels   = <?= $dayLabels ?: '[]' ?>;
const dayVolumes  = <?= $dayVolumes ?: '[]' ?>;
const dayTrades   = <?= $dayTrades ?: '[]' ?>;

if (dayLabels.length > 0) {
    new ApexCharts(document.getElementById('dailyVolumeChart'), {
        series: [
            { name: 'Volume', type: 'bar',  data: dayVolumes },
            { name: 'Trades', type: 'line', data: dayTrades },
        ],
        chart: { height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: dayLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: [
            { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0) } },
            { opposite: true, labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0) } }
        ],
        theme: { mode: 'dark' },
        colors: ['#38bdf8', '#f59e0b'],
        plotOptions: { bar: { borderRadius: 2, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
        stroke: { width: [0, 2] },
        legend: { labels: { colors: '#94a3b8' } },
    }).render();
}

if (pairLabels.length > 0) {
    new ApexCharts(document.getElementById('volumeChart'), {
        series: pairVolumes,
        chart: { type: 'donut', height: 220, background: 'transparent' },
        labels: pairLabels,
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();
}
</script>
