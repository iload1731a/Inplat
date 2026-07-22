<?php declare(strict_types=1); ?>
<div class="container-narrow mx-auto">
    <div class="glass rounded-4 p-4 shadow">
        <h1 class="h4 mb-3">Email Verification</h1>
        <?php if ((string)($_GET['invalid'] ?? '') === '1'): ?>
            <div class="alert alert-danger">Verification link is invalid or expired.</div>
        <?php endif; ?>
        <p class="text-secondary">Account verification is required before production trading actions.</p>
        <?php if (($token ?? '') !== ''): ?>
            <a class="btn btn-primary w-100" href="/email/verify?token=<?= urlencode((string)$token) ?>">Verify email now</a>
        <?php else: ?>
            <div class="alert alert-warning mb-0">Verification token is missing.</div>
        <?php endif; ?>
    </div>
</div>
