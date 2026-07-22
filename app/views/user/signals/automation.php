<?php declare(strict_types=1);
$rules        = (array)($rules ?? []);
$pairs        = (array)($pairs ?? []);
$triggerTypes = (array)($triggerTypes ?? []);
$actionTypes  = (array)($actionTypes ?? []);
$stats        = (array)($stats ?? []);
$recentLogs   = (array)($recentLogs ?? []);
$csrfToken    = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- Stats strip -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Total Rules',  $stats['total']      ?? 0, 'fa-robot',       'text-light'],
        ['Active',       $stats['active']     ?? 0, 'fa-play-circle', 'text-success'],
        ['Total Fires',  $stats['executions'] ?? 0, 'fa-bolt',        'text-warning'],
    ];
    foreach ($statCards as [$label, $val, $icon, $col]):
    ?>
    <div class="col-4">
        <div class="glass rounded-3 p-3 text-center">
            <div class="<?= $col ?> mb-1"><i class="fas <?= $icon ?> fa-lg"></i></div>
            <div class="fw-bold fs-4"><?= $val ?></div>
            <div class="text-secondary small"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-robot me-2 text-success"></i>Automation Rules</h5>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createRuleModal">
        <i class="fas fa-plus me-1"></i>New Rule
    </button>
</div>

