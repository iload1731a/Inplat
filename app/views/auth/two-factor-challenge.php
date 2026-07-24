<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">2FA Verification</h1>
        <p class="text-secondary">Enter your 6-digit authentication code to continue.</p>
        <form action="/two-factor-challenge" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3">
                <label class="form-label">Code</label>
                <input type="text" name="code" class="form-control" minlength="6" maxlength="6" pattern="[0-9]{6}" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify and continue</button>
        </form>
    </div>
</div>
