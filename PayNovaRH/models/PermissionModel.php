<?php

class PermissionModel extends Model
{
    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $fillable = ['module', 'action', 'description'];

    public function getByRoleId($roleId)
    {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                WHERE rp.role_id = :role_id";
        return $this->query($sql, ['role_id' => $roleId]);
    }

    public function getByModule($module)
    {
        return $this->findBy(['module' => $module]);
    }

    public function syncPermissions($roleId, $permissionIds)
    {
        $this->execute("DELETE FROM role_permissions WHERE role_id = :role_id", ['role_id' => $roleId]);

        foreach ($permissionIds as $permissionId) {
            $this->execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)",
                ['role_id' => $roleId, 'permission_id' => $permissionId]
            );
        }
    }
}