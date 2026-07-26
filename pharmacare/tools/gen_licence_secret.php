<?php
declare(strict_types=1);
/**
 * Générateur du secret HMAC pour les codes courts — USAGE UNIQUE, côté développeur.
 *
 * Produit :
 *   - tools/licence_secret.php  : secret (hex 64 chars), gitignoré, JAMAIS livré au client.
 *   - config/licence.php mis à jour : const LICENCE_HMAC_SECRET = '<hex>'.
 *
 * Le MÊME secret doit être côté dev (génération) et côté app (vérification).
 *
 * Usage :  php tools/gen_licence_secret.php [--force]
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }

$toolsDir   = __DIR__;
$secretFile = $toolsDir . '/licence_secret.php';
$configFile = dirname($toolsDir) . '/config/licence.php';
$force = in_array('--force', $argv, true);

// Garde : refuser si un secret est déjà injecté (sauf --force)
if (file_exists($configFile) &&
    preg_match("/const LICENCE_HMAC_SECRET = '([^']*)';/", (string)file_get_contents($configFile), $m) &&
    $m[1] !== '' && !$force) {
    fwrite(STDERR, "Un secret HMAC est déjà injecté dans config/licence.php.\n");
    fwrite(STDERR, "Le changer invaliderait TOUS les codes courts déjà émis.\n");
    fwrite(STDERR, "Pour forcer : php tools/gen_licence_secret.php --force\n");
    exit(1);
}

$secret = bin2hex(random_bytes(32)); // 64 hex chars

// Écriture du fichier dev (gitignoré)
$php  = "<?php\n// SECRET HMAC pour codes courts de licence — NE JAMAIS LIVRER AU CLIENT NI COMMITTER.\n";
$php .= "return " . var_export($secret, true) . ";\n";
file_put_contents($secretFile, $php);
@chmod($secretFile, 0600);

// Injection dans config/licence.php
$cfg = file_get_contents($configFile);
$count = 0;
$new = preg_replace(
    "/const LICENCE_HMAC_SECRET = '.*?';/s",
    "const LICENCE_HMAC_SECRET = '" . $secret . "';",
    $cfg, 1, $count
);
if ($count !== 1) {
    fwrite(STDERR, "Impossible de mettre à jour LICENCE_HMAC_SECRET dans $configFile.\n");
    exit(1);
}
file_put_contents($configFile, $new);

echo "Secret HMAC généré et injecté.\n\n";
echo "  Secret dev   : $secretFile (gitignoré — À GARDER SECRÈT)\n";
echo "  Secret app   : injecté dans $configFile (const LICENCE_HMAC_SECRET)\n\n";
echo "Vous pouvez maintenant générer des codes courts (LLLL-NNNNNN-CCCCCCCC) via :\n";
echo "  - l'interface graphique (tools/licence_gui.bat)\n";
echo "  - php tools/gen_licence.php --instance=<ID> --pack=<N> --short\n";