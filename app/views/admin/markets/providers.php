<?php declare(strict_types=1); ?>
<?php
$providers = is_array($providers ?? null) ? $providers : [];
$csrf      = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Price Data Providers</h1>
        <p class="text-secondary mb-0">Manage external providers that supply live price data (CoinGecko, Binance, CoinMarketCap, etc.).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Overview</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createProviderModal">
            <i class="fas fa-plus me-1"></i>New Provider
        </button>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $activeProviders   = count(array_filter($providers, fn($p) => (int)($p['is_active'] ?? 0)));
    $wsProviders       = count(array_filter($providers, fn($p) => in_array($p['provider_type'] ?? '', ['websocket_stream','both'], true)));
    $totalSubs         = array_sum(array_column($providers, 'active_subscriptions'));
    $totalMappings     = array_sum(array_column($providers, 'mapped_assets'));
    ?>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total</div><div class="h5 mb-0"><?= count($providers) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Active</div><div class="h5 mb-0 text-success"><?= $activeProviders ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">With WebSocket</div><div class="h5 mb-0 text-info"><?= $wsProviders ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Active Feeds</div><div class="h5 mb-0 text-warning"><?= $totalSubs ?></div></div></div>
</div>

<!-- Providers Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr>
                <th>Name</th><th>Code</th><th>Type</th><th>Auth</th>
                <th>Rate Limit/min</th><th>Priority</th><th>Subscriptions</th>
                <th>Health</th><th>Status</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($providers as $p): ?>
                <?php $isActive = (int)($p['is_active'] ?? 0); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string)$p['name']) ?></div>
                        <?php if (!empty($p['base_url'])): ?>
                        <div class="text-secondary small text-truncate" style="max-width:160px"><?= e((string)$p['base_url']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><code class="text-info"><?= e((string)$p['provider_code']) ?></code></td>
                    <td>
                        <?php
                        $typeLabels = ['rest_market_data'=>'REST','websocket_stream'=>'WebSocket','both'=>'REST+WS'];
                        $typeBadges = ['rest_market_data'=>'info','websocket_stream'=>'primary','both'=>'warning'];
                        ?>
                        <span class="badge text-bg-<?= $typeBadges[$p['provider_type'] ?? ''] ?? 'secondary' ?>"><?= $typeLabels[$p['provider_type'] ?? ''] ?? e((string)$p['provider_type']) ?></span>
                    </td>
                    <td class="small text-secondary"><?= e(ucfirst(str_replace('_', ' ', (string)$p['auth_type']))) ?></td>
                    <td><?= number_format((int)($p['rate_limit_per_minute'] ?? 0)) ?></td>
                    <td><?= (int)$p['priority'] ?></td>
                    <td><span class="badge text-bg-info"><?= (int)($p['active_subscriptions'] ?? 0) ?> active</span></td>
                    <td>
                        <span class="badge text-bg-<?= ['healthy'=>'success','degraded'=>'warning','down'=>'danger','unknown'=>'secondary'][$p['last_health_status'] ?? 'unknown'] ?? 'secondary' ?>">
                            <?= e(ucfirst((string)($p['last_health_status'] ?? 'unknown'))) ?>
                        </span>
                    </td>
                    <td>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input provider-toggle" type="checkbox"
                                   data-id="<?= (int)$p['id'] ?>"
                                   <?= $isActive ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-end">
                        <?php $pSafe = array_diff_key($p, array_flip(['api_key_encrypted', 'api_secret_encrypted'])); ?>
                        <button class="btn btn-xs btn-outline-light me-1" onclick="openEditProvider(<?= json_encode($pSafe) ?>)">Edit</button>
                        <a href="/admin/markets/mappings?provider_id=<?= (int)$p['id'] ?>" class="btn btn-xs btn-outline-info">Mappings</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$providers): ?>
                <tr><td colspan="10" class="text-center text-secondary">No providers configured.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Provider Modal -->
<div class="modal fade" id="createProviderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/markets/providers/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">New Price Data Provider</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="name" placeholder="e.g. CoinGecko" required></div>
                    <div class="col-md-6"><label class="form-label">Code <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="provider_code" placeholder="e.g. coingecko" required></div>
                    <div class="col-md-6"><label class="form-label">Type</label>
                        <select class="form-select" name="provider_type">
                            <option value="rest_market_data">REST Market Data</option>
                            <option value="websocket_stream">WebSocket Stream</option>
                            <option value="both">Both</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Auth Type</label>
                        <select class="form-select" name="auth_type">
                            <option value="none">None</option>
                            <option value="api_key_header">API Key Header</option>
                            <option value="api_key_query">API Key Query Param</option>
                            <option value="hmac_signature">HMAC Signature</option>
                            <option value="oauth2">OAuth2</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Base URL</label>
                        <input class="form-control" type="url" name="base_url" placeholder="https://api.example.com"></div>
                    <div class="col-md-6"><label class="form-label">WebSocket URL</label>
                        <input class="form-control" type="text" name="websocket_url" placeholder="wss://ws.example.com/ws"></div>
                    <div class="col-md-6"><label class="form-label">Auth Header Name</label>
                        <input class="form-control" type="text" name="auth_header_name" placeholder="X-API-KEY"></div>
                    <div class="col-md-6"><label class="form-label">API Key</label>
                        <input class="form-control" type="password" name="api_key" autocomplete="new-password"></div>
                    <div class="col-md-6"><label class="form-label">API Secret</label>
                        <input class="form-control" type="password" name="api_secret" autocomplete="new-password"></div>
                    <div class="col-md-3"><label class="form-label">Rate Limit/min</label>
                        <input class="form-control" type="number" name="rate_limit_per_minute" value="60" min="1"></div>
                    <div class="col-md-3"><label class="form-label">Priority</label>
                        <input class="form-control" type="number" name="priority" value="100" min="1"></div>
                    <div class="col-md-3"><label class="form-label">Sync Interval (s)</label>
                        <input class="form-control" type="number" name="default_sync_interval_seconds" value="60" min="1"></div>
                    <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="supports_pair_import" value="1" id="crtImport" checked>
                            <label class="form-check-label small" for="crtImport">Pair Import</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="supports_realtime_price" value="1" id="crtRealtime" checked>
                            <label class="form-check-label small" for="crtRealtime">RT Price</label>
                        </div>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Provider</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Provider Modal -->
