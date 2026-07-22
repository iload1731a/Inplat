<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Installer: Step 3 - Import SQL Schema</h1>
        <p class="text-secondary">The installer will import <code>trading_platform_schema.sql</code> as the single source of truth.</p>
        <form action="/install/import" method="post" data-ajax="true">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <button class="btn btn-primary w-100" type="submit">Import Schema & Continue</button>
        </form>
    </div>
</div>
