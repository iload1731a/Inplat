<?php declare(strict_types=1); ?>
<?php
$wallets = is_array($wallets ?? null) ? $wallets : [];
$totalBalance = array_sum(array_column($wallets, 'available_balance'));
$allocationJson = json_encode(array_values(array_filter(array_map(static fn($w) => [
    'code'    => $w['code'] ?? '-',
    'balance' => (float)($w['available_balance'] ?? 0),
], $wallets), static fn($w) => $w['balance'] > 0)), JSON_UNESCAPED_UNICODE);
require app_path('app/views/user/_nav.php');
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="text-secondary small">Total Wallets</div>
            <div class="h3 fw-bold text-info counter-animate"><?= count($wallets) ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="text-secondary small">Est. Portfolio Value</div>
            <div class="h3 fw-bold counter-animate"><?= number_format($totalBalance, 4) ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="text-secondary small">Active Currencies</div>
            <div class="h3 fw-bold text-success counter-animate">
                <?= count(array_filter($wallets, static fn($w) => (float)($w['available_balance'] ?? 0) > 0)) ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Wallets Table -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-wallet me-2 text-info"></i>My Wallets</h5>
                <div class="d-flex gap-2">
                    <a href="/user/wallet/deposit" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Deposit</a>
                    <a href="/user/wallet/withdraw" class="btn btn-sm btn-outline-warning"><i class="fas fa-minus me-1"></i>Withdraw</a>
                </div>
            </div>
            <div class="table-responsive">
                <table id="walletTable" class="table table-user table-sm">
                    <thead>
                        <tr><th>Currency</th><th>Type</th><th>Available</th><th>Locked</th><th>Total</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wallets as $wallet): ?>
                        <?php
                        $avail = (float)($wallet['available_balance'] ?? 0);
                        $locked = (float)($wallet['locked_balance'] ?? 0);
                        $total = $avail + $locked;
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($wallet['logo_url'])): ?>
                                        <img src="<?= e((string)$wallet['logo_url']) ?>" width="24" height="24" class="rounded-circle" alt="">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:.65rem">
                                            <?= e(mb_strtoupper(mb_substr((string)($wallet['code'] ?? '-'), 0, 2))) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold"><?= e((string)($wallet['code'] ?? '-')) ?></div>
                                        <div class="text-secondary" style="font-size:.72rem"><?= e((string)($wallet['currency_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= ($wallet['currency_type'] ?? '') === 'crypto' ? 'info' : 'primary' ?>"><?= e((string)($wallet['currency_type'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format($avail, 8) ?></td>
                            <td class="font-monospace small text-warning"><?= number_format($locked, 8) ?></td>
                            <td class="font-monospace small fw-semibold"><?= number_format($total, 8) ?></td>
                            <td>
                                <a href="/user/wallet/deposit" class="btn btn-xs btn-outline-success me-1" title="Deposit"><i class="fas fa-arrow-down"></i></a>
                                <a href="/user/wallet/withdraw" class="btn btn-xs btn-outline-warning" title="Withdraw"><i class="fas fa-arrow-up"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($wallets === []): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">No wallets found. Make a deposit to get started.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Allocation Chart -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-purple"></i>Allocation</h5>
            <div id="allocationChart"></div>
            <div class="mt-3">
                <a href="/user/wallet/history" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-history me-1"></i>Transaction History
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$('#walletTable').DataTable({ pageLength: 20, order: [[4,'desc']] });

const allocationData = <?= $allocationJson ?: '[]' ?>;
if (allocationData.length > 0) {
    new ApexCharts(document.getElementById('allocationChart'), {
        series: allocationData.map(w => w.balance),
        labels: allocationData.map(w => w.code),
        chart: { type: 'donut', height: 260, background: 'transparent' },
        theme: { mode: 'dark' },
        legend: { position: 'bottom' },
        colors: ['#38bdf8','#fb7185','#34d399','#fbbf24','#a78bfa','#fb923c','#22d3ee','#f472b6'],
        dataLabels: { enabled: true },
        stroke: { show: false },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Assets' } } } } },
    }).render();
}
</script>
