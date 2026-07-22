<?php declare(strict_types=1); ?>
<?php
$orders  = is_array($orders  ?? null) ? $orders  : [];
$filters = is_array($filters ?? null) ? $filters : [];
require app_path('app/views/user/_nav.php');
?>

<!-- Filter Bar -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/user/orders/history">
        <div class="col-6 col-md-2">
            <input class="form-control form-control-sm" type="text" name="pair" placeholder="Pair (e.g. BTC/USDT)" value="<?= e((string)($filters['pair'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">All Status</option>
                <?php foreach (['open','partially_filled','filled','cancelled','rejected','expired'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="side">
                <option value="">All Sides</option>
                <option value="buy"  <?= ($filters['side'] ?? '') === 'buy'  ? 'selected' : '' ?>>Buy</option>
                <option value="sell" <?= ($filters['side'] ?? '') === 'sell' ? 'selected' : '' ?>>Sell</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="order_type">
                <option value="">All Types</option>
                <?php foreach (['market','limit','stop_limit','stop_market','trailing_stop'] as $ot): ?>
                    <option value="<?= e($ot) ?>" <?= ($filters['order_type'] ?? '') === $ot ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $ot))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-1">
            <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
            <a class="btn btn-sm btn-outline-secondary" href="/user/orders/history">⟳</a>
            <a class="btn btn-sm btn-outline-success" href="/user/orders/export?<?= http_build_query($filters) ?>"><i class="fas fa-download"></i></a>
        </div>
    </form>
</div>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Order History <span class="badge bg-secondary ms-1"><?= number_format(count($orders)) ?></span></h5>
        <a href="/user/orders" class="btn btn-sm btn-outline-success"><i class="fas fa-circle-dot me-1"></i>Open Orders</a>
    </div>
    <div class="table-responsive">
        <table id="orderHistoryTable" class="table table-user table-sm">
            <thead>
                <tr><th>#</th><th>Pair</th><th>Type</th><th>Side</th><th>Quantity</th><th>Price</th><th>Avg Fill</th><th>Filled</th><th>Leverage</th><th>Status</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php
                $os = (string)($order['status'] ?? 'open');
                $oc = match($os) {
                    'filled'           => 'success',
                    'cancelled','rejected','expired' => 'danger',
                    'partially_filled' => 'info',
                    default            => 'warning',
                };
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($order['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($order['pair_symbol'] ?? '-')) ?></td>
                    <td><span class="badge bg-secondary small"><?= e((string)($order['order_type'] ?? '-')) ?></span></td>
                    <td><span class="badge bg-<?= ($order['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($order['side'] ?? '-'))) ?></span></td>
                    <td class="font-monospace small"><?= number_format((float)($order['quantity'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= ($order['price'] ?? null) ? number_format((float)$order['price'], 6) : '<span class="text-secondary">MKT</span>' ?></td>
                    <td class="font-monospace small"><?= ($order['average_fill_price'] ?? null) ? number_format((float)$order['average_fill_price'], 6) : '-' ?></td>
                    <td class="font-monospace small"><?= number_format((float)($order['filled_quantity'] ?? 0), 6) ?></td>
                    <td class="small"><span class="badge bg-secondary"><?= number_format((float)($order['leverage'] ?? 1), 0) ?>x</span></td>
                    <td><span class="badge bg-<?= $oc ?>"><?= e($os) ?></span></td>
                    <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($order['created_at'] ?? 'now')))) ?></td>
                    <td><a href="/user/orders/detail?id=<?= (int)$order['id'] ?>" class="btn btn-xs btn-outline-info">View</a></td>
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
