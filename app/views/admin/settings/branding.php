<?php declare(strict_types=1); ?>
<?php
$settings    = is_array($settings ?? null) ? $settings : [];
$activeTheme = is_array($activeTheme ?? null) ? $activeTheme : null;
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-palette me-2 text-success"></i>Branding</h1>
        <p class="text-secondary mb-0">Platform logo, favicon, brand colors, and social sharing images.</p>
    </div>
</div>

<form method="post" action="/admin/settings/branding" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <!-- Logos & Images -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-images me-2 text-secondary"></i>Logos & Images</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Primary Logo URL</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="url" name="logo_url" id="logoUrl" value="<?= e($s('logo_url')) ?>"
                               class="form-control bg-transparent text-light border-secondary flex-grow-1" placeholder="https://...">
                        <div class="flex-shrink-0">
                            <?php if ($s('logo_url') !== ''): ?>
                                <img src="<?= e($s('logo_url')) ?>" alt="Logo" style="max-height:32px;max-width:80px;object-fit:contain;">
                            <?php else: ?>
                                <span class="text-secondary small">No logo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-text text-secondary">Used in the header, emails, and admin panel.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Dark Mode Logo URL</label>
                    <input type="url" name="logo_dark_url" value="<?= e($s('logo_dark_url')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="https://... (optional, for light backgrounds)">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Favicon URL</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="url" name="favicon_url" value="<?= e($s('favicon_url')) ?>"
                               class="form-control bg-transparent text-light border-secondary flex-grow-1" placeholder="https://.../favicon.ico">
                        <div class="flex-shrink-0">
                            <?php if ($s('favicon_url') !== ''): ?>
                                <img src="<?= e($s('favicon_url')) ?>" alt="Fav" style="width:24px;height:24px;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-text text-secondary">32×32 or 64×64 .ico / .png.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">OG / Social Share Image URL</label>
                    <input type="url" name="og_image_url" value="<?= e($s('og_image_url')) ?>"
                           class="form-control bg-transparent text-light border-secondary" placeholder="https://... 1200×630px">
                    <div class="form-text text-secondary">Default Open Graph image for social media sharing (1200×630 recommended).</div>
                </div>
            </div>
        </div>

        <!-- Brand Colors -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-swatchbook me-2 text-secondary"></i>Brand Colors</h6>

                <div class="mb-4">
                    <label class="form-label small text-secondary">Primary Color</label>
                    <div class="d-flex align-items-center gap-3">
                        <input type="color" name="primary_color" value="<?= e($s('primary_color', '#3b82f6')) ?>"
                               class="form-control form-control-color" style="width:60px;height:38px;">
                        <input type="text" id="primaryColorText" value="<?= e($s('primary_color', '#3b82f6')) ?>"
                               class="form-control bg-transparent text-light border-secondary" style="width:120px;"
                               oninput="document.querySelector('[name=primary_color]').value=this.value">
                        <span class="small text-secondary">Main brand color used for buttons and highlights.</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small text-secondary">Accent / Highlight Color</label>
                    <div class="d-flex align-items-center gap-3">
                        <input type="color" name="accent_color" value="<?= e($s('accent_color', '#f59e0b')) ?>"
                               class="form-control form-control-color" style="width:60px;height:38px;">
                        <input type="text" value="<?= e($s('accent_color', '#f59e0b')) ?>"
                               class="form-control bg-transparent text-light border-secondary" style="width:120px;"
                               oninput="document.querySelector('[name=accent_color]').value=this.value">
                        <span class="small text-secondary">Secondary accent for tags, badges, and UI highlights.</span>
                    </div>
                </div>

                <!-- Color Preview -->
                <div class="p-3 rounded-3 border border-secondary-subtle">
                    <div class="small text-secondary mb-2 fw-semibold">Color Preview</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm" id="previewPrimary"
                                style="background:<?= e($s('primary_color', '#3b82f6')) ?>;border:none;color:#fff;">
                            Primary Button
                        </button>
                        <span class="badge" id="previewAccent"
                              style="background:<?= e($s('accent_color', '#f59e0b')) ?>;color:#000;font-size:.85rem;padding:.4rem .8rem;">
                            Accent Badge
                        </span>
                        <a href="#" class="link-offset-2" id="previewLink"
                           style="color:<?= e($s('primary_color', '#3b82f6')) ?>">Sample Link</a>
                    </div>
                </div>

                <?php if ($activeTheme): ?>
                    <div class="alert alert-info py-2 small mt-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Active theme: <strong><?= e((string)($activeTheme['name'] ?? '')) ?></strong>.
                        <a href="/admin/settings/theme" class="alert-link">Manage themes →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-1"></i>Save Branding</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>

<script>
document.querySelector('[name=primary_color]').addEventListener('input', function() {
    document.getElementById('primaryColorText').value = this.value;
    document.getElementById('previewPrimary').style.background = this.value;
    document.getElementById('previewLink').style.color = this.value;
});
document.querySelector('[name=accent_color]').addEventListener('input', function() {
    document.getElementById('previewAccent').style.background = this.value;
});
</script>
