<?php
namespace App\Models;

class Customer extends BaseModel
{
    protected string $table = 'customers';
    protected array $fillable = ['name', 'phone', 'email', 'address', 'balance', 'is_active'];
}