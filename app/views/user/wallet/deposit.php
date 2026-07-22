<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$deposits   = is_array($deposits   ?? null) ? $deposits   : [];
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Deposit Form -->
    <div class="col-lg-5">
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
