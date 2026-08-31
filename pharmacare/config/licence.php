<?php
declare(strict_types=1);
/**
 * PharmaCare — Système de licence par code d'activation
 *
 * Plafonne l'usage de l'app à un nombre de lignes de vente (vente_lignes).
 * Palier gratuit : LICENCE_FREE_CAP (10 000) lignes.
 * Au-delà, le client doit obtenir un code d'activation auprès du développeur
 * (packs additionnels de lignes, facturés au prix fixé par le développeur).
 *
 * Sécurité (on-premise : le client a le code + la BDD, donc aucune solution
 * n'est 100 % inviolable — on rend le contournement pénible) :
 *   - Signature asymétrique RSA-2048/SHA-256 (OpenSSL, chargé par défaut).
 *     Le développeur détient la clé privée (tools/licence_privatekey.php,
 *     gitignorée), l'app embarque uniquement la clé publique (LICENCE_PUBKEY).
 *     Le client ne peut pas forger un code valide.
 *   - Chaque code est lié à l'INSTANCE_ID de la pharmacie (pas de revente d'un
 *     code entre clients).
 *   - Le plafond effectif est signé : modifier la valeur en BDD casse la
 *     signature → repli sur le palier gratuit. Le client n'a aucun intérêt à
 *     trafiquer, et ne perd jamais ses données.
 *   - Anti-rejeu : un compteur strictement croissant par code empêche de
 *     rejouer un ancien code.
 *
 * Format d'un code = enregistrement de licence signé :
 *   base64url(JSON{i,c,n,e}) . "." . base64url(signature_RSA_SHA256(payload))
 *   i = instance_id (hex 32 chars), c = cap_lines absolu, n = counter, e = expiry_ts (0 = sans).
 * L'app ne signe jamais (pas de clé privée) : le code reçu EST l'enregistrement
 * signé par le dev, stocké tel quel dans parametres.licence_record.
 *
 * Outils développeur (à ne JAMAIS livrer au client — gitignorés) :
 *   tools/gen_licence_keypair.php  — génère le keypair (privée + publique)
 *   tools/gen_licence.php          — génère un code pour une instance + pack
 *   tools/licence_privatekey.php   — clé privée (gitignorée)
 *   tools/licence_ledger.json      — cap/counter par instance (gitignoré)
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/../includes/audit.php';

// Clé publique RSA (PEM, base64 sur une ligne).
// Remplie par tools/gen_licence_keypair.php. Vide = aucun code ne peut être
// vérifié ; l'app reste alors sur le palier gratuit.
const LICENCE_PUBKEY = 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQklqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FROEFNSUlCQ2dLQ0FRRUF4UlVhejZHNTJpSmo4d1V6SlcxWQp6aTRGU2pOU0dwV2EzZlFHUnJSZEUwM1ZhT0NtbC9mQ0N4ekJDSkZUQTVYbGNLVXc0OHU5MVhUWnZVYVZOWHNpCjRwaUZiU20xaHc5djl0cGVVUjlBV0RnODlIYWE0aUZDUEFCMHdvbnphbUluQmo5aUJQZ0kwdnlobmlmNGsyNGEKRmo4SUVZZiticVp2RVdBY0R6VWNkcnEyV2JzSWNvN2lFWFQyVXZzMFc0bTVHWVJZcEFCcVR0TUNjZFUvQi9yZQo0SDhOMlFHcDE5dm5iRGZFdzBpSDduMDRUWjN5K3dBRlVUUEZrZ04xa0hiOW1BR1ZGTXJ3WFl3RXJQaWpRRSs0CjZ2SUlFMkx2L2NoNk9tbXJwa1VzK3EzZFhLdXdHRXFGZngramhWTXI4NVNpRXc0Q212S09kazZlaGwvbEIwZEQKVHdJREFRQUIKLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0tCg==';
const LICENCE_FREE_CAP = 150;

// Secret HMAC pour les CODES COURTS (format LLLL-NNNNNN-CCCCCCCC, SMS-friendly).
// Vivait ici en dur (fuie via le dépôt git public le 2026-08-31) : il vit
// désormais dans config/licence_secret.php — livré dans les bundles, absent
// du dépôt git. Vide (fichier absent) = seuls les codes longs (RSA) sont
// acceptés. ⚠ Sécurité réduite par construction : ce secret est embarqué dans
// l'app livrée au client — un client qui lit le source peut forger des codes
// courts (on-premise : contournement pénible, pas impossible).
const LICENCE_HMAC_SECRET_FILE = __DIR__ . '/licence_secret.php';

/** Secret HMAC des codes courts ('' = codes courts désactivés). */
function licence_hmac_secret(): string {
    static $s = null;
    if ($s !== null) return $s;
    if (!is_file(LICENCE_HMAC_SECRET_FILE)) return $s = '';
    $v = require LICENCE_HMAC_SECRET_FILE;
    return $s = (is_string($v) && $v !== '') ? $v : '';
}

