<?php declare(strict_types=1); ?>
<?php
$integrations = is_array($integrations ?? null) ? $integrations : [];
$byCategory   = is_array($byCategory ?? null) ? $byCategory : [];
$categories   = is_array($categories ?? null) ? $categories : ['payment','kyc','analytics','trading','social','other'];
$csrf         = \App\Libraries\Csrf::token();
$catIcons     = ['payment'=>'fa-credit-card','kyc'=>'fa-id-card','analytics'=>'fa-chart-bar','trading'=>'fa-chart-line','social'=>'fa-share-alt','other'=>'fa-plug'];
$catColors    = ['payment'=>'text-success','kyc'=>'text-info','analytics'=>'text-warning','trading'=>'text-primary','social'=>'text-danger','other'=>'text-secondary'];
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-plug me-2 text-danger"></i>API Integrations</h1>
        <p class="text-secondary mb-0">Manage third-party API credentials for payments, KYC, analytics, and more.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createApiModal">
        <i class="fas fa-plus me-1"></i>Add Integration
    </button>
</div>

<?php if ($integrations === []): ?>
    <div class="glass rounded-4 p-5 text-center">
        <i class="fas fa-plug fa-3x text-secondary mb-3"></i>
        <h5>No API integrations configured</h5>
        <p class="text-secondary">Connect payment gateways, KYC providers, analytics platforms, and more.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createApiModal">
            <i class="fas fa-plus me-1"></i>Add First Integration
        </button>
    </div>
<?php else: ?>
    <?php foreach ($categories as $cat): ?>
    <?php if (empty($byCategory[$cat])) continue; ?>
    <h6 class="text-secondary text-uppercase fw-bold mt-4 mb-2">
        <i class="fas <?= e($catIcons[$cat] ?? 'fa-plug') ?> me-1 <?= e($catColors[$cat] ?? '') ?>"></i>
        <?= e(ucfirst($cat)) ?>
    </h6>
    <div class="row g-3 mb-2">
        <?php foreach ($byCategory[$cat] as $item): ?>
        <div class="col-lg-4 col-md-6">
            <div class="glass rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold"><?= e((string)($item['name'] ?? '-')) ?></div>
                        <div class="small text-secondary"><code><?= e((string)($item['provider'] ?? '')) ?></code></div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <span class="badge <?= (int)($item['is_active'] ?? 0) ? 'bg-success' : 'bg-secondary' ?>">
                            <?= (int)($item['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if ((int)($item['sandbox_mode'] ?? 1) === 1): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-flask me-1"></i>Sandbox</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Live</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (($item['notes'] ?? '') !== ''): ?>
                    <div class="small text-secondary mb-2"><?= e((string)$item['notes']) ?></div>
                <?php endif; ?>
                <div class="small text-secondary mb-3">Updated: <?= e((string)($item['updated_at'] ?? '-')) ?></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-xs btn-outline-light" onclick='openEditApi(<?= e(json_encode($item)) ?>)'>Edit</button>
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteApi(<?= (int)$item['id'] ?>, '<?= e((string)$item['name']) ?>')">Delete</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Create API Modal -->
<div class="modal fade" id="createApiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add API Integration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/api/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="modal-body">
                    <?php include __DIR__ . '/_api_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Integration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit API Modal -->
<div class="modal fade" id="editApiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit API Integration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/api/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" id="editApiId">
                <div class="modal-body">
                    <?php include __DIR__ . '/_api_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditApi(item) {
    const m = document.getElementById('editApiModal');
    document.getElementById('editApiId').value = item.id;
    ['name','provider','notes','extra_config'].forEach(f => {
        const el = m.querySelector('[name="' + f + '"]');
        if (el && item[f] != null) el.value = item[f];
    });
    const catEl = m.querySelector('[name="category"]');
    if (catEl && item.category) catEl.value = item.category;
    const sandEl = m.querySelector('[name="sandbox_mode"]');
    if (sandEl) sandEl.checked = parseInt(item.sandbox_mode) === 1;
    const actEl = m.querySelector('[name="is_active"]');
    if (actEl) actEl.checked = parseInt(item.is_active) === 1;
    new bootstrap.Modal(m).show();
}
function deleteApi(id, name) {
    if (!confirm('Delete API integration "' + name + '"?')) return;
    $.ajax({
        url: '/admin/settings/api/delete',
        method: 'POST',
        data: { _token: '<?= e($csrf) ?>', integration_id: id },
        success: r => { alert(r.message); if (r.redirect) location.href = r.redirect; },
        error: x => alert((x.responseJSON || {}).message || 'Error'),
    });
}
</script>
