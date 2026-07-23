#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Libraries\Database;
use App\Libraries\Env;
use App\Libraries\LicenseGuard;

define('APP_BASE_PATH', dirname(__DIR__));

require APP_BASE_PATH . '/vendor/autoload.php';

Env::load(APP_BASE_PATH . '/.env');

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

if (!LicenseGuard::ownerLicenseEnabled()) {
    fwrite(STDERR, "Owner install mode is disabled. Set OWNER_LICENSE_ENABLED=true in your environment.\n");
    exit(1);
}

$options = getopt('', [
    'host::',
    'port::',
    'database:',
    'username:',
    'password::',
    'admin-username:',
    'admin-email:',
    'admin-password:',
    'owner-name:',
    'owner-email:',
    'domain:',
    'owner-token:',
]);

$required = ['database', 'username', 'admin-username', 'admin-email', 'admin-password', 'owner-name', 'owner-email', 'domain', 'owner-token'];
foreach ($required as $field) {
    if (!isset($options[$field]) || trim((string)$options[$field]) === '') {
        fwrite(STDERR, "Missing required option --{$field}\n");
        exit(1);
    }
}

if (!LicenseGuard::validateOwnerInstallToken((string)$options['owner-token'])) {
    fwrite(STDERR, "Owner token is invalid.\n");
    exit(1);
}

$ownerName = LicenseGuard::normalizeBuyerName((string)$options['owner-name']);
$ownerEmail = strtolower(trim((string)$options['owner-email']));
$domain = LicenseGuard::normalizeDomain((string)$options['domain']);
if ($ownerName === '' || !LicenseGuard::isValidBuyerName($ownerName)) {
    fwrite(STDERR, "Owner name is invalid.\n");
    exit(1);
}
if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Owner email is invalid.\n");
    exit(1);
}
if ($domain === '') {
    fwrite(STDERR, "Domain is required.\n");
    exit(1);
}

$db = [
    'host' => (string)($options['host'] ?? '127.0.0.1'),
    'port' => (int)($options['port'] ?? 3306),
    'database' => (string)$options['database'],
    'username' => (string)$options['username'],
    'password' => (string)($options['password'] ?? ''),
];

$connectionTest = Database::testConnection($db);
if (!($connectionTest['ok'] ?? false)) {
    fwrite(STDERR, "Database connection failed: " . (string)($connectionTest['message'] ?? 'unknown error') . "\n");
    exit(1);
}

$configDir = APP_BASE_PATH . '/storage/config';
if (!is_dir($configDir) && !mkdir($configDir, 0750, true) && !is_dir($configDir)) {
    fwrite(STDERR, "Unable to create storage/config directory.\n");
    exit(1);
}

$dbConfigContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($db, true) . ";\n";
$dbConfigPath = APP_BASE_PATH . '/storage/config/database.php';
if (file_put_contents($dbConfigPath, $dbConfigContent, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write storage/config/database.php\n");
    exit(1);
}
chmod($dbConfigPath, 0600);

LicenseGuard::ensureSecretFile();
$licensePayload = LicenseGuard::packOwner($ownerName, $ownerEmail, $domain);
$licenseContent = json_encode($licensePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$licensePath = APP_BASE_PATH . '/storage/config/license.json';
if (file_put_contents($licensePath, $licenseContent, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write storage/config/license.json\n");
    exit(1);
}
chmod($licensePath, 0600);

$schemaFile = APP_BASE_PATH . '/trading_platform_schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Schema file not found: trading_platform_schema.sql\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
if ($sql === false) {
    fwrite(STDERR, "Unable to read schema file.\n");
    exit(1);
}

try {
    $pdo = Database::serverConnection((array)config('database'));

    foreach (splitSqlStatements($sql) as $statement) {
        assertSchemaStatementAllowed($statement);
        $pdo->exec($statement);
    }

    $pdo = Database::connection();
    $pdo->beginTransaction();

    $roleStmt = $pdo->prepare('INSERT IGNORE INTO roles (name, description, is_system_role, created_at, updated_at) VALUES (:name, :description, 1, NOW(), NOW())');
    $roleStmt->execute(['name' => 'super_admin', 'description' => 'System Super Administrator']);

    $roleIdStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $roleIdStmt->execute(['name' => 'super_admin']);
    $roleId = (int)($roleIdStmt->fetchColumn() ?: 0);
    if ($roleId <= 0) {
        throw new RuntimeException('Unable to resolve super_admin role id.');
    }

    $adminPasswordHash = password_hash((string)$options['admin-password'], password_algo());
    if ($adminPasswordHash === false) {
        throw new RuntimeException('Unable to hash admin password.');
    }

    $adminStmt = $pdo->prepare('INSERT INTO admin_users (username, email, password_hash, full_name, role_id, status, created_at, updated_at)
        VALUES (:username, :email, :password_hash, :full_name, :role_id, :status, NOW(), NOW())');
    $adminStmt->execute([
        'username' => trim((string)$options['admin-username']),
        'email' => trim((string)$options['admin-email']),
        'password_hash' => $adminPasswordHash,
        'full_name' => 'Platform Administrator',
        'role_id' => $roleId,
        'status' => 'active',
    ]);

    persistOwnerLicenseSettings($pdo, $licensePayload);
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Owner install failed: {$e->getMessage()}\n");
    exit(1);
}

$lockPath = APP_BASE_PATH . '/storage/installed.lock';
if (file_put_contents($lockPath, 'installed=' . date('c'), LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write installation lock file.\n");
    exit(1);
}

echo "Owner install complete.\n";
echo "Admin login: " . rtrim((string)($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000'), '/') . "/admin/login\n";

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($escape) {
            $buffer .= $char;
            $escape = false;
            continue;
        }

        if ($char === '\\') {
            $buffer .= $char;
            $escape = true;
            continue;
        }

        if ($char === "'" && !$inDouble) {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle) {
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statement = trim($buffer);
            if ($statement !== '' && !str_starts_with($statement, '--')) {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '' && !str_starts_with($tail, '--')) {
        $statements[] = $tail;
    }

    return $statements;
}

function assertSchemaStatementAllowed(string $statement): void
{
    $normalized = ltrim($statement);
    $prefix = strtoupper((string)strtok($normalized, " \n\t\r"));
    $allowed = ['SET', 'CREATE', 'USE', 'INSERT'];
    $disallowedPattern = '/\\b(DROP|DELETE|TRUNCATE|RENAME|GRANT|REVOKE)\\b/i';

    if (!in_array($prefix, $allowed, true) || preg_match($disallowedPattern, $normalized) === 1) {
        throw new RuntimeException('Unsupported SQL statement in schema import: ' . $prefix);
    }
}

function persistOwnerLicenseSettings(PDO $pdo, array $licensePayload): void
{
    $values = [
        'license_type' => LicenseGuard::TYPE_OWNER,
        'license_domain' => (string)$licensePayload['domain'],
        'license_owner_name' => (string)$licensePayload['owner_name'],
        'license_owner_email' => (string)$licensePayload['owner_email'],
        'license_owner_identity_hash' => (string)$licensePayload['owner_identity_hash'],
        'license_verified_at' => date('Y-m-d H:i:s'),
    ];

    $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, value_type, category, description, is_public, updated_at)
        VALUES (:setting_key, :setting_value, :value_type, :category, :description, 0, NOW()) AS incoming
        ON DUPLICATE KEY UPDATE setting_value = incoming.setting_value, value_type = incoming.value_type, category = incoming.category, description = incoming.description, updated_at = NOW()');

    foreach ($values as $key => $value) {
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => 'string',
            'category' => 'license',
            'description' => 'License metadata',
        ]);
    }
}
