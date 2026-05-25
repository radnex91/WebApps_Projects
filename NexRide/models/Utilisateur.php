<?php
namespace Models;

use Core\Model;

class Utilisateur extends Model
{
    protected static string $table = 'utilisateurs';
    protected static string $primaryKey = 'id';
    protected array $fillable = ['nom', 'email', 'mot_de_passe', 'telephone', 'role', 'actif', 'avatar', 'derniere_connexion'];
    protected array $casts = ['id' => 'int', 'actif' => 'bool'];

    public static function findByEmail(string $email): ?self
    {
        return self::whereFirst('email', '=', $email);
    }

    public static function getActiveUsers(): array
    {
        return self::raw("SELECT * FROM utilisateurs WHERE actif = 1 ORDER BY nom ASC");
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->attributes['mot_de_passe'] ?? '');
    }

    public function setPassword(string $password): void
    {
        $this->attributes['mot_de_passe'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}