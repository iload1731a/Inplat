<?php declare(strict_types=1); ?>
<?php
$overview = is_array($overview ?? null) ? $overview : [];
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$recentDeposits = is_array($recentDeposits ?? null) ? $recentDeposits : [];
$recentWithdrawals = is_array($recentWithdrawals ?? null) ? $recentWithdrawals : [];
$recentLogins = is_array($recentLogins ?? null) ? $recentLogins : [];
$activityTimeline = is_array($activityTimeline ?? null) ? $activityTimeline : [];
$tradeVolumeSeries = is_array($tradeVolumeSeries ?? null) ? $tradeVolumeSeries : [];
$userGrowthSeries = is_array($userGrowthSeries ?? null) ? $userGrowthSeries : [];
$revenueSeries = is_array($revenueSeries ?? null) ? $revenueSeries : [];
$orderStatusSeries = is_array($orderStatusSeries ?? null) ? $orderStatusSeries : [];
$candlestickSeries = is_array($candlestickSeries ?? null) ? $candlestickSeries : [];
$tradeVolumeJson = json_encode($tradeVolumeSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$userGrowthJson = json_encode($userGrowthSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$revenueJson = json_encode($revenueSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$orderStatusJson = json_encode($orderStatusSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$candlestickJson = json_encode($candlestickSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Admin Dashboard</h1>
        <p class="text-secondary mb-0">Welcome back, <?= e((string)($username ?? 'Admin')) ?>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/platform" class="btn btn-outline-warning btn-sm">Admin Modules</a>
        <form action="/logout" method="post">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
        </form>
    </div>
</div>

<?php if (!empty($dashboardError)): ?>
    <div class="alert alert-warning"><?= e((string)$dashboardError) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-xl-2"><div class="glass rounded-4 p-3 h-100"><div class="text-secondary small">Users</div><div class="h5 mb-0"><?= number_format((int)($overview['users'] ?? 0)) ?></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="glass rounded-4 p-3 h-100"><div class="text-secondary small">Orders</div><div class="h5 mb-0"><?= number_format((int)($overview['orders'] ?? 0)) ?></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="glass rounded-4 p-3 h-100"><div class="text-secondary small">Revenue</div><div class="h5 mb-0"><?= number_format((float)($overview['revenue_total'] ?? 0), 4) ?></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="glass rounded-4 p-3 h-100"><div class="text-secondary small">24H Volume</div><div class="h5 mb-0"><?= number_format((float)($overview['trade_volume_24h'] ?? 0), 4) ?></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="glass rounded-4 p-3 h-100"><div class="text-secondary small">24H Deposits</div><div class="h5 mb-0"><?= number_format((float)($overview['deposits_24h'] ?? 0), 4) ?></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="glass rounded-4 p-3 h-100"><div class="text-secondary small">24H Withdrawals</div><div class="h5 mb-0"><?= number_format((float)($overview['withdrawals_24h'] ?? 0), 4) ?></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-9">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Live Growth & Profit Charts (Last 7 Days)</h2>
            <canvas id="tradeVolumeChart" height="110"></canvas>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Orders Status Pie</h2>
            <canvas id="orderPieChart" height="170"></canvas>
            <div class="mt-3 small text-secondary">
                Pending Deposits: <strong class="text-light"><?= (int)($overview['deposits_pending'] ?? 0) ?></strong><br>
                Pending Withdrawals: <strong class="text-light"><?= (int)($overview['withdrawals_pending'] ?? 0) ?></strong><br>
                Open Tickets: <strong class="text-light"><?= (int)($overview['open_tickets'] ?? 0) ?></strong><br>
                Unread Notifications: <strong class="text-light"><?= (int)($overview['unread_notifications'] ?? 0) ?></strong><br>
                Active Maintenance: <strong class="text-light"><?= (int)($overview['open_maintenance'] ?? 0) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Candlestick Chart</h2>
            <div id="candlestickChart" style="height: 260px;"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Activity Timeline</h2>
            <div class="d-flex flex-column gap-2 small">
                <?php foreach ($activityTimeline as $event): ?>
                    <div class="border border-secondary-subtle rounded-3 px-2 py-2">
                        <div class="text-light"><strong><?= e((string)($event['username'] ?? 'admin')) ?></strong> <?= e((string)($event['action'] ?? 'updated')) ?> <?= e((string)($event['entity_type'] ?? 'entity')) ?>#<?= e((string)($event['entity_id'] ?? '-')) ?></div>
                        <div class="text-secondary"><?= e((string)($event['created_at'] ?? '-')) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($activityTimeline === []): ?><div class="text-secondary">No admin activity yet</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Latest Trades</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0" id="latestTradesTable">
                    <thead><tr><th>#</th><th>Pair</th><th>Qty</th><th>Price</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTrades as $trade): ?>
                        <tr>
                            <td><?= (int)($trade['id'] ?? 0) ?></td>
                            <td><?= e((string)($trade['pair_symbol'] ?? '-')) ?></td>
                            <td><?= number_format((float)($trade['quantity'] ?? 0), 6) ?></td>
                            <td><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                            <td><?= e((string)($trade['executed_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentTrades === []): ?><tr><td colspan="5" class="text-secondary text-center">No trades yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Recent Logins</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0" id="recentLoginsTable">
                    <thead><tr><th>User</th><th>Status</th><th>IP</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentLogins as $login): ?>
                        <tr>
                            <td><?= e((string)($login['username'] ?? '-')) ?></td>
                            <td><?= e((string)($login['status'] ?? '-')) ?></td>
                            <td><?= e((string)($login['ip_address'] ?? '-')) ?></td>
                            <td><?= e((string)($login['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentLogins === []): ?><tr><td colspan="4" class="text-secondary text-center">No login history</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Latest Deposits</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentDeposits as $deposit): ?>
                        <tr>
                            <td><?= (int)($deposit['id'] ?? 0) ?></td>
                            <td><?= number_format((float)($deposit['amount'] ?? 0), 4) ?></td>
                            <td><?= e((string)($deposit['status'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentDeposits === []): ?><tr><td colspan="3" class="text-secondary text-center">No deposits yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Latest Withdrawals</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentWithdrawals as $withdrawal): ?>
                        <tr>
                            <td><?= (int)($withdrawal['id'] ?? 0) ?></td>
                            <td><?= number_format((float)($withdrawal['amount'] ?? 0), 4) ?></td>
                            <td><?= e((string)($withdrawal['status'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentWithdrawals === []): ?><tr><td colspan="3" class="text-secondary text-center">No withdrawals yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.53.0/dist/apexcharts.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script>
(() => {
    const tradeSeries = <?= $tradeVolumeJson ?: '[]' ?>;
    const growthSeries = <?= $userGrowthJson ?: '[]' ?>;
    const revenueSeries = <?= $revenueJson ?: '[]' ?>;
    const orderStatus = <?= $orderStatusJson ?: '[]' ?>;
    const candles = <?= $candlestickJson ?: '[]' ?>;
    const labels = tradeSeries.map(item => item.day);
    const volumeData = tradeSeries.map(item => Number(item.volume || 0));
    const growthData = growthSeries.map(item => Number(item.total || 0));
    const revenueData = revenueSeries.map(item => Number(item.total || 0));

    new Chart(document.getElementById('tradeVolumeChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Trade Volume', data: volumeData, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,0.2)', tension: 0.35, fill: true },
                { label: 'Daily New Users', data: growthData, borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,0.15)', tension: 0.35, fill: false },
                { label: 'Fee Revenue', data: revenueData, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.15)', tension: 0.35, fill: false }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById('orderPieChart'), {
        type: 'doughnut',
        data: {
            labels: orderStatus.map(item => item.status || 'unknown'),
            datasets: [{
                data: orderStatus.map(item => Number(item.total || 0)),
                backgroundColor: ['#38bdf8', '#fb7185', '#34d399', '#a78bfa', '#f59e0b', '#60a5fa']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    if (candles.length > 0) {
        const seriesData = candles.map(item => ({
            x: new Date(item.open_time),
            y: [Number(item.open_price || 0), Number(item.high_price || 0), Number(item.low_price || 0), Number(item.close_price || 0)]
        }));
        const chart = new ApexCharts(document.querySelector('#candlestickChart'), {
            chart: { type: 'candlestick', height: 260, toolbar: { show: false }, animations: { enabled: true } },
            series: [{ name: 'Price', data: seriesData }],
            xaxis: { type: 'datetime' },
            yaxis: { tooltip: { enabled: true } },
            theme: { mode: 'dark' }
        });
        chart.render();
    } else {
        document.getElementById('candlestickChart').innerHTML = '<div class="text-secondary small">No candlestick data available yet.</div>';
    }

    if (window.DataTable) {
        new DataTable('#latestTradesTable', { paging: false, searching: false, info: false, ordering: false });
        new DataTable('#recentLoginsTable', { paging: false, searching: false, info: false, ordering: false });
    }
})();
</script>
