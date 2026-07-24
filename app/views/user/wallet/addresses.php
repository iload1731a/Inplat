<?php declare(strict_types=1); ?>
<?php
$addresses  = is_array($addresses  ?? null) ? $addresses  : [];
$currencies = is_array($currencies ?? null) ? $currencies : [];
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="fas fa-shield-alt me-2 text-success"></i>Withdrawal Addresses</h1>
        <p class="text-secondary mb-0">Manage your whitelisted withdrawal addresses. New addresses have a 24-hour security cooldown.</p>
    </div>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAddressModal">
        <i class="fas fa-plus me-1"></i>Add Address
    </button>
</div>

<!-- Security Warning Banner -->
<div class="alert alert-warning d-flex align-items-center gap-3 rounded-4 mb-4">
    <i class="fas fa-exclamation-triangle fa-lg text-warning flex-shrink-0"></i>
    <div class="small">
        <strong>Security Notice:</strong> New addresses are subject to a 24-hour cooldown period before they can be used for withdrawals. 
        Never share your withdrawal addresses with anyone.
    </div>
</div>

<!-- Addresses Table -->
<div class="glass rounded-4 p-4">
    <div class="table-responsive">
        <table id="addressTable" class="table table-user">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Label</th>
                    <th>Currency</th>
                    <th>Address</th>
                    <th>Tag/Memo</th>
                    <th>Status</th>
                    <th>Cooldown Ends</th>
                    <th>Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($addresses as $addr): ?>
                <?php
                $statusColor = match($addr['status'] ?? '') {
                    'active'           => 'success',
                    'pending_cooldown' => 'warning',
                    'revoked'          => 'danger',
                    default            => 'secondary',
                };
                $statusLabel = match($addr['status'] ?? '') {
                    'active'           => 'Active',
                    'pending_cooldown' => 'Cooldown',
                    'revoked'          => 'Revoked',
                    default            => ucfirst((string)($addr['status'] ?? '-')),
                };
                ?>
                <tr>
                    <td class="text-secondary"><?= (int)$addr['id'] ?></td>
                    <td><?= e((string)($addr['label'] ?? '—')) ?></td>
                    <td><span class="badge bg-secondary"><?= e((string)($addr['currency_code'] ?? '-')) ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <code class="small text-info" style="word-break:break-all;max-width:200px">
                                <?= e((string)($addr['address'] ?? '')) ?>
                            </code>
                            <button class="btn btn-xs btn-outline-secondary copy-btn"
                                    data-address="<?= e((string)($addr['address'] ?? '')) ?>" title="Copy">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-secondary small"><?= e((string)($addr['tag_or_memo'] ?? '—')) ?></td>
                    <td><span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span></td>
                    <td class="small text-secondary">
                        <?php if ($addr['status'] === 'pending_cooldown' && !empty($addr['cooldown_ends_at'])): ?>
                            <span class="text-warning">
                                <i class="fas fa-clock me-1"></i>
                                <?= e(substr((string)$addr['cooldown_ends_at'], 0, 16)) ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="small text-secondary"><?= e(substr((string)($addr['created_at'] ?? ''), 0, 10)) ?></td>
                    <td>
                        <?php if (($addr['status'] ?? '') !== 'revoked'): ?>
                        <button class="btn btn-xs btn-outline-danger revoke-btn"
                                data-id="<?= (int)$addr['id'] ?>"
                                data-address="<?= e(substr((string)($addr['address'] ?? ''), 0, 20)) ?>...">
                            <i class="fas fa-trash-alt me-1"></i>Revoke
                        </button>
                        <?php else: ?>
                            <span class="text-secondary small">Revoked</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($addresses === []): ?>
                <tr>
                    <td colspan="9" class="text-center text-secondary py-5">
                        <i class="fas fa-shield-alt fa-2x mb-2 d-block opacity-50"></i>
                        No whitelisted addresses yet.<br>
                        <small>Add an address to get started.</small>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-success"></i>Add Withdrawal Address</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAddressForm">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Currency *</label>
                        <select name="currency_id" class="form-select bg-transparent text-light border-secondary" required>
                            <option value="">— Select Currency —</option>
                            <?php foreach ($currencies as $cur): ?>
                            <option value="<?= (int)$cur['id'] ?>"><?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Address *</label>
                        <input type="text" name="address"
                               class="form-control bg-transparent text-light border-secondary font-monospace"
                               placeholder="Crypto wallet address" required minlength="10" maxlength="191">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Tag / Memo <span class="text-secondary">(XRP, XLM, etc.)</span></label>
                        <input type="text" name="tag_or_memo"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="Optional memo or destination tag" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Label <span class="text-secondary">(optional)</span></label>
                        <input type="text" name="label"
                               class="form-control bg-transparent text-light border-secondary"
                               placeholder="e.g. My Binance wallet" maxlength="100">
                    </div>
                    <div class="alert alert-warning small rounded-3 mb-0">
                        <i class="fas fa-clock me-1"></i>
                        This address will be available after a <strong>24-hour security cooldown</strong>.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="addSpinner"></span>
                        <i class="fas fa-shield-alt me-1"></i>Add Address
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#addressTable').DataTable({ order:[[7,'desc']], pageLength:10 });

// Copy address
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        navigator.clipboard.writeText(btn.dataset.address).then(() => {
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
        });
    });
});

// Add address form
document.getElementById('addAddressForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('addSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/user/wallet/addresses/add', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': data._token},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Address Added!', text: json.message, confirmButtonColor:'#3b82f6' })
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

// Revoke
document.querySelectorAll('.revoke-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            icon: 'warning',
            title: 'Revoke Address?',
            text: `This will revoke: ${btn.dataset.address}`,
            showCancelButton: true,
            confirmButtonText: 'Yes, Revoke',
            confirmButtonColor: '#ef4444',
        });
        if (!confirmed.isConfirmed) return;

        const res  = await fetch('/user/wallet/addresses/revoke', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ _token: '<?= e(\App\Libraries\Csrf::token()) ?>', address_id: parseInt(btn.dataset.id) })
        });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Revoked', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    });
});
</script>
