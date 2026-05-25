<?php
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
            if (IS_PROD) {
                error_log('PharmaCare DB Error: ' . $e->getMessage());
                header('HTTP/1.1 503 Service Unavailable');
                die('<div style="font-family:sans-serif;padding:40px;background:#1a0a0a;color:#ff5e57;border:1px solid #ff5e57;border-radius:8px;margin:40px auto;max-width:600px;">
                    <h3>Service temporairement indisponible</h3>
                    <p style="color:#aaa;font-size:13px;">L\'application est en maintenance. Veuillez réessayer dans quelques minutes.</p>
                </div>');
            }
            die('<div style="font-family:sans-serif;padding:40px;background:#1a0a0a;color:#ff5e57;border:1px solid #ff5e57;border-radius:8px;margin:40px auto;max-width:600px;">
                <h3>Erreur de connexion à la base de données</h3>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p style="color:#aaa;font-size:13px;">Vérifiez que XAMPP est démarré et que la base <strong>pharmacare</strong> existe.</p>
            </div>');
        }
    }
    return $pdo;
}
