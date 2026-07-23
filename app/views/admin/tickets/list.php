<?php declare(strict_types=1); ?>
<?php
$rows       = is_array($rows       ?? null) ? $rows       : [];
$total      = (int)($total         ?? 0);
$page       = (int)($page          ?? 1);
$perPage    = (int)($perPage       ?? 30);
$totalPages = (int)($totalPages    ?? 1);
$categories = is_array($categories ?? null) ? $categories : [];
$admins     = is_array($admins     ?? null) ? $admins     : [];
$filters    = is_array($filters    ?? null) ? $filters    : [];
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1"><i class="fas fa-ticket me-2 text-warning"></i>All Tickets</h5>
        <span class="text-secondary small"><?= number_format($total) ?> total</span>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/tickets/analytics" class="btn btn-outline-info btn-sm"><i class="fas fa-chart-line me-1"></i>Analytics</a>
        <a href="/admin/tickets/export?<?= http_build_query(array_filter($filters)) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
        <button class="btn btn-danger btn-sm" id="bulkBtn" disabled onclick="submitBulk()"><i class="fas fa-tasks me-1"></i>Bulk Action</button>
    </div>
</div>

<!-- FILTERS -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="get" action="/admin/tickets/list" class="row g-2">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="Ticket #, subject, username, email"
                   value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['open','in_progress','waiting_on_user','resolved','closed'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="priority" class="form-select">
                <option value="">All Priorities</option>
                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                    <option value="<?= e($p) ?>" <?= (($filters['priority'] ?? '') === $p) ? 'selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string)($cat['slug'] ?? '')) ?>" <?= (($filters['category'] ?? '') === ($cat['slug'] ?? '')) ? 'selected' : '' ?>><?= e((string)($cat['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="assigned" class="form-select">
                <option value="">All Assigned</option>
                <option value="unassigned" <?= (($filters['assigned'] ?? '') === 'unassigned') ? 'selected' : '' ?>>Unassigned</option>
                <?php foreach ($admins as $adm): ?>
                    <option value="<?= (int)($adm['id'] ?? 0) ?>" <?= (($filters['assigned'] ?? '') === (string)($adm['id'] ?? '')) ? 'selected' : '' ?>><?= e((string)($adm['display_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
        </div>
    </form>
    <div class="row g-2 mt-1">
        <div class="col-md-2">
            <input type="date" id="fDateFrom" class="form-control" placeholder="From"
                   value="<?= e((string)($filters['date_from'] ?? '')) ?>"
                   onchange="applyDateFilter()">
        </div>
        <div class="col-md-2">
            <input type="date" id="fDateTo" class="form-control" placeholder="To"
                   value="<?= e((string)($filters['date_to'] ?? '')) ?>"
                   onchange="applyDateFilter()">
        </div>
    </div>
</div>

<!-- BULK ACTION BAR (hidden until checked) -->
<div id="bulkBar" class="glass rounded-4 p-3 mb-3 d-none">
    <form id="bulkForm" data-ajax="true" action="/admin/tickets/bulk" method="POST">
        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
        <input type="hidden" name="ids" id="bulkIds">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="small text-secondary"><span id="selectedCount">0</span> selected</span>
            <select name="action" class="form-select form-select-sm" style="width:auto">
                <option value="close">Close</option>
                <option value="resolve">Mark Resolved</option>
                <option value="reopen">Re-open</option>
                <option value="assign">Assign to …</option>
            </select>
            <select name="assign_to" class="form-select form-select-sm" style="width:auto">
                <option value="0">Unassigned</option>
                <?php foreach ($admins as $adm): ?>
                    <option value="<?= (int)($adm['id'] ?? 0) ?>"><?= e((string)($adm['display_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-warning btn-sm">Apply</button>
        </div>
    </form>
</div>

<!-- TICKET TABLE -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                    <th>Ticket</th><th>User</th><th>Category</th><th>Priority</th><th>Status</th>
                    <th>Assigned</th><th>Replies</th><th>Updated</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $pri = (string)($row['priority'] ?? 'medium');
                $pc  = match($pri){ 'urgent'=>'danger','high'=>'warning','medium'=>'info', default=>'secondary' };
                $st  = (string)($row['status'] ?? 'open');
                $sc  = match($st){ 'resolved','closed'=>'success','in_progress'=>'primary','waiting_on_user'=>'info', default=>'warning' };
                ?>
                <tr>
                    <td><input type="checkbox" class="rowCheck" value="<?= (int)($row['id'] ?? 0) ?>" onchange="onCheck()"></td>
                    <td>
                        <div class="fw-semibold small"><?= e((string)($row['ticket_number'] ?? '#')) ?></div>
                        <div class="text-truncate text-secondary small" style="max-width:180px"><?= e((string)($row['subject'] ?? '')) ?></div>
                    </td>
                    <td>
                        <div class="small"><?= e((string)($row['username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($row['email'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= e((string)($row['category'] ?? '-')) ?></span></td>
                    <td><span class="badge bg-<?= $pc ?>"><?= e($pri) ?></span></td>
                    <td><span class="badge bg-<?= $sc ?>"><?= e(str_replace('_',' ',$st)) ?></span></td>
                    <td class="small"><?= e((string)($row['assigned_name'] ?? '—')) ?></td>
                    <td class="small text-center"><?= (int)($row['message_count'] ?? 0) ?></td>
                    <td class="small"><?= e(date('M d H:i', strtotime((string)($row['updated_at'] ?? 'now')))) ?></td>
                    <td>
                        <a href="/admin/tickets/detail?id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-xs btn-outline-info">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">No tickets match your filters</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <span class="small text-secondary">Page <?= $page ?> of <?= $totalPages ?></span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.rowCheck').forEach(cb => cb.checked = master.checked);
    onCheck();
}
function onCheck() {
    const checked = [...document.querySelectorAll('.rowCheck:checked')];
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('bulkIds').value = checked.map(c => c.value).join(',');
    document.getElementById('bulkBtn').disabled = checked.length === 0;
    document.getElementById('bulkBar').classList.toggle('d-none', checked.length === 0);
}
function submitBulk() { document.getElementById('bulkForm').dispatchEvent(new Event('submit')); }
function applyDateFilter() {
    const url = new URL(window.location);
    url.searchParams.set('date_from', document.getElementById('fDateFrom').value);
    url.searchParams.set('date_to',   document.getElementById('fDateTo').value);
    window.location = url;
}
</script>
