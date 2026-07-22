<?php declare(strict_types=1);
$signals   = (array)($signals ?? []);
$pairs     = (array)($pairs ?? []);
$providers = (array)($providers ?? []);
$filters   = (array)($filters ?? []);
$page      = (int)($page ?? 1);
$csrfToken = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Create Signal + Filters -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-chart-bar me-2 text-info"></i>All Signals</h5>
    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#createSignalModal">
        <i class="fas fa-plus me-1"></i>New Signal
    </button>
</div>

<!-- Filters -->
<form method="get" action="/admin/signals/list" class="glass rounded-3 p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <select name="provider_id" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Providers</option>
                <?php foreach ($providers as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= ($filters['provider_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="signal_type" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Types</option>
                <?php foreach (['buy','sell','hold','close_long','close_short','watch'] as $t): ?>
                <option value="<?= $t ?>" <?= ($filters['signal_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Status</option>
                <?php foreach (['active','hit_tp','hit_sl','cancelled','expired'] as $s): ?>
                <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="pair_symbol" class="form-control form-control-sm bg-dark text-light border-secondary"
                   placeholder="Pair symbol…" value="<?= e($filters['pair_symbol'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-info btn-sm w-100">Filter</button>
        </div>
    </div>
</form>

<!-- Signals Table -->
<div class="glass rounded-3">
    <div class="table-responsive">
        <table class="table table-user mb-0" id="signalsTable">
            <thead>
                <tr>
                    <th>ID</th><th>Pair</th><th>Type</th><th>Market</th><th>Provider</th>
                    <th>Entry</th><th>TP1</th><th>SL</th><th>Conf</th><th>Status</th><th>Result</th><th>Published</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($signals)): ?>
            <tr><td colspan="13" class="text-center text-secondary py-3">No signals found.</td></tr>
            <?php else: ?>
            <?php foreach ($signals as $sig):
                $tc  = match($sig['signal_type']) { 'buy','close_short' => 'success', 'sell','close_long' => 'danger', 'hold' => 'warning', default => 'secondary' };
                $sc  = match($sig['status']) { 'hit_tp' => 'success', 'hit_sl' => 'danger', 'active' => 'info', default => 'secondary' };
            ?>
            <tr>
                <td class="text-secondary small"><?= (int)$sig['id'] ?></td>
                <td class="fw-semibold"><?= e($sig['pair_symbol'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $tc ?>"><?= e($sig['signal_type']) ?></span></td>
                <td class="small"><?= e($sig['market_type']) ?></td>
                <td class="small"><?= e($sig['provider_name']) ?></td>
                <td class="font-monospace small"><?= !empty($sig['entry_price']) ? number_format((float)$sig['entry_price'], 6) : '—' ?></td>
                <td class="font-monospace small text-success"><?= !empty($sig['take_profit_1']) ? number_format((float)$sig['take_profit_1'], 6) : '—' ?></td>
                <td class="font-monospace small text-danger"><?= !empty($sig['stop_loss']) ? number_format((float)$sig['stop_loss'], 6) : '—' ?></td>
                <td><?= !empty($sig['confidence_score']) ? (int)$sig['confidence_score'].'%' : '—' ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= e($sig['status']) ?></span></td>
                <td>
                    <?php if ($sig['profit_pct'] !== null): ?>
                    <span class="<?= $sig['profit_pct'] >= 0 ? 'text-profit' : 'text-loss' ?> small fw-semibold">
                        <?= $sig['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$sig['profit_pct'], 2) ?>%
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small text-secondary"><?= e(date('M d H:i', strtotime($sig['published_at']))) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-xs btn-outline-success" title="Hit TP"
                                onclick="updateStatus(<?= (int)$sig['id'] ?>, 'hit_tp')">TP</button>
                        <button class="btn btn-xs btn-outline-danger" title="Hit SL"
                                onclick="updateStatus(<?= (int)$sig['id'] ?>, 'hit_sl')">SL</button>
                        <button class="btn btn-xs btn-outline-secondary" title="Cancel"
                                onclick="updateStatus(<?= (int)$sig['id'] ?>, 'cancelled')"><i class="fas fa-ban"></i></button>
                        <button class="btn btn-xs btn-outline-danger" title="Delete"
                                onclick="deleteSignal(<?= (int)$sig['id'] ?>)"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Signal Modal -->
<div class="modal fade" id="createSignalModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-chart-bar me-2 text-info"></i>Create Trading Signal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createSignalForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Provider <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="provider_id" required>
                                <option value="">Select provider…</option>
                                <?php foreach ($providers as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Pair Symbol <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" name="pair_symbol" placeholder="BTCUSDT" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Trading Pair</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="trading_pair_id">
                                <option value="">Not linked</option>
                                <?php foreach ($pairs as $pair): ?>
                                <option value="<?= (int)$pair['id'] ?>"><?= e($pair['symbol']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-secondary">Signal Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="signal_type" required>
                                <?php foreach (['buy','sell','hold','close_long','close_short','watch'] as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-secondary">Market Type</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="market_type">
                                <option value="spot">Spot</option>
                                <option value="futures">Futures</option>
                                <option value="margin">Margin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-secondary">Timeframe</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="timeframe">
                                <?php foreach (['1m','5m','15m','30m','1h','4h','1d','1w'] as $tf): ?>
                                <option value="<?= $tf ?>" <?= $tf==='1h'?'selected':'' ?>><?= $tf ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-secondary">Confidence (0-100)</label>
                            <input type="number" min="0" max="100" class="form-control form-control-sm bg-dark text-light border-secondary" name="confidence_score">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-secondary">Leverage</label>
                            <input type="number" min="1" class="form-control form-control-sm bg-dark text-light border-secondary" name="leverage">
                        </div>
                        <div class="col-md-2"><label class="form-label small text-secondary">Entry Price</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary" name="entry_price"></div>
                        <div class="col-md-2"><label class="form-label small text-secondary">Entry Zone High</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary" name="entry_price_high"></div>
                        <div class="col-md-2"><label class="form-label small text-secondary">Entry Zone Low</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary" name="entry_price_low"></div>
                        <div class="col-md-2"><label class="form-label small text-secondary">Take Profit 1</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary" name="take_profit_1"></div>
                        <div class="col-md-2"><label class="form-label small text-secondary">Take Profit 2</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary" name="take_profit_2"></div>
                        <div class="col-md-2"><label class="form-label small text-secondary">Stop Loss</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary" name="stop_loss"></div>
                        <div class="col-md-2"><label class="form-label small text-secondary">R:R Ratio</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-dark text-light border-secondary" name="risk_reward_ratio"></div>
                        <div class="col-md-4"><label class="form-label small text-secondary">Expires At</label>
                            <input type="datetime-local" class="form-control form-control-sm bg-dark text-light border-secondary" name="expires_at"></div>
                        <div class="col-md-4"><label class="form-label small text-secondary">Tags (comma separated)</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" name="tags" placeholder="breakout, rsi_oversold"></div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Analysis Text</label>
                            <textarea class="form-control form-control-sm bg-dark text-light border-secondary" name="analysis_text" rows="3" placeholder="Analysis and reasoning…"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info btn-sm" onclick="submitCreateSignal()">
                    <i class="fas fa-broadcast-tower me-1"></i>Publish Signal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function updateStatus(signalId, status) {
    const label = { hit_tp: 'Mark as Hit TP?', hit_sl: 'Mark as Hit SL?', cancelled: 'Cancel signal?' }[status] || 'Update?';
    if (!confirm(label)) return;
    fetch('/admin/signals/status', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&signal_id=${signalId}&status=${status}`
    })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert(d.message || 'Error'); });
}

function deleteSignal(signalId) {
    if (!confirm('Delete this signal permanently?')) return;
    fetch('/admin/signals/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&signal_id=${signalId}`
    })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert(d.message || 'Error'); });
}

function submitCreateSignal() {
    const form = document.getElementById('createSignalForm');
    const data = new URLSearchParams(new FormData(form)).toString();
    fetch('/admin/signals/create', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('createSignalModal'))?.hide();
            location.reload();
        } else alert(d.message || 'Error creating signal');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('signalsTable')) {
        new DataTable('#signalsTable', {pageLength: 50, order: [[11, 'desc']]});
    }
});
</script>
