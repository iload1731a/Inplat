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
            <h2 class="h6 mb-3">CodeCanyon License</h2>
            <div class="mb-3"><label class="form-label">Buyer Name</label><input class="form-control" name="buyer_name" required></div>
            <div class="mb-3"><label class="form-label">Buyer Email</label><input class="form-control" type="email" name="buyer_email" required></div>
            <div class="mb-3"><label class="form-label">Purchase Code</label><input class="form-control" name="purchase_code" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required></div>
            <div class="mb-3"><label class="form-label">Licensed Domain</label><input class="form-control" name="domain" value="<?= e((string)($detectedDomain ?? '')) ?>" required></div>
            <?php endif; ?>
            <button class="btn btn-primary w-100 mt-2" type="submit">Save & Continue</button>
        </form>
    </div>
</div>
