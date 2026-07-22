<?php declare(strict_types=1); ?>
<?php
$currencies  = is_array($currencies  ?? null) ? $currencies  : [];
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
$wallets     = is_array($wallets     ?? null) ? $wallets     : [];
// Build balance map: currency_id => available_balance
$walletBalanceMap = [];
foreach ($wallets as $w) {
    $walletBalanceMap[$w['currency_id'] ?? 0] = (float)($w['available_balance'] ?? 0);
}
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Withdrawal Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-arrow-up-from-bracket me-2 text-warning"></i>New Withdrawal</h5>
            <div id="availBalanceInfo" class="alert alert-secondary small mb-3 d-none">
                Available: <strong id="availBalanceVal">0.00000000</strong>
            </div>
            <form id="withdrawForm" data-ajax="true" action="/user/wallet/withdraw" method="POST">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency</label>
                    <select name="currency_id" id="withdrawCurrency" class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-balance="<?= number_format((float)($walletBalanceMap[(int)$cur['id']] ?? 0), 8) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="withdrawAmount" class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00" required>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMaxAmount">MAX</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Destination Address <span class="text-danger">*</span></label>
                    <input type="text" name="destination_address" class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Wallet address or bank account" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Memo / Tag <span class="text-secondary">(optional)</span></label>
                    <input type="text" name="destination_memo" class="form-control bg-transparent text-light border-secondary"
                           placeholder="XRP tag, EOS memo, etc.">
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Withdrawal Method</label>
                    <select name="method" class="form-select bg-transparent text-light border-secondary">
                        <option value="crypto">Crypto Network</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="wire">Wire Transfer</option>
                    </select>
                </div>
                <div class="alert alert-warning small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Withdrawal requests are reviewed by our team. Processing time: 1-24 hours.
                </div>
                <button type="submit" class="btn btn-warning w-100">
                    <i class="fas fa-paper-plane me-1"></i>Submit Withdrawal Request
                </button>
            </form>
        </div>
    </div>

    <!-- Withdrawal History -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Withdrawal History</h5>
            <div class="table-responsive">
                <table id="withdrawTable" class="table table-user table-sm">
                    <thead><tr><th>#</th><th>Currency</th><th>Amount</th><th>Address</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($withdrawals as $wd): ?>
                        <tr>
                            <td class="small"><?= (int)($wd['id'] ?? 0) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($wd['net_amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary text-truncate" style="max-width:120px">
                                <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 16)) ?>…
                            </td>
                            <td>
                                <?php
                                $ws = (string)($wd['status'] ?? 'pending');
                                $wc = match($ws) { 'completed' => 'success', 'failed', 'cancelled', 'rejected' => 'danger', 'processing' => 'info', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $wc ?>"><?= e($ws) ?></span>
                            </td>
                            <td class="small"><?= e(date('M d, Y H:i', strtotime((string)($wd['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const walletBalanceMap = <?= json_encode($walletBalanceMap) ?>;

$('#withdrawTable').DataTable({ order: [[0,'desc']], pageLength: 15 });

$('#withdrawCurrency').on('change', function () {
    const opt = $(this).find(':selected');
    const bal = opt.data('balance') || '0.00000000';
    $('#availBalanceVal').text(bal);
    $('#availBalanceInfo').removeClass('d-none');
});

$('#btnMaxAmount').on('click', function () {
    const opt = $('#withdrawCurrency').find(':selected');
    const bal = opt.data('balance') || 0;
    $('#withdrawAmount').val(bal);
});
</script>
