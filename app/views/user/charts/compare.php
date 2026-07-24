<?php declare(strict_types=1); ?>
<?php
$pairs    = is_array($pairs ?? null) ? $pairs : [];
$selected = is_array($selected ?? null) ? $selected : ['BTCUSDT', 'ETHUSDT'];
$interval = (string)($interval ?? '1d');
$csrfToken = (string)($csrfToken ?? \App\Libraries\Csrf::token());
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<div class="glass rounded-4 p-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="h5 fw-bold mb-0"><i class="fas fa-code-branch me-2 text-info"></i>Market Comparison</h2>
            <div class="text-secondary small">Normalised % performance — base 0 at first candle</div>
        </div>
        <a href="/charts" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-bar me-1"></i>Advanced Charts</a>
    </div>

    <!-- Controls -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <label class="form-label small text-secondary">Pairs to compare (up to 6)</label>
            <div id="pairChips" class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach ($selected as $sym): ?>
                <span class="badge text-bg-info d-flex align-items-center gap-1" data-sym="<?= htmlspecialchars($sym, ENT_QUOTES) ?>" style="font-size:.8rem;padding:.4rem .65rem">
                    <?= htmlspecialchars($sym, ENT_QUOTES) ?>
                    <span style="cursor:pointer;margin-left:.3rem" onclick="removePair('<?= htmlspecialchars($sym, ENT_QUOTES) ?>')">✕</span>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2">
                <select id="addPairSelect" class="form-select form-select-sm bg-dark text-light border-secondary" style="max-width:200px">
                    <option value="">— Add Pair —</option>
                    <?php foreach ($pairs as $p): ?>
                    <option value="<?= htmlspecialchars((string)$p['symbol'], ENT_QUOTES) ?>"><?= htmlspecialchars((string)$p['symbol'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-info" onclick="addSelectedPair()"><i class="fas fa-plus me-1"></i>Add</button>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-secondary">Interval</label>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach (['1h','4h','1d','1w'] as $iv): ?>
                <button class="btn btn-xs <?= $iv === $interval ? 'btn-info' : 'btn-outline-secondary' ?>"
                        id="ivBtn_<?= $iv ?>" onclick="setCompareInterval('<?= $iv ?>')"><?= $iv ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="glass rounded-3 p-3 mb-4" style="min-height:380px">
        <div id="compareChart" style="min-height:350px"></div>
    </div>

    <!-- Stats Table -->
    <div id="statsTable"></div>

</div>

<script>
let comparePairs    = <?= json_encode($selected) ?>;
let compareInterval = <?= json_encode($interval) ?>;

// Colour palette
const COLOURS = ['#38bdf8','#22c55e','#f59e0b','#f43f5e','#a78bfa','#fb923c'];
let compareChart = null;

document.addEventListener('DOMContentLoaded', () => {
    fetchCompare();
    document.getElementById('addPairSelect').addEventListener('change', function() {
        if (this.value) { addPair(this.value); this.value = ''; }
    });
});

function addSelectedPair() {
    const sel = document.getElementById('addPairSelect');
    if (sel.value) { addPair(sel.value); sel.value = ''; }
}

function addPair(sym) {
    if (comparePairs.includes(sym) || comparePairs.length >= 6) return;
    comparePairs.push(sym);
    renderChips();
    fetchCompare();
    updateUrl();
}

function removePair(sym) {
    comparePairs = comparePairs.filter(s => s !== sym);
    renderChips();
    fetchCompare();
    updateUrl();
}

function renderChips() {
    const el = document.getElementById('pairChips');
    el.innerHTML = comparePairs.map((sym, i) =>
        `<span class="badge d-flex align-items-center gap-1" data-sym="${escHtml(sym)}"
              style="background:${COLOURS[i % COLOURS.length]}22;border:1px solid ${COLOURS[i % COLOURS.length]}66;color:${COLOURS[i % COLOURS.length]};font-size:.8rem;padding:.4rem .65rem">
            ${escHtml(sym)}
            <span style="cursor:pointer;margin-left:.3rem" onclick="removePair('${escHtml(sym)}')">✕</span>
        </span>`).join('');
}

function setCompareInterval(iv) {
    compareInterval = iv;
    document.querySelectorAll('[id^=ivBtn_]').forEach(btn => {
        btn.className = btn.id === 'ivBtn_' + iv ? 'btn btn-xs btn-info' : 'btn btn-xs btn-outline-secondary';
    });
    fetchCompare();
    updateUrl();
}

async function fetchCompare() {
    if (comparePairs.length < 1) return;
    const url = `/charts/compare/data?pairs=${encodeURIComponent(comparePairs.join(','))}&interval=${compareInterval}&limit=200`;
    try {
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.ok) return;
        renderCompare(json.data);
    } catch(e) { console.error(e); }
}

function renderCompare(data) {
    const symbols = Object.keys(data);
    if (!symbols.length) return;

    const series = symbols.map((sym, i) => ({
        name: sym,
        type: 'line',
        data: data[sym].map(d => ({ x: d.time, y: d.pct_change })),
        color: COLOURS[i % COLOURS.length],
    }));

    const opts = {
        series,
        chart: {
            type: 'line', height: 350,
            background: 'transparent', foreColor: '#94a3b8',
            animations: { enabled: false },
            toolbar: { tools: { download: true, zoom: true, pan: true, reset: true, zoomin: true, zoomout: true } },
        },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { type: 'datetime', labels: { style: { colors: '#64748b' }, datetimeUTC: false } },
        yaxis: {
            labels: {
                style: { colors: '#94a3b8' },
                formatter: v => (v >= 0 ? '+' : '') + v?.toFixed?.(2) + '%',
            },
        },
        annotations: {
            yaxis: [{ y: 0, borderColor: '#475569', strokeDashArray: 2 }],
        },
        tooltip: {
            theme: 'dark',
            shared: true,
            x: { format: 'dd MMM yyyy HH:mm' },
            y: { formatter: v => (v >= 0 ? '+' : '') + (v?.toFixed?.(2) ?? 0) + '%' },
        },
        legend: { show: true, position: 'top', labels: { colors: '#94a3b8' } },
        grid: { borderColor: '#1e293b', strokeDashArray: 4 },
    };

    if (compareChart) {
        compareChart.updateOptions(opts, true, false);
    } else {
        compareChart = new ApexCharts(document.getElementById('compareChart'), opts);
        compareChart.render();
    }

    // Stats table
    renderStats(symbols, data);
}

function renderStats(symbols, data) {
    const el = document.getElementById('statsTable');
    const rows = symbols.map((sym, i) => {
        const series = data[sym];
        if (!series?.length) return null;
        const first  = series[0];
        const last   = series[series.length - 1];
        const change = last.pct_change;
        const prices = series.map(d => d.pct_change);
        const high   = Math.max(...prices);
        const low    = Math.min(...prices);
        const colour = COLOURS[i % COLOURS.length];
        return `<tr>
            <td><span class="fw-bold" style="color:${colour}">${escHtml(sym)}</span></td>
            <td class="${change >= 0 ? 'text-success' : 'text-danger'}">${change >= 0 ? '+' : ''}${change.toFixed(2)}%</td>
            <td class="text-success">+${high.toFixed(2)}%</td>
            <td class="text-danger">${low.toFixed(2)}%</td>
            <td class="text-secondary">${first.price?.toFixed?.(4) ?? '—'}</td>
            <td class="text-light">${last.price?.toFixed?.(4) ?? '—'}</td>
        </tr>`;
    }).filter(Boolean).join('');

    el.innerHTML = `
        <div class="glass rounded-3 p-3">
            <h6 class="text-secondary mb-2"><i class="fas fa-table me-1"></i>Comparison Summary</h6>
            <div class="table-responsive">
                <table class="table table-sm table-dark table-hover mb-0">
                    <thead><tr>
                        <th>Pair</th><th>Period Return</th><th>Period High</th><th>Period Low</th><th>Start Price</th><th>Current Price</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>`;
}

function updateUrl() {
    history.replaceState(null, '', '/charts/compare?pairs=' + encodeURIComponent(comparePairs.join(',')) + '&interval=' + compareInterval);
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
