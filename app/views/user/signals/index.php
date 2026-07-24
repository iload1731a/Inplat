<?php declare(strict_types=1);
$feed         = (array)($feed ?? []);
$providers    = (array)($providers ?? []);
$subscriptions = (array)($subscriptions ?? []);
$subscribedIds = (array)($subscribed_ids ?? []);
$alerts       = (array)($alerts ?? []);
$rules        = (array)($rules ?? []);
$bookmarks    = (array)($bookmarks ?? []);
$activeAlertCount = (int)($active_alert_count ?? 0);
$activeRuleCount  = (int)($active_rule_count ?? 0);
$csrfToken    = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- KPI strip -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['Subscribed Providers', count($subscriptions),  'fa-satellite-dish',  'text-info'],
        ['Active Alerts',        $activeAlertCount,       'fa-bell',            'text-warning'],
        ['Automation Rules',     $activeRuleCount,        'fa-robot',           'text-success'],
        ['Bookmarked Signals',   count($bookmarks),       'fa-bookmark',        'text-primary'],
    ];
    foreach ($kpis as [$label, $val, $icon, $col]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-3 p-3 text-center h-100">
            <div class="<?= $col ?> mb-1"><i class="fas <?= $icon ?> fa-lg"></i></div>
            <div class="fw-bold fs-4"><?= $val ?></div>
            <div class="text-secondary small"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Left: Signal Feed -->
    <div class="col-lg-8">
        <div class="glass rounded-3 p-0">
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
                <div class="fw-semibold"><i class="fas fa-chart-bar me-2 text-info"></i>Latest Signals</div>
                <div class="d-flex gap-2">
                    <a href="/user/signals/feed" class="btn btn-xs btn-outline-info">Full Feed</a>
                    <a href="/user/signals/subscribe" class="btn btn-xs btn-outline-secondary">Providers</a>
                </div>
            </div>
            <?php if (empty($feed)): ?>
            <div class="p-4 text-center text-secondary">
                <i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>
                No signals yet. <a href="/user/signals/subscribe">Subscribe to a provider</a> to get started.
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush rounded-bottom-3">
                <?php foreach ($feed as $sig):
                    $typeColor = match($sig['signal_type']) {
                        'buy','close_short'  => 'success',
                        'sell','close_long'  => 'danger',
                        'hold'               => 'warning',
                        default              => 'secondary',
                    };
                    $statusColor = match($sig['status']) {
                        'hit_tp'   => 'success',
                        'hit_sl'   => 'danger',
                        'active'   => 'info',
                        'cancelled','expired' => 'secondary',
                        default    => 'secondary',
                    };
                ?>
                <a href="/user/signals/detail?id=<?= (int)$sig['id'] ?>"
                   class="list-group-item list-group-item-action bg-transparent border-secondary px-3 py-2">
                    <div class="d-flex align-items-start gap-3">
                        <div class="flex-shrink-0">
                            <span class="badge bg-<?= $typeColor ?> text-uppercase fw-bold" style="min-width:56px">
                                <?= e($sig['signal_type']) ?>
                            </span>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="fw-semibold"><?= e((string)($sig['pair_symbol'] ?? $sig['pair_symbol_live'] ?? '')) ?></span>
                                <span class="badge bg-<?= $statusColor ?> small"><?= e($sig['status']) ?></span>
                            </div>
                            <div class="text-secondary small mt-1">
                                <?= e($sig['provider_name']) ?>
                                · <?= e($sig['timeframe']) ?>
                                · <?= e($sig['market_type']) ?>
                                <?php if (!empty($sig['confidence_score'])): ?>
                                · <i class="fas fa-signal"></i> <?= (int)$sig['confidence_score'] ?>%
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($sig['entry_price'])): ?>
                            <div class="small mt-1 text-light">
                                Entry: <strong><?= number_format((float)$sig['entry_price'], 8) ?></strong>
                                <?php if (!empty($sig['take_profit_1'])): ?>
                                · TP1: <span class="text-success"><?= number_format((float)$sig['take_profit_1'], 8) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($sig['stop_loss'])): ?>
                                · SL: <span class="text-danger"><?= number_format((float)$sig['stop_loss'], 8) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <div class="text-secondary" style="font-size:.7rem"><?= e(date('M d H:i', strtotime($sig['published_at']))) ?></div>
                            <?php if ($sig['profit_pct'] !== null): ?>
                            <div class="<?= $sig['profit_pct'] >= 0 ? 'text-profit' : 'text-loss' ?> fw-semibold small">
                                <?= $sig['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$sig['profit_pct'], 2) ?>%
                            </div>
                            <?php endif; ?>
                            <div class="text-secondary small"><i class="fas fa-eye me-1"></i><?= (int)$sig['views_count'] ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Quick Panels -->
    <div class="col-lg-4">
        <!-- Active Alerts -->
        <div class="glass rounded-3 mb-4">
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
                <div class="fw-semibold"><i class="fas fa-bell me-2 text-warning"></i>Active Alerts</div>
                <a href="/user/signals/alerts" class="btn btn-xs btn-outline-warning">Manage</a>
            </div>
            <?php if (empty($alerts)): ?>
            <div class="p-3 text-center text-secondary small">
                No active alerts. <a href="/user/signals/alerts" class="text-warning">Create one</a>.
            </div>
            <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach (array_slice($alerts, 0, 5) as $al): ?>
                <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom border-secondary">
                    <i class="fas fa-bell-on text-warning small"></i>
                    <div class="flex-grow-1 min-width-0">
                        <div class="small fw-semibold text-truncate"><?= e($al['pair_symbol']) ?></div>
                        <div class="text-secondary" style="font-size:.7rem">
                            <?= e(str_replace('_', ' ', $al['alert_type'])) ?>
                            @ <?= number_format((float)$al['threshold_value'], 6) ?>
                        </div>
                    </div>
                    <span class="badge bg-info-subtle text-info small"><?= e($al['timeframe']) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (count($alerts) > 5): ?>
                <li class="text-center py-2">
                    <a href="/user/signals/alerts" class="text-info small">+<?= count($alerts) - 5 ?> more</a>
                </li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Automation Rules -->
        <div class="glass rounded-3 mb-4">
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
                <div class="fw-semibold"><i class="fas fa-robot me-2 text-success"></i>Automation</div>
                <a href="/user/signals/automation" class="btn btn-xs btn-outline-success">Manage</a>
            </div>
            <?php if (empty($rules)): ?>
            <div class="p-3 text-center text-secondary small">
                No automation rules. <a href="/user/signals/automation" class="text-success">Create one</a>.
            </div>
            <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach (array_slice($rules, 0, 4) as $rule): ?>
                <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom border-secondary">
                    <span class="badge <?= $rule['is_active'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill" style="width:8px;height:8px;padding:0"></span>
                    <div class="flex-grow-1 min-width-0">
                        <div class="small fw-semibold text-truncate"><?= e($rule['name']) ?></div>
                        <div class="text-secondary" style="font-size:.7rem">
                            <?= e((int)$rule['execution_count']) ?> executions
                        </div>
                    </div>
                    <span class="text-secondary" style="font-size:.7rem"><?= $rule['is_active'] ? '<span class="text-success">Active</span>' : 'Paused' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Subscriptions -->
        <div class="glass rounded-3">
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
                <div class="fw-semibold"><i class="fas fa-satellite-dish me-2 text-info"></i>My Subscriptions</div>
                <a href="/user/signals/subscribe" class="btn btn-xs btn-outline-info">Browse</a>
            </div>
            <?php if (empty($subscriptions)): ?>
            <div class="p-3 text-center text-secondary small">Not subscribed to any provider.</div>
            <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($subscriptions as $sub): ?>
                <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom border-secondary">
                    <div class="user-avatar" style="width:28px;height:28px;font-size:.65rem">
                        <?= e(mb_strtoupper(mb_substr($sub['provider_name'], 0, 2))) ?>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="small fw-semibold text-truncate"><?= e($sub['provider_name']) ?></div>
                        <div class="text-secondary" style="font-size:.7rem">
                            Win: <?= number_format((float)$sub['win_rate_30d'], 1) ?>%
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick links bar -->
<div class="glass rounded-3 p-3 mt-4 d-flex flex-wrap gap-2 align-items-center">
    <span class="text-secondary small fw-semibold me-2">Quick Access:</span>
    <a href="/user/signals/feed" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-bar me-1"></i>Signal Feed</a>
    <a href="/user/signals/alerts" class="btn btn-sm btn-outline-warning"><i class="fas fa-bell me-1"></i>Price Alerts</a>
    <a href="/user/signals/automation" class="btn btn-sm btn-outline-success"><i class="fas fa-robot me-1"></i>Automation</a>
    <a href="/user/signals/performance" class="btn btn-sm btn-outline-primary"><i class="fas fa-trophy me-1"></i>Performance</a>
    <a href="/user/signals/bookmarks" class="btn btn-sm btn-outline-secondary"><i class="fas fa-bookmark me-1"></i>Bookmarks</a>
    <a href="/user/signals/subscribe" class="btn btn-sm btn-outline-light"><i class="fas fa-satellite-dish me-1"></i>Providers</a>
</div>
