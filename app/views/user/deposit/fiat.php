<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$deposits   = is_array($deposits   ?? null) ? $deposits   : [];
$stats      = is_array($stats      ?? null) ? $stats      : [];
$paymentRef = isset($paymentRef) ? e((string)$paymentRef) : 'DEP-' . e((string)\App\Libraries\Session::get('auth.user_id'));
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="/user/deposit" class="text-secondary text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Back to Deposits
        </a>
        <h1 class="h4 fw-bold mb-0 mt-1"><i class="fas fa-university me-2 text-info"></i>Fiat / Bank Transfer Deposit</h1>
    </div>
</div>

<div class="row g-4">
    <!-- Fiat deposit form -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3 fw-bold"><i class="fas fa-landmark me-2 text-info"></i>Submit Bank Transfer</h5>

            <div class="alert alert-info small rounded-3 mb-4">
                <i class="fas fa-info-circle me-1"></i>
                To make a fiat deposit, transfer funds to our bank account below and submit this form with your payment reference.
                Our team will verify and credit your account within 1–3 business days.
            </div>

            <!-- Bank details card -->
            <div class="glass rounded-3 p-3 mb-4 border border-secondary">
                <h6 class="mb-2 text-info"><i class="fas fa-building-columns me-1"></i>Our Bank Details</h6>
                <div class="row g-2 small">
                    <div class="col-5 text-secondary">Bank Name:</div>
                    <div class="col-7 fw-semibold">Platform Finance Bank</div>
                    <div class="col-5 text-secondary">Account Name:</div>
                    <div class="col-7 fw-semibold">Trading Platform Ltd.</div>
                    <div class="col-5 text-secondary">Account No:</div>
                    <div class="col-7 font-monospace fw-semibold">0123456789</div>
                    <div class="col-5 text-secondary">Sort Code:</div>
                    <div class="col-7 font-monospace fw-semibold">12-34-56</div>
                    <div class="col-5 text-secondary">IBAN:</div>
                    <div class="col-7 font-monospace fw-semibold">GB00XXXX12345601234567</div>
                    <div class="col-5 text-secondary">SWIFT/BIC:</div>
                    <div class="col-7 font-monospace fw-semibold">XXXX GB 2L</div>
                    <div class="col-5 text-secondary">Reference:</div>
                    <div class="col-7 text-warning font-monospace fw-bold" id="yourRef">
                        <?= $paymentRef ?>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Always use your unique reference number shown above when making the transfer.
                    </small>
                </div>
            </div>

            <form id="fiatDepositForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

                <div class="mb-3">
                    <label class="form-label text-secondary small">Fiat Currency *</label>
                    <select name="currency_id" class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Fiat Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount Transferred *</label>
                    <input type="number" name="amount"
                           class="form-control bg-transparent text-light border-secondary"
                           step="0.01" min="1" placeholder="0.00" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Payment Reference *</label>
                    <input type="text" name="payment_ref"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Your unique payment reference" maxlength="191" required>
                    <div class="form-text text-secondary">Enter the reference you used in your bank transfer.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Your Bank Name</label>
                    <input type="text" name="bank_name"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Name of your bank" maxlength="100">
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small">Your Account Name</label>
                    <input type="text" name="account_name"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Name on your bank account" maxlength="100">
                </div>

                <button type="submit" class="btn btn-info w-100 fw-semibold" id="fiatDepositBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="fiatDepositSpinner"></span>
                    <i class="fas fa-paper-plane me-1"></i>Submit Fiat Deposit
                </button>
            </form>
        </div>
    </div>

    <!-- Recent fiat deposits -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3 fw-bold"><i class="fas fa-history me-2"></i>Recent Fiat Deposits</h5>
            <div class="table-responsive">
                <table class="table table-user table-sm">
                    <thead>
                        <tr><th>Currency</th><th>Amount</th><th>Ref</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    // Show fiat deposits (those with JSON in from_address)
                    $fiatDeposits = array_filter($deposits, static function ($d) {
                        if (empty($d['from_address'])) return false;
                        $meta = json_decode((string)$d['from_address'], true);
                        return is_array($meta) && isset($meta['payment_ref']);
                    });
                    foreach ($fiatDeposits as $dep):
                        $meta = json_decode((string)$dep['from_address'], true);
                        $ds   = (string)($dep['status'] ?? 'pending');
                        $dc   = match($ds) {
                            'credited' => 'success', 'confirmed' => 'info',
                            'failed'   => 'danger',  'flagged'   => 'warning',
                            default    => 'secondary',
                        };
                    ?>
                    <tr>
                        <td><span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '')) ?></span></td>
                        <td class="font-monospace small"><?= number_format((float)($dep['amount'] ?? 0), 2) ?></td>
                        <td class="small text-secondary"><?= e((string)($meta['payment_ref'] ?? '—')) ?></td>
                        <td><span class="badge bg-<?= $dc ?>"><?= ucfirst($ds) ?></span></td>
                        <td class="small text-secondary"><?= e(substr((string)($dep['created_at'] ?? ''), 0, 10)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fiatDeposits)): ?>
                    <tr><td colspan="5" class="text-center text-secondary py-4">No fiat deposits yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('fiatDepositForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn  = document.getElementById('fiatDepositBtn');
        const spin = document.getElementById('fiatDepositSpinner');
        btn.disabled = true; spin.classList.remove('d-none');

        const fd   = new FormData(this);
        const data = Object.fromEntries(fd.entries());

        fetch('/user/deposit/submit-fiat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({
                icon: res.ok ? 'success' : 'error',
                title: res.ok ? 'Fiat Deposit Submitted' : 'Error',
                text: res.message,
                timer: res.ok ? 4000 : undefined,
                showConfirmButton: !res.ok,
            }).then(() => { if (res.ok) location.reload(); });
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }))
        .finally(() => { btn.disabled = false; spin.classList.add('d-none'); });
    });
})();
</script>
