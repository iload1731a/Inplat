<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use App\Libraries\Database;
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
        $this->view('install/step2', ['title' => 'Installer - Database']);
    }

    public function saveDatabase(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $db = [
            'host' => (string)$request->input('host', '127.0.0.1'),
            'port' => (int)$request->input('port', 3306),
            'database' => (string)$request->input('database', 'trading_platform'),
            'username' => (string)$request->input('username', 'root'),
            'password' => (string)$request->input('password', ''),
        ];

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
}
