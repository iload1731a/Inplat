<?php declare(strict_types=1); ?>
<?php
$deposits = is_array($deposits ?? null) ? $deposits : [];
$stats    = is_array($stats    ?? null) ? $stats    : [];
$filters  = is_array($filters  ?? null) ? $filters  : [];
$csrf     = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-arrow-circle-down me-2 text-success"></i>Deposit Management</h1>
        <p class="text-secondary mb-0">Review, credit, and manage all user deposit requests.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/deposits/reports"  class="btn btn-outline-info btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
        <a href="/admin/deposits/gateways" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cogs me-1"></i>Gateways</a>
        <a href="/admin/deposits/export<?= !empty(array_filter($filters)) ? '?' . http_build_query(array_filter($filters)) : '' ?>"
           class="btn btn-outline-success btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
        <a href="/admin/withdrawals" class="btn btn-outline-warning btn-sm"><i class="fas fa-arrow-circle-up me-1"></i>Withdrawals</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statItems = [
        ['label' => 'Total',      'key' => 'total',            'color' => 'secondary'],
        ['label' => 'Pending',    'key' => 'pending',          'color' => 'warning'],
        ['label' => 'Confirmed',  'key' => 'confirmed',        'color' => 'info'],
        ['label' => 'Credited',   'key' => 'credited',         'color' => 'success'],
        ['label' => 'Failed',     'key' => 'failed',           'color' => 'danger'],
        ['label' => 'Flagged',    'key' => 'flagged',          'color' => 'danger'],
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
            <div class="h6 mb-0 fw-bold text-light font-monospace"><?= number_format((float)($stats['today_amount'] ?? 0), 4) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary mb-1">Today Credited</div>
            <div class="h6 mb-0 fw-bold text-success font-monospace"><?= number_format((float)($stats['today_credited'] ?? 0), 4) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2 align-items-end" method="get" action="/admin/deposits">
        <div class="col-lg-2">
            <input class="form-control" type="text" name="search"
                   placeholder="Username, email, TxHash, address"
                   value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'confirmed', 'credited', 'failed', 'flagged'] as $s): ?>
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
                   placeholder="BTC, ETH…"
                   value="<?= e((string)($filters['currency'] ?? '')) ?>">
        </div>
        <div class="col-lg-2">
            <input class="form-control" type="date" name="date_from"
                   value="<?= e((string)($filters['date_from'] ?? '')) ?>"
                   placeholder="From">
        </div>
        <div class="col-lg-2">
            <input class="form-control" type="date" name="date_to"
                   value="<?= e((string)($filters['date_to'] ?? '')) ?>"
                   placeholder="To">
        </div>
        <div class="col-lg-2 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
            <a class="btn btn-outline-secondary" href="/admin/deposits"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- Bulk Actions -->
<div class="glass rounded-4 p-3 mb-3 d-none" id="bulkActionsBar">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="text-secondary small"><i class="fas fa-check-square me-1"></i><span id="selectedCount">0</span> selected</span>
        <button class="btn btn-success btn-sm" id="bulkCreditBtn"><i class="fas fa-check-double me-1"></i>Bulk Credit</button>
        <button class="btn btn-warning btn-sm" id="bulkFlagBtn"><i class="fas fa-flag me-1"></i>Bulk Flag</button>
        <button class="btn btn-outline-secondary btn-sm" id="clearSelectionBtn"><i class="fas fa-times me-1"></i>Clear</button>
    </div>
</div>

<!-- Deposits Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table id="depositsTable" class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th>ID</th><th>User</th><th>Currency</th><th>Amount</th>
                    <th>TxHash</th><th>Confirmations</th><th>Status</th>
                    <th>Submitted</th><th class="text-end">Actions</th>
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
                    <td><input type="checkbox" class="form-check-input dep-chk" value="<?= (int)$dep['id'] ?>"></td>
                    <td class="small text-secondary"><?= (int)$dep['id'] ?></td>
                    <td>
                        <div class="fw-semibold small"><?= e((string)($dep['username'] ?? '-')) ?></div>
                        <div class="text-secondary" style="font-size:.72rem"><?= e((string)($dep['email'] ?? '')) ?></div>
                    </td>
                    <td>
                        <span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span>
                        <span class="badge bg-secondary ms-1 small"><?= e(strtoupper((string)($dep['currency_type'] ?? ''))) ?></span>
                    </td>
                    <td class="font-monospace small fw-semibold"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-secondary">
                        <?php if (!empty($dep['tx_hash'])): ?>
                            <span title="<?= e((string)$dep['tx_hash']) ?>"><?= e(substr((string)$dep['tx_hash'], 0, 20)) ?>…</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= (int)($dep['confirmations'] ?? 0) ?></span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($dep['status'] ?? 'pending') ?></span>
                        <?php if (!empty($dep['flagged_reason'])): ?>
                            <i class="fas fa-exclamation-triangle text-warning ms-1" title="<?= e((string)$dep['flagged_reason']) ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary"><?= e((string)($dep['created_at'] ?? '-')) ?></td>
                    <td class="text-end">
                        <a href="/admin/deposits/detail?id=<?= (int)$dep['id'] ?>"
                           class="btn btn-outline-info btn-xs py-0 px-2 me-1">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($dep['status'] !== 'credited'): ?>
                        <button class="btn btn-outline-success btn-xs py-0 px-2 me-1 review-btn"
                                data-id="<?= (int)$dep['id'] ?>"
                                data-status="credited"
                                title="Credit">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-xs py-0 px-2 review-btn"
                                data-id="<?= (int)$dep['id'] ?>"
                                data-status="flagged"
                                title="Flag">
                            <i class="fas fa-flag"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deposits)): ?>
            <tr><td colspan="10" class="text-center text-secondary py-4">No deposits found.</td></tr>
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
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Review Deposit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reviewDepositId">
                <div class="mb-3">
                    <label class="form-label text-secondary small">New Status</label>
                    <select class="form-select" id="reviewStatus">
                        <option value="confirmed">Confirmed</option>
                        <option value="credited">Credited (credits wallet)</option>
                        <option value="failed">Failed</option>
                        <option value="flagged">Flagged</option>
                    </select>
                </div>
                <div id="flagReasonWrap" class="mb-3 d-none">
                    <label class="form-label text-secondary small">Flag Reason</label>
                    <textarea class="form-control" id="flagReason" rows="2" placeholder="Reason for flagging…"></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="submitReviewBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="reviewSpinner"></span>
                    Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Flag Modal -->