<!-- Rules list -->
<div class="row g-3 mb-4">
    <?php if (empty($rules)): ?>
    <div class="col-12">
        <div class="glass rounded-3 p-5 text-center text-secondary">
            <i class="fas fa-robot fa-3x mb-3 d-block"></i>
            <div class="fw-semibold mb-2">No automation rules yet</div>
            <div class="small mb-3">Create rules that automatically place orders, send alerts, or call webhooks when market conditions are met.</div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRuleModal">
                <i class="fas fa-plus me-1"></i>Create First Rule
            </button>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($rules as $rule):
        $execPct = $rule['max_executions'] ? min(100, round(100 * $rule['execution_count'] / $rule['max_executions'])) : null;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass rounded-3 h-100">
            <div class="p-3 border-bottom border-secondary d-flex align-items-start justify-content-between">
                <div>
                    <div class="fw-semibold"><?= e($rule['name']) ?></div>
                    <?php if (!empty($rule['description'])): ?>
                    <div class="text-secondary small"><?= e($rule['description']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox"
                               <?= $rule['is_active'] ? 'checked' : '' ?>
                               onchange="toggleRule(<?= (int)$rule['id'] ?>, this)">
                    </div>
                </div>
            </div>
            <div class="p-3">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="text-secondary" style="font-size:.7rem;text-transform:uppercase">Trigger</div>
                        <div class="small fw-semibold"><?= e($triggerTypes[$rule['trigger_type']] ?? $rule['trigger_type']) ?></div>
                        <?php if (!empty($rule['trigger_pair_symbol'])): ?>
                        <div class="text-info small"><?= e($rule['trigger_pair_symbol']) ?> @ <?= number_format((float)$rule['trigger_value'], 6) ?></div>
                        <?php else: ?>
                        <div class="text-secondary small">Value: <?= number_format((float)$rule['trigger_value'], 6) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <div class="text-secondary" style="font-size:.7rem;text-transform:uppercase">Action</div>
                        <div class="small fw-semibold"><?= e($actionTypes[$rule['action_type']] ?? $rule['action_type']) ?></div>
                        <?php if (!empty($rule['action_pair_symbol'])): ?>
                        <div class="text-success small"><?= e($rule['action_pair_symbol']) ?>
                            <?= !empty($rule['action_side']) ? strtoupper($rule['action_side']) : '' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-3 text-secondary small mb-2">
                    <span><i class="fas fa-bolt me-1"></i><?= (int)$rule['execution_count'] ?> fires</span>
                    <span><i class="fas fa-clock me-1"></i><?= (int)$rule['cooldown_minutes'] ?>m cooldown</span>
                    <?php if ($rule['max_executions']): ?>
                    <span><i class="fas fa-flag-checkered me-1"></i>max <?= (int)$rule['max_executions'] ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($execPct !== null): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small text-secondary mb-1">
                        <span>Execution limit</span><span><?= (int)$rule['execution_count'] ?>/<?= (int)$rule['max_executions'] ?></span>
                    </div>
                    <div class="progress" style="height:4px">
                        <div class="progress-bar bg-warning" style="width:<?= $execPct ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-xs btn-outline-info flex-grow-1"
                            onclick="viewRuleLogs(<?= (int)$rule['id'] ?>, '<?= e(addslashes($rule['name'])) ?>')">
                        <i class="fas fa-history me-1"></i>Logs
                    </button>
                    <button class="btn btn-xs btn-outline-danger"
                            onclick="deleteRule(<?= (int)$rule['id'] ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Recent Logs -->
<?php if (!empty($recentLogs)): ?>
<h5 class="mb-3 fw-semibold"><i class="fas fa-history me-2 text-secondary"></i>Recent Executions</h5>
<div class="glass rounded-3">
    <div class="table-responsive">
        <table class="table table-user mb-0">
            <thead><tr><th>Rule</th><th>Trigger</th><th>Action</th><th>Result</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($recentLogs as $log):
                $resColor = match($log['action_result']) {
                    'success' => 'success', 'failed' => 'danger', default => 'secondary'
                };
            ?>
            <tr>
                <td class="small fw-semibold"><?= e($log['rule_name'] ?? '—') ?></td>
                <td class="small"><?= e(str_replace('_', ' ', $log['trigger_type'])) ?> @ <?= number_format((float)$log['trigger_value'], 6) ?></td>
                <td class="small"><?= e(str_replace('_', ' ', $log['action_type'])) ?></td>
                <td><span class="badge bg-<?= $resColor ?>"><?= e($log['action_result']) ?></span>
                    <?php if (!empty($log['result_detail'])): ?>
                    <span class="text-secondary small ms-1"><?= e($log['result_detail']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="small text-secondary"><?= e(date('M d H:i:s', strtotime($log['executed_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Create Rule Modal -->
<div class="modal fade" id="createRuleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-robot me-2 text-success"></i>New Automation Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createRuleForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small text-secondary">Rule Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="name" placeholder="e.g. Buy BTC on dip" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Cooldown (minutes)</label>
                            <input type="number" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="cooldown_minutes" value="60" min="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Description</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="description" placeholder="Optional description">
                        </div>
                        <!-- Trigger section -->
                        <div class="col-12"><hr class="border-secondary my-0"><div class="text-info small fw-semibold mt-2">TRIGGER — When this happens…</div></div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Trigger Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="trigger_type" required>
                                <?php foreach ($triggerTypes as $k => $v): ?>
                                <option value="<?= e($k) ?>"><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Trigger Pair</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="trigger_pair_id">
                                <option value="">Any / not applicable</option>
                                <?php foreach ($pairs as $pair): ?>
                                <option value="<?= (int)$pair['id'] ?>"><?= e($pair['symbol']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-secondary">Trigger Value <span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="trigger_value" placeholder="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-secondary">Timeframe</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="trigger_timeframe">
                                <?php foreach (['1m'=>'1m','5m'=>'5m','15m'=>'15m','1h'=>'1h','4h'=>'4h','1d'=>'1d'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= $k==='1h'?'selected':'' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Action section -->
                        <div class="col-12"><hr class="border-secondary my-0"><div class="text-success small fw-semibold mt-2">ACTION — Do this…</div></div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Action Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary"
                                    name="action_type" required onchange="toggleActionFields(this.value)">
                                <?php foreach ($actionTypes as $k => $v): ?>
                                <option value="<?= e($k) ?>"><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="actionPairGroup">
                            <label class="form-label small text-secondary">Action Pair</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="action_pair_id">
                                <option value="">Same as trigger</option>
                                <?php foreach ($pairs as $pair): ?>
                                <option value="<?= (int)$pair['id'] ?>"><?= e($pair['symbol']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2" id="actionSideGroup">
                            <label class="form-label small text-secondary">Side</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="action_side">
                                <option value="">—</option>
                                <option value="buy">Buy</option>
                                <option value="sell">Sell</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="actionQtyGroup">
                            <label class="form-label small text-secondary">Quantity</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="action_quantity" placeholder="0">
                        </div>
                        <div class="col-md-3" id="actionPriceGroup">
                            <label class="form-label small text-secondary">Limit Price</label>
                            <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="action_price" placeholder="For limit orders only">
                        </div>
                        <div class="col-md-3" id="webhookGroup" style="display:none">
                            <label class="form-label small text-secondary">Webhook URL</label>
                            <input type="url" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="webhook_url" placeholder="https://…">
                        </div>
                        <div class="col-md-3" id="notifyMsgGroup" style="display:none">
                            <label class="form-label small text-secondary">Notification Message</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="notification_message" placeholder="Custom message…">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-secondary">Max Executions (0=∞)</label>
                            <input type="number" class="form-control form-control-sm bg-dark text-light border-secondary"
                                   name="max_executions" value="0" min="0">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" onclick="submitCreateRule()">
                    <i class="fas fa-robot me-1"></i>Create Rule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rule Logs Modal -->
<div class="modal fade" id="ruleLogsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="ruleLogsTitle">Rule Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ruleLogsBody">
                <div class="text-center py-4"><div class="spinner-border text-info"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function toggleRule(ruleId, checkbox) {
    fetch('/user/signals/automation/toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&rule_id=${ruleId}`
    })
    .then(r => r.json())
    .then(d => { if (!d.ok) { checkbox.checked = !checkbox.checked; alert(d.message || 'Error'); } });
}

function deleteRule(ruleId) {
    if (!confirm('Delete this automation rule?')) return;
    fetch('/user/signals/automation/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&rule_id=${ruleId}`
    })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert(d.message || 'Error'); });
}

function toggleActionFields(type) {
    const orderTypes = ['place_market_order', 'place_limit_order', 'close_position', 'cancel_open_orders'];
    const show = orderTypes.includes(type);
    ['actionPairGroup','actionSideGroup','actionQtyGroup','actionPriceGroup'].forEach(id => {
        document.getElementById(id).style.display = show ? '' : 'none';
    });
    document.getElementById('webhookGroup').style.display    = (type === 'webhook_call') ? '' : 'none';
    document.getElementById('notifyMsgGroup').style.display  = (type === 'send_notification') ? '' : 'none';
    document.getElementById('actionPriceGroup').style.display = (type === 'place_limit_order') ? '' : 'none';
}

function submitCreateRule() {
    const form = document.getElementById('createRuleForm');
    const data = new URLSearchParams(new FormData(form)).toString();
    fetch('/user/signals/automation/create', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('createRuleModal'))?.hide();
            location.reload();
        } else {
            alert(d.message || 'Error creating rule');
        }
    });
}

function viewRuleLogs(ruleId, ruleName) {
    document.getElementById('ruleLogsTitle').textContent = 'Logs: ' + ruleName;
    document.getElementById('ruleLogsBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-info"></div></div>';
    new bootstrap.Modal(document.getElementById('ruleLogsModal')).show();

    fetch(`/user/signals/automation/logs?rule_id=${ruleId}`)
    .then(r => r.json())
    .then(d => {
        if (!d.ok || !d.logs.length) {
            document.getElementById('ruleLogsBody').innerHTML = '<p class="text-center text-secondary py-3">No execution logs yet.</p>';
            return;
        }
        let html = '<table class="table table-user table-sm"><thead><tr><th>Trigger</th><th>Action</th><th>Result</th><th>Detail</th><th>Time</th></tr></thead><tbody>';
        d.logs.forEach(l => {
            const rc = l.action_result === 'success' ? 'success' : (l.action_result === 'failed' ? 'danger' : 'secondary');
            html += `<tr>
                <td>${l.trigger_type.replace(/_/g,' ')} @ ${parseFloat(l.trigger_value).toFixed(6)}</td>
                <td>${l.action_type.replace(/_/g,' ')}</td>
                <td><span class="badge bg-${rc}">${l.action_result}</span></td>
                <td class="small text-secondary">${l.result_detail || '—'}</td>
                <td class="small">${l.executed_at}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('ruleLogsBody').innerHTML = html;
    });
}

// Init action field visibility
toggleActionFields(document.querySelector('[name="action_type"]')?.value || '');
</script>
