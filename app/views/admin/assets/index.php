<?php declare(strict_types=1); ?>
<?php
$currencies   = is_array($currencies ?? null) ? $currencies : [];
$priceTickers = is_array($priceTickers ?? null) ? $priceTickers : [];
$filters      = is_array($filters ?? null) ? $filters : [];
$csrf         = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Markets &amp; Assets</h1>
        <p class="text-secondary mb-0">Manage currencies, tokens and their deposit/withdrawal configurations.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/assets/pairs" class="btn btn-outline-info btn-sm">Trading Pairs</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCurrencyModal">
            <i class="fas fa-plus me-1"></i> New Currency
        </button>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/assets">
        <div class="col-lg-4"><input class="form-control" type="text" name="search" placeholder="Search by code or name" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="type">
                <option value="">All Types</option>
                <?php foreach (['crypto','fiat','token','stablecoin'] as $t): ?>
                    <option value="<?= e($t) ?>" <?= (($filters['type'] ?? '') === $t) ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
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
            <a class="btn btn-outline-light" href="/admin/assets">Reset</a>
        </div>
    </form>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php
    $totalCurrencies = count($currencies);
    $activeCurrencies = count(array_filter($currencies, fn($c) => (int)($c['is_active'] ?? 0) === 1));
    $cryptos  = count(array_filter($currencies, fn($c) => ($c['type'] ?? '') === 'crypto'));
    $fiats    = count(array_filter($currencies, fn($c) => ($c['type'] ?? '') === 'fiat'));
    ?>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total</div><div class="h4 mb-0"><?= $totalCurrencies ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Active</div><div class="h4 mb-0 text-success"><?= $activeCurrencies ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Crypto</div><div class="h4 mb-0 text-info"><?= $cryptos ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Fiat</div><div class="h4 mb-0 text-warning"><?= $fiats ?></div></div></div>
</div>

