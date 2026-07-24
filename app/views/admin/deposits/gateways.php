<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$csrf       = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/admin/deposits" class="text-secondary text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Back to Deposits
        </a>
        <h1 class="h3 mb-0 mt-1"><i class="fas fa-cogs me-2 text-secondary"></i>Deposit Gateways</h1>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="glass rounded-4 p-4">
    <p class="text-secondary small mb-3">
        <i class="fas fa-info-circle me-1"></i>
        Enable or disable deposits per currency, set required blockchain confirmations and network details.
    </p>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Currency</th><th>Type</th><th>Network</th>
                    <th class="text-center">Deposit Enabled</th>
                    <th class="text-center">Confirmations</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($currencies as $cur): ?>
            <tr>
                <td>
                    <span class="fw-bold"><?= e((string)($cur['code'] ?? '')) ?></span>
                    <span class="text-secondary small ms-2"><?= e((string)($cur['name'] ?? '')) ?></span>
                </td>
                <td>
                    <span class="badge bg-<?= ($cur['type'] ?? '') === 'crypto' ? 'warning text-dark' : 'info' ?>">
                        <?= ucfirst((string)($cur['type'] ?? '')) ?>
                    </span>
                </td>
                <td class="text-secondary small"><?= !empty($cur['network']) ? e((string)$cur['network']) : '—' ?></td>
                <td class="text-center">
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input type="checkbox" class="form-check-input gateway-toggle"
                               data-id="<?= (int)$cur['id'] ?>"
                               <?= (int)($cur['is_deposit_enabled'] ?? 0) ? 'checked' : '' ?>
                               role="switch">
                    </div>
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm text-center bg-transparent text-light border-secondary confirms-input"
                           data-id="<?= (int)$cur['id'] ?>"
                           value="<?= (int)($cur['confirmations_required'] ?? 1) ?>"
                           min="1" max="100" style="width:80px;margin:auto">
                </td>
                <td class="text-end">
                    <button class="btn btn-outline-primary btn-xs py-0 px-2 save-gateway-btn"
                            data-id="<?= (int)$cur['id'] ?>"
                            data-network="<?= e((string)($cur['network'] ?? '')) ?>">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($currencies)): ?>
            <tr><td colspan="6" class="text-center text-secondary py-4">No currencies configured.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const csrf = <?= json_encode($csrf) ?>;

    document.querySelectorAll('.save-gateway-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id       = btn.dataset.id;
            const row      = btn.closest('tr');
            const enabled  = row.querySelector('.gateway-toggle').checked ? 1 : 0;
            const confirms = parseInt(row.querySelector('.confirms-input').value) || 1;
            const network  = btn.dataset.network;

            fetch('/admin/deposits/gateways/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    _token: csrf,
                    currency_id: id,
                    is_deposit_enabled: enabled,
                    confirmations_required: confirms,
                    network: network,
                }),
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false });
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }));
        });
    });
})();
</script>
