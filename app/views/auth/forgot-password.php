<?php declare(strict_types=1); ?>
<?php
$recaptchaEnabled = (bool)config('app.recaptcha_enabled', false);
$recaptchaSiteKey = trim((string)config('app.recaptcha_site_key', ''));
?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Forgot Password</h1>
        <p class="text-secondary">Enter your account email to generate a reset link.</p>
        <form action="/forgot-password" method="post" data-ajax="true" id="forgotPasswordForm">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3">
                <label class="form-label">Account Email</label>
                <input type="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <?php if ($recaptchaEnabled && $recaptchaSiteKey !== ''): ?>
                <div class="mb-3 d-flex justify-content-center">
                    <div class="g-recaptcha" data-sitekey="<?= e($recaptchaSiteKey) ?>"></div>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-100">Generate reset link</button>
        </form>
        <div id="resetLinkBox" class="alert alert-info mt-3 d-none"></div>
    </div>
</div>
<?php if ($recaptchaEnabled && $recaptchaSiteKey !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<script>
$(document).on('ajax:success', '#forgotPasswordForm', function (event, payload) {
    const debugMode = <?= (bool)config('app.debug', false) ? 'true' : 'false' ?>;
    if (!debugMode || !payload || !payload.reset_link || typeof payload.reset_link !== 'string') return;

    let parsedUrl;
    try {
        parsedUrl = new URL(payload.reset_link, window.location.origin);
    } catch {
        return;
    }

    if (parsedUrl.origin !== window.location.origin || parsedUrl.pathname !== '/reset-password' || !parsedUrl.searchParams.get('token')) {
        return;
    }

    const box = $('#resetLinkBox');
    const safeHref = parsedUrl.pathname + parsedUrl.search;
    const link = $('<a>').attr('href', safeHref).text(safeHref);
    box.removeClass('d-none').empty().append('Reset URL: ').append(link);
});
</script>
