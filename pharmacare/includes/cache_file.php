<?php
declare(strict_types=1);
/**
 * Petit cache disque (fichiers) pour agrégats coûteux.
 * Zéro dépendance, fonctionne en dev (XAMPP) comme en prod (LAMP).
 * Fichiers dans pharmacare/cache/ (gitignore, créé à la volée).
 *
 * Usage :
 *   $v = cache_get('cle', 60, $found);
 *   if (!$found) { $v = <calcul couteux>; cache_set('cle', $v); }
 *   cache_delete('cle'); // invalidation evenementielle apres ecriture
 */

define('CACHE_FILE_DIR', __DIR__ . '/../cache');

/** Sérialise valeur + date d'expiration dans un fichier .cache. */
function cache_set(string $key, mixed $value, int $ttl = 60): void {
    $path = cache_path($key);
    if ($path === '') return;
    if (!is_dir(CACHE_FILE_DIR)) {
        @mkdir(CACHE_FILE_DIR, 0775, true);
    }
    $payload = ['exp' => time() + max(1, $ttl), 'val' => $value];
    @file_put_contents($path, serialize($payload), LOCK_EX);
}

/** Lit la valeur si présente et non expirée. $found passe à true si HIT. */
function cache_get(string $key, int $ttl, ?bool &$found = null): mixed {
    $found = false;
    $path = cache_path($key);
    if ($path === '' || !is_file($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return null;
    $payload = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($payload) || !isset($payload['exp'], $payload['val'])) {
        @unlink($path);
        return null;
    }
    if ((int)$payload['exp'] < time()) {
        @unlink($path);
        return null;
    }
    $found = true;
    return $payload['val'];
}

/** Invalide une clé précise. */
function cache_delete(string $key): void {
    $path = cache_path($key);
    if ($path !== '' && is_file($path)) @unlink($path);
}

/** Invalide toutes les clés débutant par $prefix (ex: 'dash.'). */
function cache_delete_prefix(string $prefix): void {
    if (!is_dir(CACHE_FILE_DIR)) return;
    $pf = str_replace('_', '\_', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix));
    foreach (glob(CACHE_FILE_DIR . '/' . $pf . '*.cache') ?: [] as $f) {
        @unlink($f);
    }
}

function cache_path(string $key): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    if ($safe === null || $safe === '' || $safe === '_' || str_contains($safe, '..')) return '';
    return CACHE_FILE_DIR . '/' . $safe . '.cache';
}