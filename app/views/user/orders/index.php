<?php declare(strict_types=1); ?>
<?php
$openOrders   = is_array($openOrders   ?? null) ? $openOrders   : [];
$stats        = is_array($stats        ?? null) ? $stats        : [];
$ordersByPair = is_array($ordersByPair ?? null) ? $ordersByPair : [];
$monthlyStats = is_array($monthlyStats ?? null) ? $monthlyStats : [];
$csrf         = \App\Libraries\Csrf::token();
$monthLabels  = json_encode(array_column($monthlyStats, 'month'));
$monthTotals  = json_encode(array_map('intval', array_column($monthlyStats, 'total')));
$monthFilled  = json_encode(array_map('intval', array_column($monthlyStats, 'filled')));
require app_path('app/views/user/_nav.php');
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Open',      $stats['open_orders']      ?? 0, 'success',   'fa-circle-dot'],
        ['Filled',    $stats['filled_orders']    ?? 0, 'info',      'fa-check-circle'],
        ['Cancelled', $stats['cancelled_orders'] ?? 0, 'secondary', 'fa-times-circle'],
        ['Today',     $stats['orders_today']     ?? 0, 'warning',   'fa-calendar-day'],
        ['7 Days',    $stats['orders_7d']        ?? 0, 'primary',   'fa-calendar-week'],
        ['Buy',       $stats['buy_orders']       ?? 0, 'success',   'fa-arrow-up'],
        ['Sell',      $stats['sell_orders']      ?? 0, 'danger',    'fa-arrow-down'],
        ['Total',     $stats['total_orders']     ?? 0, 'light',     'fa-list-ol'],
    ];
    foreach ($statCards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-xl text-<?= $color ?> mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= number_format((int)$val) ?></div>
            <div class="text-secondary small"><?= e($label) ?> Orders</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Monthly Chart -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Monthly Order Activity</h5>
            <div id="monthlyOrderChart"></div>
        </div>
    </div>
    <!-- Orders by Pair -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Top Pairs</h5>
            <?php if (!empty($ordersByPair)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-user mb-0">
                    <thead><tr><th>Pair</th><th class="text-end">Orders</th><th class="text-end">Filled</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($ordersByPair, 0, 8) as $pair): ?>
                        <tr>
                            <td class="fw-semibold small"><?= e((string)($pair['symbol'] ?? '-')) ?></td>
                            <td class="text-end small"><?= number_format((int)($pair['order_count'] ?? 0)) ?></td>
                            <td class="text-end small text-success"><?= number_format((int)($pair['filled_count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-secondary small mb-0">No order data yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-success"></i>Open Orders</h5>
        <div class="d-flex gap-2">
            <a href="/user/orders/history" class="btn btn-sm btn-outline-secondary"><i class="fas fa-history me-1"></i>History</a>
            <a href="/trading" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-candlestick me-1"></i>Trading</a>
        </div>
    </div>
    <div class="table-responsive">
        <table id="openOrdersTable" class="table table-user table-sm">
            <thead>
                <tr><th>#</th><th>Pair</th><th>Type</th><th>Side</th><th>Quantity</th><th>Price</th><th>Filled %</th><th>Leverage</th><th>Status</th><th>Created</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($openOrders as $order): ?>
                <?php
                $pct = ($order['quantity'] ?? 0) > 0
                    ? (float)($order['filled_quantity'] ?? 0) / (float)$order['quantity'] * 100
                    : 0;
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($order['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($order['pair_symbol'] ?? '-')) ?></td>
                    <td class="small"><span class="badge bg-secondary"><?= e((string)($order['order_type'] ?? '-')) ?></span></td>
                    <td><span class="badge bg-<?= ($order['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($order['side'] ?? '-'))) ?></span></td>
                    <td class="font-monospace small"><?= number_format((float)($order['quantity'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= ($order['price'] ?? null) ? number_format((float)$order['price'], 6) : '<span class="text-secondary">MKT</span>' ?></td>
                    <td>
                        <div class="progress" style="height:6px;width:60px;display:inline-block;vertical-align:middle">
                            <div class="progress-bar bg-info" style="width:<?= min(100, round($pct)) ?>%"></div>
                        </div>
                        <span class="text-secondary ms-1" style="font-size:.7rem"><?= round($pct, 1) ?>%</span>
                    </td>
                    <td class="small"><span class="badge bg-secondary"><?= number_format((float)($order['leverage'] ?? 1), 0) ?>x</span></td>
                    <td><span class="badge bg-success"><?= e((string)($order['status'] ?? '-')) ?></span></td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($order['created_at'] ?? 'now')))) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/user/orders/detail?id=<?= (int)$order['id'] ?>" class="btn btn-xs btn-outline-info">View</a>
                            <button class="btn btn-xs btn-outline-danger btn-cancel-order" data-order-id="<?= (int)$order['id'] ?>">Cancel</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($openOrders === []): ?>
                <tr><td colspan="11" class="text-center text-secondary py-4">
                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>No open orders
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#openOrdersTable').DataTable({ order: [[0,'desc']], pageLength: 25 });

$(document).on('click', '.btn-cancel-order', function () {
    const orderId = $(this).data('order-id');
    Swal.fire({
        title: 'Cancel Order #' + orderId + '?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Cancel Order'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/user/orders/cancel', { _token: csrfToken, order_id: orderId }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Cancelled', timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', text: res.message });
            }
        }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
    });
});

const monthLabels = <?= $monthLabels ?: '[]' ?>;
const monthTotals = <?= $monthTotals ?: '[]' ?>;
const monthFilled = <?= $monthFilled ?: '[]' ?>;

if (monthLabels.length > 0) {
    new ApexCharts(document.getElementById('monthlyOrderChart'), {
        series: [
            { name: 'Total Orders', data: monthTotals },
            { name: 'Filled', data: monthFilled }
        ],
        chart: { type: 'bar', height: 200, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: monthLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        theme: { mode: 'dark' },
        colors: ['#38bdf8', '#34d399'],
        dataLabels: { enabled: false },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
        legend: { labels: { colors: '#94a3b8' } },
    }).render();
}
</script>
