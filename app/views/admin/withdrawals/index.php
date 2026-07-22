<?php declare(strict_types=1); ?>
<?php
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
$stats       = is_array($stats       ?? null) ? $stats       : [];
$filters     = is_array($filters     ?? null) ? $filters     : [];
$csrf        = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-arrow-circle-up me-2 text-warning"></i>Withdrawal Management</h1>
        <p class="text-secondary mb-0">Review, approve, process and reject user withdrawal requests.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/withdrawals/reports"  class="btn btn-outline-info btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
        <a href="/admin/withdrawals/gateways" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cogs me-1"></i>Gateways</a>
        <a href="/admin/withdrawals/export<?= http_build_query(array_filter($filters)) ? '?'.http_build_query(array_filter($filters)) : '' ?>"
           class="btn btn-outline-success btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
        <a href="/admin/wallets/deposits" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-circle-down me-1"></i>Deposits</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statItems = [
        ['label'=>'Total',          'key'=>'total',           'color'=>'secondary'],
        ['label'=>'Pending',        'key'=>'pending',         'color'=>'warning'],
        ['label'=>'Manual Pending', 'key'=>'manual_pending',  'color'=>'danger'],
        ['label'=>'Approved',       'key'=>'approved',        'color'=>'info'],
        ['label'=>'Processing',     'key'=>'processing',      'color'=>'primary'],
        ['label'=>'Completed',      'key'=>'completed',       'color'=>'success'],
        ['label'=>'Rejected',       'key'=>'rejected',        'color'=>'danger'],
    ];
    foreach ($statItems as $s):
    ?>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary mb-1"><?= e($s['label']) ?></div>
            <div class="h5 mb-0 fw-bold text-<?= e($s['color']) ?>">
                <?= number_format((int)($stats[$s['key']] ?? 0)) ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary mb-1">Today Volume</div>
            <div class="h6 mb-0 fw-bold text-light font-monospace"><?= number_format((float)($stats['today_amount'] ?? 0), 2) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary mb-1">Fee Revenue</div>
            <div class="h6 mb-0 fw-bold text-success font-monospace"><?= number_format((float)($stats['total_fee_revenue'] ?? 0), 4) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2 align-items-end" method="get" action="/admin/withdrawals">
        <div class="col-lg-2">
            <input class="form-control" type="text" name="search"
                   placeholder="User, email, address, TxHash"
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
        <div class="col-lg-1">
            <select class="form-select" name="type">
                <option value="">All Types</option>
                <option value="crypto" <?= ($filters['type'] ?? '') === 'crypto' ? 'selected' : '' ?>>Crypto</option>
                <option value="fiat"   <?= ($filters['type'] ?? '') === 'fiat'   ? 'selected' : '' ?>>Fiat</option>
            </select>
        </div>
        <div class="col-lg-1">
            <input class="form-control" type="text" name="currency"
                   placeholder="Currency (BTC)"
                   value="<?= e((string)($filters['currency'] ?? '')) ?>">
        </div>
        <div class="col-lg-1">
            <select class="form-select" name="manual_review">
                <option value="">All</option>
                <option value="1" <?= ($filters['manual_review'] ?? '') === '1' ? 'selected' : '' ?>>Manual Only</option>
            </select>
        </div>
        <div class="col-lg-1">
            <input class="form-control" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>" placeholder="From">
        </div>
        <div class="col-lg-1">
            <input class="form-control" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>" placeholder="To">
        </div>
        <div class="col-lg-1">
            <input class="form-control" type="number" name="amount_min" step="0.01" placeholder="Min Amount"
                   value="<?= e((string)($filters['amount_min'] ?? '')) ?>">
        </div>
        <div class="col-lg-1">
            <input class="form-control" type="number" name="amount_max" step="0.01" placeholder="Max Amount"
                   value="<?= e((string)($filters['amount_max'] ?? '')) ?>">
        </div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i></button>
            <a class="btn btn-outline-secondary" href="/admin/withdrawals"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- Bulk Actions -->
<div class="glass rounded-4 p-3 mb-3" id="bulkActionsBar" style="display:none">
    <div class="d-flex align-items-center gap-3">
        <span class="text-secondary small" id="selectedCount">0 selected</span>
        <button class="btn btn-sm btn-success" id="btnBulkApprove">
            <i class="fas fa-check me-1"></i>Approve Selected
        </button>
        <button class="btn btn-sm btn-danger" id="btnBulkReject">
            <i class="fas fa-times me-1"></i>Reject Selected
        </button>
        <button class="btn btn-sm btn-outline-secondary" id="btnClearSelection">
            <i class="fas fa-square me-1"></i>Clear Selection
        </button>
    </div>
</div>

