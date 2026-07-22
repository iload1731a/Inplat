<?php declare(strict_types=1); ?>
<?php
$referralCode   = (string)($referralCode   ?? '');
$stats          = is_array($stats          ?? null) ? $stats          : [];
$referrals      = is_array($referrals      ?? null) ? $referrals      : [];
$commissions    = is_array($commissions    ?? null) ? $commissions    : [];
$earningsSeries = is_array($earningsSeries ?? null) ? $earningsSeries : [];
$referralLink   = (string)config('app.url') . '/register?ref=' . $referralCode;
$earnLabels     = json_encode(array_column($earningsSeries, 'day'));
$earnValues     = json_encode(array_map('floatval', array_column($earningsSeries, 'earned')));
require app_path('app/views/user/_nav.php');
?>

<!-- Referral Link -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-link me-2 text-info"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" id="refLink" class="form-control bg-transparent text-light border-secondary font-monospace"
               value="<?= e($referralLink) ?>" readonly>
        <button class="btn btn-info" onclick="copyRefLink()">
            <i class="fas fa-copy me-1"></i>Copy
        </button>
    </div>
    <div class="mt-2 text-secondary small">
        Share this link to earn commissions when your referrals trade.
        Code: <span class="badge bg-info"><?= e($referralCode ?: '(not assigned)') ?></span>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Referred',    number_format((int)($stats['total_referred']     ?? 0)), 'info',    'fa-users'],
        ['Qualified',         number_format((int)($stats['qualified_referrals']?? 0)), 'success', 'fa-user-check'],
        ['Total Earned',      number_format((float)($stats['total_earned']     ?? 0), 4), 'warning', 'fa-coins'],
        ['Pending Earnings',  number_format((float)($stats['pending_earnings'] ?? 0), 4), 'secondary','fa-clock'],
    ];
    foreach ($statCards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas <?= $icon ?> fa-2x text-<?= $color ?> mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= e($val) ?></div>
            <div class="text-secondary small"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Earnings Chart -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4 h-100">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-warning"></i>Earnings (30d)</h5>
            <div id="earningsChart"></div>
        </div>
    </div>

    <!-- Referrals Table -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-users me-2"></i>Referrals</h5>
            <div class="table-responsive">
                <table id="referralsTable" class="table table-user table-sm">
                    <thead><tr><th>Username</th><th>Joined</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($referrals as $ref): ?>
                        <tr>
                            <td><?= e((string)($ref['referred_username'] ?? '—')) ?></td>
                            <td class="small"><?= e(date('M d, Y', strtotime((string)($ref['user_joined_at'] ?? 'now')))) ?></td>
                            <td>
                                <?php
                                $rs = (string)($ref['status'] ?? 'pending');
                                $rc = match($rs) { 'qualified' => 'success', 'paid' => 'info', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $rc ?>"><?= e($rs) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($referrals === []): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-3">No referrals yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Commissions Table -->
    <div class="col-12">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-coins me-2 text-warning"></i>Commission History</h5>
            <div class="table-responsive">
                <table id="commissionsTable" class="table table-user table-sm">
                    <thead><tr><th>#</th><th>From User</th><th>Amount</th><th>Currency</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($commissions as $comm): ?>
                        <tr>
                            <td class="small"><?= (int)($comm['id'] ?? 0) ?></td>
                            <td><?= e((string)($comm['from_user'] ?? '—')) ?></td>
                            <td class="font-monospace small text-warning"><?= number_format((float)($comm['commission_amount'] ?? 0), 8) ?></td>
                            <td><span class="badge bg-secondary"><?= e((string)($comm['currency_code'] ?? '-')) ?></span></td>
                            <td>
                                <?php
                                $cs = (string)($comm['status'] ?? 'pending');
                                $cc = match($cs) { 'paid' => 'success', 'cancelled' => 'danger', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $cc ?>"><?= e($cs) ?></span>
                            </td>
                            <td class="small"><?= e(date('M d, Y', strtotime((string)($comm['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#referralsTable').DataTable({ pageLength: 10 });
$('#commissionsTable').DataTable({ order: [[0,'desc']], pageLength: 15 });

function copyRefLink() {
    navigator.clipboard.writeText(document.getElementById('refLink').value)
        .then(() => Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false }))
        .catch(() => {
            const el = document.getElementById('refLink');
            el.select(); document.execCommand('copy');
        });
}

const earnLabels = <?= $earnLabels ?: '[]' ?>;
const earnValues = <?= $earnValues ?: '[]' ?>;

if (earnLabels.length > 0) {
    new ApexCharts(document.getElementById('earningsChart'), {
        series: [{ name: 'Earned', data: earnValues }],
        chart: { type: 'area', height: 200, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: earnLabels, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        theme: { mode: 'dark' },
        colors: ['#fbbf24'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}
</script>
