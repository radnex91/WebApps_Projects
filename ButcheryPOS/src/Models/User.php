<?php
namespace App\Models;

class User extends BaseModel
{
    protected string $table = 'users';
    protected array $fillable = ['full_name', 'username', 'email', 'password', 'role_id', 'preferred_language', 'phone', 'is_active'];
}