<?php
declare(strict_types=1);
/**
 * Générateur du secret HMAC pour les codes courts — USAGE UNIQUE, côté développeur.
 *
 * Produit DEUX fichiers avec le MÊME secret :
 *   - tools/licence_secret.php  : secret dev (émission des codes) — gitignoré.
 *   - config/licence_secret.php : secret app (vérification) — gitignoré hors
 *     dépôt, mais LIVRÉ dans les bundles deploy (nécessaire à l'app cliente).
 *
 * Historique : avant le 2026-08-31 le secret était injecté en dur dans
 * config/licence.php (const LICENCE_HMAC_SECRET). Ce fichier étant suivi par
 * git sur un dépôt PUBLIC, le secret a fuité et a été roté : la const laisse
 * place à licence_hmac_secret() qui lit config/licence_secret.php.
 *
 * Usage :  php tools/gen_licence_secret.php [--force]
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }

$toolsDir   = __DIR__;
$rootDir    = dirname($toolsDir);
$devSecret  = $toolsDir . '/licence_secret.php';
$appSecret  = $rootDir . '/config/licence_secret.php';
$force = in_array('--force', $argv, true);

// Garde : refuser si un secret existe déjà (sauf --force) — le changer
// invalide TOUS les codes courts déjà émis.
if (!$force && (file_exists($devSecret) || file_exists($appSecret))) {
    fwrite(STDERR, "Un secret HMAC existe déjà (tools/licence_secret.php et/ou config/licence_secret.php).\n");
    fwrite(STDERR, "Le changer invaliderait TOUS les codes courts déjà émis.\n");
    fwrite(STDERR, "Pour forcer la rotation : php tools/gen_licence_secret.php --force\n");
    exit(1);
}

$secret = bin2hex(random_bytes(32)); // 64 hex chars

$php  = "<?php\n// SECRET HMAC pour codes courts de licence — NE JAMAIS LIVRER AU CLIENT NI COMMITTER.\n";
$php .= "return " . var_export($secret, true) . ";\n";
file_put_contents($devSecret, $php);
@chmod($devSecret, 0600);

$phpApp  = "<?php\n// SECRET HMAC pour codes courts de licence — livré dans les bundles deploy,\n";
$phpApp .= "// ABSENT du dépôt git (gitigné). Ne jamais committer.\n";
$phpApp .= "return " . var_export($secret, true) . ";\n";
file_put_contents($appSecret, $phpApp);
@chmod($appSecret, 0600);

echo "Secret HMAC généré (rotation) et écrit dans les deux emplacements :\n\n";
echo "  Secret dev   : $devSecret (gitigné — émission des codes)\n";
echo "  Secret app   : $appSecret (gitigné — vérification app, LIVRÉ dans les bundles)\n\n";
echo "⚠ Les codes courts émis avec l'ANCIEN secret ne sont plus valides :\n";
echo "  ré-émettez un code pour chaque instance cliente concernée.\n\n";
echo "Rappel déploiement : après rotation, reconstruire les bundles\n";
echo "(tools/build_deploy_bundles.sh) et mettre à jour le manifeste d'intégrité.\n";
echo "Codes courts : php tools/gen_licence.php --instance=<ID> --pack=<N> --short\n";