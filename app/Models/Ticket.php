<?php
namespace App\Models;
use App\Core\Model;
class Ticket extends Model
{
    protected static string $table = 'tickets';

    public static function create(array $data): int
    {
        $data['reference'] = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        return parent::create($data);
    }
}
