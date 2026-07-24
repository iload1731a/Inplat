<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Installer: Step 4 - Create Admin Account</h1>
        <form action="/install/admin" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3"><label class="form-label">Admin Username</label><input class="form-control" name="username" value="admin" required></div>
            <div class="mb-3"><label class="form-label">Admin Email</label><input class="form-control" type="email" name="email" required></div>
            <div class="mb-3"><label class="form-label">Admin Password</label><input class="form-control" type="password" name="password" minlength="8" required></div>
            <button class="btn btn-primary w-100" type="submit">Create Admin & Continue</button>
        </form>
    </div>
</div>
