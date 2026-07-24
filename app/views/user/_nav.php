<?php declare(strict_types=1); ?>
<?php $userSection = (string)($userSection ?? 'dashboard'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1 fw-semibold"><?= e((string)($title ?? 'User Panel')) ?></h1>
        <?php if (!empty($breadcrumb)): ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard" class="text-info text-decoration-none">Dashboard</a></li>
                <?php foreach ((array)$breadcrumb as $crumb): ?>
                <li class="breadcrumb-item <?= empty($crumb['url']) ? 'active text-secondary' : '' ?>">
                    <?php if (!empty($crumb['url'])): ?>
                        <a href="<?= e((string)$crumb['url']) ?>" class="text-info text-decoration-none"><?= e((string)$crumb['label']) ?></a>
                    <?php else: ?>
                        <?= e((string)$crumb['label']) ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>
    </div>
    <?php if (!empty($headerActions)): ?>
    <div class="d-flex gap-2"><?= $headerActions ?></div>
    <?php endif; ?>
</div>
