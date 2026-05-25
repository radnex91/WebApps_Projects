<?php
namespace App\Models;

class StockBatch extends BaseModel
{
    protected string $table = 'stock_batches';
    protected array $fillable = ['product_id', 'batch_reference', 'quantity_received', 'quantity_remaining', 'unit_cost', 'expiry_date', 'supplier_id', 'is_fully_consumed'];
}