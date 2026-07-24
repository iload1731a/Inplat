<?php declare(strict_types=1); ?>
<?php
$trades     = is_array($trades     ?? null) ? $trades     : [];
$tradeStats = is_array($tradeStats ?? null) ? $tradeStats : [];
$filters    = is_array($filters    ?? null) ? $filters    : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trades Management</h1>
        <p class="text-secondary mb-0">Monitor all trade executions and fee revenue.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/trades/reports" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-bar me-1"></i>Reports</a>
        <a href="/admin/trades/export?<?= http_build_query($filters) ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>Export CSV</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statsCards = [
        ['Total Trades',   (int)($tradeStats['total_trades'] ?? 0),     'info',    'fa-receipt'],
        ['Today',          (int)($tradeStats['trades_today'] ?? 0),     'primary', 'fa-calendar-day'],
        ['Total Volume',   number_format((float)($tradeStats['total_volume'] ?? 0), 2), 'success', 'fa-chart-line'],
        ['Today Volume',   number_format((float)($tradeStats['volume_today'] ?? 0), 2), 'warning', 'fa-bolt'],
        ['Total Fees',     number_format((float)($tradeStats['total_fees'] ?? 0), 4),   'yellow',  'fa-tag'],
        ['Avg Trade',      number_format((float)($tradeStats['avg_trade_value'] ?? 0), 2), 'secondary', 'fa-balance-scale'],
    ];
    foreach ($statsCards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-6 col-md-2">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($label) ?></div>
            <div class="h5 mb-0 text-<?= $color ?>"><?= is_int($val) ? number_format($val) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/trades">
        <div class="col-lg-3"><input class="form-control" type="text" name="search" placeholder="Search user, UUID, symbol" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2"><input class="form-control" type="text" name="symbol" placeholder="Symbol (e.g. BTC/USDT)" value="<?= e((string)($filters['symbol'] ?? '')) ?>"></div>
        <div class="col-lg-2"><input class="form-control" type="number" name="user_id" placeholder="User ID" value="<?= (int)($filters['user_id'] ?? 0) ?: '' ?>"></div>
        <div class="col-lg-2"><input class="form-control" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>"></div>
        <div class="col-lg-2"><input class="form-control" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>"></div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-primary w-100" type="submit">Go</button>
            <a class="btn btn-outline-light" href="/admin/trades">⟳</a>
        </div>
    </form>
</div>

<!-- Trades Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>ID</th><th>Symbol</th><th>Buyer</th><th>Seller</th><th>Price</th><th>Quantity</th><th>Quote Amount</th><th>Fees</th><th>Maker</th><th>Executed</th></tr>
            </thead>
            <tbody>
            <?php foreach ($trades as $trade): ?>
                <tr>
                    <td class="small text-secondary"><?= (int)($trade['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($trade['symbol'] ?? '-')) ?><br><span class="badge bg-secondary small"><?= e((string)($trade['market_type'] ?? '')) ?></span></td>
                    <td>
                        <div class="small fw-semibold"><?= e((string)($trade['buyer_username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($trade['buyer_email'] ?? '')) ?></div>
                    </td>
                    <td>
                        <div class="small fw-semibold"><?= e((string)($trade['seller_username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($trade['seller_email'] ?? '')) ?></div>
                    </td>
                    <td class="font-monospace small"><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($trade['quantity'] ?? 0), 8) ?></td>
                    <td class="font-monospace small"><?= number_format((float)($trade['quote_amount'] ?? 0), 4) ?></td>
                    <td class="font-monospace small text-warning"><?= number_format((float)($trade['buyer_fee'] ?? 0) + (float)($trade['seller_fee'] ?? 0), 8) ?></td>
                    <td><span class="badge bg-<?= ($trade['maker_side'] ?? '') === 'buy' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($trade['maker_side'] ?? '-'))) ?></span></td>
                    <td class="text-secondary small"><?= e((string)($trade['executed_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($trades === []): ?>
                <tr><td colspan="10" class="text-center text-secondary">No trades found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
