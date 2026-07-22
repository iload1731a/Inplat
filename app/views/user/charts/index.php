<?php declare(strict_types=1); ?>
<?php
$pair      = is_array($pair ?? null) ? $pair : null;
$pairs     = is_array($pairs ?? null) ? $pairs : [];
$symbol    = (string)($symbol ?? 'BTCUSDT');
$interval  = (string)($interval ?? '1h');
$prefs     = is_array($prefs ?? null) ? $prefs : [];
$templates = is_array($templates ?? null) ? $templates : [];
$csrfToken = (string)($csrfToken ?? \App\Libraries\Csrf::token());

$savedIndicators = $prefs['indicators'] ?? [];
$savedChartType  = (string)($prefs['chart_type'] ?? 'candlestick');
$savedLayout     = is_array($prefs['layout'] ?? null) ? $prefs['layout'] : [];
$showVolume      = (bool)($savedLayout['show_volume'] ?? true);
?>
<style>
.chart-container{display:grid;grid-template-columns:200px 1fr 240px;height:calc(100vh - 120px);gap:0;overflow:hidden}
.chart-sidebar{background:rgba(2,8,23,.95);border-right:1px solid var(--border-c);overflow-y:auto;font-size:.78rem}
.chart-main{display:flex;flex-direction:column;overflow:hidden}
.chart-toolbar{background:rgba(2,8,23,.98);border-bottom:1px solid var(--border-c);padding:.35rem .6rem;display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;min-height:42px}
#mainChart{flex:1;min-height:0}
.indicator-panel{border-top:1px solid var(--border-c);height:120px;min-height:0;overflow:hidden}
.chart-right{background:rgba(2,8,23,.95);border-left:1px solid var(--border-c);overflow-y:auto;font-size:.8rem}
.pair-item{padding:.4rem .65rem;cursor:pointer;border-bottom:1px solid rgba(148,163,184,.06);transition:background .12s}
.pair-item:hover,.pair-item.active{background:rgba(56,189,248,.09)}
.pair-sym{font-weight:600;color:#e2e8f0}
.pair-chg.pos{color:#22c55e}.pair-chg.neg{color:#ef4444}
.tb-btn{background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.2);color:#cbd5e1;font-size:.75rem;padding:.2rem .5rem;border-radius:4px;cursor:pointer;transition:background .12s;white-space:nowrap}
.tb-btn:hover,.tb-btn.active{background:rgba(56,189,248,.18);border-color:rgba(56,189,248,.5);color:#38bdf8}
.tb-sep{width:1px;height:18px;background:rgba(148,163,184,.15);margin:0 .15rem}
.ind-chip{display:inline-flex;align-items:center;gap:.25rem;background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.35);color:#a5b4fc;font-size:.72rem;padding:.15rem .45rem;border-radius:4px}
.ind-chip .rm{cursor:pointer;color:#94a3b8;margin-left:.2rem}.ind-chip .rm:hover{color:#ef4444}
.section-hdr{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;padding:.5rem .65rem .2rem;font-weight:700}
.stat-row{display:flex;justify-content:space-between;padding:.2rem .65rem;border-bottom:1px solid rgba(148,163,184,.05)}
.stat-lbl{color:#64748b}.stat-val{color:#e2e8f0;font-weight:500}
.vp-bar-wrap{display:flex;align-items:center;gap:.35rem;padding:.12rem .4rem;font-size:.72rem}
.vp-bar{height:8px;background:rgba(56,189,248,.45);border-radius:2px;min-width:2px;transition:width .3s}
@media(max-width:1100px){.chart-container{grid-template-columns:160px 1fr 0px}.chart-right{display:none}}
@media(max-width:720px){.chart-container{grid-template-columns:0px 1fr 0px}.chart-sidebar{display:none}}
</style>

<?php require app_path('app/views/user/_nav.php'); ?>

<div class="chart-container glass rounded-3 overflow-hidden" style="border:1px solid var(--border-c)">

    <!-- Pair List Sidebar -->
    <div class="chart-sidebar">
        <div class="px-2 pt-2 pb-1">
            <input type="text" id="pairSearch" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Search pair…" autocomplete="off">
        </div>
        <div class="section-hdr">Markets</div>
        <div id="pairList">
            <?php foreach ($pairs as $p):
                $pc = (float)($p['change_24h_percent'] ?? 0);
            ?>
            <div class="pair-item <?= ($p['symbol'] ?? '') === $symbol ? 'active' : '' ?>"
                 data-symbol="<?= htmlspecialchars((string)$p['symbol'], ENT_QUOTES) ?>"
                 data-type="<?= htmlspecialchars((string)$p['market_type'], ENT_QUOTES) ?>">
                <div class="pair-sym"><?= htmlspecialchars((string)$p['symbol'], ENT_QUOTES) ?></div>
                <div class="d-flex justify-content-between" style="gap:.4rem">
                    <span><?= number_format((float)$p['last_price'], 4) ?></span>
                    <span class="pair-chg <?= $pc >= 0 ? 'pos' : 'neg' ?>"><?= ($pc >= 0 ? '+' : '') . number_format($pc, 2) ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Chart Area -->
    <div class="chart-main">

        <!-- Toolbar -->
        <div class="chart-toolbar">
            <!-- Pair & Price Info -->
            <span class="fw-bold text-light me-1" id="tbSymbol" style="font-size:.9rem"><?= htmlspecialchars($symbol, ENT_QUOTES) ?></span>
            <span id="tbPrice" class="fw-bold" style="font-size:.9rem">—</span>
            <span id="tbChg" class="badge text-bg-secondary" style="font-size:.7rem">—</span>
            <div class="tb-sep"></div>

            <!-- Interval Selector -->
            <?php foreach (['1m','5m','15m','30m','1h','4h','1d','1w'] as $iv): ?>
            <button class="tb-btn <?= $iv === $interval ? 'active' : '' ?>" onclick="setInterval('<?= htmlspecialchars($iv, ENT_QUOTES) ?>')"><?= htmlspecialchars($iv, ENT_QUOTES) ?></button>
            <?php endforeach; ?>
            <div class="tb-sep"></div>

            <!-- Chart Type -->
            <select id="chartTypeSelect" class="tb-btn" style="padding:.18rem .4rem;cursor:pointer" onchange="setChartType(this.value)">
                <?php foreach (['candlestick'=>'Candles','line'=>'Line','bar'=>'OHLC','area'=>'Area','heikin_ashi'=>'Heikin-Ashi'] as $val => $label): ?>
                <option value="<?= htmlspecialchars($val, ENT_QUOTES) ?>" <?= $val === $savedChartType ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="tb-sep"></div>

            <!-- Indicator Buttons -->
            <button class="tb-btn" onclick="addIndicator('sma_20')">SMA</button>
            <button class="tb-btn" onclick="addIndicator('ema_50')">EMA</button>
            <button class="tb-btn" onclick="addIndicator('bb_20')">BB</button>
            <button class="tb-btn" onclick="addIndicator('vwap')">VWAP</button>
            <button class="tb-btn" onclick="addIndicator('rsi_14')">RSI</button>
            <button class="tb-btn" onclick="addIndicator('macd')">MACD</button>
            <button class="tb-btn" onclick="addIndicator('atr_14')">ATR</button>
            <button class="tb-btn" onclick="addIndicator('stoch_14_3')">Stoch</button>
            <div class="tb-sep"></div>

            <!-- Volume Toggle -->
            <button class="tb-btn <?= $showVolume ? 'active' : '' ?>" id="volToggleBtn" onclick="toggleVolume()">Vol</button>
            <!-- Compare Link -->
            <a href="/charts/compare?pairs=<?= urlencode($symbol . ',ETHUSDT') ?>" class="tb-btn text-decoration-none">Compare</a>
            <!-- Save Prefs -->
            <button class="tb-btn ms-auto" onclick="savePreferences()"><i class="fas fa-save me-1"></i>Save</button>
            <!-- Templates -->
            <button class="tb-btn" data-bs-toggle="modal" data-bs-target="#tplModal"><i class="fas fa-layer-group me-1"></i>Templates</button>
        </div>

        <!-- Active Indicator Chips -->
        <div id="activeInds" class="px-2 py-1" style="background:rgba(2,8,23,.92);border-bottom:1px solid var(--border-c);min-height:28px;display:flex;flex-wrap:wrap;gap:.3rem;align-items:center">
        </div>

        <!-- OHLCV Crosshair Info Bar -->
        <div id="crosshairBar" class="px-3" style="font-size:.75rem;background:rgba(2,8,23,.85);border-bottom:1px solid var(--border-c);padding:.2rem .6rem;color:#94a3b8;min-height:22px">
            Hover over chart for OHLCV values
        </div>

        <!-- Main Chart Canvas -->
        <div id="mainChart"></div>

        <!-- Sub-panel for RSI / MACD / ATR / Stoch -->
        <div id="subPanel" class="indicator-panel" style="display:none"></div>
    </div>

    <!-- Right Panel: Ticker Stats + Volume Profile -->
    <div class="chart-right">
        <div class="section-hdr">Ticker</div>
        <div id="tickerStats">
            <?php if ($pair): ?>
            <?php
            $prec = (int)($pair['price_precision'] ?? 4);
            $stats = [
                ['24H High',   number_format((float)$pair['high_24h'],    $prec), 'text-success'],
                ['24H Low',    number_format((float)$pair['low_24h'],     $prec), 'text-danger'],
                ['Best Bid',   number_format((float)$pair['best_bid'],    $prec), 'text-secondary'],
                ['Best Ask',   number_format((float)$pair['best_ask'],    $prec), 'text-secondary'],
                ['Volume 24H', number_format((float)$pair['volume_24h'],  2),     'text-info'],
                ['Market',     ucfirst((string)$pair['market_type']),             'text-warning'],
                ['Maker Fee',  number_format((float)$pair['maker_fee_percent'], 3) . '%', 'text-secondary'],
                ['Taker Fee',  number_format((float)$pair['taker_fee_percent'], 3) . '%', 'text-secondary'],
            ];
            ?>
            <?php foreach ($stats as [$lbl, $val, $cls]): ?>
            <div class="stat-row"><span class="stat-lbl"><?= htmlspecialchars($lbl, ENT_QUOTES) ?></span><span class="stat-val <?= htmlspecialchars($cls, ENT_QUOTES) ?>"><?= htmlspecialchars((string)$val, ENT_QUOTES) ?></span></div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section-hdr mt-2">Volume Profile <span class="text-secondary" style="font-size:.6rem">(24H)</span></div>
        <div id="volumeProfile" style="padding:.3rem 0">
            <div class="text-secondary px-2" style="font-size:.72rem">Loading…</div>
        </div>

        <div class="section-hdr mt-2">Links</div>
        <div class="px-2 pb-2">
            <a href="/trade?pair=<?= urlencode($symbol) ?>" class="btn btn-xs btn-primary w-100 mb-1"><i class="fas fa-bolt me-1"></i>Trade <?= htmlspecialchars($symbol, ENT_QUOTES) ?></a>
            <a href="/markets/detail?symbol=<?= urlencode($symbol) ?>" class="btn btn-xs btn-outline-secondary w-100 mb-1">Market Info</a>
            <a href="/charts/compare?pairs=<?= urlencode($symbol . ',ETHUSDT') ?>" class="btn btn-xs btn-outline-secondary w-100">Compare</a>
        </div>
    </div>
</div>

<!-- Templates Modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Chart Templates</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3 border-secondary" id="tplTabs">
                    <li class="nav-item"><button class="nav-link active text-light" data-bs-toggle="tab" data-bs-target="#tplList">Saved</button></li>
                    <li class="nav-item"><button class="nav-link text-light" data-bs-toggle="tab" data-bs-target="#tplSave">Save Current</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tplList">
                        <div id="tplListContent"><div class="text-secondary small">Loading…</div></div>
                    </div>
                    <div class="tab-pane fade" id="tplSave">
                        <div class="mb-3">
                            <label class="form-label small">Template Name</label>
                            <input type="text" id="tplName" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="My Template">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Description (optional)</label>
                            <input type="text" id="tplDesc" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Optional description">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="tplPublic">
                            <label class="form-check-label small" for="tplPublic">Make public (visible to all users)</label>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="saveTemplate()">Save Template</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts CDN already loaded in user layout -->
<script>
// ============================================================
// State
// ============================================================
let currentSymbol   = <?= json_encode($symbol) ?>;
let currentInterval = <?= json_encode($interval) ?>;
let currentType     = <?= json_encode($savedChartType) ?>;
let showVolume      = <?= $showVolume ? 'true' : 'false' ?>;
let activeIndicators = <?= json_encode(array_column($savedIndicators ?? [], 'key', 'key') ?: []) ?>;
const csrfToken     = <?= json_encode($csrfToken) ?>;

// Indicator metadata
const indicatorMeta = {
    sma_20:    { label: 'SMA 20',      color: '#f59e0b', sub: false },
    sma_50:    { label: 'SMA 50',      color: '#8b5cf6', sub: false },
    ema_20:    { label: 'EMA 20',      color: '#06b6d4', sub: false },
    ema_50:    { label: 'EMA 50',      color: '#10b981', sub: false },
    ema_200:   { label: 'EMA 200',     color: '#f43f5e', sub: false },
    bb_20:     { label: 'BB(20,2)',    color: '#a78bfa', sub: false },
    vwap:      { label: 'VWAP',        color: '#fbbf24', sub: false },
    rsi_14:    { label: 'RSI 14',      color: '#f97316', sub: true  },
    macd:      { label: 'MACD',        color: '#38bdf8', sub: true  },
    atr_14:    { label: 'ATR 14',      color: '#a3e635', sub: true  },
    stoch_14_3:{ label: 'Stoch(14,3)', color: '#fb923c', sub: true  },
};

// Charts instances
let mainChart   = null;
let subChart    = null;
let chartInited = false;

// ============================================================
// Initialise on load
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Load initial candle data
    fetchAndRender();
    // Populate active indicator chips from saved prefs
    Object.keys(activeIndicators).forEach(k => renderChip(k));
    // Load volume profile
    fetchVolumeProfile();
    // Pair search
    document.getElementById('pairSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#pairList .pair-item').forEach(el => {
            el.style.display = el.dataset.symbol.toLowerCase().includes(q) ? '' : 'none';
        });
    });
    // Pair click
    document.querySelectorAll('#pairList .pair-item').forEach(el => {
        el.addEventListener('click', () => selectPair(el.dataset.symbol));
    });
    // Load templates on modal open
    document.getElementById('tplModal').addEventListener('show.bs.modal', loadTemplates);
});

// ============================================================
// Pair selection
// ============================================================
function selectPair(sym) {
    currentSymbol = sym;
    document.getElementById('tbSymbol').textContent = sym;
    document.querySelectorAll('#pairList .pair-item').forEach(el =>
        el.classList.toggle('active', el.dataset.symbol === sym));
    fetchAndRender();
    fetchVolumeProfile();
    history.replaceState(null, '', '/charts?pair=' + encodeURIComponent(sym) + '&interval=' + currentInterval);
}

// ============================================================
// Interval
// ============================================================
function setInterval(iv) {
    currentInterval = iv;
    document.querySelectorAll('.tb-btn').forEach(btn => {
        if (['1m','5m','15m','30m','1h','4h','1d','1w'].includes(btn.textContent.trim()))
            btn.classList.toggle('active', btn.textContent.trim() === iv);
    });
    fetchAndRender();
    history.replaceState(null, '', '/charts?pair=' + encodeURIComponent(currentSymbol) + '&interval=' + iv);
}

// ============================================================
// Chart type
// ============================================================
function setChartType(type) {
    currentType = type;
    fetchAndRender();
}

// ============================================================
// Volume toggle
// ============================================================
function toggleVolume() {
    showVolume = !showVolume;
    document.getElementById('volToggleBtn').classList.toggle('active', showVolume);
    fetchAndRender();
}

// ============================================================
// Indicators
// ============================================================
function addIndicator(key) {
    if (activeIndicators[key]) return;
    activeIndicators[key] = true;
    renderChip(key);
    fetchAndRender();
}

function removeIndicator(key) {
    delete activeIndicators[key];
    document.getElementById('chip_' + key)?.remove();
    fetchAndRender();
}

function renderChip(key) {
    if (document.getElementById('chip_' + key)) return;
    const meta  = indicatorMeta[key] || { label: key, color: '#94a3b8' };
    const wrap  = document.getElementById('activeInds');
    const chip  = document.createElement('span');
    chip.id     = 'chip_' + key;
    chip.className = 'ind-chip';
    chip.style.borderColor = meta.color + '66';
    chip.innerHTML = `<span style="color:${meta.color}">${meta.label}</span><span class="rm" onclick="removeIndicator('${key}')">✕</span>`;
    wrap.appendChild(chip);
}

// ============================================================
// Fetch & Render
// ============================================================
async function fetchAndRender() {
    const keys = Object.keys(activeIndicators).join(',');
    const url  = `/charts/data?pair=${encodeURIComponent(currentSymbol)}&interval=${currentInterval}&limit=400` +
                 (keys ? '&indicators=' + encodeURIComponent(keys) : '');
    try {
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.ok || !json.candles?.length) return;
        updateTicker(json.pair);
        renderChart(json.candles, json.indicators || {});
    } catch(e) {
        console.error('Chart fetch error', e);
    }
}

