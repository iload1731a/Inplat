<?php declare(strict_types=1); ?>
<?php
$apiKeys = is_array($apiKeys ?? null) ? $apiKeys : [];
require app_path('app/views/user/_nav.php');
?>

<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="fas fa-key me-2 text-warning"></i>API Keys</h5>
        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#createKeyModal">
            <i class="fas fa-plus me-1"></i>Create New Key
        </button>
    </div>

    <div class="alert alert-secondary small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Keep your API secret safe — it is only shown once during creation. Never share your API credentials with anyone.
    </div>

    <div class="alert alert-info small">
        For trading bots: create a key, enable only required permissions (Read/Trade), and request live market feed activation through a support ticket to the admin team.
    </div>

    <div class="table-responsive">
        <table id="apiKeysTable" class="table table-user table-sm">
            <thead>
                <tr><th>Label</th><th>API Key</th><th>Permissions</th><th>IP Whitelist</th><th>Status</th><th>Last Used</th><th>Expires</th><th>Created</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($apiKeys as $key): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)($key['label'] ?? '-')) ?></td>
                    <td class="font-monospace small"><?= e(substr((string)($key['api_key'] ?? ''), 0, 16)) ?>…</td>
                    <td>
                        <?php foreach (explode(',', (string)($key['permissions'] ?? '')) as $perm): ?>
                            <?php if (trim($perm) !== ''): ?>
                            <span class="badge bg-<?= $perm === 'withdraw' ? 'danger' : ($perm === 'trade' ? 'warning text-dark' : 'info') ?> me-1">
                                <?= e(trim($perm)) ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                    <td class="font-monospace small text-secondary">
                        <?php if (!empty($key['ip_whitelist'])): ?>
                            <?= e(substr((string)$key['ip_whitelist'], 0, 20)) ?>
                        <?php else: ?>
                            <span class="text-secondary">Any</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= ($key['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= ($key['is_active'] ?? 0) ? 'Active' : 'Inactive' ?></span></td>
                    <td class="small text-secondary"><?= !empty($key['last_used_at']) ? e(date('M d, Y', strtotime((string)$key['last_used_at']))) : 'Never' ?></td>
                    <td class="small text-secondary"><?= !empty($key['expires_at']) ? e(date('M d, Y', strtotime((string)$key['expires_at']))) : 'No expiry' ?></td>
                    <td class="small"><?= e(date('M d, Y', strtotime((string)($key['created_at'] ?? 'now')))) ?></td>
                    <td>
                        <button class="btn btn-xs btn-outline-danger btn-revoke-key" data-key-id="<?= (int)$key['id'] ?>" data-key-label="<?= e((string)$key['label']) ?>">
                            <i class="fas fa-trash me-1"></i>Revoke
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($apiKeys === []): ?>
                <tr><td colspan="9" class="text-center text-secondary py-4">
                    <i class="fas fa-key fa-2x d-block mb-2"></i>No API keys yet. Create your first key.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Key Modal -->
<div class="modal fade" id="createKeyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create API Key</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createKeyForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" class="form-control bg-transparent text-light border-secondary"
                               placeholder="e.g. My Trading Bot" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Permissions <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="read" id="permRead" checked>
                                <label class="form-check-label" for="permRead">
                                    <span class="badge bg-info">Read</span> — View balances, orders, trades
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="trade" id="permTrade">
                                <label class="form-check-label" for="permTrade">
                                    <span class="badge bg-warning text-dark">Trade</span> — Place and cancel orders
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="withdraw" id="permWithdraw">
                                <label class="form-check-label" for="permWithdraw">
                                    <span class="badge bg-danger">Withdraw</span> — Initiate withdrawals
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">IP Whitelist <span class="text-secondary">(optional)</span></label>
                        <input type="text" name="ip_whitelist" class="form-control bg-transparent text-light border-secondary font-monospace"
                               placeholder="192.168.1.1,10.0.0.1 (comma-separated)">
                        <div class="form-text text-secondary">Leave blank to allow all IPs. Comma-separate multiple IPs.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Expiry Date <span class="text-secondary">(optional)</span></label>
                        <input type="date" name="expires_at" class="form-control bg-transparent text-light border-secondary"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        <div class="form-text text-secondary">Leave blank for no expiry.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="createKeyBtn">
                        <i class="fas fa-key me-1"></i>Generate Key
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal (shows secret) -->
<div class="modal fade" id="keyCreatedModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success"><i class="fas fa-check-circle me-2"></i>API Key Created</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Save the secret now!</strong> It will NOT be shown again.
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">API Key</label>
                    <div class="input-group">
                        <input type="text" id="newApiKey" class="form-control bg-transparent text-light border-secondary font-monospace" readonly>
                        <button class="btn btn-outline-secondary btn-copy-val" data-target="newApiKey"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">API Secret</label>
                    <div class="input-group">
                        <input type="text" id="newApiSecret" class="form-control bg-transparent text-light border-secondary font-monospace" readonly>
                        <button class="btn btn-outline-secondary btn-copy-val" data-target="newApiSecret"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-success" id="btnConfirmKeyCreated">I have saved my credentials</button>
            </div>
        </div>
    </div>
</div>

<script>
$('#apiKeysTable').DataTable({ order: [[7,'desc']], pageLength: 10 });

$('#createKeyForm').on('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('createKeyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating...';
    const fd = new FormData(this);
    const data = { _token: fd.get('_token'), label: fd.get('label'), ip_whitelist: fd.get('ip_whitelist'), expires_at: fd.get('expires_at'), 'permissions[]': [] };
    for (const [k, v] of fd.entries()) { if (k === 'permissions[]') data['permissions[]'].push(v); }

    $.ajax({
        url: '/user/api-keys/create',
        method: 'POST',
        data: data,
        traditional: true,
        success(r) {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-key me-1"></i>Generate Key';
            if (r.ok) {
                bootstrap.Modal.getInstance(document.getElementById('createKeyModal'))?.hide();
                document.getElementById('newApiKey').value    = r.api_key;
                document.getElementById('newApiSecret').value = r.api_secret;
                new bootstrap.Modal(document.getElementById('keyCreatedModal')).show();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message });
            }
        },
        error(xhr) {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-key me-1"></i>Generate Key';
            const p = xhr.responseJSON || {};
            Swal.fire({ icon: 'error', text: p.message || 'Request failed' });
        }
    });
});

$('#btnConfirmKeyCreated').on('click', function () {
    bootstrap.Modal.getInstance(document.getElementById('keyCreatedModal'))?.hide();
    location.reload();
});

$(document).on('click', '.btn-copy-val', function () {
    const targetId = $(this).data('target');
    const val = document.getElementById(targetId)?.value || '';
    navigator.clipboard.writeText(val)
        .then(() => Swal.fire({ icon: 'success', title: 'Copied!', timer: 800, showConfirmButton: false }));
});

$(document).on('click', '.btn-revoke-key', function () {
    const keyId = $(this).data('key-id');
    const label = $(this).data('key-label');
    Swal.fire({
        title: 'Revoke "' + label + '"?',
        text: 'This key will be permanently revoked and cannot be recovered.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Revoke Key'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/user/api-keys/revoke', { _token: csrfToken, key_id: keyId }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Revoked', timer: 1200, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', text: res.message });
            }
        }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
    });
});
</script>
