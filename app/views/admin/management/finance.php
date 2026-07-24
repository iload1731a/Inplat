<?php declare(strict_types=1); ?>
<?php $filters = is_array($filters ?? null) ? $filters : []; $deposits = is_array($deposits ?? null) ? $deposits : []; $withdrawals = is_array($withdrawals ?? null) ? $withdrawals : []; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Finance Operations</h1>
        <p class="text-secondary mb-0">Process deposit credits and withdrawal reviews with balance-safe admin flows.</p>
    </div>
    <a href="/admin/dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Deposits Queue</h2>
            <form class="row g-2 mb-3" method="get" action="/admin/finance">
                <div class="col-md-4"><select class="form-select" name="deposit_status"><option value="">All statuses</option><?php foreach (['pending','confirmed','credited','failed','flagged'] as $status): ?><option value="<?= e($status) ?>" <?= (($filters['deposit_status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-5"><input class="form-control" type="text" name="deposit_search" value="<?= e((string)($filters['deposit_search'] ?? '')) ?>" placeholder="Username or tx hash"></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
                <input type="hidden" name="withdrawal_status" value="<?= e((string)($filters['withdrawal_status'] ?? '')) ?>">
                <input type="hidden" name="withdrawal_search" value="<?= e((string)($filters['withdrawal_search'] ?? '')) ?>">
            </form>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($deposits as $row): ?>
                        <tr>
                            <td><?= (int)($row['id'] ?? 0) ?></td>
                            <td><div><?= e((string)($row['username'] ?? '-')) ?></div><div class="small text-secondary"><?= e((string)($row['currency_code'] ?? '-')) ?></div></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 8) ?></td>
                            <td><span class="badge text-bg-<?= (($row['status'] ?? '') === 'credited') ? 'success' : ((($row['status'] ?? '') === 'pending') ? 'warning text-dark' : 'secondary') ?>"><?= e((string)($row['status'] ?? '-')) ?></span></td>
                            <td>
                                <form action="/admin/finance/deposit" method="post" data-ajax="true" class="d-grid gap-2">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="deposit_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                    <select class="form-select form-select-sm" name="status"><?php foreach (['pending','confirmed','credited','failed','flagged'] as $status): ?><option value="<?= e($status) ?>" <?= (($row['status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select>
                                    <input class="form-control form-control-sm" type="text" name="notes" value="<?= e((string)($row['flagged_reason'] ?? '')) ?>" placeholder="Flag reason / note">
                                    <button class="btn btn-sm btn-outline-info" type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($deposits === []): ?><tr><td colspan="5" class="text-center text-secondary">No deposits found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Withdrawals Queue</h2>
            <form class="row g-2 mb-3" method="get" action="/admin/finance">
                <div class="col-md-4"><select class="form-select" name="withdrawal_status"><option value="">All statuses</option><?php foreach (['pending','approved','processing','completed','rejected','cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= (($filters['withdrawal_status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-5"><input class="form-control" type="text" name="withdrawal_search" value="<?= e((string)($filters['withdrawal_search'] ?? '')) ?>" placeholder="Username, address, tx hash"></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
                <input type="hidden" name="deposit_status" value="<?= e((string)($filters['deposit_status'] ?? '')) ?>">
                <input type="hidden" name="deposit_search" value="<?= e((string)($filters['deposit_search'] ?? '')) ?>">
            </form>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($withdrawals as $row): ?>
                        <tr>
                            <td><?= (int)($row['id'] ?? 0) ?></td>
                            <td><div><?= e((string)($row['username'] ?? '-')) ?></div><div class="small text-secondary"><?= e((string)($row['currency_code'] ?? '-')) ?></div></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 8) ?><div class="small text-secondary">Fee <?= number_format((float)($row['fee'] ?? 0), 8) ?></div></td>
                            <td><span class="badge text-bg-<?= (($row['status'] ?? '') === 'completed') ? 'success' : ((($row['status'] ?? '') === 'pending') ? 'warning text-dark' : 'secondary') ?>"><?= e((string)($row['status'] ?? '-')) ?></span></td>
                            <td>
                                <form action="/admin/finance/withdrawal" method="post" data-ajax="true" class="d-grid gap-2">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="withdrawal_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                    <select class="form-select form-select-sm" name="status"><?php foreach (['pending','approved','processing','completed','rejected','cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= (($row['status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select>
                                    <input class="form-control form-control-sm" type="text" name="reason" value="<?= e((string)($row['rejection_reason'] ?? '')) ?>" placeholder="Reason / settlement note">
                                    <button class="btn btn-sm btn-outline-info" type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($withdrawals === []): ?><tr><td colspan="5" class="text-center text-secondary">No withdrawals found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
