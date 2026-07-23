<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\SettingsService;
use Throwable;

final class SettingsController extends AdminBaseController
{
    private function svc(): SettingsService
    {
        return new SettingsService();
    }

    // -----------------------------------------------------------------------
    // Hub
    // -----------------------------------------------------------------------

    public function hub(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->hub();
        $this->view('admin/settings/hub', [
            'title'        => 'Admin · Settings',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    // -----------------------------------------------------------------------
    // General Settings
    // -----------------------------------------------------------------------

    public function general(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->generalPage();
        $this->view('admin/settings/general', [
            'title'        => 'Admin · General Settings',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveGeneral(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveGeneral($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'General settings saved.', 'redirect' => '/admin/settings/general']);
    }

    // -----------------------------------------------------------------------
    // Company Settings
    // -----------------------------------------------------------------------

    public function company(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->companyPage();
        $this->view('admin/settings/company', [
            'title'        => 'Admin · Company Settings',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveCompany(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveCompany($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Company settings saved.', 'redirect' => '/admin/settings/company']);
    }

    // -----------------------------------------------------------------------
    // Branding
    // -----------------------------------------------------------------------

    public function branding(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->brandingPage();
        $this->view('admin/settings/branding', [
            'title'        => 'Admin · Branding',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveBranding(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveBranding($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Branding settings saved.', 'redirect' => '/admin/settings/branding']);
    }

    // -----------------------------------------------------------------------
    // Theme Management
    // -----------------------------------------------------------------------

    public function theme(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->themePage();
        $this->view('admin/settings/theme', [
            'title'        => 'Admin · Theme Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveTheme(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveTheme($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Theme saved.', 'redirect' => '/admin/settings/theme']);
    }

    public function activateTheme(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('theme_id', 0);
        try {
            $this->svc()->activateTheme($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Theme activated.', 'redirect' => '/admin/settings/theme']);
    }

    public function deleteTheme(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('theme_id', 0);
        try {
            $this->svc()->deleteTheme($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Theme deleted.', 'redirect' => '/admin/settings/theme']);
    }

    // -----------------------------------------------------------------------
    // Localization
    // -----------------------------------------------------------------------

    public function localization(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->localizationPage();
        $this->view('admin/settings/localization', [
            'title'        => 'Admin · Localization',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveLocalization(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveLocalization($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Localization settings saved.', 'redirect' => '/admin/settings/localization']);
    }

    // -----------------------------------------------------------------------
    // Languages
    // -----------------------------------------------------------------------

    public function languages(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->languagesPage();
        $this->view('admin/settings/languages', [
            'title'        => 'Admin · Languages',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function createLanguage(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createLanguage($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Language added.', 'redirect' => '/admin/settings/languages']);
    }

    public function updateLanguage(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('language_id', 0);
        try {
            $this->svc()->updateLanguage($this->adminId(), $id, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Language updated.', 'redirect' => '/admin/settings/languages']);
    }

    public function deleteLanguage(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('language_id', 0);
        try {
            $this->svc()->deleteLanguage($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Language deleted.', 'redirect' => '/admin/settings/languages']);
    }

    public function setDefaultLanguage(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('language_id', 0);
        try {
            $this->svc()->setDefaultLanguage($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Default language updated.', 'redirect' => '/admin/settings/languages']);
    }

    // -----------------------------------------------------------------------
    // SMTP Configuration
    // -----------------------------------------------------------------------

    public function smtp(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->smtpPage();
        $this->view('admin/settings/smtp', [
            'title'        => 'Admin · SMTP Configuration',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveSmtp(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveSmtp($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'SMTP configuration saved.', 'redirect' => '/admin/settings/smtp']);
    }

    public function setDefaultSmtp(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('smtp_id', 0);
        try {
            $this->svc()->setDefaultSmtp($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Default SMTP profile updated.', 'redirect' => '/admin/settings/smtp']);
    }

    public function deleteSmtp(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('smtp_id', 0);
        try {
            $this->svc()->deleteSmtp($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'SMTP profile deleted.', 'redirect' => '/admin/settings/smtp']);
    }

    public function testSmtp(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id      = (int)$request->input('smtp_id', 0);
        $toEmail = trim((string)$request->input('test_email', ''));
        try {
            $result = $this->svc()->testSmtp($this->adminId(), $id, $toEmail);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json($result);
    }

    // -----------------------------------------------------------------------
    // SMS Configuration
    // -----------------------------------------------------------------------

    public function sms(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->smsPage();
        $this->view('admin/settings/sms', [
            'title'        => 'Admin · SMS Configuration',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveSms(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveSms($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'SMS configuration saved.', 'redirect' => '/admin/settings/sms']);
    }

    public function setDefaultSms(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('sms_id', 0);
        try {
            $this->svc()->setDefaultSms($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Default SMS profile updated.', 'redirect' => '/admin/settings/sms']);
    }

    public function deleteSms(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('sms_id', 0);
        try {
            $this->svc()->deleteSms($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'SMS profile deleted.', 'redirect' => '/admin/settings/sms']);
    }

    // -----------------------------------------------------------------------
    // API Integrations
    // -----------------------------------------------------------------------

    public function apiIntegrations(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->apiIntegrationsPage();
        $this->view('admin/settings/api', [
            'title'        => 'Admin · API Integrations',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveApiIntegration(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveApiIntegration($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'API integration saved.', 'redirect' => '/admin/settings/api']);
    }

    public function deleteApiIntegration(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('integration_id', 0);
        try {
            $this->svc()->deleteApiIntegration($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'API integration deleted.', 'redirect' => '/admin/settings/api']);
    }

    // -----------------------------------------------------------------------
    // Trading Configuration
    // -----------------------------------------------------------------------

    public function tradingConfig(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->tradingConfigPage();
        $this->view('admin/settings/trading', [
            'title'        => 'Admin · Trading Configuration',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveTradingConfig(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveTradingConfig($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Trading configuration saved.', 'redirect' => '/admin/settings/trading']);
    }

    // -----------------------------------------------------------------------
    // Wallet Configuration
    // -----------------------------------------------------------------------

    public function walletConfig(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->walletConfigPage();
        $this->view('admin/settings/wallet', [
            'title'        => 'Admin · Wallet Configuration',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveWalletConfig(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveWalletConfig($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Wallet configuration saved.', 'redirect' => '/admin/settings/wallet']);
    }

    // -----------------------------------------------------------------------
    // Security Configuration
    // -----------------------------------------------------------------------

    public function securityConfig(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->securityConfigPage();
        $this->view('admin/settings/security', [
            'title'        => 'Admin · Security Configuration',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveSecurityConfig(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveSecurityConfig($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Security configuration saved.', 'redirect' => '/admin/settings/security']);
    }

    // -----------------------------------------------------------------------
    // Maintenance Mode
    // -----------------------------------------------------------------------

    public function maintenance(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->maintenancePage();
        $this->view('admin/settings/maintenance', [
            'title'        => 'Admin · Maintenance Mode',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveMaintenance(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $enable  = in_array(strtolower((string)$request->input('maintenance_mode', 'false')), ['1','true','on','yes'], true);
        $message = trim((string)$request->input('maintenance_message', ''));
        $eta     = trim((string)$request->input('maintenance_eta', ''));
        try {
            $this->svc()->saveMaintenanceMode($this->adminId(), $enable, $message, $eta);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Maintenance mode ' . ($enable ? 'enabled' : 'disabled') . '.', 'redirect' => '/admin/settings/maintenance']);
    }

    // -----------------------------------------------------------------------
    // Backup
    // -----------------------------------------------------------------------

    public function backup(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->backupPage();
        $this->view('admin/settings/backup', [
            'title'        => 'Admin · Backup Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveBackupSettings(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveBackupSettings($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Backup settings saved.', 'redirect' => '/admin/settings/backup']);
    }

    public function runBackup(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $type = trim((string)$request->input('backup_type', 'database'));
        try {
            $logId = $this->svc()->runBackup($this->adminId(), $type);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Backup completed successfully.', 'redirect' => '/admin/settings/backup']);
    }

    public function deleteBackupLog(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('log_id', 0);
        try {
            $this->svc()->deleteBackupLog($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Backup log deleted.', 'redirect' => '/admin/settings/backup']);
    }

    // -----------------------------------------------------------------------
    // Cache Management
    // -----------------------------------------------------------------------

    public function cache(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->cachePage();
        $this->view('admin/settings/cache', [
            'title'        => 'Admin · Cache Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }

    public function saveCacheConfig(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveCacheConfig($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Cache configuration saved.', 'redirect' => '/admin/settings/cache']);
    }

    public function flushCache(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $cacheType = trim((string)$request->input('cache_type', 'all'));
        try {
            $result = $this->svc()->flushCache($this->adminId(), $cacheType);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => $result['message'] ?? 'Cache flushed.', 'cleared' => $result['cleared'] ?? 0]);
    }

    // -----------------------------------------------------------------------
    // System Information
    // -----------------------------------------------------------------------

    public function systemInfo(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->systemInfoPage();
        $this->view('admin/settings/system-info', [
            'title'        => 'Admin · System Information',
            'username'     => $this->adminUsername(),
            'adminSection' => 'settings',
            ...$data,
        ]);
    }
}
