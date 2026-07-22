<?php declare(strict_types=1); ?>
<?php
$withdrawal  = is_array($withdrawal  ?? null) ? $withdrawal  : [];
$bankDetails = is_array($bankDetails ?? null) ? $bankDetails : null;
$csrf        = \App\Libraries\Csrf::token();
$id          = (int)($withdrawal['id'] ?? 0);
$statusColor = match($withdrawal['status'] ?? '') {
    'completed'  => 'success',
    'rejected', 'cancelled' => 'danger',
    'processing' => 'info',
    'approved'   => 'primary',
    default      => 'warning',
};
$isFiat = ($withdrawal['currency_type'] ?? '') === 'fiat';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-arrow-circle-up me-2 text-warning"></i>Withdrawal #<?= $id ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/admin/withdrawals" class="text-info text-decoration-none">Withdrawals</a></li>
                <li class="breadcrumb-item active text-secondary">Detail #<?= $id ?></li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-<?= $statusColor ?> fs-6"><?= e(ucfirst((string)($withdrawal['status'] ?? '-'))) ?></span>
        <?php if (!in_array($withdrawal['status'] ?? '', ['completed','rejected','cancelled'], true)): ?>
        <button class="btn btn-primary btn-sm" id="btnReview">
            <i class="fas fa-edit me-1"></i>Update Status
        </button>
        <?php endif; ?>
        <a href="/admin/withdrawals" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-4">
    <!-- Left: Withdrawal Info -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Withdrawal Information</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-secondary">Withdrawal ID</div>
                    <div class="fw-semibold">#<?= $id ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Type</div>
                    <div>
                        <span class="badge <?= $isFiat ? 'bg-info' : 'bg-warning text-dark' ?>">
                            <?= $isFiat ? 'FIAT / BANK' : 'CRYPTO' ?>
                        </span>
                        <?php if (!empty($withdrawal['network'])): ?>
                        <span class="badge bg-secondary ms-1"><?= e((string)$withdrawal['network']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Currency</div>
                    <div class="fw-semibold">
                        <span class="badge bg-warning text-dark"><?= e((string)($withdrawal['currency_code'] ?? '-')) ?></span>
                        <?= e((string)($withdrawal['currency_name'] ?? '')) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Manual Review</div>
                    <div>
                        <?php if ((int)($withdrawal['requires_manual_review'] ?? 0)): ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-eye me-1"></i>Required</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Auto</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Gross Amount</div>
                    <div class="fw-semibold font-monospace h5 mb-0">
                        <?= number_format((float)bcsub(
                            bcadd((string)($withdrawal['amount'] ?? '0'), (string)($withdrawal['fee'] ?? '0'), 18),
                            '0', 8
                        ), 8) ?>
                        <span class="text-secondary small"><?= e((string)($withdrawal['currency_code'] ?? '')) ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Fee</div>
                    <div class="text-danger font-monospace">
                        -<?= number_format((float)($withdrawal['fee'] ?? 0), 8) ?>
                        <span class="text-secondary small"><?= e((string)($withdrawal['currency_code'] ?? '')) ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Net Amount</div>
                    <div class="fw-semibold text-success font-monospace">
                        <?= number_format((float)($withdrawal['amount'] ?? 0), 8) ?>
                        <span class="text-secondary small"><?= e((string)($withdrawal['currency_code'] ?? '')) ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-secondary">Status</div>
                    <div><span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst((string)($withdrawal['status'] ?? '-'))) ?></span></div>
                </div>
                <div class="col-md-6">
                    <div class="small text-secondary">Requested At</div>
                    <div><?= e((string)($withdrawal['requested_at'] ?? '-')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="small text-secondary">Processed At</div>
                    <div><?= e((string)($withdrawal['processed_at'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($withdrawal['rejection_reason'])): ?>
                <div class="col-12">
                    <div class="alert alert-danger small mb-0">
                        <i class="fas fa-times-circle me-1"></i>
                        <strong>Rejection Reason:</strong> <?= e((string)$withdrawal['rejection_reason']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Destination -->
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3">
                <i class="fas fa-<?= $isFiat ? 'university' : 'wallet' ?> me-2 text-info"></i>
                <?= $isFiat ? 'Bank Details' : 'Destination Address' ?>
            </h5>
            <?php if ($isFiat && $bankDetails !== null): ?>
            <div class="row g-3">
                <?php
                $bankFields = [
                    'bank_name'      => 'Bank Name',
                    'account_name'   => 'Account Name',
                    'account_number' => 'Account Number',
                    'iban'           => 'IBAN',
                    'swift_bic'      => 'SWIFT / BIC',
                    'currency'       => 'Currency',
                ];
                foreach ($bankFields as $key => $label):
                    if (empty($bankDetails[$key])) continue;
                ?>
                <div class="col-md-4">
                    <div class="small text-secondary"><?= e($label) ?></div>
                    <div class="fw-semibold"><?= e((string)$bankDetails[$key]) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="mb-3">
                <div class="small text-secondary mb-1">Destination Address</div>
                <div class="font-monospace p-2 bg-dark rounded border border-secondary">
                    <?= e((string)($withdrawal['destination_address'] ?? '—')) ?>
                </div>
            </div>
            <?php if (!empty($withdrawal['destination_tag'])): ?>
            <div>
                <div class="small text-secondary mb-1">Tag / Memo</div>
                <div class="font-monospace p-2 bg-dark rounded border border-secondary">
                    <?= e((string)$withdrawal['destination_tag']) ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($withdrawal['tx_hash'])): ?>
            <div class="mt-3">
                <div class="small text-secondary mb-1">Transaction Hash</div>
                <div class="font-monospace p-2 bg-dark rounded border border-success d-flex justify-content-between align-items-center">
                    <span><?= e((string)$withdrawal['tx_hash']) ?></span>
                    <button class="btn btn-xs btn-outline-secondary ms-2"
                            onclick="navigator.clipboard.writeText('<?= e((string)$withdrawal['tx_hash']) ?>')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: User Info + Review -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3"><i class="fas fa-user me-2 text-info"></i>User Information</h5>
            <div class="mb-2">
                <div class="small text-secondary">Username</div>
                <a href="/admin/users/detail?id=<?= (int)($withdrawal['user_id'] ?? 0) ?>" class="text-info fw-semibold">
                    <?= e((string)($withdrawal['username'] ?? '-')) ?>
                </a>
            </div>
            <div class="mb-2">
                <div class="small text-secondary">Email</div>
                <div><?= e((string)($withdrawal['email'] ?? '-')) ?></div>
            </div>
            <div class="mb-2">
                <div class="small text-secondary">Wallet ID</div>
                <div class="font-monospace small">#<?= (int)($withdrawal['wallet_id'] ?? 0) ?></div>
            </div>
        </div>

        <?php if (!empty($withdrawal['reviewed_by_username'])): ?>
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3"><i class="fas fa-user-check me-2 text-success"></i>Review Information</h5>
            <div class="mb-2">
                <div class="small text-secondary">Reviewed By</div>
                <div class="fw-semibold"><?= e((string)$withdrawal['reviewed_by_username']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!in_array($withdrawal['status'] ?? '', ['completed','rejected','cancelled'], true)): ?>
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-edit me-2 text-warning"></i>Update Status</h5>
            <form id="reviewFormInline">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="withdrawal_id" value="<?= $id ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">New Status *</label>
                    <select name="status" id="inlineStatus"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="pending"    <?= ($withdrawal['status'] ?? '') === 'pending'    ? 'selected':'' ?>>Pending</option>
                        <option value="approved"   <?= ($withdrawal['status'] ?? '') === 'approved'   ? 'selected':'' ?>>Approved</option>
                        <option value="processing" <?= ($withdrawal['status'] ?? '') === 'processing' ? 'selected':'' ?>>Processing</option>
                        <option value="completed"  <?= ($withdrawal['status'] ?? '') === 'completed'  ? 'selected':'' ?>>Completed</option>
                        <option value="rejected">Rejected (Refunds Balance)</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="mb-3" id="inlineTxField" style="display:none">
                    <label class="form-label text-secondary small">Transaction Hash</label>
                    <input type="text" name="tx_hash"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           value="<?= e((string)($withdrawal['tx_hash'] ?? '')) ?>"
                           placeholder="Blockchain transaction hash">
                </div>
                <div class="mb-3" id="inlineRejectField" style="display:none">
                    <label class="form-label text-secondary small">Rejection Reason *</label>
                    <input type="text" name="rejection_reason"
                           class="form-control bg-transparent text-light border-secondary"
                           placeholder="Reason for rejection">
                </div>
                <div class="alert alert-warning small mb-3" id="inlineRefundNotice" style="display:none">
                    <i class="fas fa-undo me-1"></i>
                    Rejecting will <strong>refund</strong> the full amount to the user's balance.
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="inlineSpinner"></span>
                    Apply Update
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const _csrf = '<?= e($csrf) ?>';

document.getElementById('inlineStatus')?.addEventListener('change', function() {
    document.getElementById('inlineTxField').style.display    = ['processing','completed'].includes(this.value) ? '' : 'none';
    document.getElementById('inlineRejectField').style.display = this.value === 'rejected' ? '' : 'none';
    document.getElementById('inlineRefundNotice').style.display = this.value === 'rejected' ? '' : 'none';
});

document.getElementById('reviewFormInline')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('inlineSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/admin/withdrawals/review', {
            method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Updated!', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } finally {
        spinner.classList.add('d-none');
    }
});
</script>
