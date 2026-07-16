<?php
/**
 * Rate limiting PharmaCare — stockage fichier (aucune dépendance externe).
 *
 * 4 niveaux :
 *  1. Login        — rateLimitRemaining/Fail/Reset (verrouillage progressif)
 *  2. Global       — throttle de toutes les requêtes par IP (anti-flood)
 *  3. Actions POS   — rateLimitConsume('pos.vente:uid', max, window)
 *  4. Export/print — throttle client-side (JS) — voir modules/*
 *
 * Toutes les clés sont préfixées et hachées (md5) dans RATE_LIMIT_DIR.
 */

define('RATE_LIMIT_DIR', __DIR__ . '/.rate_limit');
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900);     // 15 minutes
define('RATE_LIMIT_LOCKOUT', 900);    // 15 min de blocage login

// Limiter global (toutes requêtes)
define('RATE_LIMIT_GLOBAL_MAX', 120);    // requêtes max…
define('RATE_LIMIT_GLOBAL_WINDOW', 60);  // …par minute par IP

// Proxies de confiance pour X-Forwarded-For (en local XAMPP : loopback)
if (!defined('RATE_LIMIT_TRUSTED_PROXIES')) {
    define('RATE_LIMIT_TRUSTED_PROXIES', ['127.0.0.1', '::1']);
}

function _rateLimitInit(): void {
    if (!is_dir(RATE_LIMIT_DIR)) {
        mkdir(RATE_LIMIT_DIR, 0700, true);
        $htaccess = RATE_LIMIT_DIR . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }
}

/**
 * IP du client, avec support X-Forwarded-For / X-Real-IP UNIQUEMENT
 * si REMOTE_ADDR est un proxy de confiance (sinon on reste sur REMOTE_ADDR
 * pour éviter le spoofing par le client).
 */
function clientIp(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = RATE_LIMIT_TRUSTED_PROXIES;
    if (in_array($remote, $trusted, true)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $remote;
}

function _rateLimitPath(string $key): string {
    return RATE_LIMIT_DIR . '/' . md5($key) . '.json';
}

function _rateLimitReadKey(string $key): array {
    $path = _rateLimitPath($key);
    if (!file_exists($path)) return ['attempts' => [], 'locked_until' => 0];
    $data = json_decode(file_get_contents($path), true);
    if (!$data) return ['attempts' => [], 'locked_until' => 0];
    return $data;
}

function _rateLimitWriteKey(string $key, array $data): void {
    _rateLimitInit();
    file_put_contents(_rateLimitPath($key), json_encode($data), LOCK_EX);
}

// ── Compatibilité : l'ancien _rateLimitRead/_rateLimitWrite prenait une IP ──
function _rateLimitRead(string $ip): array   { return _rateLimitReadKey('login:' . $ip); }
function _rateLimitWrite(string $ip, array $data): void { _rateLimitWriteKey('login:' . $ip, $data); }

/**
 * Vérifie (sans consommer) une clé sur une fenêtre glissante.
 * Retourne ['allowed','count','remaining','retry'].
 */
function rateLimitCheck(string $key, int $max, int $window): array {
    $data = _rateLimitReadKey($key);
    $cutoff = time() - $window;
    $attempts = array_values(array_filter($data['attempts'] ?? [], fn($t) => $t > $cutoff));
    $count = count($attempts);
    $allowed = $count < $max;
    return [
        'allowed'   => $allowed,
        'count'     => $count,
        'remaining' => max(0, $max - $count),
        'retry'     => $count >= $max ? ($attempts[0] + $window - time()) : 0,
    ];
}

/**
 * Consomme une tentative sur la clé. Si la limite est atteinte, n'écrit pas
 * de nouveau et retourne allowed=false. Retourne les mêmes infos que rateLimitCheck.
 */
function rateLimitConsume(string $key, int $max, int $window): array {
    $info = rateLimitCheck($key, $max, $window);
    if (!$info['allowed']) return $info;
    $data = _rateLimitReadKey($key);
    $cutoff = time() - $window;
    $data['attempts'] = array_values(array_filter($data['attempts'] ?? [], fn($t) => $t > $cutoff));
    $data['attempts'][] = time();
    _rateLimitWriteKey($key, $data);
    $info['count'] = count($data['attempts']);
    $info['remaining'] = max(0, $max - $info['count']);
    return $info;
}

// ── API login historique (verrouillage progressif) ────────────
function rateLimitRemaining(string $ip): int {
    $data = _rateLimitRead($ip);
    if ($data['locked_until'] > time()) {
        return $data['locked_until'] - time();
    }
    if ($data['locked_until'] > 0 && $data['locked_until'] <= time()) {
        $data['locked_until'] = 0;
        $data['attempts'] = [];
        _rateLimitWrite($ip, $data);
    }
    return 0;
}

function rateLimitFail(string $ip): bool {
    $data = _rateLimitRead($ip);
    $data['attempts'][] = time();
    if (count($data['attempts']) >= RATE_LIMIT_MAX_ATTEMPTS) {
        $data['locked_until'] = time() + RATE_LIMIT_LOCKOUT;
        $data['attempts'] = [];
        _rateLimitWrite($ip, $data);
        return true;
    }
    _rateLimitWrite($ip, $data);
    return false;
}

function rateLimitReset(string $ip): void {
    $path = _rateLimitPath('login:' . $ip);
    if (file_exists($path)) unlink($path);
}

function rateLimitCleanup(): void {
    _rateLimitInit();
    $files = glob(RATE_LIMIT_DIR . '/*.json');
    if (!$files) return;
    $expiry = time() - 3600;
    foreach ($files as $f) {
        if (filemtime($f) < $expiry) unlink($f);
    }
}

// Nettoyage ~10% des appels
if (mt_rand(1, 10) === 1) {
    rateLimitCleanup();
}

/**
 * Limiter global : exécuté une seule fois par requête HTTP.
 * On l'amorce via _rateLimitGlobalGuard() à l'inclusion du fichier.
 */
function _rateLimitGlobalGuard(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    // On ne throttle pas les assets statiques ni les réponses déjà envoyées
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|map)$#i', $uri)) {
        return;
    }

    $key = 'global:' . clientIp();
    $info = rateLimitConsume($key, RATE_LIMIT_GLOBAL_MAX, RATE_LIMIT_GLOBAL_WINDOW);
    if (!$info['allowed']) {
        $retry = max(1, (int)$info['retry']);
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: ' . $retry);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'   => 'Trop de requêtes',
            'message' => 'Limite de ' . RATE_LIMIT_GLOBAL_MAX
                       . ' requêtes / ' . RATE_LIMIT_GLOBAL_WINDOW . 's atteinte.',
            'retry_after' => $retry,
        ]);
        exit;
    }
}

// Amorçage automatique du limiter global dès l'inclusion de ce fichier
// (lui-même inclus via includes/auth.php sur tous les points d'entrée).
_rateLimitGlobalGuard();