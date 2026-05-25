<?php
namespace App\Models;

class SaleItem extends BaseModel
{
    protected string $table = 'sale_items';
    protected array $fillable = ['sale_id', 'product_id', 'batch_id', 'quantity', 'unit_price', 'unit_cost', 'subtotal'];
}