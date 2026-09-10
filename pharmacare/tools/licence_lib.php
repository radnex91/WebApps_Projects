<?php
declare(strict_types=1);
/**
 * Lib partagée pour les outils de licence (CLI + GUI).
 * Côté développeur uniquement — ne JAMAIS livrer au client.
 *
 * Fournit : signature d'un enregistrement de licence + gestion du ledger.
 */

// ── openssl.cnf (XAMPP Windows ne le pose pas toujours dans l'env) ──────────
function licence_lib_openssl_cnf(): string {
    $candidates = [
        getenv('OPENSSL_CONF') ?: '',
        __DIR__ . '/php/extras/openssl/openssl.cnf',             // bundle portable : runtime dans tools/php
        dirname(__DIR__, 1) . '/php/extras/openssl/openssl.cnf', // C:\xampp\php\extras\...
        dirname(__DIR__, 1) . '/php/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/openssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
    ];
    foreach ($candidates as $c) {
        if ($c !== '' && is_file($c)) return str_replace('\\', '/', $c);
    }
    return '';
}
function licence_lib_ensure_openssl(): void {
    $cnf = licence_lib_openssl_cnf();
    if ($cnf !== '' && !getenv('OPENSSL_CONF')) {
        putenv('OPENSSL_CONF=' . $cnf);
        $_ENV['OPENSSL_CONF'] = $cnf;
    }
}

// ── base64url ──────────────────────────────────────────────────────────────
function licence_lib_b64u(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

// ── Clé privée (tools/licence_privatekey.php, gitignorée) ──────────────────
function licence_lib_privkey_path(): string {
    return __DIR__ . '/licence_privatekey.php';
}

/** Vrai si config/licence.php embarque déjà une clé publique (LICENCE_PUBKEY non vide). */
function licence_lib_pubkey_configured(): bool {
    $cfg = dirname(__DIR__, 1) . '/config/licence.php';
    if (!is_file($cfg)) return false;
    return (bool)preg_match("/const LICENCE_PUBKEY = '([^']*)';/", (string)file_get_contents($cfg), $m) && $m[1] !== '';
}

/**
 * Message contextuel quand la clé privée manque (affiché par les GUI).
 * Piège : si une clé publique est déjà en place, régénérer la paire
 * (--force de gen_licence_keypair.php) écraserait la clé et invaliderait
 * TOUS les codes déjà émis — il faut RESTAURER le fichier, pas le recréer.
 */
function licence_lib_privkey_hint(): string {
    $f = licence_lib_privkey_path();
    if (licence_lib_pubkey_configured()) {
        return "Clé privée RSA absente ($f) — NE PAS lancer gen_licence_keypair.php : "
            . "une clé publique est déjà en place, régénérer la paire invaliderait TOUS les codes déjà émis. "
            . "Restaurez licence_privatekey.php depuis votre sauvegarde "
            . "(bundle dist/PharmaCare-Licence-Manager, PC de secours).";
    }
    return "Clé privée RSA absente ($f) — lancez : php tools/gen_licence_keypair.php "
        . "(aucune clé publique en place : création normale de la paire).";
}

function licence_lib_privkey_pem(): string {
    $f = licence_lib_privkey_path();
    if (!file_exists($f)) {
        throw new RuntimeException(licence_lib_privkey_hint());
    }
    $b64 = require $f;
    $pem = base64_decode((string)$b64, true);
    if ($pem === false || $pem === '') {
        throw new RuntimeException('Clé privée illisible dans ' . $f);
    }
    return $pem;
}

// ── Palier gratuit (lu depuis config/licence.php, défaut 10000) ────────────
function licence_lib_free_cap(): int {
    $cfg = dirname(__DIR__, 1) . '/config/licence.php';
    if (is_file($cfg) && preg_match("/const LICENCE_FREE_CAP = (\d+);/", (string)file_get_contents($cfg), $m)) {
        return (int)$m[1];
    }
    return 10000;
}

/**
 * Réécrit le palier gratuit (const LICENCE_FREE_CAP) dans config/licence.php.
 * C'est la valeur de départ de tout nouveau client ET le repli en cas de
 * licence absente/invalide. ⚠ Changer cette valeur modifie le repli de TOUS
 * les clients déjà déployés (qui n'ont pas de licence active) au prochain
 * déploiement — ne changez pas la valeur un client déjà en production sans
 * réfléchir. Les licences déjà actives (codes longs/courts appliqués) gardent
 * leur plafond ; seule la valeur de repli change.
 */
function licence_lib_set_free_cap(int $cap): void {
    if ($cap <= 0) throw new InvalidArgumentException('Le palier gratuit doit être > 0.');
    $cfg = dirname(__DIR__, 1) . '/config/licence.php';
    $src = (string)file_get_contents($cfg);
    $count = 0;
    $new = preg_replace(
        "/const LICENCE_FREE_CAP = \d+;/",
        "const LICENCE_FREE_CAP = " . $cap . ";",
        $src, 1, $count
    );
    if ($count !== 1) throw new RuntimeException("LICENCE_FREE_CAP introuvable dans $cfg.");
    file_put_contents($cfg, $new);
}

// ── Intégrité (manifeste signé des fichiers critiques) ─────────────────────
//
// Liste des fichiers couverts (relative à pharmacare/). La liste voyage dans le
// manifeste signé : le client ne peut pas la réduire sans re-signer.
function licence_lib_integrity_files(): array {
    return [
        'config/licence.php',
        'config/settings.php',
        'modules/vente.php',
        'modules/licence.php',
        'includes/audit.php',
        'includes/auth.php',
    ];
}

/**
 * Régénère config/licence_integrity.php (manifeste signé RSA des fichiers
 * critiques). À appeler après TOUTE modification d'un fichier couvert — en
 * particulier après licence_lib_set_free_cap(), qui édite config/licence.php.
 *
 * @return array{count:int, files:array<string,string>}
 */
function licence_lib_generate_integrity(): array {
    licence_lib_ensure_openssl();
    $root = dirname(__DIR__, 1); // pharmacare/
    $files = [];
    foreach (licence_lib_integrity_files() as $rel) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            throw new RuntimeException("Fichier couvert manquant : $rel");
        }
        $files[$rel] = hash_file('sha256', $path);
    }
    $payload = json_encode(['v' => 1, 'files' => $files], JSON_UNESCAPED_SLASHES);
    $sig = '';
    if (!openssl_sign($payload, $sig, licence_lib_privkey_pem(), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Signature manifeste échouée : ' . (openssl_error_string() ?: 'erreur inconnue'));
    }
    $manifest = licence_lib_b64u($payload) . '.' . licence_lib_b64u($sig);

    $outFile = $root . '/config/licence_integrity.php';
    $php  = "<?php\n";
    $php .= "// Manifeste d'intégrité des fichiers licence — signé RSA par le développeur.\n";
    $php .= "// Généré par tools/gen_licence_integrity.php (ou via la GUI). Ne pas éditer à la main.\n";
    $php .= "// Régénérer après toute modification d'un fichier couvert.\n";
    $php .= "return " . var_export($manifest, true) . ";\n";
    file_put_contents($outFile, $php);

    return ['count' => count($files), 'files' => $files];
}

