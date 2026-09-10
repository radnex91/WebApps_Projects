<?php
namespace App\Models;
use App\Core\Database;
use App\Core\Model;
class Sla extends Model
{
    protected static string $table = 'sla_config';

    public static function all(): array
    {
        $sql = "SELECT s.*, p.nom AS priority_nom, p.couleur AS priority_couleur
                FROM sla_config s
                LEFT JOIN priorities p ON s.priorite_id = p.id
                ORDER BY s.id DESC";
        return Database::fetchAll($sql);
    }
}
