<?php
namespace App\Models;

class Supplier extends BaseModel
{
    protected string $table = 'suppliers';
    protected array $fillable = ['name', 'phone', 'email', 'address', 'balance', 'is_active'];
}