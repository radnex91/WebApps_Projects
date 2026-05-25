<?php
namespace Models;

use Core\Model;

class Vehicule extends Model
{
    protected static string $table = 'vehicules';
    protected array $fillable = ['immatriculation', 'marque', 'modele', 'annee', 'capacite', 'type_vehicule', 'couleur', 'assurance_date', 'visite_technique_date', 'actif'];
    protected array $casts = ['id' => 'int', 'capacite' => 'int', 'annee' => 'int', 'actif' => 'bool'];

    public static function getDisponibles(): array
    {
        return self::raw("SELECT * FROM vehicules WHERE actif = 1 ORDER BY marque ASC");
    }
}