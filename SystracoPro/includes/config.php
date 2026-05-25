<?php
// includes/config.php
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'transport_db');
define('APP_NAME',   'TransportManager');
define('APP_VER',    '1.0');
define('BASE_URL',   'http://localhost/SystracoPro/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

date_default_timezone_set('Africa/Douala');

session_start();
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'french');

// ── PDO ─────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
} catch(PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#fef2f2;border:2px solid #dc2626;border-radius:8px;max-width:600px;margin:60px auto;">
        <h2 style="color:#dc2626">⚠ Erreur MySQL</h2><p>'.$e->getMessage().'</p>
        <p>Vérifiez XAMPP et la base <strong>transport_db</strong>.</p></div>');
}

// ── AUTH ────────────────────────────────────────────────────
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function isIframe(): bool { return isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'frame'; }
function requireLogin(): void { 
    if (!isLoggedIn()) { 
        if (isIframe()) {
            echo "<script>if(window.parent!==window){window.parent.location.href='".BASE_URL."login.php?iframe=1';}else{window.location.href='".BASE_URL."login.php';}</script>";
            exit();
        }
        header('Location: '.BASE_URL.'login.php'); 
        exit(); 
    } 
}
function currentUser(): array { return $_SESSION['user'] ?? []; }
function currentAgenceId(): ?int { return $_SESSION['user']['agence_id'] ?? null; }
function can(string $p): bool { return isset($_SESSION['permissions']) && in_array($p, $_SESSION['permissions']); }
function requirePerm(string $p): void { if (!can($p)) { flash('Permission insuffisante.','danger'); redirect(BASE_URL.'index.php'); } }
function hasRole(string ...$r): bool { return isset($_SESSION['role_code']) && in_array($_SESSION['role_code'], $r); }
function isSuperAdmin(): bool { return hasRole('super_admin'); }
function isAdmin(): bool { return hasRole('super_admin','admin'); }
function isChefAgence(): bool { return hasRole('super_admin','admin','chef_agence'); }
function isChefGuichet(): bool { return hasRole('super_admin','admin','chef_agence','chef_guichet'); }
function isGuichetier(): bool { return hasRole('guichetier'); }

// ── CAISSE ─────────────────────────────────────────────────
function caisseOuverte(): ?array {
    global $pdo;
    if (!isLoggedIn()) return null;
    $uid = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $s = $pdo->prepare("SELECT * FROM caisses WHERE guichetier_id=? AND DATE(date_ouverture)=? AND statut='ouverte' ORDER BY id DESC LIMIT 1");
    $s->execute([$uid, $today]);
    return $s->fetch() ?: null;
}
function requireCaisseOuverte(): void {
    if (isGuichetier() && !caisseOuverte()) {
        if (isIframe()) {
            echo "<script>if(window.parent!==window){window.parent.location.href='".BASE_URL."modules/caisse/index.php';}else{window.location.href='".BASE_URL."modules/caisse/index.php';}</script>";
            exit();
        }
        flash('Vous devez ouvrir votre caisse avant de vendre des tickets.', 'danger');
        redirect(BASE_URL.'modules/caisse/index.php');
    }
}

// ── CSRF ────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfField(): string { return '<input type="hidden" name="_csrf" value="'.csrfToken().'">'; }
function csrfCheck(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($t) && hash_equals($_SESSION['csrf_token'] ?? '', $t);
}
function requireCsrf(): void { if (!csrfCheck()) { flash('Requête invalide (CSRF).', 'danger'); redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL.'index.php'); } }

// ── AUTO CSRF CHECK on all POST (except login page & JSON API endpoints) ─────────
$isJsonRequest = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && basename($_SERVER['PHP_SELF']) !== 'login.php' && !$isJsonRequest) {
    if (!csrfCheck()) {
        flash('Session expirée ou requête invalide. Veuillez réessayer.', 'danger');
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL.'index.php');
    }
}

