<?php
namespace App\Models;

class Role extends BaseModel
{
    protected string $table = 'app_roles';
    protected array $fillable = ['role_name', 'display_name', 'description', 'is_system_role'];

    public function withPermissions(int $roleId): ?array
    {
        $role = $this->find($roleId);
        if (!$role) return null;
        $role['permissions'] = $this->repo->all('role_module_permissions', ['role_id' => $roleId]);
        return $role;
    }
}