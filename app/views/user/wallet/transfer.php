<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$wallets    = is_array($wallets    ?? null) ? $wallets    : [];
$transfers  = is_array($transfers  ?? null) ? $transfers  : [];
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="fas fa-exchange-alt me-2 text-info"></i>Transfer Funds</h1>
        <p class="text-secondary mb-0">Send funds to another user or move between your wallet types.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Transfer Form -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <!-- Tab Selector -->
            <ul class="nav nav-pills mb-4 gap-2" id="transferTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-type="user">
                        <i class="fas fa-user me-1"></i>User Transfer
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-type="internal">
                        <i class="fas fa-arrows-alt-h me-1"></i>Internal Transfer
                    </button>
                </li>
            </ul>

            <!-- User Transfer Form -->
            <form id="transferForm" style="display:block">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="transfer_type" id="transferType" value="user">

                <!-- User to User -->
                <div id="userTransferFields">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Recipient Email</label>
                        <div class="input-group">
                            <span class="input-group-text border-secondary bg-transparent text-secondary"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="recipient_email" id="recipientEmail"
                                   class="form-control bg-transparent text-light border-secondary"
                                   placeholder="recipient@example.com">
                        </div>
                    </div>
                </div>

                <!-- Internal (between wallet types) -->
                <div id="internalTransferFields" style="display:none">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">From Wallet Type</label>
                        <select name="from_wallet_type" class="form-select bg-transparent text-light border-secondary">
                            <option value="spot">Spot</option>
                            <option value="margin">Margin</option>
                            <option value="futures">Futures</option>
                            <option value="funding">Funding</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">To Wallet Type</label>
                        <select name="to_wallet_type" class="form-select bg-transparent text-light border-secondary">
                            <option value="futures">Futures</option>
                            <option value="spot">Spot</option>
                            <option value="margin">Margin</option>
                            <option value="funding">Funding</option>
                        </select>
                    </div>
                </div>

                <!-- Common fields -->
                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency</label>
                    <select name="currency_id" id="currencySelect"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>"
                                data-balance="<?php
                                    foreach ($wallets as $w) {
                                        if ((int)$w['currency_id'] === (int)$cur['id'] && $w['wallet_type'] === 'spot') {
                                            echo number_format((float)$w['available_balance'], 8);
                                            break;
                                        }
                                    }
                                ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="amountInput"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00000000" required>
                        <span class="input-group-text border-secondary bg-transparent text-secondary" id="currencyLabel">—</span>
                    </div>
                    <div class="form-text text-secondary" id="availableBalance"></div>
                </div>
                <div class="mb-4" id="noteField">
                    <label class="form-label text-secondary small">Note <span class="text-secondary">(optional)</span></label>
                    <input type="text" name="note" class="form-control bg-transparent text-light border-secondary"
                           placeholder="Optional memo or note" maxlength="255">
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="transferSpinner"></span>
                    <i class="fas fa-paper-plane me-2"></i>Send Transfer
                </button>
            </form>
        </div>

        <!-- Info Card -->
        <div class="glass rounded-4 p-3 mt-3">
            <h6 class="mb-2 text-secondary"><i class="fas fa-info-circle me-1"></i>Transfer Info</h6>
            <ul class="list-unstyled mb-0 small text-secondary">
                <li class="mb-1"><i class="fas fa-check text-success me-1"></i>User-to-user transfers are instant and free</li>
                <li class="mb-1"><i class="fas fa-check text-success me-1"></i>Internal transfers between your wallet types are instant</li>
                <li class="mb-1"><i class="fas fa-exclamation-triangle text-warning me-1"></i>Transfers are irreversible — double-check the recipient</li>
                <li><i class="fas fa-lock text-info me-1"></i>Frozen wallets cannot send or receive transfers</li>
            </ul>
        </div>
    </div>

    <!-- Transfer History -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2 text-purple"></i>Transfer History</h5>
            <div class="table-responsive">
                <table id="transferTable" class="table table-user table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Currency</th>
                            <th>Amount</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Note</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transfers as $t):
                        $userId = (int)(\App\Libraries\Session::get('auth.user_id') ?? 0);
                        $isSender = (int)$t['from_user_id'] === $userId;
                    ?>
                        <tr>
                            <td class="text-secondary"><?= (int)$t['id'] ?></td>
                            <td>
                                <span class="badge bg-<?= $isSender ? 'danger' : 'success' ?>">
                                    <i class="fas fa-arrow-<?= $isSender ? 'up' : 'down' ?> me-1"></i>
                                    <?= $isSender ? 'Sent' : 'Received' ?>
                                </span>
                            </td>
                            <td><span class="badge bg-secondary"><?= e((string)($t['currency_code'] ?? '-')) ?></span></td>
                            <td class="font-monospace"><?= number_format((float)($t['amount'] ?? 0), 8) ?></td>
                            <td class="small"><?= e((string)($t['from_username'] ?? '-')) ?></td>
                            <td class="small"><?= e((string)($t['to_username']   ?? '-')) ?></td>
                            <td class="text-secondary small"><?= e((string)($t['note'] ?? '—')) ?></td>
                            <td class="small text-secondary"><?= e(substr((string)($t['created_at'] ?? ''), 0, 16)) ?></td>
                            <td>
                                <span class="badge bg-<?= ($t['status'] ?? '') === 'completed' ? 'success' : 'warning' ?>">
                                    <?= e((string)($t['status'] ?? '-')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($transfers === []): ?>
                        <tr><td colspan="9" class="text-center text-secondary py-4">No transfers yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('#transferTabs .nav-link').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#transferTabs .nav-link').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const type = btn.dataset.type;
        document.getElementById('transferType').value = type;
        document.getElementById('userTransferFields').style.display     = type === 'user'     ? '' : 'none';
        document.getElementById('internalTransferFields').style.display = type === 'internal' ? '' : 'none';
        document.getElementById('noteField').style.display              = type === 'user'     ? '' : 'none';
    });
});

// Currency label + balance
document.getElementById('currencySelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('currencyLabel').textContent     = opt.dataset.code || '—';
    const bal = opt.dataset.balance || '0.00000000';
    document.getElementById('availableBalance').textContent  = `Available: ${bal} ${opt.dataset.code || ''}`;
});

// Transfer form
document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const spinner = document.getElementById('transferSpinner');
    spinner.classList.remove('d-none');
    const data = Object.fromEntries(new FormData(this));
    try {
        const res  = await fetch('/user/wallet/transfer', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token': data._token}, body: JSON.stringify(data) });
        const json = await res.json();
        if (json.ok) {
            Swal.fire({ icon:'success', title:'Transfer Complete!', text: json.message, confirmButtonColor:'#3b82f6' })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Transfer Failed', text: json.message });
        }
    } catch(err) {
        Swal.fire({ icon:'error', title:'Error', text: 'Network error. Please try again.' });
    } finally {
        spinner.classList.add('d-none');
    }
});

$('#transferTable').DataTable({ order:[[0,'desc']], pageLength:10 });
</script>
