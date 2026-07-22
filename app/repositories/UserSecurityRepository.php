<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserSecurityRepository
{
    public function loginHistory(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, ip_address, user_agent, status, created_at
             FROM login_history
             WHERE user_id = :uid
             ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function activeSessions(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, ip_address, user_agent, is_active, expires_at, created_at
             FROM user_sessions
             WHERE user_id = :uid AND is_active = 1 AND expires_at > NOW()
             ORDER BY id DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function revokeSession(int $userId, int $sessionId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE user_sessions SET is_active = 0
             WHERE id = :sid AND user_id = :uid'
        );
        $stmt->bindValue(':sid', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,    PDO::PARAM_INT);
        $stmt->execute();
    }

    public function revokeAllSessions(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE user_sessions SET is_active = 0 WHERE user_id = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :uid'
        );
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getPasswordHash(int $userId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT password_hash FROM users WHERE id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return is_string($val) ? $val : null;
    }

    public function getTwoFactorData(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT two_factor_enabled, two_factor_secret FROM users WHERE id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: ['two_factor_enabled' => 0, 'two_factor_secret' => null];
    }

    public function enableTwoFactor(int $userId, string $secret): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET two_factor_enabled = 1, two_factor_secret = :secret, updated_at = NOW()
             WHERE id = :uid'
        );
        $stmt->bindValue(':secret', $secret);
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function disableTwoFactor(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, updated_at = NOW()
             WHERE id = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getSecuritySettings(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT login_notification_enabled, withdrawal_notification_enabled, trade_notification_enabled
             FROM account_security_settings WHERE user_id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [
            'login_notification_enabled'      => 1,
            'withdrawal_notification_enabled' => 1,
            'trade_notification_enabled'      => 0,
        ];
    }

    public function upsertSecuritySettings(int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO account_security_settings
                (user_id, login_notification_enabled, withdrawal_notification_enabled,
                 trade_notification_enabled, created_at, updated_at)
             VALUES (:uid, :login_n, :withdraw_n, :trade_n, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                login_notification_enabled      = VALUES(login_notification_enabled),
                withdrawal_notification_enabled = VALUES(withdrawal_notification_enabled),
                trade_notification_enabled      = VALUES(trade_notification_enabled),
                updated_at                      = NOW()'
        );
        $stmt->bindValue(':uid',        $userId, PDO::PARAM_INT);
        $stmt->bindValue(':login_n',    (int)($data['login_notification_enabled']      ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':withdraw_n', (int)($data['withdrawal_notification_enabled'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':trade_n',    (int)($data['trade_notification_enabled']      ?? 0), PDO::PARAM_INT);
        $stmt->execute();
    }
}
