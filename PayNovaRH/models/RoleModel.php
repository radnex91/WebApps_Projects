<?php

class RoleModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'description', 'is_default'];

    public function getPermissions($roleId)
    {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                WHERE rp.role_id = :role_id";
        return $this->query($sql, ['role_id' => $roleId]);
    }
}