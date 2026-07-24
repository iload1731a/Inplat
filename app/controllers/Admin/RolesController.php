<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminRolesService;
use Throwable;

final class RolesController extends AdminBaseController
{
    private function svc(): AdminRolesService
    {
        return new AdminRolesService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->rolesIndex();
        $this->view('admin/roles/index', [
            'title'        => 'Admin · Roles & Permissions',
            'username'     => $this->adminUsername(),
            'adminSection' => 'roles',
            ...$data,
        ]);
    }

    // -----------------------------------------------------------------------
    // Role actions
    // -----------------------------------------------------------------------

    public function createRole(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $roleId = $this->svc()->createRole($this->adminId(), [
                'name'        => $request->input('name', ''),
                'description' => $request->input('description', ''),
                'is_default'  => $request->input('is_default', '0'),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Role created.', 'redirect' => '/admin/roles']);
    }

    public function updateRole(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $roleId = (int)$request->input('role_id', 0);
        try {
            $this->svc()->updateRole($this->adminId(), $roleId, [
                'name'        => $request->input('name', ''),
                'description' => $request->input('description', ''),
                'is_default'  => $request->input('is_default', '0'),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Role updated.', 'redirect' => '/admin/roles']);
    }

    public function deleteRole(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $roleId = (int)$request->input('role_id', 0);
        try {
            $this->svc()->deleteRole($this->adminId(), $roleId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Role deleted.', 'redirect' => '/admin/roles']);
    }

    public function syncPermissions(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $roleId = (int)$request->input('role_id', 0);
        $permIds = array_filter(array_map('intval', (array)$request->input('permission_ids', [])));
        try {
            $this->svc()->syncRolePermissions($this->adminId(), $roleId, $permIds);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Permissions updated.', 'redirect' => '/admin/roles']);
    }

    public function getRolePermissions(Request $request): void
    {
        $this->bootAdmin();
        $roleId = (int)$request->input('role_id', 0);
        $ids = $this->svc()->getRolePermissions($roleId);
        Response::json(['ok' => true, 'permission_ids' => $ids]);
    }

    // -----------------------------------------------------------------------
    // Permission actions
    // -----------------------------------------------------------------------

    public function createPermission(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $permId = $this->svc()->createPermission($this->adminId(), [
                'name'        => $request->input('name', ''),
                'description' => $request->input('description', ''),
                'module'      => $request->input('module', ''),
                'action'      => $request->input('action', ''),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Permission created.', 'redirect' => '/admin/roles']);
    }

    public function updatePermission(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $permId = (int)$request->input('permission_id', 0);
        try {
            $this->svc()->updatePermission($this->adminId(), $permId, [
                'name'        => $request->input('name', ''),
                'description' => $request->input('description', ''),
                'module'      => $request->input('module', ''),
                'action'      => $request->input('action', ''),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Permission updated.', 'redirect' => '/admin/roles']);
    }

    public function deletePermission(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $permId = (int)$request->input('permission_id', 0);
        try {
            $this->svc()->deletePermission($this->adminId(), $permId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Permission deleted.', 'redirect' => '/admin/roles']);
    }

    // -----------------------------------------------------------------------
    // Admin Users
    // -----------------------------------------------------------------------

    public function createAdminUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createAdminUser($this->adminId(), [
                'username'  => $request->input('username', ''),
                'email'     => $request->input('email', ''),
                'full_name' => $request->input('full_name', ''),
                'password'  => $request->input('password', ''),
                'role_id'   => (int)$request->input('role_id', 0),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Admin user created.', 'redirect' => '/admin/roles']);
    }

    public function updateAdminUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $targetId = (int)$request->input('admin_user_id', 0);
        try {
            $this->svc()->updateAdminUser($this->adminId(), $targetId, [
                'email'     => $request->input('email', ''),
                'full_name' => $request->input('full_name', ''),
                'role_id'   => (int)$request->input('role_id', 0),
                'status'    => $request->input('status', 'active'),
                'password'  => $request->input('password', ''),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Admin user updated.', 'redirect' => '/admin/roles']);
    }

    public function deleteAdminUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $targetId = (int)$request->input('admin_user_id', 0);
        try {
            $this->svc()->deleteAdminUser($this->adminId(), $targetId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Admin user removed.', 'redirect' => '/admin/roles']);
    }
}
