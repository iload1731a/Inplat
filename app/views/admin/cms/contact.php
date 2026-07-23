<?php declare(strict_types=1); ?>
<?php
$items      = is_array($items   ?? null) ? $items   : [];
$total      = (int)($total      ?? 0);
$page       = (int)($page       ?? 1);
$totalPages = (int)($totalPages ?? 1);
$kpis       = is_array($kpis    ?? null) ? $kpis    : [];
$filters    = is_array($filters ?? null) ? $filters : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-envelope me-2 text-info"></i>Contact Messages</h1>
        <p class="text-secondary mb-0"><?= number_format($total) ?> messages total</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Unread',   (int)($kpis['unread']   ?? 0), 'danger'],
        ['Read',     (int)($kpis['read_cnt'] ?? 0), 'secondary'],
        ['Replied',  (int)($kpis['replied']  ?? 0), 'success'],
        ['Spam',     (int)($kpis['spam']     ?? 0), 'warning'],
        ['Last 7d',  (int)($kpis['last7d']   ?? 0), 'info'],
        ['Total',    (int)($kpis['total']    ?? 0), 'light'],
    ];
    foreach ($cards as [$label, $val, $color]):
    ?>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="glass rounded-4 p-3 text-center">
            <div class="fw-bold h5 mb-0 text-<?= $color ?>"><?= number_format($val) ?></div>
            <div class="small text-secondary"><?= e($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="GET" action="/admin/cms/contact" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary"
                   placeholder="Search name, email, subject..." value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Status</option>
                <?php foreach (['unread','read','replied','spam'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-secondary w-100" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
        </div>
        <div class="col-md-2">
            <a href="/admin/cms/contact" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Department</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $msg): ?>
                <?php $st = (string)($msg['status'] ?? 'unread'); ?>
                <tr class="<?= $st === 'unread' ? 'table-active' : '' ?>">
                    <td class="<?= $st === 'unread' ? 'fw-bold' : '' ?>"><?= e((string)($msg['name'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($msg['email'] ?? '-')) ?></td>
                    <td class="small"><?= e(substr((string)($msg['subject'] ?? '-'), 0, 40)) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e(ucfirst((string)($msg['department'] ?? 'general'))) ?></span></td>
                    <td>
                        <span class="badge text-bg-<?= ['unread'=>'danger','read'=>'secondary','replied'=>'success','spam'=>'warning'][$st] ?? 'secondary' ?>">
                            <?= e(ucfirst($st)) ?>
                        </span>
                    </td>
                    <td class="small text-secondary"><?= e(substr((string)($msg['created_at'] ?? '-'), 0, 10)) ?></td>
                    <td class="text-end">
                        <a href="/admin/cms/contact/view?id=<?= (int)$msg['id'] ?>" class="btn btn-xs btn-outline-light me-1">View</a>
                        <button class="btn btn-xs btn-outline-warning me-1" onclick="markSpam(<?= (int)$msg['id'] ?>)" title="Mark Spam"><i class="fas fa-ban"></i></button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteMsg(<?= (int)$msg['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No messages found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="small text-secondary">Page <?= $page ?> of <?= $totalPages ?></div>
        <div class="d-flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>" class="btn btn-xs btn-outline-light">‹ Prev</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>" class="btn btn-xs btn-outline-light">Next ›</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function markSpam(id) {
    if (!confirm('Mark as spam?')) return;
    fetch('/admin/cms/contact/spam', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function deleteMsg(id) {
    if (!confirm('Delete message?')) return;
    fetch('/admin/cms/contact/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
</script>
