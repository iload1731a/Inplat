<?php declare(strict_types=1); ?>
<?php
$order  = is_array($order  ?? null) ? $order  : [];
$trades = is_array($trades ?? null) ? $trades : [];
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Order Details -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h5 class="mb-1">Order #<?= (int)($order['id'] ?? 0) ?></h5>
                    <code class="small text-secondary"><?= e((string)($order['order_uuid'] ?? '-')) ?></code>
                </div>
                <?php
                $os = (string)($order['status'] ?? '');
                $oc = match($os) {
                    'filled'           => 'success',
                    'cancelled','rejected','expired' => 'danger',
                    'partially_filled' => 'info',
                    'open'             => 'warning',
                    default            => 'secondary',
                };
                ?>
                <span class="badge bg-<?= $oc ?> fs-6"><?= e(ucwords(str_replace('_', ' ', $os))) ?></span>
            </div>

            <div class="row g-3">
                <?php
                $sideColor = ($order['side'] ?? '') === 'buy' ? 'success' : 'danger';
                $fields = [
                    ['Pair',          e((string)($order['pair_symbol'] ?? '-'))],
                    ['Market',        e((string)($order['market_type'] ?? '-'))],
                    ['Order Type',    e((string)($order['order_type'] ?? '-'))],
                    ['Side',          '<span class="badge bg-' . $sideColor . '">' . e(strtoupper((string)($order['side'] ?? '-'))) . '</span>'],
                    ['TIF',           e((string)($order['time_in_force'] ?? '-'))],
                    ['Leverage',      number_format((float)($order['leverage'] ?? 1), 0) . 'x'],
                    ['Quantity',      '<span class="font-monospace">' . number_format((float)($order['quantity'] ?? 0), 8) . ' ' . e((string)($order['base_currency'] ?? '')) . '</span>'],
                    ['Filled Qty',    '<span class="font-monospace text-success">' . number_format((float)($order['filled_quantity'] ?? 0), 8) . '</span>'],
                    ['Remaining',     '<span class="font-monospace">' . number_format((float)($order['remaining_quantity'] ?? 0), 8) . '</span>'],
                    ['Limit Price',   ($order['price'] ?? null) ? '<span class="font-monospace">' . number_format((float)$order['price'], 6) . ' ' . e((string)($order['quote_currency'] ?? '')) . '</span>' : '<span class="text-secondary">Market</span>'],
                    ['Stop Price',    ($order['stop_price'] ?? null) ? '<span class="font-monospace">' . number_format((float)$order['stop_price'], 6) . '</span>' : '-'],
                    ['Avg Fill Price',($order['average_fill_price'] ?? null) ? '<span class="font-monospace text-info">' . number_format((float)$order['average_fill_price'], 6) . '</span>' : '-'],
                    ['Source',        e((string)($order['source'] ?? '-'))],
                    ['Created',       e((string)($order['created_at'] ?? '-'))],
                    ['Updated',       e((string)($order['updated_at'] ?? '-'))],
                    ['Cancelled At',  ($order['cancelled_at'] ?? null) ? e((string)$order['cancelled_at']) : '-'],
                ];
                foreach ($fields as [$label, $value]):
                ?>
                <div class="col-md-6">
                    <div class="small text-secondary mb-1"><?= e($label) ?></div>
                    <div><?= $value ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($order['rejection_reason'])): ?>
            <div class="alert alert-danger mt-3 mb-0">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Rejection Reason:</strong> <?= e((string)$order['rejection_reason']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Trade Executions -->
        <?php if (!empty($trades)): ?>
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Trade Executions <span class="badge bg-secondary"><?= count($trades) ?></span></h5>
            <div class="table-responsive">
                <table class="table table-user table-sm">
                    <thead>
                        <tr><th>#</th><th>Side</th><th>Price</th><th>Quantity</th><th>Quote Amount</th><th>Fee</th><th>Executed</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trades as $i => $trade): ?>
                        <tr>
                            <td class="small text-secondary"><?= $i + 1 ?></td>
                            <td><span class="badge bg-<?= ($trade['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($trade['side'] ?? '-'))) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($trade['quantity'] ?? 0), 8) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($trade['quote_amount'] ?? 0), 6) ?></td>
                            <td class="font-monospace small text-warning"><?= number_format((float)($trade['fee'] ?? 0), 8) ?></td>
                            <td class="small"><?= e(date('M d H:i:s', strtotime((string)($trade['executed_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Fill Progress -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4 mb-4">
            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-info"></i>Fill Progress</h6>
            <?php
            $fillPct = ($order['quantity'] ?? 0) > 0
                ? min(100, (float)($order['filled_quantity'] ?? 0) / (float)$order['quantity'] * 100)
                : 0;
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-secondary">Filled</span>
                    <span class="fw-semibold"><?= round($fillPct, 2) ?>%</span>
                </div>
                <div class="progress" style="height:12px">
                    <div class="progress-bar bg-info" style="width:<?= round($fillPct) ?>%"></div>
                </div>
            </div>
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div class="small text-secondary">Filled</div>
                    <div class="fw-semibold font-monospace"><?= number_format((float)($order['filled_quantity'] ?? 0), 6) ?></div>
                </div>
                <div class="col-6">
                    <div class="small text-secondary">Remaining</div>
                    <div class="fw-semibold font-monospace"><?= number_format((float)($order['remaining_quantity'] ?? 0), 6) ?></div>
                </div>
            </div>
        </div>

        <?php if (in_array($os, ['open', 'partially_filled'])): ?>
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3 text-danger"><i class="fas fa-ban me-2"></i>Cancel Order</h6>
            <p class="text-secondary small mb-3">Cancel this order if it's no longer needed. Partially filled orders will retain existing fills.</p>
            <button class="btn btn-danger w-100" id="btnCancelOrder">
                <i class="fas fa-times me-2"></i>Cancel Order
            </button>
        </div>
        <?php endif; ?>

        <div class="glass rounded-4 p-4 mt-3">
            <h6 class="mb-3"><i class="fas fa-link me-2 text-secondary"></i>Quick Links</h6>
            <a href="/user/orders" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="fas fa-circle-dot me-1"></i>Open Orders</a>
            <a href="/user/orders/history" class="btn btn-outline-secondary btn-sm w-100 mb-2"><i class="fas fa-history me-1"></i>Order History</a>
            <a href="/trading" class="btn btn-outline-info btn-sm w-100"><i class="fas fa-chart-candlestick me-1"></i>Trading Terminal</a>
        </div>
    </div>
</div>

<script>
<?php if (in_array($os, ['open', 'partially_filled'])): ?>
document.getElementById('btnCancelOrder').addEventListener('click', function () {
    Swal.fire({
        title: 'Cancel Order #<?= (int)($order['id'] ?? 0) ?>?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Cancel',
        cancelButtonText: 'Keep Order'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/user/orders/cancel', {
            _token: csrfToken,
            order_id: <?= (int)($order['id'] ?? 0) ?>
        }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Cancelled', timer: 1500, showConfirmButton: false })
                    .then(() => window.location.href = '/user/orders');
            } else {
                Swal.fire({ icon: 'error', text: res.message });
            }
        }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
    });
});
<?php endif; ?>
</script>
