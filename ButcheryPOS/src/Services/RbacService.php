<?php
namespace App\Services;

use App\Models\Role;
use App\Repositories\BaseRepository;

class RbacService
{
    private BaseRepository $repo;
    private Role $roleModel;

    public function __construct(BaseRepository $repo, Role $roleModel)
    {
        $this->repo = $repo;
        $this->roleModel = $roleModel;
    }

    /**
     * Get all roles
     */
    public function getAllRoles(): array
    {
        return $this->roleModel->all([], 'id ASC');
    }

    /**
     * Get role with permissions
     */
    public function getRoleWithPermissions(int $id): ?array
    {
        return $this->roleModel->withPermissions($id);
    }

    /**
     * Create a new role
     */
    public function createRole(array $data): int
    {
        $roleId = $this->roleModel->create($data);
        // Initialize empty permissions for all modules
        $modules = ['dashboard','pos','products','categories','stock','sales','customers','suppliers','users','roles','settings','expiry','scale','reports'];
        foreach ($modules as $module) {
            $this->repo->insert('role_module_permissions', [
                'role_id' => $roleId,
                'module_key' => $module,
                'can_view' => 0, 'can_create' => 0, 'can_edit' => 0,
                'can_delete' => 0, 'can_print' => 0, 'can_manage' => 0,
            ]);
        }
        return $roleId;
    }

    /**
     * Update role (prevent modifying system roles' name)
     */
    public function updateRole(int $id, array $data): bool
    {
        $role = $this->roleModel->find($id);
        if (!$role) return false;
        if ($role['is_system_role'] && isset($data['role_name'])) {
            unset($data['role_name']); // Cannot rename system roles
        }
        return $this->roleModel->update($id, $data);
    }

    /**
     * Delete role (prevent deleting system roles)
     */
    public function deleteRole(int $id): bool
    {
        $role = $this->roleModel->find($id);
        if (!$role || $role['is_system_role']) return false;

        // Check if any users have this role
        $userCount = $this->repo->count('users', ['role_id' => $id]);
        if ($userCount > 0) return false;

        return $this->roleModel->delete($id);
    }

    /**
     * Update permission matrix for a role
     */
    public function updatePermissions(int $roleId, array $permissions): void
    {
        foreach ($permissions as $moduleKey => $flags) {
            $this->repo->statement(
                "UPDATE role_module_permissions
                 SET can_view = :can_view, can_create = :can_create, can_edit = :can_edit,
                     can_delete = :can_delete, can_print = :can_print, can_manage = :can_manage
                 WHERE role_id = :role_id AND module_key = :module_key",
                [
                    'role_id' => $roleId,
                    'module_key' => $moduleKey,
                    'can_view' => (int)($flags['can_view'] ?? 0),
                    'can_create' => (int)($flags['can_create'] ?? 0),
                    'can_edit' => (int)($flags['can_edit'] ?? 0),
                    'can_delete' => (int)($flags['can_delete'] ?? 0),
                    'can_print' => (int)($flags['can_print'] ?? 0),
                    'can_manage' => (int)($flags['can_manage'] ?? 0),
                ]
            );
        }

        // Reload the permission matrix in the current session
        $permRows = $this->repo->query("SELECT * FROM role_module_permissions");
        \App\Core\Permission::loadMatrix($permRows);
    }

    /**
     * Get all module keys
     */
    public function getModuleKeys(): array
    {
        return ['dashboard','pos','products','categories','stock','sales','customers','suppliers','users','roles','settings','expiry','scale','reports'];
    }
}