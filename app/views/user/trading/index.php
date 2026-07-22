<?php declare(strict_types=1); ?>
<?php
$symbol       = (string)($symbol ?? '');
$pairs        = is_array($pairs ?? null) ? $pairs : [];
$ticker       = is_array($ticker ?? null) ? $ticker : [];
$orderBook    = is_array($orderBook ?? null) ? $orderBook : ['bids' => [], 'asks' => []];
$candles      = is_array($candles ?? null) ? $candles : [];
$recentTrades = is_array($recentTrades ?? null) ? $recentTrades : [];
$openOrders   = is_array($openOrders ?? null) ? $openOrders : [];
$positions    = is_array($positions ?? null) ? $positions : [];
$csrfToken    = (string)($csrfToken ?? \App\Libraries\Csrf::token());
$lastPrice    = number_format((float)($ticker['last_price'] ?? 0), 6);
$change24h    = (float)($ticker['change_24h_percent'] ?? 0);
$high24h      = number_format((float)($ticker['high_24h'] ?? 0), 6);
$low24h       = number_format((float)($ticker['low_24h'] ?? 0), 6);
$volume24h    = number_format((float)($ticker['volume_24h'] ?? 0), 2);
?>

<!-- Page styles specific to trading terminal -->
<style>
    .trading-terminal { display: grid; grid-template-columns: 220px 1fr 280px; grid-template-rows: auto 1fr auto; gap: 0; height: calc(100vh - 56px); overflow: hidden; }
    .trading-terminal > * { overflow: hidden; }
    .pair-list { border-right: 1px solid var(--border-c); overflow-y: auto; background: rgba(2,8,23,0.9); }
    .pair-list .pair-item { padding: .45rem .75rem; cursor: pointer; border-bottom: 1px solid rgba(148,163,184,0.06); transition: background .15s; font-size: .82rem; }
    .pair-list .pair-item:hover, .pair-list .pair-item.active { background: rgba(56,189,248,0.08); }
    .pair-list .pair-item .pair-name { font-weight: 600; color: #e2e8f0; }
    .pair-list .pair-item .pair-price { font-size: .78rem; }
    .pair-list .pair-item .pair-change.positive { color: #22c55e; }
    .pair-list .pair-item .pair-change.negative { color: #ef4444; }
    .chart-panel { display: flex; flex-direction: column; border-right: 1px solid var(--border-c); }
    .ticker-bar { padding: .5rem .75rem; background: rgba(2,8,23,0.95); border-bottom: 1px solid var(--border-c); display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
    .ticker-price { font-size: 1.35rem; font-weight: 700; }
    .ticker-stat { font-size: .78rem; }
    .ticker-stat .label { color: #64748b; display: block; }
    #tradingChart { flex: 1; min-height: 0; }
    .tabs-panel { border-top: 1px solid var(--border-c); max-height: 180px; overflow-y: auto; }
    .sidebar-panel { display: flex; flex-direction: column; gap: 0; overflow-y: auto; background: rgba(2,8,23,0.95); }
    .order-book { padding: .5rem; }
    .order-book-row { display: flex; justify-content: space-between; font-size: .78rem; padding: .15rem .25rem; position: relative; }
    .order-book-row.ask .bar { background: rgba(239,68,68,0.12); }
    .order-book-row.bid .bar { background: rgba(34,197,94,0.12); }
    .order-book-row .bar { position: absolute; right: 0; top: 0; bottom: 0; z-index: 0; }
    .order-book-row span { position: relative; z-index: 1; }
    .order-form { padding: .75rem; border-top: 1px solid var(--border-c); }
    .order-type-tabs .nav-link { font-size: .8rem; padding: .3rem .6rem; }
    .market-type-tabs .nav-link { font-size: .78rem; padding: .25rem .55rem; }
    .position-row { font-size: .82rem; }
    .position-pnl.positive { color: #22c55e; font-weight: 600; }
    .position-pnl.negative { color: #ef4444; font-weight: 600; }
    @media(max-width:1200px) { .trading-terminal { grid-template-columns: 180px 1fr 260px; } }
    @media(max-width:900px) {
        .trading-terminal { display: block; height: auto; }
        .pair-list { max-height: 160px; overflow-y: auto; display: flex; flex-wrap: nowrap; overflow-x: auto; border-right: none; border-bottom: 1px solid var(--border-c); }
        .pair-list .pair-item { min-width: 120px; }
    }
</style>

<!-- Ticker bar at top of full-width area -->
<div class="d-flex align-items-center justify-content-between mb-2 px-1" style="min-height:40px;">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="fw-bold fs-5" id="tickerSymbol"><?= e($symbol) ?></span>
        <span class="fs-4 fw-bold <?= $change24h >= 0 ? 'text-success' : 'text-danger' ?>" id="tickerLastPrice"><?= $lastPrice ?></span>
        <span class="badge <?= $change24h >= 0 ? 'text-bg-success' : 'text-bg-danger' ?>" id="tickerChange"><?= ($change24h >= 0 ? '+' : '') . number_format($change24h, 2) ?>%</span>
        <span class="text-secondary small">H: <span id="tickerHigh"><?= $high24h ?></span></span>
        <span class="text-secondary small">L: <span id="tickerLow"><?= $low24h ?></span></span>
        <span class="text-secondary small">Vol: <span id="tickerVol"><?= $volume24h ?></span></span>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm bg-dark text-light border-secondary" id="intervalSelect" style="width:90px;">
            <?php foreach (['1m','5m','15m','30m','1h','4h','1d','1w'] as $iv): ?>
                <option value="<?= e($iv) ?>" <?= $iv === '1h' ? 'selected' : '' ?>><?= e($iv) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm bg-dark text-light border-secondary" id="pairSelect" style="width:150px;">
            <?php foreach ($pairs as $p): ?>
                <option value="<?= e((string)($p['symbol'] ?? '')) ?>" <?= ($p['symbol'] ?? '') === $symbol ? 'selected' : '' ?>>
                    <?= e((string)($p['symbol'] ?? '')) ?> (<?= e((string)($p['market_type'] ?? 'spot')) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Main trading grid -->
<div class="trading-terminal">

    <!-- Pair List -->
    <div class="pair-list">
        <div class="px-2 py-1 text-uppercase small text-secondary fw-bold border-bottom" style="font-size:.65rem;border-color:var(--border-c)!important;">Markets</div>
        <input type="text" id="pairSearch" class="form-control form-control-sm bg-dark text-light border-0 border-bottom rounded-0" placeholder="Search..." style="border-color:var(--border-c)!important;">
        <?php foreach ($pairs as $p):
            $pc = (float)($p['change_24h_percent'] ?? 0);
        ?>
        <div class="pair-item <?= ($p['symbol'] ?? '') === $symbol ? 'active' : '' ?>"
             data-symbol="<?= e((string)($p['symbol'] ?? '')) ?>"
             onclick="selectPair('<?= e((string)($p['symbol'] ?? '')) ?>')">
            <div class="pair-name"><?= e((string)($p['symbol'] ?? '')) ?></div>
            <div class="d-flex justify-content-between">
                <span class="pair-price"><?= number_format((float)($p['last_price'] ?? 0), 4) ?></span>
                <span class="pair-change <?= $pc >= 0 ? 'positive' : 'negative' ?>"><?= ($pc >= 0 ? '+' : '') . number_format($pc, 2) ?>%</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart + Order Tabs -->
    <div class="chart-panel">
        <div id="tradingChart"></div>

        <!-- Bottom panel: Open Orders / Positions / Trade History -->
        <div class="tabs-panel glass">
            <ul class="nav nav-tabs nav-tabs-sm border-0 px-2 pt-1" id="bottomTabs">
                <li class="nav-item"><a class="nav-link active small py-1 px-2" data-bs-toggle="tab" href="#tabOpenOrders">Open Orders <span class="badge text-bg-secondary" id="openOrderCount"><?= count($openOrders) ?></span></a></li>
                <li class="nav-item"><a class="nav-link small py-1 px-2" data-bs-toggle="tab" href="#tabPositions">Positions <span class="badge text-bg-secondary" id="positionCount"><?= count($positions) ?></span></a></li>
                <li class="nav-item"><a class="nav-link small py-1 px-2" data-bs-toggle="tab" href="#tabRecentTrades">Recent Trades</a></li>
            </ul>
            <div class="tab-content">

                <!-- Open Orders -->
                <div class="tab-pane fade show active" id="tabOpenOrders">
                    <div class="table-responsive" style="max-height:130px;">
                        <table class="table table-dark table-sm mb-0" id="openOrdersTable">
                            <thead><tr><th>ID</th><th>Pair</th><th>Type</th><th>Side</th><th>Price</th><th>Qty</th><th>Filled</th><th>Status</th><th>Time</th><th>Action</th></tr></thead>
                            <tbody id="openOrdersBody">
                            <?php foreach ($openOrders as $o): ?>
                                <tr data-order-id="<?= (int)($o['id'] ?? 0) ?>">
                                    <td><?= (int)($o['id'] ?? 0) ?></td>
                                    <td><?= e((string)($o['pair_symbol'] ?? '-')) ?></td>
                                    <td><?= e((string)($o['order_type'] ?? '-')) ?></td>
                                    <td class="<?= ($o['side'] ?? '') === 'buy' ? 'text-success' : 'text-danger' ?>"><?= e(strtoupper((string)($o['side'] ?? ''))) ?></td>
                                    <td><?= $o['price'] !== null ? number_format((float)$o['price'], 6) : 'MARKET' ?></td>
                                    <td><?= number_format((float)($o['quantity'] ?? 0), 6) ?></td>
                                    <td><?= number_format((float)($o['filled_quantity'] ?? 0), 6) ?></td>
                                    <td><span class="badge text-bg-info"><?= e((string)($o['status'] ?? '-')) ?></span></td>
                                    <td class="text-secondary"><?= e((string)($o['created_at'] ?? '-')) ?></td>
                                    <td><button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="cancelOrder(<?= (int)($o['id'] ?? 0) ?>)">Cancel</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($openOrders === []): ?>
                                <tr><td colspan="10" class="text-center text-secondary">No open orders</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Positions -->
                <div class="tab-pane fade" id="tabPositions">
                    <div class="table-responsive" style="max-height:130px;">
                        <table class="table table-dark table-sm mb-0" id="positionsTable">
                            <thead><tr><th>ID</th><th>Pair</th><th>Side</th><th>Entry</th><th>Current</th><th>Qty</th><th>Leverage</th><th>Liq Price</th><th>Unreal. PnL</th><th>Action</th></tr></thead>
                            <tbody id="positionsBody">
                            <?php foreach ($positions as $pos):
                                $upnl = (float)($pos['unrealized_pnl'] ?? 0);
                            ?>
                                <tr>
                                    <td><?= (int)($pos['id'] ?? 0) ?></td>
                                    <td><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                                    <td class="<?= ($pos['side'] ?? '') === 'long' ? 'text-success' : 'text-danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? ''))) ?></td>
                                    <td><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                                    <td><?= number_format((float)($pos['current_price'] ?? 0), 6) ?></td>
                                    <td><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                                    <td><?= number_format((float)($pos['leverage'] ?? 1), 2) ?>x</td>
                                    <td><?= $pos['liquidation_price'] ? number_format((float)$pos['liquidation_price'], 6) : '—' ?></td>
                                    <td class="position-pnl <?= $upnl >= 0 ? 'positive' : 'negative' ?>"><?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 6) ?></td>
                                    <td><button class="btn btn-xs btn-outline-warning py-0 px-1" onclick="closePosition(<?= (int)($pos['id'] ?? 0) ?>)">Close</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($positions === []): ?>
                                <tr><td colspan="10" class="text-center text-secondary">No open positions</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Trades -->
                <div class="tab-pane fade" id="tabRecentTrades">
                    <div class="table-responsive" style="max-height:130px;">
                        <table class="table table-dark table-sm mb-0" id="recentTradesTable">
                            <thead><tr><th>Price</th><th>Qty</th><th>Value</th><th>Side</th><th>Time</th></tr></thead>
                            <tbody id="recentTradesBody">
                            <?php foreach ($recentTrades as $t): ?>
                                <tr>
                                    <td class="<?= ($t['maker_side'] ?? '') === 'buy' ? 'text-success' : 'text-danger' ?>"><?= number_format((float)($t['price'] ?? 0), 6) ?></td>
                                    <td><?= number_format((float)($t['quantity'] ?? 0), 6) ?></td>
                                    <td><?= number_format((float)($t['quote_amount'] ?? 0), 4) ?></td>
                                    <td class="text-secondary small"><?= e((string)($t['maker_side'] ?? '-')) ?></td>
                                    <td class="text-secondary small"><?= e((string)($t['executed_at'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($recentTrades === []): ?>
                                <tr><td colspan="5" class="text-center text-secondary">No recent trades</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div><!-- /.tabs-panel -->
    </div><!-- /.chart-panel -->

    <!-- Right Sidebar: Order Book + Order Form -->
    <div class="sidebar-panel">

        <!-- Order Book -->
        <div class="order-book">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">Order Book</span>
                <span class="text-secondary small"><?= e($symbol) ?></span>
            </div>
            <!-- Asks (sell) - reversed so highest ask is at top of section -->
            <div class="d-flex justify-content-between text-secondary mb-1" style="font-size:.72rem;">
                <span>Price</span><span>Quantity</span><span>Total</span>
            </div>
            <div id="asksContainer">
            <?php
            $asks = array_reverse($orderBook['asks'] ?? []);
            $maxAskQty = max(0.001, array_reduce($asks, fn($c, $r) => max($c, (float)($r['quantity'] ?? 0)), 0.001));
            foreach ($asks as $ask):
                $pct = min(100, (float)($ask['quantity'] ?? 0) / $maxAskQty * 100);
            ?>
                <div class="order-book-row ask">
                    <div class="bar" style="width:<?= number_format($pct, 1) ?>%;"></div>
                    <span class="text-danger"><?= number_format((float)($ask['price'] ?? 0), 6) ?></span>
                    <span><?= number_format((float)($ask['quantity'] ?? 0), 4) ?></span>
                    <span class="text-secondary"><?= number_format((float)($ask['price'] ?? 0) * (float)($ask['quantity'] ?? 0), 2) ?></span>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- Spread -->
            <div class="text-center py-1 my-1 border-top border-bottom" style="border-color:var(--border-c)!important;font-size:.8rem;">
                <span class="fw-bold <?= $change24h >= 0 ? 'text-success' : 'text-danger' ?>" id="spreadPrice"><?= $lastPrice ?></span>
            </div>

            <!-- Bids (buy) -->
            <div id="bidsContainer">
            <?php
            $bids = $orderBook['bids'] ?? [];
            $maxBidQty = max(0.001, array_reduce($bids, fn($c, $r) => max($c, (float)($r['quantity'] ?? 0)), 0.001));
            foreach ($bids as $bid):
                $pct = min(100, (float)($bid['quantity'] ?? 0) / $maxBidQty * 100);
            ?>
                <div class="order-book-row bid">
                    <div class="bar" style="width:<?= number_format($pct, 1) ?>%;"></div>
                    <span class="text-success"><?= number_format((float)($bid['price'] ?? 0), 6) ?></span>
                    <span><?= number_format((float)($bid['quantity'] ?? 0), 4) ?></span>
                    <span class="text-secondary"><?= number_format((float)($bid['price'] ?? 0) * (float)($bid['quantity'] ?? 0), 2) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($bids === [] && $asks === []): ?>
                <div class="text-center text-secondary py-2 small">No orders in book</div>
            <?php endif; ?>
            </div>
        </div>

        <!-- Order Form -->
        <div class="order-form">
            <!-- Market Type Tabs: Spot / Margin / Futures -->
            <ul class="nav nav-tabs nav-fill market-type-tabs mb-2" id="marketTypeTabs">
                <li class="nav-item"><a class="nav-link active" data-market="spot" href="#" onclick="setMarketType('spot',this);return false;">Spot</a></li>
                <li class="nav-item"><a class="nav-link" data-market="margin" href="#" onclick="setMarketType('margin',this);return false;">Margin</a></li>
                <li class="nav-item"><a class="nav-link" data-market="futures" href="#" onclick="setMarketType('futures',this);return false;">Futures</a></li>
            </ul>

            <!-- Order Type: Limit / Market / Stop -->
            <ul class="nav nav-tabs order-type-tabs mb-2" id="orderTypeTabs">
                <li class="nav-item"><a class="nav-link active" data-otype="limit" href="#" onclick="setOrderType('limit',this);return false;">Limit</a></li>
                <li class="nav-item"><a class="nav-link" data-otype="market" href="#" onclick="setOrderType('market',this);return false;">Market</a></li>
                <li class="nav-item"><a class="nav-link" data-otype="stop_limit" href="#" onclick="setOrderType('stop_limit',this);return false;">Stop</a></li>
            </ul>

            <!-- Buy / Sell tabs -->
            <div class="d-flex mb-2 gap-1">
                <button class="btn btn-success flex-fill btn-sm" id="buyBtn" onclick="setSide('buy')">BUY</button>
                <button class="btn btn-outline-danger flex-fill btn-sm" id="sellBtn" onclick="setSide('sell')">SELL</button>
            </div>

            <div id="orderForm">
                <div class="mb-2" id="priceField">
                    <label class="form-label form-label-sm mb-1">Price</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control bg-dark text-light border-secondary" id="orderPrice" step="any" min="0" placeholder="0.00">
                        <span class="input-group-text bg-dark text-secondary border-secondary" id="quoteCode">USDT</span>
                    </div>
                </div>
                <div class="mb-2" id="stopPriceField" style="display:none;">
                    <label class="form-label form-label-sm mb-1">Stop Price</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control bg-dark text-light border-secondary" id="orderStopPrice" step="any" min="0" placeholder="0.00">
                        <span class="input-group-text bg-dark text-secondary border-secondary">USDT</span>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Quantity</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control bg-dark text-light border-secondary" id="orderQty" step="any" min="0" placeholder="0.00">
                        <span class="input-group-text bg-dark text-secondary border-secondary" id="baseCode">BTC</span>
                    </div>
                </div>

                <!-- Qty % buttons -->
                <div class="d-flex gap-1 mb-2">
                    <?php foreach ([25, 50, 75, 100] as $pct): ?>
                        <button class="btn btn-xs btn-outline-secondary flex-fill" onclick="setQtyPct(<?= $pct ?>)"><?= $pct ?>%</button>
                    <?php endforeach; ?>
                </div>

                <!-- Leverage (futures only) -->
                <div class="mb-2" id="leverageField" style="display:none;">
                    <label class="form-label form-label-sm mb-1">Leverage: <span id="leverageLabel">1x</span></label>
                    <input type="range" class="form-range" id="leverageRange" min="1" max="100" step="1" value="1" oninput="document.getElementById('leverageLabel').textContent=this.value+'x'">
                </div>

                <div class="mb-2 small text-secondary">
                    <div class="d-flex justify-content-between">
                        <span>Available:</span>
                        <span id="availableBalance">—</span>
                    </div>
                    <div class="d-flex justify-content-between" id="estimatedCostRow">
                        <span>Est. Cost:</span>
                        <span id="estimatedCost">—</span>
                    </div>
                </div>

                <button class="btn btn-success w-100 btn-sm fw-bold" id="placeOrderBtn" onclick="placeOrder()">
                    <span id="placeOrderLabel">BUY <?= e($symbol) ?></span>
                </button>
            </div>
        </div><!-- /.order-form -->

    </div><!-- /.sidebar-panel -->

</div><!-- /.trading-terminal -->

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"></script>
<script>
/* ============================================================
   Trading Terminal JavaScript
   ============================================================ */

const CSRF     = <?= json_encode($csrfToken) ?>;
let currentSymbol    = <?= json_encode($symbol) ?>;
let currentInterval  = '1h';
let currentSide      = 'buy';
let currentMarket    = 'spot';
let currentOrderType = 'limit';
let chart            = null;
let candleSeries     = null;
let refreshTimer     = null;

// ---- Initial Candle data ----
const initialCandles = <?= json_encode($candles) ?>;

// ---- Initialize chart ----
function initChart(data) {
    if (chart) { chart.destroy(); chart = null; }

    const formatted = data.map(c => ({ x: new Date(c.t), y: [c.o, c.h, c.l, c.c] }));
    const volSeries = data.map(c => ({ x: new Date(c.t), y: c.v }));

    const options = {
        series: [
            { name: 'Candles', type: 'candlestick', data: formatted },
            { name: 'Volume',  type: 'bar',         data: volSeries }
        ],
        chart: {
            id: 'tradingChart',
            height: '100%',
            background: 'transparent',
            toolbar: { show: true, tools: { download: false, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
            animations: { enabled: false },
        },
        plotOptions: {
            candlestick: { colors: { upward: '#22c55e', downward: '#ef4444' }, wick: { useFillColor: true } },
            bar: { columnWidth: '80%' }
        },
        colors: ['transparent', 'rgba(56,189,248,0.4)'],
        yaxis: [
            { seriesName: 'Candles', labels: { style: { colors: '#94a3b8' }, formatter: v => v ? v.toFixed(4) : '' }, tooltip: { enabled: true } },
            { seriesName: 'Volume',  opposite: true, labels: { style: { colors: '#64748b' }, formatter: v => v ? v.toFixed(2) : '' }, show: false }
        ],
        xaxis: { type: 'datetime', labels: { style: { colors: '#94a3b8' }, datetimeUTC: false } },
        grid: { borderColor: '#1e293b', strokeDashArray: 3 },
        tooltip: { theme: 'dark', x: { format: 'dd MMM HH:mm' } },
        legend: { show: false },
    };

    chart = new ApexCharts(document.getElementById('tradingChart'), options);
    chart.render();
}

// ---- Pair selection ----
function selectPair(sym) {
    currentSymbol = sym;
    document.querySelectorAll('.pair-item').forEach(el => el.classList.toggle('active', el.dataset.symbol === sym));
    document.getElementById('pairSelect').value = sym;
    document.getElementById('tickerSymbol').textContent = sym;
    document.getElementById('placeOrderLabel').textContent = (currentSide === 'buy' ? 'BUY ' : 'SELL ') + sym;
    fetchTicker();
    fetchOrderBook();
    fetchCandles();
    fetchMyOrders();
}

document.getElementById('pairSelect').addEventListener('change', function() { selectPair(this.value); });
document.getElementById('intervalSelect').addEventListener('change', function() { currentInterval = this.value; fetchCandles(); });

// ---- Pair search ----
document.getElementById('pairSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.pair-item').forEach(el => {
        el.style.display = el.dataset.symbol.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ---- Market type & order type ----
function setMarketType(type, el) {
    currentMarket = type;
    document.querySelectorAll('[data-market]').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('leverageField').style.display = (type === 'futures' || type === 'margin') ? '' : 'none';
    if (type === 'margin') { document.getElementById('leverageRange').max = 10; }
    else if (type === 'futures') { document.getElementById('leverageRange').max = 100; }
    else { document.getElementById('leverageRange').max = 1; }
}

function setOrderType(type, el) {
    currentOrderType = type;
    document.querySelectorAll('[data-otype]').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    const isMarket = (type === 'market');
    const isStop   = (type === 'stop_limit' || type === 'stop_market');
    document.getElementById('priceField').style.display     = isMarket ? 'none' : '';
    document.getElementById('stopPriceField').style.display = isStop   ? ''     : 'none';
}

function setSide(side) {
    currentSide = side;
    document.getElementById('buyBtn').className  = side === 'buy'  ? 'btn btn-success flex-fill btn-sm'         : 'btn btn-outline-success flex-fill btn-sm';
    document.getElementById('sellBtn').className = side === 'sell' ? 'btn btn-danger flex-fill btn-sm'          : 'btn btn-outline-danger flex-fill btn-sm';
    document.getElementById('placeOrderBtn').className = 'btn btn-' + (side === 'buy' ? 'success' : 'danger') + ' w-100 btn-sm fw-bold';
    document.getElementById('placeOrderLabel').textContent = (side === 'buy' ? 'BUY ' : 'SELL ') + currentSymbol;
}

function setQtyPct(pct) {
    const balText = document.getElementById('availableBalance').textContent.replace(/[^0-9.]/g, '');
    const avail   = parseFloat(balText) || 0;
    if (avail <= 0) return;
    const priceInput = document.getElementById('orderPrice').value;
    const lastPrice  = parseFloat(document.getElementById('tickerLastPrice').textContent) || 0;
    const usePrice   = parseFloat(priceInput) > 0 ? parseFloat(priceInput) : lastPrice;
    if (currentSide === 'buy' && usePrice > 0) {
        // Buy: available is quote; qty = (avail * pct%) / price
        const qty = (avail * pct / 100) / usePrice;
        document.getElementById('orderQty').value = qty > 0 ? qty.toFixed(6) : '';
    } else if (currentSide === 'sell') {
        // Sell: available is base; qty = avail * pct%
        const qty = avail * pct / 100;
        document.getElementById('orderQty').value = qty > 0 ? qty.toFixed(6) : '';
    }
    // Update cost estimate
    const qty2 = parseFloat(document.getElementById('orderQty').value) || 0;
    const cost  = usePrice > 0 ? (qty2 * usePrice).toFixed(4) : '—';
    document.getElementById('estimatedCost').textContent = cost;
}

// ---- AJAX helpers ----
async function apiFetch(url) {
    try {
        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return await r.json();
    } catch { return { ok: false }; }
}

async function apiPost(url, data) {
    try {
        data._token = CSRF;
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });
        return await r.json();
    } catch(e) { return { ok: false, message: e.message }; }
}

// ---- Fetch functions ----
async function fetchTicker() {
    const res = await apiFetch('/trading/ticker?symbol=' + encodeURIComponent(currentSymbol));
    if (!res.ok) return;
    const t = res.data;
    document.getElementById('tickerLastPrice').textContent = parseFloat(t.last_price || 0).toFixed(6);
    document.getElementById('tickerChange').textContent    = (parseFloat(t.change_24h_percent || 0) >= 0 ? '+' : '') + parseFloat(t.change_24h_percent || 0).toFixed(2) + '%';
    document.getElementById('tickerHigh').textContent      = parseFloat(t.high_24h || 0).toFixed(6);
    document.getElementById('tickerLow').textContent       = parseFloat(t.low_24h  || 0).toFixed(6);
    document.getElementById('tickerVol').textContent       = parseFloat(t.volume_24h || 0).toFixed(2);
    document.getElementById('spreadPrice').textContent     = parseFloat(t.last_price || 0).toFixed(6);
    const chg = parseFloat(t.change_24h_percent || 0);
    document.getElementById('tickerChange').className      = 'badge text-bg-' + (chg >= 0 ? 'success' : 'danger');
}

async function fetchOrderBook() {
    const res = await apiFetch('/trading/orderbook?symbol=' + encodeURIComponent(currentSymbol) + '&depth=15');
    if (!res.ok) return;
    renderOrderBook(res.data.bids || [], res.data.asks || []);
}

function renderOrderBook(bids, asks) {
    const maxAsk = Math.max(0.001, ...asks.map(r => parseFloat(r.quantity)));
    const maxBid = Math.max(0.001, ...bids.map(r => parseFloat(r.quantity)));

    const asksHtml = [...asks].reverse().map(r => {
        const pct = Math.min(100, parseFloat(r.quantity) / maxAsk * 100);
        return `<div class="order-book-row ask"><div class="bar" style="width:${pct.toFixed(1)}%"></div><span class="text-danger">${parseFloat(r.price).toFixed(6)}</span><span>${parseFloat(r.quantity).toFixed(4)}</span><span class="text-secondary">${(parseFloat(r.price)*parseFloat(r.quantity)).toFixed(2)}</span></div>`;
    }).join('');

    const bidsHtml = bids.map(r => {
        const pct = Math.min(100, parseFloat(r.quantity) / maxBid * 100);
        return `<div class="order-book-row bid"><div class="bar" style="width:${pct.toFixed(1)}%"></div><span class="text-success">${parseFloat(r.price).toFixed(6)}</span><span>${parseFloat(r.quantity).toFixed(4)}</span><span class="text-secondary">${(parseFloat(r.price)*parseFloat(r.quantity)).toFixed(2)}</span></div>`;
    }).join('');

    document.getElementById('asksContainer').innerHTML = asksHtml || '<div class="text-center text-secondary py-1 small">No asks</div>';
    document.getElementById('bidsContainer').innerHTML = bidsHtml || '<div class="text-center text-secondary py-1 small">No bids</div>';
}

async function fetchCandles() {
    const res = await apiFetch('/trading/candles?symbol=' + encodeURIComponent(currentSymbol) + '&interval=' + currentInterval + '&limit=200');
    if (!res.ok || !res.data) return;
    if (chart) {
        const formatted = res.data.map(c => ({ x: new Date(c.t), y: [c.o, c.h, c.l, c.c] }));
        const volSeries = res.data.map(c => ({ x: new Date(c.t), y: c.v }));
        chart.updateSeries([{ name: 'Candles', type: 'candlestick', data: formatted }, { name: 'Volume', type: 'bar', data: volSeries }]);
    }
}

async function fetchMyOrders() {
    const res = await apiFetch('/trading/my-orders?symbol=' + encodeURIComponent(currentSymbol));
    if (!res.ok) return;
    renderOpenOrders(res.data || []);
}

async function fetchMyPositions() {
    const res = await apiFetch('/trading/my-positions?status=open');
    if (!res.ok) return;
    renderPositions(res.data || []);
}

async function fetchRecentTrades() {
    const res = await apiFetch('/trading/recent-trades?symbol=' + encodeURIComponent(currentSymbol) + '&limit=30');
    if (!res.ok) return;
    renderRecentTrades(res.data || []);
}

function renderOpenOrders(orders) {
    document.getElementById('openOrderCount').textContent = orders.length;
    const tbody = document.getElementById('openOrdersBody');
    if (!orders.length) { tbody.innerHTML = '<tr><td colspan="10" class="text-center text-secondary">No open orders</td></tr>'; return; }
    tbody.innerHTML = orders.map(o => `
        <tr>
            <td>${o.id}</td>
            <td>${o.pair_symbol||'-'}</td>
            <td>${o.order_type||'-'}</td>
            <td class="${o.side==='buy'?'text-success':'text-danger'}">${(o.side||'').toUpperCase()}</td>
            <td>${o.price?parseFloat(o.price).toFixed(6):'MARKET'}</td>
            <td>${parseFloat(o.quantity||0).toFixed(6)}</td>
            <td>${parseFloat(o.filled_quantity||0).toFixed(6)}</td>
            <td><span class="badge text-bg-info">${o.status||'-'}</span></td>
            <td class="text-secondary">${o.created_at||'-'}</td>
            <td><button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="cancelOrder(${o.id})">Cancel</button></td>
        </tr>`).join('');
}

function renderPositions(positions) {
    document.getElementById('positionCount').textContent = positions.length;
    const tbody = document.getElementById('positionsBody');
    if (!positions.length) { tbody.innerHTML = '<tr><td colspan="10" class="text-center text-secondary">No open positions</td></tr>'; return; }
    tbody.innerHTML = positions.map(p => {
        const upnl = parseFloat(p.unrealized_pnl||0);
        return `
        <tr>
            <td>${p.id}</td>
            <td>${p.pair_symbol||'-'}</td>
            <td class="${p.side==='long'?'text-success':'text-danger'}">${(p.side||'').toUpperCase()}</td>
            <td>${parseFloat(p.entry_price||0).toFixed(6)}</td>
            <td>${parseFloat(p.current_price||0).toFixed(6)}</td>
            <td>${parseFloat(p.quantity||0).toFixed(6)}</td>
            <td>${parseFloat(p.leverage||1).toFixed(2)}x</td>
            <td>${p.liquidation_price?parseFloat(p.liquidation_price).toFixed(6):'—'}</td>
            <td class="position-pnl ${upnl>=0?'positive':'negative'}">${(upnl>=0?'+':'')+upnl.toFixed(6)}</td>
            <td><button class="btn btn-xs btn-outline-warning py-0 px-1" onclick="closePosition(${p.id})">Close</button></td>
        </tr>`;
    }).join('');
}

function renderRecentTrades(trades) {
    const tbody = document.getElementById('recentTradesBody');
    if (!trades.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary">No recent trades</td></tr>'; return; }
    tbody.innerHTML = trades.map(t => `
        <tr>
            <td class="${t.maker_side==='buy'?'text-success':'text-danger'}">${parseFloat(t.price||0).toFixed(6)}</td>
            <td>${parseFloat(t.quantity||0).toFixed(6)}</td>
            <td>${parseFloat(t.quote_amount||0).toFixed(4)}</td>
            <td class="text-secondary small">${t.maker_side||'-'}</td>
            <td class="text-secondary small">${t.executed_at||'-'}</td>
        </tr>`).join('');
}

// ---- Order Actions ----
async function placeOrder() {
    const price    = document.getElementById('orderPrice').value;
    const stopPrice= document.getElementById('orderStopPrice').value;
    const qty      = document.getElementById('orderQty').value;
    const leverage = document.getElementById('leverageRange').value;

    if (!qty || parseFloat(qty) <= 0) { showAlert('error', 'Quantity is required'); return; }

    const payload = {
        symbol: currentSymbol,
        order_type: currentOrderType,
        side: currentSide,
        quantity: qty,
        price: price || '',
        stop_price: stopPrice || '',
        time_in_force: 'GTC',
        leverage: leverage,
        is_reduce_only: 0,
    };

    const endpoint = currentMarket === 'futures' ? '/trading/order/futures'
                   : currentMarket === 'margin'   ? '/trading/order/margin'
                   : '/trading/order/spot';

    document.getElementById('placeOrderBtn').disabled = true;
    const res = await apiPost(endpoint, payload);
    document.getElementById('placeOrderBtn').disabled = false;

    if (res.ok) {
        showAlert('success', 'Order placed! ID: ' + (res.data?.order_id || ''));
        document.getElementById('orderQty').value = '';
        fetchMyOrders();
        fetchOrderBook();
    } else {
        showAlert('error', res.message || 'Failed to place order');
    }
}

async function cancelOrder(orderId) {
    if (!confirm('Cancel order #' + orderId + '?')) return;
    const res = await apiPost('/trading/order/cancel', { order_id: orderId });
    if (res.ok) { showAlert('success', res.message || 'Order cancelled'); fetchMyOrders(); fetchOrderBook(); }
    else showAlert('error', res.message || 'Failed to cancel');
}

async function closePosition(positionId) {
    if (!confirm('Close position #' + positionId + ' at market price?')) return;
    const res = await apiPost('/trading/position/close', { position_id: positionId });
    if (res.ok) { showAlert('success', 'Position closed. PnL: ' + (res.data?.realized_pnl || '0')); fetchMyPositions(); }
    else showAlert('error', res.message || 'Failed to close position');
}

function showAlert(type, msg) {
    if (window.Swal) { Swal.fire({ icon: type === 'success' ? 'success' : 'error', title: msg, toast: true, position: 'top-end', timer: 3000, showConfirmButton: false }); }
    else { alert(msg); }
}

// ---- Auto-refresh ----
function startRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(() => {
        fetchTicker();
        fetchOrderBook();
        fetchRecentTrades();
    }, 5000);
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
    initChart(initialCandles);
    startRefresh();
});
</script>