<!-- Withdrawals Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table id="withdrawalsTable" class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                    <th>ID</th><th>User</th><th>Type</th><th>Currency</th>
                    <th>Amount</th><th>Fee</th><th>Net</th>
                    <th>Destination</th><th>Manual</th>
                    <th>Status</th><th>Requested</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($withdrawals as $wd): ?>
                <?php
                $statusColor = match($wd['status'] ?? '') {
                    'completed'  => 'success',
                    'rejected', 'cancelled' => 'danger',
                    'processing' => 'info',
                    'approved'   => 'primary',
                    default      => 'warning',
                };
                $net = bcsub((string)($wd['amount'] ?? '0'), (string)($wd['fee'] ?? '0'), 8);
                $isFiat = ($wd['currency_type'] ?? '') === 'fiat';
                ?>
                <tr>
                    <td>
                        <?php if (!in_array($wd['status'] ?? '', ['completed','rejected','cancelled'], true)): ?>
                        <input type="checkbox" class="form-check-input row-check" value="<?= (int)$wd['id'] ?>">
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary">
                        <a href="/admin/withdrawals/detail?id=<?= (int)$wd['id'] ?>" class="text-info">#<?= (int)$wd['id'] ?></a>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= e((string)($wd['username'] ?? '-')) ?></div>
                        <div class="text-secondary" style="font-size:.72rem"><?= e((string)($wd['email'] ?? '')) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $isFiat ? 'bg-info' : 'bg-purple' ?> text-dark" style="<?= $isFiat ? '' : 'background:#6f42c1!important' ?>">
                            <?= $isFiat ? 'FIAT' : 'CRYPTO' ?>
                        </span>
                        <?php if (!empty($wd['network'])): ?>
                        <div class="small text-secondary"><?= e((string)$wd['network']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                    <td class="font-monospace small fw-semibold"><?= number_format((float)($wd['amount'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-danger">-<?= number_format((float)($wd['fee'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-success"><?= number_format((float)$net, 8) ?></td>
                    <td class="small text-secondary" style="max-width:140px">
                        <?php if ($isFiat): ?>
                            <?php
                            $bank = json_decode((string)($wd['destination_address'] ?? '{}'), true);
                            ?>
                            <span class="text-info">
                                <i class="fas fa-university me-1"></i>
                                <?= e((string)($bank['bank_name'] ?? 'Bank Transfer')) ?>
                            </span>
                            <?php if (!empty($bank['account_number'])): ?>
                            <div style="font-size:.72rem">A/C: <?= e((string)$bank['account_number']) ?></div>
                            <?php elseif (!empty($bank['iban'])): ?>
                            <div style="font-size:.72rem">IBAN: <?= e((string)$bank['iban']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span title="<?= e((string)($wd['destination_address'] ?? '')) ?>"
                                  class="font-monospace">
                                <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 18)) ?>…
                            </span>
                            <?php if (!empty($wd['destination_tag'])): ?>
                            <div style="font-size:.72rem" class="text-info">Tag: <?= e((string)$wd['destination_tag']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ((int)($wd['requires_manual_review'] ?? 0)): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-eye me-1"></i>Yes</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Auto</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusColor ?>">
                            <?= e(ucfirst((string)($wd['status'] ?? '-'))) ?>
                        </span>
                        <?php if (!empty($wd['rejection_reason'])): ?>
                        <div class="small text-danger mt-1" style="font-size:.72rem">
                            <?= e(substr((string)$wd['rejection_reason'], 0, 40)) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary"><?= e(substr((string)($wd['requested_at'] ?? ''), 0, 16)) ?></td>
                    <td class="text-end">
                        <a href="/admin/withdrawals/detail?id=<?= (int)$wd['id'] ?>"
                           class="btn btn-xs btn-outline-info me-1" title="View Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (!in_array($wd['status'] ?? '', ['completed','rejected','cancelled'], true)): ?>
                        <button class="btn btn-xs btn-outline-primary review-btn"
                                data-id="<?= (int)$wd['id'] ?>"
                                data-user="<?= e((string)($wd['username'] ?? '')) ?>"
                                data-amount="<?= number_format((float)($wd['amount'] ?? 0), 8) ?>"
                                data-currency="<?= e((string)($wd['currency_code'] ?? '')) ?>"
                                data-status="<?= e((string)($wd['status'] ?? 'pending')) ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php else: ?>
                        <?php if (!empty($wd['tx_hash'])): ?>
                            <span class="text-secondary font-monospace" style="font-size:.65rem" title="<?= e((string)$wd['tx_hash']) ?>">
                                <?= e(substr((string)$wd['tx_hash'], 0, 12)) ?>…
                            </span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($withdrawals === []): ?>
                <tr><td colspan="13" class="text-center text-secondary py-4">No withdrawals found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Review Withdrawal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="withdrawal_id" id="reviewId">
                    <div class="alert alert-secondary small mb-3" id="reviewInfo"></div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">New Status *</label>
                        <select name="status" id="reviewStatus"
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
                        <label class="form-label text-secondary small">Rejection Reason *</label>
                        <input type="text" name="rejection_reason"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="Reason for rejection">
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0" id="refundNotice" style="display:none">
                        <i class="fas fa-undo me-1"></i>
                        Rejecting a pending/approved withdrawal will <strong>automatically refund</strong> the full amount (including fee) to the user's balance.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="reviewSpinner"></span>
                        Apply Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2 text-danger"></i>Bulk Reject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkRejectForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <p class="small text-secondary mb-3">
                        <span id="bulkRejectCount">0</span> withdrawal(s) will be rejected and the amounts will be <strong>refunded</strong> to users.
                    </p>
                    <div>
                        <label class="form-label text-secondary small">Rejection Reason *</label>
                        <input type="text" name="reason" required
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="Reason for rejection (visible to users)">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="bulkRejectSpinner"></span>
                        Reject &amp; Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const _csrf = '<?= e($csrf) ?>';
$('#withdrawalsTable').DataTable({ order:[[1,'desc']], pageLength:25, dom:'Bfrtip', buttons:['excel','csv','print'] });

// Row selection
const selectedIds = new Set();
function syncBulkBar() {
    const c = selectedIds.size;
    document.getElementById('selectedCount').textContent = c + ' selected';
    document.getElementById('bulkActionsBar').style.display = c > 0 ? '' : 'none';
    document.getElementById('bulkRejectCount').textContent = c;
}
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = this.checked;
        if (this.checked) selectedIds.add(parseInt(cb.value));
        else selectedIds.delete(parseInt(cb.value));
    });
    syncBulkBar();
});
document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', function() {
        if (this.checked) selectedIds.add(parseInt(this.value));
        else selectedIds.delete(parseInt(this.value));
        syncBulkBar();
    });
});
document.getElementById('btnClearSelection').addEventListener('click', () => {
    selectedIds.clear();
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.getElementById('checkAll').checked = false;
    syncBulkBar();
});

