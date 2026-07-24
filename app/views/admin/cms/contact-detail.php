<?php declare(strict_types=1); ?>
<?php
$message = is_array($message ?? null) ? $message : [];
$csrf    = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-envelope-open me-2 text-info"></i>Contact Message</h1>
    </div>
    <a href="/admin/cms/contact" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Messages
    </a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4 mb-4">
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0"><?= e((string)($message['subject'] ?? 'No subject')) ?></h5>
                    <?php $st = (string)($message['status'] ?? 'unread'); ?>
                    <span class="badge text-bg-<?= ['unread'=>'danger','read'=>'secondary','replied'=>'success','spam'=>'warning'][$st] ?? 'secondary' ?>">
                        <?= e(ucfirst($st)) ?>
                    </span>
                </div>
                <div class="text-secondary small">
                    From: <strong class="text-light"><?= e((string)($message['name'] ?? '-')) ?></strong>
                    &lt;<?= e((string)($message['email'] ?? '-')) ?>&gt;
                    · <?= e(substr((string)($message['created_at'] ?? '-'), 0, 16)) ?>
                </div>
                <div class="text-secondary small mt-1">
                    Department: <span class="text-light"><?= e(ucfirst((string)($message['department'] ?? 'general'))) ?></span>
                    · IP: <span class="text-light"><?= e((string)($message['ip_address'] ?? '-')) ?></span>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="py-3" style="white-space:pre-wrap;line-height:1.7"><?= e((string)($message['message'] ?? '-')) ?></div>
        </div>

        <?php if ($message['reply_message'] ?? ''): ?>
        <div class="glass rounded-4 p-4 mb-4">
            <h6 class="mb-3 text-success"><i class="fas fa-reply me-2"></i>Your Reply</h6>
            <div class="text-secondary small mb-2">
                Replied: <?= e(substr((string)($message['replied_at'] ?? '-'), 0, 16)) ?>
            </div>
            <div style="white-space:pre-wrap"><?= e((string)$message['reply_message']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Reply Form -->
        <?php if ($st !== 'spam'): ?>
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-reply me-2 text-primary"></i>Send Reply</h6>
            <div class="mb-3">
                <label class="form-label small text-secondary">Reply Message</label>
                <textarea id="replyMsg" class="form-control bg-dark text-light border-secondary" rows="6"
                          placeholder="Type your reply..."></textarea>
            </div>
            <button class="btn btn-primary" onclick="sendReply()">
                <i class="fas fa-paper-plane me-1"></i> Send Reply
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3">Actions</h6>
            <div class="d-grid gap-2">
                <a href="mailto:<?= e((string)($message['email'] ?? '')) ?>?subject=Re: <?= e((string)($message['subject'] ?? '')) ?>"
                   class="btn btn-sm btn-outline-info">
                    <i class="fas fa-at me-1"></i> Open in Email Client
                </a>
                <?php if ($st !== 'spam'): ?>
                <button class="btn btn-sm btn-outline-warning" onclick="markSpam()">
                    <i class="fas fa-ban me-1"></i> Mark as Spam
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteMsg()">
                    <i class="fas fa-trash me-1"></i> Delete Message
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
const msgId = <?= (int)($message['id'] ?? 0) ?>;
function sendReply() {
    const msg = document.getElementById('replyMsg').value.trim();
    if (!msg) { alert('Reply message cannot be empty.'); return; }
    fetch('/admin/cms/contact/reply', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: msgId, reply_message: msg, _token: csrf})
    }).then(r => r.json()).then(d => {
        if (d.ok) window.location.href = d.redirect || '/admin/cms/contact';
        else alert(d.message);
    });
}
function markSpam() {
    if (!confirm('Mark as spam?')) return;
    fetch('/admin/cms/contact/spam', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: msgId, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) window.location.href = '/admin/cms/contact'; else alert(d.message); });
}
function deleteMsg() {
    if (!confirm('Delete this message?')) return;
    fetch('/admin/cms/contact/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: msgId, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) window.location.href = '/admin/cms/contact'; else alert(d.message); });
}
</script>
