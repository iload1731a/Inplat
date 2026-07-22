<?php declare(strict_types=1); ?>
<?php
$order  = is_array($order ?? null) ? $order : [];
$trades = is_array($trades ?? null) ? $trades : [];
$csrf   = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Order Detail – #<?= (int)($order['id'] ?? 0) ?></h1>
        <p class="text-secondary mb-0"><?= e((string)($order['uuid'] ?? '-')) ?></p>
    </div>
    <a href="/admin/orders" class="btn btn-outline-secondary btn-sm">← Back to Orders</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4 mb-4">
            <h2 class="h6 mb-3 text-warning">Order Information</h2>
            <div class="row g-3">
                <?php
                $fields = [
                    'User'           => e((string)($order['username'] ?? '-')) . ' <small class="text-secondary">' . e((string)($order['email'] ?? '')) . '</small>',
                    'Pair'           => e((string)($order['symbol'] ?? '-')),
                    'Type'           => e(ucwords(str_replace('_',' ', (string)($order['order_type'] ?? '-')))),
                    'Side'           => '<span class="badge text-bg-' . ((($order['side'] ?? '') === 'buy') ? 'success' : 'danger') . '">' . e(strtoupper((string)($order['side'] ?? '-'))) . '</span>',
                    'Status'         => e(ucwords(str_replace('_',' ', (string)($order['status'] ?? '-')))),
                    'Quantity'       => number_format((float)($order['quantity'] ?? 0), 8),
                    'Filled Qty'     => number_format((float)($order['filled_quantity'] ?? 0), 8),
                    'Remaining Qty'  => number_format((float)($order['remaining_quantity'] ?? 0), 8),
                    'Price'          => $order['price'] ? number_format((float)$order['price'], 6) : 'Market',
                    'Stop Price'     => $order['stop_price'] ? number_format((float)$order['stop_price'], 6) : '-',
                    'Avg Fill Price' => $order['average_fill_price'] ? number_format((float)$order['average_fill_price'], 6) : '-',
                    'Quote Amount'   => number_format((float)($order['quote_amount'] ?? 0), 8),
                    'Filled Quote'   => number_format((float)($order['filled_quote_amount'] ?? 0), 8),
                    'Fee'            => number_format((float)($order['fee_amount'] ?? 0), 8) . ' ' . e((string)($order['fee_currency'] ?? '')),
                    'Leverage'       => (int)($order['leverage'] ?? 1) . 'x',
                    'TIF'            => e(strtoupper((string)($order['time_in_force'] ?? '-'))),
                    'Created'        => e((string)($order['created_at'] ?? '-')),
                    'Updated'        => e((string)($order['updated_at'] ?? '-')),
                    'Filled At'      => $order['filled_at'] ? e((string)$order['filled_at']) : '-',
                    'Cancelled At'   => $order['cancelled_at'] ? e((string)$order['cancelled_at']) : '-',
                ];
                foreach ($fields as $label => $value): ?>
                    <div class="col-md-6">
                        <div class="small text-secondary"><?= e($label) ?></div>
                        <div><?= $value ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($trades)): ?>
        <div class="glass rounded-4 p-4">
            <h2 class="h6 mb-3 text-warning">Trade Executions</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Price</th><th>Qty</th><th>Quote</th><th>Fee</th><th>Buyer</th><th>Seller</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($trades as $trade): ?>
                        <tr>
                            <td><?= (int)($trade['id'] ?? 0) ?></td>
                            <td><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                            <td><?= number_format((float)($trade['quantity'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($trade['quote_amount'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($trade['taker_fee'] ?? 0), 8) ?></td>
                            <td class="small"><?= e((string)($trade['buyer_username'] ?? '-')) ?></td>
                            <td class="small"><?= e((string)($trade['seller_username'] ?? '-')) ?></td>
                            <td class="text-secondary small"><?= e((string)($trade['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h2 class="h6 mb-3 text-danger">Admin Actions</h2>
            <?php if (in_array(($order['status'] ?? ''), ['pending','open','partially_filled'], true)): ?>
            <form data-ajax="true" action="/admin/orders/cancel" method="post" class="mb-3">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="order_id" value="<?= (int)($order['id'] ?? 0) ?>">
                <div class="mb-2">
                    <label class="form-label small">Cancel Reason</label>
                    <input class="form-control form-control-sm" type="text" name="reason" placeholder="Admin cancellation reason" required>
                </div>
                <button type="submit" class="btn btn-danger btn-sm w-100">Cancel This Order</button>
            </form>
            <?php else: ?>
            <p class="text-secondary small">Order cannot be cancelled (<?= e((string)($order['status'] ?? '')) ?>).</p>
            <?php endif; ?>
        </div>
    </div>
</div>
