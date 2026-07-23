<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use App\Libraries\Database;
use App\Libraries\LicenseGuard;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use PDO;
use Throwable;

final class InstallerController extends BaseController
{
    public function step1(Request $request): void
    {
        $requirements = [
            'PHP 8.3+' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'PDO extension' => extension_loaded('pdo'),
            'PDO MySQL extension' => extension_loaded('pdo_mysql'),
            'OpenSSL extension' => extension_loaded('openssl'),
            'MBString extension' => extension_loaded('mbstring'),
            'JSON extension' => extension_loaded('json'),
            'Storage writable' => is_writable(app_path('storage')),
            'Uploads writable' => is_writable(app_path('storage/uploads')),
        ];

        $this->view('install/step1', ['title' => 'Installer - Requirements', 'requirements' => $requirements]);
    }

    public function step2(Request $request): void
    {
        $domain = LicenseGuard::normalizeDomain((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));

        $this->view('install/step2', [
            'title' => 'Installer - Database & License',
            'detectedDomain' => $domain,
            'demoMode' => (bool)config('app.demo_mode'),
            'ownerLicenseEnabled' => LicenseGuard::ownerLicenseEnabled(),
        ]);
    }

    public function saveDatabase(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $demoMode = (bool)config('app.demo_mode');

        $db = [
            'host' => (string)$request->input('host', '127.0.0.1'),
            'port' => (int)$request->input('port', 3306),
            'database' => (string)$request->input('database', 'trading_platform'),
            'username' => (string)$request->input('username', 'root'),
            'password' => (string)$request->input('password', ''),
        ];
        $licenseType = LicenseGuard::normalizeLicenseType((string)$request->input('license_type', LicenseGuard::TYPE_CODECANYON));
        $license = [
            'type' => $licenseType,
            'buyer_name' => LicenseGuard::normalizeBuyerName((string)$request->input('buyer_name', '')),
            'buyer_email' => trim((string)$request->input('buyer_email', '')),
            'purchase_code' => trim((string)$request->input('purchase_code', '')),
            'provider_name' => LicenseGuard::normalizeIdentityName((string)$request->input('provider_name', '')),
            'third_party_license_key' => trim((string)$request->input('third_party_license_key', '')),
            'owner_name' => LicenseGuard::normalizeIdentityName((string)$request->input('owner_name', '')),
            'owner_email' => trim((string)$request->input('owner_email', '')),
            'owner_install_token' => (string)$request->input('owner_install_token', ''),
            'domain' => LicenseGuard::normalizeDomain((string)$request->input('domain', (string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''))),
        ];

        if (!$demoMode) {
            if (!LicenseGuard::isSupportedLicenseType($licenseType)) {
                Response::json(['ok' => false, 'message' => 'Unsupported license source selected.'], 422);
            }

            if ($license['domain'] === '') {
                Response::json(['ok' => false, 'message' => 'Licensed domain is required.'], 422);
            }
        }

        $result = Database::testConnection($db);

        if (!$result['ok']) {
            Response::json(['ok' => false, 'message' => $result['message']], 422);
        }

        $configDir = app_path('storage/config');
        if (!is_dir($configDir) && !mkdir($configDir, 0750, true) && !is_dir($configDir)) {
            Response::json(['ok' => false, 'message' => 'Unable to create storage/config directory.'], 500);
        }
        if (!is_writable($configDir)) {
            Response::json(['ok' => false, 'message' => 'storage/config directory must be writable.'], 500);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($db, true) . ";\n";
        $databaseConfigPath = app_path('storage/config/database.php');
        if (file_put_contents($databaseConfigPath, $content, LOCK_EX) === false) {
            Response::json(['ok' => false, 'message' => 'Unable to save database configuration file.'], 500);
        }
        if (!chmod($databaseConfigPath, 0600)) {
            Response::json(['ok' => false, 'message' => 'Unable to secure database configuration file permissions.'], 500);
        }

        if (!$demoMode) {
            LicenseGuard::ensureSecretFile();

            try {
                $licensePayload = $this->buildLicensePayload($licenseType, $license);
            } catch (Throwable $e) {
                Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            $licenseContent = json_encode($licensePayload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $licenseConfigPath = (string)config('app.license_file');
            if (!is_string($licenseContent) || file_put_contents($licenseConfigPath, $licenseContent, LOCK_EX) === false) {
                Response::json(['ok' => false, 'message' => 'Unable to save license configuration file.'], 500);
            }
            if (!chmod($licenseConfigPath, 0600)) {
                Response::json(['ok' => false, 'message' => 'Unable to secure license configuration file permissions.'], 500);
            }
        }

        Response::json(['ok' => true, 'redirect' => '/install/step3']);
    }

    public function step3(Request $request): void
    {
        $this->view('install/step3', ['title' => 'Installer - Import SQL']);
    }

    public function importSchema(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $schemaFile = (string)config('app.schema_file');

        if (!is_file($schemaFile)) {
            Response::json(['ok' => false, 'message' => 'Schema file not found.'], 422);
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            Response::json(['ok' => false, 'message' => 'Unable to read schema file.'], 500);
        }

        try {
            $pdo = Database::serverConnection((array)config('database'));
            foreach ($this->splitSqlStatements($sql) as $statement) {
                $this->assertSchemaStatementAllowed($statement);
                $pdo->exec($statement);
            }
            Response::json(['ok' => true, 'redirect' => '/install/step4']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function step4(Request $request): void
    {
        $this->view('install/step4', ['title' => 'Installer - Create Admin']);
    }

    public function createAdmin(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $username = trim((string)$request->input('username', 'admin'));
        $email = trim((string)$request->input('email', 'admin@example.com'));
        $password = (string)$request->input('password', '');

        if (strlen($password) < 8) {
            Response::json(['ok' => false, 'message' => 'Admin password must be at least 8 characters.'], 422);
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();

            $roleStmt = $pdo->prepare('INSERT IGNORE INTO roles (name, description, is_system_role, created_at, updated_at)
                VALUES (:name, :description, 1, NOW(), NOW())');
            $roleStmt->execute(['name' => 'super_admin', 'description' => 'System Super Administrator']);

            $roleIdStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
            $roleIdStmt->execute(['name' => 'super_admin']);
            $roleId = (int)($roleIdStmt->fetchColumn() ?: 0);
            if ($roleId <= 0) {
                throw new \RuntimeException('Unable to resolve super_admin role id.');
            }

            $adminStmt = $pdo->prepare('INSERT INTO admin_users (username, email, password_hash, full_name, role_id, status, created_at, updated_at)
                VALUES (:username, :email, :password_hash, :full_name, :role_id, :status, NOW(), NOW())');
            $passwordHash = password_hash($password, password_algo());
            if ($passwordHash === false) {
                throw new \RuntimeException('Password hashing failed.');
            }

            $adminStmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'full_name' => 'Platform Administrator',
                'role_id' => $roleId,
                'status' => 'active',
            ]);

            if (!(bool)config('app.demo_mode')) {
                $this->persistLicenseSettings($pdo);
            }

            $pdo->commit();
            Response::json(['ok' => true, 'redirect' => '/install/step5']);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function step5(Request $request): void
    {
        if (file_put_contents((string)config('app.installed_lock'), 'installed=' . date('c'), LOCK_EX) === false) {
            Response::json(['ok' => false, 'message' => 'Unable to write installation lock file.'], 500);
        }
        Session::put('flash.success', 'Installation complete. You can login now.');

        $this->view('install/step5', ['title' => 'Installer - Completed']);
    }

    private function splitSqlStatements(string $sql): array
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

    private function assertSchemaStatementAllowed(string $statement): void
    {
        $normalized = ltrim($statement);
        $prefix = strtoupper((string)strtok($normalized, " \n\t\r"));
        $allowed = ['SET', 'CREATE', 'USE', 'INSERT'];
        $disallowedPattern = '/\\b(DROP|DELETE|TRUNCATE|RENAME|GRANT|REVOKE)\\b/i';

        if (!in_array($prefix, $allowed, true) || preg_match($disallowedPattern, $normalized) === 1) {
            throw new \RuntimeException('Unsupported SQL statement in schema import: ' . $prefix);
        }
    }

    private function persistLicenseSettings(PDO $pdo): void
    {
        $licensePath = (string)config('app.license_file');
        if (!is_file($licensePath)) {
            throw new \RuntimeException('License configuration file is missing.');
        }

        $licenseJson = file_get_contents($licensePath);
        $license = is_string($licenseJson) ? json_decode($licenseJson, true) : null;
        if (!is_array($license)) {
            throw new \RuntimeException('Invalid license configuration format.');
        }

        $licenseType = LicenseGuard::normalizeLicenseType((string)($license['type'] ?? LicenseGuard::TYPE_CODECANYON));
        if (!LicenseGuard::isSupportedLicenseType($licenseType)) {
            throw new \RuntimeException('Unsupported license type in configuration.');
        }

        $values = [
            'license_type' => $licenseType,
            'license_domain' => (string)$license['domain'],
            'license_verified_at' => date('Y-m-d H:i:s'),
        ];

        if ($licenseType === LicenseGuard::TYPE_CODECANYON) {
            $required = ['buyer_name', 'buyer_email', 'purchase_code_hash', 'domain'];
            foreach ($required as $field) {
                if (!array_key_exists($field, $license) || trim((string)$license[$field]) === '') {
                    throw new \RuntimeException('CodeCanyon license configuration is incomplete.');
                }
            }

            $values['license_buyer_name'] = (string)$license['buyer_name'];
            $values['license_buyer_email'] = (string)$license['buyer_email'];
            $values['license_purchase_code_hash'] = (string)$license['purchase_code_hash'];
        } elseif ($licenseType === LicenseGuard::TYPE_THIRD_PARTY) {
            $required = ['provider_name', 'license_key_hash', 'domain'];
            foreach ($required as $field) {
                if (!array_key_exists($field, $license) || trim((string)$license[$field]) === '') {
                    throw new \RuntimeException('Third-party license configuration is incomplete.');
                }
            }

            $values['license_provider_name'] = (string)$license['provider_name'];
            $values['license_key_hash'] = (string)$license['license_key_hash'];
        } elseif ($licenseType === LicenseGuard::TYPE_OWNER) {
            $required = ['owner_name', 'owner_email', 'owner_identity_hash', 'domain'];
            foreach ($required as $field) {
                if (!array_key_exists($field, $license) || trim((string)$license[$field]) === '') {
                    throw new \RuntimeException('Owner license configuration is incomplete.');
                }
            }

            $values['license_owner_name'] = (string)$license['owner_name'];
            $values['license_owner_email'] = (string)$license['owner_email'];
            $values['license_owner_identity_hash'] = (string)$license['owner_identity_hash'];
        }

        $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, value_type, category, description, is_public, updated_at)
            VALUES (:setting_key, :setting_value, :value_type, :category, :description, :is_public, NOW()) AS incoming
            ON DUPLICATE KEY UPDATE setting_value = incoming.setting_value, value_type = incoming.value_type, category = incoming.category, description = incoming.description, updated_at = NOW()');

        foreach ($values as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => $value,
                'value_type' => 'string',
                'category' => 'license',
                'description' => 'License metadata',
                'is_public' => false,
            ]);
        }
    }

    private function buildLicensePayload(string $licenseType, array $license): array
    {
        return match ($licenseType) {
            LicenseGuard::TYPE_CODECANYON => $this->buildCodecanyonPayload($license),
            LicenseGuard::TYPE_THIRD_PARTY => $this->buildThirdPartyPayload($license),
            LicenseGuard::TYPE_OWNER => $this->buildOwnerPayload($license),
            default => throw new \RuntimeException('Unsupported license type.'),
        };
    }

    private function buildCodecanyonPayload(array $license): array
    {
        if ($license['buyer_name'] === '' || $license['buyer_email'] === '' || $license['purchase_code'] === '' || $license['domain'] === '') {
            throw new \RuntimeException('CodeCanyon license fields are required.');
        }

        if (filter_var($license['buyer_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('CodeCanyon license email is invalid.');
        }

        if (!LicenseGuard::isValidBuyerName($license['buyer_name'])) {
            throw new \RuntimeException('CodeCanyon buyer name is invalid.');
        }

        if (!LicenseGuard::isValidPurchaseCode($license['purchase_code'])) {
            throw new \RuntimeException('Invalid CodeCanyon purchase code format.');
        }

        $payload = LicenseGuard::pack($license['purchase_code'], $license['domain']);
        $payload['buyer_name'] = $license['buyer_name'];
        $payload['buyer_email'] = $license['buyer_email'];
        $payload['purchase_code_hash'] = LicenseGuard::purchaseCodeHash($license['purchase_code']);
        return $payload;
    }

    private function buildThirdPartyPayload(array $license): array
    {
        if ($license['provider_name'] === '' || $license['third_party_license_key'] === '' || $license['domain'] === '') {
            throw new \RuntimeException('Third-party license fields are required.');
        }

        if (!LicenseGuard::isValidIdentityName($license['provider_name'])) {
            throw new \RuntimeException('Third-party provider name is invalid.');
        }

        if (!LicenseGuard::isValidThirdPartyLicenseKey($license['third_party_license_key'])) {
            throw new \RuntimeException('Third-party license key format is invalid.');
        }

        return LicenseGuard::packThirdParty(
            $license['provider_name'],
            $license['third_party_license_key'],
            $license['domain']
        );
    }

    private function buildOwnerPayload(array $license): array
    {
        if (!LicenseGuard::ownerLicenseEnabled()) {
            throw new \RuntimeException('Owner license mode is disabled.');
        }

        if ($license['owner_name'] === '' || $license['owner_email'] === '' || $license['owner_install_token'] === '' || $license['domain'] === '') {
            throw new \RuntimeException('Owner license fields are required.');
        }

        if (filter_var($license['owner_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Owner email is invalid.');
        }

        if (!LicenseGuard::isValidIdentityName($license['owner_name'])) {
            throw new \RuntimeException('Owner name is invalid.');
        }

        if (!LicenseGuard::validateOwnerInstallToken($license['owner_install_token'])) {
            throw new \RuntimeException('Owner install token is invalid.');
        }

        return LicenseGuard::packOwner(
            $license['owner_name'],
            $license['owner_email'],
            $license['domain']
        );
    }
}
