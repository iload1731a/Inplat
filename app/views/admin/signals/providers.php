<?php declare(strict_types=1);
$providers   = (array)($providers ?? []);
$performance = (array)($performance ?? []);
$csrfToken   = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- Actions bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-satellite-dish me-2 text-warning"></i>Signal Providers</h5>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary"
                onclick="recalcPerf(this)">
            <i class="fas fa-sync me-1"></i>Recalculate Performance
        </button>
        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#createProviderModal">
            <i class="fas fa-plus me-1"></i>New Provider
        </button>
    </div>
</div>

<!-- Providers Grid -->
<?php if (empty($providers)): ?>
<div class="glass rounded-3 p-5 text-center text-secondary">
    <i class="fas fa-satellite-dish fa-3x mb-3 d-block"></i>
    No signal providers yet.
</div>
<?php else: ?>
<div class="row g-3 mb-4">
    <?php foreach ($providers as $p):
        $pid  = (int)$p['id'];
        $p30  = $performance[$pid]['30d'] ?? [];
        $pAll = $performance[$pid]['all'] ?? [];
        $wr   = (float)($p30['win_rate'] ?? 0);
        $wrc  = $wr >= 60 ? 'success' : ($wr >= 40 ? 'warning' : 'danger');
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass rounded-3 h-100">
            <div class="p-3 border-bottom border-secondary d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar" style="width:36px;height:36px;font-size:.8rem;border-radius:6px">
                        <?= e(mb_strtoupper(mb_substr($p['name'], 0, 2))) ?>
                    </div>
                    <div>
                        <div class="fw-semibold small"><?= e($p['name']) ?></div>
                        <div class="text-secondary" style="font-size:.7rem"><?= e($p['provider_type']) ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $p['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span>
                    <span class="badge <?= $p['is_public'] ? 'bg-info' : 'bg-dark border border-secondary' ?>"><?= $p['is_public'] ? 'Public' : 'Private' ?></span>
                </div>
            </div>
            <div class="p-3">
                <div class="row g-2 text-center mb-3">
                    <div class="col-3">
                        <div class="text-secondary" style="font-size:.6rem">WIN RATE</div>
                        <div class="fw-bold small text-<?= $wrc ?>"><?= number_format($wr, 1) ?>%</div>
                    </div>
                    <div class="col-3">
                        <div class="text-secondary" style="font-size:.6rem">SIGNALS</div>
                        <div class="fw-bold small"><?= (int)($p30['total_signals'] ?? $p['total_signals'] ?? 0) ?></div>
                    </div>
                    <div class="col-3">
                        <div class="text-secondary" style="font-size:.6rem">SUBS</div>
                        <div class="fw-bold small"><?= (int)($p['subscriber_count'] ?? 0) ?></div>
                    </div>
                    <div class="col-3">
                        <div class="text-secondary" style="font-size:.6rem">RETURN</div>
                        <div class="fw-bold small <?= ($pAll['total_return_pct'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= isset($pAll['total_return_pct']) ? (($pAll['total_return_pct'] >= 0 ? '+' : '') . number_format((float)$pAll['total_return_pct'], 1) . '%') : '—' ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-xs btn-outline-light flex-grow-1"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                    <button class="btn btn-xs <?= $p['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                            onclick="toggleProvider(<?= $pid ?>)">
                        <i class="fas fa-<?= $p['is_active'] ? 'pause' : 'play' ?>"></i>
                    </button>
                    <a href="/admin/signals/list?provider_id=<?= $pid ?>" class="btn btn-xs btn-outline-info">
                        <i class="fas fa-chart-bar"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Provider Modal -->
<div class="modal fade" id="createProviderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Create Signal Provider</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createProviderForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Type</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="provider_type">
                                <option value="internal">Internal</option>
                                <option value="bot">Bot / Algorithm</option>
                                <option value="third_party">Third Party</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Description</label>
                            <textarea class="form-control form-control-sm bg-dark text-light border-secondary" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Logo URL</label>
                            <input type="url" class="form-control form-control-sm bg-dark text-light border-secondary" name="logo_url">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Website URL</label>
                            <input type="url" class="form-control form-control-sm bg-dark text-light border-secondary" name="website_url">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Subscription Price (0 = free)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-dark text-light border-secondary" name="subscription_price" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Subscription Currency</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" name="subscription_currency" placeholder="USDT">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="createActive" checked>
                                <label class="form-check-label small" for="createActive">Active</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_public" value="1" id="createPublic" checked>
                                <label class="form-check-label small" for="createPublic">Public</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" onclick="submitCreateProvider()">Create Provider</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Provider Modal -->
<div class="modal fade" id="editProviderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Provider</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProviderForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="provider_id" id="editProviderId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Name</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" name="name" id="editName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Type</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary" name="provider_type" id="editType">
                                <option value="internal">Internal</option>
                                <option value="bot">Bot / Algorithm</option>
                                <option value="third_party">Third Party</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Description</label>
                            <textarea class="form-control form-control-sm bg-dark text-light border-secondary" name="description" id="editDesc" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Logo URL</label>
                            <input type="url" class="form-control form-control-sm bg-dark text-light border-secondary" name="logo_url" id="editLogo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Website URL</label>
                            <input type="url" class="form-control form-control-sm bg-dark text-light border-secondary" name="website_url" id="editWebsite">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Subscription Price</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-dark text-light border-secondary" name="subscription_price" id="editPrice">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Currency</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" name="subscription_currency" id="editCurrency">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="editActive">
                                <label class="form-check-label small" for="editActive">Active</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_public" value="1" id="editPublic">
                                <label class="form-check-label small" for="editPublic">Public</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" onclick="submitEditProvider()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function recalcPerf(btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Recalculating…';
    fetch('/admin/signals/providers/recalc', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) location.reload();
        else { alert(d.message || 'Error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync me-1"></i>Recalculate Performance'; }
    });
}

function toggleProvider(id) {
    fetch('/admin/signals/providers/toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&provider_id=${id}`
    })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert(d.message || 'Error'); });
}

function submitCreateProvider() {
    const form = document.getElementById('createProviderForm');
    const data = new URLSearchParams(new FormData(form)).toString();
    fetch('/admin/signals/providers/create', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('createProviderModal'))?.hide();
            location.reload();
        } else alert(d.message || 'Error');
    });
}

function openEditModal(provider) {
    document.getElementById('editProviderId').value  = provider.id;
    document.getElementById('editName').value        = provider.name || '';
    document.getElementById('editType').value        = provider.provider_type || 'internal';
    document.getElementById('editDesc').value        = provider.description || '';
    document.getElementById('editLogo').value        = provider.logo_url || '';
    document.getElementById('editWebsite').value     = provider.website_url || '';
    document.getElementById('editPrice').value       = provider.subscription_price || 0;
    document.getElementById('editCurrency').value    = provider.subscription_currency || '';
    document.getElementById('editActive').checked    = !!parseInt(provider.is_active);
    document.getElementById('editPublic').checked    = !!parseInt(provider.is_public);
    new bootstrap.Modal(document.getElementById('editProviderModal')).show();
}

function submitEditProvider() {
    const form = document.getElementById('editProviderForm');
    const data = new URLSearchParams(new FormData(form)).toString();
    fetch('/admin/signals/providers/update', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('editProviderModal'))?.hide();
            location.reload();
        } else alert(d.message || 'Error');
    });
}
</script>
