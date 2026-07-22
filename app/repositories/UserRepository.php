<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use DateTimeImmutable;
use PDO;

final class UserRepository
{
    public function findByEmailOrUsername(string $identity): ?array
    {
        $sql = 'SELECT id, username, email, password_hash, status, two_factor_enabled, two_factor_secret, email_verified_at FROM users WHERE email = :identity OR username = :identity LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['identity' => $identity]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, username, email, password_hash, status, two_factor_enabled, two_factor_secret, email_verified_at FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, username, email, password_hash, status FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO users (uuid, username, email, password_hash, status, kyc_status, account_type, created_at, updated_at)
                VALUES (:uuid, :username, :email, :password_hash, :status, :kyc_status, :account_type, NOW(), NOW())';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'uuid' => $payload['uuid'],
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password_hash' => $payload['password_hash'],
            'status' => 'active',
            'kyc_status' => 'unverified',
            'account_type' => 'individual',
        ]);

        return (int)Database::connection()->lastInsertId();
    }

    public function markEmailVerified(int $userId): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':password_hash', $passwordHash);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function createPasswordReset(int $userId, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :token_hash, :expires_at, NOW())');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $tokenHash);
        $stmt->bindValue(':expires_at', $expiresAt->format('Y-m-d H:i:s'));
        $stmt->execute();
    }


    public function hasRecentPasswordReset(int $userId, int $seconds = 60): bool
    {
        $safeSeconds = max(1, $seconds);
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':seconds', $safeSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    public function findValidPasswordResetByTokenHash(string $tokenHash): ?array
    {
        $sql = 'SELECT pr.id, pr.user_id, pr.expires_at, u.email, u.username
                FROM password_resets pr
                INNER JOIN users u ON u.id = pr.user_id
                WHERE pr.token_hash = :token_hash AND pr.used_at IS NULL AND pr.expires_at > NOW()
                ORDER BY pr.id DESC
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':token_hash', $tokenHash);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function markPasswordResetUsed(int $resetId): void
    {
        $stmt = Database::connection()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $resetId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function createSession(int $userId, string $sessionTokenHash, string $ipAddress, string $userAgent, DateTimeImmutable $expiresAt): void
    {
        $sql = 'INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, is_active, expires_at, created_at)
                VALUES (:user_id, :session_token, :ip_address, :user_agent, 1, :expires_at, NOW())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':session_token', $sessionTokenHash);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':user_agent', $userAgent);
        $stmt->bindValue(':expires_at', $expiresAt->format('Y-m-d H:i:s'));
        $stmt->execute();
    }

    public function findActiveSessionByTokenHash(string $sessionTokenHash): ?array
    {
        $sql = "SELECT s.id AS session_id, s.user_id, s.ip_address, s.user_agent, u.username, u.email
                FROM user_sessions s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.session_token = :token_hash
                  AND s.is_active = 1
                  AND s.expires_at > NOW()
                  AND u.status = 'active'
                LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':token_hash', $sessionTokenHash);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function sessionsForUser(int $userId, int $limit = 20): array
    {
        $safeLimit = max(1, $limit);
        $stmt = Database::connection()->prepare('SELECT id, ip_address, user_agent, country_code, is_active, expires_at, created_at FROM user_sessions WHERE user_id = :user_id ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function revokeSessionById(int $userId, int $sessionId): void
    {
        $stmt = Database::connection()->prepare('UPDATE user_sessions SET is_active = 0 WHERE user_id = :user_id AND id = :session_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function logLoginAttempt(int $userId, string $ipAddress, string $userAgent, string $status): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO login_history (user_id, ip_address, user_agent, status, created_at) VALUES (:user_id, :ip_address, :user_agent, :status, NOW())');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':user_agent', $userAgent);
        $stmt->bindValue(':status', $status);
        $stmt->execute();
    }

    public function isAdminIdentity(string $identity): bool
    {
        $sql = "SELECT COUNT(*) FROM admin_users WHERE (email = :identity OR username = :identity) AND status = 'active'";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':identity', $identity);
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }
}
