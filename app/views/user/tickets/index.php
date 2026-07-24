<?php declare(strict_types=1); ?>
<?php
$tickets = is_array($tickets ?? null) ? $tickets : [];
$error   = (string)($error ?? '');
require app_path('app/views/user/_nav.php');
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="fas fa-headset me-2 text-info"></i>Support Tickets</h5>
        <a href="/user/tickets/create" class="btn btn-info btn-sm">
            <i class="fas fa-plus me-1"></i>New Ticket
        </a>
    </div>
    <div class="table-responsive">
        <table id="ticketsTable" class="table table-user table-sm">
            <thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Last Update</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td class="small"><?= (int)($ticket['id'] ?? 0) ?></td>
                    <td><?= e(substr((string)($ticket['subject'] ?? ''), 0, 60)) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($ticket['category'] ?? '-')) ?></span></td>
                    <td>
                        <?php
                        $pri = (string)($ticket['priority'] ?? 'normal');
                        $pc  = match($pri) { 'urgent' => 'danger', 'high' => 'warning', 'normal' => 'info', default => 'secondary' };
                        ?>
                        <span class="badge bg-<?= $pc ?>"><?= e($pri) ?></span>
                    </td>
                    <td>
                        <?php
                        $ts = (string)($ticket['status'] ?? 'open');
                        $tc = match($ts) { 'resolved', 'closed' => 'success', 'in_progress' => 'info', default => 'warning' };
                        ?>
                        <span class="badge bg-<?= $tc ?>"><?= e($ts) ?></span>
                    </td>
                    <td class="small"><?= e(date('M d, Y H:i', strtotime((string)($ticket['updated_at'] ?? 'now')))) ?></td>
                    <td>
                        <a href="/user/tickets/view?id=<?= (int)$ticket['id'] ?>" class="btn btn-xs btn-outline-info">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($tickets === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">
                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>No tickets yet. Need help? Open a ticket!
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#ticketsTable').DataTable({ order: [[0,'desc']], pageLength: 15 });
</script>
