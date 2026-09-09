<?php
declare(strict_types=1);
/**
 * Interface graphique de génération de codes de licence — CÔTÉ DÉVELOPPEUR.
 *
 * À lancer via le serveur PHP local (ne JAMAIS exposer via Apache) :
 *   double-clic sur tools/licence_gui.bat
 *   ou :  php -S 127.0.0.1:8080 -t tools  puis ouvrir http://127.0.0.1:8080/gen_licence_gui.php
 *
 * Protégé par une passphrase (ci-dessous) + accessible uniquement depuis localhost.
 * Utilise la clé privée tools/licence_privatekey.php et le ledger tools/licence_ledger.json.
 */
require_once __DIR__ . '/licence_lib.php';

// ── Passphrase (CHANGEZ-MOI) — l'accès à cette interface. ──────────────────
const GUI_PASS = 'ramadan061091@1433';

session_start();

// Contrôle d'accès : localhost uniquement + passphrase.
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$isLocal) { http_response_code(403); echo 'Accès local uniquement.'; exit; }

if (isset($_GET['logout'])) { session_destroy(); header('Location: ' . basename(__FILE__)); exit; }

if (empty($_SESSION['gui_ok'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        if (hash_equals(GUI_PASS, (string)$_POST['pass'])) {
            $_SESSION['gui_ok'] = true;
            header('Location: ' . basename(__FILE__)); exit;
        }
        $authErr = 'Passphrase incorrecte.';
    }
    renderGate($authErr ?? null);
    exit;
}

// ── Traitements ─────────────────────────────────────────────
$message = null;
$gen = null; // ['code','cap','old_cap','counter','instance','expire']

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'generate') {
        try {
            $instance = trim((string)($_POST['instance'] ?? ''));
            $mode = ($_POST['mode'] ?? 'pack') === 'set' ? 'set' : 'pack';
            $value = (int)($_POST['value'] ?? 0);
            $exp = trim((string)($_POST['expire'] ?? ''));
            $format = ($_POST['format'] ?? 'short') === 'long' ? 'long' : 'short';
            if ($format === 'short' && $exp !== '') {
                throw new InvalidArgumentException("Les codes courts n'acceptent pas d'expiration.");
            }
            $expiry = 0;
            if ($format === 'long' && $exp !== '') {
                $ts = strtotime($exp);
                if ($ts === false) throw new InvalidArgumentException("Date d'expiration invalide.");
                $expiry = (int)$ts;
            }
            if ($format === 'short') {
                $r = licence_lib_emit_short($instance, $mode, $value);
                $gen = [
                    'code' => $r['code'], 'cap' => $r['cap'], 'old_cap' => $r['old_cap'],
                    'counter' => $r['counter'], 'instance' => $instance, 'expire' => 0, 'short' => true,
                ];
            } else {
                $r = licence_lib_emit($instance, $mode, $value, $expiry);
                $gen = [
                    'code' => $r['code'], 'cap' => $r['cap'], 'old_cap' => $r['old_cap'],
                    'counter' => $r['counter'], 'instance' => $instance, 'expire' => $expiry, 'short' => false,
                ];
            }
            $message = ['ok' => true, 'text' => "Code généré : plafond {$r['old_cap']} → {$r['cap']} (+" . ($r['cap'] - $r['old_cap']) . ") lignes."];
            // Garde de cohérence : régénérer le manifeste d'intégrité à chaque
            // émission de code, pour ne jamais livrer un manifeste périmé.
            // (Ne fait pas échouer la génération du code si le manifeste échoue.)
            try {
                licence_lib_generate_integrity();
                $message['text'] .= " Manifeste d'intégrité régénéré.";
            } catch (Throwable $ie) {
                $message['text'] .= " (manifeste non régénéré : " . $ie->getMessage() . ")";
            }
        } catch (Throwable $e) {
            $message = ['ok' => false, 'text' => $e->getMessage()];
        }
    } elseif ($action === 'remove') {
        $id = trim((string)($_POST['instance'] ?? ''));
        $ledger = licence_lib_ledger_load();
        if (isset($ledger[$id])) {
            unset($ledger[$id]);
            licence_lib_ledger_save($ledger);
            $message = ['ok' => true, 'text' => "Client retiré du ledger : $id"];
        } else {
            $message = ['ok' => false, 'text' => "Instance introuvable dans le ledger."];
        }
    } elseif ($action === 'reset') {
        $id = trim((string)($_POST['instance'] ?? ''));
        $ledger = licence_lib_ledger_load();
        $ledger[$id] = ['cap' => licence_lib_free_cap(), 'counter' => 0, 'expiry' => 0];
        licence_lib_ledger_save($ledger);
        $message = ['ok' => true, 'text' => "Ledger réinitialisé pour $id (cap=" . licence_lib_free_cap() . ", counter=0)."];
    } elseif ($action === 'setfree') {
        $cap = (int)($_POST['freecap'] ?? 0);
        try {
            licence_lib_set_free_cap($cap);
            // config/licence.php a été modifié → régénérer le manifeste d'intégrité
            // pour qu'il reste cohérent (sinon l'app signalera une intégrité compromise).
            $int = licence_lib_generate_integrity();
            $message = ['ok' => true, 'text' => "Palier gratuit défini à " . number_format($cap, 0, ',', ' ')
                . " lignes. Manifeste d'intégrité régénéré (" . $int['count'] . " fichiers)."];
        } catch (Throwable $e) {
            $message = ['ok' => false, 'text' => $e->getMessage()];
        }
    }
}

