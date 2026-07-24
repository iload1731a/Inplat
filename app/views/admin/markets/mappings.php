<?php declare(strict_types=1); ?>
<?php
$provider = is_array($provider ?? null) ? $provider : null;
$mappings = is_array($mappings ?? null) ? $mappings : [];
$csrf     = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Asset Mappings<?= $provider ? ' — ' . e((string)$provider['name']) : '' ?></h1>
        <p class="text-secondary mb-0">Map internal currencies to this provider's external identifiers.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/markets/providers" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Providers</a>
        <?php if ($provider): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMappingModal">
            <i class="fas fa-plus me-1"></i>Add Mapping
        </button>
        <?php endif; ?>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!$provider): ?>
<div class="alert alert-warning">Please select a provider from the <a href="/admin/markets/providers" class="alert-link">Providers page</a>.</div>
<?php else: ?>

<!-- Provider Info -->
<div class="glass rounded-4 p-3 mb-4">
    <div class="row g-3">
        <div class="col-md-3"><span class="text-secondary small">Name:</span> <span class="fw-semibold"><?= e((string)$provider['name']) ?></span></div>
        <div class="col-md-3"><span class="text-secondary small">Code:</span> <code class="text-info"><?= e((string)$provider['provider_code']) ?></code></div>
        <div class="col-md-3"><span class="text-secondary small">Type:</span> <?= e((string)$provider['provider_type']) ?></div>
        <div class="col-md-3"><span class="text-secondary small">Auth:</span> <?= e((string)$provider['auth_type']) ?></div>
    </div>
</div>

<!-- Mappings Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="mappingsTable">
            <thead><tr>
                <th>Currency</th><th>External ID</th><th>External Symbol</th>
                <th>External Slug</th><th>Status</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($mappings as $m): ?>
                <tr>
                    <td>
                        <span class="fw-semibold"><?= e((string)$m['currency_code']) ?></span>
                        <span class="text-secondary small ms-1"><?= e((string)($m['currency_name'] ?? '')) ?></span>
                    </td>
                    <td class="small"><?= e((string)($m['external_id'] ?? '—')) ?></td>
                    <td class="small"><?= e((string)($m['external_symbol'] ?? '—')) ?></td>
                    <td class="small"><?= e((string)($m['external_slug'] ?? '—')) ?></td>
                    <td>
                        <span class="badge text-bg-<?= (int)($m['is_active'] ?? 0) ? 'success' : 'secondary' ?>">
                            <?= (int)($m['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1"
                                onclick="openEditMapping(<?= json_encode($m) ?>)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger"
                                onclick="deleteMapping(<?= (int)$m['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$mappings): ?>
                <tr><td colspan="6" class="text-center text-secondary">No mappings configured for this provider.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Mapping Modal -->
<div class="modal fade" id="addMappingModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/markets/mappings/save" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="provider_id" value="<?= (int)$provider['id'] ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add Asset Mapping</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Currency ID <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" name="currency_id" placeholder="Internal currency ID" required min="1">
                    </div>
                    <div class="col-md-6"><label class="form-label">External ID</label>
                        <input class="form-control" type="text" name="external_id" placeholder="e.g. 1 (CMC BTC)"></div>
                    <div class="col-md-6"><label class="form-label">External Symbol</label>
                        <input class="form-control" type="text" name="external_symbol" placeholder="e.g. BTC"></div>
                    <div class="col-md-6"><label class="form-label">External Slug</label>
                        <input class="form-control" type="text" name="external_slug" placeholder="e.g. bitcoin"></div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="addMapActive" checked>
                            <label class="form-check-label" for="addMapActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Mapping</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Mapping Modal -->
<div class="modal fade" id="editMappingModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/markets/mappings/save" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="provider_id" value="<?= (int)$provider['id'] ?>">
            <input type="hidden" name="currency_id" id="editMapCurrId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Mapping — <span id="editMapCurrCode"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">External ID</label>
                        <input class="form-control" type="text" name="external_id" id="editMapExtId"></div>
                    <div class="col-md-6"><label class="form-label">External Symbol</label>
                        <input class="form-control" type="text" name="external_symbol" id="editMapExtSym"></div>
                    <div class="col-md-6"><label class="form-label">External Slug</label>
                        <input class="form-control" type="text" name="external_slug" id="editMapExtSlug"></div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editMapActive">
                            <label class="form-check-label" for="editMapActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<form id="deleteMappingForm" data-ajax="true" action="/admin/markets/mappings/delete" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="mapping_id" id="deleteMappingId">
    <input type="hidden" name="provider_id" value="<?= (int)$provider['id'] ?>">
</form>

<script>
function openEditMapping(m) {
    document.getElementById('editMapCurrId').value    = m.currency_id;
    document.getElementById('editMapCurrCode').textContent = m.currency_code || '';
    document.getElementById('editMapExtId').value     = m.external_id || '';
    document.getElementById('editMapExtSym').value    = m.external_symbol || '';
    document.getElementById('editMapExtSlug').value   = m.external_slug || '';
    document.getElementById('editMapActive').checked  = parseInt(m.is_active) === 1;
    new bootstrap.Modal(document.getElementById('editMappingModal')).show();
}

function deleteMapping(id) {
    if (!confirm('Delete this mapping?')) return;
    document.getElementById('deleteMappingId').value = id;
    $('#deleteMappingForm').trigger('submit');
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#mappingsTable').DataTable({ order: [[0,'asc']], pageLength: 25, dom: 'lrtip' });
    }
});
</script>

<?php endif; ?>