// Bulk approve
document.getElementById('btnBulkApprove').addEventListener('click', async () => {
    if (selectedIds.size === 0) return;
    const confirmed = await Swal.fire({
        icon:'question', title:`Approve ${selectedIds.size} withdrawal(s)?`,
        showCancelButton:true, confirmButtonText:'Yes, Approve', confirmButtonColor:'#22c55e'
    });
    if (!confirmed.isConfirmed) return;
    const res  = await fetch('/admin/withdrawals/bulk-approve', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ _token: _csrf, ids: [...selectedIds] })
    });
    const json = await res.json();
    if (json.ok) {
        Swal.fire({ icon:'success', title:'Done!', text: json.message, confirmButtonColor:'#3b82f6' })
            .then(() => location.reload());
    } else {
        Swal.fire({ icon:'error', title:'Error', text: json.message });
    }
});

// Bulk reject
document.getElementById('btnBulkReject').addEventListener('click', () => {
    if (selectedIds.size === 0) return;
    new bootstrap.Modal(document.getElementById('bulkRejectModal')).show();
});
document.getElementById('bulkRejectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('bulkRejectSpinner');
    spinner.classList.remove('d-none');
    const reason = new FormData(this).get('reason');
    try {
        const res  = await fetch('/admin/withdrawals/bulk-reject', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ _token: _csrf, ids: [...selectedIds], reason })
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Rejected!', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } finally {
        spinner.classList.add('d-none');
    }
});

// Single review
document.querySelectorAll('.review-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reviewId').value     = btn.dataset.id;
        document.getElementById('reviewStatus').value = btn.dataset.status;
        document.getElementById('reviewInfo').innerHTML =
            `<strong>Withdrawal #${btn.dataset.id}</strong> &bull; ${btn.dataset.user} &bull; 
             <strong>${btn.dataset.amount} ${btn.dataset.currency}</strong>`;
        ['txHashField','rejectionField','refundNotice'].forEach(id =>
            document.getElementById(id).style.display = 'none'
        );
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    });
});
document.getElementById('reviewStatus').addEventListener('change', function() {
    document.getElementById('txHashField').style.display    = ['processing','completed'].includes(this.value) ? '' : 'none';
    document.getElementById('rejectionField').style.display  = this.value === 'rejected' ? '' : 'none';
    document.getElementById('refundNotice').style.display    = this.value === 'rejected' ? '' : 'none';
});
document.getElementById('reviewForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('reviewSpinner');
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