<div class="modal fade" id="bulkFlagModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-flag me-2 text-warning"></i>Bulk Flag Deposits</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Flag Reason *</label>
                    <textarea class="form-control" id="bulkFlagReason" rows="3"
                              placeholder="Reason for flagging selected deposits…"></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="submitBulkFlagBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="bulkFlagSpinner"></span>
                    <i class="fas fa-flag me-1"></i>Flag Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf  = <?= json_encode($csrf) ?>;
    let selectedIds = [];

    // ─── Checkbox logic ─────────────────────────────────────────────
    document.getElementById('selectAll').addEventListener('change', function () {
        document.querySelectorAll('.dep-chk').forEach(cb => { cb.checked = this.checked; });
        syncSelected();
    });
    document.querySelectorAll('.dep-chk').forEach(cb => {
        cb.addEventListener('change', syncSelected);
    });
    function syncSelected() {
        selectedIds = Array.from(document.querySelectorAll('.dep-chk:checked')).map(c => parseInt(c.value));
        const bar = document.getElementById('bulkActionsBar');
        document.getElementById('selectedCount').textContent = selectedIds.length;
        bar.classList.toggle('d-none', selectedIds.length === 0);
    }
    document.getElementById('clearSelectionBtn').addEventListener('click', () => {
        document.querySelectorAll('.dep-chk, #selectAll').forEach(cb => { cb.checked = false; });
        syncSelected();
    });

    // ─── Single review ───────────────────────────────────────────────
    const reviewModal  = new bootstrap.Modal(document.getElementById('reviewModal'));
    document.querySelectorAll('.review-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('reviewDepositId').value = btn.dataset.id;
            document.getElementById('reviewStatus').value    = btn.dataset.status;
            document.getElementById('flagReasonWrap').classList.toggle('d-none', btn.dataset.status !== 'flagged');
            reviewModal.show();
        });
    });
    document.getElementById('reviewStatus').addEventListener('change', function () {
        document.getElementById('flagReasonWrap').classList.toggle('d-none', this.value !== 'flagged');
    });
    document.getElementById('submitReviewBtn').addEventListener('click', function () {
        const id     = document.getElementById('reviewDepositId').value;
        const status = document.getElementById('reviewStatus').value;
        const flag   = document.getElementById('flagReason').value;
        const spin   = document.getElementById('reviewSpinner');
        this.disabled = true; spin.classList.remove('d-none');
        fetch('/admin/deposits/review', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: csrf, deposit_id: id, status, flag_reason: flag }),
        })
        .then(r => r.json())
        .then(data => {
            reviewModal.hide();
            Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false })
                .then(() => { if (data.ok) location.reload(); });
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }))
        .finally(() => { this.disabled = false; spin.classList.add('d-none'); });
    });

    // ─── Bulk credit ─────────────────────────────────────────────────
    document.getElementById('bulkCreditBtn').addEventListener('click', () => {
        if (selectedIds.length === 0) return;
        Swal.fire({
            icon: 'question', title: `Credit ${selectedIds.length} deposit(s)?`,
            text: 'This will credit the wallet balance for each selected deposit.',
            showCancelButton: true, confirmButtonText: 'Yes, Credit All',
        }).then(res => {
            if (!res.isConfirmed) return;
            fetch('/admin/deposits/bulk-credit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: csrf, ids: selectedIds }),
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 3000, showConfirmButton: false })
                    .then(() => { if (data.ok) location.reload(); });
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }));
        });
    });

    // ─── Bulk flag ───────────────────────────────────────────────────
    const bulkFlagModal = new bootstrap.Modal(document.getElementById('bulkFlagModal'));
    document.getElementById('bulkFlagBtn').addEventListener('click', () => {
        if (selectedIds.length > 0) bulkFlagModal.show();
    });
    document.getElementById('submitBulkFlagBtn').addEventListener('click', function () {
        const reason = document.getElementById('bulkFlagReason').value.trim();
        if (!reason) { Swal.fire({ icon: 'warning', title: 'Please enter a flag reason.' }); return; }
        const spin = document.getElementById('bulkFlagSpinner');
        this.disabled = true; spin.classList.remove('d-none');
        fetch('/admin/deposits/bulk-flag', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: csrf, ids: selectedIds, reason }),
        })
        .then(r => r.json())
        .then(data => {
            bulkFlagModal.hide();
            Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false })
                .then(() => { if (data.ok) location.reload(); });
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }))
        .finally(() => { this.disabled = false; spin.classList.add('d-none'); });
    });

    // ─── DataTables ──────────────────────────────────────────────────
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#depositsTable').DataTable({
            order: [[1, 'desc']], pageLength: 25,
            columnDefs: [{ orderable: false, targets: [0, 9] }],
        });
    }
})();
</script>
