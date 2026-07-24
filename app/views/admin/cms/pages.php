<?php declare(strict_types=1); ?>
<?php
$items      = is_array($items    ?? null) ? $items    : [];
$total      = (int)($total       ?? 0);
$page       = (int)($page        ?? 1);
$perPage    = (int)($perPage     ?? 20);
$totalPages = (int)($totalPages  ?? 1);
$filters    = is_array($filters  ?? null) ? $filters  : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-file-alt me-2 text-info"></i>CMS Pages</h1>
        <p class="text-secondary mb-0"><?= number_format($total) ?> pages total</p>
    </div>
    <a href="/admin/cms/pages/create" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> New Page
    </a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="GET" action="/admin/cms/pages" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary"
                   name="search" placeholder="Search title or slug..." value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Status</option>
                <?php foreach (['draft','published','archived'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Types</option>
                <?php foreach (['landing','static','custom','homepage'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-secondary w-100" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
        </div>
        <div class="col-md-2">
            <a href="/admin/cms/pages" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th><th>Slug</th><th>Type</th><th>Status</th><th>Sort</th><th>Updated</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $pg): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($pg['title'] ?? '-')) ?></td>
                    <td><code class="text-info small">/page/<?= e((string)($pg['slug'] ?? '')) ?></code></td>
                    <td><span class="badge text-bg-secondary"><?= e(ucfirst((string)($pg['page_type'] ?? '-'))) ?></span></td>
                    <td>
                        <?php $st = (string)($pg['status'] ?? 'draft'); ?>
                        <span class="badge text-bg-<?= $st === 'published' ? 'success' : ($st === 'draft' ? 'warning' : 'secondary') ?>">
                            <?= e(ucfirst($st)) ?>
                        </span>
                    </td>
                    <td><?= (int)($pg['sort_order'] ?? 0) ?></td>
                    <td class="small text-secondary"><?= e(substr((string)($pg['updated_at'] ?? '-'), 0, 10)) ?></td>
                    <td class="text-end">
                        <a href="/admin/cms/pages/edit?id=<?= (int)$pg['id'] ?>" class="btn btn-xs btn-outline-light me-1">Edit</a>
                        <?php if ($st === 'published'): ?>
                            <a href="/page?slug=<?= e((string)($pg['slug'] ?? '')) ?>" target="_blank" class="btn btn-xs btn-outline-info me-1">View</a>
                        <?php endif; ?>
                        <button class="btn btn-xs btn-outline-danger" onclick="deletePage(<?= (int)$pg['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No pages found.</td></tr>
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
function deletePage(id) {
    if (!confirm('Delete this page? This action cannot be undone.')) return;
    fetch('/admin/cms/pages/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: '<?= e($csrf) ?>'})
    }).then(r => r.json()).then(d => {
        if (d.ok) { window.location.href = d.redirect || '/admin/cms/pages'; }
        else alert(d.message || 'Error');
    });
}
</script>
