<?php
namespace App\Models;

class BreakdownItem extends BaseModel
{
    protected string $table = 'breakdown_items';
    protected array $fillable = [
        'breakdown_id', 'output_product_id', 'output_batch_id',
        'output_quantity', 'output_unit_cost', 'is_byproduct',
    ];
}