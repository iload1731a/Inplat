<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Installer: Step 2 - Database <?= ($demoMode ?? false) ? '' : '& License' ?></h1>
        <form action="/install/database" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3"><label class="form-label">Host</label><input class="form-control" name="host" value="127.0.0.1" required></div>
            <div class="mb-3"><label class="form-label">Port</label><input class="form-control" type="number" name="port" value="3306" required></div>
            <div class="mb-3"><label class="form-label">Database</label><input class="form-control" name="database" value="trading_platform" required></div>
            <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="root" required></div>
            <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password"></div>
            <?php if ($demoMode ?? false): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mt-3">
                <i class="fa fa-flask"></i>
                <div>
                    <strong>Demo Mode Active</strong> — License validation is disabled.
                    This installation is for evaluation only and must not be used in production.
                </div>
            </div>
            <?php else: ?>
            <hr class="my-4 border-secondary-subtle">
            <h2 class="h6 mb-3">License Source</h2>
            <div class="mb-3">
                <label class="form-label">License Type</label>
                <select class="form-select" id="licenseType" name="license_type" required>
                    <option value="codecanyon">CodeCanyon</option>
                    <option value="third_party">Third-Party Provider</option>
                    <?php if ($ownerLicenseEnabled ?? false): ?>
                    <option value="owner_self">Owner License (token required)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3"><label class="form-label">Licensed Domain</label><input class="form-control" name="domain" value="<?= e((string)($detectedDomain ?? '')) ?>" required></div>

            <div id="codecanyonFields" class="license-fields">
                <div class="mb-3"><label class="form-label">Buyer Name</label><input class="form-control" name="buyer_name"></div>
                <div class="mb-3"><label class="form-label">Buyer Email</label><input class="form-control" type="email" name="buyer_email"></div>
                <div class="mb-3"><label class="form-label">Purchase Code</label><input class="form-control" name="purchase_code" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>
            </div>

            <div id="thirdPartyFields" class="license-fields d-none">
                <div class="mb-3"><label class="form-label">Provider Name</label><input class="form-control" name="provider_name" placeholder="Your licensing vendor"></div>
                <div class="mb-3"><label class="form-label">License Key</label><input class="form-control" name="third_party_license_key" placeholder="External license key"></div>
            </div>

            <div id="ownerFields" class="license-fields d-none">
                <div class="alert alert-info">
                    Owner license is intended for platform owner deployments. This option appears only when owner mode is enabled. If it is missing, set <code>OWNER_LICENSE_ENABLED=true</code> and an <code>OWNER_INSTALL_TOKEN</code> value in <code>.env</code> before opening the installer.
                </div>
                <div class="mb-3"><label class="form-label">Owner Name</label><input class="form-control" name="owner_name"></div>
                <div class="mb-3"><label class="form-label">Owner Email</label><input class="form-control" type="email" name="owner_email"></div>
                <div class="mb-3"><label class="form-label">Owner Install Token</label><input class="form-control" type="password" name="owner_install_token"></div>
            </div>
            <?php endif; ?>
            <button class="btn btn-primary w-100 mt-2" type="submit">Save & Continue</button>
        </form>
    </div>
</div>
<?php if (!($demoMode ?? false)): ?>
<script>
(() => {
    const type = document.getElementById('licenseType');
    const blocks = {
        codecanyon: document.getElementById('codecanyonFields'),
        third_party: document.getElementById('thirdPartyFields'),
        owner_self: document.getElementById('ownerFields'),
    };
    if (!type) return;
    const render = () => {
        Object.entries(blocks).forEach(([key, el]) => {
            if (!el) return;
            el.classList.toggle('d-none', key !== type.value);
        });
    };
    type.addEventListener('change', render);
    render();
})();
</script>
<?php endif; ?>
