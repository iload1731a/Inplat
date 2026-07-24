<?php declare(strict_types=1); ?>
<?php
$languages = is_array($languages ?? null) ? $languages : [];
$csrf      = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-flag me-2 text-primary"></i>Language Management</h1>
        <p class="text-secondary mb-0">Add, edit, enable/disable, and set the default platform language.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createLangModal">
        <i class="fas fa-plus me-1"></i>Add Language
    </button>
</div>

<div class="glass rounded-4 p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Native Name</th>
                    <th>Flag</th>
                    <th>RTL</th>
                    <th>Sort</th>
                    <th>Active</th>
                    <th>Default</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($languages as $lang): ?>
                <?php $isDefault = (int)($lang['is_default'] ?? 0) === 1; ?>
                <tr>
                    <td><code class="text-info"><?= e((string)($lang['code'] ?? '')) ?></code></td>
                    <td class="fw-semibold"><?= e((string)($lang['name'] ?? '')) ?></td>
                    <td class="text-secondary"><?= e((string)($lang['native_name'] ?? '')) ?></td>
                    <td>
                        <?php if (($lang['flag_code'] ?? '') !== ''): ?>
                            <span class="badge bg-secondary"><i class="fi fi-<?= e(strtolower((string)$lang['flag_code'])) ?>"></i> <?= e(strtoupper((string)$lang['flag_code'])) ?></span>
                        <?php else: ?>
                            <span class="text-secondary">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)($lang['is_rtl'] ?? 0) ? '<span class="badge bg-info">RTL</span>' : '<span class="text-secondary small">LTR</span>' ?></td>
                    <td class="text-secondary"><?= (int)($lang['sort_order'] ?? 0) ?></td>
                    <td>
                        <?php if ((int)($lang['is_active'] ?? 0)): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isDefault): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Default</span>
                        <?php else: ?>
                            <form method="post" action="/admin/settings/languages/default" data-ajax="true" class="d-inline">
                                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="language_id" value="<?= (int)$lang['id'] ?>">
                                <button class="btn btn-xs btn-outline-warning" type="submit">Set Default</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1" onclick='openEditLang(<?= e(json_encode($lang)) ?>)'>Edit</button>
                        <?php if (!$isDefault): ?>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteLang(<?= (int)$lang['id'] ?>, '<?= e((string)$lang['name']) ?>')">Del</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($languages === []): ?>
                <tr><td colspan="9" class="text-center text-secondary py-4">No languages configured.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Language Modal -->
<div class="modal fade" id="createLangModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add Language</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/languages/create" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="modal-body">
                    <?php include __DIR__ . '/_lang_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Language</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Language Modal -->
<div class="modal fade" id="editLangModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Language</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/languages/update" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="language_id" id="editLangId">
                <div class="modal-body">
                    <?php include __DIR__ . '/_lang_form.php'; ?>
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
function openEditLang(lang) {
    const m = document.getElementById('editLangModal');
    document.getElementById('editLangId').value = lang.id;
    ['code','name','native_name','flag_code','sort_order'].forEach(f => {
        const el = m.querySelector('[name="' + f + '"]');
        if (el && lang[f] != null) el.value = lang[f];
    });
    ['is_rtl','is_active'].forEach(f => {
        const el = m.querySelector('[name="' + f + '"]');
        if (el) el.checked = parseInt(lang[f]) === 1;
    });
    new bootstrap.Modal(m).show();
}
function deleteLang(id, name) {
    if (!confirm('Delete language "' + name + '"?')) return;
    $.ajax({
        url: '/admin/settings/languages/delete',
        method: 'POST',
        data: { _token: '<?= e($csrf) ?>', language_id: id },
        success: r => { alert(r.message); if (r.redirect) location.href = r.redirect; },
        error: x => alert((x.responseJSON || {}).message || 'Error'),
    });
}
</script>
