<?php declare(strict_types=1); ?>
<?php
$pairs = is_array($pairs ?? null) ? $pairs : [];
$openOrders = is_array($openOrders ?? null) ? $openOrders : [];
$positions = is_array($positions ?? null) ? $positions : [];
$wallets = is_array($wallets ?? null) ? $wallets : [];
$notifications = is_array($notifications ?? null) ? $notifications : [];
$tickets = is_array($tickets ?? null) ? $tickets : [];
$apiKeys = is_array($apiKeys ?? null) ? $apiKeys : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trading Workspace</h1>
        <p class="text-secondary mb-0">Spot, margin and futures workspace with portfolio and support features.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
        <a href="/security/sessions" class="btn btn-outline-info btn-sm">Sessions</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Market Overview</h2>
            <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Pair</th><th>Type</th><th>Last Price</th><th>24h %</th><th>24h Volume</th></tr></thead><tbody>
            <?php foreach ($pairs as $row): ?>
                <tr>
                    <td><?= e((string)($row['symbol'] ?? '-')) ?></td>
                    <td><?= e((string)($row['market_type'] ?? '-')) ?></td>
                    <td><?= number_format((float)($row['last_price'] ?? 0), 6) ?></td>
                    <td class="<?= (float)($row['change_24h_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format((float)($row['change_24h_percent'] ?? 0), 2) ?>%</td>
                    <td><?= number_format((float)($row['volume_24h'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pairs === []): ?><tr><td colspan="5" class="text-secondary text-center">No pairs available</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">API Keys & Security</h2>
            <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Label</th><th>Permissions</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($apiKeys as $row): ?><tr><td><?= e((string)($row['label'] ?? 'API Key')) ?></td><td><?= e((string)($row['permissions'] ?? '-')) ?></td><td><?= ((int)($row['is_active'] ?? 0) === 1) ? 'Active' : 'Revoked' ?></td></tr><?php endforeach; ?>
            <?php if ($apiKeys === []): ?><tr><td colspan="3" class="text-secondary text-center">No API keys created</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6"><div class="glass rounded-4 p-3"><h2 class="h6 mb-3">Open Orders</h2><div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>ID</th><th>Pair</th><th>Side</th><th>Qty</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($openOrders as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['symbol'] ?? '-')) ?></td><td><?= e((string)($row['side'] ?? '-')) ?></td><td><?= number_format((float)($row['quantity'] ?? 0), 6) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td></tr><?php endforeach; ?>
    <?php if ($openOrders === []): ?><tr><td colspan="5" class="text-secondary text-center">No orders</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-6"><div class="glass rounded-4 p-3"><h2 class="h6 mb-3">Positions</h2><div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>ID</th><th>Pair</th><th>Side</th><th>Leverage</th><th>PnL</th></tr></thead><tbody>
    <?php foreach ($positions as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['symbol'] ?? '-')) ?></td><td><?= e((string)($row['position_side'] ?? '-')) ?></td><td><?= number_format((float)($row['leverage'] ?? 1), 2) ?>x</td><td class="<?= (float)($row['unrealized_pnl'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format((float)($row['unrealized_pnl'] ?? 0), 6) ?></td></tr><?php endforeach; ?>
    <?php if ($positions === []): ?><tr><td colspan="5" class="text-secondary text-center">No positions</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4"><div class="glass rounded-4 p-3"><h2 class="h6 mb-3">Wallets</h2><div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Currency</th><th>Type</th><th>Available</th></tr></thead><tbody>
    <?php foreach ($wallets as $row): ?><tr><td><?= e((string)($row['code'] ?? '-')) ?></td><td><?= e((string)($row['wallet_type'] ?? '-')) ?></td><td><?= number_format((float)($row['available_balance'] ?? 0), 6) ?></td></tr><?php endforeach; ?>
    <?php if ($wallets === []): ?><tr><td colspan="3" class="text-secondary text-center">No wallet balances</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-4"><div class="glass rounded-4 p-3"><h2 class="h6 mb-3">Notifications</h2><div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Title</th><th>Channel</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($notifications as $row): ?><tr><td><?= e((string)($row['title'] ?? '-')) ?></td><td><?= e((string)($row['channel'] ?? '-')) ?></td><td><?= ((int)($row['is_read'] ?? 0) === 1) ? 'Read' : 'Unread' ?></td></tr><?php endforeach; ?>
    <?php if ($notifications === []): ?><tr><td colspan="3" class="text-secondary text-center">No notifications</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-4"><div class="glass rounded-4 p-3"><h2 class="h6 mb-3">Support Tickets</h2><div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Ticket</th><th>Priority</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($tickets as $row): ?><tr><td><?= e((string)($row['ticket_number'] ?? '-')) ?></td><td><?= e((string)($row['priority'] ?? '-')) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td></tr><?php endforeach; ?>
    <?php if ($tickets === []): ?><tr><td colspan="3" class="text-secondary text-center">No tickets</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>
