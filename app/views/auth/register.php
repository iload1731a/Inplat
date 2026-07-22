<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Create Trading Account</h1>
        <form action="/register" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" minlength="3" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-3">Create account</button>
        </form>
        <p class="small text-secondary mt-3 mb-0">After sign-up you will be redirected to email verification before trading access.</p>
    </div>
</div>
