<?php
// ============================================================
//  MediCore ERP  Sécurité centrale
//  Protection : SQL injection, XSS, CSRF, URL tampering,
//               Path traversal, Rate limiting, Headers HTTP
// ============================================================

//  0. OUTPUT BUFFERING
// Dmarre le tampon de sortie pour viter "headers already sent"
// Doit tre le PREMIER appel dans toute la chane d'includes
if (ob_get_level() === 0) {
    ob_start();
}

//  1. EN-TTES DE SCURIT HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com;");

//  2. CSRF TOKEN
if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        _log_error('SECURITY', 'Token CSRF invalide', __FILE__, __LINE__);
        _respond_error(403, 'Token CSRF invalide. Rafraîchissez la page.');
    }
}

//  3. NETTOYAGE & VALIDATION DES ENTRES

/**
 * Nettoie une chane contre XSS et injection
 */
function clean(string $val): string {
    return htmlspecialchars(trim(strip_tags($val)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Récupère un paramtre GET nettoy
 */
function get_str(string $key, string $default = ''): string {
    $val = $_GET[$key] ?? $default;
    return clean((string)$val);
}

/**
 * Récupère un paramtre GET entier, valid (protection contre manipulation d'ID)
 */
function get_int(string $key, int $default = 0, int $min = 1, int $max = PHP_INT_MAX): int {
    $val = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, [
        'options' => ['default' => $default, 'min_range' => $min, 'max_range' => $max]
    ]);
    return $val === false ? $default : (int)$val;
}

/**
 * Récupère un paramtre POST nettoy
 */
function post_str(string $key, string $default = ''): string {
    $val = $_POST[$key] ?? $default;
    return clean((string)$val);
}

/**
 * Récupère un paramtre POST entier valid
 */
function post_int(string $key, int $default = 0): int {
    return (int)filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, [
        'options' => ['default' => $default]
    ]);
}

/**
 * Récupère un paramtre POST dcimal valid
 */
function post_float(string $key, float $default = 0.0): float {
    $val = filter_input(INPUT_POST, $key, FILTER_VALIDATE_FLOAT);
    return $val === false ? $default : (float)$val;
}

/**
 * Récupère un email valid ou null
 */
function post_email(string $key): ?string {
    $val = filter_input(INPUT_POST, $key, FILTER_VALIDATE_EMAIL);
    return $val ?: null;
}

/**
 * Récupère un nom d'utilisateur validé ou null
 * Format: a-z0-9_. uniquement, 3-50 caractères, forcé en minuscule
 */
function post_username(string $key): ?string {
    $val = filter_input(INPUT_POST, $key, FILTER_DEFAULT);
    if (!$val) return null;
    $val = trim(strip_tags($val));
    if (!preg_match('/^[a-z0-9_\.]{3,50}$/', strtolower($val))) return null;
    return strtolower($val);
}

/**
 * Valide une date au format YYYY-MM-DD
 */
function validate_date(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Valide qu'une valeur appartient  une liste blanche
 */
function in_whitelist(string $val, array $allowed, string $default = ''): string {
    return in_array($val, $allowed, true) ? $val : $default;
}

//  4. PROTECTION URL (ID tampering)

/**
 * Génère un token sign pour un ID (empche la falsification d'URL)
 * Usage: href="patients.php?id=5&tok=<?= url_sign(5) ?>"
 */
function url_sign(int $id, string $context = 'default'): string {
    $secret = APP_SECRET . $context . ($_SESSION['user_id'] ?? '');
    return substr(hash_hmac('sha256', $id . $context, $secret), 0, 16);
}

/**
 * Vrifie la signature d'un ID en URL
 */
function url_verify(int $id, string $token, string $context = 'default'): bool {
    return hash_equals(url_sign($id, $context), $token);
}

/**
 * Récupère un ID sign depuis l'URL, die si invalide
 */
function get_signed_id(string $key = 'id', string $context = 'default'): int {
    $id  = get_int($key);
    $tok = get_str('tok');
    if ($id <= 0 || !url_verify($id, $tok, $context)) {
        _log_error('SECURITY', 'ID signé invalide (key=' . $key . ', context=' . $context . ')', __FILE__, __LINE__);
        http_response_code(403);
        _respond_error(403, 'Lien invalide ou expiré.');
    }
    return $id;
}

//  5. REQUTES SQL SCURISES

/**
 * Excute une requte SELECT prpare, retourne tous les rsultats
 */
function db_select(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Retourne une seule ligne
 */
function db_row(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Retourne une seule valeur scalaire
 */
function db_scalar(string $sql, array $params = []): mixed {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Excute INSERT/UPDATE/DELETE, retourne le lastInsertId ou rowCount
 */
function db_exec(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $lid = getDB()->lastInsertId();
    return $lid ? (int)$lid : $stmt->rowCount();
}

/**
 * Vrifie qu'un enregistrement appartient  la ressource autorise
 * Empche l'accs IDOR (Insecure Direct Object Reference)
 */
function assert_owns(string $table, int $id, string $col = 'id'): array {
    $allowed_tables = ['patients','utilisateurs','hospitalisations','rendez_vous','factures','medicaments','analyses','stocks','lits','stock_entries','stock_entry_lignes','observations_infirmieres','administration_medicaments','notes_cliniques','triage_urgences','interventions_chirurgicales','examens_imagerie','suivi_grossesses','accouchements','nouveau_nes','certificats_deces','compagnies_assurance','prises_en_charge','comptes_patients','consentements_patients'];
    if (!in_array($table, $allowed_tables, true)) {
        _log_error('SECURITY', 'Tentative accès table non autorisée: ' . $table, __FILE__, __LINE__);
        _respond_error(403, 'Table non autorisée');
    }
    $row = db_row("SELECT * FROM `$table` WHERE `$col` = ? LIMIT 1", [$id]);
    if (!$row) {
        _log_error('NOT_FOUND', "Ressource introuvable: $table.$col=$id", __FILE__, __LINE__);
        http_response_code(404);
        _respond_error(404, 'Ressource inexistante.');
    }
    return $row;
}

//  6. RATE LIMITING (anti-brute force)

function rate_limit(string $key, int $max = 10, int $window = 60): void {
    $k = 'rl_' . $key . '_' . floor(time() / $window);
    $_SESSION[$k] = ($_SESSION[$k] ?? 0) + 1;
    if ($_SESSION[$k] > $max) {
        _log_error('RATE_LIMIT', "Limite atteinte: $key ($max/$window s)", __FILE__, __LINE__);
        http_response_code(429);
        _respond_error(429, 'Trop de tentatives. Ressayez dans ' . $window . ' secondes.');
    }
}

/**
 * Limite les tentatives de connexion (5 par minute par IP)
 */
function rate_limit_login(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    rate_limit('login_' . md5($ip), 5, 60);
}

//  7. PROTECTION PATH TRAVERSAL

/**
 * Scurise un nom de fichier (empche ../../etc/passwd etc.)
 */
function safe_filename(string $name): string {
    $name = basename($name);
    $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $name);
    return $name ?: 'fichier';
}

//  8. OUTPUT HELPERS

/**
 * Affiche une valeur HTML-chappe (raccourci)
 */
function e(mixed $val): void {
    echo htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Retourne une valeur HTML-chappe
 */
function h(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

//  9. URL BUILDER SCURIS

/**
 * Construit une URL scurise avec ID sign
 * Usage: secure_url('patients.php', 42, 'patient')
 * => patients.php?id=42&tok=a1b2c3d4e5f6g7h8
 */
function secure_url(string $page, int $id, string $context = 'default', array $extra = []): string {
    $params = array_merge(['id' => $id, 'tok' => url_sign($id, $context)], $extra);
    return APP_URL . '/' . $page . '?' . http_build_query($params);
}

//  10. VALIDATION FORMULAIRE

class Validator {
    private array $errors = [];
    private array $data   = [];

    public function required(string $key, string $label): self {
        $val = post_str($key);
        if ($val === '') $this->errors[$key] = "$label est obligatoire.";
        else $this->data[$key] = $val;
        return $this;
    }

    public function email(string $key, string $label): self {
        $val = post_email($key);
        if ($val === null) $this->errors[$key] = "$label doit tre un email valide.";
        else $this->data[$key] = $val;
        return $this;
    }

    public function username(string $key, string $label): self {
        $val = post_username($key);
        if ($val === null) $this->errors[$key] = "$label doit contenir 3  50 caractres (a-z, 0-9, _ ou .).";
        else $this->data[$key] = $val;
        return $this;
    }

    public function date(string $key, string $label): self {
        $val = post_str($key);
        if (!validate_date($val)) $this->errors[$key] = "$label doit tre une date valide (AAAA-MM-JJ).";
        else $this->data[$key] = $val;
        return $this;
    }

    public function min_length(string $key, int $min, string $label): self {
        $val = post_str($key);
        if (mb_strlen($val) < $min) $this->errors[$key] = "$label doit contenir au moins $min caractres.";
        else $this->data[$key] = $val;
        return $this;
    }

    public function whitelist(string $key, array $allowed, string $label): self {
        $val = post_str($key);
        if (!in_array($val, $allowed, true)) $this->errors[$key] = "$label contient une valeur non autorisée.";
        else $this->data[$key] = $val;
        return $this;
    }

    public function int_range(string $key, int $min, int $max, string $label): self {
        $val = post_int($key);
        if ($val < $min || $val > $max) $this->errors[$key] = "$label doit tre entre $min et $max.";
        else $this->data[$key] = $val;
        return $this;
    }

    public function passes(): bool { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function first_error(): string { return reset($this->errors) ?: ''; }
    public function data(): array { return $this->data; }
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
}
