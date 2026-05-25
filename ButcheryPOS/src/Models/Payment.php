<?php
namespace App\Models;

class Payment extends BaseModel
{
    protected string $table = 'payments';
    protected array $fillable = ['sale_id', 'amount', 'payment_method', 'payment_gateway', 'gateway_reference', 'gateway_status', 'gateway_response'];
}