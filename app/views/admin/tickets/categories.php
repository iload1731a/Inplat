<?php declare(strict_types=1); ?>
<?php
$categories = is_array($categories ?? null) ? $categories : [];
$csrfToken  = \App\Libraries\Csrf::token();

$colors = ['primary','secondary','success','danger','warning','info','dark'];
$icons  = ['fa-tag','fa-user-circle','fa-arrow-down','fa-arrow-up','fa-chart-line',
           'fa-id-card','fa-tools','fa-percent','fa-question-circle','fa-headset',
           'fa-exclamation-circle','fa-lock','fa-money-bill'];
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1"><i class="fas fa-folder me-2 text-warning"></i>Ticket Categories</h5>
        <span class="text-secondary small"><?= count($categories) ?> categories</span>
    </div>
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#createCatModal">
        <i class="fas fa-plus me-1"></i>New Category
    </button>
</div>

<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Slug</th><th>SLA (hrs)</th><th>Tickets</th><th>Status</th><th>Sort</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <i class="fas <?= e((string)($cat['icon'] ?? 'fa-tag')) ?> text-<?= e((string)($cat['color'] ?? 'secondary')) ?> me-2"></i>
                        <?= e((string)($cat['name'] ?? '')) ?>
                        <?php if (!empty($cat['description'])): ?>
                            <div class="text-secondary small"><?= e(substr((string)$cat['description'], 0, 60)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><code class="small"><?= e((string)($cat['slug'] ?? '')) ?></code></td>
                    <td><?= (int)($cat['sla_hours'] ?? 24) ?>h</td>
                    <td><?= number_format((int)($cat['total_tickets'] ?? 0)) ?></td>
                    <td>
                        <?php if ((int)($cat['is_active'] ?? 1)): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)($cat['sort_order'] ?? 0) ?></td>
                    <td>
                        <button class="btn btn-xs btn-outline-warning me-1"
                                onclick="editCat(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ((int)($cat['total_tickets'] ?? 0) === 0): ?>
                        <form data-ajax="true" action="/admin/tickets/categories/delete" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int)($cat['id'] ?? 0) ?>">
                            <button type="submit" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($categories === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No categories found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE MODAL -->
<div class="modal fade" id="createCatModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark" data-ajax="true" action="/admin/tickets/categories/create" method="POST">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="mb-3">
                    <label class="form-label small">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="80">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Description</label>
                    <input type="text" name="description" class="form-control" maxlength="255">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Icon (FontAwesome class)</label>
                        <select name="icon" class="form-select">
                            <?php foreach ($icons as $ic): ?>
                                <option value="<?= e($ic) ?>"><?= e($ic) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Color</label>
                        <select name="color" class="form-select">
                            <?php foreach ($colors as $c): ?>
                                <option value="<?= e($c) ?>"><?= e(ucfirst($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">SLA Hours</label>
                        <input type="number" name="sla_hours" class="form-control" value="24" min="1" max="720">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="createIsActive" checked>
                            <label class="form-check-label small" for="createIsActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark" data-ajax="true" action="/admin/tickets/categories/update" method="POST">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="editCatId">
                <div class="mb-3">
                    <label class="form-label small">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editCatName" class="form-control" required maxlength="80">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Description</label>
                    <input type="text" name="description" id="editCatDesc" class="form-control" maxlength="255">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Icon</label>
                        <select name="icon" id="editCatIcon" class="form-select">
                            <?php foreach ($icons as $ic): ?>
                                <option value="<?= e($ic) ?>"><?= e($ic) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Color</label>
                        <select name="color" id="editCatColor" class="form-select">
                            <?php foreach ($colors as $c): ?>
                                <option value="<?= e($c) ?>"><?= e(ucfirst($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">SLA Hours</label>
                        <input type="number" name="sla_hours" id="editCatSla" class="form-control" min="1" max="720">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Sort Order</label>
                        <input type="number" name="sort_order" id="editCatSort" class="form-control" min="0">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editIsActive">
                            <label class="form-check-label small" for="editIsActive">Active</label>
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

<script>
function editCat(cat) {
    document.getElementById('editCatId').value    = cat.id;
    document.getElementById('editCatName').value  = cat.name || '';
    document.getElementById('editCatDesc').value  = cat.description || '';
    document.getElementById('editCatIcon').value  = cat.icon || 'fa-tag';
    document.getElementById('editCatColor').value = cat.color || 'secondary';
    document.getElementById('editCatSla').value   = cat.sla_hours || 24;
    document.getElementById('editCatSort').value  = cat.sort_order || 0;
    document.getElementById('editIsActive').checked = parseInt(cat.is_active||'1') === 1;
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
}
</script>
