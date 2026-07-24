<?php declare(strict_types=1); ?>
<?php
$referralCode        = (string)($referralCode   ?? '');
$stats               = is_array($stats          ?? null) ? $stats          : [];
$tierStats           = is_array($tierStats       ?? null) ? $tierStats       : [];
$earningsSeries      = is_array($earningsSeries  ?? null) ? $earningsSeries  : [];
$earningsByCurrency  = is_array($earningsByCurrency ?? null) ? $earningsByCurrency : [];
$recentReferrals     = is_array($recentReferrals ?? null) ? $recentReferrals : [];
$recentCommissions   = is_array($recentCommissions ?? null) ? $recentCommissions : [];
$tiers               = is_array($tiers           ?? null) ? $tiers           : [];
$settings            = is_array($settings        ?? null) ? $settings        : [];
$programEnabled      = (bool)($programEnabled    ?? true);
$referralLink        = (string)config('app.url') . '/register?ref=' . $referralCode;
$earnLabels          = json_encode(array_column($earningsSeries, 'day'));
$earnValues          = json_encode(array_map('floatval', array_column($earningsSeries, 'earned')));
require app_path('app/views/user/_nav.php');
?>

<?php if (!$programEnabled): ?>
<div class="alert alert-warning rounded-4">
    <i class="fas fa-pause-circle me-2"></i>The affiliate program is currently disabled.
</div>
<?php else: ?>

