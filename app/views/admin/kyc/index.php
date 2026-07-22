<?php declare(strict_types=1); ?>
<?php
$queue        = is_array($queue ?? null)        ? $queue        : [];
$kycStats     = is_array($kycStats ?? null)     ? $kycStats     : [];
$userStats    = is_array($userStats ?? null)    ? $userStats    : [];
$daily        = is_array($daily ?? null)        ? $daily        : [];
$typeBreakdown= is_array($typeBreakdown ?? null)? $typeBreakdown: [];
$filters      = is_array($filters ?? null)      ? $filters      : [];
$total        = (int)($total    ?? 0);
$page         = (int)($page     ?? 1);
$totalPages   = (int)($totalPages ?? 1);
$perPage      = (int)($perPage  ?? 50);
$avgReviewHrs = (float)($avgReviewHrs ?? 0);
$csrf         = \App\Libraries\Csrf::token();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-id-card me-2 text-info"></i>KYC Verification</h1>
        <p class="text-secondary mb-0">Review identity documents and manage KYC compliance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/kyc/compliance" class="btn btn-outline-info btn-sm"><i class="fas fa-chart-bar me-1"></i>Compliance</a>
        <a href="/admin/kyc/requirements" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list-check me-1"></i>Requirements</a>
        <a href="/admin/kyc/audit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history me-1"></i>Audit Log</a>
        <a href="/admin/kyc/export?<?= http_build_query($filters) ?>" class="btn btn-outline-success btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
    </div>
</div>

<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Pending Review',   $kycStats['pending_review'] ?? 0,   'fa-clock',        '#f59e0b', '/admin/kyc?status=pending'],
        ['Approved Today',   $kycStats['reviewed_today'] ?? 0,   'fa-check-circle', '#34d399', '/admin/kyc?status=approved'],
        ['Submitted Today',  $kycStats['submitted_today'] ?? 0,  'fa-upload',       '#38bdf8', '/admin/kyc'],
        ['Total Docs',       $kycStats['total_documents'] ?? 0,  'fa-folder-open',  '#a78bfa', '/admin/kyc'],
        ['Verified Users',   $userStats['verified_users'] ?? 0,  'fa-user-check',   '#4ade80', '/admin/kyc?status=approved'],
        ['Pending Users',    $userStats['pending_users'] ?? 0,   'fa-user-clock',   '#fb923c', '/admin/kyc?status=pending'],
        ['Rejected',         $kycStats['rejected'] ?? 0,         'fa-times-circle', '#f87171', '/admin/kyc?status=rejected'],
        ['Avg Review Time',  $avgReviewHrs . 'h',                'fa-stopwatch',    '#e879f9', '/admin/kyc/compliance'],
    ];
    foreach ($cards as [$label, $val, $icon, $color, $link]):
    ?>
    <div class="col-6 col-md-3">
        <a href="<?= e($link) ?>" class="text-decoration-none">
            <div class="glass rounded-3 p-3 h-100">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas <?= $icon ?> small" style="color:<?= $color ?>"></i>
                    <span class="text-secondary small"><?= $label ?></span>
                </div>
                <div class="fw-bold fs-5"><?= is_numeric($val) ? number_format((float)$val) : e((string)$val) ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- 30-Day Chart + Type Breakdown -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-3 p-3">
            <div class="fw-semibold mb-3"><i class="fas fa-chart-area me-2 text-info"></i>30-Day Submission Trend</div>
            <div id="kycTrendChart"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-3 p-3 h-100">
            <div class="fw-semibold mb-3"><i class="fas fa-pie-chart me-2 text-warning"></i>By Document Type</div>
            <?php if (empty($typeBreakdown)): ?>
            <div class="text-secondary small text-center py-3">No data yet.</div>
            <?php else: ?>
            <?php foreach ($typeBreakdown as $tb):
                $total_tb = (int)($tb['total'] ?? 0);
            ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between">
                        <span class="small"><?= e(ucwords(str_replace('_', ' ', (string)$tb['document_type']))) ?></span>
                        <span class="small text-secondary"><?= $total_tb ?></span>
                    </div>
                    <div class="progress mt-1" style="height:4px">
                        <?php $totAll = array_sum(array_column($typeBreakdown, 'total')); ?>
                        <div class="progress-bar bg-info" style="width:<?= $totAll > 0 ? round($total_tb / $totAll * 100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="glass rounded-3 p-3 mb-3">
    <form class="row g-2 align-items-end" method="get" action="/admin/kyc" id="filterForm">
        <div class="col-lg-3"><input class="form-control" type="text" name="search" placeholder="Username, email or doc number" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <?php foreach (['pending','approved','rejected'] as $s): ?>
                <option value="<?= e($s) ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="document_type">
                <option value="">All Doc Types</option>
                <?php foreach (['passport','national_id','drivers_license','proof_of_address','selfie','corporate_doc','other'] as $dt): ?>
                <option value="<?= e($dt) ?>" <?= (($filters['document_type'] ?? '') === $dt) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$dt))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <input type="date" class="form-control" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>" placeholder="From">
        </div>
        <div class="col-lg-2">
            <input type="date" class="form-control" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>" placeholder="To">
        </div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-primary flex-grow-1" type="submit">Go</button>
            <a class="btn btn-outline-secondary" href="/admin/kyc">✕</a>
        </div>
    </form>