// ── Helpers base64url ───────────────────────────────────────
function licence_b64url_encode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function licence_b64url_decode(string $s): ?string {
    $bin = base64_decode(strtr($s, '-_', '+/'), true);
    return ($bin === false) ? null : $bin;
}

function licence_pubkey_pem(): string {
    $b64 = defined('LICENCE_PUBKEY') ? LICENCE_PUBKEY : '';
    if ($b64 === '') {
        throw new RuntimeException('LICENCE_PUBKEY non configurée — lancez tools/gen_licence_keypair.php');
    }
    $pem = base64_decode($b64, true);
    if ($pem === false || $pem === '') {
        throw new RuntimeException('LICENCE_PUBKEY illisible');
    }
    return $pem;
}

// ── Codes courts (HMAC, sécurité réduite, SMS-friendly) ────────────────────
//
// Format d'un code court : LLLL-NNNNNN-CCCCCCCC
//   LLLL    = 4 lettres (préfixe cosmétique, non vérifié)
//   NNNNNN  = plafond absolu en chiffres (4+ digits)
//   CCCCCCCC = jeton de contrôle = 8 symboles dérivés de HMAC-SHA256(secret, instance:cap)
// L'app recalcule le jeton avec son instance_id + le secret embarqué et le
// compare (constant-time). Le code n'est valable que pour l'instance liée.

/** Alphabet 32 symboles sans ambigüité (pas de I, O, 0, 1). */
function licence_hmac_alphabet(): string {
    return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 24 lettres + 8 chiffres = 32
}

/** 8 symboles de contrôle = HMAC-SHA256(secret, "instance:cap") tronqué à 32 bits. */
function licence_hmac8(string $instance, int $cap, string $secret): string {
    $hmac = hash_hmac('sha256', $instance . ':' . $cap, $secret, true);
    $alpha = licence_hmac_alphabet();
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $alpha[ord($hmac[$i]) % 32];
    }
    return $out;
}

/**
 * Vérifie un code court. Renvoie le plafond (int) si valide, sinon null.
 */
function licence_short_verify(string $code, string $instanceId): ?int {
    $code = strtoupper(str_replace([' ', "\t", "\r", "\n"], '', trim($code)));
    if (!preg_match('/^[A-Z0-9]{4}-[0-9]{4,}-[A-Z0-9]{8}$/', $code)) return null;
    $parts = explode('-', $code);
    $cap = (int)$parts[1];
    $check = $parts[2];
    if ($cap <= 0) return null;
    $secret = licence_hmac_secret();
    if ($secret === '') return null; // codes courts désactivés
    $expected = licence_hmac8($instanceId, $cap, $secret);
    return hash_equals($expected, $check) ? $cap : null;
}

/**
 * UUID d'instance (lié à cette pharmacie). Généré paresseusement au 1er appel
 * et stocké dans parametres.licence_instance_id. Stable tant que la BDD n'est
 * pas réinitialisée.
 */
function licence_instance_id(): string {
    static $id = null;
    if ($id !== null) return $id;
    try {
        $db = getDB();
        $id = $db->query("SELECT valeur FROM parametres WHERE cle = 'licence_instance_id'")->fetchColumn();
        if (!$id) {
            $id = bin2hex(random_bytes(16));
            $stmt = $db->prepare(
                "INSERT IGNORE INTO parametres (cle, valeur, label, groupe)
                 VALUES ('licence_instance_id', ?, 'Identifiant d''instance', 'licence')"
            );
            $stmt->execute([$id]);
            // re-lire en cas de concurrence
            $id = $db->query("SELECT valeur FROM parametres WHERE cle = 'licence_instance_id'")->fetchColumn() ?: $id;
        }
    } catch (\Throwable $e) {
        $id = 'unknown';
    }
    return $id;
}

/**
 * Vérifie un enregistrement de licence (code signé).
 * Renvoie ['cap'=>int,'counter'=>int,'expiry'=>int] ou null si invalide.
 */
function licence_record_verify(string $record, string $instanceId): ?array {
    $record = trim($record);
    if ($record === '') return null;
    $parts = explode('.', $record);
    if (count($parts) !== 2) return null;
    [$payloadB64, $sigB64] = $parts;
    $payload = licence_b64url_decode($payloadB64);
    $sig = licence_b64url_decode($sigB64);
    if ($payload === null || $sig === null) return null;
    try {
        $ok = openssl_verify($payload, $sig, licence_pubkey_pem(), OPENSSL_ALGO_SHA256);
    } catch (\Throwable $e) {
        return null;
    }
    if ($ok !== 1) return null; // 0 = signature fausse, -1 = erreur
    $data = json_decode($payload, true);
    if (!is_array($data)) return null;
    if (($data['i'] ?? '') !== $instanceId) return null;
    $cap = (int)($data['c'] ?? 0);
    $counter = (int)($data['n'] ?? 0);
    $expiry = (int)($data['e'] ?? 0);
    if ($cap <= 0 || $counter <= 0) return null;
    if ($expiry > 0 && $expiry < time()) return null; // expiré
    return ['cap' => $cap, 'counter' => $counter, 'expiry' => $expiry];
}

