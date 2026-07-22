<?php declare(strict_types=1); ?>
<?php
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$providers     = is_array($providers ?? null)     ? $providers     : [];
$filters       = is_array($filters ?? null)       ? $filters       : [];
$csrf          = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Price Feed Subscriptions</h1>
        <p class="text-secondary mb-0">Configure which price provider feeds each trading pair in real-time.</p>
    </div>
    <a href="/admin/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Overview</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<?php
$totalSubs   = count($subscriptions);
$activeSubs  = count(array_filter($subscriptions, fn($s) => (int)($s['is_active'] ?? 0)));
$wsSubs      = count(array_filter($subscriptions, fn($s) => ($s['feed_mode'] ?? '') === 'websocket'));
$pollSubs    = count(array_filter($subscriptions, fn($s) => ($s['feed_mode'] ?? '') === 'polling'));
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total</div><div class="h5 mb-0"><?= $totalSubs ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Active</div><div class="h5 mb-0 text-success"><?= $activeSubs ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">WebSocket</div><div class="h5 mb-0 text-info"><?= $wsSubs ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Polling</div><div class="h5 mb-0 text-warning"><?= $pollSubs ?></div></div></div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/markets/feed">
        <div class="col-md-4">
            <select class="form-select" name="provider_id">
                <option value="">All Providers</option>
                <?php foreach ($providers as $prov): ?>
                    <option value="<?= (int)$prov['id'] ?>" <?= (int)($filters['provider_id'] ?? 0) === (int)$prov['id'] ? 'selected' : '' ?>>
                        <?= e((string)$prov['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="is_active">
                <option value="">All Status</option>
                <option value="1" <?= (($filters['is_active'] ?? '') === '1') ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= (($filters['is_active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="/admin/markets/feed">Reset</a>
        </div>
    </form>
</div>

<!-- Subscriptions Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="feedTable">
            <thead><tr>
                <th>Pair</th><th>Primary Provider</th><th>Fallback</th>
                <th>Mode</th><th>Poll Interval</th><th>Max Staleness</th>
                <th>Status</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)$sub['symbol']) ?></td>
                    <td><?= e((string)($sub['primary_provider_name'] ?? '-')) ?></td>
                    <td class="text-secondary"><?= e((string)($sub['fallback_provider_name'] ?? 'None')) ?></td>
                    <td>
                        <span class="badge text-bg-<?= ($sub['feed_mode'] ?? '') === 'websocket' ? 'info' : 'warning' ?>">
                            <?= e(ucfirst((string)($sub['feed_mode'] ?? '-'))) ?>
                        </span>
                    </td>
                    <td class="small"><?= (int)($sub['poll_interval_seconds'] ?? 0) ?>s</td>
                    <td class="small"><?= (int)($sub['max_allowed_staleness_seconds'] ?? 0) ?>s</td>
                    <td>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input feed-toggle" type="checkbox"
                                   data-pair="<?= (int)$sub['trading_pair_id'] ?>"
                                   <?= (int)($sub['is_active'] ?? 0) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light"
                                onclick="openEditFeed(<?= e(json_encode($sub)) ?>)">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$subscriptions): ?>
                <tr><td colspan="8" class="text-center text-secondary py-4">No subscriptions configured.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Feed Modal -->
<div class="modal fade" id="editFeedModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/markets/feed/save" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="trading_pair_id" id="editFeedPairId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Feed — <span id="editFeedPairSymbol"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Primary Provider <span class="text-danger">*</span></label>
                        <select class="form-select" name="primary_provider_id" id="editFeedPrimary" required>
                            <option value="">— Select —</option>
                            <?php foreach ($providers as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>"><?= e((string)$prov['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fallback Provider</label>
                        <select class="form-select" name="fallback_provider_id" id="editFeedFallback">
                            <option value="">None</option>
                            <?php foreach ($providers as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>"><?= e((string)$prov['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Feed Mode</label>
                        <select class="form-select" name="feed_mode" id="editFeedMode">
                            <option value="websocket">WebSocket</option>
                            <option value="polling">Polling</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Poll Interval (s)</label>
                        <input class="form-control" type="number" name="poll_interval_seconds" id="editFeedInterval" min="1" max="3600">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Max Staleness (s)</label>
                        <input class="form-control" type="number" name="max_allowed_staleness_seconds" id="editFeedStaleness" min="5">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editFeedActive">
                            <label class="form-check-label" for="editFeedActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Save</button>
            </div>
        </form>
    </div>
</div>

<form id="toggleFeedForm" data-ajax="true" action="/admin/markets/feed/toggle" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="trading_pair_id" id="toggleFeedPair">
    <input type="hidden" name="is_active" id="toggleFeedActive">
</form>

<script>
function openEditFeed(sub) {
    document.getElementById('editFeedPairId').value      = sub.trading_pair_id;
    document.getElementById('editFeedPairSymbol').textContent = sub.symbol || '';
    document.getElementById('editFeedPrimary').value     = sub.primary_provider_id || '';
    document.getElementById('editFeedFallback').value    = sub.fallback_provider_id || '';
    document.getElementById('editFeedMode').value        = sub.feed_mode || 'websocket';
    document.getElementById('editFeedInterval').value    = sub.poll_interval_seconds || 30;
    document.getElementById('editFeedStaleness').value   = sub.max_allowed_staleness_seconds || 30;
    document.getElementById('editFeedActive').checked    = parseInt(sub.is_active) === 1;
    new bootstrap.Modal(document.getElementById('editFeedModal')).show();
}
document.querySelectorAll('.feed-toggle').forEach(function(tog) {
    tog.addEventListener('change', function() {
        document.getElementById('toggleFeedPair').value   = this.dataset.pair;
        document.getElementById('toggleFeedActive').value = this.checked ? '1' : '0';
        $('#toggleFeedForm').trigger('submit');
    });
});
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#feedTable').DataTable({ order: [], pageLength: 25, dom: 'lrtip' });
    }
});
</script>
