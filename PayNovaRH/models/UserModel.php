<?php

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = ['username', 'email', 'password', 'role_id', 'is_active', 'last_login'];

    public function getByRoleId($roleId)
    {
        return $this->findBy(['role_id' => $roleId]);
    }
}