<?php declare(strict_types=1); ?>
<?php
$testimonials = is_array($testimonials ?? null) ? $testimonials : [];
$csrf         = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-star me-2 text-warning"></i>Testimonials</h1>
        <p class="text-secondary mb-0"><?= count($testimonials) ?> testimonials</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openModal(null)" data-bs-toggle="modal" data-bs-target="#testimonialModal">
        <i class="fas fa-plus me-1"></i> New Testimonial
    </button>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<div class="row g-3">
    <?php foreach ($testimonials as $t): ?>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3 h-100">
            <div class="d-flex align-items-start gap-3 mb-2">
                <?php if ($t['avatar'] ?? ''): ?>
                    <img src="<?= e((string)$t['avatar']) ?>" class="rounded-circle" style="width:48px;height:48px;object-fit:cover">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:48px;height:48px;flex-shrink:0">
                        <i class="fas fa-user text-light"></i>
                    </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e((string)($t['name'] ?? '-')) ?></div>
                    <div class="small text-secondary"><?= e((string)($t['title'] ?? '')) ?><?= ($t['company'] ?? '') ? ' · ' . e((string)$t['company']) : '' ?></div>
                    <div class="mt-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= (int)($t['rating'] ?? 5) ? 'text-warning' : 'text-secondary' ?>" style="font-size:.7rem"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="d-flex flex-column gap-1">
                    <?php if ((int)($t['is_featured'] ?? 0)): ?>
                        <span class="badge text-bg-warning"><i class="fas fa-star" style="font-size:.6rem"></i></span>
                    <?php endif; ?>
                    <span class="badge text-bg-<?= (int)($t['is_active'] ?? 0) ? 'success' : 'secondary' ?>" style="font-size:.6rem"><?= (int)($t['is_active'] ?? 0) ? 'On' : 'Off' ?></span>
                </div>
            </div>
            <p class="small text-secondary mb-3" style="font-style:italic">
                "<?= e(substr((string)($t['content'] ?? ''), 0, 200)) ?><?= strlen((string)($t['content'] ?? '')) > 200 ? '…' : '' ?>"
            </p>
            <div class="d-flex gap-1">
                <button class="btn btn-xs btn-outline-light flex-grow-1" onclick="openModal(<?= e(json_encode($t)) ?>)" data-bs-toggle="modal" data-bs-target="#testimonialModal">Edit</button>
                <button class="btn btn-xs btn-outline-danger" onclick="deleteTestimonial(<?= (int)$t['id'] ?>)">Del</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($testimonials === []): ?>
    <div class="col-12">
        <div class="glass rounded-4 p-5 text-center text-secondary">
            <i class="fas fa-star fa-2x mb-3 d-block"></i>
            No testimonials yet. Add your first one.
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="testimonialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="tModalTitle">Testimonial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Name *</label>
                        <input type="text" id="tName" class="form-control bg-dark text-light border-secondary">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Title / Role</label>
                        <input type="text" id="tTitle" class="form-control bg-dark text-light border-secondary">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Company</label>
                        <input type="text" id="tCompany" class="form-control bg-dark text-light border-secondary">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Platform Source</label>
                        <input type="text" id="tPlatform" class="form-control bg-dark text-light border-secondary" placeholder="trustpilot, google...">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Avatar URL</label>
                        <input type="text" id="tAvatar" class="form-control bg-dark text-light border-secondary">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Content *</label>
                        <textarea id="tContent" class="form-control bg-dark text-light border-secondary" rows="4"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Rating (1-5)</label>
                        <input type="number" id="tRating" class="form-control bg-dark text-light border-secondary" min="1" max="5" value="5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Sort Order</label>
                        <input type="number" id="tSort" class="form-control bg-dark text-light border-secondary" value="0">
                    </div>
                    <div class="col-md-4 d-flex gap-3 align-items-end pb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tFeatured">
                            <label class="form-check-label small" for="tFeatured">Featured</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tActive" checked>
                            <label class="form-check-label small" for="tActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTestimonial()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function openModal(t) {
    document.getElementById('tModalTitle').textContent = t ? 'Edit Testimonial' : 'New Testimonial';
    document.getElementById('tId').value       = t?.id ?? '';
    document.getElementById('tName').value     = t?.name ?? '';
    document.getElementById('tTitle').value    = t?.title ?? '';
    document.getElementById('tCompany').value  = t?.company ?? '';
    document.getElementById('tPlatform').value = t?.platform ?? '';
    document.getElementById('tAvatar').value   = t?.avatar ?? '';
    document.getElementById('tContent').value  = t?.content ?? '';
    document.getElementById('tRating').value   = t?.rating ?? 5;
    document.getElementById('tSort').value     = t?.sort_order ?? 0;
    document.getElementById('tFeatured').checked = t ? !!+t.is_featured : false;
    document.getElementById('tActive').checked   = t ? !!+t.is_active : true;
}
function saveTestimonial() {
    fetch('/admin/cms/testimonials/save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token: csrf, id: document.getElementById('tId').value,
            name: document.getElementById('tName').value, title: document.getElementById('tTitle').value,
            company: document.getElementById('tCompany').value, platform: document.getElementById('tPlatform').value,
            avatar: document.getElementById('tAvatar').value, content: document.getElementById('tContent').value,
            rating: document.getElementById('tRating').value, sort_order: document.getElementById('tSort').value,
            is_featured: document.getElementById('tFeatured').checked ? 1 : 0,
            is_active: document.getElementById('tActive').checked ? 1 : 0,
        })
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function deleteTestimonial(id) {
    if (!confirm('Delete?')) return;
    fetch('/admin/cms/testimonials/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
</script>
