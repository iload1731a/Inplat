<?php declare(strict_types=1); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-wallet me-2 text-warning"></i>Wallet Configuration</h1>
        <p class="text-secondary mb-0">Control deposit/withdrawal operations, limits, thresholds, and cold storage policy.</p>
    </div>
</div>

<form method="post" action="/admin/settings/wallet" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <!-- Toggles -->
        <div class="col-lg-5">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-toggle-on me-2 text-secondary"></i>Operation Toggles</h6>
                <?php
                $toggles = [
                    ['deposit_enabled',    'Deposits Enabled',    'Allow new deposits from all users.'],
                    ['withdrawal_enabled', 'Withdrawals Enabled', 'Allow withdrawal requests from all users.'],
                ];
                foreach ($toggles as [$key, $label, $desc]):
                ?>
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle mb-2">
                    <div>
                        <div class="fw-semibold"><?= e($label) ?></div>
                        <div class="small text-secondary"><?= e($desc) ?></div>
                    </div>
                    <div class="form-check form-switch mb-0 ms-3">
                        <input type="hidden" name="<?= e($key) ?>" value="false">
                        <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="true"
                               <?= $s($key, 'true') === 'true' ? 'checked' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Thresholds & Limits -->
        <div class="col-lg-7">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-balance-scale me-2 text-secondary"></i>Thresholds & Limits</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Auto-Approve Deposit Below (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text border-secondary text-secondary">$</span>
                            <input type="number" name="auto_approve_deposit_usd" value="<?= e($s('auto_approve_deposit_usd', '1000')) ?>"
                                   class="form-control bg-transparent text-light border-secondary" min="0" step="0.01">
                        </div>
                        <div class="form-text text-secondary">Deposits below this amount are auto-approved.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Minimum Withdrawal (USD equiv.)</label>
                        <div class="input-group">
                            <span class="input-group-text border-secondary text-secondary">$</span>
                            <input type="number" name="min_withdrawal_usd" value="<?= e($s('min_withdrawal_usd', '10')) ?>"
                                   class="form-control bg-transparent text-light border-secondary" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Daily Withdrawal Limit per User (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text border-secondary text-secondary">$</span>
                            <input type="number" name="daily_withdrawal_limit_usd" value="<?= e($s('daily_withdrawal_limit_usd', '50000')) ?>"
                                   class="form-control bg-transparent text-light border-secondary" min="0" step="0.01">
                        </div>
                        <div class="form-text text-secondary">Default limit; override per user in User Management.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Withdrawal Review SLA (hours)</label>
                        <input type="number" name="withdrawal_review_hours" value="<?= e($s('withdrawal_review_hours', '24')) ?>"
                               class="form-control bg-transparent text-light border-secondary" min="1" max="720">
                        <div class="form-text text-secondary">Target hours for manual withdrawal review.</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small text-secondary">Cold Storage Percentage (%)</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="range" name="cold_wallet_threshold_pct" id="coldRange"
                                   value="<?= e($s('cold_wallet_threshold_pct', '80')) ?>"
                                   class="form-range flex-grow-1" min="0" max="100" step="5"
                                   oninput="document.getElementById('coldRangeVal').textContent = this.value + '%'">
                            <span class="text-warning fw-semibold" id="coldRangeVal"><?= e($s('cold_wallet_threshold_pct', '80')) ?>%</span>
                        </div>
                        <div class="form-text text-secondary">Target percentage of platform funds held in cold storage.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-1"></i>Save Wallet Configuration</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>
