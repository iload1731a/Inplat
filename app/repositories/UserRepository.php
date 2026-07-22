<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;

final class UserRepository
{
    public function findByEmailOrUsername(string $identity): ?array
    {
        $sql = 'SELECT id, username, email, password_hash, status FROM users WHERE email = :identity OR username = :identity LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['identity' => $identity]);
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
}
