<?php declare(strict_types=1); ?>
<?php
$plans = is_array($plans ?? null) ? $plans : [];
$csrf  = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-tag me-2 text-success"></i>Pricing Plans</h1>
        <p class="text-secondary mb-0"><?= count($plans) ?> plans configured</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#planModal" onclick="openModal(null)">
        <i class="fas fa-plus me-1"></i> New Plan
    </button>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<!-- Plan Preview Cards -->
<div class="row g-3 mb-4">
    <?php foreach ($plans as $plan): ?>
    <div class="col-md-4">
        <div class="glass rounded-4 p-4 h-100 position-relative <?= (int)($plan['is_featured'] ?? 0) ? 'border border-warning' : '' ?>">
            <?php if ($plan['badge'] ?? ''): ?>
                <span class="position-absolute top-0 start-50 translate-middle badge text-bg-<?= e((string)($plan['badge_color'] ?? 'warning')) ?>">
                    <?= e((string)$plan['badge']) ?>
                </span>
            <?php endif; ?>
            <div class="text-center mb-3 mt-2">
                <h5 class="mb-1"><?= e((string)($plan['name'] ?? '-')) ?></h5>
                <div class="text-secondary small"><?= e((string)($plan['description'] ?? '')) ?></div>
                <div class="mt-2">
                    <span class="h2 fw-bold">$<?= number_format((float)($plan['price_monthly'] ?? 0), 2) ?></span>
                    <span class="text-secondary">/mo</span>
                </div>
                <?php if ((float)($plan['price_yearly'] ?? 0) > 0): ?>
                    <div class="small text-success">$<?= number_format((float)$plan['price_yearly'], 2) ?>/yr</div>
                <?php endif; ?>
            </div>
            <?php
            $features = json_decode((string)($plan['features'] ?? '[]'), true);
            if (is_array($features) && $features !== []):
            ?>
            <ul class="list-unstyled mb-3">
                <?php foreach (array_slice($features, 0, 6) as $feat): ?>
                    <li class="small mb-1"><i class="fas fa-check text-success me-2"></i><?= e((string)$feat) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <div class="d-flex gap-1 mt-auto">
                <span class="badge text-bg-<?= (int)($plan['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($plan['is_active'] ?? 0) ? 'Active' : 'Inactive' ?></span>
                <button class="btn btn-xs btn-outline-light ms-auto me-1" data-bs-toggle="modal" data-bs-target="#planModal" onclick="openModal(<?= e(json_encode($plan)) ?>)">Edit</button>
                <button class="btn btn-xs btn-outline-danger" onclick="deletePlan(<?= (int)$plan['id'] ?>)">Del</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($plans === []): ?>
    <div class="col-12">
        <div class="glass rounded-4 p-5 text-center text-secondary">No pricing plans configured.</div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="planModalTitle">Pricing Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="planId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Plan Name *</label>
                        <input type="text" id="planName" class="form-control bg-dark text-light border-secondary">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Slug</label>
                        <input type="text" id="planSlug" class="form-control bg-dark text-light border-secondary">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Description</label>
                        <textarea id="planDesc" class="form-control bg-dark text-light border-secondary" rows="2"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Monthly Price</label>
                        <input type="number" id="planMonthly" class="form-control bg-dark text-light border-secondary" step="0.01" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Yearly Price</label>
                        <input type="number" id="planYearly" class="form-control bg-dark text-light border-secondary" step="0.01" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Currency</label>
                        <input type="text" id="planCurrency" class="form-control bg-dark text-light border-secondary" value="USD">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Features (one per line)</label>
                        <textarea id="planFeatures" class="form-control bg-dark text-light border-secondary" rows="6" placeholder="Basic trading access&#10;Advanced charts&#10;API access"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Badge Text</label>
                        <input type="text" id="planBadge" class="form-control bg-dark text-light border-secondary" placeholder="Most Popular">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Badge Color</label>
                        <select id="planBadgeColor" class="form-select bg-dark text-light border-secondary">
                            <?php foreach (['warning','primary','success','danger','info','secondary'] as $c): ?>
                                <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Sort Order</label>
                        <input type="number" id="planSort" class="form-control bg-dark text-light border-secondary" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">CTA Button Text</label>
                        <input type="text" id="planCta" class="form-control bg-dark text-light border-secondary" value="Get Started">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">CTA URL</label>
                        <input type="text" id="planCtaUrl" class="form-control bg-dark text-light border-secondary" value="/register">
                    </div>
                    <div class="col-12 d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="planFeatured">
                            <label class="form-check-label small" for="planFeatured">Featured Plan</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="planActive" checked>
                            <label class="form-check-label small" for="planActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePlan()">Save Plan</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function openModal(p) {
    document.getElementById('planModalTitle').textContent = p ? 'Edit Plan' : 'New Plan';
    document.getElementById('planId').value       = p?.id ?? '';
    document.getElementById('planName').value     = p?.name ?? '';
    document.getElementById('planSlug').value     = p?.slug ?? '';
    document.getElementById('planDesc').value     = p?.description ?? '';
    document.getElementById('planMonthly').value  = p?.price_monthly ?? 0;
    document.getElementById('planYearly').value   = p?.price_yearly ?? 0;
    document.getElementById('planCurrency').value = p?.currency ?? 'USD';
    const feats = p?.features ? (typeof p.features === 'string' ? JSON.parse(p.features) : p.features) : [];
    document.getElementById('planFeatures').value = Array.isArray(feats) ? feats.join('\n') : '';
    document.getElementById('planBadge').value    = p?.badge ?? '';
    document.getElementById('planBadgeColor').value = p?.badge_color ?? 'warning';
    document.getElementById('planSort').value     = p?.sort_order ?? 0;
    document.getElementById('planCta').value      = p?.cta_text ?? 'Get Started';
    document.getElementById('planCtaUrl').value   = p?.cta_url ?? '/register';
    document.getElementById('planFeatured').checked = p ? !!+p.is_featured : false;
    document.getElementById('planActive').checked   = p ? !!+p.is_active : true;
}
function savePlan() {
    const featsRaw = document.getElementById('planFeatures').value;
    const feats    = featsRaw.split('\n').map(s => s.trim()).filter(Boolean);
    fetch('/admin/cms/pricing/save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token: csrf, id: document.getElementById('planId').value,
            name: document.getElementById('planName').value, slug: document.getElementById('planSlug').value,
            description: document.getElementById('planDesc').value,
            price_monthly: document.getElementById('planMonthly').value,
            price_yearly: document.getElementById('planYearly').value,
            currency: document.getElementById('planCurrency').value,
            features: feats, badge: document.getElementById('planBadge').value,
            badge_color: document.getElementById('planBadgeColor').value,
            sort_order: document.getElementById('planSort').value,
            cta_text: document.getElementById('planCta').value, cta_url: document.getElementById('planCtaUrl').value,
            is_featured: document.getElementById('planFeatured').checked ? 1 : 0,
            is_active: document.getElementById('planActive').checked ? 1 : 0,
        })
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function deletePlan(id) {
    if (!confirm('Delete plan?')) return;
    fetch('/admin/cms/pricing/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
</script>
