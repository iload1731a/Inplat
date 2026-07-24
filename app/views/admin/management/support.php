<?php declare(strict_types=1); ?>
<?php
$filters = is_array($filters ?? null) ? $filters : [];
$tickets = is_array($tickets ?? null) ? $tickets : [];
$ticket = is_array($ticket ?? null) ? $ticket : [];
$messages = is_array($messages ?? null) ? $messages : [];
$admins = is_array($admins ?? null) ? $admins : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Support Desk</h1>
        <p class="text-secondary mb-0">Assign tickets, manage status, and reply to users from a single workspace.</p>
    </div>
    <a href="/admin/communications" class="btn btn-outline-light btn-sm">Communications</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="glass rounded-4 p-3 h-100">
            <form class="row g-2 mb-3" method="get" action="/admin/support">
                <div class="col-md-4"><input class="form-control" type="text" name="ticket_search" placeholder="Ticket, subject, username" value="<?= e((string)($filters['ticket_search'] ?? '')) ?>"></div>
                <div class="col-md-3"><select class="form-select" name="ticket_status"><option value="">All statuses</option><?php foreach (['open','in_progress','waiting_on_user','resolved','closed'] as $status): ?><option value="<?= e($status) ?>" <?= (($filters['ticket_status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><select class="form-select" name="ticket_priority"><option value="">All priorities</option><?php foreach (['low','medium','high','urgent'] as $priority): ?><option value="<?= e($priority) ?>" <?= (($filters['ticket_priority'] ?? '') === $priority) ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead><tr><th>Ticket</th><th>User</th><th>Status</th><th>Priority</th><th>Assigned</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($tickets as $row): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e((string)($row['ticket_number'] ?? '-')) ?></div><div class="small text-secondary"><?= e((string)($row['subject'] ?? '-')) ?></div></td>
                            <td><?= e((string)($row['username'] ?? '-')) ?></td>
                            <td><?= e((string)($row['status'] ?? '-')) ?></td>
                            <td><?= e((string)($row['priority'] ?? '-')) ?></td>
                            <td><?= e((string)($row['assigned_to_name'] ?? 'Unassigned')) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-info" href="/admin/support?ticket_id=<?= (int)($row['id'] ?? 0) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($tickets === []): ?><tr><td colspan="6" class="text-center text-secondary">No tickets found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Ticket Detail</h2>
            <?php if ($ticket !== []): ?>
                <div class="mb-3">
                    <div class="fw-semibold"><?= e((string)($ticket['ticket_number'] ?? '-')) ?> · <?= e((string)($ticket['subject'] ?? '-')) ?></div>
                    <div class="small text-secondary">User: <?= e((string)($ticket['username'] ?? '-')) ?> · <?= e((string)($ticket['email'] ?? '-')) ?></div>
                </div>
                <form action="/admin/support/update" method="post" data-ajax="true" class="row g-3 mb-4">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <input type="hidden" name="ticket_id" value="<?= (int)($ticket['id'] ?? 0) ?>">
                    <div class="col-md-6"><label class="form-label">Assign To</label><select class="form-select" name="assigned_to"><option value="0">Unassigned</option><?php foreach ($admins as $row): ?><option value="<?= (int)($row['id'] ?? 0) ?>" <?= ((int)($ticket['assigned_to'] ?? 0) === (int)($row['id'] ?? 0)) ? 'selected' : '' ?>><?= e((string)($row['display_name'] ?? '-')) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['open','in_progress','waiting_on_user','resolved','closed'] as $status): ?><option value="<?= e($status) ?>" <?= (($ticket['status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12 d-flex justify-content-end"><button class="btn btn-outline-warning" type="submit">Save Ticket</button></div>
                </form>
                <div class="border rounded-4 border-secondary-subtle p-3 mb-3" style="max-height: 280px; overflow:auto;">
                    <?php foreach ($messages as $message): ?>
                        <div class="mb-3">
                            <div class="small text-secondary text-uppercase"><?= e((string)($message['sender_type'] ?? '-')) ?> · <?= e((string)($message['created_at'] ?? '-')) ?></div>
                            <div><?= nl2br(e((string)($message['message'] ?? ''))) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($messages === []): ?><div class="text-secondary">No messages yet.</div><?php endif; ?>
                </div>
                <form action="/admin/support/reply" method="post" data-ajax="true" class="row g-3">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <input type="hidden" name="ticket_id" value="<?= (int)($ticket['id'] ?? 0) ?>">
                    <div class="col-12"><label class="form-label">Reply</label><textarea class="form-control" name="message" rows="5" required></textarea></div>
                    <div class="col-12"><label class="form-label">New Status</label><select class="form-select" name="status"><?php foreach (['in_progress','waiting_on_user','resolved','closed'] as $status): ?><option value="<?= e($status) ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit">Post Reply</button></div>
                </form>
            <?php else: ?>
                <div class="text-secondary">Select a ticket from the list to assign and reply.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
