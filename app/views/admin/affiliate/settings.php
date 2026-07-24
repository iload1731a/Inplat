<?php declare(strict_types=1); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$tiers    = is_array($tiers    ?? null) ? $tiers    : [];
$val      = fn(string $k, string $d = '') => (string)($settings[$k]['value'] ?? $d);
require app_path('app/views/admin/_nav.php');
?>

<form method="post" action="/admin/affiliate/settings">
    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

    <div class="row g-4">
        <!-- General Settings -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4 h-100">
                <h6 class="mb-4"><i class="fas fa-cog me-2 text-secondary"></i>General Settings</h6>

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="program_enabled" value="0">
                    <input type="checkbox" name="program_enabled" value="1" id="programEnabled" class="form-check-input"
                           <?= $val('program_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="programEnabled">Affiliate Program Enabled</label>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Cookie Validity (days)</label>
                    <input type="number" name="cookie_days" value="<?= e($val('cookie_days', '30')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="1" max="365">
                    <div class="form-text text-secondary">How long a referral cookie tracks new registrations.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Max Referral Levels</label>
                    <select name="max_levels" class="form-select bg-transparent text-light border-secondary">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= $val('max_levels', '3') === (string)$i ? 'selected' : '' ?>><?= $i ?> level<?= $i > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Commission Calculated On</label>
                    <select name="commission_on" class="form-select bg-transparent text-light border-secondary">
                        <option value="fee"    <?= $val('commission_on', 'fee') === 'fee'    ? 'selected' : '' ?>>Trading Fee</option>
                        <option value="volume" <?= $val('commission_on') === 'volume' ? 'selected' : '' ?>>Trade Volume</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Terms & Conditions URL</label>
                    <input type="url" name="terms_url" value="<?= e($val('terms_url')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="https://...">
                </div>
            </div>
        </div>

        <!-- Qualification & Payouts -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-user-check me-2 text-success"></i>Qualification & Payouts</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Trades Required to Qualify</label>
                    <input type="number" name="qualification_trades" value="<?= e($val('qualification_trades', '1')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="0">
                    <div class="form-text text-secondary">Minimum trades a referral must make to be "qualified".</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Volume Required to Qualify (USD)</label>
                    <input type="number" name="qualification_volume" value="<?= e($val('qualification_volume', '0')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="0" step="0.01">
                    <div class="form-text text-secondary">Set to 0 to disable volume requirement.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Minimum Payout Amount</label>
                    <input type="number" name="min_payout_amount" value="<?= e($val('min_payout_amount', '10')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="0" step="0.01">
                </div>

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="payout_auto_approve" value="0">
                    <input type="checkbox" name="payout_auto_approve" value="1" id="autoApprove" class="form-check-input"
                           <?= $val('payout_auto_approve') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="autoApprove">Auto-approve Payouts Under Threshold</label>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Auto-approve Threshold</label>
                    <input type="number" name="payout_auto_threshold" value="<?= e($val('payout_auto_threshold', '100')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="0" step="0.01">
                </div>
            </div>

            <!-- Signup Bonus -->
            <div class="glass rounded-4 p-4 mt-4">
                <h6 class="mb-4"><i class="fas fa-star me-2 text-warning"></i>Signup Bonus</h6>

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="signup_bonus_enabled" value="0">
                    <input type="checkbox" name="signup_bonus_enabled" value="1" id="signupBonusEnabled" class="form-check-input"
                           <?= $val('signup_bonus_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="signupBonusEnabled">Enable Signup Bonus</label>
                </div>

                <div class="row g-3">
                    <div class="col-7">
                        <label class="form-label small text-secondary">Bonus Amount</label>
                        <input type="number" name="signup_bonus_amount" value="<?= e($val('signup_bonus_amount', '5')) ?>"
                               class="form-control bg-transparent text-light border-secondary" step="0.01" min="0">
                    </div>
                    <div class="col-5">
                        <label class="form-label small text-secondary">Currency</label>
                        <input type="text" name="signup_bonus_currency" value="<?= e($val('signup_bonus_currency', 'USDT')) ?>"
                               class="form-control bg-transparent text-light border-secondary" maxlength="10">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-3">
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Settings</button>
        <a href="/admin/affiliate" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>
</form>