function updateTicker(pair) {
    if (!pair) return;
    const price = parseFloat(pair.last_price || 0);
    const chg   = parseFloat(pair.change_24h_percent || 0);
    document.getElementById('tbPrice').textContent = price.toFixed(pair.price_precision || 4);
    document.getElementById('tbPrice').className   = 'fw-bold ' + (chg >= 0 ? 'text-success' : 'text-danger');
    const badge = document.getElementById('tbChg');
    badge.textContent = (chg >= 0 ? '+' : '') + chg.toFixed(2) + '%';
    badge.className   = 'badge ' + (chg >= 0 ? 'text-bg-success' : 'text-bg-danger');
}

function renderChart(candles, indicators) {
    // Separate overlay vs sub-panel indicators
    const overlayKeys = Object.keys(indicators).filter(k => !(indicatorMeta[k]?.sub));
    const subKeys     = Object.keys(indicators).filter(k =>  indicatorMeta[k]?.sub);

    // Build OHLCV series
    const ohSeries = buildOHLCSeries(candles);
    const allSeries = [ohSeries];

    if (showVolume) {
        allSeries.push({
            name: 'Volume', type: 'bar',
            data: candles.map(c => ({ x: c.time, y: parseFloat(c.volume || 0) })),
        });
    }

    // Overlay indicators
    overlayKeys.forEach(key => {
        const meta = indicatorMeta[key] || { label: key, color: '#94a3b8' };
        const data = indicators[key];
        if (!data) return;
        // BB has middle/upper/lower
        if (key.startsWith('bb')) {
            ['upper','middle','lower'].forEach((band, i) => {
                allSeries.push({
                    name: meta.label + ' ' + band,
                    type: 'line',
                    data: data.map(d => ({ x: d.time, y: d[band] })),
                    color: i === 0 ? '#a78bfa' : (i === 1 ? '#6d28d9' : '#a78bfa'),
                });
            });
        } else {
            allSeries.push({
                name: meta.label, type: 'line',
                data: data.map(d => ({ x: d.time, y: d.value })),
                color: meta.color,
            });
        }
    });

    const yAxes = buildYAxes(allSeries, overlayKeys);

    const opts = {
        series: allSeries,
        chart: {
            type: 'candlestick',
            height: '100%',
            background: 'transparent',
            foreColor: '#94a3b8',
            animations: { enabled: false },
            toolbar: {
                show: true,
                tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true },
            },
            events: {
                mouseMove(_e, _ctx, config) {
                    const i = config.dataPointIndex;
                    if (i >= 0 && candles[i]) {
                        const c = candles[i];
                        document.getElementById('crosshairBar').textContent =
                            `O: ${parseFloat(c.open).toFixed(6)}  H: ${parseFloat(c.high).toFixed(6)}  L: ${parseFloat(c.low).toFixed(6)}  C: ${parseFloat(c.close).toFixed(6)}  V: ${parseFloat(c.volume).toFixed(4)}`;
                    }
                },
            },
        },
        plotOptions: {
            candlestick: {
                colors: { upward: '#22c55e', downward: '#ef4444' },
                wick: { useFillColor: true },
            },
            bar: { columnWidth: '80%' },
        },
        xaxis: {
            type: 'datetime',
            labels: { style: { colors: '#64748b' }, datetimeUTC: false },
            axisBorder: { color: '#1e293b' },
            axisTicks: { color: '#1e293b' },
        },
        yaxis: yAxes,
        grid: { borderColor: '#1e293b', strokeDashArray: 4 },
        tooltip: {
            theme: 'dark',
            shared: true,
            x: { format: 'dd MMM yyyy HH:mm' },
        },
        legend: { show: false },
    };

    if (mainChart) {
        mainChart.updateOptions(opts, true, false);
    } else {
        mainChart = new ApexCharts(document.getElementById('mainChart'), opts);
        mainChart.render();
    }

    // Sub-panel indicators (RSI, MACD, ATR, Stoch)
    renderSubPanel(subKeys, indicators, candles);
}

