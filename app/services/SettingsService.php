<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminManagementRepository;
use App\Repositories\SettingsRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Business-logic layer for the complete System Settings module.
 * Covers: general, company, branding, theme, localization, languages,
 * SMTP, SMS, API integrations, trading config, wallet config,
 * security config, maintenance, backup, cache management, system info.
 */
final class SettingsService
{
    public function __construct(
        private readonly SettingsRepository        $repo    = new SettingsRepository(),
        private readonly AdminManagementRepository $mgmtRepo = new AdminManagementRepository(),
    ) {}

    // -----------------------------------------------------------------------
    // Dashboard / Hub
    // -----------------------------------------------------------------------

    public function hub(): array
    {
        $map = $this->repo->getMap();
        return [
            'siteName'      => $map['site_name'] ?? 'Trading Platform',
            'maintenance'   => ($map['maintenance_mode'] ?? 'false') === 'true',
            'smtpConfigs'   => $this->repo->listSmtpConfigs(),
            'smsConfigs'    => $this->repo->listSmsConfigs(),
            'languages'     => $this->repo->listLanguages(),
            'themes'        => $this->repo->listThemes(),
            'integrations'  => $this->repo->listApiIntegrations(),
            'settingsMap'   => $map,
        ];
    }

    // -----------------------------------------------------------------------
    // General Settings
    // -----------------------------------------------------------------------

    public function generalPage(): array
    {
        return [
            'settings' => $this->repo->getMap(['general']),
        ];
    }

