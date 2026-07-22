<?php declare(strict_types=1); ?>
<?php
$currencies  = is_array($currencies  ?? null) ? $currencies  : [];
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
$stats       = is_array($stats       ?? null) ? $stats       : [];
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0">
        <i class="fas fa-university me-2 text-info"></i>Bank / Fiat Withdrawal
    </h1>
    <div class="d-flex gap-2">
        <a href="/user/withdrawal" class="btn btn-outline-secondary btn-sm"><i class="fas fa-coins me-1"></i>Crypto Withdrawal</a>
        <a href="/user/wallet/addresses" class="btn btn-outline-secondary btn-sm"><i class="fas fa-shield-alt me-1"></i>Whitelist</a>
    </div>
</div>

<div class="row g-4">
    <!-- Fiat Withdrawal Form -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-1"><i class="fas fa-paper-plane me-2 text-info"></i>New Bank Transfer Request</h5>
            <p class="text-secondary small mb-4">
                Bank transfers are processed manually within 1–3 business days. You will receive a notification when your transfer is processed.
            </p>

            <?php if (empty($currencies)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No fiat currencies are available for withdrawal at this time.
            </div>
            <?php else: ?>

            <div class="alert alert-secondary small mb-3 d-none" id="fiatFeeBox">
                <div class="row g-1">
                    <div class="col-6">Fee: <strong id="fiatFeeAmount">—</strong></div>
                    <div class="col-6">Net Amount: <strong class="text-success" id="fiatNetAmount">—</strong></div>
                    <div class="col-6">Min Amount: <strong id="fiatMinAmount">—</strong></div>
                    <div class="col-6">Daily Remaining: <strong id="fiatRemaining">—</strong></div>
                </div>
            </div>

            <form id="fiatWithdrawForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency *</label>
                    <select name="currency_id" id="fiatCurrency"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Fiat Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>"
                                data-fee-fixed="<?= e((string)($cur['withdrawal_fee_fixed']   ?? '0')) ?>"
                                data-fee-pct="<?= e((string)($cur['withdrawal_fee_percent'] ?? '0')) ?>"
                                data-min="<?= e((string)($cur['min_withdrawal'] ?? '0')) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount *</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="fiatAmount"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.01" min="0.01" placeholder="0.00" required>
                        <span class="input-group-text border-secondary bg-transparent text-secondary" id="fiatCodeLabel">—</span>
                    </div>
                </div>

                <hr class="border-secondary my-3">
                <h6 class="text-secondary mb-3"><i class="fas fa-university me-1"></i>Bank Account Details</h6>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Account Holder Name *</label>
                    <input type="text" name="account_name"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Full name as on bank account" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Bank Name *</label>
                    <input type="text" name="bank_name"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Name of your bank" required maxlength="100">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary small">Account Number</label>
                        <input type="text" name="account_number"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="Bank account number" maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small">IBAN</label>
                        <input type="text" name="iban"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="International Bank Account Number" maxlength="50">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small">SWIFT / BIC Code</label>
                    <input type="text" name="swift"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Bank SWIFT or BIC code" maxlength="20">
                </div>

                <div class="alert alert-info small rounded-3 mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    All fiat withdrawals require manual verification. Processing time: 1–3 business days.
                    Ensure bank details match your KYC documents.
                </div>

                <button type="submit" class="btn btn-info w-100 fw-semibold text-dark">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="fiatSpinner"></span>
                    <i class="fas fa-paper-plane me-1"></i>Submit Bank Transfer Request
                </button>
            </form>

            <?php endif; ?>
        </div>
    </div>

    <!-- Right: History + Info -->
    <div class="col-lg-6">
        <!-- Info card -->
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Important Information</h5>
            <ul class="list-unstyled small text-secondary mb-0">
                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Provide accurate bank details — incorrect information may result in delays or loss of funds.</li>
                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Your bank account name must match your KYC-verified identity.</li>
                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>All fiat withdrawals are subject to compliance review.</li>
                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>International transfers may take up to 5 business days.</li>
                <li class="mb-2"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Cancellation is only possible while the request is in <em>Pending</em> status.</li>
            </ul>
        </div>

        <!-- Recent fiat withdrawals -->
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Bank Transfer History</h5>
            <?php
            $fiatWds = array_filter($withdrawals ?? [], fn($w) => ($w['currency_type'] ?? '') === 'fiat');
            $fiatWds = array_values($fiatWds);
            ?>
            <?php if (empty($fiatWds)): ?>
                <p class="text-secondary small text-center py-3">No bank transfers yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-user table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Currency</th><th>Amount</th><th>Bank</th><th>Status</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($fiatWds, 0, 20) as $fw):
                        $fwStatusColor = match($fw['status'] ?? '') {
                            'completed'  => 'success',
                            'rejected', 'cancelled' => 'danger',
                            'processing' => 'info',
                            'approved'   => 'primary',
                            default      => 'warning text-dark',
                        };
                        $bankDet = json_decode((string)($fw['destination_address'] ?? '{}'), true) ?: [];
                    ?>
                    <tr>
                        <td class="small text-secondary"><?= (int)$fw['id'] ?></td>
                        <td><span class="badge bg-info text-dark"><?= e((string)($fw['currency_code'] ?? '-')) ?></span></td>
                        <td class="font-monospace small"><?= number_format((float)($fw['amount'] ?? 0), 4) ?></td>
                        <td class="small text-secondary">
                            <?= e(substr((string)($bankDet['bank_name'] ?? '—'), 0, 18)) ?>
                        </td>
                        <td><span class="badge bg-<?= $fwStatusColor ?>"><?= e(ucfirst((string)($fw['status'] ?? '-'))) ?></span></td>
                        <td class="small text-secondary"><?= e(substr((string)($fw['requested_at'] ?? ''), 0, 10)) ?></td>
                        <td>
                            <?php if (($fw['status'] ?? '') === 'pending'): ?>
                            <button class="btn btn-xs btn-outline-danger cancel-btn" data-id="<?= (int)$fw['id'] ?>">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="/user/withdrawal" class="btn btn-outline-secondary btn-sm mt-3 w-100">View All Withdrawals</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const _csrf = '<?= e(\App\Libraries\Csrf::token()) ?>';