function buildOHLCSeries(candles) {
    let data;
    if (currentType === 'heikin_ashi') {
        data = toHeikinAshi(candles);
    } else {
        data = candles.map(c => ({
            x: c.time,
            y: [parseFloat(c.open), parseFloat(c.high), parseFloat(c.low), parseFloat(c.close)],
        }));
    }
    const typeMap = { candlestick:'candlestick', line:'line', bar:'bar', area:'area', heikin_ashi:'candlestick' };
    return { name: currentSymbol, type: typeMap[currentType] || 'candlestick', data };
}

function toHeikinAshi(candles) {
    const ha = [];
    for (let i = 0; i < candles.length; i++) {
        const c    = candles[i];
        const haC  = (parseFloat(c.open) + parseFloat(c.high) + parseFloat(c.low) + parseFloat(c.close)) / 4;
        const haO  = i === 0 ? (parseFloat(c.open) + parseFloat(c.close)) / 2
                             : (ha[i-1].y[0] + ha[i-1].y[3]) / 2;
        const haH  = Math.max(parseFloat(c.high), haO, haC);
        const haL  = Math.min(parseFloat(c.low),  haO, haC);
        ha.push({ x: c.time, y: [haO, haH, haL, haC] });
    }
    return ha;
}

function buildYAxes(series, overlayKeys) {
    const axes = [];
    const seriesWithOverlay = series.filter(s => s.name !== 'Volume' && !overlayKeys.some(k =>
        (indicatorMeta[k]?.label ?? k) === s.name));
    // Main price axis
    axes.push({
        seriesName: series[0]?.name,
        labels: { style: { colors: '#94a3b8' }, formatter: v => v?.toFixed?.(4) ?? v },
        tooltip: { enabled: true },
    });
    // Volume axis (opposite)
    if (showVolume) {
        axes.push({
            seriesName: 'Volume', opposite: true, show: false,
            labels: { style: { colors: '#64748b' }, formatter: v => v?.toFixed?.(2) ?? v },
        });
    }
    // Overlay indicators share the main axis
    overlayKeys.forEach(k => {
        const meta = indicatorMeta[k] || { label: k };
        if (k.startsWith('bb')) {
            ['upper','middle','lower'].forEach(band => {
                axes.push({ seriesName: meta.label + ' ' + band, show: false });
            });
        } else {
            axes.push({ seriesName: meta.label, show: false });
        }
    });
    return axes;
}

