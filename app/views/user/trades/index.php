<?php declare(strict_types=1); ?>
<?php
$trades       = is_array($trades       ?? null) ? $trades       : [];
$stats        = is_array($stats        ?? null) ? $stats        : [];
$volumeByPair = is_array($volumeByPair ?? null) ? $volumeByPair : [];
$pairLabels   = json_encode(array_column($volumeByPair, 'symbol'));
$pairVolumes  = json_encode(array_map('floatval', array_column($volumeByPair, 'volume')));
require app_path('app/views/user/_nav.php');
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Trades',  number_format((int)($stats['total_trades'] ?? 0)),                          'info',    'fa-receipt'],
        ['Total Volume',  number_format((float)($stats['total_volume'] ?? 0), 4),                     'success', 'fa-chart-line'],
        ['Total Fees',    number_format((float)($stats['total_fees'] ?? 0), 8),                       'warning', 'fa-percentage'],
        ['Largest Trade', number_format((float)($stats['largest_trade'] ?? 0), 4),                    'purple',  'fa-star'],
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

<div class="row g-4">
    <!-- Trade History Table -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-receipt me-2"></i>Trade History</h5>
            <div class="table-responsive">
                <table id="tradesTable" class="table table-user table-sm">
                    <thead>
                        <tr><th>#</th><th>Pair</th><th>Side</th><th>Price</th><th>Quantity</th><th>Value</th><th>Fee</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trades as $trade): ?>
                        <tr>
                            <td class="small"><?= (int)($trade['id'] ?? 0) ?></td>
                            <td class="fw-semibold"><?= e((string)($trade['pair_symbol'] ?? '-')) ?></td>
                            <td>
                                <span class="badge bg-<?= ($trade['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>">
                                    <?= e(strtoupper((string)($trade['side'] ?? '-'))) ?>
                                </span>
                            </td>
                            <td class="font-monospace small"><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($trade['quantity'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($trade['price'] ?? 0) * (float)($trade['quantity'] ?? 0), 4) ?></td>
                            <td class="font-monospace small text-warning"><?= number_format((float)($trade['fee'] ?? 0), 8) ?></td>
                            <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($trade['executed_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Volume by Pair Chart -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Volume by Pair</h5>
            <div id="volumeChart"></div>
        </div>
    </div>
</div>

<script>
$('#tradesTable').DataTable({ order: [[0,'desc']], pageLength: 25 });

const pairLabels = <?= $pairLabels ?: '[]' ?>;
const pairVolumes = <?= $pairVolumes ?: '[]' ?>;

if (pairLabels.length > 0) {
    new ApexCharts(document.getElementById('volumeChart'), {
        series: [{ name: 'Volume', data: pairVolumes }],
        chart: { type: 'bar', height: 280, background: 'transparent' },
        xaxis: { categories: pairLabels },
        theme: { mode: 'dark' },
        colors: ['#38bdf8'],
        dataLabels: { enabled: false },
        plotOptions: { bar: { borderRadius: 4 } },
    }).render();
}
</script>
