<?php declare(strict_types=1); ?>
<?php
$recaptchaEnabled = (bool)config('app.recaptcha_enabled', false);
$recaptchaSiteKey = trim((string)config('app.recaptcha_site_key', ''));
?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Professional Login</h1>
        <form action="/login" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3">
                <label class="form-label">Email or Username</label>
                <input type="text" name="identity" class="form-control" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="8" required autocomplete="current-password">
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">Remember me</label>
                </div>
                <a href="/forgot-password">Forgot password?</a>
            </div>
            <?php if ($recaptchaEnabled && $recaptchaSiteKey !== ''): ?>
                <div class="mb-3 d-flex justify-content-center">
                    <div class="g-recaptcha" data-sitekey="<?= e($recaptchaSiteKey) ?>"></div>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="d-flex justify-content-between mt-3 small">
            <a href="/register">Create account</a>
            <span class="text-secondary">2FA is requested automatically when enabled.</span>
        </div>
    </div>
</div>
<?php if ($recaptchaEnabled && $recaptchaSiteKey !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
