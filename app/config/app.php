<?php

declare(strict_types=1);

return [
    'name' => 'Inplat',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    'timezone' => 'UTC',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'schema_file' => app_path('trading_platform_schema.sql'),
    'installed_lock' => app_path('storage/installed.lock'),
    'license_file' => app_path('storage/config/license.json'),
    'license_secret_file' => app_path('storage/config/license.key'),
    'log_file' => app_path('storage/logs/app.log'),
    'session_name' => 'inplat_session',
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string)($_ENV['TRUSTED_PROXIES'] ?? ''))))),
    'recaptcha_enabled' => filter_var($_ENV['RECAPTCHA_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
    'recaptcha_site_key' => trim((string)($_ENV['RECAPTCHA_SITE_KEY'] ?? '')),
    'recaptcha_secret_key' => trim((string)($_ENV['RECAPTCHA_SECRET_KEY'] ?? '')),
];
