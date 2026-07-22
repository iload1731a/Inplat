<?php declare(strict_types=1); ?>
<?php
$history = is_array($history ?? null) ? $history : [];
$csrf    = \App\Libraries\Csrf::token();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-sliders-h me-2 text-purple"></i>Manual Balance Adjustment</h1>
        <p class="text-secondary mb-0">Credit or debit user wallet balances with full audit trail. All adjustments are logged.</p>
    </div>
    <a href="/admin/wallets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Wallets</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-4">
    <!-- Adjustment Form -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-4"><i class="fas fa-plus-minus me-2"></i>New Adjustment</h5>
            <form id="adjustmentForm">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Wallet ID *</label>
                    <div class="input-group">
                        <input type="number" name="wallet_id" id="walletIdInput"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="Enter wallet ID" min="1" required>
                        <button type="button" class="btn btn-outline-secondary" id="lookupWalletBtn"
                                title="Lookup wallet">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="walletLookupResult" class="mt-2 small text-secondary"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Direction *</label>
                    <div class="d-flex gap-2">
                        <label class="flex-fill">
                            <input type="radio" name="direction" value="credit" class="btn-check" id="dirCredit" checked>
                            <label for="dirCredit" class="btn btn-outline-success w-100">
                                <i class="fas fa-plus me-1"></i>Credit
                            </label>
                        </label>
                        <label class="flex-fill">
                            <input type="radio" name="direction" value="debit" class="btn-check" id="dirDebit">
                            <label for="dirDebit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-minus me-1"></i>Debit
                            </label>
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount *</label>
                    <input type="number" name="amount"
                           class="form-control bg-transparent text-light border-secondary"
                           step="0.00000001" min="0.00000001" placeholder="0.00000000" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small">Notes / Reason *</label>
                    <textarea name="notes" class="form-control bg-transparent text-light border-secondary"
                              rows="3" placeholder="Required: reason for this adjustment" required></textarea>
                    <div class="form-text text-secondary">This note will appear in the ledger and audit log.</div>
                </div>
                <div class="alert alert-danger small rounded-3 mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>Caution:</strong> Balance adjustments are immediate and recorded in the immutable ledger.
                    Double-check before submitting.
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="adjustSpinner"></span>
                    <i class="fas fa-check me-1"></i>Apply Adjustment
                </button>
            </form>
        </div>
    </div>

    <!-- Adjustment History -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Adjustment History</h5>
            <div class="table-responsive">
                <table id="adjustmentTable" class="table table-dark table-hover align-middle mb-0 table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>User</th><th>Currency</th><th>Wallet</th>
                            <th>Direction</th><th>Amount</th><th>Balance After</th>
                            <th>Notes</th><th>Admin</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="small text-secondary"><?= (int)$h['id'] ?></td>
                            <td class="small fw-semibold"><?= e((string)($h['username'] ?? '-')) ?></td>
                            <td><span class="badge bg-secondary"><?= e((string)($h['currency_code'] ?? '-')) ?></span></td>
                            <td class="small text-secondary">#<?= (int)$h['wallet_id'] ?></td>
                            <td>
                                <span class="badge bg-<?= ($h['direction'] ?? '') === 'credit' ? 'success' : 'danger' ?>">
                                    <i class="fas fa-arrow-<?= ($h['direction'] ?? '') === 'credit' ? 'down' : 'up' ?> me-1"></i>
                                    <?= ucfirst((string)($h['direction'] ?? '-')) ?>
                                </span>
                            </td>
                            <td class="font-monospace small fw-semibold"><?= number_format((float)($h['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($h['balance_after'] ?? 0), 8) ?></td>
                            <td class="small text-secondary" style="max-width:150px;overflow:hidden;text-overflow:ellipsis">
                                <?= e((string)($h['notes'] ?? '—')) ?>
                            </td>
                            <td class="small text-info"><?= e((string)($h['admin_username'] ?? '-')) ?></td>
                            <td class="small text-secondary"><?= e(substr((string)($h['created_at'] ?? ''), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($history === []): ?>
                        <tr><td colspan="10" class="text-center text-secondary py-4">No manual adjustments yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#adjustmentTable').DataTable({ order:[[0,'desc']], pageLength:20 });

document.getElementById('adjustmentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('adjustSpinner');

    const data = Object.fromEntries(new FormData(this));
    const dirLabel = data.direction === 'credit' ? '+ Credit' : '- Debit';
    const safeNotes = document.createTextNode(data.notes || '').textContent;

    const confirmed = await Swal.fire({
        icon: 'warning',
        title: 'Confirm Adjustment',
        html: `Apply <strong>${dirLabel} ${data.amount}</strong> to wallet <strong>#${data.wallet_id}</strong>?<br>
               <small class="text-secondary">Notes: ${safeNotes}</small>`,
        showCancelButton: true,
        confirmButtonText: 'Yes, Apply',
        confirmButtonColor: '#3b82f6',
    });
    if (!confirmed.isConfirmed) return;

    spinner.classList.remove('d-none');
    try {
        const res  = await fetch('/admin/wallets/adjustment', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Applied!', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } catch(err) {
        Swal.fire({ icon:'error', title:'Error', text: 'Network error.' });
    } finally {
        spinner.classList.add('d-none');
    }
});
</script>
