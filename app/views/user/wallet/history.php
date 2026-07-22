<?php declare(strict_types=1); ?>
<?php
$deposits    = is_array($deposits    ?? null) ? $deposits    : [];
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
require app_path('app/views/user/_nav.php');
?>

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
</ul>

<div class="tab-content">
    <!-- Deposits Tab -->
    <div class="tab-pane fade show active" id="tabDeposits">
        <div class="glass rounded-4 p-4">
            <div class="table-responsive">
                <table id="depositsHistoryTable" class="table table-user table-sm">
                    <thead>
                        <tr><th>#</th><th>Currency</th><th>Amount</th><th>Net Amount</th><th>Fee</th><th>Method</th><th>Reference</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deposits as $dep): ?>
                        <tr>
                            <td><?= (int)($dep['id'] ?? 0) ?></td>
                            <td><span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-success"><?= number_format((float)($dep['net_amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary"><?= number_format((float)($dep['fee'] ?? 0), 8) ?></td>
                            <td class="small"><?= e((string)($dep['method'] ?? '-')) ?></td>
                            <td class="font-monospace small text-secondary"><?= e(substr((string)($dep['reference'] ?? ''), 0, 20)) ?></td>
                            <td>
                                <?php
                                $ds = (string)($dep['status'] ?? 'pending');
                                $dc = match($ds) { 'completed' => 'success', 'failed', 'cancelled' => 'danger', 'confirming' => 'info', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $dc ?>"><?= e($ds) ?></span>
                            </td>
                            <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($dep['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
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
                        <tr><th>#</th><th>Currency</th><th>Amount</th><th>Net Amount</th><th>Fee</th><th>Destination</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($withdrawals as $wd): ?>
                        <tr>
                            <td><?= (int)($wd['id'] ?? 0) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= e((string)($wd['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($wd['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($wd['net_amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary"><?= number_format((float)($wd['fee'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary" title="<?= e((string)($wd['destination_address'] ?? '')) ?>">
                                <?= e(substr((string)($wd['destination_address'] ?? '-'), 0, 16)) ?>…
                            </td>
                            <td>
                                <?php
                                $ws = (string)($wd['status'] ?? 'pending');
                                $wc = match($ws) { 'completed' => 'success', 'failed', 'cancelled', 'rejected' => 'danger', 'processing' => 'info', default => 'warning' };
                                ?>
                                <span class="badge bg-<?= $wc ?>"><?= e($ws) ?></span>
                            </td>
                            <td class="small"><?= e(date('Y-m-d H:i', strtotime((string)($wd['created_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#depositsHistoryTable').DataTable({ order: [[0,'desc']], pageLength: 20 });
$('#withdrawalsHistoryTable').DataTable({ order: [[0,'desc']], pageLength: 20 });
</script>
