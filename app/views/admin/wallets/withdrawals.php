<?php declare(strict_types=1); ?>
<?php
$withdrawals     = is_array($withdrawals     ?? null) ? $withdrawals     : [];
$withdrawalStats = is_array($withdrawalStats ?? null) ? $withdrawalStats : [];
$filters         = is_array($filters         ?? null) ? $filters         : [];
$csrf            = \App\Libraries\Csrf::token();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-arrow-up me-2 text-warning"></i>Withdrawals Management</h1>
        <p class="text-secondary mb-0">Review, approve, process and reject user withdrawal requests.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/wallets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-wallet me-1"></i>Wallets</a>
        <a href="/admin/wallets/deposits" class="btn btn-outline-success btn-sm"><i class="fas fa-arrow-down me-1"></i>Deposits</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statItems = [
        ['label'=>'Total',      'val'=>number_format((int)($withdrawalStats['total']     ?? 0)), 'color'=>'secondary'],
        ['label'=>'Pending',    'val'=>number_format((int)($withdrawalStats['pending']   ?? 0)), 'color'=>'warning'],
        ['label'=>'Approved',   'val'=>number_format((int)($withdrawalStats['approved']  ?? 0)), 'color'=>'info'],
        ['label'=>'Processing', 'val'=>number_format((int)($withdrawalStats['processing']?? 0)), 'color'=>'primary'],
        ['label'=>'Completed',  'val'=>number_format((int)($withdrawalStats['completed'] ?? 0)), 'color'=>'success'],
        ['label'=>'Rejected',   'val'=>number_format((int)($withdrawalStats['rejected']  ?? 0)), 'color'=>'danger'],
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
    <form class="row g-2 align-items-end" method="get" action="/admin/wallets/withdrawals">
        <div class="col-lg-3">
            <input class="form-control" type="text" name="search"
                   placeholder="Username, email, address, TxHash"
                   value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending','approved','processing','completed','rejected','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
            <a class="btn btn-outline-secondary" href="/admin/wallets/withdrawals"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- Withdrawals Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table id="withdrawalsTable" class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th><th>User</th><th>Currency</th><th>Amount</th><th>Fee</th>
                    <th>Destination</th><th>Manual Review</th>
                    <th>Status</th><th>Requested</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($withdrawals as $wd): ?>
                <?php
                $statusColor = match($wd['status'] ?? '') {
                    'completed'  => 'success',
                    'rejected','cancelled' => 'danger',
                    'processing' => 'info',
                    'approved'   => 'primary',
                    default      => 'warning',
                };
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)$wd['id'] ?></td>
                    <td>
                        <div class="fw-semibold small"><?= e((string)($wd['username'] ?? '-')) ?></div>
                        <div class="text-secondary" style="font-size:.72rem"><?= e((string)($wd['email'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                    <td class="font-monospace small fw-semibold"><?= number_format((float)($wd['amount'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-secondary"><?= number_format((float)($wd['fee'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-secondary" style="max-width:140px">
                        <span title="<?= e((string)($wd['destination_address'] ?? '')) ?>">
                            <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 20)) ?>…
                        </span>
                        <?php if (!empty($wd['destination_tag'])): ?>
                            <div class="small text-info">Tag: <?= e((string)$wd['destination_tag']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ((int)($wd['requires_manual_review'] ?? 0)): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-eye me-1"></i>Required</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Auto</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst((string)($wd['status'] ?? '-'))) ?></span>
                        <?php if (!empty($wd['rejection_reason'])): ?>
                            <div class="small text-danger mt-1"><?= e((string)$wd['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary"><?= e(substr((string)($wd['requested_at'] ?? ''), 0, 16)) ?></td>
                    <td class="text-end">
                        <?php if (!in_array($wd['status'] ?? '', ['completed','rejected','cancelled'], true)): ?>
                        <button class="btn btn-xs btn-outline-primary review-withdrawal-btn"
                                data-id="<?= (int)$wd['id'] ?>"
                                data-user="<?= e((string)($wd['username'] ?? '')) ?>"
                                data-amount="<?= number_format((float)($wd['amount'] ?? 0), 8) ?>"
                                data-currency="<?= e((string)($wd['currency_code'] ?? '')) ?>"
                                data-status="<?= e((string)($wd['status'] ?? 'pending')) ?>">
                            <i class="fas fa-edit me-1"></i>Review
                        </button>
                        <?php else: ?>
                            <span class="text-<?= ($wd['status'] ?? '') === 'completed' ? 'success' : 'danger' ?> small">
                                <?= ucfirst((string)($wd['status'] ?? '')) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($withdrawals === []): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">No withdrawals found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Withdrawal Modal -->
<div class="modal fade" id="reviewWithdrawalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Review Withdrawal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewWithdrawalForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="withdrawal_id" id="reviewWithdrawalId">
                    <div class="alert alert-secondary small mb-3" id="reviewWithdrawalInfo"></div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">New Status *</label>
                        <select name="status" id="reviewWithdrawalStatus"
                                class="form-select bg-transparent text-light border-secondary" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected (Refunds Balance)</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3" id="txHashField" style="display:none">
                        <label class="form-label text-secondary small">Transaction Hash</label>
                        <input type="text" name="tx_hash"
                               class="form-control bg-transparent text-light border-secondary font-monospace"
                               placeholder="Blockchain transaction hash">
                    </div>
                    <div class="mb-0" id="rejectionField" style="display:none">
                        <label class="form-label text-secondary small">Rejection Reason</label>
                        <input type="text" name="rejection_reason"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="Reason for rejection">
                    </div>
                    <div class="alert alert-info small mt-3 mb-0" id="refundNotice" style="display:none">
                        <i class="fas fa-info-circle me-1"></i>
                        Rejecting a <em>pending</em> withdrawal will automatically refund the amount to the user's balance.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="wdReviewSpinner"></span>
                        Apply Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#withdrawalsTable').DataTable({ order:[[0,'desc']], pageLength:25, dom:'Bfrtip', buttons:['excel','csv','print'] });

document.querySelectorAll('.review-withdrawal-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reviewWithdrawalId').value     = btn.dataset.id;
        document.getElementById('reviewWithdrawalStatus').value = btn.dataset.status;
        document.getElementById('reviewWithdrawalInfo').innerHTML =
            `<strong>Withdrawal #${btn.dataset.id}</strong> · ${btn.dataset.user} · 
             <strong>${btn.dataset.amount} ${btn.dataset.currency}</strong>`;
        new bootstrap.Modal(document.getElementById('reviewWithdrawalModal')).show();
    });
});

document.getElementById('reviewWithdrawalStatus').addEventListener('change', function() {
    document.getElementById('txHashField').style.display   = ['processing','completed'].includes(this.value) ? '' : 'none';
    document.getElementById('rejectionField').style.display = this.value === 'rejected' ? '' : 'none';
    document.getElementById('refundNotice').style.display   = this.value === 'rejected' ? '' : 'none';
});

document.getElementById('reviewWithdrawalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('wdReviewSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/admin/wallets/withdrawals/review', {
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
