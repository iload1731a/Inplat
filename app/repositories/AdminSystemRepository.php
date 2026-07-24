<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminSystemRepository
{
    // -----------------------------------------------------------------------
    // Feature Flags
    // -----------------------------------------------------------------------

    public function listFeatureFlags(): array
    {
        $stmt = Database::connection()->query(
            "SELECT ff.id, ff.flag_key, ff.description, ff.is_enabled_globally, ff.rollout_percent,
                    ff.created_at, ff.updated_at,
                    COUNT(DISTINCT ffo.id) AS override_count
             FROM feature_flags ff
             LEFT JOIN feature_flag_overrides ffo ON ffo.feature_flag_id = ff.id
             GROUP BY ff.id, ff.flag_key, ff.description, ff.is_enabled_globally, ff.rollout_percent, ff.created_at, ff.updated_at
             ORDER BY ff.flag_key ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findFeatureFlagById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM feature_flags WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createFeatureFlag(string $key, string $description, bool $enabled, int $rollout): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO feature_flags (flag_key, description, is_enabled_globally, rollout_percent, created_at, updated_at)
             VALUES (:key, :desc, :enabled, :rollout, NOW(), NOW())'
        );
        $stmt->execute([':key' => $key, ':desc' => $description, ':enabled' => (int)$enabled, ':rollout' => $rollout]);
        return (int)$pdo->lastInsertId();
    }

    public function updateFeatureFlag(int $id, string $key, string $description, bool $enabled, int $rollout): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE feature_flags SET flag_key = :key, description = :desc, is_enabled_globally = :enabled, rollout_percent = :rollout, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':key' => $key, ':desc' => $description, ':enabled' => (int)$enabled, ':rollout' => $rollout, ':id' => $id]);
    }

    public function deleteFeatureFlag(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM feature_flags WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Maintenance Windows
    // -----------------------------------------------------------------------

    public function listMaintenanceWindows(): array
    {
        $stmt = Database::connection()->query(
            "SELECT mw.id, mw.title, mw.affected_services, mw.starts_at, mw.ends_at,
                    mw.is_active, mw.created_at,
                    COALESCE(au.full_name, au.username) AS created_by_name
             FROM maintenance_windows mw
             LEFT JOIN admin_users au ON au.id = mw.created_by
             ORDER BY mw.starts_at DESC
             LIMIT 50"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function createMaintenanceWindow(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO maintenance_windows (title, affected_services, starts_at, ends_at, is_active, created_by, created_at)
             VALUES (:title, :affected_services, :starts_at, :ends_at, :is_active, :created_by, NOW())"
        );
        $stmt->execute([
            ':title'             => trim((string)($data['title'] ?? '')),
            ':affected_services' => trim((string)($data['affected_services'] ?? $data['description'] ?? '')),
            ':starts_at'         => (string)($data['starts_at'] ?? $data['scheduled_start'] ?? ''),
            ':ends_at'           => ($data['ends_at'] ?? $data['scheduled_end'] ?? '') !== '' ? ($data['ends_at'] ?? $data['scheduled_end']) : null,
            ':is_active'         => (int)(bool)($data['is_active'] ?? 1),
            ':created_by'        => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateMaintenanceStatus(int $id, bool $isActive): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE maintenance_windows SET is_active = :is_active WHERE id = :id'
        );
        $stmt->bindValue(':is_active', (int)$isActive, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteMaintenanceWindow(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM maintenance_windows WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // System Settings
    // -----------------------------------------------------------------------

    public function listSettings(string $category = ''): array
    {
        $sql = 'SELECT id, setting_key, setting_value, value_type, category, description, is_public
                FROM system_settings WHERE 1=1';
        $params = [];

        if ($category !== '') {
            $sql .= ' AND category = :category';
            $params['category'] = $category;
        }

        $sql .= ' ORDER BY category ASC, setting_key ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function getSettingCategories(): array
    {
        $stmt = Database::connection()->query(
            'SELECT DISTINCT category FROM system_settings ORDER BY category ASC'
        );
        return array_column($stmt->fetchAll() ?: [], 'category');
    }

    public function updateSetting(int $id, string $valueType, ?string $value): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE system_settings SET setting_value = :val, value_type = :type, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':val', $value);
        $stmt->bindValue(':type', $valueType);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function createSetting(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO system_settings (setting_key, setting_value, value_type, category, description, is_public)
             VALUES (:key, :value, :type, :category, :desc, :is_public)"
        );
        $stmt->execute([
            ':key'       => trim((string)($data['setting_key'] ?? '')),
            ':value'     => (string)($data['setting_value'] ?? ''),
            ':type'      => trim((string)($data['value_type'] ?? 'string')),
            ':category'  => trim((string)($data['category'] ?? 'general')),
            ':desc'      => trim((string)($data['description'] ?? '')),
            ':is_public' => (int)(bool)($data['is_public'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }

    // -----------------------------------------------------------------------
    // Price Data Providers
    // -----------------------------------------------------------------------

    public function listPriceDataProviders(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, provider_type, base_url, is_active, priority, created_at FROM price_data_providers ORDER BY priority ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function updateProviderStatus(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE price_data_providers SET is_active = :a WHERE id = :id');
        $stmt->bindValue(':a', (int)$active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Webhooks
    // -----------------------------------------------------------------------

    public function listWebhooks(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, event, target_url, is_active, created_at FROM webhooks ORDER BY id DESC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function createWebhook(string $url, string $event, string $secret): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO webhooks (event, target_url, secret, is_active, created_at)
             VALUES (:event, :url, :secret, 1, NOW())"
        );
        $stmt->execute([':event' => $event, ':url' => $url, ':secret' => $secret]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteWebhook(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM webhooks WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
