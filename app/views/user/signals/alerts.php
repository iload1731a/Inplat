<?php declare(strict_types=1);
$alerts     = (array)($alerts ?? []);
$history    = (array)($history ?? []);
$pairs      = (array)($pairs ?? []);
$alertTypes = (array)($alertTypes ?? []);
$stats      = (array)($stats ?? []);
$csrfToken  = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- Stats strip -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Alerts',    $stats['total']     ?? 0, 'fa-bell',        'text-light'],
        ['Active',          $stats['active']    ?? 0, 'fa-bell-on',     'text-success'],
        ['Triggered',       $stats['triggered'] ?? 0, 'fa-bolt',        'text-warning'],
        ['Paused',          $stats['paused']    ?? 0, 'fa-pause-circle','text-secondary'],
    ];
    foreach ($statCards as [$label, $val, $icon, $col]):
    ?>
    <div class="col-6 col-md-3">
        <div class="glass rounded-3 p-3 text-center">
            <div class="<?= $col ?> mb-1"><i class="fas <?= $icon ?> fa-lg"></i></div>
            <div class="fw-bold fs-4"><?= $val ?></div>
            <div class="text-secondary small"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Create Alert Button -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-bell me-2 text-warning"></i>Price Alerts</h5>
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#createAlertModal">
        <i class="fas fa-plus me-1"></i>New Alert
    </button>
</div>

<!-- Alerts Table -->
<div class="glass rounded-3 mb-4">
    <?php if (empty($alerts)): ?>
    <div class="p-4 text-center text-secondary">
        <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
        No price alerts yet. Create your first one to get notified when prices move.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-user mb-0" id="alertsTable">
            <thead>
                <tr>
                    <th>Pair</th><th>Type</th><th>Threshold</th><th>Timeframe</th>
                    <th>Recurring</th><th>Triggers</th><th>Last Triggered</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alerts as $al):
                $statusCol = match($al['status']) {
                    'active'    => 'badge-open',
                    'triggered' => 'badge-completed',
                    'paused'    => 'badge-cancelled',
                    default     => 'badge-cancelled',
                };
            ?>
            <tr>
                <td><span class="fw-semibold"><?= e($al['pair_symbol']) ?></span></td>
                <td><span class="badge bg-info-subtle text-info"><?= e(str_replace('_', ' ', $al['alert_type'])) ?></span></td>
                <td class="font-monospace"><?= number_format((float)$al['threshold_value'], 8) ?></td>
                <td><?= e($al['timeframe']) ?></td>
                <td><?= $al['is_recurring'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-minus text-secondary"></i>' ?></td>
                <td><?= (int)$al['trigger_count'] ?></td>
                <td><?= $al['last_triggered_at'] ? e(date('M d H:i', strtotime($al['last_triggered_at']))) : '—' ?></td>
                <td><span class="badge <?= $statusCol ?>"><?= e($al['status']) ?></span></td>
                <td>
                    <div class="d-flex gap-1">
                        <?php if ($al['status'] === 'active'): ?>
                        <button class="btn btn-xs btn-outline-warning"
                                onclick="alertAction('pause', <?= (int)$al['id'] ?>)">
                            <i class="fas fa-pause"></i>
                        </button>
                        <?php elseif ($al['status'] === 'paused'): ?>
                        <button class="btn btn-xs btn-outline-success"
                                onclick="alertAction('resume', <?= (int)$al['id'] ?>)">
                            <i class="fas fa-play"></i>
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-xs btn-outline-danger"
                                onclick="alertAction('delete', <?= (int)$al['id'] ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Alert History -->
<h5 class="mb-3 fw-semibold"><i class="fas fa-history me-2 text-secondary"></i>Trigger History</h5>
<div class="glass rounded-3">
    <?php if (empty($history)): ?>
    <div class="p-3 text-center text-secondary small">No trigger history yet.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-user mb-0" id="historyTable">
            <thead>
                <tr><th>Pair</th><th>Type</th><th>Threshold</th><th>Triggered At</th><th>Value</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
                <td><?= e($h['pair_symbol'] ?? '—') ?></td>
                <td><?= e(str_replace('_', ' ', $h['alert_type'])) ?></td>
                <td class="font-monospace"><?= number_format((float)$h['threshold_value'], 8) ?></td>
                <td><?= e(date('M d H:i:s', strtotime($h['triggered_at']))) ?></td>
                <td class="font-monospace text-warning"><?= number_format((float)$h['triggered_value'], 8) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create Alert Modal -->
<div class="modal fade" id="createAlertModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-bell-plus me-2 text-warning"></i>New Price Alert</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createAlertForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Trading Pair <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="trading_pair_id" required>
                                <option value="">Select pair…</option>
                                <?php foreach ($pairs as $pair): ?>
                                <option value="<?= (int)$pair['id'] ?>"><?= e($pair['symbol']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Alert Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="alert_type" required>
                                <?php foreach ($alertTypes as $k => $v): ?>
                                <option value="<?= e($k) ?>"><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Threshold Value <span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="threshold_value" placeholder="e.g. 50000" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Timeframe</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="timeframe">
                                <option value="1m">1 Minute</option>
                                <option value="5m">5 Minutes</option>
                                <option value="15m">15 Minutes</option>
                                <option value="1h" selected>1 Hour</option>
                                <option value="4h">4 Hours</option>
                                <option value="1d">1 Day</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Note</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="note" placeholder="Optional note">
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="notify_platform" id="notifyPlatform" value="1" checked>
                                    <label class="form-check-label small" for="notifyPlatform">Platform notification</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="notify_email" id="notifyEmail" value="1" checked>
                                    <label class="form-check-label small" for="notifyEmail">Email notification</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_recurring" id="isRecurring" value="1">
                                    <label class="form-check-label small" for="isRecurring">Recurring (keep firing)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" onclick="submitCreateAlert()">
                    <i class="fas fa-bell-plus me-1"></i>Create Alert
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function alertAction(action, alertId) {
    if (action === 'delete' && !confirm('Delete this alert?')) return;
    const urlMap = {
        pause:  '/user/signals/alerts/pause',
        resume: '/user/signals/alerts/resume',
        delete: '/user/signals/alerts/delete',
    };
    fetch(urlMap[action], {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&alert_id=${alertId}`
    })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert(d.message || 'Error'); });
}

function submitCreateAlert() {
    const form = document.getElementById('createAlertForm');
    const data = new URLSearchParams(new FormData(form)).toString();
    fetch('/user/signals/alerts/create', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('createAlertModal'))?.hide();
            location.reload();
        } else {
            alert(d.message || 'Error creating alert');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('alertsTable')) {
        new DataTable('#alertsTable', {pageLength: 25, order: [[7, 'asc']]});
    }
    if (document.getElementById('historyTable')) {
        new DataTable('#historyTable', {pageLength: 25, order: [[3, 'desc']]});
    }
});
</script>
