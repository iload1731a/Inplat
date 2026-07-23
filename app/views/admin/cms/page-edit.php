<?php declare(strict_types=1); ?>
<?php
$page     = is_array($page     ?? null) ? $page     : null;
$sections = is_array($sections ?? null) ? $sections : [];
$csrf     = \App\Libraries\Csrf::token();
$isEdit   = $page !== null;
$pageId   = $isEdit ? (int)$page['id'] : 0;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="fas fa-file-alt me-2 text-info"></i><?= $isEdit ? 'Edit Page' : 'Create Page' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="text-secondary mb-0">Last updated: <?= e(substr((string)($page['updated_at'] ?? '-'), 0, 16)) ?></p>
        <?php endif; ?>
    </div>
    <a href="/admin/cms/pages" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Pages
    </a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<form id="pageForm">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $pageId ?>"><?php endif; ?>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-xl-8">
            <div class="glass rounded-4 p-4 mb-4">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Page Title *</label>
                    <input type="text" name="title" class="form-control bg-dark text-light border-secondary"
                           value="<?= e((string)($page['title'] ?? '')) ?>" required id="titleInput">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Slug</label>
                    <div class="input-group">
                        <span class="input-group-text bg-secondary text-light border-secondary">/page/</span>
                        <input type="text" name="slug" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($page['slug'] ?? '')) ?>" id="slugInput">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Excerpt</label>
                    <textarea name="excerpt" class="form-control bg-dark text-light border-secondary" rows="2"><?= e((string)($page['excerpt'] ?? '')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Content (HTML)</label>
                    <textarea name="content" id="pageContent" class="form-control bg-dark text-light border-secondary" rows="18"
                              style="font-family:monospace;font-size:.85rem"><?= e((string)($page['content'] ?? '')) ?></textarea>
                </div>
            </div>

            <!-- SEO -->
            <div class="glass rounded-4 p-4 mb-4">
                <h6 class="mb-3"><i class="fas fa-search me-2 text-warning"></i>SEO Settings</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small text-secondary">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($page['meta_title'] ?? '')) ?>" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Meta Description</label>
                        <textarea name="meta_description" class="form-control bg-dark text-light border-secondary" rows="2"><?= e((string)($page['meta_description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($page['meta_keywords'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">OG Title</label>
                        <input type="text" name="og_title" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($page['og_title'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">OG Image URL</label>
                        <input type="text" name="og_image" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($page['og_image'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">OG Description</label>
                        <textarea name="og_description" class="form-control bg-dark text-light border-secondary" rows="2"><?= e((string)($page['og_description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Canonical URL</label>
                        <input type="text" name="canonical_url" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($page['canonical_url'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="no_index" value="1" id="noIndex"
                                   <?= (int)($page['no_index'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="noIndex">No Index (noindex robots)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-xl-4">
            <div class="glass rounded-4 p-4 mb-4">
                <h6 class="mb-3">Page Settings</h6>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Status</label>
                    <select name="status" class="form-select bg-dark text-light border-secondary">
                        <?php foreach (['draft'=>'Draft','published'=>'Published','archived'=>'Archived'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($page['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Page Type</label>
                    <select name="page_type" class="form-select bg-dark text-light border-secondary">
                        <?php foreach (['static'=>'Static','landing'=>'Landing Page','custom'=>'Custom','homepage'=>'Homepage'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($page['page_type'] ?? 'static') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Layout</label>
                    <select name="layout" class="form-select bg-dark text-light border-secondary">
                        <?php foreach (['default'=>'Default','wide'=>'Wide','minimal'=>'Minimal','landing'=>'Landing'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($page['layout'] ?? 'default') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control bg-dark text-light border-secondary"
                           value="<?= (int)($page['sort_order'] ?? 0) ?>" min="0">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Featured Image URL</label>
                    <input type="text" name="featured_image" class="form-control bg-dark text-light border-secondary"
                           value="<?= e((string)($page['featured_image'] ?? '')) ?>">
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="button" class="btn btn-primary" onclick="savePage()">
                        <i class="fas fa-save me-1"></i> Save Page
                    </button>
                    <?php if ($isEdit && ($page['status'] ?? '') === 'published'): ?>
                    <a href="/page?slug=<?= e((string)($page['slug'] ?? '')) ?>" target="_blank" class="btn btn-outline-info">
                        <i class="fas fa-external-link-alt me-1"></i> View Live
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Auto-generate slug from title
document.getElementById('titleInput')?.addEventListener('input', function() {
    const slugInput = document.getElementById('slugInput');
    if (!slugInput.value || slugInput.dataset.manual !== '1') {
        slugInput.value = this.value.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }
});
document.getElementById('slugInput')?.addEventListener('input', function() {
    this.dataset.manual = '1';
});

function savePage() {
    const form  = document.getElementById('pageForm');
    const data  = Object.fromEntries(new FormData(form).entries());
    const url   = <?= $isEdit ? "'/admin/cms/pages/update'" : "'/admin/cms/pages/create'" ?>;

    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.ok) { window.location.href = d.redirect || '/admin/cms/pages'; }
        else alert(d.message || 'Error saving page.');
    });
}
</script>
