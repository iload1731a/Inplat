<?php declare(strict_types=1); ?>
<?php
$filters = is_array($filters ?? null) ? $filters : [];
$pairs = is_array($pairs ?? null) ? $pairs : [];
$feeTiers = is_array($feeTiers ?? null) ? $feeTiers : [];
$activeHalts = is_array($activeHalts ?? null) ? $activeHalts : [];
$recentHalts = is_array($recentHalts ?? null) ? $recentHalts : [];
$recentOrders = is_array($recentOrders ?? null) ? $recentOrders : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trading Management</h1>
        <p class="text-secondary mb-0">Manage trading pairs, fee tiers, market halts and live order flow.</p>
    </div>
    <a href="/admin/dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if ($activeHalts !== []): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="fa fa-triangle-exclamation"></i>
    <strong><?= count($activeHalts) ?> active trading halt<?= count($activeHalts) > 1 ? 's' : '' ?>.</strong>
    Review and resolve below.
</div>
<?php endif; ?>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/trading">
        <div class="col-md-3"><input class="form-control" type="text" name="search" value="<?= e((string)($filters['search'] ?? '')) ?>" placeholder="Symbol or username"></div>
        <div class="col-md-2">
            <select class="form-select" name="market_type">
                <option value="">All types</option>
                <?php foreach (['spot', 'margin', 'futures'] as $mt): ?>
                    <option value="<?= e($mt) ?>" <?= (($filters['market_type'] ?? '') === $mt) ? 'selected' : '' ?>><?= e(ucfirst($mt)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="is_active">
                <option value="">Active / Inactive</option>
                <option value="1" <?= (($filters['is_active'] ?? '') === '1') ? 'selected' : '' ?>>Active only</option>
                <option value="0" <?= (($filters['is_active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="status">
                <option value="">All order statuses</option>
                <?php foreach (['open', 'filled', 'partially_filled', 'cancelled', 'expired'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
        <div class="col-md-1"><a class="btn btn-outline-light w-100" href="/admin/trading">Reset</a></div>
    </form>
</div>

<div class="row g-3 mb-4">
    <!-- Trading Pairs -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Trading Pairs</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Symbol</th><th>Type</th><th>Maker %</th><th>Taker %</th>
                            <th>Max Lev.</th><th>Active</th><th>Trading</th><th>Visible</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pairs as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string)($row['symbol'] ?? '-')) ?></div>
                                <div class="small text-secondary"><?= e((string)($row['base_currency'] ?? '')) ?>/<?= e((string)($row['quote_currency'] ?? '')) ?></div>
                            </td>
                            <td><?= e((string)($row['market_type'] ?? '-')) ?></td>
                            <td><?= number_format((float)($row['maker_fee_percent'] ?? 0), 4) ?>%</td>
                            <td><?= number_format((float)($row['taker_fee_percent'] ?? 0), 4) ?>%</td>
                            <td><?= number_format((float)($row['max_leverage'] ?? 1), 2) ?>x</td>
                            <td><?= (int)($row['is_active'] ?? 0) === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            <td><?= (int)($row['trading_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning text-dark">Halted</span>' ?></td>
                            <td><?= (int)($row['is_visible'] ?? 0) === 1 ? '<span class="badge text-bg-info">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            <td>
                                <button class="btn btn-xs btn-outline-light btn-sm" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#pair-edit-<?= (int)($row['id'] ?? 0) ?>">
                                    Edit
                                </button>
                                <button class="btn btn-xs btn-outline-warning btn-sm ms-1" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#pair-halt-<?= (int)($row['id'] ?? 0) ?>">
                                    Halt
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="pair-edit-<?= (int)($row['id'] ?? 0) ?>">
                            <td colspan="9">
                                <form action="/admin/trading/pair/update" method="post" data-ajax="true" class="row g-2 p-2 border border-secondary rounded-3">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="pair_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                    <div class="col-md-2"><label class="form-label small">Maker Fee %</label><input class="form-control form-control-sm" type="number" step="0.0001" name="maker_fee_percent" value="<?= number_format((float)($row['maker_fee_percent'] ?? 0), 4) ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Taker Fee %</label><input class="form-control form-control-sm" type="number" step="0.0001" name="taker_fee_percent" value="<?= number_format((float)($row['taker_fee_percent'] ?? 0), 4) ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Min Order Size</label><input class="form-control form-control-sm" type="text" name="min_order_size" value="<?= e((string)($row['min_order_size'] ?? '0')) ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Max Order Size</label><input class="form-control form-control-sm" type="text" name="max_order_size" value="<?= e((string)($row['max_order_size'] ?? '')) ?>" placeholder="(no limit)"></div>
                                    <div class="col-md-2"><label class="form-label small">Min Notional</label><input class="form-control form-control-sm" type="text" name="min_notional" value="<?= e((string)($row['min_notional'] ?? '0')) ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Max Leverage</label><input class="form-control form-control-sm" type="number" step="0.01" name="max_leverage" value="<?= number_format((float)($row['max_leverage'] ?? 1), 2) ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Price Precision</label><input class="form-control form-control-sm" type="number" min="0" max="18" name="price_precision" value="<?= (int)($row['price_precision'] ?? 2) ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Qty Precision</label><input class="form-control form-control-sm" type="number" min="0" max="18" name="quantity_precision" value="<?= (int)($row['quantity_precision'] ?? 6) ?>"></div>
                                    <div class="col-md-1"><label class="form-label small">Active</label><select class="form-select form-select-sm" name="is_active"><option value="1" <?= (int)($row['is_active'] ?? 0) === 1 ? 'selected' : '' ?>>Yes</option><option value="0" <?= (int)($row['is_active'] ?? 0) === 0 ? 'selected' : '' ?>>No</option></select></div>
                                    <div class="col-md-1"><label class="form-label small">Trading</label><select class="form-select form-select-sm" name="trading_enabled"><option value="1" <?= (int)($row['trading_enabled'] ?? 0) === 1 ? 'selected' : '' ?>>On</option><option value="0" <?= (int)($row['trading_enabled'] ?? 0) === 0 ? 'selected' : '' ?>>Off</option></select></div>
                                    <div class="col-md-1"><label class="form-label small">Visible</label><select class="form-select form-select-sm" name="is_visible"><option value="1" <?= (int)($row['is_visible'] ?? 0) === 1 ? 'selected' : '' ?>>Yes</option><option value="0" <?= (int)($row['is_visible'] ?? 0) === 0 ? 'selected' : '' ?>>No</option></select></div>
                                    <div class="col-md-2"><label class="form-label small">Display Order</label><input class="form-control form-control-sm" type="number" name="display_order" value="<?= (int)($row['display_order'] ?? 0) ?>"></div>
                                    <div class="col-12 text-end"><button class="btn btn-sm btn-outline-info" type="submit">Save Pair Changes</button></div>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="pair-halt-<?= (int)($row['id'] ?? 0) ?>">
                            <td colspan="9">
                                <form action="/admin/trading/halt" method="post" data-ajax="true" class="row g-2 p-2 border border-warning rounded-3">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="pair_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                    <div class="col-md-9"><input class="form-control form-control-sm" type="text" name="reason" placeholder="Reason for halting trading on <?= e((string)($row['symbol'] ?? '')) ?>" required></div>
                                    <div class="col-md-3"><button class="btn btn-sm btn-warning text-dark w-100" type="submit">Halt Trading</button></div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($pairs === []): ?><tr><td colspan="9" class="text-center text-secondary">No trading pairs found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Fee Tiers -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">Fee Tiers</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Tier</th><th>Min Vol.</th><th>Maker %</th><th>Taker %</th><th>Active</th></tr></thead>
                    <tbody>
                    <?php foreach ($feeTiers as $tier): ?>
                        <tr>
                            <td><?= e((string)($tier['tier_name'] ?? '-')) ?></td>
                            <td><?= number_format((float)($tier['min_30d_volume'] ?? 0), 0) ?></td>
                            <td><?= number_format((float)($tier['maker_fee_percent'] ?? 0), 4) ?>%</td>
                            <td><?= number_format((float)($tier['taker_fee_percent'] ?? 0), 4) ?>%</td>
                            <td><?= (int)($tier['is_active'] ?? 0) === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($feeTiers === []): ?><tr><td colspan="5" class="text-secondary text-center">No fee tiers configured.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Active Halts -->
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Active Halts <span class="badge text-bg-warning text-dark"><?= count($activeHalts) ?></span></h2>
            <?php foreach ($activeHalts as $halt): ?>
                <div class="border border-warning rounded-3 p-2 mb-2">
                    <div class="fw-semibold"><?= e((string)($halt['symbol'] ?? '-')) ?></div>
                    <div class="small text-secondary mb-1"><?= e((string)($halt['reason'] ?? '-')) ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary"><?= e((string)($halt['started_at'] ?? '-')) ?></span>
                        <form action="/admin/trading/halt/resolve" method="post" data-ajax="true" class="d-inline">
                            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                            <input type="hidden" name="halt_id" value="<?= (int)($halt['id'] ?? 0) ?>">
                            <button class="btn btn-xs btn-outline-success btn-sm" type="submit">Resolve</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($activeHalts === []): ?><p class="text-secondary small mb-0">No active halts.</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="glass rounded-4 p-3 mb-4">
    <h2 class="h6 mb-3">Recent Orders</h2>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead><tr><th>ID</th><th>User</th><th>Pair</th><th>Side</th><th>Price</th><th>Qty</th><th>Filled</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><?= (int)($order['id'] ?? 0) ?></td>
                    <td><?= e((string)($order['username'] ?? '-')) ?></td>
                    <td><?= e((string)($order['symbol'] ?? '-')) ?></td>
                    <td class="<?= strtolower((string)($order['side'] ?? '')) === 'buy' ? 'text-success' : 'text-danger' ?>"><?= e(strtoupper((string)($order['side'] ?? '-'))) ?></td>
                    <td><?= number_format((float)($order['price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($order['quantity'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($order['filled_quantity'] ?? 0), 6) ?></td>
                    <td>
                        <span class="badge text-bg-<?= match($order['status'] ?? '') {
                            'filled' => 'success',
                            'open', 'partially_filled' => 'warning text-dark',
                            'cancelled', 'expired' => 'secondary',
                            default => 'secondary'
                        } ?>"><?= e((string)($order['status'] ?? '-')) ?></span>
                    </td>
                    <td class="small text-secondary"><?= e((string)($order['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recentOrders === []): ?><tr><td colspan="9" class="text-center text-secondary">No orders found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Halt History -->
<div class="glass rounded-4 p-3">
    <h2 class="h6 mb-3">Recent Halt History</h2>
    <div class="table-responsive">
        <table class="table table-dark table-sm mb-0">
            <thead><tr><th>Symbol</th><th>Reason</th><th>Triggered By</th><th>Status</th><th>Started</th><th>Ended</th></tr></thead>
            <tbody>
            <?php foreach ($recentHalts as $halt): ?>
                <tr>
                    <td><?= e((string)($halt['symbol'] ?? '-')) ?></td>
                    <td><?= e((string)($halt['reason'] ?? '-')) ?></td>
                    <td><?= e((string)($halt['triggered_by'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= ($halt['status'] ?? '') === 'active' ? 'warning text-dark' : 'success' ?>"><?= e((string)($halt['status'] ?? '-')) ?></span></td>
                    <td class="small"><?= e((string)($halt['started_at'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($halt['ended_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recentHalts === []): ?><tr><td colspan="6" class="text-center text-secondary">No halt history.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
