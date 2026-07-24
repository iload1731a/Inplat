<?php declare(strict_types=1); ?>
<?php
$adminLogs    = is_array($adminLogs ?? null) ? $adminLogs : [];
$auditLogs    = is_array($auditLogs ?? null) ? $auditLogs : [];
$loginHistory = is_array($loginHistory ?? null) ? $loginHistory : [];
$logStats     = is_array($logStats ?? null) ? $logStats : [];
$filters      = is_array($filters ?? null) ? $filters : [];
$activeTab    = (string)($activeTab ?? 'admin');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Activity &amp; Audit Logs</h1>
        <p class="text-secondary mb-0">Track all admin actions, system events, and login history.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Today Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Admin Actions Today</div><div class="h4 mb-0 text-warning"><?= number_format((int)($logStats['admin_actions_today'] ?? 0)) ?></div></div></div>
    <div class="col-md-4"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Audit Entries Today</div><div class="h4 mb-0 text-info"><?= number_format((int)($logStats['audit_entries_today'] ?? 0)) ?></div></div></div>
    <div class="col-md-4"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Failed Logins Today</div><div class="h4 mb-0 text-danger"><?= number_format((int)($logStats['failed_logins_today'] ?? 0)) ?></div></div></div>
</div>

<ul class="nav nav-tabs border-secondary mb-4" role="tablist">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'admin' ? 'active text-white' : 'text-light' ?>" href="?tab=admin">Admin Activity</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'audit' ? 'active text-white' : 'text-light' ?>" href="?tab=audit">Audit Log</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'logins' ? 'active text-white' : 'text-light' ?>" href="?tab=logins">Login History</a></li>
</ul>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/logs">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
        <div class="col-lg-4"><input class="form-control" type="text" name="search" placeholder="Search..." value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <?php if ($activeTab === 'admin'): ?>
        <div class="col-lg-2"><input class="form-control" type="text" name="action" placeholder="Action filter" value="<?= e((string)($filters['action'] ?? '')) ?>"></div>
        <?php endif; ?>
        <?php if ($activeTab === 'logins'): ?>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <option value="success" <?= (($filters['status'] ?? '') === 'success') ? 'selected' : '' ?>>Success</option>
                <option value="failed" <?= (($filters['status'] ?? '') === 'failed') ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-lg-2"><input class="form-control" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>"></div>
        <div class="col-lg-2"><input class="form-control" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>"></div>
        <div class="col-lg-2 d-flex gap-1">
            <button class="btn btn-primary w-100" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="?tab=<?= e($activeTab) ?>">⟳</a>
        </div>
    </form>
</div>

<!-- Admin Activity Log -->
<?php if ($activeTab === 'admin'): ?>
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Admin</th><th>Action</th><th>Resource</th><th>Resource ID</th><th>IP</th><th>Changes</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($adminLogs as $log): ?>
                <tr>
                    <td class="text-secondary small"><?= (int)($log['id'] ?? 0) ?></td>
                    <td class="small fw-semibold"><?= e((string)($log['admin_name'] ?? '-')) ?></td>
                    <td><code class="text-warning small"><?= e((string)($log['action'] ?? '-')) ?></code></td>
                    <td class="small"><?= e((string)($log['resource_type'] ?? '-')) ?></td>
                    <td class="small text-secondary"><?= e((string)($log['resource_id'] ?? '-')) ?></td>
                    <td class="small text-secondary"><?= e((string)($log['ip_address'] ?? '-')) ?></td>
                    <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis">
                        <?php if (!empty($log['changes_json'])): ?>
                            <code class="text-secondary" style="font-size:10px"><?= e(substr((string)$log['changes_json'], 0, 100)) ?></code>
                        <?php endif; ?>
                    </td>
                    <td class="text-secondary small"><?= e((string)($log['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($adminLogs === []): ?><tr><td colspan="8" class="text-center text-secondary">No logs found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Audit Log -->
<?php if ($activeTab === 'audit'): ?>
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Actor</th><th>Actor ID</th><th>Action</th><th>Resource</th><th>Resource ID</th><th>IP</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($auditLogs as $log): ?>
                <tr>
                    <td class="text-secondary small"><?= (int)($log['id'] ?? 0) ?></td>
                    <td><span class="badge text-bg-<?= ($log['actor_type'] ?? '') === 'admin' ? 'warning text-dark' : 'info' ?>"><?= e(ucfirst((string)($log['actor_type'] ?? '-'))) ?></span></td>
                    <td class="text-secondary small"><?= (int)($log['actor_id'] ?? 0) ?></td>
                    <td><code class="text-warning small"><?= e((string)($log['action'] ?? '-')) ?></code></td>
                    <td class="small"><?= e((string)($log['resource_type'] ?? '-')) ?></td>
                    <td class="text-secondary small"><?= e((string)($log['resource_id'] ?? '-')) ?></td>
                    <td class="text-secondary small"><?= e((string)($log['ip_address'] ?? '-')) ?></td>
                    <td class="text-secondary small"><?= e((string)($log['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($auditLogs === []): ?><tr><td colspan="8" class="text-center text-secondary">No audit logs found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Login History -->
<?php if ($activeTab === 'logins'): ?>
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>User</th><th>IP</th><th>Country</th><th>Status</th><th>Failure Reason</th><th>User Agent</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($loginHistory as $lh): ?>
                <tr>
                    <td class="text-secondary small"><?= (int)($lh['id'] ?? 0) ?></td>
                    <td>
                        <div class="small fw-semibold"><?= e((string)($lh['username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($lh['email'] ?? '')) ?></div>
                    </td>
                    <td class="small"><?= e((string)($lh['ip_address'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($lh['country_code'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= ($lh['status'] ?? '') === 'success' ? 'success' : 'danger' ?>"><?= e(ucfirst((string)($lh['status'] ?? '-'))) ?></span></td>
                    <td class="small text-secondary"><?= e((string)($lh['failure_reason'] ?? '')) ?></td>
                    <td class="small text-secondary" style="max-width:150px;overflow:hidden;text-overflow:ellipsis"><?= e((string)($lh['user_agent'] ?? '')) ?></td>
                    <td class="text-secondary small"><?= e((string)($lh['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($loginHistory === []): ?><tr><td colspan="8" class="text-center text-secondary">No login history found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
