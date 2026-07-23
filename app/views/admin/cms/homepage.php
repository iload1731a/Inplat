<?php declare(strict_types=1); ?>
<?php
$sections     = is_array($sections     ?? null) ? $sections     : [];
$testimonials = is_array($testimonials ?? null) ? $testimonials : [];
$features     = is_array($features     ?? null) ? $features     : [];
$plans        = is_array($plans        ?? null) ? $plans        : [];
$recentPosts  = is_array($recentPosts  ?? null) ? $recentPosts  : [];
$csrf         = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-home me-2 text-info"></i>Homepage Builder</h1>
        <p class="text-secondary mb-0">Configure and reorder homepage sections.</p>
    </div>
    <a href="/" target="_blank" class="btn btn-sm btn-outline-info">
        <i class="fas fa-external-link-alt me-1"></i> Preview Homepage
    </a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<!-- Section Toggles & Order -->
<div class="glass rounded-4 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Section Order & Visibility</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="saveOrder()">
            <i class="fas fa-save me-1"></i> Save Order
        </button>
    </div>
    <div id="sectionList">
        <?php foreach ($sections as $sec): ?>
        <div class="d-flex align-items-center gap-3 glass rounded-3 p-3 mb-2 section-row" data-key="<?= e((string)($sec['section_key'] ?? '')) ?>">
            <i class="fas fa-grip-lines text-secondary" style="cursor:move"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold small"><?= e((string)($sec['section_title'] ?? '')) ?></div>
                <code class="text-secondary" style="font-size:.7rem"><?= e((string)($sec['section_key'] ?? '')) ?></code>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input section-toggle" type="checkbox"
                       data-key="<?= e((string)($sec['section_key'] ?? '')) ?>"
                       <?= (int)($sec['is_enabled'] ?? 0) ? 'checked' : '' ?>
                       onchange="toggleSection(this)">
                <label class="form-check-label small"><?= (int)($sec['is_enabled'] ?? 0) ? 'Enabled' : 'Disabled' ?></label>
            </div>
            <button class="btn btn-xs btn-outline-light" data-bs-toggle="collapse"
                    data-bs-target="#secEdit_<?= e((string)($sec['section_key'] ?? '')) ?>">
                <i class="fas fa-edit"></i>
            </button>
        </div>
        <!-- Inline edit panel -->
        <div class="collapse mb-2" id="secEdit_<?= e((string)($sec['section_key'] ?? '')) ?>">
            <div class="glass rounded-3 p-3 ms-4">
                <?php
                $secData = json_decode((string)($sec['section_data'] ?? '{}'), true) ?? [];
                $key     = (string)($sec['section_key'] ?? '');
                ?>
                <?php if ($key === 'hero'): ?>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Headline</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="headline" value="<?= e((string)($secData['headline'] ?? '')) ?>"
                               data-key="<?= e($key) ?>" placeholder="Trade Smarter">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Sub-headline</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="subheadline" value="<?= e((string)($secData['subheadline'] ?? '')) ?>"
                               data-key="<?= e($key) ?>" placeholder="Professional trading platform">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Primary CTA Text</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="cta_primary" value="<?= e((string)($secData['cta_primary'] ?? 'Start Trading')) ?>"
                               data-key="<?= e($key) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Primary CTA URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="cta_primary_url" value="<?= e((string)($secData['cta_primary_url'] ?? '/register')) ?>"
                               data-key="<?= e($key) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Background Image URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="bg_image" value="<?= e((string)($secData['bg_image'] ?? '')) ?>"
                               data-key="<?= e($key) ?>">
                    </div>
                </div>
                <?php elseif ($key === 'cta'): ?>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small text-secondary">CTA Headline</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="headline" value="<?= e((string)($secData['headline'] ?? 'Start Trading Today')) ?>"
                               data-key="<?= e($key) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Button Text</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="btn_text" value="<?= e((string)($secData['btn_text'] ?? 'Create Free Account')) ?>"
                               data-key="<?= e($key) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Sub-text</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary sec-data-input"
                               name="subtext" value="<?= e((string)($secData['subtext'] ?? '')) ?>"
                               data-key="<?= e($key) ?>">
                    </div>
                </div>
                <?php else: ?>
                <div class="text-secondary small">
                    This section is configured automatically from its respective module
                    (<?= e(ucfirst($key)) ?>). Toggle visibility above to show/hide on homepage.
                </div>
                <?php endif; ?>
                <?php if (in_array($key, ['hero', 'cta'], true)): ?>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="saveSectionData('<?= e($key) ?>')">
                        <i class="fas fa-save me-1"></i> Save Section
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Live preview summary -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <h6 class="mb-2"><i class="fas fa-users me-2 text-warning"></i>Testimonials</h6>
            <div class="text-secondary small"><?= count($testimonials) ?> active testimonials</div>
            <a href="/admin/cms/testimonials" class="btn btn-xs btn-outline-warning mt-2">Manage</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <h6 class="mb-2"><i class="fas fa-bolt me-2 text-primary"></i>Features</h6>
            <div class="text-secondary small"><?= count($features) ?> active features (home section)</div>
            <a href="/admin/cms/features" class="btn btn-xs btn-outline-primary mt-2">Manage</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <h6 class="mb-2"><i class="fas fa-tag me-2 text-success"></i>Pricing Plans</h6>
            <div class="text-secondary small"><?= count($plans) ?> active pricing plans</div>
            <a href="/admin/cms/pricing" class="btn btn-xs btn-outline-success mt-2">Manage</a>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function toggleSection(el) {
    const key = el.dataset.key;
    fetch('/admin/cms/homepage/section/save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ _token: csrf, section_key: key, is_enabled: el.checked ? 1 : 0, section_title: key })
    }).then(r => r.json()).then(d => {
        if (!d.ok) { el.checked = !el.checked; alert(d.message); }
        el.nextElementSibling.textContent = el.checked ? 'Enabled' : 'Disabled';
    });
}

function saveOrder() {
    const rows  = document.querySelectorAll('.section-row');
    const order = Array.from(rows).map(r => r.dataset.key);
    fetch('/admin/cms/homepage/reorder', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ _token: csrf, order })
    }).then(r => r.json()).then(d => {
        if (d.ok) { const b = event.target; b.textContent = '✓ Saved'; setTimeout(() => b.textContent = 'Save Order', 2000); }
        else alert(d.message);
    });
}

function saveSectionData(key) {
    const inputs = document.querySelectorAll(`.sec-data-input[data-key="${key}"]`);
    const data   = {};
    inputs.forEach(i => { data[i.name] = i.value; });

    fetch('/admin/cms/homepage/section/save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ _token: csrf, section_key: key, section_data: data, is_enabled: 1 })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const btn = event.target;
            btn.textContent = '✓ Saved!'; btn.classList.add('btn-success');
            setTimeout(() => { btn.textContent = 'Save Section'; btn.classList.remove('btn-success'); }, 2000);
        } else alert(d.message);
    });
}
</script>