// ── FLASH ───────────────────────────────────────────────────
function flash(string $m, string $t='success'): void { $_SESSION['flash']=['msg'=>$m,'type'=>$t]; }
function getFlash(): ?array { if(isset($_SESSION['flash'])){$f=$_SESSION['flash'];unset($_SESSION['flash']);return $f;} return null; }
function redirect(string $u): void { header("Location: $u"); exit(); }
function sanitize(string $s): string { return htmlspecialchars(trim($s),ENT_QUOTES,'UTF-8'); }

// ── FORMATAGE ────────────────────────────────────────────────
function money(float $n): string { return number_format($n,0,',',' ').' '.getParam('monnaie','FCFA'); }
function moneyRaw(float $n): string { return number_format($n,0,',',' '); }
function fdate(string $d): string { return $d ? date('d/m/Y',strtotime($d)) : '—'; }
function fdatetime(string $d): string { return $d ? date('d/m/Y H:i',strtotime($d)) : '—'; }
function moisFrancais(string $date): string {
    static $mois = [1=>'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($date ?: 'now');
    return $mois[(int)date('n',$ts)].' '.date('Y',$ts);
}
function timeAgo(string $dt): string {
    $d=time()-strtotime($dt);
    if($d<60) return 'À l\'instant'; if($d<3600) return floor($d/60).' min';
    if($d<86400) return floor($d/3600).'h'; return date('d/m/Y',strtotime($dt));
}
function initials(string $n): string {
    $p=explode(' ',trim($n));
    return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));
}

// ── PARAMÈTRES ───────────────────────────────────────────────
function getParam(string $key, string $default=''): string {
    global $pdo;
    try { $s=$pdo->prepare("SELECT valeur FROM parametres WHERE cle=?"); $s->execute([$key]); $r=$s->fetchColumn(); return $r!==false?$r:$default; } catch(Exception $e){ return $default; }
}
function getTicketClasses(): array {
    global $pdo;
    try { return $pdo->query("SELECT * FROM ticket_classes WHERE actif=1 ORDER BY ordre,nom")->fetchAll(); } catch(Exception $e){ return [['id'=>0,'nom'=>'Normale','code'=>'normale'],['id'=>0,'nom'=>'VIP','code'=>'vip'],['id'=>0,'nom'=>'Affaires','code'=>'affaires']]; }
}
function ticketClassOptions(string $selected=''): string {
    $html=''; foreach(getTicketClasses() as $c) { $sel=($c['code']===$selected)?'selected':''; $html.='<option value="'.sanitize($c['code']).'" '.$sel.'>'.sanitize($c['nom']).'</option>'; } return $html;
}

// ── LIBELLÉS STATUTS & CATÉGORIES ────────────────────────────
function statutLabel(string $statut, string $module=''): string {
    static $map = [
        // Tickets
        'vendu'=>'Vendu','annule'=>'Annulé','reserve'=>'Réservé','utilise'=>'Voyagé',
        // Classes
        'cla'=>'Classique','vip'=>'VIP','spc'=>'Super Classique',
        // Voyages
        'programme'=>'Programmé','en_cours'=>'En cours','arrive'=>'Arrivé','annule'=>'Annulé','reporte'=>'Reporté',
        // Bordereaux
        'genere'=>'Généré','en_cours'=>'En cours','cloture'=>'Clôturé',
        // Depenses
        'en_attente'=>'En attente','approuve'=>'Approuvé','rejete'=>'Rejeté','paye'=>'Payé',
        // Versements
        'confirme'=>'Confirmé',
        // Reservations
        'active'=>'Activée','confirmee'=>'Confirmée','annulee'=>'Annulée','expiree'=>'Expirée',
        // Personnel
        'actif'=>'Actif','inactif'=>'Inactif','conge'=>'En congé',
        // Vehicules
        'panne'=>'En panne','maintenance'=>'Maintenance','hors_service'=>'Hors service',
        // Correspondance tickets
        'embarque'=>'Embarqué',
    ];
    return $map[$statut] ?? ucfirst(str_replace('_',' ',$statut));
}
function categorieDepLabel(string $cat): string {
    static $map = ['reparation'=>'Réparation','carburant'=>'Carburant','salaire'=>'Salaire','loyer'=>'Loyer','fourniture'=>'Fourniture','peage'=>'Péage','autre'=>'Autre'];
    return $map[$cat] ?? ucfirst($cat);
}

