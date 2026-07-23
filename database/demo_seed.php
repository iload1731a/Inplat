#!/usr/bin/env php
<?php

/**
 * Demo Seeder — creates demo admin and demo user accounts for local evaluation.
 *
 * Usage (from project root):
 *   php database/demo_seed.php
 *
 * Requirements:
 *   - The installer must have already been completed (storage/installed.lock exists).
 *   - storage/config/database.php must exist with valid connection details.
 *
 * Credentials created:
 *   Demo Admin  →  admin@demo.test  /  Demo@1234
 *   Demo User   →  user@demo.test   /  Demo@1234
 */

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────

define('APP_BASE_PATH', dirname(__DIR__));

$dbConfigPath = APP_BASE_PATH . '/storage/config/database.php';
if (!is_file($dbConfigPath)) {
    fwrite(STDERR, "Error: storage/config/database.php not found.\n");
    fwrite(STDERR, "Run the installer first: http://127.0.0.1:8000/install/step1\n");
    exit(1);
}

$db = require $dbConfigPath;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'],
        $db['port'],
        $db['database'],
    );
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Password ───────────────────────────────────────────────────────────────

$demoPassword = 'Demo@1234';
$passwordHash = password_hash($demoPassword, PASSWORD_ARGON2ID);
if ($passwordHash === false) {
    // Fallback to bcrypt if argon2id is unavailable
    $passwordHash = password_hash($demoPassword, PASSWORD_BCRYPT);
}

// ── Helper ─────────────────────────────────────────────────────────────────

function upsertRow(PDO $pdo, string $table, array $data, string $uniqueKey): int
{
    // Check existence
    $checkStmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE `{$uniqueKey}` = :val LIMIT 1");
    $checkStmt->execute(['val' => $data[$uniqueKey]]);
    $existing = $checkStmt->fetchColumn();

    if ($existing !== false) {
        echo "  ℹ  {$table}.{$uniqueKey} = '{$data[$uniqueKey]}' already exists — skipping.\n";
        return (int)$existing;
    }

    $cols   = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
    $params = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
    $stmt   = $pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$params})");
    $stmt->execute($data);
    return (int)$pdo->lastInsertId();
}

// ── 1. Demo Admin Role ─────────────────────────────────────────────────────

echo "\n[1/4] Creating demo_admin role...\n";

$demoAdminRoleId = upsertRow($pdo, 'roles', [
    'name'          => 'demo_admin',
    'description'   => 'Read-only demo admin — for evaluation purposes only',
    'is_system_role' => 0,
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
], 'name');

// Assign view-only permissions
$viewPermissions = ['users.view', 'kyc.review'];
foreach ($viewPermissions as $permKey) {
    $permStmt = $pdo->prepare("SELECT id FROM permissions WHERE `key` = :key LIMIT 1");
    $permStmt->execute(['key' => $permKey]);
    $permId = (int)($permStmt->fetchColumn() ?: 0);
    if ($permId > 0) {
        $pdo->prepare(
            "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :perm_id)"
        )->execute(['role_id' => $demoAdminRoleId, 'perm_id' => $permId]);
    }
}

echo "  ✓  role 'demo_admin' id={$demoAdminRoleId}\n";

// ── 2. Demo Admin User ─────────────────────────────────────────────────────

echo "\n[2/4] Creating demo admin user...\n";

$demoAdminId = upsertRow($pdo, 'admin_users', [
    'username'      => 'demo_admin',
    'email'         => 'admin@demo.test',
    'password_hash' => $passwordHash,
    'full_name'     => 'Demo Administrator',
    'role_id'       => $demoAdminRoleId,
    'status'        => 'active',
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
], 'email');

echo "  ✓  admin_users.email = 'admin@demo.test' id={$demoAdminId}\n";

// ── 3. Demo User Role ──────────────────────────────────────────────────────

echo "\n[3/4] Creating demo_user role...\n";

// Check if a user role already exists in the roles table (for front-end users)
$checkUserRole = $pdo->prepare("SELECT id FROM roles WHERE name = 'demo_user' LIMIT 1");
$checkUserRole->execute();
$demoUserRoleId = (int)($checkUserRole->fetchColumn() ?: 0);

if ($demoUserRoleId === 0) {
    $pdo->prepare(
        "INSERT INTO roles (name, description, is_system_role, created_at, updated_at)
         VALUES ('demo_user', 'Read-only demo user — for evaluation purposes only', 0, NOW(), NOW())"
    )->execute();
    $demoUserRoleId = (int)$pdo->lastInsertId();
    echo "  ✓  role 'demo_user' id={$demoUserRoleId}\n";
} else {
    echo "  ℹ  role 'demo_user' already exists id={$demoUserRoleId} — skipping.\n";
}

// ── 4. Demo Front-End User ─────────────────────────────────────────────────

echo "\n[4/4] Creating demo user account...\n";

$checkUser = $pdo->prepare("SELECT id FROM users WHERE email = 'user@demo.test' LIMIT 1");
$checkUser->execute();
$existingUserId = (int)($checkUser->fetchColumn() ?: 0);

if ($existingUserId > 0) {
    echo "  ℹ  users.email = 'user@demo.test' already exists id={$existingUserId} — skipping.\n";
} else {
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $pdo->prepare(
        "INSERT INTO users
            (uuid, username, email, email_verified_at, password_hash, password_algo,
             first_name, last_name, account_type, status, kyc_status, kyc_level,
             referral_code, created_at, updated_at)
         VALUES
            (:uuid, 'demo_user', 'user@demo.test', NOW(), :password_hash, 'argon2id',
             'Demo', 'User', 'individual', 'active', 'unverified', 0,
             'DEMO01', NOW(), NOW())"
    )->execute([
        'uuid'          => $uuid,
        'password_hash' => $passwordHash,
    ]);
    $demoUserId = (int)$pdo->lastInsertId();

    // Create an empty user profile row
    $pdo->prepare(
        "INSERT IGNORE INTO user_profiles (user_id, created_at, updated_at) VALUES (:user_id, NOW(), NOW())"
    )->execute(['user_id' => $demoUserId]);

    echo "  ✓  users.email = 'user@demo.test' id={$demoUserId}\n";
}

// ── Summary ────────────────────────────────────────────────────────────────

echo <<<EOT

════════════════════════════════════════════════════════════
  Demo seeding complete!
════════════════════════════════════════════════════════════

  Demo Admin
    URL      : http://127.0.0.1:8000/admin/dashboard
    Email    : admin@demo.test
    Password : Demo@1234
    Role     : demo_admin (view-only)

  Demo User
    URL      : http://127.0.0.1:8000/dashboard
    Email    : user@demo.test
    Password : Demo@1234
    Role     : demo_user (view-only)

  ⚠  These accounts are for local evaluation only.
     Do NOT use them in a production environment.
════════════════════════════════════════════════════════════

EOT;
