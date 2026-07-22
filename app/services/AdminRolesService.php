<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminRolesRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminRolesService
{
    public function __construct(
        private readonly AdminRolesRepository    $rolesRepo    = new AdminRolesRepository(),
        private readonly AdminManagementRepository $mgmtRepo   = new AdminManagementRepository(),
    ) {}

    // -----------------------------------------------------------------------
    // Roles Index
    // -----------------------------------------------------------------------

    public function rolesIndex(): array
    {
        return [
            'roles'       => $this->rolesRepo->listRoles(),
            'permissions' => $this->rolesRepo->listPermissions(),
            'adminUsers'  => $this->rolesRepo->listAdminUsers(),
        ];
    }

    // -----------------------------------------------------------------------
    // Role CRUD
    // -----------------------------------------------------------------------

    public function createRole(int $adminId, array $payload): int
    {
        $name = trim((string)($payload['name'] ?? ''));
        $desc = trim((string)($payload['description'] ?? ''));
        $isDefault = (bool)($payload['is_default'] ?? false);

        if ($name === '') {
            throw new InvalidArgumentException('Role name is required.');
        }

        $roleId = $this->rolesRepo->createRole($name, $desc, $isDefault);
        $this->mgmtRepo->logAdminAction($adminId, 'create_role', 'roles', (string)$roleId, null, ['name' => $name], RequestContext::ipAddress());
        return $roleId;
    }

    public function updateRole(int $adminId, int $roleId, array $payload): void
    {
        $name = trim((string)($payload['name'] ?? ''));
        $desc = trim((string)($payload['description'] ?? ''));
        $isDefault = (bool)($payload['is_default'] ?? false);

        if ($name === '') {
            throw new InvalidArgumentException('Role name is required.');
        }

        $this->rolesRepo->updateRole($roleId, $name, $desc, $isDefault);
        $this->mgmtRepo->logAdminAction($adminId, 'update_role', 'roles', (string)$roleId, null, ['name' => $name], RequestContext::ipAddress());
    }

    public function deleteRole(int $adminId, int $roleId): void
    {
        $role = $this->rolesRepo->findRoleById($roleId);
        if ($role === null) {
            throw new InvalidArgumentException('Role not found.');
        }

        $this->rolesRepo->deleteRole($roleId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_role', 'roles', (string)$roleId, null, ['name' => $role['name']], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Permission CRUD
    // -----------------------------------------------------------------------

    public function createPermission(int $adminId, array $payload): int
    {
        $name   = trim((string)($payload['name'] ?? ''));
        $desc   = trim((string)($payload['description'] ?? ''));
        $module = trim((string)($payload['module'] ?? ''));
        $action = trim((string)($payload['action'] ?? ''));

        if ($name === '' || $module === '' || $action === '') {
            throw new InvalidArgumentException('Permission name, module and action are required.');
        }

        $permId = $this->rolesRepo->createPermission($name, $desc, $module, $action);
        $this->mgmtRepo->logAdminAction($adminId, 'create_permission', 'permissions', (string)$permId, null, ['name' => $name, 'module' => $module, 'action' => $action], RequestContext::ipAddress());
        return $permId;
    }

    public function updatePermission(int $adminId, int $permId, array $payload): void
    {
        $name   = trim((string)($payload['name'] ?? ''));
        $desc   = trim((string)($payload['description'] ?? ''));
        $module = trim((string)($payload['module'] ?? ''));
        $action = trim((string)($payload['action'] ?? ''));

        if ($name === '' || $module === '' || $action === '') {
            throw new InvalidArgumentException('Permission name, module and action are required.');
        }

        $this->rolesRepo->updatePermission($permId, $name, $desc, $module, $action);
        $this->mgmtRepo->logAdminAction($adminId, 'update_permission', 'permissions', (string)$permId, null, ['name' => $name], RequestContext::ipAddress());
    }

    public function deletePermission(int $adminId, int $permId): void
    {
        $perm = $this->rolesRepo->findPermissionById($permId);
        if ($perm === null) {
            throw new InvalidArgumentException('Permission not found.');
        }

        $this->rolesRepo->deletePermission($permId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_permission', 'permissions', (string)$permId, null, ['name' => $perm['name']], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Role Permissions Sync
    // -----------------------------------------------------------------------

    public function syncRolePermissions(int $adminId, int $roleId, array $permissionIds): void
    {
        $role = $this->rolesRepo->findRoleById($roleId);
        if ($role === null) {
            throw new InvalidArgumentException('Role not found.');
        }

        $cleaned = array_map('intval', $permissionIds);
        $this->rolesRepo->syncRolePermissions($roleId, $cleaned);
        $this->mgmtRepo->logAdminAction($adminId, 'sync_role_permissions', 'roles', (string)$roleId, null, ['permission_ids' => $cleaned], RequestContext::ipAddress());
    }

    public function getRolePermissions(int $roleId): array
    {
        return $this->rolesRepo->getRolePermissions($roleId);
    }

    // -----------------------------------------------------------------------
    // Admin User Management
    // -----------------------------------------------------------------------

    public function createAdminUser(int $adminId, array $payload): int
    {
        $username = trim((string)($payload['username'] ?? ''));
        $email    = trim((string)($payload['email'] ?? ''));
        $fullName = trim((string)($payload['full_name'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $roleId   = (int)($payload['role_id'] ?? 0);

        if ($username === '' || $email === '' || $password === '' || $roleId <= 0) {
            throw new InvalidArgumentException('Username, email, password and role are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $hash = password_hash($password, password_algo());
        $newId = $this->rolesRepo->createAdminUser($username, $email, $fullName, $hash, $roleId);
        $this->mgmtRepo->logAdminAction($adminId, 'create_admin_user', 'admin_users', (string)$newId, null, ['username' => $username, 'email' => $email], RequestContext::ipAddress());
        return $newId;
    }

    public function updateAdminUser(int $adminId, int $targetId, array $payload): void
    {
        $email    = trim((string)($payload['email'] ?? ''));
        $fullName = trim((string)($payload['full_name'] ?? ''));
        $roleId   = (int)($payload['role_id'] ?? 0);
        $status   = trim((string)($payload['status'] ?? 'active'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        if (!in_array($status, ['active', 'suspended', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid admin status.');
        }

        $this->rolesRepo->updateAdminUser($targetId, $email, $fullName, $roleId, $status);

        $password = (string)($payload['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Password must be at least 8 characters.');
            }
            $hash = password_hash($password, password_algo());
            $this->rolesRepo->updateAdminPassword($targetId, $hash);
        }

        $this->mgmtRepo->logAdminAction($adminId, 'update_admin_user', 'admin_users', (string)$targetId, null, ['email' => $email, 'status' => $status], RequestContext::ipAddress());
    }

    public function deleteAdminUser(int $adminId, int $targetId): void
    {
        if ($adminId === $targetId) {
            throw new InvalidArgumentException('You cannot delete your own admin account.');
        }

        $user = $this->rolesRepo->findAdminUserById($targetId);
        if ($user === null) {
            throw new InvalidArgumentException('Admin user not found.');
        }

        $this->rolesRepo->softDeleteAdminUser($targetId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_admin_user', 'admin_users', (string)$targetId, null, ['username' => $user['username']], RequestContext::ipAddress());
    }
}
