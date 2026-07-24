<?php declare(strict_types=1); ?>
<?php
// Settings sub-navigation
$currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$items = [
    '/admin/settings/hub'          => ['Hub',         'fa-layer-group'],
    '/admin/settings/general'      => ['General',     'fa-globe'],
    '/admin/settings/company'      => ['Company',     'fa-building'],
    '/admin/settings/branding'     => ['Branding',    'fa-palette'],
    '/admin/settings/theme'        => ['Theme',       'fa-paint-brush'],
    '/admin/settings/localization' => ['Locale',      'fa-language'],
    '/admin/settings/languages'    => ['Languages',   'fa-flag'],
    '/admin/settings/smtp'         => ['SMTP',        'fa-envelope'],
    '/admin/settings/sms'          => ['SMS',         'fa-sms'],
    '/admin/settings/api'          => ['API Keys',    'fa-plug'],
    '/admin/settings/trading'      => ['Trading',     'fa-chart-line'],
    '/admin/settings/wallet'       => ['Wallet',      'fa-wallet'],
    '/admin/settings/security'     => ['Security',    'fa-shield-alt'],
    '/admin/settings/maintenance'  => ['Maintenance', 'fa-wrench'],
    '/admin/settings/backup'       => ['Backup',      'fa-database'],
    '/admin/settings/cache'        => ['Cache',       'fa-bolt'],
    '/admin/settings/system-info'  => ['System',      'fa-info-circle'],
];
?>
<div class="glass rounded-4 p-2 mb-4">
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($items as $href => [$label, $icon]): ?>
            <?php $active = $currentPath === $href; ?>
            <a href="<?= e($href) ?>" class="btn btn-xs <?= $active ? 'btn-warning text-dark' : 'btn-outline-light' ?>">
                <i class="fas <?= e($icon) ?> me-1"></i><?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
