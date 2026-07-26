<?php
declare(strict_types=1);
/**
 * Générateur de keypair de licence — USAGE UNIQUE, côté développeur.
 *
 * Produit :
 *   - tools/licence_privatekey.php  : clé privée RSA-2048 (base64 du PEM),
 *                                     À GARDER SECRÈTE, gitignorée, jamais livrée au client.
 *   - config/licence.php mis à jour  : constante LICENCE_PUBKEY = base64(PEM publique).
 *
 * Usage :  php tools/gen_licence_keypair.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

// ── Auto-configuration openssl.cnf (XAMPP Windows : souvent absent de l'env) ──
licence_ensure_openssl_conf();

$toolsDir   = __DIR__;
$configFile = dirname($toolsDir) . '/config/licence.php';

// ── Garde : refuser de re-générer un keypair si une clé publique est déjà en
// place (sauf --force). Évite qu'un client qui récupère cet outil ne re-crée
// sa propre clé pour signer lui-même des codes. (Levier de dissuasion, pas
// une garantie absolue en on-premise.)
$force = in_array('--force', $argv, true);
if (file_exists($configFile) &&
    preg_match("/const LICENCE_PUBKEY = '([^']*)';/", (string)file_get_contents($configFile), $m) &&
    $m[1] !== '' && !$force) {
    fwrite(STDERR, "Une clé publique est déjà injectée dans config/licence.php.\n");
    fwrite(STDERR, "Re-générer écraserait la clé et invaliderait TOUS les codes déjà émis.\n");
    fwrite(STDERR, "Pour forcer : php tools/gen_licence_keypair.php --force\n");
    exit(1);
}
$privFile   = $toolsDir . '/licence_privatekey.php';

if (!file_exists($configFile)) {
    fwrite(STDERR, "config/licence.php introuvable : $configFile\n");
    exit(1);
}

// ── Génération RSA-2048 ─────────────────────────────────────
$res = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'config'           => licence_openssl_cnf(),
]);
if ($res === false) {
    fwrite(STDERR, "openssl_pkey_new a échoué : " . openssl_error_string() . "\n");
    exit(1);
}
openssl_pkey_export($res, $privPem, null, ['config' => licence_openssl_cnf()]);
if ($privPem === null || $privPem === '') {
    fwrite(STDERR, "openssl_pkey_export a échoué : " . openssl_error_string() . "\n");
    exit(1);
}
$details = openssl_pkey_get_details($res);
$pubPem  = $details['key'];

// ── Écriture de la clé privée (base64 du PEM, ligne unique) ──
$privPhp  = "<?php\n";
$privPhp .= "// CLÉ PRIVÉE DE LICENCE — NE JAMAIS LIVRER AU CLIENT NI COMMITTER.\n";
$privPhp .= "// Fichier gitignoré. Rechargez-le via require puis base64_decode().\n";
$privPhp .= "return " . var_export(base64_encode($privPem), true) . ";\n";
file_put_contents($privFile, $privPhp);
@chmod($privFile, 0600);

// ── Injection de la clé publique dans config/licence.php ──
$pubB64 = base64_encode($pubPem);
$cfg    = file_get_contents($configFile);
$count  = 0;
$new    = preg_replace(
    "/const LICENCE_PUBKEY = '.*?';/s",
    "const LICENCE_PUBKEY = '" . $pubB64 . "';",
    $cfg, 1, $count
);
if ($count !== 1) {
    fwrite(STDERR, "Impossible de mettre à jour LICENCE_PUBKEY dans $configFile (ligne introuvable).\n");
    exit(1);
}
file_put_contents($configFile, $new);

echo "Keypair de licence généré avec succès.\n\n";
echo "  Clé privée : $privFile\n";
echo "               (gitignorée — À GARDER SECRÈTE, ne jamais livrer ni committer)\n";
echo "  Clé publique : injectée dans $configFile  (const LICENCE_PUBKEY)\n\n";
echo "Clé publique (PEM) :\n" . $pubPem . "\n";
echo "Vous pouvez maintenant générer des codes : php tools/gen_licence.php --instance=<ID> --pack=<N>\n";

/**
 * Pointe OPENSSL_CONF vers un openssl.cnf existant (XAMPP Windows ne le pose pas).
 */
function licence_ensure_openssl_conf(): void {
    $cnf = licence_openssl_cnf();
    if ($cnf !== '' && !getenv('OPENSSL_CONF')) {
        putenv('OPENSSL_CONF=' . $cnf);
        $_ENV['OPENSSL_CONF'] = $cnf;
    }
}

function licence_openssl_cnf(): string {
    $candidates = [
        getenv('OPENSSL_CONF') ?: '',
        dirname(__DIR__, 2) . '/xampp/php/extras/openssl/openssl.cnf', // C:\xampp\php\extras\...
        dirname(__DIR__, 2) . '/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/openssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
    ];
    foreach ($candidates as $c) {
        if ($c !== '' && is_file($c)) return str_replace('\\', '/', $c);
    }
    return '';
}