<!-- Currencies Table -->
<div class="glass rounded-4 p-3 mb-4">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th><th>Name</th><th>Type</th><th>Network</th>
                    <th>Min Deposit</th><th>Min Withdraw</th><th>W/D Fee</th>
                    <th>Wallets</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($currencies as $c): ?>
                <tr>
                    <td>
                        <?php if (!empty($c['icon_url'])): ?>
                            <img src="<?= e($c['icon_url']) ?>" alt="" class="me-1" style="width:18px;height:18px;border-radius:50%">
                        <?php endif; ?>
                        <strong><?= e((string)($c['code'] ?? '-')) ?></strong>
                        <span class="ms-1 text-secondary small"><?= e((string)($c['symbol'] ?? '')) ?></span>
                    </td>
                    <td><?= e((string)($c['name'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= ['crypto'=>'info','fiat'=>'success','token'=>'warning','stablecoin'=>'primary'][$c['type'] ?? ''] ?? 'secondary' ?>"><?= e(ucfirst((string)($c['type'] ?? '-'))) ?></span></td>
                    <td class="text-secondary small"><?= e((string)($c['network'] ?? '-')) ?></td>
                    <td><?= number_format((float)($c['min_deposit'] ?? 0), 8) ?></td>
                    <td><?= number_format((float)($c['min_withdrawal'] ?? 0), 8) ?></td>
                    <td><?= number_format((float)($c['withdrawal_fee_flat'] ?? 0), 8) ?><br>
                        <small class="text-secondary"><?= number_format((float)($c['withdrawal_fee_percent'] ?? 0), 4) ?>%</small></td>
                    <td><?= (int)($c['wallet_count'] ?? 0) ?></td>
                    <td>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input currency-toggle" type="checkbox"
                                data-id="<?= (int)$c['id'] ?>"
                                <?= ((int)($c['is_active'] ?? 0) === 1) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1" onclick="openEditCurrency(<?= e(json_encode($c)) ?>)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteCurrency(<?= (int)$c['id'] ?>, '<?= e((string)$c['code']) ?>')">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($currencies === []): ?>
                <tr><td colspan="10" class="text-center text-secondary">No currencies found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Live Price Tickers -->
<?php if (!empty($priceTickers)): ?>
<div class="glass rounded-4 p-3">
    <h2 class="h6 mb-3">Live Price Tickers (Top by Volume)</h2>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead><tr><th>Pair</th><th>Last</th><th>Bid</th><th>Ask</th><th>24H Change</th><th>Volume 24H</th><th>High</th><th>Low</th></tr></thead>
            <tbody>
            <?php foreach ($priceTickers as $ticker): ?>
                <?php $change = (float)($ticker['price_change_pct_24h'] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($ticker['symbol'] ?? '-')) ?></td>
                    <td><?= number_format((float)($ticker['last_price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($ticker['bid_price'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($ticker['ask_price'] ?? 0), 6) ?></td>
                    <td class="<?= $change >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $change >= 0 ? '+' : '' ?><?= number_format($change, 2) ?>%
                    </td>
                    <td><?= number_format((float)($ticker['volume_24h'] ?? 0), 2) ?></td>
                    <td><?= number_format((float)($ticker['high_24h'] ?? 0), 6) ?></td>
                    <td><?= number_format((float)($ticker['low_24h'] ?? 0), 6) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Create Currency Modal -->
<div class="modal fade" id="createCurrencyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/assets/currency/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">New Currency / Asset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Code <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="code" placeholder="BTC" required maxlength="20"></div>
                    <div class="col-md-3"><label class="form-label">Symbol</label>
                        <input class="form-control" type="text" name="symbol" placeholder="₿"></div>
                    <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="name" placeholder="Bitcoin" required></div>
                    <div class="col-md-3"><label class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="type" required>
                            <option value="crypto">Crypto</option><option value="fiat">Fiat</option>
                            <option value="token">Token</option><option value="stablecoin">Stablecoin</option>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Precision</label>
                        <input class="form-control" type="number" name="precision" value="8" min="0" max="18"></div>
                    <div class="col-md-3"><label class="form-label">Network</label>
                        <input class="form-control" type="text" name="network" placeholder="ERC-20"></div>
                    <div class="col-md-3"><label class="form-label">Contract Address</label>
                        <input class="form-control" type="text" name="contract_address"></div>
                    <div class="col-md-3"><label class="form-label">Min Deposit</label>
                        <input class="form-control" type="text" name="min_deposit" value="0"></div>
                    <div class="col-md-3"><label class="form-label">Max Deposit</label>
                        <input class="form-control" type="text" name="max_deposit" placeholder="unlimited"></div>
                    <div class="col-md-3"><label class="form-label">Min Withdrawal</label>
                        <input class="form-control" type="text" name="min_withdrawal" value="0"></div>
                    <div class="col-md-3"><label class="form-label">Max Withdrawal</label>
                        <input class="form-control" type="text" name="max_withdrawal" placeholder="unlimited"></div>
                    <div class="col-md-3"><label class="form-label">W/D Fee Flat</label>
                        <input class="form-control" type="text" name="withdrawal_fee_flat" value="0"></div>
                    <div class="col-md-3"><label class="form-label">W/D Fee %</label>
                        <input class="form-control" type="text" name="withdrawal_fee_percent" value="0"></div>
                    <div class="col-md-3"><label class="form-label">Deposit Fee Flat</label>
                        <input class="form-control" type="text" name="deposit_fee_flat" value="0"></div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="createCurrActive" checked>
                            <label class="form-check-label" for="createCurrActive">Active</label>
                        </div>
                    </div>
                    <div class="col-md-6"><label class="form-label">Explorer URL</label>
                        <input class="form-control" type="url" name="explorer_url"></div>
                    <div class="col-md-6"><label class="form-label">Icon URL</label>
                        <input class="form-control" type="url" name="icon_url"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Currency</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Currency Modal (reuse same structure, JS fills it) -->
<div class="modal fade" id="editCurrencyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/assets/currency/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="currency_id" id="editCurrId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Currency</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Code</label>
                        <input class="form-control" type="text" name="code" id="editCurrCode" required maxlength="20"></div>
                    <div class="col-md-3"><label class="form-label">Symbol</label>
                        <input class="form-control" type="text" name="symbol" id="editCurrSymbol"></div>
                    <div class="col-md-6"><label class="form-label">Name</label>
                        <input class="form-control" type="text" name="name" id="editCurrName" required></div>
                    <div class="col-md-3"><label class="form-label">Type</label>
                        <select class="form-select" name="type" id="editCurrType">
                            <option value="crypto">Crypto</option><option value="fiat">Fiat</option>
                            <option value="token">Token</option><option value="stablecoin">Stablecoin</option>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Precision</label>
                        <input class="form-control" type="number" name="precision" id="editCurrPrecision" min="0" max="18"></div>
                    <div class="col-md-3"><label class="form-label">Network</label>
                        <input class="form-control" type="text" name="network" id="editCurrNetwork"></div>
                    <div class="col-md-3"><label class="form-label">Contract Address</label>
                        <input class="form-control" type="text" name="contract_address" id="editCurrContract"></div>
                    <div class="col-md-3"><label class="form-label">Min Deposit</label>
                        <input class="form-control" type="text" name="min_deposit" id="editCurrMinDep"></div>
                    <div class="col-md-3"><label class="form-label">Max Deposit</label>
                        <input class="form-control" type="text" name="max_deposit" id="editCurrMaxDep"></div>
                    <div class="col-md-3"><label class="form-label">Min Withdrawal</label>
                        <input class="form-control" type="text" name="min_withdrawal" id="editCurrMinWd"></div>
                    <div class="col-md-3"><label class="form-label">Max Withdrawal</label>
                        <input class="form-control" type="text" name="max_withdrawal" id="editCurrMaxWd"></div>
                    <div class="col-md-3"><label class="form-label">W/D Fee Flat</label>
                        <input class="form-control" type="text" name="withdrawal_fee_flat" id="editCurrWdFee"></div>
                    <div class="col-md-3"><label class="form-label">W/D Fee %</label>
                        <input class="form-control" type="text" name="withdrawal_fee_percent" id="editCurrWdFeePct"></div>
                    <div class="col-md-3"><label class="form-label">Deposit Fee Flat</label>
                        <input class="form-control" type="text" name="deposit_fee_flat" id="editCurrDepFee"></div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editCurrActive">
                            <label class="form-check-label" for="editCurrActive">Active</label>
                        </div>
                    </div>
                    <div class="col-md-6"><label class="form-label">Explorer URL</label>
                        <input class="form-control" type="url" name="explorer_url" id="editCurrExplorer"></div>
                    <div class="col-md-6"><label class="form-label">Icon URL</label>
                        <input class="form-control" type="url" name="icon_url" id="editCurrIcon"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<form id="deleteCurrForm" data-ajax="true" action="/admin/assets/currency/delete" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="currency_id" id="deleteCurrId">
</form>
<form id="toggleCurrForm" data-ajax="true" action="/admin/assets/currency/toggle" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="currency_id" id="toggleCurrId">
    <input type="hidden" name="is_active" id="toggleCurrActive">
</form>

<script>
function openEditCurrency(c) {
    document.getElementById('editCurrId').value = c.id;
    document.getElementById('editCurrCode').value = c.code || '';
    document.getElementById('editCurrSymbol').value = c.symbol || '';
    document.getElementById('editCurrName').value = c.name || '';
    document.getElementById('editCurrType').value = c.type || 'crypto';
    document.getElementById('editCurrPrecision').value = c.precision || 8;
    document.getElementById('editCurrNetwork').value = c.network || '';
    document.getElementById('editCurrContract').value = c.contract_address || '';
    document.getElementById('editCurrMinDep').value = c.min_deposit || '0';
    document.getElementById('editCurrMaxDep').value = c.max_deposit || '';
    document.getElementById('editCurrMinWd').value = c.min_withdrawal || '0';
    document.getElementById('editCurrMaxWd').value = c.max_withdrawal || '';
    document.getElementById('editCurrWdFee').value = c.withdrawal_fee_flat || '0';
    document.getElementById('editCurrWdFeePct').value = c.withdrawal_fee_percent || '0';
    document.getElementById('editCurrDepFee').value = c.deposit_fee_flat || '0';
    document.getElementById('editCurrActive').checked = parseInt(c.is_active) === 1;
    document.getElementById('editCurrExplorer').value = c.explorer_url || '';
    document.getElementById('editCurrIcon').value = c.icon_url || '';
    new bootstrap.Modal(document.getElementById('editCurrencyModal')).show();
}

function deleteCurrency(id, code) {
    if (!confirm('Delete currency ' + code + '?')) return;
    document.getElementById('deleteCurrId').value = id;
    $('#deleteCurrForm').trigger('submit');
}

document.querySelectorAll('.currency-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        document.getElementById('toggleCurrId').value = this.dataset.id;
        document.getElementById('toggleCurrActive').value = this.checked ? '1' : '0';
        $('#toggleCurrForm').trigger('submit');
    });
});
</script>
