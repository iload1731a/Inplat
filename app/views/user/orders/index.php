<?php declare(strict_types=1); ?>
<?php
$openOrders = is_array($openOrders ?? null) ? $openOrders : [];
$stats      = is_array($stats      ?? null) ? $stats      : [];
require app_path('app/views/user/_nav.php');
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Open',      $stats['open_orders']      ?? 0, 'success', 'fa-circle-dot'],
        ['Filled',    $stats['filled_orders']    ?? 0, 'info',    'fa-check-circle'],
        ['Cancelled', $stats['cancelled_orders'] ?? 0, 'secondary','fa-times-circle'],
        ['Total',     $stats['total_orders']     ?? 0, 'warning', 'fa-list-ol'],
    ];
    foreach ($statCards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= number_format((int)$val) ?></div>
            <div class="text-secondary small"><?= e($label) ?> Orders</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-success"></i>Open Orders</h5>
        <a href="/trading" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-candlestick me-1"></i>Trading</a>
    </div>
    <div class="table-responsive">
        <table id="openOrdersTable" class="table table-user table-sm">
            <thead>
                <tr><th>#</th><th>Pair</th><th>Side</th><th>Type</th><th>Quantity</th><th>Price</th><th>Filled</th><th>Status</th><th>Created</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($openOrders as $order): ?>
                <tr>
                    <td class="small"><?= (int)($order['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($order['pair_symbol'] ?? '-')) ?></td>
                    <td>
                        <span class="badge bg-<?= ($order['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>">
                            <?= e(strtoupper((string)($order['side'] ?? '-'))) ?>
                        </span>
                    </td>
                    <td class="small text-secondary"><?= e((string)($order['order_type'] ?? '-')) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($order['quantity'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($order['price'] ?? 0), 6) ?></td>
                    <td class="small">
                        <?php
                        $pct = ($order['quantity'] ?? 0) > 0
                            ? (float)($order['filled_quantity'] ?? 0) / (float)$order['quantity'] * 100
                            : 0;
                        ?>
                        <div class="progress" style="height:6px;width:60px">
                            <div class="progress-bar bg-info" style="width:<?= min(100, round($pct)) ?>%"></div>
                        </div>
                        <span class="text-secondary" style="font-size:.7rem"><?= round($pct, 1) ?>%</span>
                    </td>
                    <td><span class="badge bg-success"><?= e((string)($order['status'] ?? '-')) ?></span></td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($order['created_at'] ?? 'now')))) ?></td>
                    <td>
                        <button class="btn btn-xs btn-outline-danger btn-cancel-order" data-order-id="<?= (int)$order['id'] ?>">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($openOrders === []): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">
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
</script>
