<?php declare(strict_types=1); ?>
<?php
$logs      = is_array($logs ?? null)      ? $logs      : [];
$stats     = is_array($stats ?? null)     ? $stats     : [];
$providers = is_array($providers ?? null) ? $providers : [];
$filters   = is_array($filters ?? null)   ? $filters   : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Price Sync Logs</h1>
        <p class="text-secondary mb-0">Audit every price sync, WebSocket event, and rate-limit incident from all providers.</p>
    </div>
    <a href="/admin/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Overview</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats (24H) -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['Successes (24H)',   (int)($stats['successes']   ?? 0),        'text-success', 'fa-circle-check'],
        ['Failures (24H)',    (int)($stats['failures']    ?? 0),        'text-danger',  'fa-circle-xmark'],
        ['Rate Limited (24H)',(int)($stats['rate_limited'] ?? 0),       'text-warning', 'fa-gauge-high'],
        ['Avg Response',      number_format((int)($stats['avg_response_ms'] ?? 0)) . 'ms', 'text-info', 'fa-stopwatch'],
    ];
    ?>
    <?php foreach ($kpis as [$label, $val, $cls, $icon]): ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                <i class="fas <?= e($icon) ?> <?= e($cls) ?> small"></i>
                <span class="small text-secondary"><?= e($label) ?></span>
            </div>
            <div class="h5 mb-0 <?= e($cls) ?>"><?= is_numeric($val) ? number_format((int)$val) : e((string)$val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/markets/sync-logs">
        <div class="col-md-3">
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
            <select class="form-select" name="event_type">
                <option value="">All Events</option>
                <?php foreach (['sync_success','sync_failed','rate_limited','ws_connected','ws_disconnected','ws_error'] as $et): ?>
                    <option value="<?= e($et) ?>" <?= (($filters['event_type'] ?? '') === $et) ? 'selected' : '' ?>>
                        <?= e(ucwords(str_replace('_', ' ', $et))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input class="form-control" type="datetime-local" name="since"
                   value="<?= e((string)($filters['since'] ?? '')) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="/admin/markets/sync-logs">Reset</a>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover table-sm align-middle mb-0" id="syncLogsTable">
            <thead><tr>
                <th>Time</th><th>Provider</th><th>Pair</th><th>Event</th>
                <th>HTTP</th><th>Response (ms)</th><th>Message</th>
            </tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                $eventBadge = [
                    'sync_success'     => 'success',
                    'sync_failed'      => 'danger',
                    'rate_limited'     => 'warning',
                    'ws_connected'     => 'info',
                    'ws_disconnected'  => 'secondary',
                    'ws_error'         => 'danger',
                ][$log['event_type'] ?? ''] ?? 'secondary';
                ?>
                <tr>
                    <td class="small text-secondary text-nowrap"><?= e((string)$log['created_at']) ?></td>
                    <td class="small fw-semibold"><?= e((string)($log['provider_name'] ?? '-')) ?></td>
                    <td class="small"><?= e((string)($log['pair_symbol'] ?? '—')) ?></td>
                    <td><span class="badge text-bg-<?= $eventBadge ?>"><?= e(ucwords(str_replace('_', ' ', (string)$log['event_type']))) ?></span></td>
                    <td class="small"><?= $log['http_status'] ? (int)$log['http_status'] : '—' ?></td>
                    <td class="small"><?= $log['response_time_ms'] ? number_format((int)$log['response_time_ms']) : '—' ?></td>
                    <td class="small text-secondary text-truncate" style="max-width:220px" title="<?= e((string)($log['message'] ?? '')) ?>">
                        <?= e((string)($log['message'] ?? '')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No sync logs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#syncLogsTable').DataTable({ order: [[0,'desc']], pageLength: 50, dom: 'lrtip' });
    }
});
</script>
