<?php declare(strict_types=1); ?>
<?php
$overview = is_array($overview ?? null) ? $overview : [];
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$recentDeposits = is_array($recentDeposits ?? null) ? $recentDeposits : [];
$recentWithdrawals = is_array($recentWithdrawals ?? null) ? $recentWithdrawals : [];
$tradeVolumeSeries = is_array($tradeVolumeSeries ?? null) ? $tradeVolumeSeries : [];
$userGrowthSeries = is_array($userGrowthSeries ?? null) ? $userGrowthSeries : [];
$tradeVolumeJson = json_encode($tradeVolumeSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$userGrowthJson = json_encode($userGrowthSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Admin Dashboard</h1>
        <p class="text-secondary mb-0">Welcome back, <?= e((string)($username ?? 'Admin')) ?>.</p>
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
    <div class="col-md-3"><div class="glass rounded-4 p-3"><div class="text-secondary small">Total Users</div><div class="h4 mb-0"><?= number_format((int)($overview['users'] ?? 0)) ?></div></div></div>
    <div class="col-md-3"><div class="glass rounded-4 p-3"><div class="text-secondary small">Total Orders</div><div class="h4 mb-0"><?= number_format((int)($overview['orders'] ?? 0)) ?></div></div></div>
    <div class="col-md-3"><div class="glass rounded-4 p-3"><div class="text-secondary small">Trades 24H Volume</div><div class="h4 mb-0"><?= number_format((float)($overview['trade_volume_24h'] ?? 0), 4) ?></div></div></div>
    <div class="col-md-3"><div class="glass rounded-4 p-3"><div class="text-secondary small">Fees 24H</div><div class="h4 mb-0"><?= number_format((float)($overview['fee_revenue_24h'] ?? 0), 4) ?></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Trading Volume (Last 7 Days)</h2>
            <canvas id="tradeVolumeChart" height="120"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Operational Snapshot</h2>
            <canvas id="statusChart" height="180"></canvas>
            <div class="mt-3 small text-secondary">
                Pending Deposits: <strong class="text-light"><?= (int)($overview['deposits_pending'] ?? 0) ?></strong><br>
                Pending Withdrawals: <strong class="text-light"><?= (int)($overview['withdrawals_pending'] ?? 0) ?></strong><br>
                Total Trades: <strong class="text-light"><?= (int)($overview['trades'] ?? 0) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Latest Trades</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>Pair</th><th>Qty</th><th>Price</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTrades as $trade): ?>
                        <tr>
                            <td><?= (int)($trade['id'] ?? 0) ?></td>
                            <td><?= e((string)($trade['pair_symbol'] ?? '-')) ?></td>
                            <td><?= number_format((float)($trade['quantity'] ?? 0), 6) ?></td>
                            <td><?= number_format((float)($trade['price'] ?? 0), 6) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentTrades === []): ?><tr><td colspan="4" class="text-secondary text-center">No trades yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
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
    <div class="col-lg-4">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    const tradeSeries = <?= $tradeVolumeJson ?: '[]' ?>;
    const growthSeries = <?= $userGrowthJson ?: '[]' ?>;
    const labels = tradeSeries.map(item => item.day);
    const volumeData = tradeSeries.map(item => Number(item.volume || 0));
    const growthData = growthSeries.map(item => Number(item.total || 0));

    new Chart(document.getElementById('tradeVolumeChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Trade Volume', data: volumeData, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,0.2)', tension: 0.35, fill: true },
                { label: 'New Users', data: growthData, borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,0.15)', tension: 0.35, fill: false }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending Deposits', 'Pending Withdrawals', 'Total Trades'],
            datasets: [{
                data: [
                    <?= (int)($overview['deposits_pending'] ?? 0) ?>,
                    <?= (int)($overview['withdrawals_pending'] ?? 0) ?>,
                    <?= (int)($overview['trades'] ?? 0) ?>
                ],
                backgroundColor: ['#38bdf8', '#fb7185', '#34d399']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
})();
</script>
