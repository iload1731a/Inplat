<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminSystemRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminSystemService
{
    public function __construct(
        private readonly AdminSystemRepository     $systemRepo = new AdminSystemRepository(),
        private readonly AdminManagementRepository $mgmtRepo   = new AdminManagementRepository(),
    ) {}

    public function systemIndex(string $tab): array
    {
        return [
            'featureFlags'        => $this->systemRepo->listFeatureFlags(),
            'maintenanceWindows'  => $this->systemRepo->listMaintenanceWindows(),
            'settings'            => $this->systemRepo->listSettings(),
            'settingCategories'   => $this->systemRepo->getSettingCategories(),
            'priceProviders'      => $this->systemRepo->listPriceDataProviders(),
            'webhooks'            => $this->systemRepo->listWebhooks(),
            'activeTab'           => $tab,
        ];
    }

    // -----------------------------------------------------------------------
    // Feature Flags
    // -----------------------------------------------------------------------

    public function createFeatureFlag(int $adminId, array $payload): int
    {
        $key = trim((string)($payload['flag_key'] ?? ''));
        if ($key === '' || !preg_match('/^[a-z0-9_\.\-]+$/', $key)) {
            throw new InvalidArgumentException('Flag key must be lowercase alphanumeric with dots/dashes/underscores.');
        }

        $id = $this->systemRepo->createFeatureFlag(
            $key,
            trim((string)($payload['description'] ?? '')),
            (bool)(int)($payload['is_enabled'] ?? 0),
            min(100, max(0, (int)($payload['rollout_percentage'] ?? 100))),
        );

        $this->mgmtRepo->logAdminAction($adminId, 'create_feature_flag', 'feature_flags', (string)$id, null, ['key' => $key], RequestContext::ipAddress());
        return $id;
    }

    public function updateFeatureFlag(int $adminId, int $id, array $payload): void
    {
        $flag = $this->systemRepo->findFeatureFlagById($id);
        if ($flag === null) {
            throw new InvalidArgumentException('Feature flag not found.');
        }

        $key = trim((string)($payload['flag_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Flag key is required.');
        }

        $this->systemRepo->updateFeatureFlag(
            $id,
            $key,
            trim((string)($payload['description'] ?? '')),
            (bool)(int)($payload['is_enabled'] ?? 0),
            min(100, max(0, (int)($payload['rollout_percentage'] ?? 100))),
        );

        $this->mgmtRepo->logAdminAction($adminId, 'update_feature_flag', 'feature_flags', (string)$id, null, ['key' => $key], RequestContext::ipAddress());
    }

    public function deleteFeatureFlag(int $adminId, int $id): void
    {
        $flag = $this->systemRepo->findFeatureFlagById($id);
        if ($flag === null) {
            throw new InvalidArgumentException('Feature flag not found.');
        }

        $this->systemRepo->deleteFeatureFlag($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_feature_flag', 'feature_flags', (string)$id, null, ['key' => $flag['flag_key']], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Maintenance Windows
    // -----------------------------------------------------------------------

    public function createMaintenanceWindow(int $adminId, array $payload): int
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Maintenance window title is required.');
        }

        if (($payload['scheduled_start'] ?? '') === '') {
            throw new InvalidArgumentException('Scheduled start datetime is required.');
        }

        $id = $this->systemRepo->createMaintenanceWindow($payload, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'create_maintenance', 'maintenance_windows', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
        return $id;
    }

    public function updateMaintenanceStatus(int $adminId, int $id, string $status): void
    {
        $allowed = ['scheduled', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid maintenance status.');
        }

        $this->systemRepo->updateMaintenanceStatus($id, $status);
        $this->mgmtRepo->logAdminAction($adminId, 'update_maintenance_status', 'maintenance_windows', (string)$id, null, ['status' => $status], RequestContext::ipAddress());
    }

    public function deleteMaintenanceWindow(int $adminId, int $id): void
    {
        $this->systemRepo->deleteMaintenanceWindow($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_maintenance', 'maintenance_windows', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------------

    public function updateSetting(int $adminId, int $settingId, string $valueType, ?string $value): void
    {
        $this->systemRepo->updateSetting($settingId, $valueType, $value);
        $this->mgmtRepo->logAdminAction($adminId, 'update_setting', 'system_settings', (string)$settingId, null, ['value_type' => $valueType], RequestContext::ipAddress());
    }

    public function createSetting(int $adminId, array $payload): int
    {
        $key = trim((string)($payload['setting_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Setting key is required.');
        }

        $id = $this->systemRepo->createSetting($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'create_setting', 'system_settings', (string)$id, null, ['key' => $key], RequestContext::ipAddress());
        return $id;
    }

    // -----------------------------------------------------------------------
    // Price Providers
    // -----------------------------------------------------------------------

    public function toggleProvider(int $adminId, int $id, bool $active): void
    {
        $this->systemRepo->updateProviderStatus($id, $active);
        $this->mgmtRepo->logAdminAction($adminId, $active ? 'enable_price_provider' : 'disable_price_provider', 'price_data_providers', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Webhooks
    // -----------------------------------------------------------------------

    public function createWebhook(int $adminId, string $url, string $events): int
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid webhook URL.');
        }

        $secret = bin2hex(random_bytes(24));
        $hash   = hash('sha256', $secret);
        $id     = $this->systemRepo->createWebhook($url, $events, $hash);
        $this->mgmtRepo->logAdminAction($adminId, 'create_webhook', 'webhooks', (string)$id, null, ['url' => $url], RequestContext::ipAddress());
        return $id;
    }

    public function deleteWebhook(int $adminId, int $id): void
    {
        $this->systemRepo->deleteWebhook($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_webhook', 'webhooks', (string)$id, null, [], RequestContext::ipAddress());
    }
}
