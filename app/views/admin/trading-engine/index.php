<?php declare(strict_types=1); ?>
<?php
$engineStats   = is_array($engineStats   ?? null) ? $engineStats   : [];
$volumeChart   = is_array($volumeChart   ?? null) ? $volumeChart   : [];
$topPairs      = is_array($topPairs      ?? null) ? $topPairs      : [];
$feeTiers      = is_array($feeTiers      ?? null) ? $feeTiers      : [];
$openPositions = is_array($openPositions ?? null) ? $openPositions : [];
$csrfToken     = (string)($csrfToken     ?? \App\Libraries\Csrf::token());

$statsMap = [];
foreach ($engineStats as $k => $v) { $statsMap[$k] = $v; }
?>
<?php include __DIR__ . '/../_nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-exchange-alt me-2 text-info"></i>Trading Engine</h1>
        <p class="text-secondary mb-0">Real-time matching engine, position management, fee tiers and risk control.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/trading-engine/risk" class="btn btn-outline-warning btn-sm"><i class="fas fa-shield-alt me-1"></i>Risk Monitor</a>
        <a href="/admin/trading-engine/fee-tiers" class="btn btn-outline-info btn-sm"><i class="fas fa-percentage me-1"></i>Fee Tiers</a>
        <a href="/admin/trading-engine/positions" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-area me-1"></i>Positions</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label' => 'Open Orders',       'key' => 'open_orders',       'icon' => 'list-ol',       'color' => 'info'],
        ['label' => 'Orders Today',       'key' => 'orders_today',      'icon' => 'calendar-day',  'color' => 'primary'],
        ['label' => 'Trades Today',       'key' => 'trades_today',      'icon' => 'exchange-alt',  'color' => 'success'],
        ['label' => 'Volume Today (USD)', 'key' => 'volume_today_usd',  'icon' => 'dollar-sign',   'color' => 'warning'],
        ['label' => 'Open Positions',     'key' => 'open_positions',    'icon' => 'chart-area',    'color' => 'primary'],
        ['label' => 'Liq. Candidates',   'key' => 'liq_candidates',    'icon' => 'exclamation-triangle', 'color' => 'danger'],
        ['label' => 'Fee Revenue Today',  'key' => 'fee_revenue_today', 'icon' => 'coins',         'color' => 'success'],
        ['label' => 'Insurance Fund',     'key' => 'insurance_fund',    'icon' => 'shield-alt',    'color' => 'info'],
    ];
    foreach ($kpis as $kpi):
        $val = $statsMap[$kpi['key']] ?? '0';
        $display = is_numeric($val) ? number_format((float)$val, 2) : e((string)$val);
    ?>
    <div class="col-xl-3 col-md-6">
        <div class="glass rounded-4 p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-secondary small mb-1"><?= e($kpi['label']) ?></div>
                    <div class="h5 mb-0 fw-bold"><?= $display ?></div>
                </div>
                <div class="rounded-circle p-2" style="background:rgba(var(--bs-<?= e($kpi['color']) ?>-rgb),.15);">
                    <i class="fas fa-<?= e($kpi['icon']) ?> text-<?= e($kpi['color']) ?>"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Volume Chart -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4 h-100">
            <h2 class="h6 mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Daily Trading Volume (30 days)</h2>
            <div id="volumeChart" style="min-height:260px;"></div>
        </div>
    </div>

    <!-- Top Traded Pairs -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h2 class="h6 mb-3"><i class="fas fa-fire me-2 text-warning"></i>Top Pairs (24h)</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Pair</th><th>Volume</th><th>Trades</th></tr></thead>
                    <tbody>
                    <?php foreach ($topPairs as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($p['symbol'] ?? '-')) ?></td>
                            <td><?= number_format((float)($p['volume_24h'] ?? 0), 2) ?></td>
                            <td><?= (int)($p['trades_24h'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($topPairs === []): ?><tr><td colspan="3" class="text-secondary text-center">No trades yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Fee Tiers -->
    <div class="col-xl-6">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-percentage me-2 text-info"></i>Fee Tiers</h2>
                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#addFeeTierModal">
                    <i class="fas fa-plus me-1"></i>Add Tier
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Tier</th><th>Min Vol (30d)</th><th>Maker %</th><th>Taker %</th><th>Active</th><th>Edit</th></tr></thead>
                    <tbody>
                    <?php foreach ($feeTiers as $t): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($t['tier_name'] ?? '-')) ?></td>
                            <td><?= number_format((float)($t['min_30d_volume'] ?? 0), 0) ?></td>
                            <td><?= number_format((float)($t['maker_fee_percent'] ?? 0), 4) ?>%</td>
                            <td><?= number_format((float)($t['taker_fee_percent'] ?? 0), 4) ?>%</td>
                            <td><span class="badge text-bg-<?= (int)($t['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int)($t['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <button class="btn btn-xs btn-outline-warning"
                                    onclick='editFeeTier(<?= json_encode($t) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($feeTiers === []): ?><tr><td colspan="6" class="text-secondary text-center">No fee tiers defined</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Open Positions Summary -->
    <div class="col-xl-6">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-chart-area me-2 text-warning"></i>Open Positions</h2>
                <a href="/admin/trading-engine/positions" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive" style="max-height:260px;overflow-y:auto;">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>User</th><th>Pair</th><th>Side</th><th>Leverage</th><th>Unreal. PnL</th><th>Liq. Price</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($openPositions, 0, 10) as $pos):
                        $upnl = (float)($pos['unrealized_pnl'] ?? 0);
                    ?>
                        <tr>
                            <td><?= e((string)($pos['username'] ?? '-')) ?></td>
                            <td><?= e((string)($pos['symbol'] ?? '-')) ?></td>
                            <td class="<?= ($pos['position_side'] ?? '') === 'long' ? 'text-success' : 'text-danger' ?>"><?= e(strtoupper((string)($pos['position_side'] ?? ''))) ?></td>
                            <td><?= number_format((float)($pos['leverage'] ?? 1), 2) ?>x</td>
                            <td class="<?= $upnl >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 4) ?></td>
                            <td><?= $pos['liquidation_price'] ? number_format((float)$pos['liquidation_price'], 4) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($openPositions === []): ?><tr><td colspan="6" class="text-secondary text-center">No open positions</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Fee Tier Modal -->
<div class="modal fade" id="addFeeTierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add Fee Tier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addFeeTierForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="mb-3"><label class="form-label">Tier Name</label><input type="text" class="form-control bg-dark text-light" name="tier_name" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><label class="form-label">Maker Fee %</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" name="maker_fee_percent" value="0.1000" required></div>
                        <div class="col"><label class="form-label">Taker Fee %</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" name="taker_fee_percent" value="0.1500" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Min 30d Volume (USD)</label><input type="number" step="1" min="0" class="form-control bg-dark text-light" name="min_30d_volume" value="0"></div>
                    <div class="mb-3"><label class="form-label">Withdrawal Fee Discount %</label><input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light" name="withdrawal_fee_discount_percent" value="0"></div>
                    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" id="addActiveCheck" value="1" checked><label class="form-check-label" for="addActiveCheck">Active</label></div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="submitAddFeeTier()">Create Tier</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Fee Tier Modal -->
<div class="modal fade" id="editFeeTierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Fee Tier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editFeeTierForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="tier_id" id="editTierId">
                    <div class="mb-3"><label class="form-label">Tier Name</label><input type="text" class="form-control bg-dark text-light" name="tier_name" id="editTierName" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><label class="form-label">Maker Fee %</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" name="maker_fee_percent" id="editMakerFee" required></div>
                        <div class="col"><label class="form-label">Taker Fee %</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" name="taker_fee_percent" id="editTakerFee" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Min 30d Volume (USD)</label><input type="number" step="1" min="0" class="form-control bg-dark text-light" name="min_30d_volume" id="editMinVol"></div>
                    <div class="mb-3"><label class="form-label">Withdrawal Fee Discount %</label><input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light" name="withdrawal_fee_discount_percent" id="editWdDisc"></div>
                    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" id="editActiveCheck" value="1"><label class="form-check-label" for="editActiveCheck">Active</label></div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitEditFeeTier()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"></script>
<script>
const CSRF = <?= json_encode($csrfToken) ?>;

// Volume Chart
(function() {
    const rawData = <?= json_encode($volumeChart) ?>;
    const cats = rawData.map(r => r.day);
    const vols = rawData.map(r => parseFloat(r.volume || 0));
    const trds = rawData.map(r => parseInt(r.trade_count || 0));

    new ApexCharts(document.getElementById('volumeChart'), {
        series: [
            { name: 'Volume (USD)', type: 'area', data: vols },
            { name: 'Trades',       type: 'line', data: trds }
        ],
        chart: { height: 260, background: 'transparent', toolbar: { show: false } },
        colors: ['#38bdf8', '#f59e0b'],
        xaxis: { categories: cats, labels: { style: { colors: '#94a3b8' }, rotate: -30 } },
        yaxis: [
            { labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0) } },
            { opposite: true, labels: { style: { colors: '#f59e0b' } } }
        ],
        stroke: { curve: 'smooth', width: [2, 2] },
        fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        grid: { borderColor: '#1e293b' },
        tooltip: { theme: 'dark' },
        legend: { labels: { colors: '#e2e8f0' } },
    }).render();
})();

