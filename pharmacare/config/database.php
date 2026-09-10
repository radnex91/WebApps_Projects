<?php
declare(strict_types=1);
// ── Configuration chargée depuis env.php ─────────────────
require_once __DIR__ . '/env.php';

// ── Connexion PDO ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Page d'erreur 503 brandée (includes/erreur.php) — robuste, sans BDD.
            require_once __DIR__ . '/../includes/erreur.php';
            if (IS_PROD) {
                error_log('PharmaCare DB Error: ' . $e->getMessage());
                afficher_erreur(
                    503,
                    'Service indisponible',
                    'L\'application est temporairement indisponible. Veuillez réessayer dans quelques minutes.',
                    'Erreur 503 · Maintenance / base de données',
                    '503'
                );
            } else {
                afficher_erreur(
                    503,
                    'Base de données inaccessible',
                    'Vérifiez que XAMPP (MySQL) est démarré et que la base « pharmacare » existe.',
                    'Erreur 503 · ' . $e->getMessage(),
                    '503'
                );
            }
            exit;
        }
    }
    return $pdo;
}
