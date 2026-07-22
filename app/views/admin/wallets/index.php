<?php declare(strict_types=1); ?>
<?php
$wallets     = is_array($wallets ?? null) ? $wallets : [];
$walletStats = is_array($walletStats ?? null) ? $walletStats : [];
$topWallets  = is_array($topWallets ?? null) ? $topWallets : [];
$filters     = is_array($filters ?? null) ? $filters : [];
$csrf        = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Wallets Management</h1>
        <p class="text-secondary mb-0">Manage user wallets, balances, freeze/unfreeze, and view ledger entries.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/wallets/deposits"   class="btn btn-outline-success btn-sm"><i class="fas fa-arrow-down me-1"></i>Deposits</a>
        <a href="/admin/wallets/withdrawals" class="btn btn-outline-warning btn-sm"><i class="fas fa-arrow-up me-1"></i>Withdrawals</a>
        <a href="/admin/wallets/adjustment" class="btn btn-outline-info btn-sm"><i class="fas fa-sliders-h me-1"></i>Adjustment</a>
        <a href="/admin/wallets/monitoring" class="btn btn-outline-danger btn-sm"><i class="fas fa-shield-virus me-1"></i>Monitoring</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total Wallets</div><div class="h4 mb-0"><?= number_format((int)($walletStats['total_wallets'] ?? 0)) ?></div></div></div>
    <div class="col-md-4"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Frozen Wallets</div><div class="h4 mb-0 text-danger"><?= number_format((int)($walletStats['frozen_wallets'] ?? 0)) ?></div></div></div>
    <div class="col-md-4"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Users w/ Wallets</div><div class="h4 mb-0 text-info"><?= number_format((int)($walletStats['users_with_wallets'] ?? 0)) ?></div></div></div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/wallets">
        <div class="col-lg-4"><input class="form-control" type="text" name="search" placeholder="Search by username, email or currency" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2"><input class="form-control" type="text" name="currency" placeholder="Currency code" value="<?= e((string)($filters['currency'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="is_frozen">
                <option value="">All</option>
                <option value="1" <?= (($filters['is_frozen'] ?? '') === '1') ? 'selected' : '' ?>>Frozen</option>
                <option value="0" <?= (($filters['is_frozen'] ?? '') === '0') ? 'selected' : '' ?>>Active</option>
            </select>
        </div>
        <div class="col-lg-4 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="/admin/wallets">Reset</a>
        </div>
    </form>
</div>

<div class="row g-4">
    <!-- Main Wallets Table -->
    <div class="col-lg-9">
        <div class="glass rounded-4 p-3">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th><th>User</th><th>Currency</th><th>Type</th>
                            <th>Available</th><th>Locked</th><th>Frozen</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wallets as $w): ?>
                        <tr>
                            <td class="text-secondary small"><?= (int)($w['id'] ?? 0) ?></td>
                            <td>
                                <div class="small fw-semibold"><?= e((string)($w['username'] ?? '-')) ?></div>
                                <div class="small text-secondary"><?= e((string)($w['email'] ?? '')) ?></div>
                            </td>
                            <td>
                                <strong><?= e((string)($w['currency_code'] ?? '-')) ?></strong>
                                <div class="small text-secondary"><?= e((string)($w['currency_name'] ?? '')) ?></div>
                            </td>
                            <td><span class="badge text-bg-secondary"><?= e(ucfirst((string)($w['wallet_type'] ?? '-'))) ?></span></td>
                            <td><?= number_format((float)($w['available_balance'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($w['locked_balance'] ?? 0), 8) ?></td>
                            <td>
                                <?php if ((int)($w['is_frozen'] ?? 0)): ?>
                                    <span class="badge text-bg-danger" title="<?= e((string)($w['freeze_reason'] ?? '')) ?>">Frozen</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="/admin/wallet/ledger?id=<?= (int)$w['id'] ?>" class="btn btn-xs btn-outline-info me-1">Ledger</a>
                                <?php if ((int)($w['is_frozen'] ?? 0)): ?>
                                    <button class="btn btn-xs btn-outline-success" onclick="unfreezeWallet(<?= (int)$w['id'] ?>)">Unfreeze</button>
                                <?php else: ?>
                                    <button class="btn btn-xs btn-outline-warning" onclick="openFreezeModal(<?= (int)$w['id'] ?>)">Freeze</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($wallets === []): ?>
                        <tr><td colspan="8" class="text-center text-secondary">No wallets found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Wallets Sidebar -->
    <div class="col-lg-3">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Top Balances</h2>
            <div class="d-flex flex-column gap-2">
            <?php foreach (array_slice($topWallets, 0, 10) as $tw): ?>
                <div class="d-flex justify-content-between align-items-center small">
                    <div>
                        <div class="fw-semibold"><?= e((string)($tw['username'] ?? '-')) ?></div>
                        <div class="text-secondary"><?= e((string)($tw['currency_code'] ?? '-')) ?></div>
                    </div>
                    <div class="text-end">
                        <div><?= number_format((float)($tw['available_balance'] ?? 0), 4) ?></div>
                        <?php if ((int)($tw['is_frozen'] ?? 0)): ?>
                            <span class="badge text-bg-danger" style="font-size:9px">Frozen</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Freeze Modal -->
<div class="modal fade" id="freezeModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/wallets/freeze" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="wallet_id" id="freezeWalletId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Freeze Wallet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Freeze Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="reason" rows="3" placeholder="Reason for freezing..." required></textarea>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Freeze Wallet</button>
            </div>
        </form>
    </div>
</div>

<form id="unfreezeForm" data-ajax="true" action="/admin/wallets/unfreeze" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="wallet_id" id="unfreezeWalletId">
</form>

<script>
function openFreezeModal(walletId) {
    document.getElementById('freezeWalletId').value = walletId;
    new bootstrap.Modal(document.getElementById('freezeModal')).show();
}

function unfreezeWallet(walletId) {
    if (!confirm('Unfreeze this wallet?')) return;
    document.getElementById('unfreezeWalletId').value = walletId;
    $('#unfreezeForm').trigger('submit');
}
</script>
