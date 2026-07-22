<?php declare(strict_types=1);
$log = is_array($log ?? null) ? $log : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-history me-2 text-secondary"></i>KYC Audit Log</h1>
        <p class="text-secondary mb-0">Complete audit trail of all KYC events.</p>
    </div>
    <a href="/admin/kyc" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to KYC</a>
</div>

<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="glass rounded-3 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle small mb-0">
            <thead class="text-secondary">
                <tr>
                    <th>Date / Time</th>
                    <th>User</th>
                    <th>Document Type</th>
                    <th>Action</th>
                    <th>Status Change</th>
                    <th>Actor</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($log as $entry):
                $ns = (string)($entry['new_status'] ?? '');
                $nc = match($ns) { 'approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning', default => 'secondary' };
            ?>
                <tr>
                    <td class="text-secondary font-monospace"><?= e(date('M d, H:i', strtotime((string)($entry['created_at']??'now')))) ?></td>
                    <td>
                        <a href="/admin/kyc/user?user_id=<?= (int)($entry['user_id']??0) ?>" class="text-light fw-semibold">
                            <?= e((string)($entry['username'] ?? '-')) ?>
                        </a>
                    </td>
                    <td><?= e(ucwords(str_replace('_',' ',(string)($entry['document_type']??'-')))) ?></td>
                    <td>
                        <?php
                        $action = (string)($entry['action'] ?? '');
                        $actionColor = match($action) {
                            'approved'  => 'success',
                            'rejected'  => 'danger',
                            'submit'    => 'info',
                            're_request'=> 'warning',
                            'delete'    => 'secondary',
                            default     => 'light',
                        };
                        ?>
                        <span class="badge bg-<?= $actionColor ?>"><?= e(ucwords(str_replace('_',' ',$action))) ?></span>
                    </td>
                    <td>
                        <span class="text-secondary"><?= e((string)($entry['old_status']??'')) ?></span>
                        <?php if (!empty($ns)): ?>
                        <i class="fas fa-arrow-right mx-1 text-secondary" style="font-size:.6rem"></i>
                        <span class="text-<?= $nc ?>"><?= e($ns) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-secondary">
                        <?= e((string)($entry['actor_name'] ?? ucfirst((string)($entry['actor_type']??'')))) ?>
                    </td>
                    <td class="text-secondary" style="max-width:200px">
                        <?= e(mb_strimwidth((string)($entry['notes']??''), 0, 60, '…')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($log)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-4">No audit entries yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