document.getElementById('fiatCurrency')?.addEventListener('change', async function() {
    const opt  = this.options[this.selectedIndex];
    const code = opt.dataset.code || '—';
    document.getElementById('fiatCodeLabel').textContent = code;

    if (!opt.value) {
        document.getElementById('fiatFeeBox').classList.add('d-none');
        return;
    }

    try {
        const res  = await fetch('/user/withdrawal/limit-info?currency_id=' + opt.value);
        const json = await res.json();
        if (json.ok) {
            const d = json.data;
            document.getElementById('fiatFeeAmount').textContent  =
                parseFloat(d.fee_fixed || 0).toFixed(4) + (parseFloat(d.fee_percent || 0) > 0
                    ? ' + ' + d.fee_percent + '%' : '') + ' ' + code;
            document.getElementById('fiatMinAmount').textContent  = parseFloat(d.min_amount).toFixed(4) + ' ' + code;
            document.getElementById('fiatRemaining').textContent  = d.remaining !== null
                ? parseFloat(d.remaining).toFixed(4) + ' ' + code : 'No limit';
            document.getElementById('fiatFeeBox').classList.remove('d-none');
        }
    } catch(e) {}
});

// Fee preview on amount change
let fiatFeeTimer = null;
document.getElementById('fiatAmount')?.addEventListener('input', function() {
    clearTimeout(fiatFeeTimer);
    const cid = document.getElementById('fiatCurrency').value;
    if (!cid || !this.value) { document.getElementById('fiatNetAmount').textContent = '—'; return; }
    fiatFeeTimer = setTimeout(async () => {
        try {
            const res  = await fetch('/user/withdrawal/fee-preview?currency_id=' + cid + '&amount=' + this.value);
            const json = await res.json();
            if (json.ok) {
                const code = document.getElementById('fiatCodeLabel').textContent;
                document.getElementById('fiatFeeAmount').textContent = parseFloat(json.data.fee).toFixed(4) + ' ' + code;
                document.getElementById('fiatNetAmount').textContent = parseFloat(json.data.net).toFixed(4) + ' ' + code;
            }
        } catch(e) {}
    }, 500);
});

document.getElementById('fiatWithdrawForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('fiatSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/user/withdrawal/submit-fiat', {
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

document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            icon:'warning', title:'Cancel Bank Transfer?',
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
