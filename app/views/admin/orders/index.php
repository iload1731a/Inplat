<?php declare(strict_types=1); ?>
<?php
$orders     = is_array($orders ?? null) ? $orders : [];
$orderStats = is_array($orderStats ?? null) ? $orderStats : [];
$filters    = is_array($filters ?? null) ? $filters : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Orders Management</h1>
        <p class="text-secondary mb-0">Monitor, search and cancel platform orders.</p>
    </div>
    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bulkCancelModal">
        <i class="fas fa-ban me-1"></i> Bulk Cancel
    </button>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total</div><div class="h5 mb-0"><?= number_format((int)($orderStats['total_orders'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-2"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Open</div><div class="h5 mb-0 text-info"><?= number_format((int)($orderStats['open_orders'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-2"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Filled</div><div class="h5 mb-0 text-success"><?= number_format((int)($orderStats['filled_orders'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-2"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Cancelled</div><div class="h5 mb-0 text-secondary"><?= number_format((int)($orderStats['cancelled_orders'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-2"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Partial</div><div class="h5 mb-0 text-warning"><?= number_format((int)($orderStats['partial_orders'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-2"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Today</div><div class="h5 mb-0 text-primary"><?= number_format((int)($orderStats['orders_today'] ?? 0)) ?></div></div></div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/orders">
        <div class="col-lg-3"><input class="form-control" type="text" name="search" placeholder="Search UUID, user, symbol" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <?php foreach (['pending','open','partially_filled','filled','cancelled','rejected'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="side">
                <option value="">All Sides</option>
                <option value="buy" <?= (($filters['side'] ?? '') === 'buy') ? 'selected' : '' ?>>Buy</option>
                <option value="sell" <?= (($filters['side'] ?? '') === 'sell') ? 'selected' : '' ?>>Sell</option>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="order_type">
                <option value="">All Types</option>
                <?php foreach (['market','limit','stop_limit','stop_market','take_profit'] as $ot): ?>
                    <option value="<?= e($ot) ?>" <?= (($filters['order_type'] ?? '') === $ot) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $ot))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-1"><input class="form-control" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>"></div>
        <div class="col-lg-1"><input class="form-control" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>"></div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-primary w-100" type="submit">Go</button>
            <a class="btn btn-outline-light" href="/admin/orders">⟳</a>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th><th>User</th><th>Symbol</th><th>Type</th><th>Side</th>
                    <th>Qty / Filled</th><th>Price</th><th>Status</th><th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php
                $statusColors = ['open'=>'info','filled'=>'success','cancelled'=>'secondary','partially_filled'=>'warning','pending'=>'primary','rejected'=>'danger'];
                $sideColor    = ($order['side'] ?? '') === 'buy' ? 'success' : 'danger';
                ?>
                <tr>
                    <td class="text-secondary small"><?= (int)($order['id'] ?? 0) ?></td>
                    <td>
                        <div class="small fw-semibold"><?= e((string)($order['username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($order['email'] ?? '')) ?></div>
                    </td>
                    <td class="fw-semibold"><?= e((string)($order['symbol'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e(ucwords(str_replace('_', ' ', (string)($order['order_type'] ?? '-')))) ?></span></td>
                    <td><span class="badge text-bg-<?= $sideColor ?>"><?= e(strtoupper((string)($order['side'] ?? '-'))) ?></span></td>
                    <td>
                        <div><?= number_format((float)($order['quantity'] ?? 0), 8) ?></div>
                        <div class="small text-success"><?= number_format((float)($order['filled_quantity'] ?? 0), 8) ?> filled</div>
                    </td>
                    <td>
                        <?= $order['price'] ? number_format((float)$order['price'], 6) : '<span class="text-secondary">Market</span>' ?>
                        <?php if (!empty($order['average_fill_price'])): ?>
                            <div class="small text-secondary">avg: <?= number_format((float)$order['average_fill_price'], 6) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-<?= $statusColors[$order['status'] ?? ''] ?? 'secondary' ?>"><?= e(ucwords(str_replace('_', ' ', (string)($order['status'] ?? '-')))) ?></span></td>
                    <td class="text-secondary small"><?= e((string)($order['created_at'] ?? '-')) ?></td>
                    <td class="text-end">
                        <a href="/admin/order?id=<?= (int)$order['id'] ?>" class="btn btn-xs btn-outline-info me-1">View</a>
                        <?php if (in_array(($order['status'] ?? ''), ['pending','open','partially_filled'], true)): ?>
                            <button class="btn btn-xs btn-outline-danger"
                                onclick="cancelOrder(<?= (int)$order['id'] ?>)">Cancel</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="10" class="text-center text-secondary">No orders found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Cancel Single Order Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/orders/cancel" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="order_id" id="cancelOrderId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Cancel Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Reason</label>
                <input class="form-control" type="text" name="reason" placeholder="Admin cancellation reason" required>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Cancel Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Cancel Modal -->
<div class="modal fade" id="bulkCancelModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/orders/bulk-cancel" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Bulk Cancel Orders</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">Paste order IDs (comma-separated) to cancel in bulk.</p>
                <label class="form-label">Order IDs</label>
                <textarea class="form-control mb-3" name="order_ids_raw" rows="3" placeholder="1,2,3,4,5"></textarea>
                <label class="form-label">Reason</label>
                <input class="form-control" type="text" name="reason" value="Bulk cancel by admin" required>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Bulk Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function cancelOrder(orderId) {
    document.getElementById('cancelOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('cancelOrderModal')).show();
}

// Transform textarea order IDs to array inputs on form submit
document.querySelector('[action="/admin/orders/bulk-cancel"]').addEventListener('ajax:submit', function() {
    const raw = this.querySelector('[name="order_ids_raw"]').value;
    const ids = raw.split(/[\s,]+/).filter(x => /^\d+$/.test(x));
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'order_ids[]';
        inp.value = id;
        this.appendChild(inp);
    });
});
</script>
