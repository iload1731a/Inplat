<?php declare(strict_types=1); ?>
<?php
$roles       = is_array($roles ?? null) ? $roles : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$adminUsers  = is_array($adminUsers ?? null) ? $adminUsers : [];
$csrf        = \App\Libraries\Csrf::token();
// Group permissions by module
$permsByModule = [];
foreach ($permissions as $perm) {
    $permsByModule[(string)($perm['module'] ?? 'general')][] = $perm;
}
ksort($permsByModule);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Roles &amp; Permissions</h1>
        <p class="text-secondary mb-0">Manage admin roles, permissions, and admin user accounts.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Nav Tabs -->
<ul class="nav nav-tabs border-secondary mb-4" id="rolesTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active text-light" data-bs-toggle="tab" data-bs-target="#tabRoles">Roles</button></li>
    <li class="nav-item"><button class="nav-link text-light" data-bs-toggle="tab" data-bs-target="#tabPermissions">Permissions</button></li>
    <li class="nav-item"><button class="nav-link text-light" data-bs-toggle="tab" data-bs-target="#tabAdminUsers">Admin Users</button></li>
</ul>

<div class="tab-content">

<!-- ROLES TAB -->
<div class="tab-pane fade show active" id="tabRoles">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">
            <i class="fas fa-plus me-1"></i> New Role
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th><th>Role Name</th><th>Description</th><th>Default</th>
                        <th>Permissions</th><th>Admins</th><th>Created</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><?= (int)($role['id'] ?? 0) ?></td>
                        <td><span class="badge text-bg-warning text-dark"><?= e((string)($role['name'] ?? '-')) ?></span></td>
                        <td class="text-secondary small"><?= e((string)($role['description'] ?? '')) ?></td>
                        <td><?= (int)($role['is_default'] ?? 0) ? '<span class="badge text-bg-success">Yes</span>' : '' ?></td>
                        <td><?= (int)($role['permission_count'] ?? 0) ?></td>
                        <td><?= (int)($role['admin_count'] ?? 0) ?></td>
                        <td class="text-secondary small"><?= e((string)($role['created_at'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-info me-1"
                                data-role-id="<?= (int)$role['id'] ?>"
                                data-action="assign-perms"
                                onclick="loadRolePermissions(<?= (int)$role['id'] ?>, <?= e(json_encode($role['name'])) ?>)">Permissions</button>
                            <button class="btn btn-xs btn-outline-light me-1"
                                onclick="openEditRoleModal(<?= e(json_encode($role)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger"
                                onclick="deleteRole(<?= (int)$role['id'] ?>, '<?= e((string)$role['name']) ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($roles === []): ?>
                    <tr><td colspan="8" class="text-center text-secondary">No roles found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PERMISSIONS TAB -->
<div class="tab-pane fade" id="tabPermissions">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPermModal">
            <i class="fas fa-plus me-1"></i> New Permission
        </button>
    </div>
    <?php foreach ($permsByModule as $module => $perms): ?>
    <div class="glass rounded-4 p-3 mb-3">
        <h2 class="h6 mb-3 text-warning text-uppercase"><?= e($module) ?></h2>
        <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0">
                <thead><tr><th>ID</th><th>Name</th><th>Action</th><th>Description</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($perms as $perm): ?>
                    <tr>
                        <td><?= (int)($perm['id'] ?? 0) ?></td>
                        <td><code class="text-info small"><?= e((string)($perm['name'] ?? '-')) ?></code></td>
                        <td><?= e((string)($perm['action'] ?? '-')) ?></td>
                        <td class="text-secondary small"><?= e((string)($perm['description'] ?? '')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1"
                                onclick="openEditPermModal(<?= e(json_encode($perm)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger"
                                onclick="deletePerm(<?= (int)$perm['id'] ?>, '<?= e((string)$perm['name']) ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($permissions === []): ?>
        <div class="glass rounded-4 p-4 text-center text-secondary">No permissions defined yet.</div>
    <?php endif; ?>
</div>

<!-- ADMIN USERS TAB -->
<div class="tab-pane fade" id="tabAdminUsers">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAdminUserModal">
            <i class="fas fa-plus me-1"></i> New Admin User
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($adminUsers as $au): ?>
                    <tr>
                        <td><?= (int)($au['id'] ?? 0) ?></td>
                        <td class="fw-semibold"><?= e((string)($au['username'] ?? '-')) ?></td>
                        <td class="text-secondary small"><?= e((string)($au['email'] ?? '-')) ?></td>
                        <td><?= e((string)($au['full_name'] ?? '-')) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e((string)($au['role_name'] ?? 'No Role')) ?></span></td>
                        <td>
                            <span class="badge text-bg-<?= (($au['status'] ?? '') === 'active') ? 'success' : 'danger' ?>">
                                <?= e(ucfirst((string)($au['status'] ?? 'unknown'))) ?>
                            </span>
                        </td>
                        <td class="text-secondary small"><?= e((string)($au['last_login_at'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1"
                                onclick="openEditAdminUserModal(<?= e(json_encode($au)) ?>, <?= e(json_encode($roles)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger"
                                onclick="deleteAdminUser(<?= (int)$au['id'] ?>, '<?= e((string)$au['username']) ?>')">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($adminUsers === []): ?>
                    <tr><td colspan="8" class="text-center text-secondary">No admin users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /tab-content -->

<!-- ===== MODALS ===== -->

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/roles/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Create Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" required></div>
                <div class="mb-3"><label class="form-label">Description</label>
                    <input class="form-control" type="text" name="description"></div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="roleIsDefault">
                    <label class="form-check-label" for="roleIsDefault">Default Role</label>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/roles/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="role_id" id="editRoleId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" id="editRoleName" required></div>
                <div class="mb-3"><label class="form-label">Description</label>
                    <input class="form-control" type="text" name="description" id="editRoleDesc"></div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="editRoleIsDefault">
                    <label class="form-check-label" for="editRoleIsDefault">Default Role</label>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Permissions Modal -->
<div class="modal fade" id="assignPermsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/roles/permissions/sync" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="role_id" id="assignPermsRoleId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Assign Permissions – <span id="assignPermsRoleName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:60vh;overflow-y:auto">
                <div class="mb-2 d-flex gap-2">
                    <button type="button" class="btn btn-xs btn-outline-success" onclick="checkAllPerms()">Check All</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="uncheckAllPerms()">Uncheck All</button>
                </div>
                <?php foreach ($permsByModule as $module => $perms): ?>
                <div class="mb-3">
                    <h6 class="text-warning text-uppercase mb-2"><?= e($module) ?></h6>
                    <div class="row g-2">
                    <?php foreach ($perms as $perm): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input perm-checkbox" type="checkbox"
                                    name="permission_ids[]"
                                    value="<?= (int)$perm['id'] ?>"
                                    id="perm_<?= (int)$perm['id'] ?>">
                                <label class="form-check-label small" for="perm_<?= (int)$perm['id'] ?>">
                                    <?= e((string)($perm['name'] ?? '-')) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Permission Modal -->
<div class="modal fade" id="createPermModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/permissions/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Create Permission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" placeholder="e.g. users.manage" required></div>
                <div class="mb-3"><label class="form-label">Module <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="module" placeholder="e.g. users" required></div>
                <div class="mb-3"><label class="form-label">Action <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="action" placeholder="e.g. manage" required></div>
                <div class="mb-3"><label class="form-label">Description</label>
                    <input class="form-control" type="text" name="description"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Permission</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Permission Modal -->
<div class="modal fade" id="editPermModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/permissions/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="permission_id" id="editPermId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Permission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label>
                    <input class="form-control" type="text" name="name" id="editPermName" required></div>
                <div class="mb-3"><label class="form-label">Module</label>
                    <input class="form-control" type="text" name="module" id="editPermModule" required></div>
                <div class="mb-3"><label class="form-label">Action</label>
                    <input class="form-control" type="text" name="action" id="editPermAction" required></div>
                <div class="mb-3"><label class="form-label">Description</label>
                    <input class="form-control" type="text" name="description" id="editPermDesc"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Admin User Modal -->
<div class="modal fade" id="createAdminUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/admin-users/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">New Admin User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Username <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="username" required></div>
                    <div class="col-6"><label class="form-label">Full Name</label>
                        <input class="form-control" type="text" name="full_name"></div>
                    <div class="col-12"><label class="form-label">Email <span class="text-danger">*</span></label>
                        <input class="form-control" type="email" name="email" required></div>
                    <div class="col-12"><label class="form-label">Password <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="password" required minlength="8"></div>
                    <div class="col-12"><label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role_id" required>
                            <option value="">Select Role...</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e((string)$r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Admin User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Admin User Modal -->
<div class="modal fade" id="editAdminUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/admin-users/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="admin_user_id" id="editAdminUserId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Admin User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Full Name</label>
                        <input class="form-control" type="text" name="full_name" id="editAdminFullName"></div>
                    <div class="col-12"><label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" id="editAdminEmail"></div>
                    <div class="col-12"><label class="form-label">New Password <small class="text-secondary">(leave blank to keep current)</small></label>
                        <input class="form-control" type="password" name="password" minlength="8"></div>
                    <div class="col-6"><label class="form-label">Role</label>
                        <select class="form-select" name="role_id" id="editAdminRole">
                            <option value="0">No Role</option>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editAdminStatus">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden delete forms -->
<form id="deleteRoleForm" data-ajax="true" action="/admin/roles/delete" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="role_id" id="deleteRoleId">
</form>
<form id="deletePermForm" data-ajax="true" action="/admin/permissions/delete" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="permission_id" id="deletePermFormId">
</form>
<form id="deleteAdminUserForm" data-ajax="true" action="/admin/admin-users/delete" method="post" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="admin_user_id" id="deleteAdminUserId">
</form>

<script>
const allRoles = <?= json_encode($roles, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

function openEditRoleModal(role) {
    document.getElementById('editRoleId').value = role.id;
    document.getElementById('editRoleName').value = role.name;
    document.getElementById('editRoleDesc').value = role.description || '';
    document.getElementById('editRoleIsDefault').checked = parseInt(role.is_default) === 1;
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}

function deleteRole(id, name) {
    if (!confirm('Delete role "' + name + '"? This cannot be undone.')) return;
    document.getElementById('deleteRoleId').value = id;
    $('#deleteRoleForm').trigger('submit');
}

function loadRolePermissions(roleId, roleName) {
    document.getElementById('assignPermsRoleId').value = roleId;
    document.getElementById('assignPermsRoleName').textContent = roleName;
    // Uncheck all first
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
    $.get('/admin/roles/permissions?role_id=' + roleId, function(res) {
        if (res.ok && Array.isArray(res.permission_ids)) {
            res.permission_ids.forEach(id => {
                const cb = document.getElementById('perm_' + id);
                if (cb) cb.checked = true;
            });
        }
        new bootstrap.Modal(document.getElementById('assignPermsModal')).show();
    });
}

function checkAllPerms() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
}
function uncheckAllPerms() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
}

function openEditPermModal(perm) {
    document.getElementById('editPermId').value = perm.id;
    document.getElementById('editPermName').value = perm.name;
    document.getElementById('editPermModule').value = perm.module;
    document.getElementById('editPermAction').value = perm.action;
    document.getElementById('editPermDesc').value = perm.description || '';
    new bootstrap.Modal(document.getElementById('editPermModal')).show();
}

function deletePerm(id, name) {
    if (!confirm('Delete permission "' + name + '"?')) return;
    document.getElementById('deletePermFormId').value = id;
    $('#deletePermForm').trigger('submit');
}

function openEditAdminUserModal(au, roles) {
    document.getElementById('editAdminUserId').value = au.id;
    document.getElementById('editAdminFullName').value = au.full_name || '';
    document.getElementById('editAdminEmail').value = au.email;
    document.getElementById('editAdminStatus').value = au.status;
    const roleSelect = document.getElementById('editAdminRole');
    roleSelect.innerHTML = '<option value="0">No Role</option>';
    roles.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name;
        if (parseInt(r.id) === parseInt(au.role_id)) opt.selected = true;
        roleSelect.appendChild(opt);
    });
    new bootstrap.Modal(document.getElementById('editAdminUserModal')).show();
}

function deleteAdminUser(id, username) {
    if (!confirm('Remove admin user "' + username + '"?')) return;
    document.getElementById('deleteAdminUserId').value = id;
    $('#deleteAdminUserForm').trigger('submit');
}
</script>
