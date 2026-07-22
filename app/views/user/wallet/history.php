<?php declare(strict_types=1); ?>
<?php
$deposits    = is_array($deposits    ?? null) ? $deposits    : [];
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
$transfers   = is_array($transfers   ?? null) ? $transfers   : [];
$userId = (int)(\App\Libraries\Session::get('auth.user_id') ?? 0);
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="fas fa-history me-2 text-info"></i>Transaction History</h1>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDeposits">
            <i class="fas fa-arrow-down me-1 text-success"></i>Deposits
            <span class="badge bg-secondary ms-1"><?= count($deposits) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWithdrawals">
            <i class="fas fa-arrow-up me-1 text-warning"></i>Withdrawals
            <span class="badge bg-secondary ms-1"><?= count($withdrawals) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTransfers">
            <i class="fas fa-exchange-alt me-1 text-info"></i>Transfers
            <span class="badge bg-secondary ms-1"><?= count($transfers) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Deposits Tab -->
    <div class="tab-pane fade show active" id="tabDeposits">
        <div class="glass rounded-4 p-4">
            <div class="table-responsive">
                <table id="depositsHistoryTable" class="table table-user table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Currency</th><th>Amount</th><th>TxHash</th>
                            <th>Confirmations</th><th>Status</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deposits as $dep): ?>
                        <tr>
                            <td class="text-secondary small"><?= (int)($dep['id'] ?? 0) ?></td>
                            <td><span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary">
                                <?php if (!empty($dep['tx_hash'])): ?>
                                    <span title="<?= e((string)$dep['tx_hash']) ?>"><?= e(substr((string)$dep['tx_hash'], 0, 20)) ?>…</span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary"><?= (int)($dep['confirmations'] ?? 0) ?></span></td>
                            <td>
                                <?php
                                $ds = (string)($dep['status'] ?? 'pending');
                                $dc = match($ds) { 'credited'=>'success','confirmed'=>'info','failed'=>'danger','flagged'=>'warning text-dark',default=>'secondary' };
                                ?>
                                <span class="badge bg-<?= $dc ?>"><?= e($ds) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e(substr((string)($dep['created_at'] ?? ''), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($deposits === []): ?><tr><td colspan="7" class="text-center text-secondary py-4">No deposits yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Withdrawals Tab -->
    <div class="tab-pane fade" id="tabWithdrawals">
        <div class="glass rounded-4 p-4">
            <div class="table-responsive">
                <table id="withdrawalsHistoryTable" class="table table-user table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Currency</th><th>Amount</th><th>Fee</th>
                            <th>Address</th><th>TxHash</th><th>Status</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($withdrawals as $wd): ?>
                        <tr>
                            <td class="text-secondary small"><?= (int)($wd['id'] ?? 0) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($wd['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary"><?= number_format((float)($wd['fee'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary" style="max-width:120px;overflow:hidden;text-overflow:ellipsis">
                                <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 20)) ?>…
                            </td>
                            <td class="font-monospace small text-secondary">
                                <?php if (!empty($wd['tx_hash'])): ?>
                                    <span title="<?= e((string)$wd['tx_hash']) ?>"><?= e(substr((string)$wd['tx_hash'], 0, 16)) ?>…</span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $ws = (string)($wd['status'] ?? 'pending');
                                $wc = match($ws) { 'completed'=>'success','rejected','cancelled'=>'danger','processing'=>'info','approved'=>'primary',default=>'warning text-dark' };
                                ?>
                                <span class="badge bg-<?= $wc ?>"><?= e($ws) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e(substr((string)($wd['requested_at'] ?? $wd['created_at'] ?? ''), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($withdrawals === []): ?><tr><td colspan="8" class="text-center text-secondary py-4">No withdrawals yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Transfers Tab -->
    <div class="tab-pane fade" id="tabTransfers">
        <div class="glass rounded-4 p-4">
            <div class="table-responsive">
                <table id="transfersHistoryTable" class="table table-user table-sm">
                    <thead>
                        <tr><th>#</th><th>Type</th><th>Currency</th><th>Amount</th><th>From</th><th>To</th><th>Note</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transfers as $t):
                        $isSender = (int)$t['from_user_id'] === $userId;
                    ?>
                        <tr>
                            <td class="text-secondary small"><?= (int)$t['id'] ?></td>
                            <td>
                                <span class="badge bg-<?= $isSender ? 'danger' : 'success' ?>">
                                    <i class="fas fa-arrow-<?= $isSender ? 'up' : 'down' ?> me-1"></i>
                                    <?= $isSender ? 'Sent' : 'Received' ?>
                                </span>
                            </td>
                            <td><span class="badge bg-secondary"><?= e((string)($t['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($t['amount'] ?? 0), 8) ?></td>
                            <td class="small"><?= e((string)($t['from_username'] ?? '-')) ?></td>
                            <td class="small"><?= e((string)($t['to_username']   ?? '-')) ?></td>
                            <td class="text-secondary small"><?= e((string)($t['note'] ?? '—')) ?></td>
                            <td><span class="badge bg-<?= ($t['status'] ?? '') === 'completed' ? 'success' : 'secondary' ?>"><?= e((string)($t['status'] ?? '-')) ?></span></td>
                            <td class="small text-secondary"><?= e(substr((string)($t['created_at'] ?? ''), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($transfers === []): ?><tr><td colspan="9" class="text-center text-secondary py-4">No transfers yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#depositsHistoryTable').DataTable({ order:[[0,'desc']], pageLength:15 });
$('#withdrawalsHistoryTable').DataTable({ order:[[0,'desc']], pageLength:15 });
$('#transfersHistoryTable').DataTable({ order:[[0,'desc']], pageLength:15 });
</script>
