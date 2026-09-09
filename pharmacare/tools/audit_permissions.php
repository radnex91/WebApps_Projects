<?php
declare(strict_types=1);
/**
 * PharmaCare — Audit des rôles & permissions.
 *
 * Compare les permissions RÉELLEMENT vérifiées dans le code
 * (requirePermission / hasPermission) avec celles de la table `permissions`,
 * puis mesure la couverture de chaque rôle. Détecte ainsi les permissions
 * « fantômes » : utilisées par les modules mais non présentes en base,
 * donc invisibles dans l'éditeur de rôles (modules/roles.php) et
 * impossibles à attribuer à un nouveau rôle.
 *
 * Utilisation :
 *   php tools/audit_permissions.php [chemin_pharmacare]
 *
 * Code de sortie : 0 = aucun écart, 1 = permissions manquantes en base.
 * Idempotent, lecture seule (aucune écriture en base).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script ne doit être exécuté qu'en ligne de commande (php CLI).\n");
    exit(1);
}

$live = $argv[1] ?? getcwd();
$live = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($live, '/\\'));
$envFile = $live . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.php';
if (!is_file($envFile)) {
    fwrite(STDERR, "config/env.php introuvable sous : $live\n");
    exit(1);
}
require $envFile; // définit DB_* (env.prod.php pris en compte si présent)

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// ── 1) Permissions vérifiées dans le code (scan statique) ───────────────────
$used = [];
$scan = function (string $dir) use (&$used) {
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') continue;
        $src = file_get_contents($file->getPathname());
        if (preg_match_all("/(?:requirePermission|hasPermission|canAccess)\(\s*['\"]([a-z][a-z0-9_.]+)['\"]\s*[),]/", $src, $m)) {
            foreach ($m[1] as $code) $used[$code][] = str_replace($GLOBALS['live'] . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
};
$scan($live . DIRECTORY_SEPARATOR . 'modules');
$scan($live . DIRECTORY_SEPARATOR . 'includes');
$scan($live . DIRECTORY_SEPARATOR . 'config');
ksort($used);

// ── 2) Permissions en base ──────────────────────────────────────────────────
$dbPerms = $pdo->query('SELECT id, code, libelle, module FROM permissions ORDER BY id')->fetchAll();
$dbCodes = array_column($dbPerms, 'code');
$missing = array_diff(array_keys($used), $dbCodes);
$unused  = array_diff($dbCodes, array_keys($used));

echo "=== PharmaCare — Audit rôles & permissions ===\n";
printf("Permissions vérifiées dans le code : %d\n", count($used));
printf("Permissions en base (`permissions`) : %d\n\n", count($dbCodes));

// ── 3) Permissions fantômes (utilisées mais non attribuables) ───────────────
if ($missing) {
    echo "⚠ PERMISSIONS FANTÔMES (utilisées dans le code, ABSENTES de la table) :\n";
    echo "  → invisibles dans l'éditeur de rôles, impossibles à accorder.\n";
    foreach ($missing as $code) {
        $files = implode(', ', array_slice($used[$code], 0, 3));
        echo sprintf("  - %-28s (utilisée dans %s)\n", $code, $files);
    }
    echo "\n";
} else {
    echo "✔ Aucune permission fantôme : tout ce que le code exige est attribuable.\n\n";
}

if ($unused) {
    echo "i  Permissions en base jamais vérifiées dans le code (mortes ou vérifiées dynamiquement) :\n";
    foreach ($unused as $code) echo "  - $code\n";
    echo "\n";
}

// ── 4) Couverture par rôle ──────────────────────────────────────────────────
$roles = $pdo->query("SELECT id, code, libelle FROM roles ORDER BY id")->fetchAll();
$grants = [];
foreach ($pdo->query('SELECT role_id, permission_id FROM role_permissions') as $rp) {
    $grants[(int)$rp['role_id']][] = (int)$rp['permission_id'];
}
$pidOf = [];
foreach ($dbPerms as $p) $pidOf[$p['code']] = (int)$p['id'];

echo "=== Couverture des permissions du code, rôle par rôle ===\n";
$trous = [];
foreach ($roles as $r) {
    $rid = (int)$r['id'];
    if ($r['code'] === 'admin') { echo sprintf("  [%-13s] bypass (admin a tout par conception)\n", $r['code']); continue; }
    $have = $grants[$rid] ?? [];
    $lacks = [];
    foreach (array_keys($used) as $code) {
        if (in_array($code, $missing, true)) { $lacks[] = $code . ' (non attribuable)'; continue; }
        $pid = $pidOf[$code] ?? 0;
        if (!in_array($pid, $have, true)) $lacks[] = $code;
    }
    if ($lacks) $trous[$r['code']] = $lacks;
    echo sprintf("  [%-13s] %s : %s\n", $r['code'], $r['libelle'],
        $lacks ? count($lacks) . ' manquante(s)' : 'couverture complète');
}
if ($trous) {
    echo "\nDétail des manques :\n";
    foreach ($trous as $role => $lacks) {
        echo "  [$role] " . implode(', ', $lacks) . "\n";
    }
}

exit($missing ? 1 : 0);