</div>

<!-- Bulk Action Bar -->
<div class="d-flex align-items-center gap-2 mb-2" id="bulkBar" style="display:none!important">
    <span class="text-secondary small" id="bulkCount">0 selected</span>
    <button class="btn btn-xs btn-outline-success" id="btnBulkApprove"><i class="fas fa-check me-1"></i>Approve Selected</button>
    <button class="btn btn-xs btn-outline-danger"  id="btnBulkReject"><i class="fas fa-times me-1"></i>Reject Selected</button>
</div>

<!-- Queue Table -->
<div class="glass rounded-3 p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-secondary">
            <?= number_format($total) ?> documents
            <?php if ($total > $perPage): ?> — Page <?= $page ?> of <?= $totalPages ?><?php endif; ?>
        </span>
        <label class="small d-flex align-items-center gap-1">
            <input type="checkbox" id="selectAll"> Select all on page
        </label>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0 small" id="kycTable">
            <thead class="text-secondary">
                <tr>
                    <th width="30"><input type="checkbox" id="selectAllHead"></th>
                    <th>ID</th>
                    <th>User</th>
                    <th>KYC Status</th>
                    <th>Document Type</th>
                    <th>Doc Number</th>
                    <th>Country</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Reviewer</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($queue as $doc): ?>
                <tr>
                    <td>
                        <?php if (($doc['status'] ?? '') === 'pending'): ?>
                        <input type="checkbox" class="doc-checkbox" value="<?= (int)$doc['id'] ?>">
                        <?php endif; ?>
                    </td>
                    <td class="text-secondary"><?= (int)$doc['id'] ?></td>
                    <td>
                        <a href="/admin/kyc/user?user_id=<?= (int)$doc['user_id'] ?>" class="text-light text-decoration-none fw-semibold"><?= e((string)($doc['username'] ?? '-')) ?></a>
                        <div class="text-secondary" style="font-size:.7rem"><?= e((string)($doc['email'] ?? '')) ?></div>
                    </td>
                    <td>
                        <?php $ks = (string)($doc['kyc_status'] ?? 'unverified');
                              $kc = match($ks) { 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary' };
                        ?>
                        <span class="badge bg-<?= $kc ?>"><?= e(ucfirst($ks)) ?></span>
                    </td>
                    <td><?= e(ucwords(str_replace('_',' ', (string)($doc['document_type'] ?? '-')))) ?></td>
                    <td class="text-secondary font-monospace"><?= e((string)($doc['document_number'] ?? '-')) ?></td>
                    <td class="text-secondary"><?= e((string)($doc['issue_country'] ?? '-')) ?></td>
                    <td class="text-secondary"><?= e((string)($doc['submitted_at'] ?? '-')) ?></td>
                    <td>
                        <?php $ds = (string)($doc['status'] ?? 'pending');
                              $dc = match($ds) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
                        ?>
                        <span class="badge bg-<?= $dc ?>"><?= e(ucfirst($ds)) ?></span>
                    </td>
                    <td class="text-secondary"><?= e((string)($doc['reviewer_name'] ?? '-')) ?></td>
                    <td class="text-end">
                        <a href="/admin/kyc/detail?id=<?= (int)$doc['id'] ?>" class="btn btn-xs btn-outline-secondary me-1"><i class="fas fa-eye"></i></a>
                        <?php if ($ds === 'pending'): ?>
                        <button class="btn btn-xs btn-outline-success me-1" onclick="openReviewModal(<?= (int)$doc['id'] ?>, '<?= e((string)$doc['username']) ?>', 'approved')"><i class="fas fa-check"></i></button>
                        <button class="btn btn-xs btn-outline-danger"  onclick="openReviewModal(<?= (int)$doc['id'] ?>, '<?= e((string)$doc['username']) ?>', 'rejected')"><i class="fas fa-times"></i></button>
                        <?php else: ?>
                        <span class="text-secondary">Reviewed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($queue)): ?>
                <tr><td colspan="11" class="text-center text-secondary py-4">No KYC documents match your filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center gap-1 mt-3">
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"
           class="btn btn-xs <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- KYC Review Modal -->
