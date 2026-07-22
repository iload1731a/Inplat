<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserApiKeysRepository
{
    public function keys(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, label, api_key, permissions, ip_whitelist, is_active,
                    last_used_at, expires_at, created_at, revoked_at
             FROM api_keys
             WHERE user_id = :uid AND revoked_at IS NULL
             ORDER BY id DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function create(int $userId, string $label, string $apiKey, string $secretHash, string $permissions, ?string $ipWhitelist, ?string $expiresAt): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO api_keys
                (user_id, label, api_key, api_secret_hash, permissions, ip_whitelist,
                 is_active, created_at, expires_at)
             VALUES
                (:uid, :label, :api_key, :secret_hash, :permissions, :ip_whitelist,
                 1, NOW(), :expires_at)'
        );
        $stmt->bindValue(':uid',          $userId, PDO::PARAM_INT);
        $stmt->bindValue(':label',        $label);
        $stmt->bindValue(':api_key',      $apiKey);
        $stmt->bindValue(':secret_hash',  $secretHash);
        $stmt->bindValue(':permissions',  $permissions);
        $stmt->bindValue(':ip_whitelist', $ipWhitelist);
        $stmt->bindValue(':expires_at',   $expiresAt);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function revoke(int $userId, int $keyId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE api_keys SET is_active = 0, revoked_at = NOW()
             WHERE id = :kid AND user_id = :uid AND revoked_at IS NULL'
        );
        $stmt->bindValue(':kid', $keyId,  PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function countActive(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM api_keys WHERE user_id = :uid AND is_active = 1 AND revoked_at IS NULL'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
