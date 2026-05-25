<?php
namespace App\Models;

class Unit extends BaseModel
{
    protected string $table = 'units';
    protected array $fillable = ['name', 'abbreviation', 'unit_type', 'is_active'];
}