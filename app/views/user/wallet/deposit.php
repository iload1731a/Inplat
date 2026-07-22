<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$deposits   = is_array($deposits   ?? null) ? $deposits   : [];
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="fas fa-arrow-down-to-bracket me-2 text-success"></i>Deposit Funds</h1>
</div>

<div class="row g-4">
    <!-- Deposit Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3">Submit Deposit Request</h5>
            <form id="depositForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency *</label>
                    <select name="currency_id" id="depositCurrency"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount *</label>
                    <div class="input-group">
                        <input type="number" name="amount"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00000000" required>
                        <span class="input-group-text border-secondary text-secondary bg-transparent" id="amtCurrency">—</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Transaction Hash / TxID <span class="text-secondary">(optional)</span></label>
                    <input type="text" name="tx_hash"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Blockchain transaction hash" maxlength="191">
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small">Sender Address <span class="text-secondary">(optional)</span></label>
                    <input type="text" name="from_address"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Address you are sending from" maxlength="191">
                </div>
                <div class="alert alert-info small rounded-3">
                    <i class="fas fa-info-circle me-1"></i>
                    After submitting, our team will review and credit your wallet within 1–24 hours.
                </div>
                <button type="submit" class="btn btn-success w-100 fw-semibold" id="depositBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="depositSpinner"></span>
                    <i class="fas fa-paper-plane me-1"></i>Submit Deposit Request
                </button>
            </form>
        </div>
    </div>

    <!-- Deposit History -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Deposit History</h5>
            <div class="table-responsive">
                <table id="depositTable" class="table table-user table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Currency</th><th>Amount</th><th>TxHash</th>
                            <th>Confirmations</th><th>Status</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deposits as $dep): ?>
                        <tr>
                            <td class="small text-secondary"><?= (int)($dep['id'] ?? 0) ?></td>
                            <td><span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary">
                                <?php if (!empty($dep['tx_hash'])): ?>
                                    <span title="<?= e((string)$dep['tx_hash']) ?>"><?= e(substr((string)$dep['tx_hash'], 0, 16)) ?>…</span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="small text-center">
                                <span class="badge bg-secondary"><?= (int)($dep['confirmations'] ?? 0) ?></span>
                            </td>
                            <td>
                                <?php
                                $ds = (string)($dep['status'] ?? 'pending');
                                $dc = match($ds) {
                                    'credited'  => 'success',
                                    'confirmed' => 'info',
                                    'failed'    => 'danger',
                                    'flagged'   => 'warning text-dark',
                                    default     => 'secondary',
                                };
                                ?>
                                <span class="badge bg-<?= $dc ?>"><?= e($ds) ?></span>
                                <?php if (!empty($dep['flagged_reason'])): ?>
                                    <div class="small text-warning mt-1"><?= e((string)$dep['flagged_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?= e(substr((string)($dep['created_at'] ?? ''), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($deposits === []): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-4">No deposits yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const _csrf = '<?= e(\App\Libraries\Csrf::token()) ?>';

$('#depositTable').DataTable({ order: [[0,'desc']], pageLength: 15 });

document.getElementById('depositCurrency').addEventListener('change', function() {
    const code = this.options[this.selectedIndex].dataset.code || '—';
    document.getElementById('amtCurrency').textContent = code;
});

document.getElementById('depositForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('depositSpinner');
    const btn     = document.getElementById('depositBtn');
    spinner.classList.remove('d-none');
    btn.disabled = true;

    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/user/wallet/deposit', {
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
        btn.disabled = false;
    }
});
</script>
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-arrow-down-to-bracket me-2 text-success"></i>New Deposit</h5>
            <form id="depositForm" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency</label>
                    <select name="currency_id" class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"><?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount</label>
                    <div class="input-group">
                        <input type="number" name="amount" class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00" required>
                        <span class="input-group-text border-secondary text-secondary bg-transparent" id="amtCurrency"></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Deposit Method</label>
                    <select name="method" class="form-select bg-transparent text-light border-secondary">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="crypto">Crypto</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Reference / TxID <span class="text-secondary">(optional)</span></label>
                    <input type="text" name="reference" class="form-control bg-transparent text-light border-secondary"
                           placeholder="Transaction hash or reference number">
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Payment Proof <span class="text-secondary">(optional)</span></label>
                    <input type="file" name="payment_proof" class="form-control bg-transparent text-light border-secondary"
                           accept=".jpg,.jpeg,.png,.pdf,.gif,.webp">
                    <div class="form-text text-secondary">Screenshot or receipt. Max 5MB.</div>
                </div>
                <button type="submit" class="btn btn-success w-100" id="depositBtn">
                    <i class="fas fa-paper-plane me-1"></i>Submit Deposit Request
                </button>
            </form>
        </div>
    </div>

    <!-- Deposit History -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Deposit History</h5>
            <div class="table-responsive">
                <table id="depositTable" class="table table-user table-sm">
                    <thead><tr><th>#</th><th>Currency</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($deposits as $dep): ?>
                        <tr>
                            <td class="small"><?= (int)($dep['id'] ?? 0) ?></td>
                            <td><span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($dep['net_amount'] ?? 0), 8) ?></td>
                            <td class="small text-secondary"><?= e((string)($dep['method'] ?? '-')) ?></td>
                            <td>
                                <?php
                                $ds = (string)($dep['status'] ?? 'pending');
                                $dc = match($ds) { 'completed' => 'success', 'failed', 'cancelled' => 'danger', 'confirming' => 'info', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $dc ?>"><?= e($ds) ?></span>
                            </td>
                            <td class="small"><?= e(date('M d, Y H:i', strtotime((string)($dep['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#depositTable').DataTable({ order: [[0,'desc']], pageLength: 15 });

$('select[name=currency_id]').on('change', function () {
    const text = $(this).find(':selected').text().split(' — ')[0];
    $('#amtCurrency').text(text);
});

document.getElementById('depositForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const btn = document.getElementById('depositBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';

    $.ajax({
        url: '/user/wallet/deposit',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success(r) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Deposit Request';
            if (r.ok) {
                Swal.fire({ icon: 'success', title: 'Submitted!', text: r.message, timer: 2500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message });
            }
        },
        error(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Deposit Request';
            const p = xhr.responseJSON || {};
            Swal.fire({ icon: 'error', title: 'Error', text: p.message || 'Request failed' });
        }
    });
});
</script>
