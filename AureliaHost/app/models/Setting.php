<?php
class Setting extends Model
{
    protected string $table = 'settings';

    private static ?array $cache = null;

    /** Récupère une valeur depuis le cache */
    public static function get(string $key, string $default = ''): string
    {
        if (self::$cache === null) {
            self::loadCache();
        }
        return self::$cache[$key] ?? $default;
    }

    /** Recharge le cache depuis la base */
    public static function loadCache(): void
    {
        $instance = new static();
        $all = $instance->all();
        self::$cache = [];
        foreach ($all as $row) {
            self::$cache[$row['cle']] = $row['valeur'];
        }
    }

    /** Définit une valeur (écrit DB + met à jour cache) */
    public static function set(string $key, string $value): void
    {
        $instance = new static();
        $existing = $instance->queryOne("SELECT id FROM settings WHERE cle = :c", ['c' => $key]);
        if ($existing) {
            $instance->update($existing['id'], ['valeur' => $value]);
        } else {
            $instance->create(['cle' => $key, 'valeur' => $value]);
        }
        self::$cache[$key] = $value;
    }

    /** Retourne toutes les clés */
    public static function allCached(): array
    {
        if (self::$cache === null) {
            self::loadCache();
        }
        return self::$cache;
    }
}
