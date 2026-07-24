<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-cogs me-2 text-secondary"></i>Withdrawal Gateway Settings</h1>
        <p class="text-secondary mb-0">Configure withdrawal fees, limits, and enable/disable per currency.</p>
    </div>
    <a href="/admin/withdrawals" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="alert alert-info small rounded-4 mb-4">
    <i class="fas fa-info-circle me-2"></i>
    Changes apply immediately. Users will see the updated fees and limits on the withdrawal form.
    A <strong>zero daily limit</strong> means no daily cap.
</div>

<!-- Crypto Currencies -->
<div class="glass rounded-4 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-bitcoin me-2 text-warning"></i>Crypto Currency Settings</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="cryptoTable">
            <thead>
                <tr>
                    <th>Currency</th><th>Network</th><th>Enabled</th>
                    <th>Fixed Fee</th><th>% Fee</th><th>Min Withdrawal</th><th>Max Daily</th><th>Confirmations</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($currencies as $cur):
                if (($cur['type'] ?? '') !== 'crypto') continue;
            ?>
            <tr>
                <td>
                    <span class="badge bg-warning text-dark"><?= e((string)$cur['code']) ?></span>
                    <div class="small text-secondary"><?= e((string)($cur['name'] ?? '')) ?></div>
                </td>
                <td class="small text-secondary"><?= e((string)($cur['network'] ?? '—')) ?></td>
                <td>
                    <span class="badge <?= (int)$cur['is_withdrawal_enabled'] ? 'bg-success' : 'bg-danger' ?>">
                        <?= (int)$cur['is_withdrawal_enabled'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </td>
                <td class="font-monospace small"><?= number_format((float)($cur['withdrawal_fee_fixed']   ?? 0), 8) ?></td>
                <td class="font-monospace small"><?= number_format((float)($cur['withdrawal_fee_percent'] ?? 0), 4) ?>%</td>
                <td class="font-monospace small"><?= number_format((float)($cur['min_withdrawal']         ?? 0), 8) ?></td>
                <td class="font-monospace small">
                    <?= !empty($cur['max_withdrawal_daily']) ? number_format((float)$cur['max_withdrawal_daily'], 8) : '—' ?>
                </td>
                <td class="small text-secondary"><?= (int)($cur['confirmations_required'] ?? 0) ?: '—' ?></td>
                <td class="text-end">
                    <button class="btn btn-xs btn-outline-primary edit-gateway-btn"
                            data-id="<?= (int)$cur['id'] ?>"
                            data-code="<?= e((string)$cur['code']) ?>"
                            data-name="<?= e((string)($cur['name'] ?? '')) ?>"
                            data-enabled="<?= (int)$cur['is_withdrawal_enabled'] ?>"
                            data-fee-fixed="<?= e((string)($cur['withdrawal_fee_fixed']   ?? '0')) ?>"
                            data-fee-pct="<?= e((string)($cur['withdrawal_fee_percent'] ?? '0')) ?>"
                            data-min-wd="<?= e((string)($cur['min_withdrawal']         ?? '0')) ?>"
                            data-max-daily="<?= e((string)($cur['max_withdrawal_daily'] ?? '')) ?>">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Fiat Currencies -->
<div class="glass rounded-4 p-4">
    <h5 class="mb-3"><i class="fas fa-university me-2 text-info"></i>Fiat Currency Settings</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="fiatTable">
            <thead>
                <tr>
                    <th>Currency</th><th>Enabled</th>
                    <th>Fixed Fee</th><th>% Fee</th><th>Min Withdrawal</th><th>Max Daily</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($currencies as $cur):
                if (($cur['type'] ?? '') !== 'fiat') continue;
            ?>
            <tr>
                <td>
                    <span class="badge bg-info text-dark"><?= e((string)$cur['code']) ?></span>
                    <div class="small text-secondary"><?= e((string)($cur['name'] ?? '')) ?></div>
                </td>
                <td>
                    <span class="badge <?= (int)$cur['is_withdrawal_enabled'] ? 'bg-success' : 'bg-danger' ?>">
                        <?= (int)$cur['is_withdrawal_enabled'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </td>
                <td class="font-monospace small"><?= number_format((float)($cur['withdrawal_fee_fixed']   ?? 0), 4) ?></td>
                <td class="font-monospace small"><?= number_format((float)($cur['withdrawal_fee_percent'] ?? 0), 4) ?>%</td>
                <td class="font-monospace small"><?= number_format((float)($cur['min_withdrawal']         ?? 0), 4) ?></td>
                <td class="font-monospace small">
                    <?= !empty($cur['max_withdrawal_daily']) ? number_format((float)$cur['max_withdrawal_daily'], 4) : '—' ?>
                </td>
                <td class="text-end">
                    <button class="btn btn-xs btn-outline-primary edit-gateway-btn"
                            data-id="<?= (int)$cur['id'] ?>"
                            data-code="<?= e((string)$cur['code']) ?>"
                            data-name="<?= e((string)($cur['name'] ?? '')) ?>"
                            data-enabled="<?= (int)$cur['is_withdrawal_enabled'] ?>"
                            data-fee-fixed="<?= e((string)($cur['withdrawal_fee_fixed']   ?? '0')) ?>"
                            data-fee-pct="<?= e((string)($cur['withdrawal_fee_percent'] ?? '0')) ?>"
                            data-min-wd="<?= e((string)($cur['min_withdrawal']         ?? '0')) ?>"
                            data-max-daily="<?= e((string)($cur['max_withdrawal_daily'] ?? '')) ?>">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editGatewayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Edit Gateway: <span id="editCurrencyLabel"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editGatewayForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="currency_id" id="editCurrencyId">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <label class="form-label text-secondary mb-0">Withdrawals Enabled</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_withdrawal_enabled" id="editEnabled" value="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Fixed Fee</label>
                        <input type="number" name="withdrawal_fee_fixed" id="editFeeFixed"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0" placeholder="0">
                        <div class="form-text text-secondary">Flat amount deducted per withdrawal</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Percentage Fee (%)</label>
                        <input type="number" name="withdrawal_fee_percent" id="editFeePct"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.0001" min="0" max="100" placeholder="0">
                        <div class="form-text text-secondary">Percentage of withdrawal amount</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Minimum Withdrawal</label>
                        <input type="number" name="min_withdrawal" id="editMinWd"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0" placeholder="0">
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-secondary small">Max Daily Limit <span class="text-secondary">(0 = no limit)</span></label>
                        <input type="number" name="max_withdrawal_daily" id="editMaxDaily"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0" placeholder="0 (no limit)">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="gatewaySpinner"></span>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const _csrf = '<?= e($csrf) ?>';
$('#cryptoTable, #fiatTable').DataTable({ order:[[0,'asc']], pageLength:25 });

document.querySelectorAll('.edit-gateway-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editCurrencyLabel').textContent = btn.dataset.code + ' — ' + btn.dataset.name;
        document.getElementById('editCurrencyId').value    = btn.dataset.id;
        document.getElementById('editEnabled').checked     = btn.dataset.enabled === '1';
        document.getElementById('editFeeFixed').value      = btn.dataset.feeFixed;
        document.getElementById('editFeePct').value        = btn.dataset.feePct;
        document.getElementById('editMinWd').value         = btn.dataset.minWd;
        document.getElementById('editMaxDaily').value      = btn.dataset.maxDaily;
        new bootstrap.Modal(document.getElementById('editGatewayModal')).show();
    });
});

document.getElementById('editGatewayForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('gatewaySpinner');
    spinner.classList.remove('d-none');
    const data    = Object.fromEntries(new FormData(this));
    if (!data.is_withdrawal_enabled) data.is_withdrawal_enabled = '0';
    try {
        const res  = await fetch('/admin/withdrawals/gateways/update', {
            method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Saved!', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } finally {
        spinner.classList.add('d-none');
    }
});
</script>
