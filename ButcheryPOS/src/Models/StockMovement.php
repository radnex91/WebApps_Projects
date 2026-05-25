<?php
namespace App\Models;

class StockMovement extends BaseModel
{
    protected string $table = 'stock_movements';
    protected array $fillable = ['product_id', 'batch_id', 'movement_type', 'quantity', 'quantity_before', 'quantity_after', 'unit_cost', 'reference_table', 'reference_id', 'reason', 'created_by'];
}