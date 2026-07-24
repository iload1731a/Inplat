<?php declare(strict_types=1); ?>
<?php
$broadcasts = is_array($broadcasts ?? null) ? $broadcasts : [];
$rows       = is_array($broadcasts['rows'] ?? null) ? $broadcasts['rows'] : [];
$totalPages = (int)($broadcasts['total_pages'] ?? 1);
$currentPage= (int)($broadcasts['page']        ?? 1);
$total      = (int)($broadcasts['total']        ?? 0);
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-4">
    <!-- BROADCAST FORM -->
    <div class="col-xl-5">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-4"><i class="fas fa-broadcast-tower me-2 text-warning"></i>Send Broadcast Notification</h6>
            <form action="/admin/notifications/broadcast" method="post" data-ajax="true" class="row g-3">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

                <div class="col-12">
                    <label class="form-label">Audience <span class="text-danger">*</span></label>
                    <select class="form-select" name="audience" id="audienceSelect">
                        <option value="all">All Users (non-banned)</option>
                        <option value="active">Active Users</option>
                        <option value="kyc_approved">KYC Approved</option>
                        <option value="kyc_pending">KYC Pending</option>
                        <option value="new_users">New Users (last 30 days)</option>
                        <option value="custom_ids">Custom User IDs</option>
                    </select>
                </div>

                <div class="col-12" id="customIdsRow" style="display:none">
                    <label class="form-label">User IDs <small class="text-secondary">(comma-separated, max 1000)</small></label>
                    <input type="text" class="form-control" name="user_ids" placeholder="1,2,3,42">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Channel <span class="text-danger">*</span></label>
                    <select class="form-select" name="channel">
                        <option value="in_app">In-App</option>
                        <option value="email">Email</option>
                        <option value="push">Push (registered devices)</option>
                        <option value="sms">SMS (architecture ready)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Type</label>
                    <input type="text" class="form-control" name="type" value="admin_notice"
                           placeholder="admin_notice, security_alert, ...">
                </div>

                <div class="col-12">
                    <label class="form-label">Action URL <small class="text-secondary">(optional)</small></label>
                    <input type="url" class="form-control" name="action_url" placeholder="https://...">
                </div>

                <div class="col-12">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="message" rows="5" required></textarea>
                </div>

                <div class="col-12">
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Large broadcasts are executed synchronously.</strong>
                        Email channels send one mail per recipient — allow time for large lists.
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary px-4" type="submit">
                        <i class="fas fa-paper-plane me-1"></i>Send Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- BROADCAST HISTORY -->
    <div class="col-xl-7">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Recent Broadcasts</h6>
                <span class="text-secondary small"><?= number_format($total) ?> total</span>
            </div>

            <?php if ($rows === []): ?>
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-broadcast-tower fa-3x mb-3 d-block"></i>
                <div>No broadcasts sent yet.</div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle small">
                    <thead class="table-dark">
                        <tr>
                            <th>Title</th>
                            <th>Audience</th>
                            <th>Channel</th>
                            <th>Recipients</th>
                            <th>Sent / Failed</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $bc):
                        $bcStatus = (string)($bc['status'] ?? 'pending');
                        $bcColor  = ['pending' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'][$bcStatus] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= e(mb_substr((string)($bc['title'] ?? ''), 0, 50)) ?></div>
                            <div class="text-secondary" style="font-size:.7rem"><?= e((string)($bc['admin_username'] ?? '')) ?></div>
                        </td>
                        <td><?= e((string)($bc['audience'] ?? '')) ?></td>
                        <td><span class="badge text-bg-info"><?= e((string)($bc['channel'] ?? '')) ?></span></td>
                        <td><?= number_format((int)($bc['recipient_count'] ?? 0)) ?></td>
                        <td>
                            <span class="text-success"><?= number_format((int)($bc['sent_count'] ?? 0)) ?></span>
                            / <span class="text-danger"><?= number_format((int)($bc['failed_count'] ?? 0)) ?></span>
                        </td>
                        <td><span class="badge text-bg-<?= $bcColor ?>"><?= e(ucfirst($bcStatus)) ?></span></td>
                        <td class="text-secondary"><?= e(date('M d, Y H:i', strtotime((string)($bc['created_at'] ?? 'now')))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $currentPage - 1 ?>">«</a></li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $currentPage + 1 ?>">»</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SMS/PUSH INFO -->
<div class="glass rounded-4 p-4 mt-4">
    <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Channel Architecture</h6>
    <div class="row g-3">
        <?php
        $channels = [
            ['SMS',            'fa-sms',      'success', 'SMS dispatch is architecture-ready. Integrate your SMS provider (Twilio, Nexmo, etc.) in app/libraries/SmsService.php and call it from NotificationsService::dispatch().'],
            ['Push (Web)',     'fa-bell',     'warning', 'Web push uses the Notifications API. Register service workers and VAPID keys, then call /user/notifications/push-register to store device tokens.'],
            ['Push (Mobile)',  'fa-mobile-alt','primary','FCM/APNs push notifications can be added by creating a FirebaseService library and dispatching through NotificationsService.'],
            ['Email',          'fa-envelope', 'info',    'Email dispatch is live via PHP mail(). For production, configure a relay (Mailgun, SES, Postmark) by replacing the mail() call in EmailService::sendHtml().'],
        ];
        foreach ($channels as [$name, $icon, $color, $desc]):
        ?>
        <div class="col-md-3">
            <div class="rounded-3 p-3 h-100" style="background:rgba(var(--bs-<?= $color ?>-rgb),.07);border:1px solid rgba(var(--bs-<?= $color ?>-rgb),.2)">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas <?= $icon ?> text-<?= $color ?>"></i>
                    <span class="fw-semibold"><?= e($name) ?></span>
                </div>
                <p class="text-secondary small mb-0"><?= e($desc) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
$('#audienceSelect').on('change', function () {
    document.getElementById('customIdsRow').style.display = this.value === 'custom_ids' ? '' : 'none';
});
</script>
