<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Installer: Step 2 - Database Configuration</h1>
        <form action="/install/database" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <div class="mb-3"><label class="form-label">Host</label><input class="form-control" name="host" value="127.0.0.1" required></div>
            <div class="mb-3"><label class="form-label">Port</label><input class="form-control" type="number" name="port" value="3306" required></div>
            <div class="mb-3"><label class="form-label">Database</label><input class="form-control" name="database" value="trading_platform" required></div>
            <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="root" required></div>
            <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password"></div>
            <button class="btn btn-primary w-100" type="submit">Save & Continue</button>
        </form>
    </div>
</div>