<div class="modal fade" id="kycReviewModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content bg-dark border-secondary" id="kycReviewForm">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="document_id" id="kycDocId">
            <input type="hidden" name="status" id="kycStatus">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title mb-0" id="kycReviewTitle">Review Document</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small mb-2">User: <strong id="kycUsername"></strong></p>
                <div class="mb-2">
                    <label class="form-label small">Notes</label>
                    <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Reviewer notes…"></textarea>
                </div>
                <div id="kycLevelGroup">
                    <label class="form-label small">Grant KYC Level</label>
                    <select class="form-select form-select-sm" name="kyc_level">
                        <option value="1">Level 1 – Basic</option>
                        <option value="2">Level 2 – Intermediate</option>
                        <option value="3">Level 3 – Full</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="kycSubmitBtn" class="btn btn-sm btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Approve Modal -->
<div class="modal fade" id="bulkApproveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content bg-dark border-secondary" id="bulkApproveForm">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title">Bulk Approve</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small mb-2">Approve <strong id="bulkApproveCount">?</strong> selected documents.</p>
                <select class="form-select form-select-sm mb-2" name="kyc_level">
                    <option value="1">Level 1</option><option value="2">Level 2</option><option value="3">Level 3</option>
                </select>
                <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Notes (optional)…"></textarea>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-success">Approve All</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form class="modal-content bg-dark border-secondary" id="bulkRejectForm">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title">Bulk Reject</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small mb-2">Reject <strong id="bulkRejectCount">?</strong> selected documents.</p>
                <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Rejection reason…" required></textarea>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger">Reject All</button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── Trend Chart ─────────────────────────────────────────────────────────────
