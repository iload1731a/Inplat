<?php declare(strict_types=1); ?>
<?php
$user = is_array($user ?? null) ? $user : [];
$wallets = is_array($wallets ?? null) ? $wallets : [];
$kycDocuments = is_array($kycDocuments ?? null) ? $kycDocuments : [];
$loginHistory = is_array($loginHistory ?? null) ? $loginHistory : [];
$notifications = is_array($notifications ?? null) ? $notifications : [];
$recentOrders = is_array($recentOrders ?? null) ? $recentOrders : [];
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$recentDeposits = is_array($recentDeposits ?? null) ? $recentDeposits : [];
$recentWithdrawals = is_array($recentWithdrawals ?? null) ? $recentWithdrawals : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Manage User #<?= (int)($user['id'] ?? 0) ?></h1>
        <p class="text-secondary mb-0">Edit user profile, adjust balances, and process KYC from one page.</p>
    </div>
    <a href="/admin/users" class="btn btn-outline-light btn-sm">Back to Users</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">Admin Actions</h2>
            <div class="d-flex flex-wrap gap-2">
                <form action="/admin/users/login-as" method="post" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)($user['id'] ?? 0) ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-right-to-bracket me-1"></i>Login As User</button>
                </form>
                <a class="btn btn-sm btn-outline-warning" href="/admin/communications?user_id=<?= (int)($user['id'] ?? 0) ?>&channel=email"><i class="fas fa-envelope me-1"></i>Send Email</a>
                <a class="btn btn-sm btn-outline-info" href="/admin/orders?search=<?= urlencode((string)($user['username'] ?? '')) ?>"><i class="fas fa-list-ol me-1"></i>Order Book</a>
                <a class="btn btn-sm btn-outline-info" href="/admin/deposits?search=<?= urlencode((string)($user['username'] ?? '')) ?>"><i class="fas fa-arrow-down me-1"></i>Deposits</a>
                <a class="btn btn-sm btn-outline-info" href="/admin/withdrawals?search=<?= urlencode((string)($user['username'] ?? '')) ?>"><i class="fas fa-arrow-up me-1"></i>Withdrawals</a>
            </div>
        </div>
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Account Profile</h2>
            <form action="/admin/users/update" method="post" data-ajax="true" class="row g-3">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="user_id" value="<?= (int)($user['id'] ?? 0) ?>">
                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string)($user['email'] ?? '')) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" type="text" name="phone" value="<?= e((string)($user['phone'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">First Name</label><input class="form-control" type="text" name="first_name" value="<?= e((string)($user['first_name'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Last Name</label><input class="form-control" type="text" name="last_name" value="<?= e((string)($user['last_name'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Country</label><input class="form-control" type="text" name="country_code" maxlength="2" value="<?= e((string)($user['country_code'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Profile Country</label><input class="form-control" type="text" name="profile_country_code" maxlength="2" value="<?= e((string)($user['profile_country_code'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Timezone</label><input class="form-control" type="text" name="timezone" value="<?= e((string)($user['timezone'] ?? 'UTC')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Language</label><input class="form-control" type="text" name="preferred_language" value="<?= e((string)($user['preferred_language'] ?? 'en')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['active','pending','suspended','banned','closed'] as $status): ?><option value="<?= e($status) ?>" <?= (($user['status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">KYC Status</label><select class="form-select" name="kyc_status"><?php foreach (['unverified','pending','approved','rejected'] as $status): ?><option value="<?= e($status) ?>" <?= (($user['kyc_status'] ?? '') === $status) ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">KYC Level</label><input class="form-control" type="number" min="0" name="kyc_level" value="<?= (int)($user['kyc_level'] ?? 0) ?>"></div>
                <div class="col-md-3"><label class="form-label">Account Type</label><select class="form-select" name="account_type"><?php foreach (['individual','corporate'] as $type): ?><option value="<?= e($type) ?>" <?= (($user['account_type'] ?? '') === $type) ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="date_of_birth" value="<?= e((string)($user['date_of_birth'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><?php foreach (['' => 'Undisclosed','male' => 'Male','female' => 'Female','other' => 'Other','undisclosed' => 'Undisclosed'] as $value => $label): ?><option value="<?= e((string)$value) ?>" <?= (($user['gender'] ?? '') === $value) ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Occupation</label><input class="form-control" type="text" name="occupation" value="<?= e((string)($user['occupation'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Income Range</label><input class="form-control" type="text" name="annual_income_range" value="<?= e((string)($user['annual_income_range'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Source of Funds</label><input class="form-control" type="text" name="source_of_funds" value="<?= e((string)($user['source_of_funds'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" type="text" name="company_name" value="<?= e((string)($user['company_name'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Company Registration No.</label><input class="form-control" type="text" name="company_registration_no" value="<?= e((string)($user['company_registration_no'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Tax ID</label><input class="form-control" type="text" name="tax_id" value="<?= e((string)($user['tax_id'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Address Line 1</label><input class="form-control" type="text" name="address_line1" value="<?= e((string)($user['address_line1'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Address Line 2</label><input class="form-control" type="text" name="address_line2" value="<?= e((string)($user['address_line2'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label">City</label><input class="form-control" type="text" name="city" value="<?= e((string)($user['city'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label">State / Province</label><input class="form-control" type="text" name="state_province" value="<?= e((string)($user['state_province'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Postal Code</label><input class="form-control" type="text" name="postal_code" value="<?= e((string)($user['postal_code'] ?? '')) ?>"></div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit">Save User Changes</button></div>
            </form>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">Change Password</h2>
            <form action="/admin/users/change-password" method="post" data-ajax="true" class="row g-2">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="user_id" value="<?= (int)($user['id'] ?? 0) ?>">
                <div class="col-12"><input class="form-control form-control-sm" type="password" name="new_password" minlength="8" placeholder="New password" required></div>
                <div class="col-12"><input class="form-control form-control-sm" type="password" name="confirm_password" minlength="8" placeholder="Confirm password" required></div>
                <div class="col-12"><button class="btn btn-sm btn-outline-danger w-100" type="submit">Update Password</button></div>
            </form>
        </div>
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">Account Snapshot</h2>
            <div class="small text-secondary">Username</div><div class="mb-2"><?= e((string)($user['username'] ?? '-')) ?></div>
            <div class="small text-secondary">Created</div><div class="mb-2"><?= e((string)($user['created_at'] ?? '-')) ?></div>
            <div class="small text-secondary">Last Login</div><div class="mb-2"><?= e((string)($user['last_login_at'] ?? '-')) ?></div>
            <div class="small text-secondary">Tickets</div><div class="mb-2"><?= (int)($user['support_tickets_count'] ?? 0) ?></div>
            <div class="small text-secondary">Unread Notifications</div><div><?= (int)($user['unread_notifications_count'] ?? 0) ?></div>
        </div>
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Wallet Adjustments</h2>
            <?php foreach ($wallets as $wallet): ?>
                <form action="/admin/users/balance" method="post" data-ajax="true" class="border border-secondary-subtle rounded-4 p-3 mb-3">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <input type="hidden" name="wallet_id" value="<?= (int)($wallet['id'] ?? 0) ?>">
                    <div class="d-flex justify-content-between mb-2"><strong><?= e((string)($wallet['currency_code'] ?? '-')) ?> / <?= e((string)($wallet['wallet_type'] ?? '-')) ?></strong><span><?= number_format((float)($wallet['available_balance'] ?? 0), 8) ?></span></div>
                    <div class="row g-2">
                        <div class="col-5"><select class="form-select form-select-sm" name="direction"><option value="credit">Credit</option><option value="debit">Debit</option></select></div>
                        <div class="col-7"><input class="form-control form-control-sm" type="number" step="0.00000001" min="0.00000001" name="amount" placeholder="Amount" required></div>
                        <div class="col-12"><input class="form-control form-control-sm" type="text" name="reason" placeholder="Reason / internal note" required></div>
                        <div class="col-12"><button class="btn btn-sm btn-outline-warning w-100" type="submit">Apply Adjustment</button></div>
                    </div>
                </form>
            <?php endforeach; ?>
            <?php if ($wallets === []): ?><div class="text-secondary">This user has no wallets yet.</div><?php endif; ?>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Recent Orders</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Pair</th><th>Side</th><th>Status</th><th>Qty</th><th>Price</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $row): ?>
                        <tr>
                            <td>#<?= (int)($row['id'] ?? 0) ?></td>
                            <td><?= e((string)($row['symbol'] ?? '-')) ?></td>
                            <td><span class="badge text-bg-<?= (($row['side'] ?? '') === 'buy') ? 'success' : 'danger' ?>"><?= e((string)($row['side'] ?? '-')) ?></span></td>
                            <td><?= e((string)($row['status'] ?? '-')) ?></td>
                            <td><?= number_format((float)($row['quantity'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($row['price'] ?? 0), 8) ?></td>
                            <td class="small text-secondary"><?= e((string)($row['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentOrders === []): ?><tr><td colspan="7" class="text-center text-secondary">No orders found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Recent Trades</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Pair</th><th>Side</th><th>Qty</th><th>Price</th><th>Fee</th><th>Executed</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTrades as $row): ?>
                        <tr>
                            <td>#<?= (int)($row['id'] ?? 0) ?></td>
                            <td><?= e((string)($row['symbol'] ?? '-')) ?></td>
                            <td><span class="badge text-bg-<?= (($row['side'] ?? '') === 'buy') ? 'success' : 'danger' ?>"><?= e((string)($row['side'] ?? '-')) ?></span></td>
                            <td><?= number_format((float)($row['quantity'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($row['price'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($row['fee'] ?? 0), 8) ?></td>
                            <td class="small text-secondary"><?= e((string)($row['executed_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentTrades === []): ?><tr><td colspan="7" class="text-center text-secondary">No trades found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Recent Deposits</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Currency</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentDeposits as $row): ?>
                        <tr>
                            <td>#<?= (int)($row['id'] ?? 0) ?></td>
                            <td><?= e((string)($row['currency_code'] ?? '-')) ?></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 8) ?></td>
                            <td><?= e((string)($row['status'] ?? '-')) ?></td>
                            <td class="small text-secondary"><?= e((string)($row['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentDeposits === []): ?><tr><td colspan="5" class="text-center text-secondary">No deposits found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Recent Withdrawals</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Currency</th><th>Amount</th><th>Fee</th><th>Status</th><th>Requested</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentWithdrawals as $row): ?>
                        <tr>
                            <td>#<?= (int)($row['id'] ?? 0) ?></td>
                            <td><?= e((string)($row['currency_code'] ?? '-')) ?></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 8) ?></td>
                            <td><?= number_format((float)($row['fee'] ?? 0), 8) ?></td>
                            <td><?= e((string)($row['status'] ?? '-')) ?></td>
                            <td class="small text-secondary"><?= e((string)($row['requested_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentWithdrawals === []): ?><tr><td colspan="6" class="text-center text-secondary">No withdrawals found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">KYC Documents</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Type</th><th>Document</th><th>Status</th><th>Review</th></tr></thead>
                    <tbody>
                    <?php foreach ($kycDocuments as $row): ?>
                        <tr>
                            <td><?= (int)($row['id'] ?? 0) ?></td>
                            <td><?= e((string)($row['document_type'] ?? '-')) ?></td>
                            <td>
                                <div><?= e((string)($row['document_number'] ?? '-')) ?></div>
                                <a class="small" href="<?= e((string)($row['file_url'] ?? '#')) ?>" target="_blank" rel="noopener">Open file</a>
                            </td>
                            <td><span class="badge text-bg-<?= (($row['status'] ?? '') === 'approved') ? 'success' : ((($row['status'] ?? '') === 'pending') ? 'warning text-dark' : 'secondary') ?>"><?= e((string)($row['status'] ?? '-')) ?></span></td>
                            <td>
                                <form action="/admin/users/kyc" method="post" data-ajax="true" class="d-grid gap-2">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="document_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                    <select class="form-select form-select-sm" name="status"><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                                    <input class="form-control form-control-sm" type="text" name="notes" value="<?= e((string)($row['review_notes'] ?? '')) ?>" placeholder="Review note">
                                    <button class="btn btn-sm btn-outline-info" type="submit">Save Review</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($kycDocuments === []): ?><tr><td colspan="5" class="text-center text-secondary">No KYC documents uploaded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">Recent Notifications</h2>
            <ul class="list-group list-group-flush">
                <?php foreach ($notifications as $row): ?>
                    <li class="list-group-item bg-transparent px-0 text-light border-secondary-subtle"><strong><?= e((string)($row['title'] ?? '-')) ?></strong><div class="small text-secondary"><?= e((string)($row['channel'] ?? '-')) ?> · <?= e((string)($row['created_at'] ?? '-')) ?></div></li>
                <?php endforeach; ?>
                <?php if ($notifications === []): ?><li class="list-group-item bg-transparent px-0 text-secondary">No notifications found.</li><?php endif; ?>
            </ul>
        </div>
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Login History</h2>
            <ul class="list-group list-group-flush">
                <?php foreach ($loginHistory as $row): ?>
                    <li class="list-group-item bg-transparent px-0 text-light border-secondary-subtle"><strong><?= e((string)($row['status'] ?? '-')) ?></strong><div class="small text-secondary"><?= e((string)($row['ip_address'] ?? '-')) ?> · <?= e((string)($row['created_at'] ?? '-')) ?></div></li>
                <?php endforeach; ?>
                <?php if ($loginHistory === []): ?><li class="list-group-item bg-transparent px-0 text-secondary">No login history recorded.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