<div class="modal fade" id="editProviderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/markets/providers/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="provider_id" id="editProvId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Provider</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Name</label>
                        <input class="form-control" type="text" name="name" id="editProvName" required></div>
                    <div class="col-md-6"><label class="form-label">Code</label>
                        <input class="form-control" type="text" name="provider_code" id="editProvCode" required></div>
                    <div class="col-md-6"><label class="form-label">Type</label>
                        <select class="form-select" name="provider_type" id="editProvType">
                            <option value="rest_market_data">REST Market Data</option>
                            <option value="websocket_stream">WebSocket Stream</option>
                            <option value="both">Both</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Auth Type</label>
                        <select class="form-select" name="auth_type" id="editProvAuth">
                            <option value="none">None</option>
                            <option value="api_key_header">API Key Header</option>
                            <option value="api_key_query">API Key Query</option>
                            <option value="hmac_signature">HMAC Signature</option>
                            <option value="oauth2">OAuth2</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Base URL</label>
                        <input class="form-control" type="url" name="base_url" id="editProvBaseUrl"></div>
                    <div class="col-md-6"><label class="form-label">WebSocket URL</label>
                        <input class="form-control" type="text" name="websocket_url" id="editProvWsUrl"></div>
                    <div class="col-md-6"><label class="form-label">Auth Header Name</label>
                        <input class="form-control" type="text" name="auth_header_name" id="editProvHeader"></div>
                    <div class="col-md-3"><label class="form-label">Rate Limit/min</label>
                        <input class="form-control" type="number" name="rate_limit_per_minute" id="editProvRate" min="1"></div>
                    <div class="col-md-3"><label class="form-label">Priority</label>
                        <input class="form-control" type="number" name="priority" id="editProvPriority" min="1"></div>
                    <div class="col-md-3"><label class="form-label">Sync Interval (s)</label>
                        <input class="form-control" type="number" name="default_sync_interval_seconds" id="editProvInterval" min="1"></div>
                    <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="supports_pair_import" value="1" id="editProvImport">
                            <label class="form-check-label small" for="editProvImport">Pair Import</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="supports_realtime_price" value="1" id="editProvRealtime">
                            <label class="form-check-label small" for="editProvRealtime">RT Price</label>
                        </div>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="editProvNotes" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<form id="toggleProvForm" data-ajax="true" action="/admin/markets/providers/toggle" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="provider_id" id="toggleProvId">
    <input type="hidden" name="is_active" id="toggleProvActive">
</form>

<script>
function openEditProvider(p) {
    document.getElementById('editProvId').value       = p.id;
    document.getElementById('editProvName').value     = p.name || '';
    document.getElementById('editProvCode').value     = p.provider_code || '';
    document.getElementById('editProvType').value     = p.provider_type || 'rest_market_data';
    document.getElementById('editProvAuth').value     = p.auth_type || 'none';
    document.getElementById('editProvBaseUrl').value  = p.base_url || '';
    document.getElementById('editProvWsUrl').value    = p.websocket_url || '';
    document.getElementById('editProvHeader').value   = p.auth_header_name || '';
    document.getElementById('editProvRate').value     = p.rate_limit_per_minute || 60;
    document.getElementById('editProvPriority').value = p.priority || 100;
    document.getElementById('editProvInterval').value = p.default_sync_interval_seconds || 60;
    document.getElementById('editProvImport').checked   = parseInt(p.supports_pair_import) === 1;
    document.getElementById('editProvRealtime').checked = parseInt(p.supports_realtime_price) === 1;
    document.getElementById('editProvNotes').value    = p.notes || '';
    new bootstrap.Modal(document.getElementById('editProviderModal')).show();
}

document.querySelectorAll('.provider-toggle').forEach(function(tog) {
    tog.addEventListener('change', function() {
        document.getElementById('toggleProvId').value     = this.dataset.id;
        document.getElementById('toggleProvActive').value = this.checked ? '1' : '0';
        $('#toggleProvForm').trigger('submit');
    });
});
</script>
