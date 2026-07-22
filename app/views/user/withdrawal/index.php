<?php declare(strict_types=1); ?>
<?php
$currencies     = is_array($currencies     ?? null) ? $currencies     : [];
$withdrawals    = is_array($withdrawals    ?? null) ? $withdrawals    : [];
$stats          = is_array($stats          ?? null) ? $stats          : [];
$monthly_totals = is_array($monthly_totals ?? null) ? $monthly_totals : [];
$filters        = is_array($filters        ?? null) ? $filters        : [];

$cryptoCurrencies = array_values(array_filter($currencies, fn($c) => ($c['type'] ?? '') === 'crypto'));
$fiatCurrencies   = array_values(array_filter($currencies, fn($c) => ($c['type'] ?? '') === 'fiat'));

require app_path('app/views/user/_nav.php');
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0">
        <i class="fas fa-arrow-circle-up me-2 text-warning"></i>Withdraw Funds
    </h1>
    <div class="d-flex gap-2">
        <a href="/user/withdrawal/fiat"    class="btn btn-outline-info btn-sm"><i class="fas fa-university me-1"></i>Bank Withdrawal</a>
        <a href="/user/withdrawal/report"  class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
        <a href="/user/wallet/addresses"   class="btn btn-outline-secondary btn-sm"><i class="fas fa-shield-alt me-1"></i>Whitelist</a>
    </div>
</div>

