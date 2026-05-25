<?php
namespace Models;

use Core\Model;

class Chauffeur extends Model
{
    protected static string $table = 'chauffeurs';
    protected array $fillable = ['nom', 'prenom', 'telephone', 'email', 'permis', 'categorie_permis', 'date_naissance', 'adresse', 'date_embauche', 'actif'];
    protected array $casts = ['id' => 'int', 'actif' => 'bool'];

    public static function getDisponibles(): array
    {
        return self::raw("SELECT * FROM chauffeurs WHERE actif = 1 ORDER BY nom ASC");
    }

    public function getNomComplet(): string
    {
        return ($this->prenom ?? '') . ' ' . $this->nom;
    }
}