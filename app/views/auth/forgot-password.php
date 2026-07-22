<?php declare(strict_types=1); ?>
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
            <button type="submit" class="btn btn-primary w-100">Generate reset link</button>
        </form>
        <div id="resetLinkBox" class="alert alert-info mt-3 d-none"></div>
    </div>
</div>
<script>
$(document).on('ajax:success', '#forgotPasswordForm', function (event, payload) {
    if (!payload || !payload.reset_link) return;
    const box = $('#resetLinkBox');
    const link = $('<a>').attr('href', payload.reset_link).text(payload.reset_link);
    box.removeClass('d-none').empty().append('Reset URL: ').append(link);
});
</script>
