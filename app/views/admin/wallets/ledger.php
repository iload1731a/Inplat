<?php declare(strict_types=1); ?>
<?php
$wallet = is_array($wallet ?? null) ? $wallet : [];
$ledger = is_array($ledger ?? null) ? $ledger : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Wallet Ledger</h1>
        <p class="text-secondary mb-0">
            <?= e((string)($wallet['username'] ?? '-')) ?> · <?= e((string)($wallet['currency_code'] ?? '-')) ?>
            <?php if ((int)($wallet['is_frozen'] ?? 0)): ?><span class="badge text-bg-danger ms-2">FROZEN</span><?php endif; ?>
        </p>
    </div>
    <a href="/admin/wallets" class="btn btn-outline-secondary btn-sm">← Back to Wallets</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Available</div><div class="h5 mb-0 text-success"><?= number_format((float)($wallet['available_balance'] ?? 0), 8) ?></div></div></div>
    <div class="col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Locked</div><div class="h5 mb-0 text-warning"><?= number_format((float)($wallet['locked_balance'] ?? 0), 8) ?></div></div></div>
    <div class="col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total Deposited</div><div class="h5 mb-0"><?= number_format((float)($wallet['total_deposited'] ?? 0), 8) ?></div></div></div>
    <div class="col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total Withdrawn</div><div class="h5 mb-0"><?= number_format((float)($wallet['total_withdrawn'] ?? 0), 8) ?></div></div></div>
</div>

<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead>
                <tr><th>ID</th><th>Type</th><th>Amount</th><th>Running Balance</th><th>Reference</th><th>Description</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ledger as $entry): ?>
                <?php $isCredit = in_array($entry['entry_type'] ?? '', ['deposit','credit','trade_proceeds','reward'], true); ?>
                <tr>
                    <td class="text-secondary small"><?= (int)($entry['id'] ?? 0) ?></td>
                    <td><span class="badge text-bg-<?= $isCredit ? 'success' : 'danger' ?>"><?= e((string)($entry['entry_type'] ?? '-')) ?></span></td>
                    <td class="<?= $isCredit ? 'text-success' : 'text-danger' ?>"><?= $isCredit ? '+' : '-' ?><?= number_format(abs((float)($entry['amount'] ?? 0)), 8) ?></td>
                    <td><?= number_format((float)($entry['running_balance'] ?? 0), 8) ?></td>
                    <td class="small text-secondary"><?= e((string)($entry['reference_type'] ?? '-')) ?> #<?= e((string)($entry['reference_id'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($entry['description'] ?? '')) ?></td>
                    <td class="text-secondary small"><?= e((string)($entry['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($ledger === []): ?>
                <tr><td colspan="7" class="text-center text-secondary">No ledger entries.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
