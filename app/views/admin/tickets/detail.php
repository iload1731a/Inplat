<?php declare(strict_types=1); ?>
<?php
$ticket      = is_array($ticket      ?? null) ? $ticket      : [];
$messages    = is_array($messages    ?? null) ? $messages    : [];
$notes       = is_array($notes       ?? null) ? $notes       : [];
$tags        = is_array($tags        ?? null) ? $tags        : [];
$allTags     = is_array($allTags     ?? null) ? $allTags     : [];
$history     = is_array($history     ?? null) ? $history     : [];
$admins      = is_array($admins      ?? null) ? $admins      : [];
$attachments = is_array($attachments ?? null) ? $attachments : [];

$ticketId  = (int)($ticket['id'] ?? 0);
$isClosed  = in_array((string)($ticket['status'] ?? 'open'), ['resolved','closed'], true);
$csrfToken = \App\Libraries\Csrf::token();

$priColor = match((string)($ticket['priority'] ?? 'medium')) {
    'urgent' => 'danger', 'high' => 'warning', 'low' => 'secondary', default => 'info'
};
$stColor = match((string)($ticket['status'] ?? 'open')) {
    'resolved','closed' => 'success', 'in_progress' => 'primary', 'waiting_on_user' => 'info', default => 'warning'
};
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="/admin/tickets/list" class="btn btn-outline-secondary btn-sm me-2">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <span class="text-secondary small"><?= e((string)($ticket['ticket_number'] ?? '#')) ?></span>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-<?= $priColor ?> fs-6"><?= e(ucfirst((string)($ticket['priority'] ?? 'medium'))) ?></span>
        <span class="badge bg-<?= $stColor ?> fs-6"><?= e(str_replace('_',' ',ucfirst((string)($ticket['status'] ?? 'open')))) ?></span>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT SIDEBAR: ticket meta + user + actions -->
    <div class="col-xl-3 col-lg-4">

        <!-- Ticket Info -->
        <div class="glass rounded-4 p-3 mb-3">
            <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Ticket Info</h6>
            <table class="table table-sm table-dark mb-0">
                <tbody>
                    <tr><th class="text-secondary fw-normal small" style="width:45%">Subject</th>
                        <td class="small"><?= e((string)($ticket['subject'] ?? '')) ?></td></tr>
                    <tr><th class="text-secondary fw-normal small">Category</th>
                        <td><span class="badge bg-secondary"><?= e((string)($ticket['category'] ?? '-')) ?></span></td></tr>
                    <tr><th class="text-secondary fw-normal small">Created</th>
                        <td class="small"><?= e(date('M d, Y H:i', strtotime((string)($ticket['created_at'] ?? 'now')))) ?></td></tr>
                    <tr><th class="text-secondary fw-normal small">Updated</th>
                        <td class="small"><?= e(date('M d, Y H:i', strtotime((string)($ticket['updated_at'] ?? 'now')))) ?></td></tr>
                    <?php if (!empty($ticket['closed_at'])): ?>
                    <tr><th class="text-secondary fw-normal small">Closed</th>
                        <td class="small"><?= e(date('M d, Y H:i', strtotime((string)$ticket['closed_at']))) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($ticket['csat_rating'])): ?>
                    <tr><th class="text-secondary fw-normal small">CSAT</th>
                        <td><span class="text-warning"><?= str_repeat('★', (int)$ticket['csat_rating']) ?></span></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- User Info -->
        <div class="glass rounded-4 p-3 mb-3">
            <h6 class="mb-3"><i class="fas fa-user me-2 text-primary"></i>User</h6>
            <div class="small"><strong><?= e((string)($ticket['username'] ?? '-')) ?></strong></div>
            <div class="small text-secondary mb-1"><?= e((string)($ticket['email'] ?? '')) ?></div>
            <div class="d-flex gap-1 flex-wrap">
                <span class="badge bg-secondary"><?= e((string)($ticket['account_type'] ?? '-')) ?></span>
                <?php
                $kycBadge = match((string)($ticket['kyc_status'] ?? 'unverified')) {
                    'approved' => 'success', 'pending' => 'warning', default => 'secondary'
                };
                ?>
                <span class="badge bg-<?= $kycBadge ?>">KYC: <?= e((string)($ticket['kyc_status'] ?? 'unverified')) ?></span>
            </div>
            <?php if (!empty($ticket['first_name'])): ?>
            <div class="small text-secondary mt-1"><?= e((string)$ticket['first_name']) . ' ' . e((string)($ticket['last_name'] ?? '')) ?></div>
            <?php endif; ?>
        </div>

        <!-- Update Ticket Form -->
        <div class="glass rounded-4 p-3 mb-3">
            <h6 class="mb-3"><i class="fas fa-edit me-2 text-warning"></i>Update Ticket</h6>
            <form data-ajax="true" action="/admin/tickets/update" method="POST">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <div class="mb-2">
                    <label class="form-label small text-secondary">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach (['open','in_progress','waiting_on_user','resolved','closed'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= ($ticket['status'] ?? '') === $s ? 'selected' : '' ?>>
                                <?= e(ucfirst(str_replace('_',' ',$s))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-secondary">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        <?php foreach (['low','medium','high','urgent'] as $p): ?>
                            <option value="<?= e($p) ?>" <?= ($ticket['priority'] ?? '') === $p ? 'selected' : '' ?>>
                                <?= e(ucfirst($p)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-secondary">Assign To</label>
                    <select name="assigned_to" class="form-select form-select-sm">
                        <option value="0">Unassigned</option>
                        <?php foreach ($admins as $adm): ?>
                            <option value="<?= (int)($adm['id'] ?? 0) ?>" <?= (int)($ticket['assigned_to'] ?? 0) === (int)($adm['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= e((string)($adm['display_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning btn-sm w-100">Save Changes</button>
            </form>
        </div>

        <!-- Tags -->
        <div class="glass rounded-4 p-3 mb-3">
            <h6 class="mb-2"><i class="fas fa-tags me-2 text-success"></i>Tags</h6>
            <div class="d-flex flex-wrap gap-1 mb-2" id="ticketTagsDisplay">
                <?php foreach ($tags as $tag): ?>
                    <span class="badge bg-<?= e((string)($tag['color'] ?? 'secondary')) ?>"><?= e((string)($tag['name'] ?? '')) ?></span>
                <?php endforeach; ?>
                <?php if ($tags === []): ?><span class="text-secondary small">No tags</span><?php endif; ?>
            </div>
            <form data-ajax="true" action="/admin/tickets/tags/update" method="POST">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <select name="tag_ids[]" class="form-select form-select-sm mb-2" multiple size="4">
                    <?php
                    $selectedTagIds = array_column($tags, 'id');
                    foreach ($allTags as $t):
                    ?>
                        <option value="<?= (int)($t['id'] ?? 0) ?>" <?= in_array((int)($t['id'] ?? 0), array_map('intval', $selectedTagIds), true) ? 'selected' : '' ?>>
                            <?= e((string)($t['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-success btn-sm w-100">Update Tags</button>
            </form>
        </div>

        <!-- User's other tickets -->
        <?php if ($history !== []): ?>
        <div class="glass rounded-4 p-3">
            <h6 class="mb-2"><i class="fas fa-history me-2 text-secondary"></i>Previous Tickets</h6>
            <?php foreach ($history as $h): ?>
                <?php
                $hst  = (string)($h['status'] ?? 'open');
                $hsc  = match($hst){ 'resolved','closed'=>'success', default=>'warning' };
                ?>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <a href="/admin/tickets/detail?id=<?= (int)($h['id'] ?? 0) ?>" class="small text-info text-truncate" style="max-width:130px">
                        <?= e((string)($h['ticket_number'] ?? '#')) ?> <?= e(substr((string)($h['subject'] ?? ''), 0, 25)) ?>
                    </a>
                    <span class="badge bg-<?= $hsc ?> ms-1" style="font-size:.65rem"><?= e($hst) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- MAIN: conversation + reply -->
    <div class="col-xl-6 col-lg-8">

        <!-- Message Thread -->
        <div class="glass rounded-4 p-4 mb-3">
            <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Conversation
                <span class="badge bg-secondary ms-1"><?= count($messages) ?></span>
            </h6>
            <div id="messageThread" style="max-height:500px;overflow-y:auto;" class="mb-4">
                <?php foreach ($messages as $msg): ?>
                    <?php $isUser = ($msg['sender_type'] ?? '') === 'user'; ?>
                    <div class="d-flex gap-3 mb-3 <?= $isUser ? '' : 'flex-row-reverse' ?>">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                 style="width:36px;height:36px;min-width:36px;background:<?= $isUser ? 'linear-gradient(135deg,#38bdf8,#6366f1)' : 'linear-gradient(135deg,#f59e0b,#ef4444)' ?>">
                                <i class="fas <?= $isUser ? 'fa-user' : 'fa-headset' ?> fa-sm"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1" style="max-width:88%">
                            <div class="rounded-3 p-3 <?= $isUser ? 'glass' : 'bg-warning bg-opacity-10 border border-warning border-opacity-25' ?>">
                                <div class="small fw-semibold mb-1 <?= $isUser ? 'text-info' : 'text-warning' ?>">
                                    <?= e((string)($msg['sender_name'] ?? ($isUser ? 'User' : 'Support'))) ?>
                                </div>
                                <div class="small"><?= nl2br(e((string)($msg['message'] ?? ''))) ?></div>
                                <?php if (!empty($msg['attachment_url'])): ?>
                                    <a href="<?= e((string)$msg['attachment_url']) ?>" target="_blank" class="small text-info d-block mt-1">
                                        <i class="fas fa-paperclip me-1"></i>Attachment
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary mt-1 small" style="text-align:<?= $isUser ? 'left' : 'right' ?>">
                                <?= e(date('M d, Y H:i', strtotime((string)($msg['created_at'] ?? 'now')))) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($messages === []): ?>
                    <div class="text-center text-secondary py-4">No messages yet</div>
                <?php endif; ?>
            </div>

            <!-- Reply Form -->
            <form id="replyForm" data-ajax="true" action="/admin/tickets/reply" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <div class="mb-2">
                    <label class="form-label small text-secondary">Reply to User</label>
                    <textarea name="message" class="form-control bg-transparent text-light border-secondary" rows="4"
                              placeholder="Type your reply..." <?= $isClosed ? 'disabled' : '' ?>></textarea>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <select name="status" class="form-select form-select-sm" <?= $isClosed ? 'disabled' : '' ?>>
                            <?php foreach (['in_progress','waiting_on_user','resolved','closed'] as $s): ?>
                                <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="file" name="attachment" class="form-control form-control-sm"
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip" <?= $isClosed ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning btn-sm w-100" <?= $isClosed ? 'disabled' : '' ?>>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                <?php if ($isClosed): ?>
                    <div class="alert alert-secondary small py-2">
                        <i class="fas fa-lock me-1"></i>This ticket is <?= e((string)($ticket['status'] ?? 'closed')) ?>.
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Attachments -->
        <?php if ($attachments !== []): ?>
        <div class="glass rounded-4 p-3">
            <h6 class="mb-2"><i class="fas fa-paperclip me-2 text-info"></i>Attachments (<?= count($attachments) ?>)</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($attachments as $att): ?>
                    <a href="<?= e((string)($att['file_url'] ?? '#')) ?>" target="_blank"
                       class="btn btn-xs btn-outline-info d-flex align-items-center gap-1" style="max-width:180px">
                        <i class="fas fa-file me-1"></i>
                        <span class="text-truncate"><?= e((string)($att['original_name'] ?? 'file')) ?></span>
                        <span class="text-secondary ms-1" style="font-size:.6rem">
                            <?= round((int)($att['file_size'] ?? 0) / 1024, 1) ?>KB
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Internal Notes -->
    <div class="col-xl-3">
        <div class="glass rounded-4 p-3 h-100">
            <h6 class="mb-3"><i class="fas fa-lock me-2 text-danger"></i>Internal Notes
                <span class="badge bg-danger ms-1"><?= count($notes) ?></span>
            </h6>
            <div style="max-height:350px;overflow-y:auto;" class="mb-3">
                <?php foreach ($notes as $note): ?>
                    <div class="glass rounded-3 p-2 mb-2">
                        <div class="small fw-semibold text-danger mb-1"><?= e((string)($note['admin_name'] ?? 'Admin')) ?></div>
                        <div class="small"><?= nl2br(e((string)($note['note'] ?? ''))) ?></div>
                        <div class="text-secondary mt-1" style="font-size:.65rem">
                            <?= e(date('M d H:i', strtotime((string)($note['created_at'] ?? 'now')))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($notes === []): ?><div class="text-secondary small">No notes yet</div><?php endif; ?>
            </div>
            <form data-ajax="true" action="/admin/tickets/note" method="POST">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <textarea name="note" class="form-control form-control-sm bg-transparent text-light border-secondary mb-2"
                          rows="3" placeholder="Private note (not visible to user)…"></textarea>
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fas fa-sticky-note me-1"></i>Add Note
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const thread = document.getElementById('messageThread');
if (thread) thread.scrollTop = thread.scrollHeight;
</script>
