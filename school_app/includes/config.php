<?php
// includes/config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'complexe_scolaire');
define('APP_NAME', 'Complexe Scolaire');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://localhost/school_app/');

session_start();

$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('<div style="background:#fee;padding:20px;border-radius:8px;font-family:sans-serif;">
        <h3 style="color:#c00">⚠️ Erreur de connexion à la base de données</h3>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <p>Vérifiez que XAMPP (MySQL) est démarré et que la base <b>' . DB_NAME . '</b> existe.</p>
    </div>');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function hasRole(...$roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function getAnneeActive(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM annees_scolaires WHERE active=1 LIMIT 1");
    return $stmt->fetch();
}
