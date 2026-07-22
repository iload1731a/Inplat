<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Reset Password</h1>
        <?php if (($token ?? '') === ''): ?>
            <div class="alert alert-warning mb-0">Reset token is missing. Generate a new link from forgot password.</div>
        <?php else: ?>
            <form action="/reset-password" method="post" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="token" value="<?= e((string)$token) ?>">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Update password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
