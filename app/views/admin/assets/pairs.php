<?php declare(strict_types=1); ?>
<?php
$pairs      = is_array($pairs ?? null) ? $pairs : [];
$currencies = is_array($currencies ?? null) ? $currencies : [];
$filters    = is_array($filters ?? null) ? $filters : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trading Pairs</h1>
        <p class="text-secondary mb-0">Configure spot, futures and margin trading pairs.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/assets" class="btn btn-outline-secondary btn-sm">← Assets</a>
        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#importPairsModal">
            <i class="fas fa-file-import me-1"></i> Bulk Import
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPairModal">
            <i class="fas fa-plus me-1"></i> New Pair
        </button>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/assets/pairs">
        <div class="col-lg-4"><input class="form-control" type="text" name="search" placeholder="Search symbol or currency" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="market_type">
                <option value="">All Markets</option>
                <?php foreach (['spot','futures','margin'] as $mt): ?>
                    <option value="<?= e($mt) ?>" <?= (($filters['market_type'] ?? '') === $mt) ? 'selected' : '' ?>><?= e(ucfirst($mt)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="is_active">
                <option value="">All Status</option>
                <option value="1" <?= (($filters['is_active'] ?? '') === '1') ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= (($filters['is_active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-lg-4 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="/admin/assets/pairs">Reset</a>
        </div>
    </form>
</div>

<!-- Pairs Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Symbol</th><th>Market</th><th>Maker Fee</th><th>Taker Fee</th>
                    <th>Min Order</th><th>Leverage</th><th>Price Prec.</th>
                    <th>Active</th><th>Trading</th><th>Visible</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pairs as $pair): ?>
                <tr>
                    <td>
                        <strong><?= e((string)($pair['symbol'] ?? '-')) ?></strong><br>
                        <small class="text-secondary"><?= e((string)($pair['base_code'] ?? '')) ?> / <?= e((string)($pair['quote_code'] ?? '')) ?></small>
                    </td>
                    <td><span class="badge text-bg-<?= ['spot'=>'info','futures'=>'warning','margin'=>'danger'][$pair['market_type'] ?? ''] ?? 'secondary' ?>"><?= e(ucfirst((string)($pair['market_type'] ?? '-'))) ?></span></td>
                    <td><?= number_format((float)($pair['maker_fee_percent'] ?? 0), 4) ?>%</td>
                    <td><?= number_format((float)($pair['taker_fee_percent'] ?? 0), 4) ?>%</td>
                    <td><?= number_format((float)($pair['min_order_size'] ?? 0), 8) ?></td>
                    <td><?= (int)($pair['max_leverage'] ?? 1) ?>x</td>
                    <td><?= (int)($pair['price_precision'] ?? 2) ?></td>
                    <td><span class="badge text-bg-<?= (int)($pair['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($pair['is_active'] ?? 0) ? 'Yes' : 'No' ?></span></td>
                    <td><span class="badge text-bg-<?= (int)($pair['trading_enabled'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($pair['trading_enabled'] ?? 0) ? 'Yes' : 'No' ?></span></td>
                    <td><span class="badge text-bg-<?= (int)($pair['is_visible'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($pair['is_visible'] ?? 0) ? 'Yes' : 'No' ?></span></td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1" onclick="openEditPair(<?= e(json_encode($pair)) ?>, <?= e(json_encode($currencies)) ?>)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deletePair(<?= (int)$pair['id'] ?>, '<?= e((string)$pair['symbol']) ?>')">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pairs === []): ?>
                <tr><td colspan="11" class="text-center text-secondary">No trading pairs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Pair Modal -->
<div class="modal fade" id="createPairModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/assets/pair/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">New Trading Pair</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Symbol <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="symbol" placeholder="BTCUSDT" required></div>
                    <div class="col-md-4"><label class="form-label">Base Currency <span class="text-danger">*</span></label>
                        <select class="form-select" name="base_currency_id" required>
                            <option value="">Select...</option>
                            <?php foreach ($currencies as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'] . ' – ' . $c['name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Quote Currency <span class="text-danger">*</span></label>
                        <select class="form-select" name="quote_currency_id" required>
                            <option value="">Select...</option>
                            <?php foreach ($currencies as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'] . ' – ' . $c['name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Market Type</label>
                        <select class="form-select" name="market_type">
                            <option value="spot">Spot</option><option value="futures">Futures</option><option value="margin">Margin</option>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Maker Fee %</label>
                        <input class="form-control" type="text" name="maker_fee_percent" value="0.1"></div>
                    <div class="col-md-3"><label class="form-label">Taker Fee %</label>
                        <input class="form-control" type="text" name="taker_fee_percent" value="0.1"></div>
                    <div class="col-md-3"><label class="form-label">Max Leverage</label>
                        <input class="form-control" type="number" name="max_leverage" value="1" min="1"></div>
                    <div class="col-md-3"><label class="form-label">Min Order Size</label>
                        <input class="form-control" type="text" name="min_order_size" value="0.001"></div>
                    <div class="col-md-3"><label class="form-label">Max Order Size</label>
                        <input class="form-control" type="text" name="max_order_size" placeholder="unlimited"></div>
                    <div class="col-md-3"><label class="form-label">Min Notional</label>
                        <input class="form-control" type="text" name="min_notional" value="1"></div>
                    <div class="col-md-3"><label class="form-label">Price Precision</label>
                        <input class="form-control" type="number" name="price_precision" value="2" min="0"></div>
                    <div class="col-md-3"><label class="form-label">Quantity Precision</label>
                        <input class="form-control" type="number" name="quantity_precision" value="6" min="0"></div>
                    <div class="col-md-3"><label class="form-label">Display Order</label>
                        <input class="form-control" type="number" name="display_order" value="0" min="0"></div>
                    <div class="col-12 d-flex gap-4">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="pairIsActive" checked><label class="form-check-label" for="pairIsActive">Active</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="trading_enabled" value="1" id="pairTradingEnabled" checked><label class="form-check-label" for="pairTradingEnabled">Trading Enabled</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_visible" value="1" id="pairIsVisible" checked><label class="form-check-label" for="pairIsVisible">Visible</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Pair</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Pair Modal -->
<div class="modal fade" id="editPairModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/assets/pair/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="pair_id" id="editPairId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Trading Pair – <span id="editPairSymbolLabel"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Symbol</label>
                        <input class="form-control" type="text" name="symbol" id="editPairSymbol" required></div>
                    <div class="col-md-4"><label class="form-label">Base Currency</label>
                        <select class="form-select" name="base_currency_id" id="editPairBase"></select></div>
                    <div class="col-md-4"><label class="form-label">Quote Currency</label>
                        <select class="form-select" name="quote_currency_id" id="editPairQuote"></select></div>
                    <div class="col-md-3"><label class="form-label">Market Type</label>
                        <select class="form-select" name="market_type" id="editPairMarket">
                            <option value="spot">Spot</option><option value="futures">Futures</option><option value="margin">Margin</option>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Maker Fee %</label>
                        <input class="form-control" type="text" name="maker_fee_percent" id="editPairMakerFee"></div>
                    <div class="col-md-3"><label class="form-label">Taker Fee %</label>
                        <input class="form-control" type="text" name="taker_fee_percent" id="editPairTakerFee"></div>
                    <div class="col-md-3"><label class="form-label">Max Leverage</label>
                        <input class="form-control" type="number" name="max_leverage" id="editPairLeverage" min="1"></div>
                    <div class="col-md-3"><label class="form-label">Min Order Size</label>
                        <input class="form-control" type="text" name="min_order_size" id="editPairMinOrder"></div>
                    <div class="col-md-3"><label class="form-label">Max Order Size</label>
                        <input class="form-control" type="text" name="max_order_size" id="editPairMaxOrder"></div>
                    <div class="col-md-3"><label class="form-label">Min Notional</label>
                        <input class="form-control" type="text" name="min_notional" id="editPairMinNotional"></div>
                    <div class="col-md-3"><label class="form-label">Price Precision</label>
                        <input class="form-control" type="number" name="price_precision" id="editPairPricePrec" min="0"></div>
                    <div class="col-md-3"><label class="form-label">Qty Precision</label>
                        <input class="form-control" type="number" name="quantity_precision" id="editPairQtyPrec" min="0"></div>
                    <div class="col-md-3"><label class="form-label">Display Order</label>
                        <input class="form-control" type="number" name="display_order" id="editPairDisplayOrder" min="0"></div>
                    <div class="col-12 d-flex gap-4">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="editPairIsActive"><label class="form-check-label" for="editPairIsActive">Active</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="trading_enabled" value="1" id="editPairTradingEnabled"><label class="form-check-label" for="editPairTradingEnabled">Trading Enabled</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_visible" value="1" id="editPairIsVisible"><label class="form-check-label" for="editPairIsVisible">Visible</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<form id="deletePairForm" data-ajax="true" action="/admin/assets/pair/delete" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="pair_id" id="deletePairId">
</form>

<!-- Bulk Import Pairs Modal -->
<div class="modal fade" id="importPairsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-file-import me-2"></i>Bulk Import Trading Pairs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <strong>Format (one pair per line):</strong>
                    <code>SYMBOL BASE QUOTE [market_type]</code> &mdash;
                    e.g. <code>BTCUSDT BTC USDT spot</code><br>
                    Separate fields with spaces or commas.
                    Pairs whose symbols already exist or whose currencies are not in the system are skipped.
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Default Market Type</label>
                        <select class="form-select form-select-sm bg-dark text-light border-secondary" id="importMarketType">
                            <option value="spot">Spot</option>
                            <option value="futures">Futures</option>
                            <option value="margin">Margin</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Maker Fee %</label>
                        <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="importMakerFee" value="0.1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Taker Fee %</label>
                        <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="importTakerFee" value="0.1">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Quick-fill popular pairs</label>
                    <div class="d-flex flex-wrap gap-1">
                        <?php
                        $popularPairs = [
                            'BTCUSDT BTC USDT spot',
                            'ETHUSDT ETH USDT spot',
                            'BNBUSDT BNB USDT spot',
                            'SOLUSDT SOL USDT spot',
                            'XRPUSDT XRP USDT spot',
                            'ADAUSDT ADA USDT spot',
                            'DOGEUSDT DOGE USDT spot',
                            'TRXUSDT TRX USDT spot',
                            'LTCUSDT LTC USDT spot',
                            'DOTUSDT DOT USDT spot',
                            'MATICUSDT MATIC USDT spot',
                            'LINKUSDT LINK USDT spot',
                            'AVAXUSDT AVAX USDT spot',
                            'SHIBUSDT SHIB USDT spot',
                            'ETHBTC ETH BTC spot',
                            'BNBBTC BNB BTC spot',
                        ];
                        foreach ($popularPairs as $pp):
                            $sym = explode(' ', $pp)[0];
                        ?>
                        <button type="button" class="btn btn-xs btn-outline-secondary quick-pair-btn"
                                data-line="<?= e($pp) ?>"><?= e($sym) ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-xs btn-outline-info" id="fillAllPopular">+ All</button>
                    </div>
                </div>
                <label class="form-label small">Pairs to Import</label>
                <textarea class="form-control bg-dark text-light border-secondary font-monospace"
                          id="importPairsText" rows="10"
                          placeholder="BTCUSDT BTC USDT spot&#10;ETHUSDT ETH USDT spot&#10;SOLUSDT SOL USDT futures"></textarea>
                <div class="form-text text-secondary">Each non-empty, non-comment (#) line is one pair.</div>
                <div id="importResult" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info fw-semibold" id="runImportBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="importSpinner"></span>
                    <i class="fas fa-upload me-1"></i>Run Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openEditPair(pair, currencies) {
    document.getElementById('editPairId').value = pair.id;
    document.getElementById('editPairSymbolLabel').textContent = pair.symbol;
    document.getElementById('editPairSymbol').value = pair.symbol;
    document.getElementById('editPairMarket').value = pair.market_type || 'spot';
    document.getElementById('editPairMakerFee').value = pair.maker_fee_percent || '0.1';
    document.getElementById('editPairTakerFee').value = pair.taker_fee_percent || '0.1';
    document.getElementById('editPairLeverage').value = pair.max_leverage || 1;
    document.getElementById('editPairMinOrder').value = pair.min_order_size || '0.001';
    document.getElementById('editPairMaxOrder').value = pair.max_order_size || '';
    document.getElementById('editPairMinNotional').value = pair.min_notional || '1';
    document.getElementById('editPairPricePrec').value = pair.price_precision || 2;
    document.getElementById('editPairQtyPrec').value = pair.quantity_precision || 6;
    document.getElementById('editPairDisplayOrder').value = pair.display_order || 0;
    document.getElementById('editPairIsActive').checked = parseInt(pair.is_active) === 1;
    document.getElementById('editPairTradingEnabled').checked = parseInt(pair.trading_enabled) === 1;
    document.getElementById('editPairIsVisible').checked = parseInt(pair.is_visible) === 1;

    ['editPairBase', 'editPairQuote'].forEach(function(selId) {
        const sel = document.getElementById(selId);
        sel.innerHTML = '<option value="">Select...</option>';
        currencies.forEach(function(c) {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.code + ' – ' + c.name;
            if (selId === 'editPairBase' && parseInt(c.id) === parseInt(pair.base_currency_id)) opt.selected = true;
            if (selId === 'editPairQuote' && parseInt(c.id) === parseInt(pair.quote_currency_id)) opt.selected = true;
            sel.appendChild(opt);
        });
    });

    new bootstrap.Modal(document.getElementById('editPairModal')).show();
}

function deletePair(id, symbol) {
    if (!confirm('Delete pair ' + symbol + '?')) return;
    document.getElementById('deletePairId').value = id;
    $('#deletePairForm').trigger('submit');
}

// Quick-fill individual popular pair
document.querySelectorAll('.quick-pair-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const ta   = document.getElementById('importPairsText');
        const line = this.dataset.line;
        const existing = ta.value.trim();
        if (existing === '') {
            ta.value = line;
        } else if (!existing.split('\n').includes(line)) {
            ta.value = existing + '\n' + line;
        }
    });
});

// Fill ALL popular pairs at once
document.getElementById('fillAllPopular').addEventListener('click', function() {
    const lines = [];
    document.querySelectorAll('.quick-pair-btn').forEach(function(btn) {
        lines.push(btn.dataset.line);
    });
    document.getElementById('importPairsText').value = lines.join('\n');
});

// Run Bulk Import
document.getElementById('runImportBtn').addEventListener('click', function() {
    const text = document.getElementById('importPairsText').value.trim();
    const csrf = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : (document.querySelector('[name="_token"]') ? document.querySelector('[name="_token"]').value : '');
    if (!text) {
        Swal.fire({ icon: 'warning', title: 'Nothing to import', text: 'Please enter at least one pair.' });
        return;
    }
    const btn     = this;
    const spinner = document.getElementById('importSpinner');
    btn.disabled  = true;
    spinner.classList.remove('d-none');
    const resultDiv = document.getElementById('importResult');
    resultDiv.className = 'mt-3 d-none';
    resultDiv.innerHTML = '';

    const params = new URLSearchParams({
        _token:            csrf,
        pairs_text:        text,
        market_type:       document.getElementById('importMarketType').value,
        maker_fee_percent: document.getElementById('importMakerFee').value,
        taker_fee_percent: document.getElementById('importTakerFee').value,
    });

    fetch('/admin/assets/pairs/import', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf },
        body:    params.toString(),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        resultDiv.className = 'mt-3';
        let html = '<div class="alert ' + (data.ok ? 'alert-success' : 'alert-danger') + ' small">' + data.message + '</div>';
        if (data.errors && data.errors.length > 0) {
            html += '<div class="alert alert-warning small"><strong>Skipped / Errors:</strong><ul class="mb-0">';
            data.errors.forEach(function(e) { html += '<li>' + e + '</li>'; });
            html += '</ul></div>';
        }
        resultDiv.innerHTML = html;
        if (data.ok && data.created > 0) {
            setTimeout(function() { location.reload(); }, 2200);
        }
    })
    .catch(function() { Swal.fire({ icon: 'error', title: 'Request failed', text: 'Network error.' }); })
    .finally(function() { btn.disabled = false; spinner.classList.add('d-none'); });
});
</script>
