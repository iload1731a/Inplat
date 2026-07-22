<?php declare(strict_types=1); ?>
<?php
$featureFlags       = is_array($featureFlags ?? null) ? $featureFlags : [];
$maintenanceWindows = is_array($maintenanceWindows ?? null) ? $maintenanceWindows : [];
$settings           = is_array($settings ?? null) ? $settings : [];
$settingCategories  = is_array($settingCategories ?? null) ? $settingCategories : [];
$priceProviders     = is_array($priceProviders ?? null) ? $priceProviders : [];
$webhooks           = is_array($webhooks ?? null) ? $webhooks : [];
$activeTab          = (string)($activeTab ?? 'flags');
$csrf               = \App\Libraries\Csrf::token();
// Group settings by category
$settingsByCategory = [];
foreach ($settings as $s) {
    $settingsByCategory[(string)($s['category'] ?? 'general')][] = $s;
}
ksort($settingsByCategory);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">System Management</h1>
        <p class="text-secondary mb-0">Feature flags, maintenance windows, settings, API providers, webhooks.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<ul class="nav nav-tabs border-secondary mb-4" role="tablist">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'flags' ? 'active text-white' : 'text-light' ?>" href="?tab=flags">Feature Flags</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'maintenance' ? 'active text-white' : 'text-light' ?>" href="?tab=maintenance">Maintenance</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'settings' ? 'active text-white' : 'text-light' ?>" href="?tab=settings">Settings</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'providers' ? 'active text-white' : 'text-light' ?>" href="?tab=providers">Price Providers</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'webhooks' ? 'active text-white' : 'text-light' ?>" href="?tab=webhooks">Webhooks</a></li>
</ul>

