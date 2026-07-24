<?php declare(strict_types=1); ?>
<?php
$ticket   = is_array($ticket   ?? null) ? $ticket   : [];
$messages = is_array($messages ?? null) ? $messages : [];
$ticketId = (int)($ticket['id'] ?? 0);
$isClosed = in_array((string)($ticket['status'] ?? 'open'), ['resolved', 'closed'], true);
$csrfToken = \App\Libraries\Csrf::token();
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Ticket Details -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-ticket me-2 text-info"></i>Ticket #<?= $ticketId ?></h5>
            <table class="table table-user table-sm mb-0">
                <tbody>
                    <tr><th class="text-secondary fw-normal small" width="40%">Subject</th><td class="small"><?= e((string)($ticket['subject'] ?? '')) ?></td></tr>
                    <tr>
                        <th class="text-secondary fw-normal small">Status</th>
                        <td>
                            <?php
                            $ts = (string)($ticket['status'] ?? 'open');
                            $tc = match($ts) { 'resolved', 'closed' => 'success', 'in_progress' => 'primary', 'waiting_on_user' => 'info', default => 'warning' };
                            ?>
                            <span class="badge bg-<?= $tc ?>"><?= e(str_replace('_',' ',$ts)) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-normal small">Priority</th>
                        <td>
                            <?php
                            $pri = (string)($ticket['priority'] ?? 'medium');
                            $pc  = match($pri) { 'urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', default => 'secondary' };
                            ?>
                            <span class="badge bg-<?= $pc ?>"><?= e($pri) ?></span>
                        </td>
                    </tr>
                    <tr><th class="text-secondary fw-normal small">Category</th><td class="small"><?= e((string)($ticket['category'] ?? '')) ?></td></tr>
                    <tr><th class="text-secondary fw-normal small">Created</th><td class="small"><?= e(date('M d, Y', strtotime((string)($ticket['created_at'] ?? 'now')))) ?></td></tr>
                    <tr><th class="text-secondary fw-normal small">Updated</th><td class="small"><?= e(date('M d, Y', strtotime((string)($ticket['updated_at'] ?? 'now')))) ?></td></tr>
                </tbody>
            </table>

            <a href="/user/tickets" class="btn btn-outline-secondary btn-sm mt-3 w-100">
                <i class="fas fa-arrow-left me-1"></i>Back to Tickets
            </a>

            <?php if (!$isClosed): ?>
            <form data-ajax="true" action="/user/tickets/close" method="POST" class="mt-2"
                  onsubmit="return confirm('Close this ticket?')">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fas fa-times-circle me-1"></i>Close Ticket
                </button>
            </form>
            <?php endif; ?>

            <?php if ($isClosed && empty($ticket['csat_rating'])): ?>
            <!-- CSAT Rating Form -->
            <div class="mt-3 p-3 glass rounded-3">
                <div class="small text-secondary mb-2"><i class="fas fa-star me-1 text-warning"></i>Rate your experience</div>
                <form data-ajax="true" action="/user/tickets/rate" method="POST" id="csatForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                    <input type="hidden" name="rating" id="csatRating" value="">
                    <div class="d-flex gap-2 mb-2" id="starBtns">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="btn btn-xs btn-outline-warning star-btn" data-val="<?= $i ?>" onclick="setCsat(<?= $i ?>)">
                                <?= $i ?>★
                            </button>
                        <?php endfor; ?>
                    </div>
                    <textarea name="comment" class="form-control form-control-sm bg-transparent text-light border-secondary mb-2"
                              rows="2" placeholder="Optional comment…"></textarea>
                    <button type="submit" class="btn btn-warning btn-sm w-100" id="csatSubmit" disabled>Submit Rating</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-comments me-2"></i>Conversation</h5>
            <div id="messageThread" style="max-height:450px;overflow-y:auto;" class="mb-4">
                <?php foreach ($messages as $msg): ?>
                    <?php $isUser = ($msg['sender_type'] ?? '') === 'user'; ?>
                    <div class="d-flex gap-3 mb-3 <?= $isUser ? 'flex-row-reverse' : '' ?>">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                 style="width:36px;height:36px;background:<?= $isUser ? 'linear-gradient(135deg,#38bdf8,#6366f1)' : 'linear-gradient(135deg,#f59e0b,#ef4444)' ?>">
                                <i class="fas <?= $isUser ? 'fa-user' : 'fa-headset' ?> fa-sm"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1" style="max-width:85%">
                            <div class="rounded-3 p-3 <?= $isUser ? 'bg-info bg-opacity-10 border border-info border-opacity-25' : 'glass' ?>">
                                <div class="small"><?= nl2br(e((string)($msg['message'] ?? ''))) ?></div>
                                <?php if (!empty($msg['attachment_url'])): ?>
                                    <a href="<?= e((string)$msg['attachment_url']) ?>" target="_blank" class="small text-info d-block mt-1">
                                        <i class="fas fa-paperclip me-1"></i>Attachment
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary mt-1" style="font-size:.7rem; text-align:<?= $isUser ? 'right' : 'left' ?>">
                                <?= $isUser ? 'You' : 'Support Team' ?> &bull;
                                <?= e(date('M d, H:i', strtotime((string)($msg['created_at'] ?? 'now')))) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$isClosed): ?>
            <form id="replyForm" data-ajax="true" action="/user/tickets/reply" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Your Reply</label>
                    <textarea name="message" class="form-control bg-transparent text-light border-secondary" rows="3"
                              placeholder="Type your reply..." minlength="2" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Attachment <span class="text-muted">(optional)</span></label>
                    <input type="file" name="attachment" class="form-control bg-transparent text-light border-secondary"
                           accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip">
                </div>
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fas fa-paper-plane me-1"></i>Send Reply
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-secondary">
                <i class="fas fa-lock me-2"></i>This ticket is <?= e((string)($ticket['status'] ?? 'closed')) ?>. No further replies are accepted.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-scroll to bottom of message thread
const thread = document.getElementById('messageThread');
if (thread) thread.scrollTop = thread.scrollHeight;

$('#replyForm').on('ajax:success', function (e, r) {
    if (r.ok) {
        Swal.fire({ icon: 'success', title: 'Sent!', timer: 1000, showConfirmButton: false })
            .then(() => location.reload());
    }
});

function setCsat(val) {
    document.getElementById('csatRating').value = val;
    document.getElementById('csatSubmit').disabled = false;
    document.querySelectorAll('.star-btn').forEach(function(btn) {
        var v = parseInt(btn.dataset.val);
        btn.classList.toggle('btn-warning', v <= val);
        btn.classList.toggle('btn-outline-warning', v > val);
    });
}

$('#csatForm').on('ajax:success', function(e, r) {
    if (r.ok) {
        Swal.fire({ icon: 'success', title: 'Thank you!', text: r.message, timer: 2000, showConfirmButton: false })
            .then(() => location.reload());
    }
});
</script>