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
    'session_name' => 'inplat_session',
];
