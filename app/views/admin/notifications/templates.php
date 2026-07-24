<?php declare(strict_types=1); ?>
<?php
$templates    = is_array($templates    ?? null) ? $templates    : [];
$editTemplate = is_array($editTemplate ?? null) ? $editTemplate : null;
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-4">
    <!-- TEMPLATE LIST -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-envelope me-2 text-info"></i>Email Templates</h6>
                <a href="/admin/notifications/templates" class="btn btn-xs btn-outline-primary">
                    <i class="fas fa-plus me-1"></i>New
                </a>
            </div>

            <?php if ($templates === []): ?>
            <div class="text-center py-4 text-secondary small">
                <i class="fas fa-envelope-open fa-2x mb-2 d-block"></i>No templates yet.
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
            <?php foreach ($templates as $tpl): ?>
            <a href="/admin/notifications/templates?id=<?= (int)$tpl['id'] ?>"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-2 px-0"
               style="background:transparent;border-color:rgba(148,163,184,0.15)">
                <div>
                    <div class="small fw-medium"><?= e((string)($tpl['template_key'] ?? '')) ?></div>
                    <div class="text-secondary" style="font-size:.72rem"><?= e(mb_substr((string)($tpl['subject'] ?? ''), 0, 50)) ?></div>
                </div>
                <div class="d-flex gap-1 align-items-center ms-2">
                    <?php if ((bool)$tpl['is_active']): ?>
                    <span class="badge bg-success" style="font-size:.6rem">Active</span>
                    <?php else: ?>
                    <span class="badge bg-secondary" style="font-size:.6rem">Inactive</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TEMPLATE EDITOR -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-4">
                <i class="fas fa-edit me-2 text-warning"></i>
                <?= $editTemplate ? 'Edit Template: ' . e((string)($editTemplate['template_key'] ?? '')) : 'New Email Template' ?>
            </h6>

            <form action="/admin/notifications/templates/save" method="post" data-ajax="true" class="row g-3">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="template_id" value="<?= $editTemplate ? (int)$editTemplate['id'] : 0 ?>">

                <?php if (!$editTemplate): ?>
                <div class="col-12">
                    <label class="form-label">Template Key <span class="text-danger">*</span>
                        <small class="text-secondary ms-1">Unique identifier, e.g. <code>welcome_email</code></small>
                    </label>
                    <input type="text" class="form-control" name="template_key" required
                           placeholder="order_filled_email">
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label">Subject <span class="text-danger">*</span>
                        <small class="text-secondary ms-1">Use <code>{{VARIABLE}}</code> for placeholders</small>
                    </label>
                    <input type="text" class="form-control" name="subject" required
                           value="<?= $editTemplate ? e((string)($editTemplate['subject'] ?? '')) : '' ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">HTML Body <span class="text-danger">*</span>
                        <small class="text-secondary ms-1">Full HTML email — use <code>{{VARIABLE}}</code> placeholders</small>
                    </label>
                    <textarea class="form-control" name="body_html" rows="16" required
                              id="bodyHtml"><?= $editTemplate ? e((string)($editTemplate['body_html'] ?? '')) : '' ?></textarea>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveCheck"
                               value="1" <?= (!$editTemplate || (bool)$editTemplate['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActiveCheck">Template is active</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <?php if ($editTemplate): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="deleteTemplateBtn"
                            data-id="<?= (int)$editTemplate['id'] ?>">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="previewBtn">
                        <i class="fas fa-eye me-1"></i>Preview
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PLACEHOLDER REFERENCE -->
<div class="glass rounded-4 p-4 mt-4">
    <h6 class="mb-3"><i class="fas fa-code me-2 text-secondary"></i>Available Placeholders</h6>
    <div class="row g-3 small">
        <?php
        $vars = [
            ['{{USERNAME}}',      "User's display name"],
            ['{{EMAIL}}',         "User's email address"],
            ['{{AMOUNT}}',        'Transaction/order amount'],
            ['{{CURRENCY}}',      'Currency code (BTC, USDT, etc.)'],
            ['{{PAIR}}',          'Trading pair (BTC/USDT)'],
            ['{{ORDER_ID}}',      'Order ID'],
            ['{{PRICE}}',         'Execution price'],
            ['{{DATE}}',          'Current date (YYYY-MM-DD)'],
            ['{{PLATFORM_NAME}}', 'Platform name from config'],
            ['{{ACTION_URL}}',    'Call-to-action URL link'],
        ];
        foreach ($vars as [$placeholder, $desc]):
        ?>
        <div class="col-md-3">
            <code class="text-warning"><?= e($placeholder) ?></code>
            <div class="text-secondary"><?= e($desc) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- HTML PREVIEW MODAL -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="previewFrame" style="width:100%;height:600px;border:none"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= e(\App\Libraries\Csrf::token()) ?>';

// Live preview
$('#previewBtn').on('click', function () {
    const html = document.getElementById('bodyHtml').value;
    const frame = document.getElementById('previewFrame');
    frame.contentDocument.open();
    frame.contentDocument.write(html);
    frame.contentDocument.close();
    new bootstrap.Modal(document.getElementById('previewModal')).show();
});

// Delete template
$('#deleteTemplateBtn').on('click', function () {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Delete this template?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete',
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/admin/notifications/templates/delete', { _token: csrfToken, id }, res => {
            if (res.ok) window.location.href = '/admin/notifications/templates';
        });
    });
});
</script>
