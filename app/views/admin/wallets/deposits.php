<?php declare(strict_types=1); ?>
<?php
$deposits     = is_array($deposits     ?? null) ? $deposits     : [];
$depositStats = is_array($depositStats ?? null) ? $depositStats : [];
$filters      = is_array($filters      ?? null) ? $filters      : [];
$csrf         = \App\Libraries\Csrf::token();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-arrow-down me-2 text-success"></i>Deposits Management</h1>
        <p class="text-secondary mb-0">Review, approve, and credit user deposits.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/wallets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-wallet me-1"></i>Wallets</a>
        <a href="/admin/wallets/withdrawals" class="btn btn-outline-warning btn-sm"><i class="fas fa-arrow-up me-1"></i>Withdrawals</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statItems = [
        ['label'=>'Total Deposits',  'val'=>number_format((int)($depositStats['total']   ?? 0)), 'color'=>'secondary'],
        ['label'=>'Pending',         'val'=>number_format((int)($depositStats['pending'] ?? 0)), 'color'=>'warning'],
        ['label'=>'Credited',        'val'=>number_format((int)($depositStats['credited']?? 0)), 'color'=>'success'],
        ['label'=>'Flagged',         'val'=>number_format((int)($depositStats['flagged'] ?? 0)), 'color'=>'danger'],
        ['label'=>'Total Credited',  'val'=>number_format((float)($depositStats['total_credited_amount'] ?? 0), 4), 'color'=>'info'],
    ];
    foreach ($statItems as $s):
    ?>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($s['label']) ?></div>
            <div class="h5 mb-0 text-<?= e($s['color']) ?> fw-bold"><?= e($s['val']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2 align-items-end" method="get" action="/admin/wallets/deposits">
        <div class="col-lg-3">
            <input class="form-control" type="text" name="search"
                   placeholder="Username, email, TxHash"
                   value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending','confirmed','credited','failed','flagged'] as $s): ?>
                <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
            <a class="btn btn-outline-secondary" href="/admin/wallets/deposits"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- Deposits Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table id="depositsTable" class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th><th>User</th><th>Currency</th><th>Amount</th>
                    <th>TxHash</th><th>From Address</th><th>Confirmations</th>
                    <th>Status</th><th>Date</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deposits as $dep): ?>
                <?php
                $statusColor = match($dep['status'] ?? '') {
                    'credited'  => 'success',
                    'confirmed' => 'info',
                    'failed'    => 'danger',
                    'flagged'   => 'warning',
                    default     => 'secondary',
                };
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)$dep['id'] ?></td>
                    <td>
                        <div class="fw-semibold small"><?= e((string)($dep['username'] ?? '-')) ?></div>
                        <div class="text-secondary" style="font-size:.72rem"><?= e((string)($dep['email'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span></td>
                    <td class="font-monospace small fw-semibold"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-secondary">
                        <?php if (!empty($dep['tx_hash'])): ?>
                            <span title="<?= e((string)$dep['tx_hash']) ?>"><?= e(substr((string)$dep['tx_hash'], 0, 20)) ?>…</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="font-monospace small text-secondary">
                        <?php if (!empty($dep['from_address'])): ?>
                            <?= e(substr((string)$dep['from_address'], 0, 20)) ?>…
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-center"><span class="badge bg-secondary"><?= (int)($dep['confirmations'] ?? 0) ?></span></td>
                    <td>
                        <span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst((string)($dep['status'] ?? '-'))) ?></span>
                        <?php if (!empty($dep['flagged_reason'])): ?>
                            <div class="small text-warning mt-1"><?= e((string)$dep['flagged_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary"><?= e(substr((string)($dep['created_at'] ?? ''), 0, 16)) ?></td>
                    <td class="text-end">
                        <?php if (($dep['status'] ?? '') !== 'credited'): ?>
                        <button class="btn btn-xs btn-outline-success review-deposit-btn"
                                data-id="<?= (int)$dep['id'] ?>"
                                data-user="<?= e((string)($dep['username'] ?? '')) ?>"
                                data-amount="<?= number_format((float)($dep['amount'] ?? 0), 8) ?>"
                                data-currency="<?= e((string)($dep['currency_code'] ?? '')) ?>"
                                data-status="<?= e((string)($dep['status'] ?? 'pending')) ?>"
                                title="Review Deposit">
                            <i class="fas fa-edit me-1"></i>Review
                        </button>
                        <?php else: ?>
                            <span class="text-success small"><i class="fas fa-check me-1"></i>Credited</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($deposits === []): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">No deposits found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Deposit Modal -->
<div class="modal fade" id="reviewDepositModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Review Deposit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewDepositForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="deposit_id" id="reviewDepositId">
                    <div class="alert alert-secondary small mb-3" id="reviewDepositInfo"></div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">New Status *</label>
                        <select name="status" id="reviewDepositStatus" class="form-select bg-transparent text-light border-secondary" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="credited">Credited (Credits Balance)</option>
                            <option value="failed">Failed</option>
                            <option value="flagged">Flagged</option>
                        </select>
                    </div>
                    <div class="mb-0" id="flagReasonField" style="display:none">
                        <label class="form-label text-secondary small">Flag Reason</label>
                        <input type="text" name="flag_reason" class="form-control bg-transparent text-light border-secondary"
                               placeholder="Reason for flagging this deposit">
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0" id="creditWarning" style="display:none">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>This will atomically credit the user's wallet balance.</strong>
                        This action cannot be reversed without a manual debit adjustment.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="reviewDepositSubmit">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="reviewSpinner"></span>
                        Apply Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#depositsTable').DataTable({ order:[[0,'desc']], pageLength:25, dom:'Bfrtip', buttons:['excel','csv','print'] });

document.querySelectorAll('.review-deposit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reviewDepositId').value    = btn.dataset.id;
        document.getElementById('reviewDepositStatus').value = btn.dataset.status;
        document.getElementById('reviewDepositInfo').innerHTML =
            `<strong>Deposit #${btn.dataset.id}</strong> · ${btn.dataset.user} · 
             <strong>${btn.dataset.amount} ${btn.dataset.currency}</strong>`;
        new bootstrap.Modal(document.getElementById('reviewDepositModal')).show();
    });
});

document.getElementById('reviewDepositStatus').addEventListener('change', function() {
    document.getElementById('flagReasonField').style.display  = this.value === 'flagged'  ? '' : 'none';
    document.getElementById('creditWarning').style.display    = this.value === 'credited' ? '' : 'none';
});

document.getElementById('reviewDepositForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('reviewSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/admin/wallets/deposits/review', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Updated!', text: json.message, confirmButtonColor:'#3b82f6' })
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