<!-- FEATURE FLAGS -->
<?php if ($activeTab === 'flags'): ?>
<div>
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createFlagModal">
            <i class="fas fa-plus me-1"></i> New Flag
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Key</th><th>Description</th><th>Enabled</th><th>Rollout %</th><th>Overrides</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($featureFlags as $flag): ?>
                    <tr>
                        <td><code class="text-info"><?= e((string)($flag['flag_key'] ?? '-')) ?></code></td>
                        <td class="small text-secondary"><?= e((string)($flag['description'] ?? '')) ?></td>
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input flag-toggle" type="checkbox"
                                    data-id="<?= (int)$flag['id'] ?>"
                                    data-flag='<?= e(json_encode($flag)) ?>'
                                    <?= (int)($flag['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td><?= (int)($flag['rollout_percentage'] ?? 100) ?>%</td>
                        <td><?= (int)($flag['override_count'] ?? 0) ?></td>
                        <td class="text-secondary small"><?= e((string)($flag['created_at'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1" onclick="openEditFlag(<?= e(json_encode($flag)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteFlag(<?= (int)$flag['id'] ?>, '<?= e((string)$flag['flag_key']) ?>')">Del</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($featureFlags === []): ?><tr><td colspan="7" class="text-center text-secondary">No feature flags.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MAINTENANCE -->
<?php if ($activeTab === 'maintenance'): ?>
<div>
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createMaintenanceModal">
            <i class="fas fa-plus me-1"></i> Schedule Maintenance
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Title</th><th>Status</th><th>Scheduled Start</th><th>Scheduled End</th><th>Actual Start</th><th>Created By</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($maintenanceWindows as $mw): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string)($mw['title'] ?? '-')) ?></div>
                            <div class="small text-secondary"><?= e((string)($mw['description'] ?? '')) ?></div>
                        </td>
                        <td><span class="badge text-bg-<?= ['scheduled'=>'info','in_progress'=>'warning','completed'=>'success','cancelled'=>'secondary'][$mw['status'] ?? ''] ?? 'secondary' ?>"><?= e(ucwords(str_replace('_',' ',(string)($mw['status'] ?? '-')))) ?></span></td>
                        <td class="small"><?= e((string)($mw['scheduled_start'] ?? '-')) ?></td>
                        <td class="small"><?= e((string)($mw['scheduled_end'] ?? '-')) ?></td>
                        <td class="small"><?= e((string)($mw['actual_start'] ?? '-')) ?></td>
                        <td class="small"><?= e((string)($mw['created_by_name'] ?? '-')) ?></td>
                        <td class="text-end">
                            <?php if (in_array(($mw['status'] ?? ''), ['scheduled'], true)): ?>
                                <button class="btn btn-xs btn-outline-warning me-1" onclick="updateMaintenanceStatus(<?= (int)$mw['id'] ?>, 'in_progress')">Start</button>
                                <button class="btn btn-xs btn-outline-secondary me-1" onclick="updateMaintenanceStatus(<?= (int)$mw['id'] ?>, 'cancelled')">Cancel</button>
                            <?php elseif (($mw['status'] ?? '') === 'in_progress'): ?>
                                <button class="btn btn-xs btn-outline-success me-1" onclick="updateMaintenanceStatus(<?= (int)$mw['id'] ?>, 'completed')">Complete</button>
                            <?php endif; ?>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteMaintenance(<?= (int)$mw['id'] ?>)">Del</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($maintenanceWindows === []): ?><tr><td colspan="7" class="text-center text-secondary">No maintenance windows.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- SETTINGS -->
<?php if ($activeTab === 'settings'): ?>
<div>
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSettingModal">
            <i class="fas fa-plus me-1"></i> New Setting
        </button>
    </div>
    <?php foreach ($settingsByCategory as $category => $catSettings): ?>
    <div class="glass rounded-4 p-3 mb-3">
        <h2 class="h6 mb-3 text-warning text-uppercase"><?= e(ucwords(str_replace('_', ' ', $category))) ?></h2>
        <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0">
                <thead><tr><th>Key</th><th>Value</th><th>Type</th><th>Public</th><th>Description</th><th class="text-end">Edit</th></tr></thead>
                <tbody>
                <?php foreach ($catSettings as $s): ?>
                    <tr>
                        <td><code class="text-info small"><?= e((string)($s['setting_key'] ?? '-')) ?></code></td>
                        <td>
                            <?php if (($s['value_type'] ?? '') === 'json'): ?>
                                <code class="text-secondary small"><?= e(substr((string)($s['setting_value'] ?? ''), 0, 60)) ?></code>
                            <?php else: ?>
                                <?= e((string)($s['setting_value'] ?? '')) ?>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary"><?= e((string)($s['value_type'] ?? '-')) ?></td>
                        <td><?= (int)($s['is_public'] ?? 0) ? '<span class="badge text-bg-success text-dark">Yes</span>' : '' ?></td>
                        <td class="small text-secondary"><?= e((string)($s['description'] ?? '')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light" onclick="openEditSetting(<?= e(json_encode($s)) ?>)">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($settings === []): ?>
        <div class="glass rounded-4 p-4 text-center text-secondary">No system settings found.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- PRICE PROVIDERS -->
<?php if ($activeTab === 'providers'): ?>
<div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Base URL</th><th>Priority</th><th>Active</th></tr></thead>
                <tbody>
                <?php foreach ($priceProviders as $pp): ?>
                    <tr>
                        <td><?= (int)($pp['id'] ?? 0) ?></td>
                        <td class="fw-semibold"><?= e((string)($pp['name'] ?? '-')) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e(ucfirst((string)($pp['provider_type'] ?? '-'))) ?></span></td>
                        <td class="small text-secondary"><?= e((string)($pp['base_url'] ?? '-')) ?></td>
                        <td><?= (int)($pp['priority'] ?? 0) ?></td>
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input provider-toggle" type="checkbox"
                                    data-id="<?= (int)$pp['id'] ?>"
                                    <?= (int)($pp['is_active'] ?? 0) ? 'checked' : '' ?>>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($priceProviders === []): ?><tr><td colspan="6" class="text-center text-secondary">No providers.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- WEBHOOKS -->
<?php if ($activeTab === 'webhooks'): ?>
<div>
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWebhookModal">
            <i class="fas fa-plus me-1"></i> New Webhook
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>ID</th><th>URL</th><th>Events</th><th>Active</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($webhooks as $wh): ?>
                    <tr>
                        <td><?= (int)($wh['id'] ?? 0) ?></td>
                        <td class="small"><?= e((string)($wh['url'] ?? '-')) ?></td>
                        <td class="small text-secondary"><?= e((string)($wh['event_types'] ?? 'all')) ?></td>
                        <td><span class="badge text-bg-<?= (int)($wh['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($wh['is_active'] ?? 0) ? 'Active' : 'Off' ?></span></td>
                        <td class="text-secondary small"><?= e((string)($wh['created_at'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteWebhook(<?= (int)$wh['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($webhooks === []): ?><tr><td colspan="6" class="text-center text-secondary">No webhooks.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== MODALS ===== -->

<!-- Create Feature Flag -->
<div class="modal fade" id="createFlagModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/system/flags/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Feature Flag</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Flag Key <span class="text-danger">*</span></label><input class="form-control" type="text" name="flag_key" placeholder="feature.new_trading_ui" required pattern="[a-z0-9._\-]+"></div>
                <div class="mb-3"><label class="form-label">Description</label><input class="form-control" type="text" name="description"></div>
                <div class="mb-3"><label class="form-label">Rollout %</label><input class="form-control" type="number" name="rollout_percentage" value="100" min="0" max="100"></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="flagIsEnabled"><label class="form-check-label" for="flagIsEnabled">Enabled</label></div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<!-- Edit Feature Flag -->
<div class="modal fade" id="editFlagModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/system/flags/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="flag_id" id="editFlagId">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit Feature Flag</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Key</label><input class="form-control" type="text" name="flag_key" id="editFlagKey" required></div>
                <div class="mb-3"><label class="form-label">Description</label><input class="form-control" type="text" name="description" id="editFlagDesc"></div>
                <div class="mb-3"><label class="form-label">Rollout %</label><input class="form-control" type="number" name="rollout_percentage" id="editFlagRollout" min="0" max="100"></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="editFlagEnabled"><label class="form-check-label" for="editFlagEnabled">Enabled</label></div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Create Maintenance Window -->
<div class="modal fade" id="createMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/system/maintenance/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">Schedule Maintenance</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label><input class="form-control" type="text" name="title" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label">Scheduled Start <span class="text-danger">*</span></label><input class="form-control" type="datetime-local" name="scheduled_start" required></div>
                <div class="mb-3"><label class="form-label">Scheduled End</label><input class="form-control" type="datetime-local" name="scheduled_end"></div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Schedule</button></div>
        </form>
    </div>
</div>

<!-- Edit Setting Modal -->
<div class="modal fade" id="editSettingModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/system/settings/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="setting_id" id="editSettingId">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit Setting – <span id="editSettingKey" class="text-info font-monospace"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Value Type</label>
                    <select class="form-select" name="value_type" id="editSettingType">
                        <option value="string">String</option><option value="integer">Integer</option><option value="boolean">Boolean</option><option value="json">JSON</option><option value="text">Text</option>
                    </select></div>
                <div class="mb-3"><label class="form-label">Value</label><textarea class="form-control" name="setting_value" id="editSettingValue" rows="4"></textarea></div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Create Setting Modal -->
<div class="modal fade" id="createSettingModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/system/settings/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Setting</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Key <span class="text-danger">*</span></label><input class="form-control" type="text" name="setting_key" required></div>
                    <div class="col-md-6"><label class="form-label">Category</label><input class="form-control" type="text" name="category" value="general"></div>
                    <div class="col-md-4"><label class="form-label">Value Type</label>
                        <select class="form-select" name="value_type"><option value="string">String</option><option value="integer">Integer</option><option value="boolean">Boolean</option><option value="json">JSON</option><option value="text">Text</option></select></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_public" value="1"><label class="form-check-label">Public</label></div></div>
                    <div class="col-12"><label class="form-label">Value</label><textarea class="form-control" name="setting_value" rows="3"></textarea></div>
                    <div class="col-12"><label class="form-label">Description</label><input class="form-control" type="text" name="description"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<!-- Create Webhook Modal -->
<div class="modal fade" id="createWebhookModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/system/webhooks/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Webhook</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">URL <span class="text-danger">*</span></label><input class="form-control" type="url" name="url" required></div>
                <div class="mb-3"><label class="form-label">Event Types</label><input class="form-control" type="text" name="event_types" placeholder="order.created,deposit.confirmed (or blank for all)"></div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<!-- Hidden delete / status forms -->
<form id="deleteFlagForm" data-ajax="true" action="/admin/system/flags/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="flag_id" id="deleteFlagId"></form>
<form id="deleteMaintenanceForm" data-ajax="true" action="/admin/system/maintenance/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="window_id" id="deleteMaintenanceId"></form>
<form id="maintenanceStatusForm" data-ajax="true" action="/admin/system/maintenance/status" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="window_id" id="maintenanceStatusId"><input type="hidden" name="status" id="maintenanceStatusValue"></form>
<form id="toggleProviderForm" data-ajax="true" action="/admin/system/providers/toggle" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="provider_id" id="toggleProviderId"><input type="hidden" name="is_active" id="toggleProviderActive"></form>
<form id="deleteWebhookForm" data-ajax="true" action="/admin/system/webhooks/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="webhook_id" id="deleteWebhookId"></form>

<script>
function openEditFlag(flag) {
    document.getElementById('editFlagId').value = flag.id;
    document.getElementById('editFlagKey').value = flag.flag_key;
    document.getElementById('editFlagDesc').value = flag.description || '';
    document.getElementById('editFlagRollout').value = flag.rollout_percentage || 100;
    document.getElementById('editFlagEnabled').checked = parseInt(flag.is_enabled) === 1;
    new bootstrap.Modal(document.getElementById('editFlagModal')).show();
}
function deleteFlag(id, key) {
    if (!confirm('Delete feature flag "' + key + '"?')) return;
    document.getElementById('deleteFlagId').value = id;
    $('#deleteFlagForm').trigger('submit');
}
function updateMaintenanceStatus(id, status) {
    if (!confirm('Set maintenance status to "' + status + '"?')) return;
    document.getElementById('maintenanceStatusId').value = id;
    document.getElementById('maintenanceStatusValue').value = status;
    $('#maintenanceStatusForm').trigger('submit');
}
function deleteMaintenance(id) {
    if (!confirm('Delete maintenance window?')) return;
    document.getElementById('deleteMaintenanceId').value = id;
    $('#deleteMaintenanceForm').trigger('submit');
}
function openEditSetting(s) {
    document.getElementById('editSettingId').value = s.id;
    document.getElementById('editSettingKey').textContent = s.setting_key;
    document.getElementById('editSettingType').value = s.value_type || 'string';
    document.getElementById('editSettingValue').value = s.setting_value || '';
    new bootstrap.Modal(document.getElementById('editSettingModal')).show();
}
function deleteWebhook(id) {
    if (!confirm('Delete webhook?')) return;
    document.getElementById('deleteWebhookId').value = id;
    $('#deleteWebhookForm').trigger('submit');
}

// Feature flag toggle
document.querySelectorAll('.flag-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        const flag = JSON.parse(this.dataset.flag);
        flag.is_enabled = this.checked ? 1 : 0;
        openEditFlag(flag);
    });
});

// Provider toggle
document.querySelectorAll('.provider-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        document.getElementById('toggleProviderId').value = this.dataset.id;
        document.getElementById('toggleProviderActive').value = this.checked ? '1' : '0';
        $('#toggleProviderForm').trigger('submit');
    });
});
</script>
