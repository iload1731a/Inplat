<?php declare(strict_types=1); ?>
<?php
$backupLogs = is_array($backupLogs ?? null) ? $backupLogs : [];
$settings   = is_array($settings ?? null) ? $settings : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
$statusColors = ['running'=>'info','completed'=>'success','failed'=>'danger','deleted'=>'secondary'];
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-database me-2 text-success"></i>Backup Management</h1>
        <p class="text-secondary mb-0">Schedule automatic backups, run manual backups, and manage backup history.</p>
    </div>
    <!-- Manual backup trigger -->
    <div class="d-flex gap-2">
        <?php foreach (['database'=>'Database','files'=>'Files','config'=>'Config','full'=>'Full Backup'] as $type => $label): ?>
            <form method="post" action="/admin/settings/backup/run" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="backup_type" value="<?= e($type) ?>">
                <button class="btn btn-sm btn-outline-<?= $type === 'full' ? 'warning' : 'light' ?>">
                    <i class="fas fa-download me-1"></i><?= e($label) ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Backup Settings -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-4"><i class="fas fa-cog me-2 text-secondary"></i>Scheduled Backup Settings</h6>

            <form method="post" action="/admin/settings/backup/settings" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">

                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle mb-3">
                    <div>
                        <div class="fw-semibold">Automatic Backups</div>
                        <div class="small text-secondary">Enable scheduled backup jobs.</div>
                    </div>
                    <div class="form-check form-switch mb-0 ms-3">
                        <input type="hidden" name="backup_enabled" value="false">
                        <input class="form-check-input" type="checkbox" name="backup_enabled" value="true"
                               <?= $s('backup_enabled', 'false') === 'true' ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Backup Schedule</label>
                    <select name="backup_schedule" class="form-select bg-transparent text-light border-secondary">
                        <option value="hourly"  <?= $s('backup_schedule', 'daily') === 'hourly'  ? 'selected' : '' ?>>Every Hour</option>
                        <option value="daily"   <?= $s('backup_schedule', 'daily') === 'daily'   ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly"  <?= $s('backup_schedule', 'daily') === 'weekly'  ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= $s('backup_schedule', 'daily') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Retention Period (days)</label>
                    <input type="number" name="backup_retention_days" value="<?= e($s('backup_retention_days', '30')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="1" max="365">
                    <div class="form-text text-secondary">Backups older than this are automatically deleted.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Storage Path</label>
                    <input type="text" name="backup_storage_path" value="<?= e($s('backup_storage_path', 'storage/backups')) ?>"
                           class="form-control bg-transparent text-light border-secondary font-monospace">
                    <div class="form-text text-secondary">Relative to application root.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="backup_include_files" value="false">
                        <input type="checkbox" class="form-check-input" name="backup_include_files" value="true"
                               id="backupFiles" <?= $s('backup_include_files', 'true') === 'true' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="backupFiles">Include Uploaded Files</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Notify Email on Completion</label>
                    <input type="email" name="backup_notify_email" value="<?= e($s('backup_notify_email')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="admin@yourplatform.com">
                </div>

                <button class="btn btn-primary w-100">Save Backup Settings</button>
            </form>
        </div>
    </div>

    <!-- Backup Logs -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-0 overflow-hidden">
            <div class="p-3 border-bottom border-secondary-subtle">
                <h6 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Backup History</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Duration</th>
                            <th>By</th>
                            <th>Started</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backupLogs as $log): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?= e(ucfirst((string)($log['backup_type'] ?? ''))) ?></span>
                                <span class="text-secondary ms-1"><?= e((string)($log['trigger_type'] ?? '')) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= e($statusColors[$log['status'] ?? ''] ?? 'secondary') ?>">
                                    <?= e(ucfirst((string)($log['status'] ?? '-'))) ?>
                                </span>
                            </td>
                            <td class="text-secondary">
                                <?php
                                $bytes = (int)($log['file_size_bytes'] ?? 0);
                                if ($bytes > 0) {
                                    if ($bytes > 1048576) echo round($bytes/1048576, 1) . ' MB';
                                    elseif ($bytes > 1024) echo round($bytes/1024, 1) . ' KB';
                                    else echo $bytes . ' B';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="text-secondary">
                                <?= ($log['duration_seconds'] ?? 0) > 0 ? (int)$log['duration_seconds'] . 's' : '-' ?>
                            </td>
                            <td class="text-secondary"><?= e((string)($log['created_by_name'] ?? 'System')) ?></td>
                            <td class="text-secondary"><?= e(substr((string)($log['started_at'] ?? '-'), 0, 16)) ?></td>
                            <td>
                                <?php if (($log['status'] ?? '') !== 'deleted'): ?>
                                    <form method="post" action="/admin/settings/backup/delete-log" data-ajax="true" class="d-inline">
                                        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">
                                        <button class="btn btn-xs btn-outline-danger">Del</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (($log['file_path'] ?? '') !== '' && ($log['status'] ?? '') === 'completed'): ?>
                                    <code class="ms-1 small text-secondary" title="<?= e((string)$log['file_path']) ?>">
                                        <?= e(basename((string)$log['file_path'])) ?>
                                    </code>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($backupLogs === []): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-4">No backup history.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
