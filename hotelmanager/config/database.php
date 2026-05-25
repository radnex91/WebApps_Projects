<?php
/**
 * HotelPro Suite - Connexion PDO (Singleton)
 */

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Retourne l'instance PDO unique (pattern Singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // En production, ne jamais exposer les détails de l'erreur
                error_log('Database connection failed: ' . $e->getMessage());
                die(json_encode([
                    'error' => true,
                    'message' => 'Connexion à la base de données impossible. Vérifiez votre configuration XAMPP.'
                ]));
            }
        }

        return self::$instance;
    }

    /**
     * Raccourci : exécute une requête préparée et retourne le statement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne le dernier ID inséré
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }
}