// ── Signature : produit le code d'activation (payload.sig) ─────────────────
/**
 * @return string le code d'activation : base64url(payload) . "." . base64url(sig)
 */
function licence_lib_sign(string $instance, int $cap, int $counter, int $expiry): string {
    licence_lib_ensure_openssl();
    $payload = json_encode(['i' => $instance, 'c' => $cap, 'n' => $counter, 'e' => $expiry], JSON_UNESCAPED_SLASHES);
    $sig = '';
    if (!openssl_sign($payload, $sig, licence_lib_privkey_pem(), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Signature échouée : ' . (openssl_error_string() ?: 'erreur inconnue'));
    }
    return licence_lib_b64u($payload) . '.' . licence_lib_b64u($sig);
}

// ── Ledger (tools/licence_ledger.json, gitignoré) ─────────────────────────
function licence_lib_ledger_path(): string {
    return __DIR__ . '/licence_ledger.json';
}
function licence_lib_ledger_load(): array {
    $f = licence_lib_ledger_path();
    if (!file_exists($f)) return [];
    $a = json_decode((string)file_get_contents($f), true);
    return is_array($a) ? $a : [];
}
function licence_lib_ledger_save(array $ledger): void {
    file_put_contents(licence_lib_ledger_path(), json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Émet un code pour une instance. Met à jour le ledger.
 *
 * @param string $instance
 * @param string $mode     'pack' (cap += value) ou 'set' (cap = value)
 * @param int    $value
 * @param int    $expiry   0 = sans expiration
 * @return array{code:string, cap:int, counter:int, old_cap:int}
 */
function licence_lib_emit(string $instance, string $mode, int $value, int $expiry = 0): array {
    $instance = trim($instance);
    if ($instance === '') throw new InvalidArgumentException('Instance vide.');
    if ($value <= 0) throw new InvalidArgumentException('La valeur doit être > 0.');

    $ledger = licence_lib_ledger_load();
    $freeCap = licence_lib_free_cap();
    $cur = $ledger[$instance] ?? ['cap' => $freeCap, 'counter' => 0];
    $oldCap = (int)$cur['cap'];

    if ($mode === 'set') {
        $newCap = $value;
    } else { // pack
        $newCap = $oldCap + $value;
    }
    $newCounter = (int)$cur['counter'] + 1;

    $code = licence_lib_sign($instance, $newCap, $newCounter, $expiry);

    $ledger[$instance] = ['cap' => $newCap, 'counter' => $newCounter, 'expiry' => $expiry];
    licence_lib_ledger_save($ledger);

    return ['code' => $code, 'cap' => $newCap, 'counter' => $newCounter, 'old_cap' => $oldCap];
}

// ── Codes courts (HMAC, SMS-friendly) ──────────────────────────────────────
//
// Format : LLLL-NNNNNN-CCCCCCCC
//   LLLL     = 4 lettres cosmétiques (préfixe aléatoire, non vérifié)
//   NNNNNN   = plafond absolu (6+ digits)
//   CCCCCCCC = jeton de contrôle = 8 symboles dérivés de HMAC-SHA256(secret, "instance:cap")
// Le secret est partagé entre le dev (tools/licence_secret.php) et l'app
// (const LICENCE_HMAC_SECRET). ⚠ Sécurité réduite : le secret est embarqué
// côté client, donc lisible dans le source. Voir docs/LICENCE.md.

function licence_lib_secret_path(): string {
    return __DIR__ . '/licence_secret.php';
}

/** Lit le secret HMAC dev (tools/licence_secret.php). */
function licence_lib_hmac_secret(): string {
    $f = licence_lib_secret_path();
    if (!file_exists($f)) {
        throw new RuntimeException(licence_lib_secret_hint());
    }
    $s = require $f;
    if (!is_string($s) || $s === '') {
        throw new RuntimeException('Secret HMAC illisible dans ' . $f);
    }
    return $s;
}

/**
 * Message contextuel quand le secret HMAC dev manque. Même piège que la clé
 * privée : si l'app embarque déjà un secret (config/licence_secret.php), le
 * régénérer le désynchroniserait de l'app et invaliderait tous les codes
 * courts déjà appliqués chez les clients (repli au palier gratuit).
 */
function licence_lib_secret_hint(): string {
    $f = licence_lib_secret_path();
    if (is_file(dirname(__DIR__, 1) . '/config/licence_secret.php')) {
        return "Secret HMAC absent ($f) — NE PAS lancer gen_licence_secret.php : "
            . "l'app embarque déjà un secret (config/licence_secret.php), le régénérer invaliderait "
            . "tous les codes courts déjà appliqués. Restaurez licence_secret.php depuis votre "
            . "sauvegarde (il doit rester IDENTIQUE à config/licence_secret.php).";
    }
    return "Secret HMAC absent ($f) — lancez : php tools/gen_licence_secret.php "
        . "(crée la paire dev + app d'un seul coup).";
}

/** Alphabet 32 symboles sans ambigüité (pas de I, O, 0, 1). */
function licence_lib_hmac_alphabet(): string {
    return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 24 lettres + 8 chiffres = 32
}

/**
 * 8 symboles de contrôle = HMAC-SHA256(secret, "instance:cap") → 8 octets mappés.
 * DOIT rester identique à licence_hmac8() côté app (config/licence.php).
 */
function licence_lib_hmac8(string $instance, int $cap, string $secret): string {
    $hmac = hash_hmac('sha256', $instance . ':' . $cap, $secret, true);
    $alpha = licence_lib_hmac_alphabet();
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $alpha[ord($hmac[$i]) % 32];
    }
    return $out;
}

/** Construit le code court LLLL-NNNNNN-CCCCCCCC pour (instance, cap). */
function licence_lib_short_code(string $instance, int $cap): string {
    if ($cap <= 0) throw new InvalidArgumentException('Cap doit être > 0.');
    $secret = licence_lib_hmac_secret();
    $prefix = strtoupper(bin2hex(random_bytes(2)));        // 4 hex → chiffres+lettres A-F0-9
    $prefix = str_replace(['0', '1'], ['A', 'B'], $prefix); // éviter 0/1 (ambigus)
    $check = licence_lib_hmac8($instance, $cap, $secret);
    return $prefix . '-' . $cap . '-' . $check;
}

/**
 * Émet un code court pour une instance. Met à jour le ledger (cap seulement —
 * les codes courts n'ont pas de counter : l'anti-rejeu se fait côté app par
 * "cap > cap courant").
 *
 * @return array{code:string, cap:int, old_cap:int, counter:int}
 */
function licence_lib_emit_short(string $instance, string $mode, int $value): array {
    $instance = trim($instance);
    if ($instance === '') throw new InvalidArgumentException('Instance vide.');
    if ($value <= 0) throw new InvalidArgumentException('La valeur doit être > 0.');

    $ledger = licence_lib_ledger_load();
    $freeCap = licence_lib_free_cap();
    $cur = $ledger[$instance] ?? ['cap' => $freeCap, 'counter' => 0];
    $oldCap = (int)$cur['cap'];

    $newCap = ($mode === 'set') ? $value : ($oldCap + $value);
    $code = licence_lib_short_code($instance, $newCap);

    // On conserve le counter (pour la traçabilité des codes longs éventuels),
    // sans l'incrémenter : les codes courts n'en utilisent pas.
    $ledger[$instance] = ['cap' => $newCap, 'counter' => (int)$cur['counter'], 'expiry' => 0];
    licence_lib_ledger_save($ledger);

    return ['code' => $code, 'cap' => $newCap, 'old_cap' => $oldCap, 'counter' => (int)$cur['counter']];
}