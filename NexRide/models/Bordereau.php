<?php
namespace Models;

use Core\Model;
use Core\Helpers;

class Bordereau extends Model
{
    protected static string $table = 'bordereaux';
    protected array $fillable = ['reference', 'date_bordereau', 'type', 'montant_total', 'description', 'statut', 'valide_par', 'date_validation', 'offline_id', 'synced', 'created_by'];
    protected array $casts = ['id' => 'int', 'montant_total' => 'float', 'synced' => 'bool'];

    public static function generateReference(): string
    {
        return Helpers::generateRef('BR');
    }

    public static function getUnsynced(): array
    {
        return self::raw("SELECT * FROM bordereaux WHERE synced = 0");
    }

    public function lignes(): array
    {
        return self::db()->fetchAll("SELECT * FROM bordereau_lignes WHERE bordereau_id = ?", [$this->id]);
    }

    public function getStatutLabel(): string
    {
        return ['BROUILLON' => 'Brouillon', 'VALIDE' => 'Validé', 'CLOTURE' => 'Clôturé'][$this->statut] ?? $this->statut;
    }

    public static function getStats(): object
    {
        return self::db()->fetch("SELECT COUNT(*) as total,
            SUM(CASE WHEN statut = 'BROUILLON' THEN 1 ELSE 0 END) as brouillons,
            SUM(CASE WHEN statut = 'VALIDE' THEN 1 ELSE 0 END) as valides,
            SUM(CASE WHEN statut = 'CLOTURE' THEN 1 ELSE 0 END) as clotures,
            COALESCE(SUM(CASE WHEN type = 'RECETTE' AND statut != 'BROUILLON' THEN montant_total ELSE 0 END), 0) as total_recettes,
            COALESCE(SUM(CASE WHEN type = 'DEPENSE' AND statut != 'BROUILLON' THEN montant_total ELSE 0 END), 0) as total_depenses
        FROM bordereaux");
    }

    public static function getByDateRange(string $debut, string $fin): array
    {
        return self::raw(
            "SELECT b.*, u.nom as created_nom FROM bordereaux b
            LEFT JOIN utilisateurs u ON b.created_by = u.id
            WHERE b.date_bordereau BETWEEN ? AND ? ORDER BY b.date_bordereau DESC",
            [$debut, $fin]
        );
    }
}