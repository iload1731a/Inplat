<?php declare(strict_types=1); ?>
<?php
$features = is_array($features ?? null) ? $features : [];
$csrf     = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-bolt me-2 text-warning"></i>Platform Features</h1>
        <p class="text-secondary mb-0"><?= count($features) ?> features</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#featureModal" onclick="openModal(null)">
        <i class="fas fa-plus me-1"></i> New Feature
    </button>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>Icon</th><th>Title</th><th>Section</th><th>Badge</th><th>Sort</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($features as $f): ?>
                <tr>
                    <td><i class="fas <?= e((string)($f['icon'] ?? 'fa-star')) ?> text-warning"></i></td>
                    <td>
                        <div class="fw-semibold"><?= e((string)($f['title'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e(substr((string)($f['description'] ?? ''), 0, 60)) ?><?= strlen((string)($f['description'] ?? '')) > 60 ? '…' : '' ?></div>
                    </td>
                    <td><span class="badge text-bg-secondary"><?= e((string)($f['section'] ?? '-')) ?></span></td>
                    <td>
                        <?php if ($f['badge'] ?? ''): ?>
                            <span class="badge text-bg-<?= e((string)($f['badge_color'] ?? 'primary')) ?>"><?= e((string)$f['badge']) ?></span>
                        <?php else: ?>
                            <span class="text-secondary small">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)($f['sort_order'] ?? 0) ?></td>
                    <td><span class="badge text-bg-<?= (int)($f['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($f['is_active'] ?? 0) ? 'Yes' : 'No' ?></span></td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1" data-bs-toggle="modal" data-bs-target="#featureModal" onclick="openModal(<?= e(json_encode($f)) ?>)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteFeature(<?= (int)$f['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($features === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No features yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="featureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="fModalTitle">Feature</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fId">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Section</label>
                    <input type="text" id="fSection" class="form-control bg-dark text-light border-secondary" placeholder="home, trading, about...">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">FontAwesome Icon</label>
                    <input type="text" id="fIcon" class="form-control bg-dark text-light border-secondary" placeholder="fa-chart-line">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Title *</label>
                    <input type="text" id="fTitle" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Description *</label>
                    <textarea id="fDesc" class="form-control bg-dark text-light border-secondary" rows="3"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-secondary">Badge Text</label>
                        <input type="text" id="fBadge" class="form-control bg-dark text-light border-secondary" placeholder="New, Popular...">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-secondary">Badge Color</label>
                        <select id="fBadgeColor" class="form-select bg-dark text-light border-secondary">
                            <?php foreach (['primary','success','warning','danger','info','secondary'] as $c): ?>
                                <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-secondary">Sort Order</label>
                        <input type="number" id="fSort" class="form-control bg-dark text-light border-secondary" value="0">
                    </div>
                    <div class="col-6 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fActive" checked>
                            <label class="form-check-label small" for="fActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveFeature()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function openModal(f) {
    document.getElementById('fModalTitle').textContent = f ? 'Edit Feature' : 'New Feature';
    document.getElementById('fId').value          = f?.id ?? '';
    document.getElementById('fSection').value     = f?.section ?? 'home';
    document.getElementById('fIcon').value        = f?.icon ?? 'fa-star';
    document.getElementById('fTitle').value       = f?.title ?? '';
    document.getElementById('fDesc').value        = f?.description ?? '';
    document.getElementById('fBadge').value       = f?.badge ?? '';
    document.getElementById('fBadgeColor').value  = f?.badge_color ?? 'primary';
    document.getElementById('fSort').value        = f?.sort_order ?? 0;
    document.getElementById('fActive').checked    = f ? !!+f.is_active : true;
}
function saveFeature() {
    fetch('/admin/cms/features/save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token: csrf, id: document.getElementById('fId').value,
            section: document.getElementById('fSection').value, icon: document.getElementById('fIcon').value,
            title: document.getElementById('fTitle').value, description: document.getElementById('fDesc').value,
            badge: document.getElementById('fBadge').value, badge_color: document.getElementById('fBadgeColor').value,
            sort_order: document.getElementById('fSort').value,
            is_active: document.getElementById('fActive').checked ? 1 : 0,
        })
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function deleteFeature(id) {
    if (!confirm('Delete feature?')) return;
    fetch('/admin/cms/features/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
</script>
