<?php declare(strict_types=1); ?>
<?php
$rules = is_array($rules ?? null) ? $rules : [];
$csrf  = \App\Libraries\Csrf::token();
$validTypes = ['deposit_velocity','withdrawal_velocity','volume_threshold',
               'structuring_pattern','new_account_high_value','geo_risk','custom'];
$validActions = ['flag_only','freeze_account','open_sar_case','notify_compliance'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-shield-virus me-2 text-danger"></i>Transaction Monitoring Rules</h1>
        <p class="text-secondary mb-0">Automated rules to detect suspicious transaction patterns (AML/compliance).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/wallets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-wallet me-1"></i>Wallets</a>
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#createRuleModal">
            <i class="fas fa-plus me-1"></i>New Rule
        </button>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Active Rules Stats -->
<div class="row g-3 mb-4">
    <?php
    $active   = count(array_filter($rules, fn($r) => (int)($r['is_active'] ?? 0) === 1));
    $inactive = count($rules) - $active;
    ?>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Total Rules</div>
            <div class="h4 fw-bold"><?= count($rules) ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Active</div>
            <div class="h4 fw-bold text-success"><?= $active ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Inactive</div>
            <div class="h4 fw-bold text-secondary"><?= $inactive ?></div>
        </div>
    </div>
</div>

<!-- Rules List -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table id="rulesTable" class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th><th>Rule Name</th><th>Type</th><th>Action</th>
                    <th>Conditions</th><th>Created By</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <?php
                $actionColor = match($rule['action'] ?? '') {
                    'freeze_account'     => 'danger',
                    'open_sar_case'      => 'warning',
                    'notify_compliance'  => 'info',
                    default              => 'secondary',
                };
                $conditions = [];
                try {
                    $conditions = json_decode((string)($rule['conditions'] ?? '{}'), true) ?: [];
                } catch (\Throwable) {}
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)$rule['id'] ?></td>
                    <td class="fw-semibold small"><?= e((string)($rule['rule_name'] ?? '-')) ?></td>
                    <td>
                        <span class="badge bg-secondary text-capitalize">
                            <?= e(str_replace('_', ' ', (string)($rule['rule_type'] ?? '-'))) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $actionColor ?> text-capitalize">
                            <?= e(str_replace('_', ' ', (string)($rule['action'] ?? '-'))) ?>
                        </span>
                    </td>
                    <td class="font-monospace small text-secondary" style="max-width:180px">
                        <code class="text-secondary small"><?= e(json_encode($conditions, JSON_UNESCAPED_UNICODE)) ?></code>
                    </td>
                    <td class="small text-info"><?= e((string)($rule['created_by_username'] ?? 'System')) ?></td>
                    <td>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input toggle-rule" type="checkbox"
                                   data-id="<?= (int)$rule['id'] ?>"
                                   <?= (int)($rule['is_active'] ?? 0) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-danger delete-rule-btn"
                                data-id="<?= (int)$rule['id'] ?>"
                                data-name="<?= e((string)($rule['rule_name'] ?? '')) ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rules === []): ?>
                <tr>
                    <td colspan="8" class="text-center text-secondary py-5">
                        <i class="fas fa-shield-alt fa-2x mb-2 d-block opacity-50"></i>
                        No monitoring rules configured yet.<br>
                        <small>Click "New Rule" to add your first rule.</small>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Rule Modal -->
<div class="modal fade" id="createRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-danger"></i>Create Monitoring Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createRuleForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small">Rule Name *</label>
                            <input type="text" name="rule_name"
                                   class="form-control bg-transparent text-light border-secondary"
                                   placeholder="e.g. Large Withdrawal Alert" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small">Rule Type *</label>
                            <select name="rule_type" class="form-select bg-transparent text-light border-secondary" required>
                                <?php foreach ($validTypes as $type): ?>
                                <option value="<?= $type ?>"><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small">Action on Trigger *</label>
                            <select name="action" class="form-select bg-transparent text-light border-secondary" required>
                                <?php foreach ($validActions as $action): ?>
                                <option value="<?= $action ?>"><?= e(ucwords(str_replace('_', ' ', $action))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small">
                                Conditions (JSON) *
                                <span class="text-secondary">— e.g. <code>{"amount_usd_gt": 10000, "window_hours": 24}</code></span>
                            </label>
                            <textarea name="conditions"
                                      class="form-control bg-transparent text-light border-secondary font-monospace"
                                      rows="3" required placeholder='{"amount_gt": 10000, "window_hours": 24}'>{}</textarea>
                            <div class="form-text text-secondary">Must be valid JSON object.</div>
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <strong>Rule Types:</strong>
                        <ul class="mb-0 mt-1">
                            <li><strong>deposit_velocity</strong> – Too many deposits in a time window</li>
                            <li><strong>withdrawal_velocity</strong> – Too many withdrawals in a time window</li>
                            <li><strong>volume_threshold</strong> – Total amount exceeds threshold</li>
                            <li><strong>structuring_pattern</strong> – Multiple transactions just below reporting limit</li>
                            <li><strong>new_account_high_value</strong> – New account with high-value transactions</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="createRuleSpinner"></span>
                        <i class="fas fa-plus me-1"></i>Create Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#rulesTable').DataTable({ order:[[0,'desc']], pageLength:20 });

// Toggle rule
document.querySelectorAll('.toggle-rule').forEach(toggle => {
    toggle.addEventListener('change', async function() {
        const active = this.checked ? 1 : 0;
        try {
            const res = await fetch('/admin/wallets/monitoring/toggle', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ _token:'<?= e($csrf) ?>', rule_id: parseInt(this.dataset.id), active })
            });
            const json = await res.json();
            if (!json.ok) {
                this.checked = !this.checked;
                Swal.fire({ icon:'error', title:'Error', text: json.message });
            }
        } catch(err) {
            this.checked = !this.checked;
        }
    });
});

// Delete rule
document.querySelectorAll('.delete-rule-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            icon:'warning', title:'Delete Rule?',
            text: `Delete rule: "${btn.dataset.name}"?`,
            showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#ef4444'
        });
        if (!confirmed.isConfirmed) return;
        const res = await fetch('/admin/wallets/monitoring/delete', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ _token:'<?= e($csrf) ?>', rule_id: parseInt(btn.dataset.id) })
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Deleted', confirmButtonColor:'#3b82f6' }).then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    });
});

// Create rule form
document.getElementById('createRuleForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('createRuleSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/admin/wallets/monitoring/create', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Rule Created!', confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } catch(err) {
        Swal.fire({ icon:'error', title:'Error', text: 'Network error.' });
    } finally {
        spinner.classList.add('d-none');
    }
});
</script>
