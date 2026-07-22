<?php declare(strict_types=1); ?>
<?php
$pair         = is_array($pair ?? null)         ? $pair         : null;
$subscription = is_array($subscription ?? null) ? $subscription : null;
$providers    = is_array($providers ?? null)    ? $providers    : [];
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$candles      = is_array($candles ?? null)      ? $candles      : [];
$error        = (string)($error ?? '');
$csrf         = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $pair ? e((string)$pair['symbol']) : 'Pair Detail' ?></h1>
        <p class="text-secondary mb-0">Live market data, feed configuration, and recent trades.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/markets/pairs" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <?php if ($pair): ?>
        <a href="/admin/markets/feed?search=<?= e((string)$pair['symbol']) ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-satellite-dish me-1"></i>Feed</a>
        <a href="/trade?pair=<?= urlencode((string)$pair['symbol']) ?>" target="_blank" class="btn btn-outline-info btn-sm"><i class="fas fa-chart-line me-1"></i>Terminal</a>
        <?php endif; ?>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php elseif ($pair): ?>

<!-- Market Header -->
<div class="row g-3 mb-4">
    <?php
    $chg  = (float)($pair['change_24h_percent'] ?? 0);
    $prec = (int)($pair['price_precision'] ?? 2);
    $kpis = [
        ['Last Price',  number_format((float)($pair['last_price'] ?? 0), $prec),  $chg >= 0 ? 'text-success' : 'text-danger'],
        ['24H Change',  ($chg >= 0 ? '+' : '') . number_format($chg, 2) . '%',    $chg >= 0 ? 'text-success' : 'text-danger'],
        ['24H High',    number_format((float)($pair['high_24h'] ?? 0), $prec),    'text-secondary'],
        ['24H Low',     number_format((float)($pair['low_24h'] ?? 0), $prec),     'text-secondary'],
        ['Volume 24H',  number_format((float)($pair['volume_24h'] ?? 0), 2),      'text-info'],
        ['Best Bid',    number_format((float)($pair['best_bid'] ?? 0), $prec),    'text-success'],
        ['Best Ask',    number_format((float)($pair['best_ask'] ?? 0), $prec),    'text-danger'],
        ['Max Leverage',($pair['max_leverage'] ?? '1') . 'x',                      'text-warning'],
    ];
    ?>
    <?php foreach ($kpis as [$label, $val, $cls]): ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary mb-1"><?= e($label) ?></div>
            <div class="h5 mb-0 <?= e($cls) ?>"><?= e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Left: details + feed config -->
    <div class="col-lg-5">
        <!-- Pair Info -->
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">Pair Configuration</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <?php
                    $fields = [
                        'Symbol'         => $pair['symbol'],
                        'Market Type'    => ucfirst($pair['market_type'] ?? '-'),
                        'Base Currency'  => $pair['base_code'] . ' (' . $pair['base_name'] . ')',
                        'Quote Currency' => $pair['quote_code'] . ' (' . $pair['quote_name'] . ')',
                        'Price Precision'=> $pair['price_precision'],
                        'Qty Precision'  => $pair['quantity_precision'],
                        'Min Order'      => $pair['min_order_size'],
                        'Min Notional'   => $pair['min_notional'],
                        'Maker Fee'      => number_format((float)($pair['maker_fee_percent'] ?? 0), 4) . '%',
                        'Taker Fee'      => number_format((float)($pair['taker_fee_percent'] ?? 0), 4) . '%',
                        'Active'         => (int)($pair['is_active'] ?? 0) ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-danger">No</span>',
                        'Trading'        => (int)($pair['trading_enabled'] ?? 0) ? '<span class="badge text-bg-info">Enabled</span>' : '<span class="badge text-bg-secondary">Paused</span>',
                        'Visible'        => (int)($pair['is_visible'] ?? 0) ? 'Yes' : 'No',
                        'Ticker Updated' => $pair['ticker_updated_at'] ?? 'N/A',
                    ];
                    ?>
                    <?php foreach ($fields as $k => $v): ?>
                    <tr>
                        <td class="text-secondary small" width="45%"><?= e($k) ?></td>
                        <td class="small fw-semibold"><?= $v ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Feed Subscription -->
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3"><i class="fas fa-satellite-dish me-1 text-warning"></i>Price Feed Configuration</h2>
            <?php if ($subscription): ?>
            <div class="table-responsive mb-3">
                <table class="table table-dark table-sm mb-0">
                    <?php
                    $feedFields = [
                        'Primary Provider' => $subscription['primary_provider_name'] ?? '-',
                        'Fallback Provider'=> $subscription['fallback_provider_name'] ?? 'None',
                        'Feed Mode'        => ucfirst($subscription['feed_mode'] ?? '-'),
                        'Poll Interval'    => ($subscription['poll_interval_seconds'] ?? 0) . 's',
                        'Max Staleness'    => ($subscription['max_allowed_staleness_seconds'] ?? 0) . 's',
                        'Status'           => (int)($subscription['is_active'] ?? 0) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-danger">Inactive</span>',
                    ];
                    ?>
                    <?php foreach ($feedFields as $k => $v): ?>
                    <tr><td class="text-secondary small" width="50%"><?= e($k) ?></td><td class="small"><?= $v ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php else: ?>
            <p class="text-warning small mb-3"><i class="fas fa-exclamation-triangle me-1"></i>No price feed configured for this pair.</p>
            <?php endif; ?>

            <!-- Edit Feed Form -->
            <form data-ajax="true" action="/admin/markets/feed/save" method="post">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="trading_pair_id" value="<?= (int)$pair['id'] ?>">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small">Primary Provider</label>
                        <select class="form-select form-select-sm" name="primary_provider_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($providers as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>" <?= (int)($subscription['primary_provider_id'] ?? 0) === (int)$prov['id'] ? 'selected' : '' ?>>
                                <?= e((string)$prov['name']) ?> (<?= e((string)$prov['provider_code']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Fallback Provider</label>
                        <select class="form-select form-select-sm" name="fallback_provider_id">
                            <option value="">None</option>
                            <?php foreach ($providers as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>" <?= (int)($subscription['fallback_provider_id'] ?? 0) === (int)$prov['id'] ? 'selected' : '' ?>>
                                <?= e((string)$prov['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Feed Mode</label>
                        <select class="form-select form-select-sm" name="feed_mode">
                            <option value="websocket" <?= ($subscription['feed_mode'] ?? '') === 'websocket' ? 'selected' : '' ?>>WebSocket</option>
                            <option value="polling" <?= ($subscription['feed_mode'] ?? '') === 'polling' ? 'selected' : '' ?>>Polling</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Poll Interval (s)</label>
                        <input class="form-control form-control-sm" type="number" name="poll_interval_seconds"
                               value="<?= (int)($subscription['poll_interval_seconds'] ?? 30) ?>" min="1" max="3600">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Max Staleness (s)</label>
                        <input class="form-control form-control-sm" type="number" name="max_allowed_staleness_seconds"
                               value="<?= (int)($subscription['max_allowed_staleness_seconds'] ?? 30) ?>" min="5">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="feedActive"
                                <?= (int)($subscription['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="feedActive">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-warning w-100"><i class="fas fa-save me-1"></i>Save Feed Config</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Right: candle chart + recent trades -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-3 mb-3">
            <h2 class="h6 mb-3">1H Candlestick Chart</h2>
            <div id="candleChart" style="height:280px"></div>
        </div>
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Recent Trades</h2>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Side</th><th>Price</th><th>Quantity</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTrades as $t): ?>
                        <tr>
                            <td><span class="badge text-bg-<?= ($t['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>"><?= e(ucfirst((string)($t['side'] ?? '-'))) ?></span></td>
                            <td class="<?= ($t['side'] ?? '') === 'buy' ? 'text-success' : 'text-danger' ?>"><?= number_format((float)($t['price'] ?? 0), $prec) ?></td>
                            <td><?= number_format((float)($t['quantity'] ?? 0), (int)($pair['quantity_precision'] ?? 6)) ?></td>
                            <td class="text-secondary small"><?= e((string)($t['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentTrades): ?>
                        <tr><td colspan="4" class="text-center text-secondary">No recent trades.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($candles): ?>
    const candles = <?= json_encode(array_values($candles)) ?>;
    const series = candles.map(c => ({
        x: new Date(c.bucket * 1000),
        y: [parseFloat(c.open_price), parseFloat(c.high_price), parseFloat(c.low_price), parseFloat(c.close_price)]
    }));
    if (typeof ApexCharts !== 'undefined' && series.length) {
        new ApexCharts(document.getElementById('candleChart'), {
            chart:  { type: 'candlestick', height: 280, background: 'transparent', toolbar: { show: false } },
            theme:  { mode: 'dark' },
            series: [{ data: series }],
            xaxis:  { type: 'datetime', labels: { style: { colors: '#94a3b8' } } },
            yaxis:  { labels: { style: { colors: '#94a3b8' } } },
            grid:   { borderColor: 'rgba(148,163,184,0.1)' },
        }).render();
    } else if (!series.length) {
        document.getElementById('candleChart').innerHTML = '<p class="text-secondary text-center pt-4">No candlestick data yet.</p>';
    }
    <?php else: ?>
    document.getElementById('candleChart').innerHTML = '<p class="text-secondary text-center pt-4">No candlestick data yet.</p>';
    <?php endif; ?>
});
</script>
