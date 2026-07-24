<?php declare(strict_types=1); ?>
<?php
$feeTiers  = is_array($feeTiers  ?? null) ? $feeTiers  : [];
$csrfToken = (string)($csrfToken ?? \App\Libraries\Csrf::token());
?>
<?php include __DIR__ . '/../_nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-percentage me-2 text-info"></i>Fee Tiers</h1>
        <p class="text-secondary mb-0">Define VIP fee tiers by trading volume. Lower-volume users get standard pair fees; high-volume users get tier discounts.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addFeeTierModal">
            <i class="fas fa-plus me-1"></i>Add Tier
        </button>
        <a href="/admin/trading-engine" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>
</div>

<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table class="table table-dark table-hover" id="feeTierTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tier Name</th>
                    <th>Min 30d Vol (USD)</th>
                    <th>Min Token Holding</th>
                    <th>Maker Fee %</th>
                    <th>Taker Fee %</th>
                    <th>Withdrawal Discount %</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($feeTiers as $t): ?>
                <tr>
                    <td><?= (int)($t['id'] ?? 0) ?></td>
                    <td class="fw-semibold"><?= e((string)($t['tier_name'] ?? '-')) ?></td>
                    <td><?= number_format((float)($t['min_30d_volume'] ?? 0), 0) ?></td>
                    <td><?= number_format((float)($t['min_token_holding'] ?? 0), 2) ?></td>
                    <td><?= number_format((float)($t['maker_fee_percent'] ?? 0), 4) ?>%</td>
                    <td><?= number_format((float)($t['taker_fee_percent'] ?? 0), 4) ?>%</td>
                    <td><?= number_format((float)($t['withdrawal_fee_discount_percent'] ?? 0), 2) ?>%</td>
                    <td>
                        <span class="badge text-bg-<?= (int)($t['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>">
                            <?= (int)($t['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="text-secondary small"><?= e((string)($t['created_at'] ?? '-')) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning"
                            onclick='editFeeTier(<?= json_encode($t) ?>)'>
                            <i class="fas fa-edit me-1"></i>Edit
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($feeTiers === []): ?>
                <tr><td colspan="10" class="text-secondary text-center py-4">No fee tiers defined yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Help text -->
<div class="glass rounded-4 p-4 mt-4">
    <h6 class="mb-2"><i class="fas fa-info-circle me-2 text-info"></i>How Fee Tiers Work</h6>
    <ul class="text-secondary small mb-0">
        <li>Users without a fee override get the <strong>trading pair's</strong> default maker/taker rates.</li>
        <li>A fee tier can be assigned to a user via <em>User Management → Fee Override</em>.</li>
        <li>Individual user overrides (custom_maker_fee_percent / custom_taker_fee_percent) take priority over tier rates.</li>
        <li>Maker fee is charged to the resting order; taker fee to the aggressor.</li>
        <li>Fee revenue is recorded in the <code>fee_revenue_ledger</code> table and visible in Finance reports.</li>
    </ul>
</div>

<!-- Add Fee Tier Modal -->
<div class="modal fade" id="addFeeTierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add Fee Tier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Tier Name *</label><input type="text" class="form-control bg-dark text-light" id="addTierName" placeholder="e.g. VIP 1" required></div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Maker Fee % *</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" id="addMakerFee" value="0.1000" required></div>
                    <div class="col"><label class="form-label">Taker Fee % *</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" id="addTakerFee" value="0.1500" required></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Min 30d Volume (USD)</label><input type="number" step="1" min="0" class="form-control bg-dark text-light" id="addMinVol" value="0"></div>
                    <div class="col"><label class="form-label">Min Token Holding</label><input type="number" step="0.01" min="0" class="form-control bg-dark text-light" id="addMinToken" value="0"></div>
                </div>
                <div class="mb-3"><label class="form-label">Withdrawal Fee Discount %</label><input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light" id="addWdDisc" value="0"></div>
                <div class="form-check"><input type="checkbox" class="form-check-input" id="addIsActive" checked><label class="form-check-label" for="addIsActive">Active</label></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="submitAdd()">Create Tier</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Fee Tier Modal -->
<div class="modal fade" id="editFeeTierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Fee Tier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editTierId">
                <div class="mb-3"><label class="form-label">Tier Name *</label><input type="text" class="form-control bg-dark text-light" id="editTierName" required></div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Maker Fee % *</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" id="editMakerFee" required></div>
                    <div class="col"><label class="form-label">Taker Fee % *</label><input type="number" step="0.0001" min="0" max="100" class="form-control bg-dark text-light" id="editTakerFee" required></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Min 30d Volume (USD)</label><input type="number" step="1" min="0" class="form-control bg-dark text-light" id="editMinVol"></div>
                    <div class="col"><label class="form-label">Min Token Holding</label><input type="number" step="0.01" min="0" class="form-control bg-dark text-light" id="editMinToken"></div>
                </div>
                <div class="mb-3"><label class="form-label">Withdrawal Fee Discount %</label><input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light" id="editWdDisc"></div>
                <div class="form-check"><input type="checkbox" class="form-check-input" id="editIsActive"><label class="form-check-label" for="editIsActive">Active</label></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitEdit()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function editFeeTier(tier) {
    document.getElementById('editTierId').value      = tier.id;
    document.getElementById('editTierName').value    = tier.tier_name;
    document.getElementById('editMakerFee').value    = tier.maker_fee_percent;
    document.getElementById('editTakerFee').value    = tier.taker_fee_percent;
    document.getElementById('editMinVol').value      = tier.min_30d_volume;
    document.getElementById('editMinToken').value    = tier.min_token_holding;
    document.getElementById('editWdDisc').value      = tier.withdrawal_fee_discount_percent;
    document.getElementById('editIsActive').checked  = parseInt(tier.is_active) === 1;
    new bootstrap.Modal(document.getElementById('editFeeTierModal')).show();
}

async function submitAdd() {
    const data = {
        tier_name:                       document.getElementById('addTierName').value,
        maker_fee_percent:               document.getElementById('addMakerFee').value,
        taker_fee_percent:               document.getElementById('addTakerFee').value,
        min_30d_volume:                  document.getElementById('addMinVol').value,
        min_token_holding:               document.getElementById('addMinToken').value,
        withdrawal_fee_discount_percent: document.getElementById('addWdDisc').value,
        is_active:                       document.getElementById('addIsActive').checked ? 1 : 0,
        _token:                          CSRF,
    };
    const res = await apiPost('/admin/trading-engine/fee-tiers/create', data);
    if (res.ok) { alert('Fee tier created!'); location.reload(); }
    else alert(res.message || 'Error creating tier');
}

async function submitEdit() {
    const data = {
        tier_id:                         document.getElementById('editTierId').value,
        tier_name:                       document.getElementById('editTierName').value,
        maker_fee_percent:               document.getElementById('editMakerFee').value,
        taker_fee_percent:               document.getElementById('editTakerFee').value,
        min_30d_volume:                  document.getElementById('editMinVol').value,
        min_token_holding:               document.getElementById('editMinToken').value,
        withdrawal_fee_discount_percent: document.getElementById('editWdDisc').value,
        is_active:                       document.getElementById('editIsActive').checked ? 1 : 0,
        _token:                          CSRF,
    };
    const res = await apiPost('/admin/trading-engine/fee-tiers/update', data);
    if (res.ok) { alert('Fee tier updated!'); location.reload(); }
    else alert(res.message || 'Error updating tier');
}

async function apiPost(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    });
    return await r.json();
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof $.fn !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        $('#feeTierTable').DataTable({ order: [[2,'asc']], pageLength: 25, responsive: true });
    }
});
</script>
