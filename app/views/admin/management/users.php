<?php declare(strict_types=1); ?>
<?php $filters = is_array($filters ?? null) ? $filters : []; $users = is_array($users ?? null) ? $users : []; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">User Management</h1>
        <p class="text-secondary mb-0">Search users, inspect balances, edit identity data, and review KYC.</p>
    </div>
    <a href="/admin/platform" class="btn btn-outline-warning btn-sm">Admin Modules</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-3" method="get" action="/admin/users">
        <div class="col-lg-5"><input class="form-control" type="text" name="search" placeholder="Search by username, email, or name" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['active','pending','suspended','banned','closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= (($filters['status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="kyc_status">
                <option value="">All KYC</option>
                <?php foreach (['unverified','pending','approved','rejected'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= (($filters['kyc_status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="/admin/users">Reset</a>
        </div>
    </form>
</div>
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>User</th><th>Status</th><th>KYC</th><th>Available</th><th>Locked</th><th>2FA</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= (int)($row['id'] ?? 0) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e((string)($row['username'] ?? '-')) ?></div>
                        <div class="small text-secondary"><?= e((string)($row['email'] ?? '-')) ?></div>
                    </td>
                    <td><span class="badge text-bg-<?= in_array((string)($row['status'] ?? ''), ['active'], true) ? 'success' : 'secondary' ?>"><?= e((string)($row['status'] ?? '-')) ?></span></td>
                    <td><span class="badge text-bg-<?= (($row['kyc_status'] ?? '') === 'approved') ? 'success' : ((($row['kyc_status'] ?? '') === 'pending') ? 'warning text-dark' : 'secondary') ?>"><?= e((string)($row['kyc_status'] ?? '-')) ?></span></td>
                    <td><?= number_format((float)($row['total_available_balance'] ?? 0), 8) ?></td>
                    <td><?= number_format((float)($row['total_locked_balance'] ?? 0), 8) ?></td>
                    <td><?= ((int)($row['two_factor_enabled'] ?? 0) === 1) ? 'Enabled' : 'Off' ?></td>
                    <td><?= e((string)($row['created_at'] ?? '-')) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-info" href="/admin/user?id=<?= (int)($row['id'] ?? 0) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?><tr><td colspan="9" class="text-center text-secondary">No users matched your filters.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
