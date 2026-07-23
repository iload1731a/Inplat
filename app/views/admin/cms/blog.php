<?php declare(strict_types=1); ?>
<?php
$items      = is_array($items      ?? null) ? $items      : [];
$total      = (int)($total         ?? 0);
$page       = (int)($page          ?? 1);
$totalPages = (int)($totalPages    ?? 1);
$categories = is_array($categories ?? null) ? $categories : [];
$filters    = is_array($filters    ?? null) ? $filters    : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-rss me-2 text-primary"></i>Blog &amp; News</h1>
        <p class="text-secondary mb-0"><?= number_format($total) ?> posts total</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/cms/blog/categories" class="btn btn-sm btn-outline-secondary">Categories</a>
        <a href="/admin/cms/blog/create" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> New Post
        </a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form method="GET" action="/admin/cms/blog" class="row g-2 align-items-end">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary"
                   placeholder="Search posts..." value="<?= e((string)($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Status</option>
                <?php foreach (['draft','published','archived','scheduled'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Types</option>
                <?php foreach (['blog','news','tutorial','guide'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="category_id" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)($filters['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e((string)$cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-secondary w-100" type="submit"><i class="fas fa-search"></i></button>
        </div>
        <div class="col-md-2">
            <a href="/admin/cms/blog" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th><th>Type</th><th>Category</th><th>Status</th>
                    <th>Views</th><th>Published</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $post): ?>
                <?php $st = (string)($post['status'] ?? 'draft'); ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ((int)($post['is_featured'] ?? 0)): ?>
                                <i class="fas fa-star text-warning" title="Featured"></i>
                            <?php endif; ?>
                            <?php if ((int)($post['is_pinned'] ?? 0)): ?>
                                <i class="fas fa-thumbtack text-info" title="Pinned"></i>
                            <?php endif; ?>
                            <span class="fw-semibold"><?= e((string)($post['title'] ?? '-')) ?></span>
                        </div>
                        <div class="small text-secondary"><?= e((string)($post['author_name'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge text-bg-secondary"><?= e(ucfirst((string)($post['post_type'] ?? '-'))) ?></span></td>
                    <td class="small"><?= e((string)($post['category_name'] ?? '-')) ?></td>
                    <td>
                        <span class="badge text-bg-<?= $st === 'published' ? 'success' : ($st === 'scheduled' ? 'info' : ($st === 'draft' ? 'warning' : 'secondary')) ?>">
                            <?= e(ucfirst($st)) ?>
                        </span>
                    </td>
                    <td><?= number_format((int)($post['view_count'] ?? 0)) ?></td>
                    <td class="small text-secondary"><?= e(substr((string)($post['published_at'] ?? '-'), 0, 10)) ?></td>
                    <td class="text-end">
                        <a href="/admin/cms/blog/edit?id=<?= (int)$post['id'] ?>" class="btn btn-xs btn-outline-light me-1">Edit</a>
                        <?php if ($st === 'published'): ?>
                            <a href="/blog/post?slug=<?= e((string)($post['slug'] ?? '')) ?>" target="_blank" class="btn btn-xs btn-outline-info me-1">View</a>
                        <?php endif; ?>
                        <button class="btn btn-xs btn-outline-danger" onclick="deletePost(<?= (int)$post['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No posts found.</td></tr>
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
function deletePost(id) {
    if (!confirm('Delete this post?')) return;
    fetch('/admin/cms/blog/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: '<?= e($csrf) ?>'})
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
        else alert(d.message || 'Error');
    });
}
</script>