/**
 * État courant de la licence.
 * ['cap','counter','source'=>'record'|'free','valid'=>bool]
 */
function licence_current(bool $refresh = false): array {
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;
    $inst = licence_instance_id();
    $rec = getParam('licence_record', '');
    if ($rec !== '') {
        // Enregistrement court (code SMS) : S:cap:check
        if (str_starts_with($rec, 'S:')) {
            $parts = explode(':', substr($rec, 2), 2);
            if (count($parts) === 2) {
                $cap = (int)$parts[0];
                $check = $parts[1];
                $secret = licence_hmac_secret();
                if ($cap > 0 && $secret !== '' && hash_equals(licence_hmac8($inst, $cap, $secret), $check)) {
                    return $cache = [
                        'cap' => $cap, 'counter' => 0,
                        'source' => 'short', 'valid' => true,
                    ];
                }
            }
            // enregistrement court invalide → repli sur le palier gratuit
        } else {
            // Enregistrement long (code RSA signé)
            $v = licence_record_verify($rec, $inst);
            if ($v !== null) {
                return $cache = [
                    'cap' => $v['cap'], 'counter' => $v['counter'],
                    'source' => 'record', 'valid' => true,
                ];
            }
        }
    }
    return $cache = [
        'cap' => LICENCE_FREE_CAP, 'counter' => 0,
        'source' => 'free', 'valid' => false,
    ];
}

/** Nombre total de vente_lignes en BDD (cache disque 60 s, invalidé à chaque vente). */
function licence_usage(): int {
    static $mem = null;
    if ($mem !== null) return $mem;
    $found = false;
    require_once __DIR__ . '/../includes/cache_file.php';
    $val = cache_get('licence_usage', 60, $found);
    if ($found) {
        return $mem = (int)$val;
    }
    try {
        $n = (int)getDB()->query("SELECT COUNT(*) FROM vente_lignes")->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
    cache_set('licence_usage', $n, 60);
    return $mem = $n;
}

/** Lignes restantes avant blocage (≥ 0). */
function licence_remaining(): int {
    return max(0, licence_current()['cap'] - licence_usage());
}

/**
 * Applique un code d'activation.
 * Vérifie signature + instance + counter > courant + non expiré, puis stocke
 * l'enregistrement dans parametres.licence_record.
 * Renvoie ['ok'=>bool,'msg'=>string].
 */
function licence_apply_code(string $code): array {
    $code = trim($code);
    if ($code === '') return ['ok' => false, 'msg' => 'Code vide.'];
    $inst = licence_instance_id();

    // ── Discriminateur : '.' = code long (RSA) ; sinon code court (SMS) ──
    $isShort = strpos($code, '.') === false;

    if ($isShort) {
        // ── Code court LLLL-NNNNNN-CCCCCCCC ──
        $cap = licence_short_verify($code, $inst);
        if ($cap === null) {
            return ['ok' => false, 'msg' => 'Code court invalide ou non lié à cette instance.'];
        }
        $cur = licence_current();
        if ($cap <= $cur['cap']) {
            return ['ok' => false, 'msg' => 'Code déjà actif ou plafond (' . $cap . ') inférieur ou égal au plafond courant (' . $cur['cap'] . ').'];
        }
        $check = licence_hmac8($inst, $cap, licence_hmac_secret());
        $record = 'S:' . $cap . ':' . $check;
        return licence_store_record($record, $cap, 0, 0, $inst);
    }

    // ── Code long (RSA signé) ──
    $v = licence_record_verify($code, $inst);
    if ($v === null) {
        return ['ok' => false, 'msg' => 'Code invalide, expiré ou non lié à cette instance.'];
    }
    $cur = licence_current();
    if ($v['counter'] <= $cur['counter']) {
        return ['ok' => false, 'msg' => 'Code déjà utilisé ou obsolète (contre n°' . $v['counter'] . ' ≤ ' . $cur['counter'] . ').'];
    }
    return licence_store_record($code, $v['cap'], $v['counter'], $v['expiry'], $inst);
}

/**
 * Stocke l'enregistrement de licence en BDD + journal + audit + rafraîchit le cache.
 */
function licence_store_record(string $record, int $cap, int $counter, int $expiry, string $inst): array {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO parametres (cle, valeur, label, groupe)
             VALUES ('licence_record', ?, 'Licence active', 'licence')
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
        );
        $stmt->execute([$record]);

        $entry = json_encode(['at' => date('c'), 'cap' => $cap], JSON_UNESCAPED_SLASHES);
        $db->prepare(
            "INSERT INTO parametres (cle, valeur, label, groupe)
             VALUES ('licence_applied_codes', ?, 'Journal des licences', 'licence')
             ON DUPLICATE KEY UPDATE valeur = CONCAT(valeur, CHAR(10), ?)"
        )->execute([$entry, $entry]);

        auditLog('licence.apply', sprintf(
            'Licence appliquée : cap=%d lignes, instance=%s',
            $cap, substr($inst, 0, 8)
        ));
        paramCacheClear();      // getParam('licence_record') doit voir la nouvelle valeur
        licence_current(true); // rafraîchir le cache
        return ['ok' => true, 'msg' => 'Licence activée. Nouveau plafond : ' . $cap . ' lignes de vente.'];
    } catch (\Throwable $e) {
        if (!defined('IS_PROD') || !IS_PROD) {
            return ['ok' => false, 'msg' => 'Erreur base de données : ' . $e->getMessage()];
        }
        error_log('PharmaCare licence.apply: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Erreur base de données lors de l\'activation de la licence. Contactez un administrateur.'];
    }
}

