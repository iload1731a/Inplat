<?php declare(strict_types=1); ?>
<?php
$categories = is_array($categories ?? null) ? $categories : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-rss me-2 text-primary"></i>Blog Categories</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/cms/blog" class="btn btn-sm btn-outline-secondary">← Back to Posts</a>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="openCatModal(null)">
            <i class="fas fa-plus me-1"></i> New Category
        </button>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Slug</th><th>Posts</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($cat['name'] ?? '-')) ?></td>
                    <td><code class="small text-info"><?= e((string)($cat['slug'] ?? '')) ?></code></td>
                    <td><?= number_format((int)($cat['post_count'] ?? 0)) ?></td>
                    <td><span class="badge text-bg-<?= (int)($cat['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($cat['is_active'] ?? 0) ? 'Active' : 'Off' ?></span></td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1" onclick="openCatModal(<?= e(json_encode($cat)) ?>)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteCat(<?= (int)$cat['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($categories === []): ?>
                <tr><td colspan="5" class="text-center text-secondary py-4">No categories yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="catModalTitle">Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="catId">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Name *</label>
                    <input type="text" id="catName" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Slug</label>
                    <input type="text" id="catSlug" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Description</label>
                    <textarea id="catDesc" class="form-control bg-dark text-light border-secondary" rows="2"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-secondary">Sort Order</label>
                        <input type="number" id="catSort" class="form-control bg-dark text-light border-secondary" value="0">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="catActive" checked>
                            <label class="form-check-label small" for="catActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCat()">Save Category</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function openCatModal(cat) {
    document.getElementById('catModalTitle').textContent = cat ? 'Edit Category' : 'New Category';
    document.getElementById('catId').value    = cat?.id    ?? '';
    document.getElementById('catName').value  = cat?.name  ?? '';
    document.getElementById('catSlug').value  = cat?.slug  ?? '';
    document.getElementById('catDesc').value  = cat?.description ?? '';
    document.getElementById('catSort').value  = cat?.sort_order  ?? 0;
    document.getElementById('catActive').checked = cat ? !!+cat.is_active : true;
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
function saveCat() {
    fetch('/admin/cms/blog/categories/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token:   csrf,
            id:       document.getElementById('catId').value,
            name:     document.getElementById('catName').value,
            slug:     document.getElementById('catSlug').value,
            description: document.getElementById('catDesc').value,
            sort_order: document.getElementById('catSort').value,
            is_active:  document.getElementById('catActive').checked ? 1 : 0,
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
        else alert(d.message || 'Error');
    });
}
function deleteCat(id) {
    if (!confirm('Delete this category?')) return;
    fetch('/admin/cms/blog/categories/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
        else alert(d.message || 'Error');
    });
}
</script>
