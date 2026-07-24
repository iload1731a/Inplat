<?php declare(strict_types=1); ?>
<?php
$pair         = is_array($pair ?? null)         ? $pair         : null;
$inWatchlist  = (bool)($inWatchlist ?? false);
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$candles1h    = is_array($candles1h ?? null)    ? $candles1h    : [];
$candles1d    = is_array($candles1d ?? null)    ? $candles1d    : [];
$error        = (string)($error ?? '');
$csrf         = \App\Libraries\Csrf::token();
?>
<style>
.price-big { font-size: 2rem; font-weight: 700; line-height: 1.1; }
.stat-card { background: rgba(15,23,42,.5); border: 1px solid rgba(148,163,184,.1); border-radius: 10px; padding: .75rem 1rem; }
.orderbook-sell td { color: #f87171; }
.orderbook-buy  td { color: #34d399; }
</style>

<?php require app_path('app/views/user/_nav.php'); ?>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= e($error) ?></div>
<?php elseif ($pair): ?>

<div class="row g-4">
    <!-- Left: chart + market info -->
    <div class="col-lg-8">
        <!-- Header -->
        <div class="glass rounded-4 p-3 mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($pair['base_icon'])): ?>
                        <img src="<?= e($pair['base_icon']) ?>" alt="" style="width:42px;height:42px;border-radius:50%">
                    <?php else: ?>
                        <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#38bdf8,#6366f1);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;"><?= e(mb_substr((string)$pair['base_code'], 0, 2)) ?></div>
                    <?php endif; ?>
                    <div>
                        <h1 class="h4 mb-0 fw-bold"><?= e((string)$pair['symbol']) ?></h1>
                        <div class="text-secondary small"><?= e((string)$pair['base_name']) ?> / <?= e((string)$pair['quote_name']) ?></div>
                    </div>
                    <span class="badge text-bg-<?= ['spot'=>'info','margin'=>'warning','futures'=>'danger'][$pair['market_type'] ?? ''] ?? 'secondary' ?> ms-1">
                        <?= e(ucfirst((string)$pair['market_type'])) ?>
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button id="wlBtn" class="btn btn-sm <?= $inWatchlist ? 'btn-warning' : 'btn-outline-warning' ?>"
                            onclick="toggleWatchlist()">
                        <i class="fas fa-star me-1"></i><?= $inWatchlist ? 'Watching' : 'Watch' ?>
                    </button>
                    <a href="/trade?pair=<?= urlencode((string)$pair['symbol']) ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-bolt me-1"></i>Trade Now
                    </a>
                </div>
            </div>

            <!-- Price Row -->
            <?php $chg = (float)($pair['change_24h_percent'] ?? 0); $prec = (int)($pair['price_precision'] ?? 2); ?>
            <div class="d-flex align-items-end gap-4 mt-3 flex-wrap">
                <div>
                    <div class="price-big <?= $chg >= 0 ? 'text-success' : 'text-danger' ?>" id="livePrice">
                        <?= number_format((float)($pair['last_price'] ?? 0), $prec) ?>
                    </div>
                    <div class="<?= $chg >= 0 ? 'text-success' : 'text-danger' ?> small">
                        <?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>% (24H)
                    </div>
                </div>
                <?php
                $stats = [
                    ['24H High',   number_format((float)($pair['high_24h'] ?? 0), $prec),   'text-success'],
                    ['24H Low',    number_format((float)($pair['low_24h'] ?? 0), $prec),    'text-danger'],
                    ['Best Bid',   number_format((float)($pair['best_bid'] ?? 0), $prec),   'text-secondary'],
                    ['Best Ask',   number_format((float)($pair['best_ask'] ?? 0), $prec),   'text-secondary'],
                    ['Volume 24H', number_format((float)($pair['volume_24h'] ?? 0), 2),     'text-info'],
                    ['Max Leverage', ($pair['max_leverage'] ?? '1') . 'x',                       'text-warning'],
                ];
                ?>
                <?php foreach ($stats as [$label, $val, $cls]): ?>
                <div>
                    <div class="text-secondary" style="font-size:.7rem"><?= e($label) ?></div>
                    <div class="fw-semibold <?= e($cls) ?> small"><?= e((string)$val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chart Tabs -->
        <div class="glass rounded-4 p-3 mb-3">
            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-xs btn-outline-info active" id="btn1h" onclick="showChart('1h')">1H</button>
                <button class="btn btn-xs btn-outline-info" id="btn1d" onclick="showChart('1d')">1D</button>
            </div>
            <div id="chart1h" style="height:300px"></div>
            <div id="chart1d" style="height:300px;display:none"></div>
        </div>

        <!-- Market Info -->
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Market Configuration</h2>
            <div class="row g-3">
                <?php
                $infoFields = [
                    ['Maker Fee',           number_format((float)($pair['maker_fee_percent'] ?? 0), 4) . '%'],
                    ['Taker Fee',           number_format((float)($pair['taker_fee_percent'] ?? 0), 4) . '%'],
                    ['Min Order Size',      $pair['min_order_size'] ?? '0'],
                    ['Max Order Size',      $pair['max_order_size'] ?? 'Unlimited'],
                    ['Min Notional',        $pair['min_notional'] ?? '0'],
                    ['Price Precision',     $pair['price_precision'] ?? '2'],
                    ['Quantity Precision',  $pair['quantity_precision'] ?? '6'],
                    ['Max Leverage',        ($pair['max_leverage'] ?? '1') . 'x'],
                ];
                ?>
                <?php foreach ($infoFields as [$k, $v]): ?>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="text-secondary" style="font-size:.7rem"><?= e($k) ?></div>
                        <div class="fw-semibold small mt-1"><?= e((string)$v) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: recent trades -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-3" style="height:fit-content">
            <h2 class="h6 mb-3">Recent Trades</h2>
            <div class="table-responsive" style="max-height:500px;overflow-y:auto">
                <table class="table table-user table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Side</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody id="recentTradesTbody">
                    <?php foreach ($recentTrades as $t): ?>
                        <tr>
                            <td><span class="badge text-bg-<?= ($t['side'] ?? '') === 'buy' ? 'success' : 'danger' ?>"><?= e(ucfirst((string)($t['side'] ?? '-'))) ?></span></td>
                            <td class="<?= ($t['side'] ?? '') === 'buy' ? 'text-success' : 'text-danger' ?> small"><?= number_format((float)($t['price'] ?? 0), $prec) ?></td>
                            <td class="small"><?= number_format((float)($t['quantity'] ?? 0), (int)($pair['quantity_precision'] ?? 6)) ?></td>
                            <td class="text-secondary" style="font-size:.7rem"><?= e(substr((string)($t['created_at'] ?? ''), 11, 8)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentTrades): ?>
                        <tr><td colspan="4" class="text-center text-secondary">No trades yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="csrfToken" value="<?= e($csrf) ?>">
<input type="hidden" id="pairId" value="<?= (int)$pair['id'] ?>">
<input type="hidden" id="pairPrec" value="<?= $prec ?>">

<script>
let chart1hInstance = null, chart1dInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof ApexCharts === 'undefined') return;

    function buildCandleSeries(candles) {
        return candles.map(c => ({
            x: new Date(c.bucket * 1000),
            y: [parseFloat(c.open_price), parseFloat(c.high_price), parseFloat(c.low_price), parseFloat(c.close_price)]
        }));
    }

    function candleOptions(series, height) {
        return {
            chart:  { type: 'candlestick', height: height, background: 'transparent', toolbar: { show: false } },
            theme:  { mode: 'dark' },
            series: [{ data: series }],
            xaxis:  { type: 'datetime', labels: { style: { colors: '#94a3b8' } } },
            yaxis:  { labels: { style: { colors: '#94a3b8' } } },
            grid:   { borderColor: 'rgba(148,163,184,0.1)' },
        };
    }

    const data1h = <?= json_encode(array_values($candles1h)) ?>;
    const data1d = <?= json_encode(array_values($candles1d)) ?>;

    if (data1h.length) {
        chart1hInstance = new ApexCharts(document.getElementById('chart1h'), candleOptions(buildCandleSeries(data1h), 300));
        chart1hInstance.render();
    } else {
        document.getElementById('chart1h').innerHTML = '<p class="text-secondary text-center pt-4">No 1H candlestick data yet.</p>';
    }

    if (data1d.length) {
        chart1dInstance = new ApexCharts(document.getElementById('chart1d'), candleOptions(buildCandleSeries(data1d), 300));
        chart1dInstance.render();
    } else {
        document.getElementById('chart1d').innerHTML = '<p class="text-secondary text-center pt-4">No 1D candlestick data yet.</p>';
    }
});