<!-- Referral Link -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-link me-2 text-info"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" id="refLink" class="form-control bg-transparent text-light border-secondary font-monospace"
               value="<?= e($referralLink) ?>" readonly>
        <button class="btn btn-info" id="btnCopyRefLink">
            <i class="fas fa-copy me-1"></i>Copy
        </button>
    </div>
    <div class="mt-2 text-secondary small">
        Share this link to earn commissions when your referrals trade.
        Code: <span class="badge bg-info"><?= e($referralCode ?: '(not assigned)') ?></span>
    </div>
    <div class="mt-3 d-flex flex-wrap gap-2">
        <a href="https://twitter.com/intent/tweet?text=Join+me+on+this+trading+platform!+<?= urlencode($referralLink) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">
            <i class="fab fa-twitter me-1"></i>Tweet
        </a>
        <a href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=Join+me+on+this+trading+platform!" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">
            <i class="fab fa-telegram me-1"></i>Telegram
        </a>
        <a href="/user/referral/withdraw" class="btn btn-sm btn-outline-warning ms-auto">
            <i class="fas fa-money-bill-wave me-1"></i>Withdraw Commissions
        </a>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Referred',   number_format((int)($stats['total_referred']      ?? 0)), 'info',      'fa-users'],
        ['Qualified',        number_format((int)($stats['qualified_referrals'] ?? 0)), 'success',   'fa-user-check'],
        ['Total Earned',     number_format((float)($stats['total_earned']      ?? 0), 4), 'warning', 'fa-coins'],
        ['Pending',          number_format((float)($stats['pending_earnings']  ?? 0), 4), 'secondary','fa-clock'],
        ['Last 30d Earned',  number_format((float)($stats['earned_30d']        ?? 0), 4), 'primary', 'fa-chart-line'],
        ['Trade Commissions',number_format((int)($stats['trade_commissions']   ?? 0)), 'info',      'fa-exchange-alt'],
        ['Total Withdrawn',  number_format((float)($stats['total_withdrawn']   ?? 0), 4), 'success', 'fa-arrow-circle-up'],
        ['Pending Payouts',  number_format((int)($stats['pending_payouts']     ?? 0)), 'warning',   'fa-hourglass-half'],
    ];
    foreach ($statCards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2 d-block"></i>
            <div class="h5 fw-bold"><?= e($val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Earnings Chart -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-chart-area me-2 text-warning"></i>Earnings (30d)</h6>
            <div id="earningsChart" style="min-height:200px"></div>
        </div>
    </div>

    <!-- Commission Tiers -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-layer-group me-2 text-success"></i>Commission Tiers</h6>
            <?php if ($tiers === []): ?>
                <div class="text-secondary small text-center py-3">No tiers configured.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>Level</th><th>Rate</th><th>Min Referred</th><th>Your Earnings</th></tr></thead>
                    <tbody>
                    <?php foreach ($tiers as $tier):
                        $tierEarned = 0.0;
                        foreach ($tierStats as $ts) {
                            if ((int)$ts['level'] === (int)$tier['level']) {
                                $tierEarned = (float)$ts['total_earned'];
                                break;
                            }
                        }
                    ?>
                    <tr>
                        <td><span class="badge bg-info"><?= e((string)$tier['label']) ?></span></td>
                        <td class="text-warning fw-semibold"><?= number_format((float)$tier['commission_rate'], 2) ?>%</td>
                        <td><?= (int)$tier['min_referred'] > 0 ? number_format((int)$tier['min_referred']) . ' referrals' : 'Instant' ?></td>
                        <td class="font-monospace small"><?= number_format($tierEarned, 4) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Earnings by Currency -->
<?php if ($earningsByCurrency !== []): ?>
<div class="glass rounded-4 p-4 mb-4">
    <h6 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Earnings by Currency</h6>
    <div class="row g-3">
        <?php foreach ($earningsByCurrency as $eb): ?>
        <div class="col-6 col-md-3">
            <div class="glass rounded-3 p-3 text-center border border-secondary border-opacity-25">
                <div class="badge bg-secondary mb-2"><?= e((string)$eb['currency_code']) ?></div>
                <div class="small text-success fw-semibold"><?= number_format((float)$eb['total_paid'], 4) ?> <span class="text-secondary">paid</span></div>
                <div class="small text-warning"><?= number_format((float)$eb['total_pending'], 4) ?> <span class="text-secondary">pending</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Recent Referrals -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Recent Referrals</h6>
                <a href="/user/referral/referrals" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>User</th><th>Status</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentReferrals as $ref): ?>
                        <tr>
                            <td><?= e((string)($ref['referred_username'] ?? '—')) ?></td>
                            <td>
                                <?php
                                $rs = (string)($ref['status'] ?? 'pending');
                                $rc = match($rs) { 'qualified' => 'success', 'active' => 'info', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $rc ?>"><?= e($rs) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e(date('M d', strtotime((string)($ref['user_joined_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentReferrals === []): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-3 small">No referrals yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Commissions -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-coins me-2 text-warning"></i>Recent Commissions</h6>
                <a href="/user/referral/commissions" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead><tr><th>Amount</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentCommissions as $comm): ?>
                        <tr>
                            <td class="font-monospace small text-warning">
                                <?= number_format((float)($comm['amount'] ?? 0), 6) ?>
                                <span class="badge bg-secondary"><?= e((string)($comm['currency_code'] ?? '-')) ?></span>
                            </td>
                            <td class="small"><?= e((string)($comm['commission_type'] ?? '—')) ?></td>
                            <td>
                                <?php
                                $cs = (string)($comm['status'] ?? 'pending');
                                $cc = match($cs) { 'paid' => 'success', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $cc ?>"><?= e($cs) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e(date('M d', strtotime((string)($comm['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentCommissions === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-3 small">No commissions yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Nav -->
<div class="glass rounded-4 p-4">
    <h6 class="mb-3"><i class="fas fa-th me-2"></i>Quick Access</h6>
    <div class="d-flex flex-wrap gap-2">
        <a href="/user/referral/referrals" class="btn btn-outline-info btn-sm"><i class="fas fa-users me-1"></i>All Referrals</a>
        <a href="/user/referral/network"   class="btn btn-outline-success btn-sm"><i class="fas fa-project-diagram me-1"></i>Network Tree</a>
        <a href="/user/referral/commissions" class="btn btn-outline-warning btn-sm"><i class="fas fa-coins me-1"></i>Commissions</a>
        <a href="/user/referral/rewards"   class="btn btn-outline-primary btn-sm"><i class="fas fa-gift me-1"></i>Rewards</a>
        <a href="/user/referral/withdraw"  class="btn btn-outline-warning btn-sm"><i class="fas fa-money-bill-wave me-1"></i>Withdraw</a>
    </div>
</div>

<?php endif; ?>

<script>
$('#btnCopyRefLink').on('click', function () {
    const val = $('#refLink').val();
    navigator.clipboard.writeText(val)
        .then(() => Swal.fire({ icon: 'success', title: 'Copied!', timer: 900, showConfirmButton: false }))
        .catch(() => Swal.fire({ icon: 'error', title: 'Copy failed', text: 'Please copy the link manually.', timer: 2000, showConfirmButton: false }));
});

const earnLabels = <?= $earnLabels ?: '[]' ?>;
const earnValues = <?= $earnValues ?: '[]' ?>;
if (earnLabels.length > 0) {
    new ApexCharts(document.getElementById('earningsChart'), {
        series: [{ name: 'Earned', data: earnValues }],
        chart: { type: 'area', height: 200, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: earnLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30, rotateAlways: false } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        theme: { mode: 'dark' },
        colors: ['#fbbf24'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
        tooltip: { theme: 'dark' },
    }).render();
}
</script>
