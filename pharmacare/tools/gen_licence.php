<?php
declare(strict_types=1);
/**
 * Générateur de codes d'activation de licence — CLI côté développeur.
 *
 * Deux modes de sortie :
 *
 * 1) Mode humain (défaut) — pour la console :
 *   php tools/gen_licence.php --instance=<ID> --pack=<N>           # +N lignes (code long RSA)
 *   php tools/gen_licence.php --instance=<ID> --set=<CAP>           # cap absolu
 *   php tools/gen_licence.php --instance=<ID> --pack=<N> --expire=<unix_ts>
 *   php tools/gen_licence.php --instance=<ID> --pack=<N> --short    # code court SMS (HMAC)
 *   php tools/gen_licence.php --list                                # lister le ledger
 *
 * 2) Mode machine (--json) — pour la GUI native (gen_licence.exe) :
 *   php tools/gen_licence.php --json --op=status
 *   php tools/gen_licence.php --json --op=generate --instance=<ID> --format=short|long --mode=pack|set --value=<N> [--expire=<unix_ts>]
 *   php tools/gen_licence.php --json --op=list
 *   php tools/gen_licence.php --json --op=remove  --instance=<ID>
 *   php tools/gen_licence.php --json --op=reset   --instance=<ID>
 *   php tools/gen_licence.php --json --op=setfree  --freecap=<N>
 *   php tools/gen_licence.php --json --op=integrity
 *
 * Le code produit = enregistrement de licence signé (tools/licence_lib.php).
 * Le client le colle dans la page Licence de l'app.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement. Pour une interface web, lancez tools/licence_gui.bat\n");
    exit(1);
}

require_once __DIR__ . '/licence_lib.php';

$opts = getopt('', ['json','op:','instance:','pack:','set:','expire:','list','short','format:','mode:','value:','freecap:']);

// ── Mode machine (--json) : pilotage par la GUI native ───────────────────────
if (isset($opts['json'])) {
    $out = json_emit($opts);
    fwrite(STDOUT, json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    exit(0);
}

// ── Mode humain (défaut) ────────────────────────────────────────────────────
// Mode liste
if (isset($opts['list'])) {
    $ledger = licence_lib_ledger_load();
    if (!$ledger) { echo "(ledger vide)\n"; exit(0); }
    foreach ($ledger as $id => $info) {
        echo "$id  cap={$info['cap']}  counter={$info['counter']}"
            . (isset($info['expiry']) && $info['expiry'] ? "  expire=" . date('c', $info['expiry']) : '') . "\n";
    }
    exit(0);
}

if (empty($opts['instance'])) {
    fwrite(STDERR, "Usage: php gen_licence.php --instance=<ID> [--pack=<N> | --set=<CAP>] [--expire=<unix_ts>] [--short]\n");
    fwrite(STDERR, "Interface graphique : double-cliquez tools/licence_gui.bat ou tools/gen_licence.exe\n");
    exit(1);
}
$instance = (string)$opts['instance'];

if (isset($opts['set'])) {
    $mode = 'set';
    $value = (int)$opts['set'];
} elseif (isset($opts['pack'])) {
    $mode = 'pack';
    $value = (int)$opts['pack'];
} else {
    fwrite(STDERR, "Préciser --pack=<N> (pack additionnel) ou --set=<CAP> (cap absolu).\n");
    exit(1);
}

$expiry = array_key_exists('expire', $opts) ? (int)$opts['expire'] : 0;
$short = isset($opts['short']);

if ($short && $expiry) {
    fwrite(STDERR, "Les codes courts n'acceptent pas d'expiration.\n");
    exit(1);
}

try {
    $r = $short
        ? licence_lib_emit_short($instance, $mode, $value)
        : licence_lib_emit($instance, $mode, $value, $expiry);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "CODE D'ACTIVATION (" . ($short ? 'COURT / SMS' : 'LONG / RSA') . ", à transmettre au client) :\n\n";
echo $r['code'] . "\n\n";
echo "instance : $instance\n";
echo "cap      : {$r['old_cap']} -> {$r['cap']}  (+" . ($r['cap'] - $r['old_cap']) . " lignes)\n";
if (!$short) echo "counter  : {$r['counter']}\n";
echo "expire   : " . ($short ? 'jamais (code court)' : ($expiry ? date('c', $expiry) : 'jamais')) . "\n";

// ════════════════════════════════════════════════════════════════════════════
//  Dispatch --json
// ════════════════════════════════════════════════════════════════════════════
function json_emit(array $opts): array {
    $op = (string)($opts['op'] ?? 'status');

    try {
        switch ($op) {

            case 'status': {
                return [
                    'ok'       => true,
                    'priv_ok'  => file_exists(licence_lib_privkey_path()),
                    'free_cap'  => licence_lib_free_cap(),
                    'ledger'   => ledger_view(),
                ];
            }

            case 'list': {
                return [
                    'ok'      => true,
                    'ledger'  => ledger_view(),
                    'free_cap' => licence_lib_free_cap(),
                ];
            }

            case 'generate': {
                $instance = trim((string)($opts['instance'] ?? ''));
                $format   = (($opts['format'] ?? 'short') === 'long') ? 'long' : 'short';
                $mode     = (($opts['mode'] ?? 'pack') === 'set') ? 'set' : 'pack';
                $value    = (int)($opts['value'] ?? 0);
                $expiry   = (int)($opts['expire'] ?? 0);

                if ($instance === '') throw new InvalidArgumentException('Instance vide.');
                if ($value <= 0)      throw new InvalidArgumentException('La valeur doit être > 0.');
                if ($format === 'short' && $expiry) throw new InvalidArgumentException("Les codes courts n'acceptent pas d'expiration.");

                if ($format === 'short') {
                    $r = licence_lib_emit_short($instance, $mode, $value);
                    $short = true;
                } else {
                    $r = licence_lib_emit($instance, $mode, $value, $expiry);
                    $short = false;
                }

                // Garde de cohérence : régénérer le manifeste d'intégrité à chaque
                // émission (ne fait pas échouer la génération si le manifeste échoue).
                $integrityNote = '';
                try {
                    licence_lib_generate_integrity();
                    $integrityNote = " Manifeste d'intégrité régénéré.";
                } catch (Throwable $ie) {
                    $integrityNote = " (manifeste non régénéré : " . $ie->getMessage() . ")";
                }

                return [
                    'ok'       => true,
                    'code'     => $r['code'],
                    'cap'      => $r['cap'],
                    'old_cap'  => $r['old_cap'],
                    'counter'  => $short ? ($r['counter'] ?? 0) : $r['counter'],
                    'instance' => $instance,
                    'expire'   => $short ? 0 : $expiry,
                    'short'    => $short,
                    'message'  => "Code généré : plafond {$r['old_cap']} → {$r['cap']} (+" . ($r['cap'] - $r['old_cap']) . ") lignes." . $integrityNote,
                    'ledger'   => ledger_view(),
                ];
            }

            case 'remove': {
                $id = trim((string)($opts['instance'] ?? ''));
                if ($id === '') throw new InvalidArgumentException('Instance vide.');
                $ledger = licence_lib_ledger_load();
                if (!isset($ledger[$id])) throw new RuntimeException('Instance introuvable dans le ledger.');
                unset($ledger[$id]);
                licence_lib_ledger_save($ledger);
                return ['ok' => true, 'message' => "Client retiré du ledger : $id", 'ledger' => ledger_view()];
            }

            case 'reset': {
                $id = trim((string)($opts['instance'] ?? ''));
                if ($id === '') throw new InvalidArgumentException('Instance vide.');
                $ledger = licence_lib_ledger_load();
                $ledger[$id] = ['cap' => licence_lib_free_cap(), 'counter' => 0, 'expiry' => 0];
                licence_lib_ledger_save($ledger);
                return ['ok' => true, 'message' => "Ledger réinitialisé pour $id (cap=" . licence_lib_free_cap() . ", counter=0).", 'ledger' => ledger_view()];
            }

            case 'setfree': {
                $cap = (int)($opts['freecap'] ?? 0);
                if ($cap <= 0) throw new InvalidArgumentException('Le palier gratuit doit être > 0.');
                licence_lib_set_free_cap($cap);
                $int = licence_lib_generate_integrity();
                return [
                    'ok'       => true,
                    'message'  => "Palier gratuit défini à " . number_format($cap, 0, ',', ' ')
                                . " lignes. Manifeste d'intégrité régénéré (" . $int['count'] . " fichiers).",
                    'free_cap' => $cap,
                ];
            }

            case 'integrity': {
                $int = licence_lib_generate_integrity();
                return ['ok' => true, 'message' => "Manifeste régénéré (" . $int['count'] . " fichiers).", 'count' => $int['count']];
            }

            default:
                throw new InvalidArgumentException("Opération inconnue : $op");
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/** Vue plat du ledger pour la sérialisation JSON. */
function ledger_view(): array {
    $out = [];
    foreach (licence_lib_ledger_load() as $id => $info) {
        $out[$id] = [
            'cap'     => (int)($info['cap'] ?? 0),
            'counter' => (int)($info['counter'] ?? 0),
            'expiry'  => (int)($info['expiry'] ?? 0),
        ];
    }
    return $out;
}