<?php declare(strict_types=1); ?>
<?php
$post       = is_array($post       ?? null) ? $post       : null;
$categories = is_array($categories ?? null) ? $categories : [];
$csrf       = \App\Libraries\Csrf::token();
$isEdit     = $post !== null;
$postId     = $isEdit ? (int)$post['id'] : 0;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="fas fa-pen me-2 text-primary"></i><?= $isEdit ? 'Edit Post' : 'New Post' ?>
        </h1>
    </div>
    <a href="/admin/cms/blog" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<form id="postForm">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $postId ?>"><?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="glass rounded-4 p-4 mb-4">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Post Title *</label>
                    <input type="text" name="title" class="form-control bg-dark text-light border-secondary"
                           value="<?= e((string)($post['title'] ?? '')) ?>" required id="titleInput">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Slug</label>
                    <input type="text" name="slug" id="slugInput" class="form-control bg-dark text-light border-secondary"
                           value="<?= e((string)($post['slug'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Excerpt</label>
                    <textarea name="excerpt" class="form-control bg-dark text-light border-secondary" rows="3"><?= e((string)($post['excerpt'] ?? '')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Content (HTML)</label>
                    <textarea name="content" class="form-control bg-dark text-light border-secondary" rows="20"
                              style="font-family:monospace;font-size:.85rem"><?= e((string)($post['content'] ?? '')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Tags (comma-separated)</label>
                    <input type="text" name="tags" class="form-control bg-dark text-light border-secondary"
                           value="<?= e((string)($post['tags'] ?? '')) ?>" placeholder="bitcoin, trading, analysis">
                </div>
            </div>
            <!-- SEO -->
            <div class="glass rounded-4 p-4">
                <h6 class="mb-3"><i class="fas fa-search me-2 text-warning"></i>SEO</h6>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small text-secondary">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($post['meta_title'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Meta Description</label>
                        <textarea name="meta_description" class="form-control bg-dark text-light border-secondary" rows="2"><?= e((string)($post['meta_description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">OG Title</label>
                        <input type="text" name="og_title" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($post['og_title'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">OG Image URL</label>
                        <input type="text" name="og_image" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($post['og_image'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Canonical URL</label>
                        <input type="text" name="canonical_url" class="form-control bg-dark text-light border-secondary"
                               value="<?= e((string)($post['canonical_url'] ?? '')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-xl-4">
            <div class="glass rounded-4 p-4 mb-4">
                <h6 class="mb-3">Post Settings</h6>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Status</label>
                    <select name="status" class="form-select bg-dark text-light border-secondary">
                        <?php foreach (['draft'=>'Draft','published'=>'Published','scheduled'=>'Scheduled','archived'=>'Archived'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($post['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Post Type</label>
                    <select name="post_type" class="form-select bg-dark text-light border-secondary">
                        <?php foreach (['blog'=>'Blog','news'=>'News','tutorial'=>'Tutorial','guide'=>'Guide'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($post['post_type'] ?? 'blog') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Category</label>
                    <select name="category_id" class="form-select bg-dark text-light border-secondary">
                        <option value="">— Uncategorised —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)($post['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e((string)$cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Featured Image URL</label>
                    <input type="text" name="featured_image" class="form-control bg-dark text-light border-secondary"
                           value="<?= e((string)($post['featured_image'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Schedule Date</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control bg-dark text-light border-secondary"
                           value="<?= e(str_replace(' ', 'T', substr((string)($post['scheduled_at'] ?? ''), 0, 16))) ?>">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                                   <?= (int)($post['is_featured'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small">Featured</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned" value="1"
                                   <?= (int)($post['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small">Pinned</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_comments" value="1"
                                   <?= (int)($post['allow_comments'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label small">Allow Comments</label>
                        </div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" onclick="savePost()">
                        <i class="fas fa-save me-1"></i> Save Post
                    </button>
                    <?php if ($isEdit && ($post['status'] ?? '') === 'published'): ?>
                    <a href="/blog/post?slug=<?= e((string)($post['slug'] ?? '')) ?>" target="_blank" class="btn btn-outline-info">
                        <i class="fas fa-external-link-alt me-1"></i> View Live
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('titleInput')?.addEventListener('input', function() {
    const slug = document.getElementById('slugInput');
    if (!slug.dataset.manual) {
        slug.value = this.value.toLowerCase().replace(/[^a-z0-9\s]/g,'').replace(/\s+/g,'-');
    }
});
document.getElementById('slugInput')?.addEventListener('input', function() { this.dataset.manual = '1'; });

function savePost() {
    const form = document.getElementById('postForm');
    const fd   = new FormData(form);
    const data = {};
    for (const [k, v] of fd.entries()) data[k] = v;
    const url  = <?= $isEdit ? "'/admin/cms/blog/update'" : "'/admin/cms/blog/create'" ?>;

    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.ok) window.location.href = d.redirect || '/admin/cms/blog';
        else alert(d.message || 'Error');
    });
}
</script>