// Fee Tier modals
function editFeeTier(tier) {
    document.getElementById('editTierId').value     = tier.id;
    document.getElementById('editTierName').value   = tier.tier_name;
    document.getElementById('editMakerFee').value   = tier.maker_fee_percent;
    document.getElementById('editTakerFee').value   = tier.taker_fee_percent;
    document.getElementById('editMinVol').value     = tier.min_30d_volume;
    document.getElementById('editWdDisc').value     = tier.withdrawal_fee_discount_percent;
    document.getElementById('editActiveCheck').checked = parseInt(tier.is_active) === 1;
    new bootstrap.Modal(document.getElementById('editFeeTierModal')).show();
}

async function submitAddFeeTier() {
    const form = document.getElementById('addFeeTierForm');
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_active = form.querySelector('[name=is_active]').checked ? 1 : 0;
    const res = await apiPost('/admin/trading-engine/fee-tiers/create', data);
    if (res.ok) { alert('Fee tier created!'); location.reload(); }
    else alert(res.message || 'Error');
}

async function submitEditFeeTier() {
    const form = document.getElementById('editFeeTierForm');
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_active = form.querySelector('[name=is_active]').checked ? 1 : 0;
    const res = await apiPost('/admin/trading-engine/fee-tiers/update', data);
    if (res.ok) { alert('Fee tier updated!'); location.reload(); }
    else alert(res.message || 'Error');
}

async function apiPost(url, data) {
    data._token = CSRF;
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    });
    return await r.json();
}
</script>
