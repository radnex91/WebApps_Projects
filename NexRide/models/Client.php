<?php
namespace Models;

use Core\Model;

class Client extends Model
{
    protected static string $table = 'clients';
    protected array $fillable = ['nom', 'prenom', 'telephone', 'email', 'adresse', 'piece_identite', 'num_piece', 'notes'];
    protected array $casts = ['id' => 'int'];

    public static function search(string $query): array
    {
        return self::raw(
            "SELECT * FROM clients WHERE nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? ORDER BY nom ASC LIMIT 20",
            ["%{$query}%", "%{$query}%", "%{$query}%"]
        );
    }

    public function getNomComplet(): string
    {
        return trim(($this->prenom ?? '') . ' ' . $this->nom);
    }
}