$ledger = licence_lib_ledger_load();
$privOk = file_exists(licence_lib_privkey_path());
$freeCap = licence_lib_free_cap();
renderMain($message, $gen, $ledger, $privOk, $freeCap);

// ── Vues ────────────────────────────────────────────────────
function renderGate(?string $err): void {
    pageHead('Générateur de licences — Accès');
    echo '<div class="wrap"><div class="card">';
    echo '<h1>🔐 Générateur de codes de licence</h1>';
    echo '<p class="muted">Outil développeur — accès restreint.</p>';
    if ($err) echo '<div class="msg err">' . e($err) . '</div>';
    echo '<form method="post"><input type="password" name="pass" placeholder="Passphrase" autofocus class="inp">'
       . '<button class="btn">Entrer</button></form>';
    echo '</div></div>';
    pageFoot();
}

function renderMain($message, $gen, array $ledger, bool $privOk, int $freeCap): void {
    pageHead('Générateur de licences');
    echo '<div class="wrap">';
    echo '<header><h1>🔐 Générateur de codes de licence</h1>'
       . '<a class="link" href="?logout=1">Déconnexion</a></header>';

    if (!$privOk) {
        echo '<div class="msg err">⚠ ' . htmlspecialchars(licence_lib_privkey_hint()) . '</div>';
    }

    if ($message) echo '<div class="msg ' . ($message['ok'] ? 'ok' : 'err') . '">' . htmlspecialchars($message['text']) . '</div>';

    // ── Palier gratuit (configurable) ──
    echo '<div class="card"><h2>Palier gratuit</h2>';
    echo '<p class="muted small">Nombre de lignes offertes à tout nouveau client, et repli quand aucune licence n\'est active. Stocké dans <code>config/licence.php</code> (const <code>LICENCE_FREE_CAP</code>).'
       . ' ⚠ Changer cette valeur modifie le repli de tous les clients déjà déployés sans licence active, au prochain déploiement. Les licences déjà activées gardent leur plafond.</p>';
    echo '<form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">'
       . '<input type="hidden" name="action" value="setfree">'
       . '<div style="flex:1;min-width:200px"><label>Palier gratuit (lignes)</label>'
       . '<input type="number" name="freecap" min="1" required class="inp" value="' . $freeCap . '"></div>'
       . '<button class="btn">Enregistrer</button></form>';
    echo '</div>';

    // ── Code généré ──
    if ($gen) {
        echo '<div class="card"><h2>Code d\'activation</h2>';
        echo '<p class="muted">Instance <code>' . htmlspecialchars($gen['instance']) . '</code> — '
           . 'plafond ' . htmlspecialchars((string)$gen['old_cap']) . ' → <b>' . htmlspecialchars((string)$gen['cap']) . '</b> lignes'
           . ($gen['short'] ? '' : ', counter ' . htmlspecialchars((string)$gen['counter']))
           . ', expire ' . ($gen['expire'] ? date('d/m/Y H:i', $gen['expire']) : 'jamais') . '.</p>';
        $isShort = !empty($gen['short']);
        echo '<textarea id="code" readonly rows="' . ($isShort ? 2 : 6) . '" class="code'
           . ($isShort ? ' code-short' : '') . '">' . htmlspecialchars($gen['code']) . '</textarea>';
        echo '<div class="row"><button class="btn" onclick="copyCode()">Copier le code</button>'
           . '<span id="cpy" class="muted"></span></div>';
        if ($isShort) echo '<p class="muted small">Code court (SMS). Donnez-le au client : il le saisit sur sa page Licence.</p>';
        echo '</div>';
    }

    // ── Formulaire ──
    echo '<div class="card"><h2>Émettre un code</h2>';
    echo '<form method="post"><input type="hidden" name="action" value="generate">';
    echo '<label>Identifiant d' . "'instance du client" . '</label>';
    echo '<input list="ids" name="instance" required class="inp" placeholder="ex : 56fb8a00..." value="' . htmlspecialchars($_POST['instance'] ?? '') . '">';
    echo '<datalist id="ids">';
    foreach ($ledger as $id => $info) echo '<option value="' . htmlspecialchars($id) . '">';
    echo '</datalist>';
    echo '<div class="muted small">L\'identifiant est affiché sur la page Licence du client (champ copiable).</div>';

    echo '<label>Format du code</label><div class="radio">';
    echo '<label><input type="radio" name="format" value="short" checked id="fmt-short"> Court <span class="muted small">(LLLL-NNNNNN-CCCCCCCC, SMS)</span></label>';
    echo '<label><input type="radio" name="format" value="long" id="fmt-long"> Long <span class="muted small">(signé RSA, sécurisé)</span></label>';
    echo '</div>';
    echo '<div class="muted small" id="fmt-note">Court : sécurité réduite (secret embarqué). Pratique pour SMS. Long : signature RSA, non forgeable par le client.</div>';

    echo '<label>Mode</label><div class="radio">';
    echo '<label><input type="radio" name="mode" value="pack" checked> Pack additionnel (+N lignes)</label>';
    echo '<label><input type="radio" name="mode" value="set"> Cap absolu (fixe le plafond)</label>';
    echo '</div>';

    echo '<label>Nombre de lignes / plafond</label>';
    echo '<input type="number" name="value" min="1" required class="inp" value="5000">';
    echo '<div class="muted small">Pack = ajoute au plafond courant du client. Cap absolu = fixe le plafond exact.</div>';

    echo '<div id="expire-block">';
    echo '<label>Expiration (optionnel)</label>';
    echo '<input type="datetime-local" name="expire" class="inp">';
    echo '<div class="muted small">Vide = sans limite de durée. (Codes longs uniquement.)</div>';
    echo '</div>';

    echo '<button class="btn big">Générer le code</button>';
    echo '</form></div>';

    // ── Ledger ──
    echo '<div class="card"><h2>Clients enregistrés <span class="muted small">(' . count($ledger) . ')</span></h2>';
    if (!$ledger) {
        echo '<p class="muted">Aucun client. Le ledger se remplit au fur et à mesure que vous émettez des codes.</p>';
    } else {
        echo '<table><thead><tr><th>Instance</th><th>Plafond</th><th>Counter</th><th>Expire</th><th>Actions</th></tr></thead><tbody>';
        foreach ($ledger as $id => $info) {
            echo '<tr><td class="mono">' . htmlspecialchars($id) . '</td>'
               . '<td>' . number_format((int)$info['cap'], 0, ',', ' ') . '</td>'
               . '<td>' . (int)$info['counter'] . '</td>'
               . '<td>' . (isset($info['expiry']) && $info['expiry'] ? date('d/m/Y', (int)$info['expiry']) : '—') . '</td>'
               . '<td class="acts">'
               . '<form method="post" style="display:inline"><input type="hidden" name="action" value="reset"><input type="hidden" name="instance" value="' . htmlspecialchars($id) . '"><button class="mini" title="Réinitialiser le ledger de ce client (cap=' . $freeCap . ', counter=0)" onclick="return confirm(\'Réinitialiser le ledger de ce client ?\')">↺</button></form>'
               . '<form method="post" style="display:inline"><input type="hidden" name="action" value="remove"><input type="hidden" name="instance" value="' . htmlspecialchars($id) . '"><button class="mini danger" title="Retirer ce client du ledger" onclick="return confirm(\'Retirer ce client du ledger ?\')">✕</button></form>'
               . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<p class="muted small">Palier gratuit de référence : ' . number_format($freeCap, 0, ',', ' ') . ' lignes.</p>';
    echo '</div>';

    echo '</div>';
    pageFoot();
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function pageHead(string $title): void {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . htmlspecialchars($title) . '</title><style>' . lic_gui_css() . '</style></head><body>';
    echo '<script>function copyCode(){var t=document.getElementById("code");t.select();document.execCommand("copy");document.getElementById("cpy").textContent="✓ copié";}</script>';
    echo '<script>function syncFmt(){var s=document.getElementById("fmt-short").checked;document.getElementById("expire-block").style.display=s?"none":"block";}'
       . 'if(document.getElementById("fmt-short")){[document.getElementById("fmt-short"),document.getElementById("fmt-long")].forEach(function(r){r.addEventListener("change",syncFmt);});syncFmt();}</script>';
}
function pageFoot(): void { echo '</body></html>'; }

function lic_gui_css(): string {
    return <<<'CSS'
:root{--bg:#0b1018;--card:#111a27;--b:#1f2d3f;--t:#e6edf3;--m:#8b9bb0;--teal:#2dd4bf;--gold:#f59e0b;--red:#ef4444;--ok:#22c55e}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--t);font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif}
.wrap{max-width:880px;margin:0 auto;padding:24px}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
h1{font-size:20px;margin:0}h2{font-size:15px;margin:0 0 14px;color:var(--teal)}
.card{background:var(--card);border:1px solid var(--b);border-radius:12px;padding:20px;margin-bottom:18px}
.muted{color:var(--m)}.small{font-size:12px}.mono{font-family:ui-monospace,Consolas,monospace;font-size:12px}
label{display:block;margin:12px 0 4px;font-weight:600;font-size:13px}
.inp{width:100%;padding:9px 11px;background:#0d1622;border:1px solid var(--b);border-radius:8px;color:var(--t);font-size:14px}
.btn{padding:9px 16px;background:var(--teal);color:#04201b;border:0;border-radius:8px;font-weight:700;cursor:pointer}
.btn.big{width:100%;margin-top:16px;padding:12px;font-size:15px}
.btn:hover{filter:brightness(1.08)}
.mini{padding:4px 8px;background:var(--b);color:var(--t);border:1px solid #2a3c52;border-radius:6px;cursor:pointer}
.mini.danger{color:var(--red)}
.radio{display:flex;gap:18px;margin:4px 0}.radio label{font-weight:400;display:flex;gap:6px;align-items:center}
table{width:100%;border-collapse:collapse;margin-top:6px}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--b)}
th{color:var(--m);font-size:12px;text-transform:uppercase}.acts{white-space:nowrap}
.msg{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px}
.msg.ok{background:rgba(34,197,94,.12);border:1px solid var(--ok);color:var(--ok)}
.msg.err{background:rgba(239,68,68,.12);border:1px solid var(--red);color:var(--red)}
.code{width:100%;font-family:ui-monospace,Consolas,monospace;font-size:11px;background:#0d1622;border:1px solid var(--b);border-radius:8px;color:var(--gold);padding:10px}
.code-short{font-size:20px;text-align:center;letter-spacing:2px;font-weight:700;padding:14px}
.row{display:flex;align-items:center;gap:12px;margin-top:8px}
.link{color:var(--m);font-size:13px;text-decoration:none}.link:hover{color:var(--t)}
code{background:#0d1622;padding:2px 6px;border-radius:4px;font-size:12px}
CSS;
}