const daily = <?= json_encode(array_values($daily)) ?>;
if (daily.length) {
    ApexCharts && new ApexCharts(document.getElementById('kycTrendChart'), {
        chart: { type: 'bar', height: 220, background: 'transparent', toolbar: { show: false } },
        series: [
            { name: 'Submitted', data: daily.map(d => ({ x: d.day, y: parseInt(d.total) })) },
            { name: 'Approved',  data: daily.map(d => ({ x: d.day, y: parseInt(d.approved) })) },
            { name: 'Rejected',  data: daily.map(d => ({ x: d.day, y: parseInt(d.rejected) })) },
        ],
        colors: ['#38bdf8', '#34d399', '#f87171'],
        xaxis: { type: 'datetime', labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        legend: { labels: { colors: '#cbd5e1' } },
        theme: { mode: 'dark' },
        dataLabels: { enabled: false },
        grid: { borderColor: '#334155' },
        stroke: { width: 0 },
        tooltip: { theme: 'dark' },
    }).render();
}

// ─── Checkbox logic ──────────────────────────────────────────────────────────
const bulkBar     = document.getElementById('bulkBar');
const bulkCountEl = document.getElementById('bulkCount');
function updateBulk() {
    const checked = document.querySelectorAll('.doc-checkbox:checked');
    const n = checked.length;
    if (n > 0) { bulkBar.style.display = ''; } else { bulkBar.style.removeProperty('display'); }
    bulkCountEl.textContent = n + ' selected';
    document.getElementById('bulkApproveCount').textContent = n;
    document.getElementById('bulkRejectCount').textContent  = n;
}
document.querySelectorAll('#selectAll, #selectAllHead').forEach(el =>
    el.addEventListener('change', function () {
        document.querySelectorAll('.doc-checkbox').forEach(c => c.checked = this.checked);
        updateBulk();
    })
);
document.querySelectorAll('.doc-checkbox').forEach(c => c.addEventListener('change', updateBulk));

function getSelectedIds() {
    return [...document.querySelectorAll('.doc-checkbox:checked')].map(c => c.value);
}

document.getElementById('btnBulkApprove')?.addEventListener('click', () =>
    new bootstrap.Modal(document.getElementById('bulkApproveModal')).show()
);
document.getElementById('btnBulkReject')?.addEventListener('click', () =>
    new bootstrap.Modal(document.getElementById('bulkRejectModal')).show()
);

// Bulk Approve submit
document.getElementById('bulkApproveForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    getSelectedIds().forEach(id => fd.append('doc_ids[]', id));
    $.ajax({ url: '/admin/kyc/bulk-approve', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) {
            bootstrap.Modal.getInstance(document.getElementById('bulkApproveModal')).hide();
            Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false })
                .then(() => r.ok && location.reload());
        },
        error(xhr) { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
});

// Bulk Reject submit
document.getElementById('bulkRejectForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    getSelectedIds().forEach(id => fd.append('doc_ids[]', id));
    $.ajax({ url: '/admin/kyc/bulk-reject', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) {
            bootstrap.Modal.getInstance(document.getElementById('bulkRejectModal')).hide();
            Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false })
                .then(() => r.ok && location.reload());
        },
        error(xhr) { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
});

// ─── Single Review Modal ──────────────────────────────────────────────────────
function openReviewModal(docId, username, status) {
    document.getElementById('kycDocId').value   = docId;
    document.getElementById('kycStatus').value  = status;
    document.getElementById('kycUsername').textContent   = username;
    document.getElementById('kycReviewTitle').textContent = (status === 'approved' ? 'Approve' : 'Reject') + ' Document';
    const btn = document.getElementById('kycSubmitBtn');
    btn.textContent  = status === 'approved' ? 'Approve' : 'Reject';
    btn.className    = 'btn btn-sm btn-' + (status === 'approved' ? 'success' : 'danger');
    document.getElementById('kycLevelGroup').style.display = status === 'approved' ? '' : 'none';
    new bootstrap.Modal(document.getElementById('kycReviewModal')).show();
}

document.getElementById('kycReviewForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({ url: '/admin/kyc/review', method: 'POST', data: fd, processData: false, contentType: false,
        success(r) {
            bootstrap.Modal.getInstance(document.getElementById('kycReviewModal')).hide();
            Swal.fire({ icon: r.ok ? 'success' : 'error', text: r.message, timer: 2000, showConfirmButton: false })
                .then(() => r.ok && location.reload());
        },
        error(xhr) { Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }); }
    });
});
</script>