// Sub-panel for oscillators
function renderSubPanel(subKeys, indicators, candles) {
    const panel = document.getElementById('subPanel');
    if (!subKeys.length) { panel.style.display = 'none'; if (subChart) { subChart.destroy(); subChart = null; } return; }
    panel.style.display = '';

    const key  = subKeys[0]; // Show first sub indicator
    const data = indicators[key];
    const meta = indicatorMeta[key] || { label: key, color: '#38bdf8' };
    let series = [];

    if (key === 'macd') {
        series = [
            { name: 'MACD',      type: 'line', data: data.map((d, i) => ({ x: candles[i]?.time, y: d.macd })),      color: '#38bdf8' },
            { name: 'Signal',    type: 'line', data: data.map((d, i) => ({ x: candles[i]?.time, y: d.signal })),    color: '#f59e0b' },
            { name: 'Histogram', type: 'bar',  data: data.map((d, i) => ({ x: candles[i]?.time, y: d.histogram })), color: '#6366f1' },
        ];
    } else if (key.startsWith('stoch')) {
        series = [
            { name: '%K', type: 'line', data: data.map((d, i) => ({ x: candles[i]?.time, y: d.k })), color: '#fb923c' },
            { name: '%D', type: 'line', data: data.map((d, i) => ({ x: candles[i]?.time, y: d.d })), color: '#a78bfa' },
        ];
    } else {
        series = [{ name: meta.label, type: 'line', data: data.map((d, i) => ({ x: candles[i]?.time, y: d.value })), color: meta.color }];
    }

    const opts = {
        series,
        chart: { type: 'line', height: 120, background: 'transparent', foreColor: '#94a3b8', animations: { enabled: false }, toolbar: { show: false }, sparkline: { enabled: false } },
        stroke: { width: [2,2,0] },
        xaxis: { type: 'datetime', labels: { show: false } },
        yaxis: { labels: { style: { colors: '#64748b' }, formatter: v => v?.toFixed?.(2) ?? v } },
        grid: { borderColor: '#1e293b', strokeDashArray: 3 },
        tooltip: { theme: 'dark', x: { format: 'dd MMM HH:mm' } },
        legend: { show: true, position: 'top', labels: { colors: '#94a3b8' } },
        annotations: key === 'rsi_14' ? {
            yaxis: [
                { y: 70, borderColor: '#ef4444', label: { text: 'OB', style: { color: '#ef4444', fontSize: '10px' } } },
                { y: 30, borderColor: '#22c55e', label: { text: 'OS', style: { color: '#22c55e', fontSize: '10px' } } },
            ]
        } : {},
        title: { text: meta.label, align: 'left', style: { color: '#64748b', fontSize: '11px' } },
    };

    if (subChart) {
        subChart.updateOptions(opts, true, false);
    } else {
        panel.innerHTML = '<div id="subChartEl" style="height:120px"></div>';
        subChart = new ApexCharts(document.getElementById('subChartEl'), opts);
        subChart.render();
    }
}

