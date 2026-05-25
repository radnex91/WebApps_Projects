<?php
namespace App\Models;

class ExpiryAlert extends BaseModel
{
    protected string $table = 'expiry_alerts';
    protected array $fillable = ['batch_id', 'product_id', 'alert_type', 'days_until_expiry', 'is_dismissed', 'dismissed_by', 'dismissed_at'];
}