<?php
/**
 * Rate limiting pour les tentatives de connexion.
 * Stockage fichier (pas de dépendance externe).
 */

define('RATE_LIMIT_DIR', __DIR__ . '/.rate_limit');
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900);     // 15 minutes en secondes
define('RATE_LIMIT_LOCKOUT', 900);    // 15 minutes de blocage

function _rateLimitInit(): void {
    if (!is_dir(RATE_LIMIT_DIR)) {
        mkdir(RATE_LIMIT_DIR, 0700, true);
        $htaccess = RATE_LIMIT_DIR . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }
}

function _rateLimitPath(string $ip): string {
    return RATE_LIMIT_DIR . '/' . md5($ip) . '.json';
}

function _rateLimitRead(string $ip): array {
    $path = _rateLimitPath($ip);
    if (!file_exists($path)) return ['attempts' => [], 'locked_until' => 0];
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!$data) return ['attempts' => [], 'locked_until' => 0];
    // Nettoyage des tentatives expirées
    $cutoff = time() - RATE_LIMIT_WINDOW;
    $data['attempts'] = array_values(array_filter($data['attempts'], fn($t) => $t > $cutoff));
    return $data;
}

function _rateLimitWrite(string $ip, array $data): void {
    _rateLimitInit();
    file_put_contents(_rateLimitPath($ip), json_encode($data), LOCK_EX);
}

/**
 * Vérifie si l'IP est bloquée. Retourne les secondes restantes ou 0.
 */
function rateLimitRemaining(string $ip): int {
    $data = _rateLimitRead($ip);
    if ($data['locked_until'] > time()) {
        return $data['locked_until'] - time();
    }
    // Si le lockout est passé, on le reset
    if ($data['locked_until'] > 0 && $data['locked_until'] <= time()) {
        $data['locked_until'] = 0;
        $data['attempts'] = [];
        _rateLimitWrite($ip, $data);
    }
    return 0;
}

/**
 * Enregistre une tentative échouée. Retourne true si bloqué après cet essai.
 */
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

/**
 * Réinitialise le compteur après une connexion réussie.
 */
function rateLimitReset(string $ip): void {
    $path = _rateLimitPath($ip);
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Nettoyage périodique des fichiers expirés (> 1h).
 */
function rateLimitCleanup(): void {
    _rateLimitInit();
    $files = glob(RATE_LIMIT_DIR . '/*.json');
    if (!$files) return;
    $expiry = time() - 3600;
    foreach ($files as $f) {
        if (filemtime($f) < $expiry) {
            unlink($f);
        }
    }
}

// Nettoyage automatique ~10% des appels
if (mt_rand(1, 10) === 1) {
    rateLimitCleanup();
}
