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
        <h1 class="h4 mb-1"><i class="fas fa-building me-2 text-warning"></i>Company Settings</h1>
        <p class="text-secondary mb-0">Legal entity information, registration details, and contact data.</p>
    </div>
</div>

<form method="post" action="/admin/settings/company" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-briefcase me-2 text-secondary"></i>Legal Information</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Company Name</label>
                    <input type="text" name="company_name" value="<?= e($s('company_name')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="200" placeholder="Acme Trading Ltd.">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Registration Number</label>
                    <input type="text" name="company_registration" value="<?= e($s('company_registration')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="100" placeholder="Company registration or tax ID">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">VAT / GST Number</label>
                    <input type="text" name="company_vat_number" value="<?= e($s('company_vat_number')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="50" placeholder="e.g. GB123456789">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Office Phone</label>
                    <input type="text" name="company_phone" value="<?= e($s('company_phone')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="50" placeholder="+1 (555) 000-0000">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Company Email</label>
                    <input type="email" name="company_email" value="<?= e($s('company_email')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="255" placeholder="info@company.com">
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-map-marker-alt me-2 text-secondary"></i>Registered Address</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Street Address</label>
                    <textarea name="company_address" class="form-control bg-transparent text-light border-secondary" rows="2" maxlength="300"><?= e($s('company_address')) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">City</label>
                    <input type="text" name="company_city" value="<?= e($s('company_city')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="100">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-secondary">Postal / ZIP Code</label>
                        <input type="text" name="company_postal_code" value="<?= e($s('company_postal_code')) ?>"
                               class="form-control bg-transparent text-light border-secondary" maxlength="20">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-secondary">Country</label>
                        <input type="text" name="company_country" value="<?= e($s('company_country')) ?>"
                               class="form-control bg-transparent text-light border-secondary" maxlength="100" placeholder="e.g. United Kingdom">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-1"></i>Save Company Settings</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>
