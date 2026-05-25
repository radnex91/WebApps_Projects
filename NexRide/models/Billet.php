<?php
namespace Models;

use Core\Model;
use Core\Helpers;

class Billet extends Model
{
    protected static string $table = 'billets';
    protected array $fillable = ['reference', 'voyage_id', 'client_id', 'nom_passager', 'telephone_passager', 'email_passager', 'siege', 'montant', 'taxe', 'remise', 'montant_total', 'mode_paiement', 'statut', 'code_validation', 'notes', 'offline_id', 'synced', 'created_by'];
    protected array $casts = ['id' => 'int', 'voyage_id' => 'int', 'client_id' => 'int', 'montant' => 'float', 'taxe' => 'float', 'remise' => 'float', 'montant_total' => 'float', 'synced' => 'bool'];

    public static function generateReference(): string
    {
        return Helpers::generateRef('BL');
    }

    public static function generateCodeValidation(): string
    {
        return strtoupper(bin2hex(random_bytes(3)));
    }

    public static function getTodayBillets(): array
    {
        return self::raw(
            "SELECT b.*, v.depart, v.destination, v.date_depart
            FROM billets b JOIN voyages v ON b.voyage_id = v.id
            WHERE DATE(b.date_emission) = CURDATE() ORDER BY b.created_at DESC"
        );
    }

    public static function getRecent(int $limit = 20): array
    {
        return self::raw(
            "SELECT b.*, v.depart, v.destination, v.date_depart
            FROM billets b JOIN voyages v ON b.voyage_id = v.id
            ORDER BY b.created_at DESC LIMIT ?", [$limit]
        );
    }

    public static function getStats(): object
    {
        return self::db()->fetch("SELECT COUNT(*) as total,
            SUM(CASE WHEN statut = 'CONFIRME' THEN 1 ELSE 0 END) as confirmes,
            SUM(CASE WHEN statut = 'ANNULE' THEN 1 ELSE 0 END) as annules,
            COALESCE(SUM(CASE WHEN statut = 'CONFIRME' THEN montant_total ELSE 0 END), 0) as recette
        FROM billets");
    }

    public static function getRecetteJour(): float
    {
        $r = self::db()->fetch("SELECT COALESCE(SUM(montant_total), 0) as total FROM billets WHERE DATE(date_emission) = CURDATE() AND statut = 'CONFIRME'");
        return (float) $r->total;
    }

    public static function getUnsynced(): array
    {
        return self::raw("SELECT * FROM billets WHERE synced = 0");
    }

    public static function getByVoyage(int $voyageId): array
    {
        return self::raw(
            "SELECT b.*, c.nom as client_nom, c.prenom as client_prenom
            FROM billets b LEFT JOIN clients c ON b.client_id = c.id
            WHERE b.voyage_id = ? ORDER BY b.siege ASC", [$voyageId]
        );
    }
}