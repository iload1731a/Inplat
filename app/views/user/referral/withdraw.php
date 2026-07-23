<?php declare(strict_types=1); ?>
<?php
$payouts    = is_array($payouts    ?? null) ? $payouts    : [];
$balances   = is_array($balances   ?? null) ? $balances   : [];
$settings   = is_array($settings   ?? null) ? $settings   : [];
$hasPending = (bool)($hasPending   ?? false);
$minPayout  = (float)($settings['min_payout_amount'] ?? 10);
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Request Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-4"><i class="fas fa-money-bill-wave me-2 text-warning"></i>Request Commission Payout</h6>

            <?php if ($hasPending): ?>
            <div class="alert alert-warning rounded-3">
                <i class="fas fa-hourglass-half me-2"></i>You have a pending payout request. Please wait for it to be processed before submitting a new one.
            </div>
            <?php elseif ($balances === []): ?>
            <div class="alert alert-info rounded-3">
                <i class="fas fa-info-circle me-2"></i>No pending commission balance available for withdrawal.
            </div>
            <?php else: ?>

            <form method="post" action="/user/referral/withdraw" id="payoutForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

                <div class="mb-3">
                    <label class="form-label small text-secondary">Currency & Available Balance</label>
                    <select name="currency_id" class="form-select bg-transparent text-light border-secondary" required id="currencySelect">
                        <option value="">Select currency...</option>
                        <?php foreach ($balances as $bal): ?>
                        <option value="<?= (int)$bal['currency_id'] ?>"
                                data-available="<?= number_format((float)$bal['available_balance'], 8, '.', '') ?>">
                            <?= e((string)$bal['currency_code']) ?> — <?= number_format((float)$bal['available_balance'], 6) ?> available
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-secondary" id="availableHint"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Amount</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="amountInput" step="0.00000001" min="<?= $minPayout ?>"
                               class="form-control bg-transparent text-light border-secondary" required
                               placeholder="Min <?= $minPayout ?>">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMax">MAX</button>
                    </div>
                    <div class="form-text text-secondary">Minimum payout: <?= number_format($minPayout, 2) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Wallet Address <span class="text-danger">*</span></label>
                    <input type="text" name="wallet_address" class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Your wallet address" required maxlength="255">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Network</label>
                    <select name="network" class="form-select bg-transparent text-light border-secondary">
                        <option value="">Auto-detect / Not applicable</option>
                        <option value="ERC20">ERC20 (Ethereum)</option>
                        <option value="TRC20">TRC20 (Tron)</option>
                        <option value="BEP20">BEP20 (BSC)</option>
                        <option value="Polygon">Polygon</option>
                        <option value="BTC">Bitcoin</option>
                        <option value="SOL">Solana</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small text-secondary">Notes (optional)</label>
                    <textarea name="notes" class="form-control bg-transparent text-light border-secondary"
                              rows="2" maxlength="500" placeholder="Additional notes for admin..."></textarea>
                </div>

                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Submit this payout request?')">
                    <i class="fas fa-paper-plane me-2"></i>Submit Payout Request
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payout History -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-history me-2"></i>Payout History</h6>
            <div class="table-responsive">
                <table class="table table-user table-sm">
                    <thead>
                        <tr><th>#</th><th>Amount</th><th>Currency</th><th>Wallet</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payouts as $p): ?>
                        <tr>
                            <td class="small"><?= (int)($p['id'] ?? 0) ?></td>
                            <td class="font-monospace small text-warning"><?= number_format((float)($p['amount'] ?? 0), 6) ?></td>
                            <td><span class="badge bg-secondary"><?= e((string)($p['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace" style="font-size:.7rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                title="<?= e((string)($p['wallet_address'] ?? '')) ?>">
                                <?= e(substr((string)($p['wallet_address'] ?? '—'), 0, 16)) ?>...
                            </td>
                            <td>
                                <?php $ps = (string)($p['status'] ?? 'pending'); ?>
                                <span class="badge bg-<?= match($ps) { 'paid' => 'success', 'approved' => 'info', 'rejected' => 'danger', default => 'warning' } ?>">
                                    <?= e($ps) ?>
                                </span>
                            </td>
                            <td class="small text-secondary"><?= e(date('M d, Y', strtotime((string)($p['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($payouts === []): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-3">No payout history.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Process Info -->
        <div class="glass rounded-4 p-4 mt-4">
            <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Payout Process</h6>
            <ul class="text-secondary small mb-0">
                <li class="mb-2"><i class="fas fa-circle text-info me-2" style="font-size:.5rem"></i>Payout requests are reviewed within 1-3 business days.</li>
                <li class="mb-2"><i class="fas fa-circle text-info me-2" style="font-size:.5rem"></i>Minimum payout: <?= number_format($minPayout, 2) ?> per request.</li>
                <li class="mb-2"><i class="fas fa-circle text-info me-2" style="font-size:.5rem"></i>Only one pending request at a time is allowed.</li>
                <li><i class="fas fa-circle text-info me-2" style="font-size:.5rem"></i>Commissions are drawn from your pending balance.</li>
            </ul>
        </div>
    </div>
</div>

<script>
const availableMap = {};
document.querySelectorAll('#currencySelect option[data-available]').forEach(o => {
    availableMap[o.value] = parseFloat(o.getAttribute('data-available'));
});
$('#currencySelect').on('change', function () {
    const avail = availableMap[this.value] || 0;
    $('#availableHint').text('Available: ' + avail.toFixed(8));
});
$('#btnMax').on('click', function () {
    const cid = $('#currencySelect').val();
    if (cid) {
        $('#amountInput').val(availableMap[cid] || 0);
    }
});
</script>
