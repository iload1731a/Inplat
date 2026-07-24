<?php declare(strict_types=1); ?>
<?php
$notifications = is_array($notifications ?? null) ? $notifications : [];
$emailTemplates = is_array($emailTemplates ?? null) ? $emailTemplates : [];
$admins = is_array($admins ?? null) ? $admins : [];
$admin = is_array($admin ?? null) ? $admin : [];
$prefillUserId = max(0, (int)($prefillUserId ?? 0));
$prefillChannel = (string)($prefillChannel ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Communications Center</h1>
        <p class="text-secondary mb-0">Send bulk or single-user notifications and maintain reusable email templates.</p>
    </div>
    <span class="badge text-bg-warning text-dark">Admin <?= e((string)($admin['role_name'] ?? 'operator')) ?></span>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-xl-5">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Send Notification</h2>
            <form action="/admin/communications/notify" method="post" data-ajax="true" class="row g-3">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="col-md-6"><label class="form-label">Audience</label><select class="form-select" name="audience"><option value="single" <?= $prefillUserId > 0 ? 'selected' : '' ?>>Single User</option><option value="active">All Active Users</option><option value="kyc_pending">Pending KYC Users</option><option value="all">All Users</option></select></div>
                <div class="col-md-6"><label class="form-label">User ID</label><input class="form-control" type="number" min="0" name="user_id" placeholder="Only for single-user sends" value="<?= $prefillUserId > 0 ? (int)$prefillUserId : '' ?>"></div>
                <div class="col-md-6"><label class="form-label">Status Filter</label><select class="form-select" name="status_filter"><option value="">Any</option><?php foreach (['active','pending','suspended','banned','closed'] as $status): ?><option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">KYC Filter</label><select class="form-select" name="kyc_filter"><option value="">Any</option><?php foreach (['unverified','pending','approved','rejected'] as $status): ?><option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="in_app" <?= $prefillChannel === 'in_app' ? 'selected' : '' ?>>In App</option><option value="email" <?= $prefillChannel === 'email' ? 'selected' : '' ?>>Email</option><option value="sms" <?= $prefillChannel === 'sms' ? 'selected' : '' ?>>SMS</option><option value="push" <?= $prefillChannel === 'push' ? 'selected' : '' ?>>Push</option></select></div>
                <div class="col-md-6"><label class="form-label">Type</label><input class="form-control" type="text" name="type" value="admin_notice"></div>
                <div class="col-12"><label class="form-label">Title</label><input class="form-control" type="text" name="title" required></div>
                <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="5" required></textarea></div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit">Send Notification</button></div>
            </form>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Template Builder</h2>
            <form action="/admin/communications/template" method="post" data-ajax="true" class="row g-3 mb-4">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="col-md-4"><label class="form-label">Template ID</label><input class="form-control" type="number" min="0" name="template_id" placeholder="0 for new template"></div>
                <div class="col-md-4"><label class="form-label">Template Key</label><input class="form-control" type="text" name="template_key" placeholder="welcome_email"></div>
                <div class="col-md-4"><label class="form-label">Active</label><select class="form-select" name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                <div class="col-12"><label class="form-label">Subject</label><input class="form-control" type="text" name="subject" required></div>
                <div class="col-12"><label class="form-label">Body HTML</label><textarea class="form-control" name="body_html" rows="6" required></textarea></div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-outline-warning" type="submit">Save Template</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Key</th><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
                    <tbody>
                    <?php foreach ($emailTemplates as $row): ?>
                        <tr>
                            <td><?= e((string)($row['template_key'] ?? '-')) ?></td>
                            <td><?= e((string)($row['subject'] ?? '-')) ?></td>
                            <td><?= ((int)($row['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></td>
                            <td><?= e((string)($row['updated_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($emailTemplates === []): ?><tr><td colspan="4" class="text-center text-secondary">No email templates created yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="glass rounded-4 p-3">
    <h2 class="h6 mb-3">Recent Notifications</h2>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>User</th><th>Type</th><th>Title</th><th>Channel</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($notifications as $row): ?>
                <tr>
                    <td><?= (int)($row['id'] ?? 0) ?></td>
                    <td><?= e((string)($row['username'] ?? '-')) ?></td>
                    <td><?= e((string)($row['type'] ?? '-')) ?></td>
                    <td><?= e((string)($row['title'] ?? '-')) ?></td>
                    <td><?= e((string)($row['channel'] ?? '-')) ?></td>
                    <td><?= e((string)($row['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><tr><td colspan="6" class="text-center text-secondary">No notifications sent yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
