<?php declare(strict_types=1); ?>
<?php
$userStatus = is_array($user_status ?? null) ? $user_status : [];
$pendingKyc = is_array($pending_kyc ?? null) ? $pending_kyc : [];
$latestDeposits = is_array($latest_deposits ?? null) ? $latest_deposits : [];
$latestWithdrawals = is_array($latest_withdrawals ?? null) ? $latest_withdrawals : [];
$latestTickets = is_array($latest_tickets ?? null) ? $latest_tickets : [];
$settings = is_array($settings ?? null) ? $settings : [];
$moduleStatus = is_array($module_status ?? null) ? $module_status : [];
$settingsStatus = is_array($settings_status ?? null) ? $settings_status : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Admin Modules</h1>
        <p class="text-secondary mb-0">User management, KYC, payments, support and settings in one operational page.</p>
    </div>
    <a href="/admin/dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Admin Modules Coverage</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Module</th><th class="text-end">Records</th></tr></thead>
                    <tbody>
                    <?php foreach ($moduleStatus as $module): ?>
                        <tr>
                            <td><?= e((string)($module['module'] ?? '-')) ?></td>
                            <td class="text-end"><?= number_format((int)($module['total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($moduleStatus === []): ?><tr><td colspan="2" class="text-secondary text-center">No module data</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Settings Readiness</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Setting</th><th>Status</th><th>Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($settingsStatus as $setting): ?>
                        <tr>
                            <td><?= e((string)($setting['setting'] ?? '-')) ?></td>
                            <td>
                                <?php $isConfigured = (($setting['state'] ?? '') === 'Configured'); ?>
                                <span class="badge text-bg-<?= $isConfigured ? 'success' : 'secondary' ?>"><?= e((string)($setting['state'] ?? 'Pending')) ?></span>
                            </td>
                            <td class="text-break"><?= e((string)($setting['value'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($settingsStatus === []): ?><tr><td colspan="3" class="text-secondary text-center">No settings readiness data</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">User Status Distribution</h2>
            <ul class="list-group list-group-flush">
                <?php foreach ($userStatus as $row): ?>
                    <li class="list-group-item bg-transparent d-flex justify-content-between px-0 text-light border-secondary-subtle">
                        <span><?= e((string)($row['status'] ?? '-')) ?></span>
                        <strong><?= (int)($row['total'] ?? 0) ?></strong>
                    </li>
                <?php endforeach; ?>
                <?php if ($userStatus === []): ?><li class="list-group-item bg-transparent px-0 text-secondary">No user data</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Pending KYC Queue</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>#</th><th>User</th><th>Document</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($pendingKyc as $row): ?>
                        <tr>
                            <td><?= (int)($row['id'] ?? 0) ?></td>
                            <td><?= e((string)($row['username'] ?? '-')) ?></td>
                            <td><?= e((string)($row['document_type'] ?? '-')) ?></td>
                            <td><?= e((string)($row['status'] ?? '-')) ?></td>
                            <td><?= e((string)($row['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($pendingKyc === []): ?><tr><td colspan="5" class="text-secondary text-center">No KYC pending</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Latest Deposits</h2>
            <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
            <?php foreach ($latestDeposits as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= number_format((float)($row['amount'] ?? 0), 4) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td><td><?= e((string)($row['created_at'] ?? '-')) ?></td></tr><?php endforeach; ?>
            <?php if ($latestDeposits === []): ?><tr><td colspan="4" class="text-secondary text-center">No records</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Latest Withdrawals</h2>
            <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead><tbody>
            <?php foreach ($latestWithdrawals as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= number_format((float)($row['amount'] ?? 0), 4) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td><td><?= e((string)($row['requested_at'] ?? '-')) ?></td></tr><?php endforeach; ?>
            <?php if ($latestWithdrawals === []): ?><tr><td colspan="4" class="text-secondary text-center">No records</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Support Tickets</h2>
            <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Ticket</th><th>Subject</th><th>Priority</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($latestTickets as $row): ?><tr><td><?= e((string)($row['ticket_number'] ?? '-')) ?></td><td><?= e((string)($row['subject'] ?? '-')) ?></td><td><?= e((string)($row['priority'] ?? '-')) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td></tr><?php endforeach; ?>
            <?php if ($latestTickets === []): ?><tr><td colspan="4" class="text-secondary text-center">No tickets</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">System Settings Preview</h2>
            <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>
            <?php foreach ($settings as $row): ?><tr><td><?= e((string)($row['setting_key'] ?? '-')) ?></td><td class="text-break"><?= e((string)($row['setting_value'] ?? '-')) ?></td></tr><?php endforeach; ?>
            <?php if ($settings === []): ?><tr><td colspan="2" class="text-secondary text-center">No settings</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
