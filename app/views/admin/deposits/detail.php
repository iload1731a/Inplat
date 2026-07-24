<?php declare(strict_types=1); ?>
<?php
$deposit = is_array($deposit ?? null) ? $deposit : [];
$csrf    = \App\Libraries\Csrf::token();

$statusColor = match($deposit['status'] ?? '') {
    'credited'  => 'success',
    'confirmed' => 'info',
    'failed'    => 'danger',
    'flagged'   => 'warning',
    default     => 'secondary',
};

// Detect fiat (from_address is JSON)
$fiatMeta = null;
if (!empty($deposit['from_address'])) {
    $decoded = json_decode((string)$deposit['from_address'], true);
    if (is_array($decoded) && isset($decoded['payment_ref'])) {
        $fiatMeta = $decoded;
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/deposits" class="text-secondary text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Back to Deposits
        </a>
        <h1 class="h3 mb-0 mt-1">
            <i class="fas fa-arrow-circle-down me-2 text-success"></i>Deposit #<?= (int)($deposit['id'] ?? 0) ?>
            <span class="badge bg-<?= $statusColor ?> ms-2 fs-6"><?= ucfirst($deposit['status'] ?? 'pending') ?></span>
        </h1>
    </div>
    <?php if (($deposit['status'] ?? '') !== 'credited'): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" id="creditBtn">
            <i class="fas fa-check me-1"></i>Credit Wallet
        </button>
        <button class="btn btn-warning btn-sm" id="flagBtn">
            <i class="fas fa-flag me-1"></i>Flag
        </button>
        <button class="btn btn-danger btn-sm" id="failBtn">
            <i class="fas fa-times me-1"></i>Mark Failed
        </button>
    </div>
    <?php endif; ?>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-4">
    <!-- Left: deposit info -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4 mb-4">
            <h5 class="mb-3 fw-bold">Deposit Details</h5>
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="small text-secondary">Amount</div>
                    <div class="h5 fw-bold font-monospace text-success">
                        <?= number_format((float)($deposit['amount'] ?? 0), 8) ?>
                        <span class="text-info small ms-1"><?= e((string)($deposit['currency_code'] ?? '')) ?></span>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small text-secondary">Currency Type</div>
                    <div class="fw-semibold">
                        <span class="badge bg-secondary"><?= e(strtoupper((string)($deposit['currency_type'] ?? ''))) ?></span>
                        <?php if (!empty($deposit['network'])): ?>
                        <span class="badge bg-dark ms-1"><?= e((string)$deposit['network']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small text-secondary">Status</div>
                    <span class="badge bg-<?= $statusColor ?> fs-6"><?= ucfirst($deposit['status'] ?? 'pending') ?></span>
                </div>
                <div class="col-sm-6">
                    <div class="small text-secondary">Confirmations</div>
                    <div class="fw-semibold"><?= (int)($deposit['confirmations'] ?? 0) ?></div>
                </div>
                <div class="col-12">
                    <div class="small text-secondary mb-1">Transaction Hash</div>
                    <div class="font-monospace small p-2 bg-black rounded text-info">
                        <?= !empty($deposit['tx_hash']) ? e((string)$deposit['tx_hash']) : '—' ?>
                    </div>
                </div>
                <?php if (!$fiatMeta && !empty($deposit['from_address'])): ?>
                <div class="col-12">
                    <div class="small text-secondary mb-1">From Address</div>
                    <div class="font-monospace small p-2 bg-black rounded text-secondary">
                        <?= e((string)$deposit['from_address']) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($deposit['to_address'])): ?>
                <div class="col-12">
                    <div class="small text-secondary mb-1">Deposit-to Address</div>
                    <div class="font-monospace small p-2 bg-black rounded text-secondary">
                        <?= e((string)$deposit['to_address']) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($fiatMeta): ?>
                <div class="col-12">
                    <div class="small text-secondary mb-1">Fiat Deposit Details</div>
                    <div class="glass p-3 rounded-3">
                        <div class="row g-2 small">
                            <div class="col-sm-4"><span class="text-secondary">Bank:</span></div>
                            <div class="col-sm-8"><?= e((string)($fiatMeta['bank_name'] ?? '—')) ?></div>
                            <div class="col-sm-4"><span class="text-secondary">Account:</span></div>
                            <div class="col-sm-8"><?= e((string)($fiatMeta['account_name'] ?? '—')) ?></div>
                            <div class="col-sm-4"><span class="text-secondary">Payment Ref:</span></div>
                            <div class="col-sm-8 font-monospace"><?= e((string)($fiatMeta['payment_ref'] ?? '—')) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($deposit['flagged_reason'])): ?>
                <div class="col-12">
                    <div class="alert alert-warning small rounded-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Flag Reason:</strong> <?= e((string)$deposit['flagged_reason']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timestamps -->
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3 fw-bold text-secondary">Timeline</h6>
            <div class="row g-3 small">
                <div class="col-sm-6">
                    <div class="text-secondary">Submitted</div>
                    <div class="fw-semibold"><?= e((string)($deposit['created_at'] ?? '—')) ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-secondary">Credited At</div>
                    <div class="fw-semibold"><?= !empty($deposit['credited_at']) ? e((string)$deposit['credited_at']) : '—' ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-secondary">Reviewed By</div>
                    <div class="fw-semibold"><?= !empty($deposit['reviewed_by_name']) ? e((string)$deposit['reviewed_by_name']) : '—' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: user info -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3 fw-bold text-secondary">User Information</h6>
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3"
                     style="width:48px;height:48px;font-size:1.4rem">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div>
                    <div class="fw-bold"><?= e((string)($deposit['username'] ?? '-')) ?></div>
                    <div class="text-secondary small"><?= e((string)($deposit['email'] ?? '')) ?></div>
                </div>
            </div>
            <div class="row g-2 small">
                <div class="col-6"><span class="text-secondary">User ID:</span></div>
                <div class="col-6 text-end"><?= (int)($deposit['user_id'] ?? 0) ?></div>
                <div class="col-6"><span class="text-secondary">Wallet ID:</span></div>
                <div class="col-6 text-end"><?= (int)($deposit['wallet_id'] ?? 0) ?></div>
            </div>
            <hr class="border-secondary">
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/users/detail?id=<?= (int)($deposit['user_id'] ?? 0) ?>"
                   class="btn btn-outline-info btn-sm"><i class="fas fa-user me-1"></i>View User</a>
                <a href="/admin/wallets?user_id=<?= (int)($deposit['user_id'] ?? 0) ?>"
                   class="btn btn-outline-secondary btn-sm"><i class="fas fa-wallet me-1"></i>Wallets</a>
                <a href="/admin/deposits?search=<?= urlencode((string)($deposit['username'] ?? '')) ?>"
                   class="btn btn-outline-success btn-sm"><i class="fas fa-list me-1"></i>All Deposits</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick review from detail page -->
<?php if (($deposit['status'] ?? '') !== 'credited'): ?>
<script>
(function () {
    const csrf       = <?= json_encode($csrf) ?>;
    const depositId  = <?= (int)($deposit['id'] ?? 0) ?>;

    function doReview(status, flagReason) {
        return fetch('/admin/deposits/review', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: csrf, deposit_id: depositId, status, flag_reason: flagReason }),
        }).then(r => r.json());
    }

    document.getElementById('creditBtn').addEventListener('click', () => {
        Swal.fire({
            icon: 'question', title: 'Credit this deposit?',
            text: 'The amount will be credited to the user\'s wallet.',
            showCancelButton: true, confirmButtonText: 'Yes, Credit',
        }).then(res => {
            if (!res.isConfirmed) return;
            doReview('credited', '').then(data => {
                Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => { if (data.ok) location.reload(); });
            });
        });
    });

    document.getElementById('failBtn').addEventListener('click', () => {
        Swal.fire({
            icon: 'warning', title: 'Mark as Failed?',
            showCancelButton: true, confirmButtonText: 'Yes, Mark Failed',
        }).then(res => {
            if (!res.isConfirmed) return;
            doReview('failed', '').then(data => {
                Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => { if (data.ok) location.reload(); });
            });
        });
    });

    document.getElementById('flagBtn').addEventListener('click', () => {
        Swal.fire({
            icon: 'question', title: 'Flag Deposit',
            input: 'textarea', inputPlaceholder: 'Enter flag reason…',
            showCancelButton: true, confirmButtonText: 'Flag',
        }).then(res => {
            if (!res.isConfirmed || !res.value.trim()) return;
            doReview('flagged', res.value.trim()).then(data => {
                Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => { if (data.ok) location.reload(); });
            });
        });
    });
})();
</script>
<?php endif; ?>