// ============================================================
// Volume Profile
// ============================================================
async function fetchVolumeProfile() {
    try {
        const res  = await fetch(`/charts/volume-profile?pair=${encodeURIComponent(currentSymbol)}&hours=24&buckets=25`);
        const json = await res.json();
        const el   = document.getElementById('volumeProfile');
        if (!json.ok || !json.profile?.length) { el.innerHTML = '<div class="text-secondary px-2" style="font-size:.72rem">No data</div>'; return; }
        const maxV = Math.max(...json.profile.map(p => p.volume));
        el.innerHTML = json.profile.slice().reverse().map(p => {
            const pct  = maxV > 0 ? (p.volume / maxV * 100).toFixed(0) : 0;
            const priceF = parseFloat(p.price_level).toFixed(4);
            return `<div class="vp-bar-wrap">
                <span style="width:55px;color:#64748b;text-align:right;font-size:.65rem">${priceF}</span>
                <div class="vp-bar" style="width:${pct}%"></div>
                <span style="color:#64748b;font-size:.65rem">${pct}%</span>
            </div>`;
        }).join('');
    } catch(e) {
        console.error('Volume profile error', e);
    }
}

// ============================================================
// Preferences
// ============================================================
async function savePreferences() {
    try {
        const body = new URLSearchParams({
            _token:       csrfToken,
            pair:         currentSymbol,
            interval_code:currentInterval,
            chart_type:   currentType,
            indicators:   JSON.stringify(Object.keys(activeIndicators).map(k => ({ key: k }))),
            layout:       JSON.stringify({ show_volume: showVolume }),
        });
        const res  = await fetch('/charts/preferences', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) showToast('Preferences saved', 'success');
    } catch(e) { console.error(e); }
}