<!-- Stats bar -->
<div class="row g-3 mb-4">
    <?php
    $userStats = [
        ['label'=>'Total',     'key'=>'total',          'color'=>'secondary'],
        ['label'=>'Pending',   'key'=>'pending',        'color'=>'warning'],
        ['label'=>'Processing','key'=>'processing',     'color'=>'info'],
        ['label'=>'Completed', 'key'=>'completed',      'color'=>'success'],
        ['label'=>'Rejected',  'key'=>'rejected',       'color'=>'danger'],
    ];
    foreach ($userStats as $s):
    ?>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($s['label']) ?></div>
            <div class="h5 mb-0 fw-bold text-<?= e($s['color']) ?>">
                <?= number_format((int)($stats[$s['key']] ?? 0)) ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Total Withdrawn</div>
            <div class="h6 mb-0 fw-bold font-monospace text-light">
                <?= number_format((float)($stats['total_withdrawn'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Fees Paid</div>
            <div class="h6 mb-0 fw-bold font-monospace text-danger">
                <?= number_format((float)($stats['total_fees'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Crypto Withdrawal Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3">
                <i class="fas fa-coins me-2 text-warning"></i>Crypto Withdrawal
            </h5>

            <div class="alert alert-secondary small mb-3 d-none" id="feeInfoBox">
                <div class="row g-1">
                    <div class="col-6">Available: <strong id="feeInfoBalance">—</strong></div>
                    <div class="col-6">Daily Remaining: <strong id="feeInfoRemaining">—</strong></div>
                    <div class="col-6">Min Amount: <strong id="feeInfoMin">—</strong></div>
                    <div class="col-6">Fee: <strong id="feeInfoFee">—</strong></div>
                    <div class="col-12 mt-1">Net You Receive: <strong class="text-success" id="feeInfoNet">—</strong></div>
                </div>
            </div>

            <form id="cryptoWithdrawForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency *</label>
                    <select name="currency_id" id="cryptoCurrency"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($cryptoCurrencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>"
                                data-network="<?= e((string)($cur['network'] ?? '')) ?>"
                                data-fee-fixed="<?= e((string)($cur['withdrawal_fee_fixed']   ?? '0')) ?>"
                                data-fee-pct="<?= e((string)($cur['withdrawal_fee_percent'] ?? '0')) ?>"
                                data-min="<?= e((string)($cur['min_withdrawal'] ?? '0')) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                            <?php if (!empty($cur['network'])): ?>
                            (<?= e((string)$cur['network']) ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($cryptoCurrencies)): ?>
                        <option disabled>No crypto currencies available</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount *</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="cryptoAmount"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00000000" required>
                        <span class="input-group-text border-secondary bg-transparent text-secondary" id="codeLabel">—</span>
                        <button type="button" class="btn btn-outline-secondary" id="btnMax">MAX</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Destination Address *</label>
                    <input type="text" name="destination_address" id="cryptoAddress"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Enter destination wallet address" required>
                    <div class="form-text text-secondary small">Double-check — transactions are irreversible.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small">Tag / Memo <span class="text-secondary">(XRP, XLM, etc.)</span></label>
                    <input type="text" name="destination_tag"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Optional destination tag or memo" maxlength="100">
                </div>
                <div class="alert alert-warning small rounded-3 mb-3">
                    <i class="fas fa-clock me-1"></i>
                    Processing time: 1–24 hours. Large amounts may require manual review.
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-semibold">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="cryptoSpinner"></span>
                    <i class="fas fa-paper-plane me-1"></i>Submit Withdrawal
                </button>
            </form>
        </div>
    </div>

    <!-- Withdrawal History -->
    <div class="col-lg-7">
        <!-- Filters -->
        <div class="glass rounded-4 p-3 mb-3">
            <form class="row g-2 align-items-end" method="get" action="/user/withdrawal">
                <div class="col">
                    <select class="form-select bg-transparent text-light border-secondary" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','processing','approved','completed','rejected','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <select class="form-select bg-transparent text-light border-secondary" name="type">
                        <option value="">All Types</option>
                        <option value="crypto" <?= ($filters['type'] ?? '') === 'crypto' ? 'selected' : '' ?>>Crypto</option>
                        <option value="fiat"   <?= ($filters['type'] ?? '') === 'fiat'   ? 'selected' : '' ?>>Fiat</option>
                    </select>
                </div>
                <div class="col">
                    <input class="form-control bg-transparent text-light border-secondary" type="date" name="date_from"
                           value="<?= e((string)($filters['date_from'] ?? '')) ?>">
                </div>
                <div class="col">
                    <input class="form-control bg-transparent text-light border-secondary" type="date" name="date_to"
                           value="<?= e((string)($filters['date_to'] ?? '')) ?>">
                </div>
                <div class="col-auto d-flex gap-1">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i></button>
                    <a class="btn btn-outline-secondary" href="/user/withdrawal"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>

        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Withdrawal History</h5>
            <div class="table-responsive">
                <table id="withdrawTable" class="table table-user table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Type</th><th>Currency</th><th>Amount</th><th>Fee</th>
                            <th>Destination</th><th>Status</th><th>Date</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($withdrawals as $wd):
                        $statusColor = match($wd['status'] ?? '') {
                            'completed'  => 'success',
                            'rejected', 'cancelled' => 'danger',
                            'processing' => 'info',
                            'approved'   => 'primary',
                            default      => 'warning text-dark',
                        };
                        $isFiatRow = ($wd['currency_type'] ?? '') === 'fiat';
                        $bankDetails = null;
                        if ($isFiatRow && !empty($wd['destination_address'])) {
                            $decoded = json_decode((string)$wd['destination_address'], true);
                            if (is_array($decoded)) $bankDetails = $decoded;
                        }
                    ?>
                    <tr>
                        <td class="small text-secondary"><?= (int)($wd['id'] ?? 0) ?></td>
                        <td>
                            <span class="badge <?= $isFiatRow ? 'bg-info text-dark' : 'bg-secondary' ?>">
                                <?= $isFiatRow ? 'FIAT' : 'CRYPTO' ?>
                            </span>
                        </td>
                        <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                        <td class="font-monospace small"><?= number_format((float)($wd['amount'] ?? 0), 8) ?></td>
                        <td class="font-monospace small text-danger">-<?= number_format((float)($wd['fee'] ?? 0), 8) ?></td>
                        <td class="small text-secondary" style="max-width:120px;overflow:hidden;text-overflow:ellipsis">
                            <?php if ($isFiatRow && $bankDetails !== null): ?>
                                <i class="fas fa-university me-1 text-info"></i>
                                <?= e(substr((string)($bankDetails['bank_name'] ?? 'Bank'), 0, 16)) ?>
                            <?php else: ?>
                                <span class="font-monospace" title="<?= e((string)($wd['destination_address'] ?? '')) ?>">
                                    <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 16)) ?>…
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst((string)($wd['status'] ?? '-'))) ?></span></td>
                        <td class="small text-secondary"><?= e(substr((string)($wd['requested_at'] ?? ''), 0, 16)) ?></td>
                        <td>
                            <?php if (($wd['status'] ?? '') === 'pending'): ?>
                            <button class="btn btn-xs btn-outline-danger cancel-btn" data-id="<?= (int)$wd['id'] ?>">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php elseif (!empty($wd['tx_hash'])): ?>
                                <span class="text-success small font-monospace" title="<?= e((string)$wd['tx_hash']) ?>">
                                    <?= e(substr((string)$wd['tx_hash'], 0, 10)) ?>…
                                </span>
                            <?php elseif (!empty($wd['rejection_reason'])): ?>
                                <span class="text-danger small" title="<?= e((string)$wd['rejection_reason']) ?>">
                                    <i class="fas fa-exclamation-circle"></i>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($withdrawals === []): ?>
                    <tr><td colspan="9" class="text-center text-secondary py-4">No withdrawals yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const _csrf = '<?= e(\App\Libraries\Csrf::token()) ?>';

$('#withdrawTable').DataTable({ order:[[0,'desc']], pageLength:15 });

// Load currency info on change
document.getElementById('cryptoCurrency').addEventListener('change', async function() {
    const opt  = this.options[this.selectedIndex];
    const code = opt.dataset.code || '—';
    document.getElementById('codeLabel').textContent = code;

    if (!opt.value) {
        document.getElementById('feeInfoBox').classList.add('d-none');
        return;
    }

    try {
        const res  = await fetch('/user/withdrawal/limit-info?currency_id=' + opt.value);
        const json = await res.json();
        if (json.ok) {
            const d = json.data;
            document.getElementById('feeInfoBalance').textContent   = '— ' + code;
            document.getElementById('feeInfoRemaining').textContent = d.remaining !== null
                ? parseFloat(d.remaining).toFixed(8) + ' ' + code
                : 'No limit';
            document.getElementById('feeInfoMin').textContent       = parseFloat(d.min_amount).toFixed(8) + ' ' + code;
            const fixedFee = parseFloat(d.fee_fixed || 0);
            const pctFee   = parseFloat(d.fee_percent || 0);
            document.getElementById('feeInfoFee').textContent =
                fixedFee.toFixed(8) + (pctFee > 0 ? ' + ' + pctFee + '%' : '') + ' ' + code;
            document.getElementById('feeInfoBox').classList.remove('d-none');
        }
    } catch(e) {}
});

// Fee preview on amount change
let feeTimer = null;
document.getElementById('cryptoAmount').addEventListener('input', function() {
    clearTimeout(feeTimer);
    const cid = document.getElementById('cryptoCurrency').value;
    if (!cid || !this.value) { document.getElementById('feeInfoNet').textContent = '—'; return; }
    feeTimer = setTimeout(async () => {
        try {
            const res  = await fetch('/user/withdrawal/fee-preview?currency_id=' + cid + '&amount=' + this.value);
            const json = await res.json();
            if (json.ok) {
                const code = document.getElementById('codeLabel').textContent;
                document.getElementById('feeInfoFee').textContent = parseFloat(json.data.fee).toFixed(8) + ' ' + code;
                document.getElementById('feeInfoNet').textContent = parseFloat(json.data.net).toFixed(8) + ' ' + code;
            }
        } catch(e) {}
    }, 500);
});

// Submit crypto withdrawal
document.getElementById('cryptoWithdrawForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('cryptoSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/user/withdrawal/submit-crypto', {
            method:'POST', headers:{'Content-Type':'application/json', 'X-CSRF-Token':_csrf},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Submitted!', text: json.message, confirmButtonColor:'#3b82f6' })
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
document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            icon:'warning', title:'Cancel Withdrawal?',
            text:'The amount will be refunded to your wallet.',
            showCancelButton:true, confirmButtonText:'Yes, Cancel',
            confirmButtonColor:'#ef4444'
        });
        if (!confirmed.isConfirmed) return;
        const res  = await fetch('/user/withdrawal/cancel', {
            method:'POST', headers:{'Content-Type':'application/json'},
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
