<?php
namespace Models;

use Core\Model;
use Core\Helpers;

class Voyage extends Model
{
    protected static string $table = 'voyages';
    protected array $fillable = ['reference', 'titre', 'type_voyage', 'depart', 'destination', 'date_depart', 'date_arrivee', 'vehicule_id', 'chauffeur_id', 'tarif_base', 'nombre_places', 'places_disponibles', 'statut', 'notes', 'created_by'];
    protected array $casts = ['id' => 'int', 'tarif_base' => 'float', 'nombre_places' => 'int', 'places_disponibles' => 'int', 'vehicule_id' => 'int', 'chauffeur_id' => 'int'];

    public static function generateReference(): string
    {
        return Helpers::generateRef('VY');
    }

    public static function getVoyagesDuJour(): array
    {
        return self::raw(
            "SELECT v.*, veh.immatriculation, CONCAT(ch.prenom, ' ', ch.nom) as chauffeur_nom
            FROM voyages v
            LEFT JOIN vehicules veh ON v.vehicule_id = veh.id
            LEFT JOIN chauffeurs ch ON v.chauffeur_id = ch.id
            WHERE DATE(v.date_depart) = CURDATE()
            ORDER BY v.date_depart ASC"
        );
    }

    public static function getVoyagesByDate(string $date): array
    {
        return self::raw(
            "SELECT v.*, veh.immatriculation, CONCAT(ch.prenom, ' ', ch.nom) as chauffeur_nom
            FROM voyages v LEFT JOIN vehicules veh ON v.vehicule_id = veh.id
            LEFT JOIN chauffeurs ch ON v.chauffeur_id = ch.id
            WHERE DATE(v.date_depart) = ? ORDER BY v.date_depart ASC",
            [$date]
        );
    }

    public static function getStats(): object
    {
        return self::db()->fetch("SELECT COUNT(*) as total,
            SUM(CASE WHEN statut = 'PROGRAMME' THEN 1 ELSE 0 END) as programmes,
            SUM(CASE WHEN statut = 'EN_COURS' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'TERMINE' THEN 1 ELSE 0 END) as termines
        FROM voyages");
    }

    public function getRecette(): float
    {
        $r = self::db()->fetch("SELECT COALESCE(SUM(montant_total), 0) as total FROM billets WHERE voyage_id = ? AND statut = 'CONFIRME'", [$this->id]);
        return (float) $r->total;
    }

    public function getNombrePassagers(): int
    {
        $r = self::db()->fetch("SELECT COUNT(*) as total FROM billets WHERE voyage_id = ? AND statut = 'CONFIRME'", [$this->id]);
        return (int) $r->total;
    }
}