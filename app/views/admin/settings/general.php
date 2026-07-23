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
        <h1 class="h4 mb-1"><i class="fas fa-globe me-2 text-info"></i>General Settings</h1>
        <p class="text-secondary mb-0">Platform name, URLs, registration, and global toggles.</p>
    </div>
</div>

<form method="post" action="/admin/settings/general" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <!-- Platform Identity -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-id-card me-2 text-secondary"></i>Platform Identity</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Site Name <span class="text-danger">*</span></label>
                    <input type="text" name="site_name" value="<?= e($s('site_name', 'Trading Platform')) ?>"
                           class="form-control bg-transparent text-light border-secondary" required maxlength="150">
                    <div class="form-text text-secondary">Displayed in browser tab, emails, and throughout the UI.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Site Tagline</label>
                    <input type="text" name="site_tagline" value="<?= e($s('site_tagline')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="255">
                    <div class="form-text text-secondary">Displayed under the logo on the homepage hero section.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Site URL</label>
                    <input type="url" name="site_url" value="<?= e($s('site_url')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="https://yourplatform.com">
                    <div class="form-text text-secondary">Canonical URL used for links in emails and SEO.</div>
                </div>
            </div>
        </div>

        <!-- Support & Legal URLs -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-headset me-2 text-secondary"></i>Support & Legal</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Support Email</label>
                    <input type="email" name="support_email" value="<?= e($s('support_email')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="support@yourplatform.com">
                    <div class="form-text text-secondary">Public-facing support email shown to users.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Support Portal URL</label>
                    <input type="text" name="support_url" value="<?= e($s('support_url', '/tickets')) ?>"
                           class="form-control bg-transparent text-light border-secondary">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Terms of Service URL</label>
                    <input type="text" name="terms_url" value="<?= e($s('terms_url', '/terms')) ?>"
                           class="form-control bg-transparent text-light border-secondary">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Privacy Policy URL</label>
                    <input type="text" name="privacy_url" value="<?= e($s('privacy_url', '/privacy')) ?>"
                           class="form-control bg-transparent text-light border-secondary">
                </div>
            </div>
        </div>

        <!-- Global Toggles -->
        <div class="col-lg-12">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-toggle-on me-2 text-secondary"></i>Global Toggles</h6>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle">
                            <div>
                                <div class="fw-semibold">User Registration</div>
                                <div class="small text-secondary">Allow new users to sign up.</div>
                            </div>
                            <div class="form-check form-switch mb-0 ms-3">
                                <input type="hidden" name="registration_enabled" value="false">
                                <input class="form-check-input" type="checkbox" name="registration_enabled" value="true"
                                       id="regEnabled" <?= $s('registration_enabled', 'true') === 'true' ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle">
                            <div>
                                <div class="fw-semibold">Cookie Consent Banner</div>
                                <div class="small text-secondary">Show GDPR cookie notice.</div>
                            </div>
                            <div class="form-check form-switch mb-0 ms-3">
                                <input type="hidden" name="cookie_consent_enabled" value="false">
                                <input class="form-check-input" type="checkbox" name="cookie_consent_enabled" value="true"
                                       id="cookieEnabled" <?= $s('cookie_consent_enabled', 'true') === 'true' ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-danger-subtle">
                            <div>
                                <div class="fw-semibold text-danger">Maintenance Mode</div>
                                <div class="small text-secondary">Take platform offline for maintenance.</div>
                            </div>
                            <div class="form-check form-switch mb-0 ms-3">
                                <input type="hidden" name="maintenance_mode" value="false">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" value="true"
                                       id="maintenanceMode" <?= $s('maintenance_mode', 'false') === 'true' ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-1"></i>Save General Settings</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>
