<?php declare(strict_types=1); ?>
<?php
$rewards     = is_array($rewards     ?? null) ? $rewards     : [];
$rewardStats = is_array($rewardStats ?? null) ? $rewardStats : [];
require app_path('app/views/user/_nav.php');
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Rewards',   number_format((int)($rewardStats['total_rewards']   ?? 0)), 'info',    'fa-gift'],
        ['Credited',        number_format((float)($rewardStats['total_credited'] ?? 0), 4), 'success', 'fa-check-circle'],
        ['Pending',         number_format((float)($rewardStats['total_pending']  ?? 0), 4), 'warning', 'fa-clock'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= e($val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Rewards Table -->
<div class="glass rounded-4 p-4">
    <h6 class="mb-3"><i class="fas fa-gift me-2 text-primary"></i>Reward History</h6>
    <div class="table-responsive">
        <table class="table table-user">
            <thead>
                <tr><th>#</th><th>Type</th><th>Amount</th><th>Currency</th><th>Description</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rewards as $r): ?>
                <tr>
                    <td class="small"><?= (int)($r['id'] ?? 0) ?></td>
                    <td>
                        <?php
                        $rt = (string)($r['reward_type'] ?? '');
                        $ri = match($rt) { 'signup_bonus' => 'fa-star', 'milestone' => 'fa-trophy', 'trading_volume' => 'fa-chart-line', default => 'fa-gift' };
                        $rc = match($rt) { 'signup_bonus' => 'warning', 'milestone' => 'success', default => 'info' };
                        ?>
                        <span class="text-<?= $rc ?>"><i class="fas <?= $ri ?> me-1"></i><?= e(str_replace('_', ' ', $rt)) ?></span>
                    </td>
                    <td class="font-monospace text-warning small"><?= number_format((float)($r['amount'] ?? 0), 4) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($r['currency_code'] ?? '-')) ?></span></td>
                    <td class="small text-secondary"><?= e((string)($r['description'] ?? '—')) ?></td>
                    <td>
                        <?php $rs = (string)($r['status'] ?? 'pending'); ?>
                        <span class="badge bg-<?= match($rs) { 'credited' => 'success', 'cancelled' => 'danger', default => 'warning' } ?>"><?= e($rs) ?></span>
                    </td>
                    <td class="small text-secondary"><?= e(date('M d, Y', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rewards === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No rewards yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Info Card -->
<div class="glass rounded-4 p-4 mt-4">
    <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>How Rewards Work</h6>
    <ul class="text-secondary small mb-0">
        <li class="mb-1"><i class="fas fa-star text-warning me-2"></i><strong>Signup Bonus:</strong> Earned when a referred user completes registration.</li>
        <li class="mb-1"><i class="fas fa-trophy text-success me-2"></i><strong>Milestones:</strong> Special rewards for reaching referral count targets.</li>
        <li class="mb-1"><i class="fas fa-chart-line text-info me-2"></i><strong>Trading Volume:</strong> Earned when your referrals hit trading volume thresholds.</li>
        <li><i class="fas fa-coins text-warning me-2"></i>Pending rewards are credited automatically once conditions are confirmed.</li>
    </ul>
</div>
