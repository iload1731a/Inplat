<?php declare(strict_types=1); ?>
<?php
$overview = is_array($overview ?? null) ? $overview : [];
$walletAllocation = is_array($walletAllocation ?? null) ? $walletAllocation : [];
$recentOrders = is_array($recentOrders ?? null) ? $recentOrders : [];
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$pnlSeries = is_array($pnlSeries ?? null) ? $pnlSeries : [];
$walletAllocationJson = json_encode($walletAllocation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$pnlSeriesJson = json_encode($pnlSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$overviewCards = [
    ['label' => 'Portfolio Balance', 'value' => number_format((float)($overview['wallet_total_balance'] ?? 0), 6), 'valueClass' => ''],
    ['label' => 'Open Orders', 'value' => number_format((int)($overview['open_orders'] ?? 0)), 'valueClass' => ''],
    ['label' => 'Open Positions', 'value' => number_format((int)($overview['open_positions'] ?? 0)), 'valueClass' => ''],
    [
        'label' => 'Total PnL',
        'value' => number_format((float)($overview['total_pnl'] ?? 0), 6),
        'valueClass' => (float)($overview['total_pnl'] ?? 0) >= 0 ? 'text-success' : 'text-danger'
    ],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trading Dashboard</h1>
        <p class="text-secondary mb-0">Welcome back, <?= e((string)($username ?? 'Trader')) ?>.</p>
    </div>
    <form action="/logout" method="post">
        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
        <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
    </form>
</div>

<?php if (!empty($dashboardError)): ?>
    <div class="alert alert-warning"><?= e((string)$dashboardError) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($overviewCards as $card): ?>
        <div class="col-md-3">
            <div class="glass rounded-4 p-3">
                <div class="text-secondary small"><?= e((string)$card['label']) ?></div>
                <div class="h4 mb-0 <?= e((string)$card['valueClass']) ?>"><?= e((string)$card['value']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Realized PnL (Last 7 Days)</h2>
            <canvas id="pnlChart" height="120"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Wallet Allocation</h2>
            <canvas id="walletChart" height="180"></canvas>
            <div class="mt-3 small text-secondary">
                Watchlist Items: <strong class="text-light"><?= (int)($overview['watchlist_items'] ?? 0) ?></strong><br>
                Total Trades: <strong class="text-light"><?= (int)($overview['total_trades'] ?? 0) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Recent Orders</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>Pair</th><th>Side</th><th>Quantity</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?= (int)($order['id'] ?? 0) ?></td>
                            <td><?= e((string)($order['pair_symbol'] ?? '-')) ?></td>
                            <td class="<?= (($order['side'] ?? '') === 'buy') ? 'text-success' : 'text-danger' ?>"><?= e((string)($order['side'] ?? '-')) ?></td>
                            <td><?= number_format((float)($order['quantity'] ?? 0), 6) ?></td>
                            <td><?= e((string)($order['status'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentOrders === []): ?><tr><td colspan="5" class="text-secondary text-center">No orders yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Recent Trades</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>Pair</th><th>Side</th><th>Price</th><th>Quantity</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTrades as $trade): ?>
                        <tr>
                            <td><?= (int)($trade['id'] ?? 0) ?></td>
                            <td><?= e((string)($trade['pair_symbol'] ?? '-')) ?></td>
                            <td class="<?= (($trade['side'] ?? '') === 'buy') ? 'text-success' : 'text-danger' ?>"><?= e((string)($trade['side'] ?? '-')) ?></td>
                            <td><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                            <td><?= number_format((float)($trade['quantity'] ?? 0), 6) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentTrades === []): ?><tr><td colspan="5" class="text-secondary text-center">No trades yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    const pnlSeries = <?= $pnlSeriesJson ?: '[]' ?>;
    const walletSeries = <?= $walletAllocationJson ?: '[]' ?>;
    const pnlLabels = pnlSeries.map(item => item.day);
    const pnlData = pnlSeries.map(item => Number(item.pnl || 0));

    new Chart(document.getElementById('pnlChart'), {
        type: 'line',
        data: {
            labels: pnlLabels,
            datasets: [{ label: 'Realized PnL', data: pnlData, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,0.2)', tension: 0.35, fill: true }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    const walletLabels = walletSeries.map(item => item.code || '-');
    const walletData = walletSeries.map(item => Number(item.total_balance || 0));

    new Chart(document.getElementById('walletChart'), {
        type: 'doughnut',
        data: {
            labels: walletLabels,
            datasets: [{ data: walletData, backgroundColor: ['#38bdf8', '#fb7185', '#34d399', '#fbbf24', '#a78bfa', '#fb923c'] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
})();
</script>
