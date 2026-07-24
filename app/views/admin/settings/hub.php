<?php declare(strict_types=1); ?>
<?php
$siteName       = (string)($siteName ?? 'Trading Platform');
$maintenance    = (bool)($maintenance ?? false);
$smtpConfigs    = is_array($smtpConfigs ?? null) ? $smtpConfigs : [];
$smsConfigs     = is_array($smsConfigs ?? null) ? $smsConfigs : [];
$languages      = is_array($languages ?? null) ? $languages : [];
$themes         = is_array($themes ?? null) ? $themes : [];
$integrations   = is_array($integrations ?? null) ? $integrations : [];
$settingsMap    = is_array($settingsMap ?? null) ? $settingsMap : [];
$smtpDefault    = array_filter($smtpConfigs, fn($s) => (int)($s['is_default'] ?? 0) === 1);
$langDefault    = array_filter($languages, fn($l) => (int)($l['is_default'] ?? 0) === 1);
$themeDefault   = array_filter($themes, fn($t) => (int)($t['is_default'] ?? 0) === 1);
require app_path('app/views/admin/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-layer-group me-2 text-warning"></i>Configuration Hub</h1>
        <p class="text-secondary mb-0">Central control for all platform settings, integrations, and configuration.</p>
    </div>
    <?php if ($maintenance): ?>
        <span class="badge bg-danger fs-6"><i class="fas fa-wrench me-1"></i>Maintenance Mode ACTIVE</span>
    <?php endif; ?>
</div>

<!-- Settings Navigation Cards -->
<div class="row g-3 mb-4">
    <?php
    $sections = [
        ['General',        '/admin/settings/general',      'fa-globe',           'text-info',    'Site name, URL, registration, cookie consent.'],
        ['Company',        '/admin/settings/company',      'fa-building',        'text-warning', 'Legal company name, address, registration number.'],
        ['Branding',       '/admin/settings/branding',     'fa-palette',         'text-success', 'Logo, favicon, brand colors, OG image.'],
        ['Theme',          '/admin/settings/theme',        'fa-paint-brush',     'text-purple',  'UI themes, dark/light mode, color presets.'],
        ['Localization',   '/admin/settings/localization', 'fa-language',        'text-info',    'Timezone, date format, default currency display.'],
        ['Languages',      '/admin/settings/languages',    'fa-flag',            'text-primary', 'Manage UI languages and default language.'],
        ['SMTP Email',     '/admin/settings/smtp',         'fa-envelope',        'text-warning', 'Email server configuration and test.'],
        ['SMS Gateway',    '/admin/settings/sms',          'fa-sms',             'text-success', 'SMS provider configuration (Twilio, Nexmo, etc.).'],
        ['API Keys',       '/admin/settings/api',          'fa-plug',            'text-danger',  'Third-party API integrations (payments, KYC, etc.).'],
        ['Trading Config', '/admin/settings/trading',      'fa-chart-line',      'text-info',    'Trading on/off, fees, order limits, precision.'],
        ['Wallet Config',  '/admin/settings/wallet',       'fa-wallet',          'text-warning', 'Deposits, withdrawals, limits, cold storage.'],
        ['Security',       '/admin/settings/security',     'fa-shield-alt',      'text-danger',  'Password policy, 2FA, session lifetime, IP whitelist.'],
        ['Maintenance',    '/admin/settings/maintenance',  'fa-wrench',          'text-orange',  'Maintenance mode toggle and message.'],
        ['Backup',         '/admin/settings/backup',       'fa-database',        'text-success', 'Scheduled and manual backups, retention policy.'],
        ['Cache',          '/admin/settings/cache',        'fa-bolt',            'text-info',    'Cache driver, OPcache, session cache flush.'],
        ['System Info',    '/admin/settings/system-info',  'fa-info-circle',     'text-secondary','PHP version, MySQL, disk, memory, extensions.'],
    ];
    foreach ($sections as [$label, $url, $icon, $color, $desc]):
    ?>
    <div class="col-lg-3 col-md-4 col-sm-6">
        <a href="<?= e($url) ?>" class="text-decoration-none">
            <div class="glass rounded-4 p-3 h-100 d-flex align-items-start gap-3 hover-card">
                <div class="mt-1 fs-4 <?= e($color) ?>"><i class="fas <?= e($icon) ?>"></i></div>
                <div>
                    <div class="fw-semibold"><?= e($label) ?></div>
                    <div class="small text-secondary"><?= e($desc) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="glass rounded-4 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><i class="fas fa-chart-line me-2 text-info"></i>Market Data Operations</h2>
        <a href="/admin/markets/data-sync" class="btn btn-xs btn-outline-info">Open Data Sync</a>
    </div>
    <div class="row g-2">
        <div class="col-md-3 col-6"><a class="btn btn-outline-light w-100" href="/admin/assets"><i class="fas fa-coins me-1"></i>Currencies</a></div>
        <div class="col-md-3 col-6"><a class="btn btn-outline-light w-100" href="/admin/assets/pairs"><i class="fas fa-exchange-alt me-1"></i>Pairs</a></div>
        <div class="col-md-3 col-6"><a class="btn btn-outline-light w-100" href="/admin/markets/mappings"><i class="fas fa-link me-1"></i>Mappings</a></div>
        <div class="col-md-3 col-6"><a class="btn btn-outline-light w-100" href="/admin/charts"><i class="fas fa-chart-bar me-1"></i>Chart Data</a></div>
    </div>
</div>

<!-- Status Summary -->
<div class="row g-4">
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-envelope me-2 text-warning"></i>Email Configuration</h6>
            <?php if ($smtpConfigs === []): ?>
                <div class="alert alert-warning py-2 small mb-2">No SMTP profiles configured.</div>
                <a href="/admin/settings/smtp" class="btn btn-sm btn-outline-warning">Configure SMTP</a>
            <?php else: ?>
                <?php $def = reset($smtpDefault); ?>
                <?php if ($def): ?>
                    <div class="small mb-2">
                        <span class="badge bg-success me-1">Active</span>
                        <strong><?= e((string)($def['name'] ?? '-')) ?></strong>
                        <span class="text-secondary ms-1"><?= e((string)($def['host'] ?? '')) ?></span>
                    </div>
                    <?php if ((int)($def['last_test_ok'] ?? -1) === 1): ?>
                        <div class="small text-success"><i class="fas fa-check-circle me-1"></i>Last test passed</div>
                    <?php elseif ((int)($def['last_test_ok'] ?? -1) === 0): ?>
                        <div class="small text-danger"><i class="fas fa-times-circle me-1"></i>Last test failed</div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="small text-secondary mt-1"><?= count($smtpConfigs) ?> profile(s) total</div>
                <a href="/admin/settings/smtp" class="btn btn-xs btn-outline-light mt-2">Manage SMTP</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-language me-2 text-info"></i>Languages & Locale</h6>
            <?php $defLang = reset($langDefault); ?>
            <?php if ($defLang): ?>
                <div class="small mb-2">
                    <span class="badge bg-primary me-1">Default</span>
                    <strong><?= e((string)($defLang['name'] ?? '-')) ?></strong>
                    <code class="ms-1"><?= e((string)($defLang['code'] ?? '')) ?></code>
                </div>
            <?php endif; ?>
            <div class="small text-secondary"><?= count($languages) ?> language(s) configured</div>
            <div class="small text-secondary mt-1">Timezone: <code><?= e($settingsMap['default_timezone'] ?? 'UTC') ?></code></div>
            <a href="/admin/settings/languages" class="btn btn-xs btn-outline-light mt-2">Manage Languages</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-paint-brush me-2 text-success"></i>Active Theme</h6>
            <?php $defTheme = reset($themeDefault); ?>
            <?php if ($defTheme): ?>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="d-flex gap-1">
                        <?php foreach (['primary_color','accent_color','success_color','danger_color'] as $c): ?>
                            <div style="width:16px;height:16px;border-radius:50%;background:<?= e((string)($defTheme[$c] ?? '#888')) ?>;"></div>
                        <?php endforeach; ?>
                    </div>
                    <strong class="small"><?= e((string)($defTheme['name'] ?? '-')) ?></strong>
                </div>
                <div class="small text-secondary"><?= e(ucfirst((string)($defTheme['color_scheme'] ?? 'dark'))) ?> mode</div>
            <?php else: ?>
                <div class="small text-secondary">No active theme.</div>
            <?php endif; ?>
            <div class="small text-secondary mt-1"><?= count($themes) ?> theme(s) available</div>
            <a href="/admin/settings/theme" class="btn btn-xs btn-outline-light mt-2">Manage Themes</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-plug me-2 text-danger"></i>API Integrations</h6>
            <div class="small text-secondary"><?= count($integrations) ?> integration(s) configured</div>
            <?php
            $activeCount = count(array_filter($integrations, fn($i) => (int)($i['is_active'] ?? 0) === 1));
            $sandboxCount = count(array_filter($integrations, fn($i) => (int)($i['sandbox_mode'] ?? 1) === 1 && (int)($i['is_active'] ?? 0) === 1));
            ?>
            <?php if ($activeCount > 0): ?>
                <div class="small mt-1"><span class="badge bg-success me-1"><?= $activeCount ?></span> active</div>
                <?php if ($sandboxCount > 0): ?>
                    <div class="small text-warning"><i class="fas fa-flask me-1"></i><?= $sandboxCount ?> in sandbox mode</div>
                <?php endif; ?>
            <?php endif; ?>
            <a href="/admin/settings/api" class="btn btn-xs btn-outline-light mt-2">Manage APIs</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-wrench me-2 text-orange"></i>Maintenance Mode</h6>
            <?php if ($maintenance): ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger">ACTIVE</span>
                    <span class="small">Platform is in maintenance.</span>
                </div>
                <form method="post" action="/admin/settings/maintenance" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <input type="hidden" name="maintenance_mode" value="false">
                    <button class="btn btn-sm btn-success">Disable Maintenance</button>
                </form>
            <?php else: ?>
                <div class="small text-secondary mb-2">Platform is running normally.</div>
                <form method="post" action="/admin/settings/maintenance" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                    <input type="hidden" name="maintenance_mode" value="true">
                    <button class="btn btn-sm btn-outline-warning">Enable Maintenance</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-sms me-2 text-success"></i>SMS Configuration</h6>
            <?php if ($smsConfigs === []): ?>
                <div class="small text-secondary mb-2">No SMS profiles configured.</div>
                <a href="/admin/settings/sms" class="btn btn-sm btn-outline-success">Configure SMS</a>
            <?php else: ?>
                <div class="small text-secondary"><?= count($smsConfigs) ?> profile(s) configured</div>
                <?php $defSms = array_values(array_filter($smsConfigs, fn($s) => (int)($s['is_default'] ?? 0) === 1)); ?>
                <?php if (!empty($defSms)): ?>
                    <div class="small mt-1"><span class="badge bg-success me-1">Default</span><?= e((string)($defSms[0]['name'] ?? '-')) ?>
                        <span class="text-secondary ms-1">(<?= e((string)($defSms[0]['provider'] ?? '')) ?>)</span>
                    </div>
                <?php endif; ?>
                <a href="/admin/settings/sms" class="btn btn-xs btn-outline-light mt-2">Manage SMS</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.hover-card { transition: transform .15s, box-shadow .15s; }
.hover-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
.text-purple { color: #8b5cf6 !important; }
.text-orange { color: #f97316 !important; }
</style>