function showChart(which) {
    document.getElementById('chart1h').style.display = which === '1h' ? '' : 'none';
    document.getElementById('chart1d').style.display = which === '1d' ? '' : 'none';
    document.getElementById('btn1h').classList.toggle('active', which === '1h');
    document.getElementById('btn1d').classList.toggle('active', which === '1d');
}

function toggleWatchlist() {
    const pairId = document.getElementById('pairId').value;
    const csrf   = document.getElementById('csrfToken').value;
    fetch('/markets/watchlist/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
        body: '_token=' + encodeURIComponent(csrf) + '&pair_id=' + pairId
    }).then(r => r.json()).then(function(data) {
        if (data.ok) {
            const btn = document.getElementById('wlBtn');
            btn.className = 'btn btn-sm ' + (data.in_watchlist ? 'btn-warning' : 'btn-outline-warning');
            btn.innerHTML = '<i class="fas fa-star me-1"></i>' + (data.in_watchlist ? 'Watching' : 'Watch');
        } else {
            alert(data.message || 'Error.');
        }
    }).catch(function() { alert('Network error.'); });
}

// Auto-refresh ticker every 10s
setInterval(function() {
    const pairId = document.getElementById('pairId').value;
    const prec   = parseInt(document.getElementById('pairPrec').value) || 2;
    fetch('/markets/ticker?id=' + pairId)
        .then(r => r.json())
        .then(function(data) {
            if (!data.ok) return;
            const p = data.data;
            const el = document.getElementById('livePrice');
            if (el) el.textContent = parseFloat(p.last_price).toFixed(prec);
        }).catch(function(){});
}, 10000);
</script>

<?php else: ?>
<div class="alert alert-warning">Market not found.</div>
<?php endif; ?>