// ── Intégrité des fichiers (manifeste signé RSA) ───────────────────────────
//
// Le développeur signe un manifeste des empreintes SHA-256 des fichiers
// critiques (config/licence.php, modules/vente.php, etc.) avec sa clé privée.
// L'app vérifie la signature (clé publique embarquée) puis recompare les
// empreintes. Un client ne possédant pas la clé privée ne peut pas re-signer
// un manifeste modifié → toute édition d'un fichier couvert est détectée.
//
// Dissuasion, pas garantie absolue (le client reste maître de ses fichiers) :
// il peut toujours effacer LA fonction de vérification — mais le fichier qui
// la contient (config/licence.php) est lui-même couvert par le manifeste, donc
// la modification est détectée avant d'être effacée, et journalisée.

/**
 * Vérifie l'intégrité des fichiers licence.
 * Renvoie :
 *   ['status'=>'ok'|'tampered'|'nomifest'|'bad', 'ok'=>bool,
 *    'tampered'=>string[], 'files'=>[['path','ok','reason'?]]]
 *
 *   ok       → tous les fichiers correspondent au manifeste signé.
 *   tampered → un ou plusieurs fichiers diffèrent (ou sont absents).
 *   nomifest → aucun manifeste d'intégrité déployé (neutre).
 *   bad      → manifeste présent mais signature invalide / illisible.
 */
function licence_integrity_check(): array {
    $manifestFile = __DIR__ . '/licence_integrity.php';
    if (!is_file($manifestFile)) {
        return ['status' => 'nomifest', 'ok' => true, 'tampered' => [], 'files' => []];
    }
    $manifest = require $manifestFile;
    if (!is_string($manifest) || $manifest === '') {
        return ['status' => 'bad', 'ok' => false, 'tampered' => ['(manifeste)'], 'files' => []];
    }
    $parts = explode('.', $manifest);
    if (count($parts) !== 2) {
        return ['status' => 'bad', 'ok' => false, 'tampered' => ['(manifeste)'], 'files' => []];
    }
    [$pb64, $sb64] = $parts;
    $payload = licence_b64url_decode($pb64);
    $sig = licence_b64url_decode($sb64);
    if ($payload === null || $sig === null) {
        return ['status' => 'bad', 'ok' => false, 'tampered' => ['(manifeste)'], 'files' => []];
    }
    try {
        $ok = openssl_verify($payload, $sig, licence_pubkey_pem(), OPENSSL_ALGO_SHA256);
    } catch (\Throwable $e) {
        $ok = 0;
    }
    if ($ok !== 1) {
        return ['status' => 'bad', 'ok' => false, 'tampered' => ['(manifeste)'], 'files' => []];
    }
    $data = json_decode($payload, true);
    if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
        return ['status' => 'bad', 'ok' => false, 'tampered' => ['(manifeste)'], 'files' => []];
    }
    $root = dirname(__DIR__); // pharmacare/
    $files = [];
    $tampered = [];
    foreach ($data['files'] as $rel => $expected) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            $tampered[] = $rel;
            $files[] = ['path' => $rel, 'ok' => false, 'reason' => 'absent'];
            continue;
        }
        $h = hash_file('sha256', $path);
        $match = hash_equals((string)$expected, $h);
        $files[] = ['path' => $rel, 'ok' => $match];
        if (!$match) $tampered[] = $rel;
    }
    return [
        'status'   => $tampered ? 'tampered' : 'ok',
        'ok'       => !$tampered,
        'tampered' => $tampered,
        'files'    => $files,
    ];
}