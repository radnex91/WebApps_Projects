<?php
// ============================================
// StockPro - Configuration
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'stockpro');
define('BASE_URL', 'http://localhost/stockpro');
define('APP_VERSION', '1.0.0');

// ============================================
// SÉCURITÉ SESSION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    // Configuration sécurisée des cookies de session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', 0); // Mettre à 1 si HTTPS activé

    session_start();

    // Génération token CSRF si inexistant
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// ============================================
// FONCTIONS CSRF
// ============================================
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token'] ?? '').'">';
}

function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('<h1>Token CSRF invalide</h1>');
        }
    }
}

// Connexion PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch(PDOException $e) {
            error_log('Erreur DB: '.$e->getMessage());
            die(json_encode(['error' => 'Erreur de connexion à la base de données']));
        }
    }
    return $pdo;
}

// Vérification auth
function requireAuth($roles = []) {
    if (empty($_SESSION['user'])) {
        header('Location: '.BASE_URL.'/index.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403);
        die('<h1>Accès refusé</h1>');
    }
}

// Récupérer config entreprise
function getEntreprise() {
    static $ent = null;
    if ($ent === null) {
        $db = getDB();
        $ent = $db->query("SELECT * FROM entreprise LIMIT 1")->fetch();
    }
    return $ent;
}

// Permissions par rôle (avec cache SESSION)
function canDo($action) {
    static $cache = null;
    $role = $_SESSION['user']['role'] ?? 'lecteur';
    $cacheKey = 'perms_'.$role;

    if ($cache === null) {
        $cache = $_SESSION['permissions_cache'] ?? [];
    }

    if (isset($cache[$cacheKey][$action])) {
        return $cache[$cacheKey][$action];
    }

    $permissions = [
        'super_admin' => ['*'],
        'admin'       => ['produit_*','categorie_*','fournisseur_*','mouvement_*','user_view','rapport_*'],
        'gestionnaire'=> ['produit_*','categorie_view','fournisseur_view','mouvement_*'],
        'caissier'    => ['produit_view','mouvement_sortie'],
        'lecteur'     => ['produit_view','categorie_view'],
    ];
    $perms = $permissions[$role] ?? [];

    $result = false;
    if (in_array('*', $perms)) {
        $result = true;
    } else {
        foreach ($perms as $p) {
            if ($p === $action) { $result = true; break; }
            if (str_ends_with($p, '_*')) {
                $prefix = rtrim($p, '*');
                if (str_starts_with($action, $prefix)) { $result = true; break; }
            }
        }
    }

    $cache[$cacheKey][$action] = $result;
    $_SESSION['permissions_cache'] = $cache;
    return $result;
}

function formatMoney($val, $devise = null) {
    if (!$devise) {
        $ent = getEntreprise();
        $devise = $ent['devise'] ?? 'FCFA';
    }
    return number_format($val, 0, ',', ' ').' '.$devise;
}

// ============================================
// VALIDATION DES ENTRÉES
// ============================================
function validateString($str, $max = 255, $required = false) {
    $str = trim($str);
    if ($required && $str === '') return false;
    if (mb_strlen($str, 'UTF-8') > $max) return false;
    return true;
}

function validateEmail($email) {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^[+]?[(]?[0-9]{1,4}[)]?[-\s./0-9]*$/', trim($phone));
}

function clean($str, $max = 255) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function validateInt($val, $min = 0, $max = null) {
    $int = (int)$val;
    if ($int < $min) return false;
    if ($max !== null && $int > $max) return false;
    return true;
}

function validateFloat($val, $min = 0, $max = null) {
    $float = (float)$val;
    if ($float < $min) return false;
    if ($max !== null && $float > $max) return false;
    return true;
}

function validateColor($color) {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color);
}

// ============================================
// JOURNAL D'AUDIT
// ============================================
function logAudit($db, $action, $entity, $entity_id = null, $details = null) {
    try {
        $db->prepare("INSERT INTO audit_log (utilisateur_id, action, entity, entity_id, new_values, ip_address, created_at) VALUES (?,?,?,?,?,?,NOW())")
           ->execute([
               $_SESSION['user']['id'] ?? null,
               $action,
               $entity,
               $entity_id,
               $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);
    } catch (Exception $e) {
        // Ne pas bloquer l'opération principale si l'audit échoue
        error_log('Audit log error: ' . $e->getMessage());
    }
}

// ============================================
// HELPERS COMMUNS
// ============================================
function movementBadge($type) {
    $types = [
        'entree'      => ['Entrée', 'badge-green'],
        'sortie'      => ['Sortie', 'badge-red'],
        'ajustement'  => ['Ajust.', 'badge-blue'],
        'retour'      => ['Retour', 'badge-orange'],
    ];
    return $types[$type] ?? [$type, 'badge-gray'];
}

function paginate($total, $per_page, $current_page) {
    $total_pages = max(1, ceil($total / $per_page));
    $offset = ($current_page - 1) * $per_page;
    return ['total' => $total, 'pages' => $total_pages, 'offset' => $offset, 'current' => $current_page, 'per' => $per_page];
}

function paginationHtml($pg, $base_url, $params = []) {
    if ($pg['pages'] <= 1) return '';
    $html = '<div class="pagination">';
    $html .= '<span class="page-info">'.$pg['total'].' résultat(s) — page '.$pg['current'].' / '.$pg['pages'].'</span>';
    $start = max(1, $pg['current'] - 2);
    $end = min($pg['pages'], $pg['current'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $qs = array_merge($params, ['p' => $i]);
        $html .= '<a href="'.$base_url.'?'.http_build_query($qs).'" class="page-btn '.($i === $pg['current'] ? 'active' : '').'">'.$i.'</a>';
    }
    $html .= '</div>';
    return $html;
}
