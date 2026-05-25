<?php
// includes/config.php
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'techschool');
define('APP_NAME',   'TechSchool');
define('APP_VER',    '1.0');
define('BASE_URL',   'http://localhost/techschool/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

session_start();

// ── PDO ────────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch(PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#fef2f2;border:2px solid #dc2626;border-radius:8px;max-width:600px;margin:60px auto;">
        <h2 style="color:#dc2626">⚠ Erreur MySQL</h2>
        <p>'.$e->getMessage().'</p>
        <p>Vérifiez que XAMPP est démarré et que la base <strong>techschool</strong> existe.</p>
    </div>');
}

// ── AUTH ────────────────────────────────────────────────────────
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: '.BASE_URL.'login.php'); exit(); }
}

function currentUser(): array { return $_SESSION['user'] ?? []; }

function can(string $perm): bool {
    return isset($_SESSION['permissions']) && in_array($perm, $_SESSION['permissions']);
}

function requirePerm(string $perm): void {
    if (!can($perm)) { flash('Permission insuffisante.','danger'); redirect(BASE_URL.'index.php'); }
}

function hasRole(string ...$roles): bool {
    return isset($_SESSION['role_code']) && in_array($_SESSION['role_code'], $roles);
}

function isSuperAdmin(): bool { return hasRole('super_admin'); }

// ── FLASH ────────────────────────────────────────────────────────
function flash(string $msg, string $type='success'): void {
    $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type];
}
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

// ── UTILS ────────────────────────────────────────────────────────
function redirect(string $url): void { header("Location: $url"); exit(); }
function sanitize(string $s): string { return htmlspecialchars(trim($s),ENT_QUOTES,'UTF-8'); }
function initials(string $n): string {
    $p=explode(' ',trim($n));
    return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));
}
function formatMoney(float $n): string { return number_format($n,0,',',' ').' FCFA'; }
function formatDate(string $d): string {
    return $d ? date('d/m/Y', strtotime($d)) : '—';
}
function timeAgo(string $dt): string {
    $d=time()-strtotime($dt);
    if($d<60) return 'À l\'instant';
    if($d<3600) return floor($d/60).' min';
    if($d<86400) return floor($d/3600).'h';
    return date('d/m/Y',strtotime($dt));
}

// ── ANNEE ACTIVE ─────────────────────────────────────────────────
function getAnneeActive(PDO $pdo): array {
    $s=$pdo->query("SELECT * FROM annees_scolaires WHERE active=1 LIMIT 1");
    return $s->fetch() ?: [];
}

// ── MENTION ──────────────────────────────────────────────────────
function getMention(float $moy, array $config=[]): array {
    $se = $config['seuil_excellent'] ?? 16;
    $st = $config['seuil_tb']        ?? 14;
    $sb = $config['seuil_b']         ?? 12;
    $sa = $config['seuil_ab']        ?? 10;
    $me = $config['mention_excellent']   ?? 'Très Bien';
    $mt = $config['mention_tb']          ?? 'Bien';
    $mb = $config['mention_b']           ?? 'Assez Bien';
    $ma = $config['mention_ab']          ?? 'Passable';
    $mi = $config['mention_insuffisant'] ?? 'Insuffisant';
    if($moy>=$se) return ['label'=>$me,'class'=>'mention-excellent','color'=>'#16a34a'];
    if($moy>=$st) return ['label'=>$mt,'class'=>'mention-tb','color'=>'#2563eb'];
    if($moy>=$sb) return ['label'=>$mb,'class'=>'mention-b','color'=>'#0891b2'];
    if($moy>=$sa) return ['label'=>$ma,'class'=>'mention-ab','color'=>'#d97706'];
    return ['label'=>$mi,'class'=>'mention-insuf','color'=>'#dc2626'];
}

// ── CALCULER BULLETIN ─────────────────────────────────────────────
function calculerBulletin(PDO $pdo, int $eleveId, int $periodeId, int $anneeId): array {
    $stmt = $pdo->prepare("
        SELECT n.*, m.nom as matiere_nom, m.type as matiere_type, m.code as matiere_code,
               mc.coefficient,
               CONCAT(e.prenom,' ',e.nom) as enseignant_nom
        FROM notes n
        JOIN matieres m       ON n.matiere_id = m.id
        JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=n.classe_id
        LEFT JOIN enseignants e ON mc.enseignant_id=e.id
        WHERE n.eleve_id=? AND n.periode_id=? AND n.annee_id=?
        ORDER BY m.type, m.nom
    ");
    $stmt->execute([$eleveId, $periodeId, $anneeId]);
    $notes = $stmt->fetchAll();

    $totalPondere = 0; $totalCoeff = 0;
    foreach($notes as &$n) {
        $totalPondere += $n['note'] * $n['coefficient'];
        $totalCoeff   += $n['coefficient'];
    }
    $moyenne = $totalCoeff > 0 ? round($totalPondere/$totalCoeff, 2) : null;
    return ['notes'=>$notes,'moyenne'=>$moyenne,'total_coeff'=>$totalCoeff,'total_pondere'=>$totalPondere];
}

// ── LOG ACTION ────────────────────────────────────────────────────
function logAction(PDO $pdo, string $action, string $module, string $details=''): void {
    if (!isLoggedIn()) return;
    try {
        $pdo->prepare("INSERT INTO logs (user_id,action,module,details,ip) VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['user_id'],$action,$module,$details,$_SERVER['REMOTE_ADDR']??'']);
    } catch(Exception $e) {}
}

// ── GÉNÉRATION MATRICULE ──────────────────────────────────────────
function genMatricule(PDO $pdo, string $prefix, string $table): string {
    $year = date('Y');
    $offset = strlen($prefix.$year) + 1;
    $like = $prefix.$year.'%';
    $last = $pdo->query("SELECT MAX(CAST(SUBSTRING(matricule,$offset) AS UNSIGNED)) FROM $table WHERE matricule LIKE '$like'")->fetchColumn();
    return $prefix.$year.str_pad(($last??0)+1,4,'0',STR_PAD_LEFT);
}
