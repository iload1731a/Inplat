<?php

declare(strict_types=1);

$storedConfigPath = app_path('storage/config/database.php');
$stored = is_file($storedConfigPath) ? require $storedConfigPath : [];

return [
    'host' => $stored['host'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
    'port' => (int)($stored['port'] ?? ($_ENV['DB_PORT'] ?? 3306)),
    'database' => $stored['database'] ?? ($_ENV['DB_DATABASE'] ?? 'trading_platform'),
    'username' => $stored['username'] ?? ($_ENV['DB_USERNAME'] ?? 'root'),
    'password' => $stored['password'] ?? ($_ENV['DB_PASSWORD'] ?? ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
