<?php
// includes/config.php
define('DB_HOST',  'localhost');
define('DB_USER',  'root');
define('DB_PASS',  '');
define('DB_NAME',  'gesttrans');
define('APP_NAME', 'GestTrans Pro');
define('APP_VER',  '1.0');
define('BASE_URL', 'http://localhost/gesttrans/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

session_start();

// ── PDO ─────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]);
} catch (PDOException $e) {
    die('<div style="font-family:Arial,sans-serif;padding:40px;background:#fef2f2;border:2px solid #dc2626;border-radius:8px;max-width:600px;margin:60px auto;">
        <h2 style="color:#dc2626">⚠ Erreur de connexion MySQL</h2>
        <p>'.$e->getMessage().'</p>
        <p>Vérifiez que XAMPP est démarré et que la base <strong>gesttrans</strong> a été créée.</p>
    </div>');
}

// ── AUTH ────────────────────────────────────────────────────
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function requireLogin(): void { if (!isLoggedIn()) { header('Location: '.BASE_URL.'login.php'); exit(); } }
function currentUser(): array { return $_SESSION['user'] ?? []; }
function can(string $p): bool { return isset($_SESSION['permissions']) && in_array($p, $_SESSION['permissions']); }
function requirePerm(string $p): void { if (!can($p)) { flash('Permission insuffisante.','danger'); redirect(BASE_URL.'index.php'); } }
function hasRole(string ...$r): bool { return isset($_SESSION['role_code']) && in_array($_SESSION['role_code'], $r); }
function isSuperAdmin(): bool { return hasRole('super_admin'); }
function isAdmin(): bool { return hasRole('super_admin','admin'); }
function isChef(): bool { return hasRole('super_admin','admin','chef_agence'); }
function getUserAgenceId(): ?int { return $_SESSION['user']['agence_id'] ?? null; }

// ── FLASH ────────────────────────────────────────────────────
function flash(string $m, string $t='success'): void { $_SESSION['flash']=['msg'=>$m,'type'=>$t]; }
function getFlash(): ?array { if(isset($_SESSION['flash'])){$f=$_SESSION['flash'];unset($_SESSION['flash']);return $f;} return null; }
function redirect(string $u): void { header("Location: $u"); exit(); }
function h(string $s): string { return htmlspecialchars(trim($s),ENT_QUOTES,'UTF-8'); }
function sanitize(string $s): string { return h($s); }

// ── FORMAT ───────────────────────────────────────────────────
function money(float $n): string { return number_format($n,0,',',' ').' FCFA'; }
function moneyRaw(float $n): string { return number_format($n,0,',',' '); }
function fdate(?string $d): string { return ($d && $d !== '0000-00-00') ? date('d/m/Y',strtotime($d)) : '—'; }
function fdatetime(?string $d): string { return ($d && $d !== '0000-00-00 00:00:00') ? date('d/m/Y H:i',strtotime($d)) : '—'; }
function timeAgo(string $dt): string {
    $d=time()-strtotime($dt);
    if($d<60) return 'À l\'instant'; if($d<3600) return floor($d/60).'mn';
    if($d<86400) return floor($d/3600).'h'; return fdate($dt);
}
function initials(string $n): string {
    $p=array_filter(explode(' ',trim($n)));
    if(count($p)>=2) return strtoupper(substr($p[0],0,1).substr(end($p),0,1));
    return strtoupper(substr($n,0,2));
}

// ── PARAMS ───────────────────────────────────────────────────
function getParam(string $key, string $default=''): string {
    global $pdo;
    try { $s=$pdo->prepare("SELECT valeur FROM parametres WHERE cle=?"); $s->execute([$key]); $r=$s->fetchColumn(); return $r!==false?$r:$default; } catch(Exception $e){ return $default; }
}

// ── NUMÉRO AUTO ──────────────────────────────────────────────
function nextNum(PDO $pdo, string $table, string $col, string $prefix=''): string {
    $date=date('Ymd');
    $max=$pdo->prepare("SELECT MAX($col) FROM $table WHERE $col LIKE ?");
    $max->execute(["$prefix%"]);
    $last=(int)$max->fetchColumn();
    return $prefix.($last+1);
}

// ── LOG ──────────────────────────────────────────────────────
function logAction(PDO $pdo, string $action, string $module, string $details=''): void {
    if(!isLoggedIn()) return;
    try { $pdo->prepare("INSERT INTO logs (user_id,action,module,details,ip) VALUES (?,?,?,?,?)")->execute([$_SESSION['user_id'],$action,$module,$details,$_SERVER['REMOTE_ADDR']??'']); } catch(Exception $e){}
}
