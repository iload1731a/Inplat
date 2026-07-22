<?php declare(strict_types=1); ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$pools = is_array($pools ?? null) ? $pools : [];
$userStakes = is_array($userStakes ?? null) ? $userStakes : [];
$recentRewards = is_array($recentRewards ?? null) ? $recentRewards : [];
$stakingError = (string)($stakingError ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Staking</h1>
        <p class="text-secondary mb-0">Earn rewards by staking your assets in flexible and locked pools.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
        <a href="/trading" class="btn btn-outline-info btn-sm">Trading</a>
    </div>
</div>

<?php if ($stakingError !== ''): ?>
    <div class="alert alert-warning"><?= e($stakingError) ?></div>
<?php endif; ?>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label' => 'Active Stakes', 'value' => number_format((int)($summary['active_stakes'] ?? 0)), 'color' => 'success'],
        ['label' => 'Total Staked Value', 'value' => number_format((float)($summary['total_staked_value'] ?? 0), 8), 'color' => 'info'],
        ['label' => 'Total Rewards Earned', 'value' => number_format((float)($summary['total_rewards_earned'] ?? 0), 8), 'color' => 'warning'],
        ['label' => 'Available Pools', 'value' => number_format((int)($summary['available_pools'] ?? 0)), 'color' => 'secondary'],
    ];
    foreach ($kpis as $kpi):
    ?>
    <div class="col-md-3 col-6">
        <div class="glass rounded-4 p-3 text-center">
            <div class="h4 fw-bold text-<?= e($kpi['color']) ?>"><?= $kpi['value'] ?></div>
            <div class="small text-secondary"><?= e($kpi['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <!-- Available Pools -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Available Staking Pools</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pool</th>
                            <th>Currency</th>
                            <th>APY %</th>
                            <th>Lock (days)</th>
                            <th>Min Stake</th>
                            <th>Capacity</th>
                            <th>Total Staked</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pools as $pool): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string)($pool['name'] ?? '-')) ?></div>
                                <?php if ((int)($pool['lock_period_days'] ?? 0) === 0): ?>
                                    <span class="badge text-bg-success small">Flexible</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning text-dark small">Locked</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-secondary"><?= e((string)($pool['currency'] ?? '-')) ?></span></td>
                            <td class="text-success fw-semibold"><?= number_format((float)($pool['apy_percent'] ?? 0), 3) ?>%</td>
                            <td><?= (int)($pool['lock_period_days'] ?? 0) === 0 ? '<span class="text-success">None</span>' : (int)($pool['lock_period_days'] ?? 0) . ' days' ?></td>
                            <td><?= number_format((float)($pool['min_stake_amount'] ?? 0), 6) ?></td>
                            <td>
                                <?php if ($pool['max_pool_capacity'] === null || $pool['max_pool_capacity'] === ''): ?>
                                    <span class="text-secondary">Unlimited</span>
                                <?php else: ?>
                                    <?= number_format((float)$pool['max_pool_capacity'], 2) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)($pool['total_staked'] ?? 0), 4) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($pools === []): ?><tr><td colspan="7" class="text-center text-secondary">No staking pools available.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- My Active Stakes -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">My Stakes</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pool</th>
                            <th>Amount</th>
                            <th>Rewards</th>
                            <th>Status</th>
                            <th>Unlock</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($userStakes as $stake): ?>
                        <tr>
                            <td>
                                <div class="small fw-semibold"><?= e((string)($stake['pool_name'] ?? '-')) ?></div>
                                <span class="badge text-bg-secondary small"><?= e((string)($stake['currency'] ?? '-')) ?></span>
                            </td>
                            <td><?= number_format((float)($stake['amount'] ?? 0), 6) ?></td>
                            <td class="text-success"><?= number_format((float)($stake['rewards_earned'] ?? 0), 6) ?></td>
                            <td>
                                <span class="badge text-bg-<?= match($stake['status'] ?? '') {
                                    'active' => 'success',
                                    'unstaking' => 'warning text-dark',
                                    'completed' => 'secondary',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                } ?>"><?= e((string)($stake['status'] ?? '-')) ?></span>
                            </td>
                            <td class="small text-secondary">
                                <?= ($stake['unlock_at'] ?? null) !== null ? e((string)$stake['unlock_at']) : '<span class="text-success">Flexible</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($userStakes === []): ?><tr><td colspan="5" class="text-center text-secondary">No active stakes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reward History -->
<div class="glass rounded-4 p-3">
    <h2 class="h6 mb-3">Recent Reward Payouts</h2>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Pool</th>
                    <th>Currency</th>
                    <th>Reward Amount</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentRewards as $reward): ?>
                <tr>
                    <td><?= e((string)($reward['pool_name'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e((string)($reward['currency'] ?? '-')) ?></span></td>
                    <td class="text-success"><?= number_format((float)($reward['reward_amount'] ?? 0), 8) ?></td>
                    <td class="small text-secondary"><?= e((string)($reward['paid_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recentRewards === []): ?><tr><td colspan="4" class="text-center text-secondary">No reward payouts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
