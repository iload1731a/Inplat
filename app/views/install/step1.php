<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Installer: Step 1 - Requirements Check</h1>
        <ul class="list-group mb-3">
            <?php foreach ($requirements as $name => $passed): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?= e($name) ?></span>
                    <span class="badge text-bg-<?= $passed ? 'success' : 'danger' ?>"><?= $passed ? 'PASS' : 'FAIL' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="btn btn-primary w-100" href="/install/step2">Continue</a>
    </div>
</div>
