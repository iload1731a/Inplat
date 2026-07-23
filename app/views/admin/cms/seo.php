<?php declare(strict_types=1); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$rows     = is_array($rows     ?? null) ? $rows     : [];
$csrf     = \App\Libraries\Csrf::token();

// Ensure defaults
$allContexts = ['global', 'home', 'blog', 'trading', 'faq', 'contact'];
$indexed     = $settings;
foreach ($allContexts as $ctx) {
    if (!isset($indexed[$ctx])) {
        $indexed[$ctx] = ['context' => $ctx, 'meta_title' => '', 'meta_description' => '', 'meta_keywords' => '',
            'og_title' => '', 'og_description' => '', 'og_image' => '', 'twitter_card' => 'summary_large_image',
            'robots' => 'index,follow', 'canonical_base' => '', 'schema_markup' => '', 'custom_head' => ''];
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-search me-2 text-warning"></i>SEO Management</h1>
        <p class="text-secondary mb-0">Configure meta tags, Open Graph, robots and schema markup per page context.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<ul class="nav nav-tabs border-secondary mb-4" role="tablist">
    <?php foreach ($allContexts as $i => $ctx): ?>
        <li class="nav-item">
            <button class="nav-link text-light <?= $i === 0 ? 'active' : '' ?>"
                    data-bs-toggle="tab" data-bs-target="#seoTab_<?= $ctx ?>">
                <?= e(ucfirst($ctx)) ?>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
<?php foreach ($allContexts as $i => $ctx):
    $s = $indexed[$ctx];
?>
<div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="seoTab_<?= $ctx ?>">
    <div class="glass rounded-4 p-4">
        <h6 class="mb-3 text-warning"><i class="fas fa-search me-2"></i><?= e(ucfirst($ctx)) ?> SEO Settings</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small text-secondary">Meta Title</label>
                <input type="text" class="form-control bg-dark text-light border-secondary seo-meta-title-<?= $ctx ?>"
                       value="<?= e((string)($s['meta_title'] ?? '')) ?>" maxlength="255">
                <div class="text-secondary" style="font-size:.7rem">Recommended: 50-60 chars</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary">Robots</label>
                <select class="form-select bg-dark text-light border-secondary seo-robots-<?= $ctx ?>">
                    <?php foreach (['index,follow','noindex,follow','index,nofollow','noindex,nofollow'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($s['robots'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label small text-secondary">Meta Description</label>
                <textarea class="form-control bg-dark text-light border-secondary seo-meta-desc-<?= $ctx ?>" rows="2"><?= e((string)($s['meta_description'] ?? '')) ?></textarea>
                <div class="text-secondary" style="font-size:.7rem">Recommended: 120-160 chars</div>
            </div>
            <div class="col-12">
                <label class="form-label small text-secondary">Meta Keywords</label>
                <input type="text" class="form-control bg-dark text-light border-secondary seo-meta-kw-<?= $ctx ?>"
                       value="<?= e((string)($s['meta_keywords'] ?? '')) ?>" placeholder="keyword1, keyword2, keyword3">
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary">OG Title</label>
                <input type="text" class="form-control bg-dark text-light border-secondary seo-og-title-<?= $ctx ?>"
                       value="<?= e((string)($s['og_title'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary">OG Image URL</label>
                <input type="text" class="form-control bg-dark text-light border-secondary seo-og-image-<?= $ctx ?>"
                       value="<?= e((string)($s['og_image'] ?? '')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label small text-secondary">OG Description</label>
                <textarea class="form-control bg-dark text-light border-secondary seo-og-desc-<?= $ctx ?>" rows="2"><?= e((string)($s['og_description'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary">Twitter Card Type</label>
                <select class="form-select bg-dark text-light border-secondary seo-twitter-<?= $ctx ?>">
                    <?php foreach (['summary_large_image','summary','app','player'] as $tc): ?>
                        <option value="<?= $tc ?>" <?= ($s['twitter_card'] ?? '') === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary">Canonical Base URL</label>
                <input type="text" class="form-control bg-dark text-light border-secondary seo-canonical-<?= $ctx ?>"
                       value="<?= e((string)($s['canonical_base'] ?? '')) ?>" placeholder="https://yourdomain.com">
            </div>
            <div class="col-12">
                <label class="form-label small text-secondary">JSON-LD Schema Markup</label>
                <textarea class="form-control bg-dark text-light border-secondary seo-schema-<?= $ctx ?>" rows="4"
                          style="font-family:monospace;font-size:.8rem"><?= e((string)($s['schema_markup'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small text-secondary">Custom Head Tags</label>
                <textarea class="form-control bg-dark text-light border-secondary seo-head-<?= $ctx ?>" rows="3"
                          style="font-family:monospace;font-size:.8rem"><?= e((string)($s['custom_head'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" onclick="saveSeo('<?= $ctx ?>')">
                    <i class="fas fa-save me-1"></i> Save <?= ucfirst($ctx) ?> SEO Settings
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function saveSeo(ctx) {
    const get = (sel) => document.querySelector(`.${sel}`)?.value ?? '';
    fetch('/admin/cms/seo/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token: csrf, context: ctx,
            meta_title:       get(`seo-meta-title-${ctx}`),
            meta_description: get(`seo-meta-desc-${ctx}`),
            meta_keywords:    get(`seo-meta-kw-${ctx}`),
            og_title:         get(`seo-og-title-${ctx}`),
            og_description:   get(`seo-og-desc-${ctx}`),
            og_image:         get(`seo-og-image-${ctx}`),
            twitter_card:     get(`seo-twitter-${ctx}`),
            robots:           get(`seo-robots-${ctx}`),
            canonical_base:   get(`seo-canonical-${ctx}`),
            schema_markup:    get(`seo-schema-${ctx}`),
            custom_head:      get(`seo-head-${ctx}`),
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const btn = event.target;
            btn.textContent = '✓ Saved!'; btn.classList.add('btn-success');
            setTimeout(() => { btn.textContent = `Save ${ctx.charAt(0).toUpperCase() + ctx.slice(1)} SEO Settings`; btn.classList.remove('btn-success'); }, 2000);
        } else alert(d.message || 'Error saving SEO settings.');
    });
}
</script>
