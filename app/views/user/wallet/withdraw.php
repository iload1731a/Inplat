<?php declare(strict_types=1); ?>
<?php
$currencies  = is_array($currencies  ?? null) ? $currencies  : [];
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
$wallets     = is_array($wallets     ?? null) ? $wallets     : [];
$whitelist   = is_array($whitelist   ?? null) ? $whitelist   : [];
$walletBalanceMap = [];
foreach ($wallets as $w) {
    if ($w['wallet_type'] === 'spot') {
        $walletBalanceMap[(int)($w['currency_id'] ?? 0)] = (float)($w['available_balance'] ?? 0);
    }
}
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="fas fa-arrow-up-from-bracket me-2 text-warning"></i>Withdraw Funds</h1>
    <a href="/user/wallet/addresses" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-shield-alt me-1"></i>Manage Addresses
    </a>
</div>

<div class="row g-4">
    <!-- Withdrawal Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3">New Withdrawal Request</h5>
            <div id="availBalanceInfo" class="alert alert-secondary small mb-3 d-none">
                Available: <strong id="availBalanceVal">0.00000000</strong>
            </div>
            <form id="withdrawForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency *</label>
                    <select name="currency_id" id="withdrawCurrency"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>"
                                data-balance="<?= number_format((float)($walletBalanceMap[(int)$cur['id']] ?? 0), 8) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount *</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="withdrawAmount"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00000000" required>
                        <span class="input-group-text border-secondary bg-transparent text-secondary" id="currencyCodeLabel">—</span>
                        <button type="button" class="btn btn-outline-secondary" id="btnMaxAmount">MAX</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Destination Address *</label>
                    <?php if (!empty($whitelist)): ?>
                    <select id="whitelistSelect" class="form-select bg-transparent text-light border-secondary mb-2">
                        <option value="">— Use whitelisted address —</option>
                        <?php foreach ($whitelist as $wa):
                            if (($wa['status'] ?? '') !== 'active') continue;
                        ?>
                        <option value="<?= e((string)$wa['address']) ?>"
                                data-tag="<?= e((string)($wa['tag_or_memo'] ?? '')) ?>"
                                data-currency="<?= (int)$wa['currency_id'] ?>">
                            [<?= e((string)($wa['currency_code'] ?? '')) ?>] <?= e((string)($wa['label'] ?? '')) ?> — <?= e(substr((string)$wa['address'], 0, 20)) ?>…
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <input type="text" name="destination_address" id="destAddress"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Destination wallet address" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small">Tag / Memo <span class="text-secondary">(XRP, XLM, etc.)</span></label>
                    <input type="text" name="destination_tag" id="destTag"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Optional memo or destination tag" maxlength="100">
                </div>
                <div class="alert alert-warning small rounded-3">
                    <i class="fas fa-clock me-1"></i>
                    Withdrawals are reviewed by our team. Processing time: 1–24 hours.
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-semibold">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="withdrawSpinner"></span>
                    <i class="fas fa-paper-plane me-1"></i>Submit Withdrawal
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
                    <thead>
                        <tr>
                            <th>#</th><th>Currency</th><th>Amount</th><th>Fee</th>
                            <th>Address</th><th>Status</th><th>Date</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($withdrawals as $wd): ?>
                        <tr>
                            <td class="small text-secondary"><?= (int)($wd['id'] ?? 0) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($wd['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary"><?= number_format((float)($wd['fee'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary" style="max-width:120px;overflow:hidden;text-overflow:ellipsis">
                                <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 20)) ?>…
                            </td>
                            <td>
                                <?php
                                $ws = (string)($wd['status'] ?? 'pending');
                                $wc = match($ws) {
                                    'completed'  => 'success',
                                    'rejected', 'cancelled' => 'danger',
                                    'processing' => 'info',
                                    'approved'   => 'primary',
                                    default      => 'warning text-dark',
                                };
                                ?>
                                <span class="badge bg-<?= $wc ?>"><?= e($ws) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e(substr((string)($wd['requested_at'] ?? $wd['created_at'] ?? ''), 0, 16)) ?></td>
                            <td>
                                <?php if ($ws === 'pending'): ?>
                                <button class="btn btn-xs btn-outline-danger cancel-withdrawal-btn"
                                        data-id="<?= (int)$wd['id'] ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php elseif (!empty($wd['tx_hash'])): ?>
                                    <span class="text-secondary small font-monospace" title="<?= e((string)$wd['tx_hash']) ?>">
                                        <?= e(substr((string)$wd['tx_hash'], 0, 10)) ?>…
                                    </span>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($withdrawals === []): ?>
                        <tr><td colspan="8" class="text-center text-secondary py-4">No withdrawals yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const walletBalanceMap = <?= json_encode($walletBalanceMap) ?>;
const _csrf = '<?= e(\App\Libraries\Csrf::token()) ?>';

$('#withdrawTable').DataTable({ order: [[0,'desc']], pageLength: 15 });

$('#withdrawCurrency').on('change', function () {
    const opt = $(this).find(':selected');
    const bal = opt.data('balance') || '0.00000000';
    const code = opt.data('code') || '—';
    $('#availBalanceVal').text(bal + ' ' + code);
    $('#availBalanceInfo').removeClass('d-none');
    $('#currencyCodeLabel').text(code);
});

$('#btnMaxAmount').on('click', function () {
    const bal = $('#withdrawCurrency').find(':selected').data('balance') || 0;
    $('#withdrawAmount').val(bal);
});

// Whitelist address auto-fill
<?php if (!empty($whitelist)): ?>
document.getElementById('whitelistSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('destAddress').value = opt.value;
        document.getElementById('destTag').value     = opt.dataset.tag || '';
    }
});
<?php endif; ?>

// Withdrawal form submit
document.getElementById('withdrawForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('withdrawSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/user/wallet/withdraw', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': _csrf},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Request Submitted!', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } catch(err) {
        Swal.fire({ icon:'error', title:'Error', text: 'Network error. Please try again.' });
    } finally {
        spinner.classList.add('d-none');
    }
});

// Cancel withdrawal
document.querySelectorAll('.cancel-withdrawal-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            icon:'warning', title:'Cancel Withdrawal?',
            text:'This action cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Cancel It',
            confirmButtonColor: '#ef4444'
        });
        if (!confirmed.isConfirmed) return;
        const res  = await fetch('/user/wallet/withdraw/cancel', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ _token: _csrf, withdrawal_id: parseInt(btn.dataset.id) })
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Cancelled', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    });
});
</script>

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
