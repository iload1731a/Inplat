<?php declare(strict_types=1); ?>
<?php
$orders = is_array($orders ?? null) ? $orders : [];
require app_path('app/views/user/_nav.php');
?>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Order History</h5>
        <a href="/user/orders" class="btn btn-sm btn-outline-success"><i class="fas fa-circle-dot me-1"></i>Open Orders</a>
    </div>
    <div class="table-responsive">
        <table id="orderHistoryTable" class="table table-user table-sm">
            <thead>
                <tr><th>#</th><th>Pair</th><th>Side</th><th>Type</th><th>Quantity</th><th>Price</th><th>Avg Fill</th><th>Filled</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
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
                    <td class="font-monospace small"><?= number_format((float)($order['average_fill_price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($order['filled_quantity'] ?? 0), 6) ?></td>
                    <td>
                        <?php
                        $os = (string)($order['status'] ?? 'open');
                        $oc = match($os) {
                            'filled'           => 'success',
                            'cancelled','rejected','expired' => 'danger',
                            'partially_filled' => 'info',
                            default            => 'warning',
                        };
                        ?>
                        <span class="badge bg-<?= $oc ?>"><?= e($os) ?></span>
                    </td>
                    <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($order['created_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#orderHistoryTable').DataTable({
    order: [[0,'desc']],
    pageLength: 25,
    dom: '<"d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2"<"d-flex align-items-center gap-2"lB>f>rtip',
    buttons: ['excel','csv','pdf','print']
});
</script>
