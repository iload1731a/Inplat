<?php declare(strict_types=1); ?>
<?php
$themes      = is_array($themes ?? null) ? $themes : [];
$activeTheme = is_array($activeTheme ?? null) ? $activeTheme : null;
$csrf        = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-paint-brush me-2 text-success"></i>Theme Management</h1>
        <p class="text-secondary mb-0">Manage UI themes, color presets, dark/light mode, and custom CSS.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createThemeModal">
        <i class="fas fa-plus me-1"></i>New Theme
    </button>
</div>

<!-- Themes Grid -->
<div class="row g-4">
    <?php foreach ($themes as $theme): ?>
    <?php $isActive = (int)($theme['is_default'] ?? 0) === 1; ?>
    <div class="col-lg-4 col-md-6">
        <div class="glass rounded-4 p-4 h-100 <?= $isActive ? 'border border-warning' : '' ?>">
            <!-- Color Swatches -->
            <div class="d-flex gap-2 mb-3">
                <?php foreach (['primary_color','secondary_color','accent_color','success_color','danger_color','bg_color','surface_color'] as $c): ?>
                    <div style="width:20px;height:20px;border-radius:4px;background:<?= e((string)($theme[$c] ?? '#888')) ?>;border:1px solid rgba(255,255,255,.15);" title="<?= e($c) ?>"></div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-semibold"><?= e((string)($theme['name'] ?? '-')) ?></div>
                    <div class="small text-secondary">
                        <span class="badge bg-secondary me-1"><?= e(ucfirst((string)($theme['color_scheme'] ?? 'dark'))) ?></span>
                        <?= e((string)($theme['slug'] ?? '')) ?>
                    </div>
                </div>
                <?php if ($isActive): ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-check me-1"></i>Active</span>
                <?php endif; ?>
            </div>

            <div class="small text-secondary mb-3">
                Font: <code><?= e((string)($theme['font_family'] ?? '')) ?></code>
                <br>Base: <code><?= e((string)($theme['font_size_base'] ?? '16px')) ?></code>
                · Radius: <code><?= e((string)($theme['border_radius'] ?? '0.5rem')) ?></code>
            </div>

            <!-- Preview Swatch -->
            <div class="rounded-3 p-3 mb-3" style="background:<?= e((string)($theme['bg_color'] ?? '#0f172a')) ?>;">
                <div class="rounded-2 p-2 mb-2" style="background:<?= e((string)($theme['surface_color'] ?? '#1e293b')) ?>;">
                    <div style="width:60%;height:8px;border-radius:4px;background:<?= e((string)($theme['primary_color'] ?? '#3b82f6')) ?>;margin-bottom:4px;"></div>
                    <div style="width:40%;height:6px;border-radius:3px;background:<?= e((string)($theme['secondary_color'] ?? '#64748b')) ?>;"></div>
                </div>
                <div class="d-flex gap-1">
                    <div style="flex:1;height:6px;border-radius:3px;background:<?= e((string)($theme['success_color'] ?? '#10b981')) ?>;"></div>
                    <div style="flex:1;height:6px;border-radius:3px;background:<?= e((string)($theme['accent_color'] ?? '#f59e0b')) ?>;"></div>
                    <div style="flex:1;height:6px;border-radius:3px;background:<?= e((string)($theme['danger_color'] ?? '#ef4444')) ?>;"></div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <?php if (!$isActive): ?>
                    <form method="post" action="/admin/settings/theme/activate" data-ajax="true" class="d-inline">
                        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="theme_id" value="<?= (int)$theme['id'] ?>">
                        <button class="btn btn-xs btn-outline-warning">Activate</button>
                    </form>
                <?php endif; ?>
                <button class="btn btn-xs btn-outline-light" onclick='openEditTheme(<?= json_encode($theme) ?>)'>Edit</button>
                <?php if (!$isActive): ?>
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteTheme(<?= (int)$theme['id'] ?>, '<?= e((string)$theme['name']) ?>')">Delete</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($themes === []): ?>
        <div class="col-12"><div class="glass rounded-4 p-4 text-center text-secondary">No themes configured.</div></div>
    <?php endif; ?>
</div>

<!-- Create Theme Modal -->
<div class="modal fade" id="createThemeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Create New Theme</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/theme/save" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <div class="modal-body">
                    <?php include __DIR__ . '/_theme_form.php'; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Theme</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Theme Modal -->
<div class="modal fade" id="editThemeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Theme</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/admin/settings/theme/save" data-ajax="true" id="editThemeForm">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="theme_id" id="editThemeId">
                <div class="modal-body" id="editThemeBody">
                    <?php include __DIR__ . '/_theme_form.php'; ?>
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
function openEditTheme(theme) {
    const m = document.getElementById('editThemeModal');
    document.getElementById('editThemeId').value = theme.id;
    const fields = ['name','color_scheme','primary_color','secondary_color','accent_color',
                    'success_color','danger_color','bg_color','surface_color',
                    'font_family','font_size_base','border_radius','custom_css'];
    fields.forEach(f => {
        const el = m.querySelector('[name="' + f + '"]');
        if (el && theme[f] != null) el.value = theme[f];
    });
    new bootstrap.Modal(m).show();
}

function deleteTheme(id, name) {
    if (!confirm('Delete theme "' + name + '"? This action cannot be undone.')) return;
    $.ajax({
        url: '/admin/settings/theme/delete',
        method: 'POST',
        data: { _token: '<?= e($csrf) ?>', theme_id: id },
        success: r => { alert(r.message); if (r.redirect) location.href = r.redirect; },
        error: x => alert((x.responseJSON || {}).message || 'Error'),
    });
}
</script>
