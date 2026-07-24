<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;
use RuntimeException;

final class AdminRolesRepository
{
    // -----------------------------------------------------------------------
    // Roles
    // -----------------------------------------------------------------------

    public function listRoles(): array
    {
        $stmt = Database::connection()->query(
            "SELECT r.id, r.name, r.description, r.is_system_role, r.created_at,
                    COUNT(DISTINCT rp.permission_id) AS permission_count,
                    COUNT(DISTINCT au.id)             AS admin_count
             FROM roles r
             LEFT JOIN role_permissions rp ON rp.role_id = r.id
             LEFT JOIN admin_users au ON au.role_id = r.id AND au.deleted_at IS NULL
             GROUP BY r.id, r.name, r.description, r.is_system_role, r.created_at
             ORDER BY r.id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findRoleById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.name, r.description, r.is_system_role, r.created_at
             FROM roles r
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createRole(string $name, string $description, bool $isSystemRole): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO roles (name, description, is_system_role, created_at, updated_at)
             VALUES (:name, :description, :is_system_role, NOW(), NOW())'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':is_system_role', (int)$isSystemRole, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function updateRole(int $id, string $name, string $description, bool $isSystemRole): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE roles SET name = :name, description = :description, is_system_role = :is_system_role, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':is_system_role', (int)$isSystemRole, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteRole(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM roles WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------------

    public function listPermissions(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, `key`, module, description
             FROM permissions
             ORDER BY module ASC, `key` ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findPermissionById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, `key`, module, description FROM permissions WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createPermission(string $key, string $description, string $module): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO permissions (`key`, module, description) VALUES (:key, :module, :description)'
        );
        $stmt->bindValue(':key', $key);
        $stmt->bindValue(':module', $module);
        $stmt->bindValue(':description', $description);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function updatePermission(int $id, string $key, string $description, string $module): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE permissions SET `key` = :key, module = :module, description = :description WHERE id = :id'
        );
        $stmt->bindValue(':key', $key);
        $stmt->bindValue(':module', $module);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deletePermission(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM permissions WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Role Permissions
    // -----------------------------------------------------------------------

    public function getRolePermissions(int $roleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT rp.permission_id FROM role_permissions rp WHERE rp.role_id = :role_id'
        );
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        return array_column($stmt->fetchAll() ?: [], 'permission_id');
    }

    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $del->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $del->execute();

            if (!empty($permissionIds)) {
                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id)
                     VALUES (:role_id, :perm_id)'
                );
                foreach ($permissionIds as $permId) {
                    $ins->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                    $ins->bindValue(':perm_id', (int)$permId, PDO::PARAM_INT);
                    $ins->execute();
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Admin Users
    // -----------------------------------------------------------------------

    public function listAdminUsers(): array
    {
        $stmt = Database::connection()->query(
            "SELECT au.id, au.username, au.email, au.full_name, au.status, au.last_login_at, au.created_at,
                    COALESCE(r.name, 'No Role') AS role_name, r.id AS role_id
             FROM admin_users au
             LEFT JOIN roles r ON r.id = au.role_id
             WHERE au.deleted_at IS NULL
             ORDER BY au.id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findAdminUserById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT au.id, au.username, au.email, au.full_name, au.status, au.role_id, au.last_login_at, au.created_at,
                    COALESCE(r.name, 'No Role') AS role_name
             FROM admin_users au
             LEFT JOIN roles r ON r.id = au.role_id
             WHERE au.id = :id AND au.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createAdminUser(string $username, string $email, string $fullName, string $passwordHash, int $roleId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO admin_users (username, email, full_name, password_hash, role_id, status, created_at, updated_at)
             VALUES (:username, :email, :full_name, :password_hash, :role_id, 'active', NOW(), NOW())"
        );
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':full_name', $fullName);
        $stmt->bindValue(':password_hash', $passwordHash);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function updateAdminUser(int $id, string $email, string $fullName, int $roleId, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE admin_users SET email = :email, full_name = :full_name, role_id = :role_id, status = :status, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':full_name', $fullName);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateAdminPassword(int $id, string $passwordHash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE admin_users SET password_hash = :hash, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':hash', $passwordHash);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function softDeleteAdminUser(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE admin_users SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