// ── GÉNÉRATION NUMÉROS ────────────────────────────────────────
function genNumero(PDO $pdo, string $table, string $col, string $prefix, string $agenceCode=''): string {
    $date=date('Ymd');
    if ($agenceCode) {
        $prefixFull="T$agenceCode$date";
        $like="$prefixFull-%";
        $max=$pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX($col,'-',-1) AS UNSIGNED)) FROM $table WHERE $col LIKE ?");
        $max->execute([$like]);
        $n=($max->fetchColumn()??0)+1;
        return "$prefixFull-".str_pad($n,4,'0',STR_PAD_LEFT);
    }
    $like="$prefix-$date-%";
    $max=$pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX($col,'-',-1) AS UNSIGNED)) FROM $table WHERE $col LIKE ?");
    $max->execute([$like]);
    $n=($max->fetchColumn()??0)+1;
    return "$prefix-$date-".str_pad($n,4,'0',STR_PAD_LEFT);
}

// ── NOTIFICATIONS ────────────────────────────────────────────
function addNotif(PDO $pdo, ?int $userId, ?int $agenceId, string $type, string $titre, string $msg, string $url=''): void {
    try { $pdo->prepare("INSERT INTO notifications (user_id,agence_id,type,titre,message,url) VALUES (?,?,?,?,?,?)")->execute([$userId,$agenceId,$type,$titre,$msg,$url]); } catch(Exception $e){}
}
function countUnreadNotifs(PDO $pdo, int $userId): int {
    $s=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND lue=0"); $s->execute([$userId]); return (int)$s->fetchColumn();
}

// ── AGENCE ACTIVE (pour multi-agence) ────────────────────────
function getUserAgenceId(): ?int { return $_SESSION['user']['agence_affectation_id'] ?? $_SESSION['user']['agence_id'] ?? null; }
function getUserAgenceRattachementId(): ?int { return $_SESSION['user']['agence_id'] ?? null; }
function isAffecte(): bool { return !empty($_SESSION['user']['agence_affectation_id']); }
function getUserAgenceCode(): string {
    global $pdo;
    $aid = getUserAgenceId();
    if (!$aid) return 'XXX';
    static $cache = [];
    if (isset($cache[$aid])) return $cache[$aid];
    $s = $pdo->prepare("SELECT code FROM agences WHERE id=?"); $s->execute([$aid]);
    $code = $s->fetchColumn() ?: 'XXX';
    $cache[$aid] = $code;
    return $code;
}

// ── LOG ──────────────────────────────────────────────────────
function logAction(PDO $pdo, string $action, string $module, string $details=''): void {
    if(!isLoggedIn()) return;
    try { $pdo->prepare("INSERT INTO logs (user_id,action,module,details,ip) VALUES (?,?,?,?,?)")->execute([$_SESSION['user_id'],$action,$module,$details,$_SERVER['REMOTE_ADDR']??'']); } catch(Exception $e){}
}

// ── AGENCE ACTIVE (pour multi-agence) ────────────────────────
function agenceFilter(string $alias='a'): string {
    if (isAdmin()) return '1=1';
    $aid = getUserAgenceId();
    return $aid ? "$alias.id=$aid" : '1=0';
}
function ticketAgenceFilter(string $alias='t'): string {
    if (isAdmin()) return '1=1';
    $aid = getUserAgenceId();
    return $aid ? "$alias.agence_id=$aid" : '1=0';
}
function getUserAgences(PDO $pdo, int $userId): array {
    $s = $pdo->prepare("SELECT ua.*, a.ville, a.nom FROM user_agences ua JOIN agences a ON ua.agence_id=a.id WHERE ua.user_id=? ORDER BY ua.is_principal DESC, a.ville");
    $s->execute([$userId]);
    return $s->fetchAll();
}