    public function saveGeneral(int $adminId, array $payload): void
    {
        $allowed = [
            'site_name', 'site_tagline', 'site_url', 'support_email', 'support_url',
            'terms_url', 'privacy_url', 'cookie_consent_enabled',
            'registration_enabled', 'maintenance_mode',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        // Normalize booleans from checkbox
        foreach (['cookie_consent_enabled', 'registration_enabled', 'maintenance_mode'] as $bk) {
            if (array_key_exists($bk, $payload)) {
                $data[$bk] = in_array(strtolower((string)($payload[$bk] ?? 'false')), ['1','true','on','yes'], true) ? 'true' : 'false';
            }
        }
        $this->repo->saveGroup('general', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_general_settings', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Company Settings
    // -----------------------------------------------------------------------

    public function companyPage(): array
    {
        return [
            'settings' => $this->repo->getMap(['company']),
        ];
    }

    public function saveCompany(int $adminId, array $payload): void
    {
        $allowed = [
            'company_name', 'company_registration', 'company_address', 'company_city',
            'company_country', 'company_postal_code', 'company_phone', 'company_email', 'company_vat_number',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        $this->repo->saveGroup('company', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_company_settings', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Branding Settings
    // -----------------------------------------------------------------------

    public function brandingPage(): array
    {
        return [
            'settings'     => $this->repo->getMap(['branding']),
            'activeTheme'  => $this->repo->activeTheme(),
        ];
    }

    public function saveBranding(int $adminId, array $payload): void
    {
        $allowed = ['logo_url', 'logo_dark_url', 'favicon_url', 'og_image_url', 'primary_color', 'accent_color'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        $this->repo->saveGroup('branding', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_branding_settings', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Theme Management
    // -----------------------------------------------------------------------

    public function themePage(): array
    {
        return [
            'themes'      => $this->repo->listThemes(),
            'activeTheme' => $this->repo->activeTheme(),
        ];
    }

    public function saveTheme(int $adminId, array $payload): void
    {
        $id = (int)($payload['theme_id'] ?? 0);
        if ($id <= 0) {
            // Create new theme
            $this->repo->createTheme($payload, $adminId);
            $this->mgmtRepo->logAdminAction($adminId, 'create_theme', 'app_themes', null, null, ['name' => $payload['name'] ?? ''], RequestContext::ipAddress());
            return;
        }
        $theme = $this->repo->findThemeById($id);
        if ($theme === null) {
            throw new InvalidArgumentException('Theme not found.');
        }
        $this->repo->saveTheme($id, $payload);
        $this->mgmtRepo->logAdminAction($adminId, 'update_theme', 'app_themes', (string)$id, null, ['name' => $payload['name'] ?? ''], RequestContext::ipAddress());
    }

    public function activateTheme(int $adminId, int $themeId): void
    {
        $theme = $this->repo->findThemeById($themeId);
        if ($theme === null) {
            throw new InvalidArgumentException('Theme not found.');
        }
        $this->repo->activateTheme($themeId);
        $this->mgmtRepo->logAdminAction($adminId, 'activate_theme', 'app_themes', (string)$themeId, null, ['name' => $theme['name']], RequestContext::ipAddress());
    }

    public function deleteTheme(int $adminId, int $themeId): void
    {
        $theme = $this->repo->findThemeById($themeId);
        if ($theme === null) {
            throw new InvalidArgumentException('Theme not found.');
        }
        if ((int)($theme['is_default'] ?? 0) === 1) {
            throw new InvalidArgumentException('Cannot delete the currently active theme. Activate a different theme first.');
        }
        $this->repo->deleteTheme($themeId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_theme', 'app_themes', (string)$themeId, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Localization
    // -----------------------------------------------------------------------

    public function localizationPage(): array
    {
        $map = $this->repo->getMap(['locale']);
        $allTimezones = \DateTimeZone::listIdentifiers();
        sort($allTimezones);
        return [
            'settings'  => $map,
            'timezones' => $allTimezones,
            'languages' => $this->repo->listLanguages(),
        ];
    }

    public function saveLocalization(int $adminId, array $payload): void
    {
        $allowed = [
            'default_language', 'default_timezone', 'default_date_format', 'default_time_format',
            'default_currency_display', 'number_decimal_separator', 'number_thousands_separator',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        if (isset($data['default_timezone']) && !in_array($data['default_timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Invalid timezone: ' . $data['default_timezone']);
        }
        $this->repo->saveGroup('locale', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_localization', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Languages
    // -----------------------------------------------------------------------

    public function languagesPage(): array
    {
        return ['languages' => $this->repo->listLanguages()];
    }

    public function createLanguage(int $adminId, array $payload): int
    {
        $code = strtolower(trim((string)($payload['code'] ?? '')));
        if ($code === '' || !preg_match('/^[a-z]{2,5}(-[A-Z]{2})?$/', $code)) {
            throw new InvalidArgumentException('Invalid language code (e.g. en, fr, zh-CN).');
        }
        if (trim((string)($payload['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Language name is required.');
        }
        $id = $this->repo->createLanguage($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'create_language', 'languages', (string)$id, null, ['code' => $code], RequestContext::ipAddress());
        return $id;
    }

    public function updateLanguage(int $adminId, int $id, array $payload): void
    {
        $lang = $this->repo->findLanguageById($id);
        if ($lang === null) {
            throw new InvalidArgumentException('Language not found.');
        }
        $this->repo->updateLanguage($id, $payload);
        $this->mgmtRepo->logAdminAction($adminId, 'update_language', 'languages', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function deleteLanguage(int $adminId, int $id): void
    {
        $lang = $this->repo->findLanguageById($id);
        if ($lang === null) {
            throw new InvalidArgumentException('Language not found.');
        }
        if ((int)($lang['is_default'] ?? 0) === 1) {
            throw new InvalidArgumentException('Cannot delete the default language. Set another language as default first.');
        }
        $this->repo->deleteLanguage($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_language', 'languages', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function setDefaultLanguage(int $adminId, int $id): void
    {
        $lang = $this->repo->findLanguageById($id);
        if ($lang === null) {
            throw new InvalidArgumentException('Language not found.');
        }
        $this->repo->setDefaultLanguage($id);
        $this->repo->upsert('default_language', (string)$lang['code'], 'string', 'locale', $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'set_default_language', 'languages', (string)$id, null, ['code' => $lang['code']], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // SMTP Configuration
    // -----------------------------------------------------------------------

    public function smtpPage(): array
    {
        return ['smtpConfigs' => $this->repo->listSmtpConfigs()];
    }

    public function saveSmtp(int $adminId, array $payload): int
    {
        $host = trim((string)($payload['host'] ?? ''));
        if ($host === '') {
            throw new InvalidArgumentException('SMTP host is required.');
        }
        $fromEmail = trim((string)($payload['from_email'] ?? ''));
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid From Email address is required.');
        }
        $id = $this->repo->saveSmtpConfig($payload, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_smtp_config', 'smtp_configs', (string)$id, null, ['host' => $host], RequestContext::ipAddress());
        return $id;
    }

    public function setDefaultSmtp(int $adminId, int $id): void
    {
        $this->repo->setDefaultSmtp($id);
        $this->mgmtRepo->logAdminAction($adminId, 'set_default_smtp', 'smtp_configs', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function deleteSmtp(int $adminId, int $id): void
    {
        $this->repo->deleteSmtp($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_smtp_config', 'smtp_configs', (string)$id, null, [], RequestContext::ipAddress());
    }

    /**
     * Send a test email via the specified SMTP config ID.
     * Returns ['ok' => bool, 'message' => string].
     */
    public function testSmtp(int $adminId, int $smtpId, string $toEmail): array
    {
        $config = $this->repo->findSmtpById($smtpId);
        if ($config === null) {
            return ['ok' => false, 'message' => 'SMTP configuration not found.'];
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid test recipient email address.'];
        }

        // Use EmailService to send via this config
        $emailService = new \App\Libraries\EmailService();
        $result = $emailService->sendSmtpTest($config, $toEmail);

        $this->repo->recordSmtpTestResult($smtpId, $result['ok'], $result['message'] ?? '');
        $this->mgmtRepo->logAdminAction($adminId, 'test_smtp', 'smtp_configs', (string)$smtpId, null, ['to' => $toEmail, 'ok' => $result['ok']], RequestContext::ipAddress());

        return $result;
    }

    // -----------------------------------------------------------------------
    // SMS Configuration
    // -----------------------------------------------------------------------

    public function smsPage(): array
    {
        return ['smsConfigs' => $this->repo->listSmsConfigs()];
    }

    public function saveSms(int $adminId, array $payload): int
    {
        if (trim((string)($payload['name'] ?? '')) === '') {
            throw new InvalidArgumentException('SMS configuration name is required.');
        }
        $id = $this->repo->saveSmsConfig($payload, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_sms_config', 'sms_configs', (string)$id, null, ['provider' => $payload['provider'] ?? ''], RequestContext::ipAddress());
        return $id;
    }

    public function setDefaultSms(int $adminId, int $id): void
    {
        $this->repo->setDefaultSms($id);
        $this->mgmtRepo->logAdminAction($adminId, 'set_default_sms', 'sms_configs', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function deleteSms(int $adminId, int $id): void
    {
        $this->repo->deleteSms($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_sms_config', 'sms_configs', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // API Integrations
    // -----------------------------------------------------------------------

    public function apiIntegrationsPage(): array
    {
        $integrations = $this->repo->listApiIntegrations();
        $byCategory   = [];
        foreach ($integrations as $item) {
            $byCategory[(string)($item['category'] ?? 'other')][] = $item;
        }
        return [
            'integrations' => $integrations,
            'byCategory'   => $byCategory,
            'categories'   => ['payment', 'kyc', 'analytics', 'trading', 'social', 'other'],
        ];
    }

    public function saveApiIntegration(int $adminId, array $payload): int
    {
        if (trim((string)($payload['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Integration name is required.');
        }
        if (trim((string)($payload['provider'] ?? '')) === '') {
            throw new InvalidArgumentException('Provider identifier is required.');
        }
        $id = $this->repo->saveApiIntegration($payload, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_api_integration', 'api_integrations', (string)$id, null, ['provider' => $payload['provider'] ?? ''], RequestContext::ipAddress());
        return $id;
    }

    public function deleteApiIntegration(int $adminId, int $id): void
    {
        $this->repo->deleteApiIntegration($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_api_integration', 'api_integrations', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Trading Configuration
    // -----------------------------------------------------------------------

    public function tradingConfigPage(): array
    {
        return ['settings' => $this->repo->getMap(['trading'])];
    }

    public function saveTradingConfig(int $adminId, array $payload): void
    {
        $allowed = [
            'trading_enabled', 'spot_trading_enabled', 'margin_trading_enabled', 'futures_trading_enabled',
            'default_fee_maker', 'default_fee_taker', 'max_open_orders_per_user',
            'order_book_depth', 'price_precision_default', 'quantity_precision_default',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        // Normalize booleans
        foreach (['trading_enabled', 'spot_trading_enabled', 'margin_trading_enabled', 'futures_trading_enabled'] as $bk) {
            if (array_key_exists($bk, $payload)) {
                $data[$bk] = in_array(strtolower((string)($payload[$bk] ?? 'false')), ['1','true','on','yes'], true) ? 'true' : 'false';
            }
        }
        $this->repo->saveGroup('trading', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_trading_config', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Wallet Configuration
    // -----------------------------------------------------------------------

    public function walletConfigPage(): array
    {
        return ['settings' => $this->repo->getMap(['wallet'])];
    }

    public function saveWalletConfig(int $adminId, array $payload): void
    {
        $allowed = [
            'deposit_enabled', 'withdrawal_enabled', 'auto_approve_deposit_usd',
            'withdrawal_review_hours', 'min_withdrawal_usd', 'daily_withdrawal_limit_usd', 'cold_wallet_threshold_pct',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        foreach (['deposit_enabled', 'withdrawal_enabled'] as $bk) {
            if (array_key_exists($bk, $payload)) {
                $data[$bk] = in_array(strtolower((string)($payload[$bk] ?? 'false')), ['1','true','on','yes'], true) ? 'true' : 'false';
            }
        }
        $this->repo->saveGroup('wallet', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_wallet_config', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Security Configuration
    // -----------------------------------------------------------------------

    public function securityConfigPage(): array
    {
        return ['settings' => $this->repo->getMap(['security'])];
    }

    public function saveSecurityConfig(int $adminId, array $payload): void
    {
        $allowed = [
            'session_lifetime_minutes', 'password_min_length', 'password_require_upper',
            'password_require_number', 'password_require_special', 'two_factor_required',
            'two_factor_admin_required', 'ip_whitelist_enabled', 'brute_force_lockout_mins',
            'max_login_attempts', 'cors_allowed_origins',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        foreach ([
            'password_require_upper', 'password_require_number', 'password_require_special',
            'two_factor_required', 'two_factor_admin_required', 'ip_whitelist_enabled',
        ] as $bk) {
            if (array_key_exists($bk, $payload)) {
                $data[$bk] = in_array(strtolower((string)($payload[$bk] ?? 'false')), ['1','true','on','yes'], true) ? 'true' : 'false';
            }
        }
        $this->repo->saveGroup('security', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_security_config', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Maintenance Mode
    // -----------------------------------------------------------------------

    public function maintenancePage(): array
    {
        $map = $this->repo->getMap(['general', 'notifications']);
        return [
            'maintenance_mode' => ($map['maintenance_mode'] ?? 'false') === 'true',
            'settings'         => $map,
        ];
    }

    public function saveMaintenanceMode(int $adminId, bool $enable, string $message = '', string $eta = ''): void
    {
        $this->repo->upsert('maintenance_mode', $enable ? 'true' : 'false', 'boolean', 'general', $adminId);
        if ($message !== '') {
            $this->repo->upsert('maintenance_message', $message, 'string', 'general', $adminId);
        }
        $this->repo->upsert('maintenance_eta', $eta, 'string', 'general', $adminId);
        $this->mgmtRepo->logAdminAction($adminId, $enable ? 'enable_maintenance' : 'disable_maintenance', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Backup
    // -----------------------------------------------------------------------

    public function backupPage(): array
    {
        return [
            'backupLogs'  => $this->repo->listBackupLogs(50),
            'settings'    => $this->repo->getMap(['backup']),
        ];
    }

    public function saveBackupSettings(int $adminId, array $payload): void
    {
        $allowed = [
            'backup_enabled', 'backup_schedule', 'backup_retention_days',
            'backup_storage_path', 'backup_include_files', 'backup_notify_email',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        foreach (['backup_enabled', 'backup_include_files'] as $bk) {
            if (array_key_exists($bk, $payload)) {
                $data[$bk] = in_array(strtolower((string)($payload[$bk] ?? 'false')), ['1','true','on','yes'], true) ? 'true' : 'false';
            }
        }
        $this->repo->saveGroup('backup', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_backup_settings', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    /** Trigger a manual backup. Returns backup log ID. */
    public function runBackup(int $adminId, string $type): int
    {
        $types = ['full', 'database', 'files', 'config'];
        if (!in_array($type, $types, true)) {
            throw new InvalidArgumentException('Invalid backup type.');
        }

        $logId    = $this->repo->createBackupLog($type, 'manual', $adminId);
        $map      = $this->repo->getMap(['backup']);
        $basePath = trim((string)($map['backup_storage_path'] ?? 'storage/backups'));
        $absPath  = app_path($basePath);

        if (!is_dir($absPath)) {
            mkdir($absPath, 0755, true);
        }

        $start    = time();
        $fileName = $type . '_' . date('Ymd_His') . '.sql.gz';
        $filePath = rtrim($basePath, '/') . '/' . $fileName;
        $absFile  = $absPath . '/' . $fileName;

        // Perform database dump
        $ok    = false;
        $error = '';
        if (in_array($type, ['full', 'database'], true)) {
            try {
                $ok = $this->dumpDatabase($absFile);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                $ok    = false;
            }
        } else {
            // For file/config backups write a placeholder (real implementation hooks in deployment env)
            file_put_contents($absFile . '.txt', "Backup type: $type\nDate: " . date('c') . "\n");
            $absFile .= '.txt';
            $ok = true;
        }

        $duration = time() - $start;
        $bytes    = $ok && file_exists($absFile) ? (int)filesize($absFile) : 0;
        $this->repo->completeBackupLog($logId, $ok, $filePath, $bytes, $duration, $error);

        if (!$ok) {
            throw new RuntimeException('Backup failed: ' . $error);
        }

        $this->mgmtRepo->logAdminAction($adminId, 'run_backup', 'backup_logs', (string)$logId, null, ['type' => $type, 'file' => $fileName], RequestContext::ipAddress());
        return $logId;
    }

    private function dumpDatabase(string $targetFile): bool
    {
        // Read DB credentials from environment
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $user = $_ENV['DB_USERNAME'] ?? '';
        $pass = $_ENV['DB_PASSWORD'] ?? '';
        $db   = $_ENV['DB_DATABASE'] ?? '';

        if ($db === '') {
            throw new RuntimeException('Database name not configured in environment.');
        }

        // Pass password via MYSQL_PWD env variable to avoid exposure in process list
        $cmd = sprintf(
            'mysqldump -h %s -P %s -u %s %s | gzip > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($targetFile)
        );

        $env = $_ENV;
        if ($pass !== '') {
            $env['MYSQL_PWD'] = $pass;
        }

        // Use proc_open to pass env variables cleanly without exposing credentials in shell args
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $envPairs = [];
        foreach ($env as $k => $v) {
            if (is_string($v) || is_numeric($v)) {
                $envPairs[] = $k . '=' . $v;
            }
        }

        $proc = proc_open($cmd, $descriptors, $pipes, null, $envPairs);
        if ($proc === false) {
            throw new RuntimeException('Failed to start mysqldump process.');
        }
        fclose($pipes[0]);
        $stderr     = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($proc);

        if ($returnCode !== 0) {
            throw new RuntimeException('mysqldump failed (exit ' . $returnCode . '): ' . trim($stderr));
        }
        return true;
    }

    public function deleteBackupLog(int $adminId, int $logId): void
    {
        $this->repo->deleteBackupLog($logId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_backup_log', 'backup_logs', (string)$logId, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Cache Management
    // -----------------------------------------------------------------------

    public function cachePage(): array
    {
        return [
            'cacheLogs' => $this->repo->listCacheLogs(30),
            'settings'  => $this->repo->getMap(['cache']),
            'cacheInfo' => $this->getCacheInfo(),
        ];
    }

    public function saveCacheConfig(int $adminId, array $payload): void
    {
        $allowed = ['cache_driver', 'redis_host', 'redis_port', 'redis_password', 'redis_database', 'cache_ttl_seconds'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string)$payload[$key]);
            }
        }
        $this->repo->saveGroup('cache', $data, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'save_cache_config', 'system_settings', null, null, [], RequestContext::ipAddress());
    }

    public function flushCache(int $adminId, string $cacheType): array
    {
        $allowed = ['all', 'settings', 'users', 'prices', 'sessions', 'opcache'];
        if (!in_array($cacheType, $allowed, true)) {
            throw new InvalidArgumentException('Invalid cache type.');
        }

        $cleared = 0;
        $message = '';

        if (in_array($cacheType, ['all', 'opcache'], true) && function_exists('opcache_reset')) {
            opcache_reset();
            $message .= 'OPcache cleared. ';
            $cleared++;
        }

        if (in_array($cacheType, ['all', 'sessions'], true)) {
            // Clear session cache files (file-based)
            $sessionPath = session_save_path() ?: sys_get_temp_dir();
            $files       = glob($sessionPath . '/sess_*') ?: [];
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                    $cleared++;
                }
            }
            $message .= 'Session files cleared. ';
        }

        if (in_array($cacheType, ['all', 'settings', 'prices', 'users'], true)) {
            // Clear any file-based cache in storage/cache/
            $cachePath = app_path('storage/cache');
            if (is_dir($cachePath)) {
                $files = glob($cachePath . '/*.cache') ?: [];
                foreach ($files as $f) {
                    @unlink($f);
                    $cleared++;
                }
            }
            $message .= 'Application cache cleared. ';
        }

        $this->repo->logCacheFlush($cacheType, $adminId, $cleared, trim($message));
        $this->mgmtRepo->logAdminAction($adminId, 'flush_cache', 'cache_logs', null, null, ['type' => $cacheType, 'cleared' => $cleared], RequestContext::ipAddress());

        return ['cleared' => $cleared, 'message' => trim($message) ?: 'Cache flushed.'];
    }

    private function getCacheInfo(): array
    {
        $info = [
            'opcache_enabled'    => false,
            'opcache_memory_mb'  => 0,
            'opcache_hit_rate'   => 0,
            'opcache_scripts'    => 0,
            'session_path'       => session_save_path() ?: sys_get_temp_dir(),
            'session_files'      => 0,
        ];

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status)) {
                $info['opcache_enabled']   = (bool)($status['opcache_enabled'] ?? false);
                $mem = $status['memory_usage'] ?? [];
                $used = ($mem['used_memory'] ?? 0) + ($mem['wasted_memory'] ?? 0);
                $info['opcache_memory_mb'] = round($used / 1024 / 1024, 1);
                $stats = $status['opcache_statistics'] ?? [];
                $hits  = (int)($stats['hits'] ?? 0);
                $total = $hits + (int)($stats['misses'] ?? 0);
                $info['opcache_hit_rate']  = $total > 0 ? round($hits / $total * 100, 1) : 0;
                $info['opcache_scripts']   = (int)($stats['num_cached_scripts'] ?? 0);
            }
        }

        $sessionPath = $info['session_path'];
        if (is_dir($sessionPath)) {
            $info['session_files'] = count(glob($sessionPath . '/sess_*') ?: []);
        }

        return $info;
    }

    // -----------------------------------------------------------------------
    // System Information
    // -----------------------------------------------------------------------

    public function systemInfoPage(): array
    {
        return [
            'phpVersion'   => PHP_VERSION,
            'phpSapi'      => PHP_SAPI,
            'osInfo'       => php_uname(),
            'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'mysqlVersion' => $this->repo->getMySQLVersion(),
            'extensions'   => $this->repo->phpExtensions(),
            'stats'        => $this->repo->systemStats(),
            'tableList'    => $this->repo->getTableList(),
            'memoryLimit'  => ini_get('memory_limit'),
            'maxExecTime'  => ini_get('max_execution_time'),
            'uploadMaxSize'=> ini_get('upload_max_filesize'),
            'postMaxSize'  => ini_get('post_max_size'),
            'timezone'     => date_default_timezone_get(),
            'diskFreeBytes'=> disk_free_space('/'),
            'diskTotalBytes'=> disk_total_space('/'),
        ];
    }
}