// ============================================================
// Templates
// ============================================================
async function loadTemplates() {
    try {
        const res   = await fetch('/charts/templates');
        const json  = await res.json();
        const el    = document.getElementById('tplListContent');
        if (!json.ok || !json.templates?.length) { el.innerHTML = '<div class="text-secondary small">No templates saved</div>'; return; }
        el.innerHTML = json.templates.map(t => `
            <div class="d-flex align-items-center justify-content-between py-1 border-bottom border-secondary">
                <div>
                    <div class="fw-semibold small">${escHtml(t.name)}</div>
                    <div class="text-secondary" style="font-size:.7rem">${escHtml(t.description || '')} · ${escHtml(t.chart_type)}</div>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-info" onclick="applyTemplate(${t.id})">Apply</button>
                    ${!t.is_public ? `<button class="btn btn-xs btn-outline-danger" onclick="deleteTpl(${t.id})">Del</button>` : ''}
                </div>
            </div>`).join('');
    } catch(e) { console.error(e); }
}

async function saveTemplate() {
    const body = new URLSearchParams({
        _token:     csrfToken,
        name:       document.getElementById('tplName').value || 'My Template',
        description:document.getElementById('tplDesc').value || '',
        chart_type: currentType,
        indicators: JSON.stringify(Object.keys(activeIndicators).map(k => ({ key: k }))),
        layout:     JSON.stringify({ show_volume: showVolume }),
        is_public:  document.getElementById('tplPublic').checked ? '1' : '0',
    });
    try {
        const res  = await fetch('/charts/templates', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) { showToast('Template saved', 'success'); loadTemplates(); }
    } catch(e) { console.error(e); }
}

async function applyTemplate(id) {
    try {
        const res  = await fetch('/charts/templates');
        const json = await res.json();
        const tpl  = json.templates?.find(t => t.id == id);
        if (!tpl) return;
        // Apply indicators
        activeIndicators = {};
        document.getElementById('activeInds').innerHTML = '';
        (tpl.indicators || []).forEach(item => addIndicator(item.key));
        // Apply chart type
        document.getElementById('chartTypeSelect').value = tpl.chart_type;
        setChartType(tpl.chart_type);
        bootstrap.Modal.getInstance(document.getElementById('tplModal'))?.hide();
        showToast('Template applied', 'success');
    } catch(e) { console.error(e); }
}

async function deleteTpl(id) {
    if (!confirm('Delete this template?')) return;
    const body = new URLSearchParams({ _token: csrfToken, id });
    try {
        const res  = await fetch('/charts/templates/delete', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) { showToast('Template deleted', 'success'); loadTemplates(); }
    } catch(e) { console.error(e); }
}

// ============================================================
// Helpers
// ============================================================
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
    el.style.zIndex = '9999';
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${escHtml(msg)}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    document.body.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 3000 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
</script>
