<?php declare(strict_types=1); ?>
<?php
$wallet   = is_array($wallet ?? null) ? $wallet : [];
$items    = is_array($items  ?? null) ? $items  : [];
$total    = (int)($total     ?? 0);
$page     = (int)($page      ?? 1);
$perPage  = (int)($per_page  ?? 50);
$lastPage = (int)($last_page ?? 1);
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">
            <i class="fas fa-book me-2 text-info"></i>Wallet Ledger
        </h1>
        <p class="text-secondary mb-0">
            <?= e((string)($wallet['code'] ?? '-')) ?> — <?= e((string)($wallet['currency_name'] ?? '')) ?>
            · <?= e(ucfirst((string)($wallet['wallet_type'] ?? 'spot'))) ?> wallet
        </p>
    </div>
    <a href="/user/wallet" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Wallets
    </a>
</div>

<!-- Balance Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="text-secondary small">Available Balance</div>
            <div class="h5 fw-bold text-success font-monospace">
                <?= number_format((float)($wallet['available_balance'] ?? 0), 8) ?>
                <span class="text-secondary small"><?= e((string)($wallet['code'] ?? '')) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="text-secondary small">Locked Balance</div>
            <div class="h5 fw-bold text-warning font-monospace">
                <?= number_format((float)($wallet['locked_balance'] ?? 0), 8) ?>
                <span class="text-secondary small"><?= e((string)($wallet['code'] ?? '')) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="text-secondary small">Total Entries</div>
            <div class="h5 fw-bold text-info"><?= number_format($total) ?></div>
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-user table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Direction</th>
                    <th>Type</th>
                    <th>Ref ID</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Notes</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $entry): ?>
                <tr>
                    <td class="text-secondary small"><?= (int)$entry['id'] ?></td>
                    <td>
                        <span class="badge bg-<?= ($entry['direction'] ?? '') === 'credit' ? 'success' : 'danger' ?>">
                            <i class="fas fa-arrow-<?= ($entry['direction'] ?? '') === 'credit' ? 'down' : 'up' ?> me-1"></i>
                            <?= ucfirst((string)($entry['direction'] ?? '-')) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-secondary text-capitalize">
                            <?= e(str_replace('_', ' ', (string)($entry['reference_type'] ?? '-'))) ?>
                        </span>
                    </td>
                    <td class="text-secondary small"><?= ($entry['reference_id'] ?? 0) > 0 ? '#' . (int)$entry['reference_id'] : '—' ?></td>
                    <td class="font-monospace <?= ($entry['direction'] ?? '') === 'credit' ? 'text-success' : 'text-danger' ?>">
                        <?= ($entry['direction'] ?? '') === 'credit' ? '+' : '-' ?>
                        <?= number_format((float)($entry['amount'] ?? 0), 8) ?>
                    </td>
                    <td class="font-monospace small"><?= number_format((float)($entry['balance_after'] ?? 0), 8) ?></td>
                    <td class="text-secondary small"><?= e((string)($entry['notes'] ?? '—')) ?></td>
                    <td class="small text-secondary"><?= e(substr((string)($entry['created_at'] ?? ''), 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr>
                    <td colspan="8" class="text-center text-secondary py-5">
                        <i class="fas fa-book fa-2x mb-2 d-block opacity-50"></i>
                        No ledger entries yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($lastPage > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?wallet_id=<?= (int)($wallet['id'] ?? 0) ?>&page=<?= $page - 1 ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?wallet_id=<?= (int)($wallet['id'] ?? 0) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $lastPage): ?>
                <li class="page-item">
                    <a class="page-link" href="?wallet_id=<?= (int)($wallet['id'] ?? 0) ?>&page=<?= $page + 1 ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
