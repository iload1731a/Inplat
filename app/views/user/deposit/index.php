<?php declare(strict_types=1); ?>
<?php
$currencies = is_array($currencies ?? null) ? $currencies : [];
$deposits   = is_array($deposits   ?? null) ? $deposits   : [];
$stats      = is_array($stats      ?? null) ? $stats      : [];
$filters    = is_array($filters    ?? null) ? $filters    : [];
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h4 fw-bold mb-0"><i class="fas fa-arrow-circle-down me-2 text-success"></i>Deposit Funds</h1>
    <div class="d-flex gap-2">
        <a href="/user/deposit/fiat" class="btn btn-outline-info btn-sm">
            <i class="fas fa-university me-1"></i>Fiat / Bank Transfer
        </a>
        <a href="/user/deposit/report" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-chart-bar me-1"></i>Reports
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php
    $statItems = [
        ['Total Deposits',  (int)($stats['total']              ?? 0), 'secondary'],
        ['Pending',         (int)($stats['pending']            ?? 0), 'warning'],
        ['Credited',        (int)($stats['credited']           ?? 0), 'success'],
        ['Failed/Flagged',  ((int)($stats['failed'] ?? 0) + (int)($stats['flagged'] ?? 0)), 'danger'],
    ];
    foreach ($statItems as [$lbl, $val, $col]):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($lbl) ?></div>
            <div class="h5 mb-0 fw-bold text-<?= $col ?>"><?= number_format($val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-sm-6 col-lg-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Total Credited</div>
            <div class="h6 mb-0 fw-bold text-success font-monospace">
                <?= number_format((float)($stats['total_credited_amount'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ── Crypto Deposit Form ── -->
    <div class="col-lg-5">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3 fw-bold"><i class="fas fa-coins me-2 text-warning"></i>Crypto Deposit</h5>
            <form id="cryptoDepositForm">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

                <div class="mb-3">
                    <label class="form-label text-secondary small">Currency *</label>
                    <select name="currency_id" id="cryptoCurrencySelect"
                            class="form-select bg-transparent text-light border-secondary" required>
                        <option value="">— Select Currency —</option>
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>"
                                data-code="<?= e((string)$cur['code']) ?>"
                                data-network="<?= e((string)($cur['network'] ?? '')) ?>"
                                data-confirms="<?= (int)($cur['confirmations_required'] ?? 1) ?>">
                            <?= e((string)$cur['code']) ?> — <?= e((string)$cur['name']) ?>
                            <?php if (!empty($cur['network'])): ?>
                                <span class="text-secondary">(<?= e((string)$cur['network']) ?>)</span>
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Network info (shown after currency select) -->
                <div id="networkInfoBox" class="alert alert-dark small rounded-3 mb-3 d-none">
                    <i class="fas fa-network-wired me-1 text-info"></i>
                    <span id="networkInfoText"></span>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Amount *</label>
                    <div class="input-group">
                        <input type="number" name="amount" id="cryptoAmount"
                               class="form-control bg-transparent text-light border-secondary"
                               step="0.00000001" min="0.00000001" placeholder="0.00000000" required>
                        <span class="input-group-text border-secondary text-secondary bg-transparent" id="cryptoAmtCode">—</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Transaction Hash / TxID</label>
                    <input type="text" name="tx_hash"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Blockchain transaction hash" maxlength="191">
                    <div class="form-text text-secondary">Optional — helps our team verify faster.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small">Sender Address</label>
                    <input type="text" name="from_address"
                           class="form-control bg-transparent text-light border-secondary font-monospace"
                           placeholder="Address you are sending from" maxlength="191">
                </div>

                <div class="alert alert-info small rounded-3 mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Submit your deposit request and our team will review and credit your wallet within 1–24 hours.
                </div>

                <button type="submit" class="btn btn-success w-100 fw-semibold" id="cryptoDepositBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="cryptoDepositSpinner"></span>
                    <i class="fas fa-paper-plane me-1"></i>Submit Deposit Request
                </button>
            </form>
        </div>
    </div>

    <!-- ── Deposit History ── -->
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Deposit History</h5>
                <!-- Quick filter -->
                <form class="d-flex gap-2" method="get" action="/user/deposit">
                    <select class="form-select form-select-sm bg-transparent text-light border-secondary" name="status">
                        <option value="">All</option>
                        <?php foreach (['pending','confirmed','credited','failed','flagged'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fas fa-filter"></i></button>
                    <?php if (!empty(array_filter($filters))): ?>
                    <a href="/user/deposit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table id="userDepositTable" class="table table-user table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Currency</th><th>Amount</th>
                            <th>TxHash</th><th>Status</th><th>Date</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deposits as $dep): ?>
                        <?php
                        $ds  = (string)($dep['status'] ?? 'pending');
                        $dc  = match($ds) {
                            'credited'  => 'success',
                            'confirmed' => 'info',
                            'failed'    => 'danger',
                            'flagged'   => 'warning',
                            default     => 'secondary',
                        };
                        ?>
                        <tr>
                            <td class="small text-secondary"><?= (int)$dep['id'] ?></td>
                            <td>
                                <span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span>
                                <?php if (!empty($dep['network'])): ?>
                                <span class="badge bg-dark ms-1" style="font-size:.65rem"><?= e((string)$dep['network']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace small"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                            <td class="font-monospace small text-secondary">
                                <?php if (!empty($dep['tx_hash'])): ?>
                                    <span title="<?= e((string)$dep['tx_hash']) ?>"><?= e(substr((string)$dep['tx_hash'], 0, 16)) ?>…</span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $dc ?>"><?= ucfirst($ds) ?></span>
                                <?php if (!empty($dep['flagged_reason'])): ?>
                                    <i class="fas fa-exclamation-triangle text-warning ms-1" title="<?= e((string)$dep['flagged_reason']) ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?= e(substr((string)($dep['created_at'] ?? ''), 0, 16)) ?></td>
                            <td>
                                <?php if ($ds === 'pending'): ?>
                                <button class="btn btn-outline-danger btn-xs py-0 px-2 cancel-deposit-btn"
                                        data-id="<?= (int)$dep['id'] ?>" title="Cancel">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($deposits)): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No deposits yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = <?= json_encode(\App\Libraries\Csrf::token()) ?>;

    // Currency select → update amount code + network info
    document.getElementById('cryptoCurrencySelect').addEventListener('change', function () {
        const opt      = this.options[this.selectedIndex];
        const code     = opt.dataset.code     || '—';
        const network  = opt.dataset.network  || '';
        const confirms = opt.dataset.confirms || '1';
        document.getElementById('cryptoAmtCode').textContent = code;
        const box  = document.getElementById('networkInfoBox');
        const text = document.getElementById('networkInfoText');
        if (network) {
            text.textContent = `Network: ${network} — Required confirmations: ${confirms}`;
            box.classList.remove('d-none');
        } else {
            box.classList.add('d-none');
        }
    });

    // Submit crypto deposit
    document.getElementById('cryptoDepositForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn  = document.getElementById('cryptoDepositBtn');
        const spin = document.getElementById('cryptoDepositSpinner');
        btn.disabled = true; spin.classList.remove('d-none');

        const fd   = new FormData(this);
        const data = Object.fromEntries(fd.entries());

        fetch('/user/deposit/submit-crypto', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({
                icon: res.ok ? 'success' : 'error',
                title: res.ok ? 'Deposit Submitted' : 'Error',
                text: res.message,
                timer: res.ok ? 3000 : undefined,
                showConfirmButton: !res.ok,
            }).then(() => { if (res.ok) location.reload(); });
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }))
        .finally(() => { btn.disabled = false; spin.classList.add('d-none'); });
    });

    // Cancel deposit
    document.querySelectorAll('.cancel-deposit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            Swal.fire({
                icon: 'question', title: 'Cancel this deposit?',
                showCancelButton: true, confirmButtonText: 'Yes, Cancel',
            }).then(res => {
                if (!res.isConfirmed) return;
                fetch('/user/deposit/cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ _token: csrf, deposit_id: id }),
                })
                .then(r => r.json())
                .then(data => {
                    Swal.fire({ icon: data.ok ? 'success' : 'error', title: data.message, timer: 2000, showConfirmButton: false })
                        .then(() => { if (data.ok) location.reload(); });
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Request failed' }));
            });
        });
    });

    // DataTables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#userDepositTable').DataTable({ order: [[0, 'desc']], pageLength: 15 });
    }
})();
</script>
