<?php
// includes/config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'zimbrax');
define('APP_NAME', 'ZimbraX');
define('APP_VERSION', '2.0');
define('BASE_URL', 'http://localhost/zimbrax/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/attachments/');
define('MAX_UPLOAD_MB', 25);
define('SESSION_LIFETIME', 86400); // 24h

session_set_cookie_params([
  'lifetime' => SESSION_LIFETIME,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

// PDO Connection
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#0f1117;color:#f75f5f;min-height:100vh;">
        <h2>⚠ Erreur de connexion MySQL</h2>
        <p style="color:#8b8fa8">'.$e->getMessage().'</p>
        <p style="color:#8b8fa8">Vérifiez que XAMPP (MySQL) est démarré et que la base <b style="color:#e2e4f0">zimbrax</b> existe.</p>
    </div>');
}

// ── Auth helpers ───────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: '.BASE_URL.'login.php');
        exit();
    }
    // Verify user actually exists in DB
    $user = currentUser();
    if (empty($user) || !isset($user['id'])) {
        $_SESSION = [];
        session_destroy();
        session_start();
        header('Location: '.BASE_URL.'login.php');
        exit();
    }
}
function isAdmin(): bool {
    return isLoggedIn() && (currentUser()['is_admin'] ?? 0) == 1;
}
function currentUser(): array {
    global $pdo;
    if (!isLoggedIn()) return [];
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return [];
    unset($user['password'], $user['mail_password']);
    $_SESSION['user'] = $user;
    $cached = $user;
    return $user;
}
function redirect(string $url): void {
    header("Location: $url"); exit();
}

// ── Flash messages ─────────────────────────────────────────
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
    }
    return null;
}

// ── Utilities ──────────────────────────────────────────────
function sanitize(?string $s): string {
    return htmlspecialchars(trim($s ?? ''), ENT_QUOTES, 'UTF-8');
}
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'À l\'instant';
    if ($diff < 3600)     return floor($diff/60).' min';
    if ($diff < 86400)    return floor($diff/3600).'h';
    if ($diff < 604800)   return date('d M', strtotime($datetime));
    return date('d/m/Y', strtotime($datetime));
}
function formatSize(int $bytes): string {
    if ($bytes < 1024) return $bytes.' B';
    if ($bytes < 1048576) return round($bytes/1024,1).' Ko';
    return round($bytes/1048576,1).' Mo';
}
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    return strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
}
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
function apiError(string $msg, int $code = 400): void {
    jsonResponse(['success'=>false,'error'=>$msg], $code);
}
function apiSuccess(array $data = []): void {
    jsonResponse(array_merge(['success'=>true], $data));
}

// ── Folder helpers ─────────────────────────────────────────
function getFolders(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT f.*, COUNT(CASE WHEN e.is_read=0 THEN 1 END) as unread_count
        FROM folders f
        LEFT JOIN emails e ON e.folder_id=f.id
        WHERE f.user_id=?
        GROUP BY f.id ORDER BY f.sort_order
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
