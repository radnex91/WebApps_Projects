<?php
$currentPage = 'comptabilite';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptabilite.php';
requireLogin();
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('comptabilite');

if (!compta_enabled()) {
    echo '<div class="alert alert-red">Module comptabilité non installé. Exécutez sql/update_v9.sql.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Helper local : lien signé vers la source d'une écriture
function secure_source_link(string $table, int $id): string {
    switch ($table) {
        case 'caisse_ventes': return 'caisse.php?ticket=' . $id . '&tok=' . url_sign($id, 'caisse_vente');
        case 'factures':      return 'facturation.php?facture=' . $id . '&tok=' . url_sign($id, 'facture');
        case 'stock_entries': return 'stocks.php?entry=' . $id . '&tok=' . url_sign($id, 'stock_entry');
        default: return '#';
    }
}

// ── POST : saisie manuelle d'écriture (partie double) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'saisie_manuelle' && can('compta.saisir')) {
    csrf_verify();
    $date = post_str('date');
    $libelle = post_str('libelle');
    $journal = post_str('journal') ?: 'OD';
    $comptes = $_POST['compte'] ?? [];
    $debits  = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];
    $tiers   = $_POST['tiers'] ?? [];
    $lignes = [];
    for ($i = 0; $i < count($comptes); $i++) {
        if (trim($comptes[$i]) === '') continue;
        $lignes[] = [
            'compte' => trim($comptes[$i]),
            'debit'  => (float)($debits[$i] ?? 0),
            'credit' => (float)($credits[$i] ?? 0),
            'tiers'  => $tiers[$i] ?? null,
        ];
    }
    $saisieErr = null;
    try {
        compta_generer_ecriture($journal, $date, $libelle, $lignes, 'saisie_manuelle', null, currentUser()['id']);
        logActivity('Écriture comptable saisie manuellement', 'blue', 'comptabilite', 0);
    } catch (Exception $e) {
        $saisieErr = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/comptabilite.php?tab=journaux' . ($saisieErr ? '&err=' . urlencode($saisieErr) : ''));
    exit;
}

// ── POST : clôturer un exercice ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'cloturer_exercice') {
    csrf_verify();
    if (can('compta.cloturer')) {
        $eid = post_int('exercice_id');
        db_exec("UPDATE compta_exercices SET statut='cloture', cloture_par=?, date_cloture=NOW() WHERE id=? AND statut='ouvert'", [currentUser()['id'], $eid]);
        logActivity('Exercice clôturé', 'yellow', 'comptabilite', $eid);
    }
    header('Location: ' . APP_URL . '/comptabilite.php?tab=exercices');
    exit;
}

// ── POST : CRUD plan comptable ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'compte_save' && can('compta.param_comptes')) {
    csrf_verify();
    $id = post_int('id');
    $numero = post_str('numero');
    $libelle = post_str('libelle');
    $classe = post_int('classe');
    $type = post_str('type');
    if ($id) {
        db_exec("UPDATE compta_comptes SET numero=?, libelle=?, classe=?, type=?, statut=? WHERE id=?", [$numero, $libelle, $classe, $type, post_str('statut', 'actif'), $id]);
    } else {
        db_exec("INSERT INTO compta_comptes (numero, libelle, classe, type, statut, position) VALUES (?, ?, ?, ?, 'actif', 0)", [$numero, $libelle, $classe, $type]);
    }
    logActivity('Compte comptable enregistré: ' . $numero, 'blue', 'comptabilite', $id);
    header('Location: ' . APP_URL . '/comptabilite.php?tab=plan');
    exit;
}

$tab = in_whitelist(get_str('tab'), ['dashboard','journaux','gl','balance','bilan','cr','plan','exercices'], 'dashboard');
$exercice = (int)get_str('exercice', date('Y'));
$dateDebut = get_str('du') ?: date('Y-01-01');
$dateFin   = get_str('au') ?: date('Y-12-31');

// ── Export CSV (balance / CR / grand livre) ──
if (get_str('export') === 'csv' && in_array($tab, ['balance','cr','gl'], true)) {
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compta_' . $tab . '_' . $dateDebut . '_' . $dateFin . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    if ($tab === 'balance') {
        fputcsv($out, ['Compte','Libellé','Classe','Total débit','Total crédit','Solde débiteur','Solde créditeur'], ';');
        $bal = db_select("SELECT c.numero, c.libelle, c.classe, SUM(l.debit) AS td, SUM(l.credit) AS tc FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.date_ecriture BETWEEN ? AND ? GROUP BY c.id ORDER BY c.numero", [$dateDebut,$dateFin]);
        foreach ($bal as $b) { $sd = (float)$b['td'] - (float)$b['tc']; fputcsv($out, [$b['numero'], $b['libelle'], $b['classe'], $b['td'], $b['tc'], $sd > 0 ? $sd : '', $sd < 0 ? -$sd : ''], ';'); }
    } elseif ($tab === 'cr') {
        fputcsv($out, ['Compte','Libellé','Solde'], ';');
        foreach ([6 => 'charges', 7 => 'produits'] as $classe => $label) {
            $sql = $classe === 6
                ? "SELECT c.numero, c.libelle, SUM(l.debit)-SUM(l.credit) AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=? AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero"
                : "SELECT c.numero, c.libelle, SUM(l.credit)-SUM(l.debit) AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=? AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero";
            $rows = db_select($sql, [$classe, $dateDebut, $dateFin]);
            foreach ($rows as $r) fputcsv($out, [$label . ' :: ' . $r['numero'], $r['libelle'], $r['solde']], ';');
        }
    } else { // gl
        fputcsv($out, ['Date','Numéro','Libellé','Compte','Débit','Crédit','Solde cumulé'], ';');
        $mvt = db_select("SELECT e.date_ecriture, e.numero, e.libelle, c.numero AS compte, l.debit, l.credit FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.date_ecriture BETWEEN ? AND ? ORDER BY e.date_ecriture, e.id", [$dateDebut, $dateFin]);
        $solde = 0.0;
        foreach ($mvt as $r) { $solde += (float)$r['debit'] - (float)$r['credit']; fputcsv($out, [$r['date_ecriture'], $r['numero'], $r['libelle'], $r['compte'], $r['debit'], $r['credit'], $solde], ';'); }
    }
    fclose($out);
    exit;
}

// ── KPIs tableau de bord (période) ──
$recettes  = (float)db_scalar("SELECT COALESCE(SUM(l.credit),0) FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=7 AND e.date_ecriture BETWEEN ? AND ?", [$dateDebut, $dateFin]);
$depenses  = (float)db_scalar("SELECT COALESCE(SUM(l.debit),0) FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=6 AND e.date_ecriture BETWEEN ? AND ?", [$dateDebut, $dateFin]);
$treso     = (float)db_scalar("SELECT COALESCE(SUM(l.debit)-SUM(l.credit),0) FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=5 AND e.date_ecriture BETWEEN ? AND ?", [$dateDebut, $dateFin]);
$resultat  = $recettes - $depenses;
$exercices = db_select("SELECT annee, statut FROM compta_exercices ORDER BY annee DESC");
$saisieErr = get_str('err');
?>
<div class="page-header-row">
  <div><h2><i class="bi bi-journal-text"></i> Comptabilité</h2><p>Synthèse financière SYSCOHADA — exercice <?= (int)$exercice ?></p></div>
</div>

<?php if ($saisieErr): ?><div class="alert alert-red alert-auto">⚠️ <?= h($saisieErr) ?></div><?php endif; ?>

<div class="tab-bar" style="display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach (['dashboard'=>'Tableau de bord','journaux'=>'Journaux','gl'=>'Grand livre','balance'=>'Balance','bilan'=>'Bilan','cr'=>'Compte de résultat','plan'=>'Plan comptable','exercices'=>'Exercices'] as $t => $l): ?>
    <a href="?tab=<?= $t ?>" class="portail-tab <?= $tab === $t ? 'active' : '' ?>" style="padding:8px 14px;border-radius:8px;border:1px solid var(--border);background:<?= $tab === $t ? 'var(--accent)' : 'var(--bg)' ?>;color:<?= $tab === $t ? '#fff' : 'var(--text2)' ?>;font-size:13px;font-weight:500;cursor:pointer"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <input type="hidden" name="tab" value="<?= h($tab) ?>">
  <label style="font-size:12px;color:var(--text3)">Exercice</label>
  <select name="exercice" onchange="this.form.submit()" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
    <?php foreach ($exercices as $ex): ?><option value="<?= (int)$ex['annee'] ?>" <?= $exercice === (int)$ex['annee'] ? 'selected' : '' ?>><?= (int)$ex['annee'] ?> (<?= h($ex['statut']) ?>)</option><?php endforeach; ?>
  </select>
  <input type="date" name="du" value="<?= h($dateDebut) ?>" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
  <input type="date" name="au" value="<?= h($dateFin) ?>" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
  <button class="btn btn-blue btn-sm">Filtrer</button>
</form>

<?php if ($tab === 'dashboard'): ?>
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px">
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Recettes (classe 7)</div><div style="font-size:22px;font-weight:700;color:var(--green)"><?= fmt_money($recettes) ?></div></div>
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Dépenses (classe 6)</div><div style="font-size:22px;font-weight:700;color:var(--red)"><?= fmt_money($depenses) ?></div></div>
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Résultat net</div><div style="font-size:22px;font-weight:700;color:<?= $resultat >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= fmt_money($resultat) ?></div></div>
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Trésorerie (classe 5)</div><div style="font-size:22px;font-weight:700"><?= fmt_money($treso) ?></div></div>
</div>
<?php
$evol = db_select("SELECT DATE_FORMAT(e.date_ecriture,'%Y-%m') AS m, SUM(l.debit) AS debits, SUM(l.credit) AS credits FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id WHERE e.date_ecriture >= DATE_SUB(?, INTERVAL 11 MONTH) GROUP BY m ORDER BY m", [$dateFin]);
?>
<div class="card"><div class="card-header"><h3>Évolution 12 mois</h3></div>
<table><thead><tr><th>Mois</th><th>Débits</th><th>Crédits</th></tr></thead><tbody>
<?php foreach ($evol as $r): ?><tr><td><?= h($r['m']) ?></td><td><?= fmt_money($r['debits']) ?></td><td><?= fmt_money($r['credits']) ?></td></tr><?php endforeach; ?>
<?php if (!$evol): ?><tr><td colspan="3" style="text-align:center;padding:16px;color:var(--text3)">Aucun mouvement.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($tab === 'journaux'):
  $journal = get_str('journal') ?: 'CA';
  $rows = db_select("SELECT e.*, u.nom AS user_nom FROM compta_ecritures e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id JOIN compta_journaux j ON j.id=e.journal_id WHERE j.code=? AND e.date_ecriture BETWEEN ? AND ? ORDER BY e.date_ecriture DESC, e.id DESC LIMIT 200", [$journal, $dateDebut, $dateFin]);
?>
<?php if (can('compta.saisir')): ?>
<button class="btn btn-blue btn-sm" onclick="document.getElementById('modal-saisie').style.display='flex'" style="margin-bottom:12px">+ Nouvelle saisie manuelle</button>
<div id="modal-saisie" class="modal-overlay" style="display:none;align-items:center;justify-content:center;z-index:300" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(640px,95vw);padding:24px">
    <h3 style="margin-bottom:16px">Saisie manuelle (partie double)</h3>
    <form method="POST"><?= csrf_field() ?>
      <input type="hidden" name="action" value="saisie_manuelle">
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required style="flex:1">
        <select name="journal" style="flex:1"><?php foreach (['VTE','ACH','BQ','CA','OD'] as $j): ?><option value="<?= $j ?>"><?= $j ?></option><?php endforeach; ?></select>
      </div>
      <input name="libelle" placeholder="Libellé de l'écriture" required style="width:100%;margin-bottom:12px">
      <table style="width:100%"><thead><tr><th>Compte</th><th>Débit</th><th>Crédit</th><th>Tiers</th></tr></thead><tbody id="saisie-lignes">
        <tr><td><input name="compte[]" placeholder="571" style="width:80px"></td><td><input name="debit[]" type="number" step="0.01" style="width:100px"></td><td><input name="credit[]" type="number" step="0.01" style="width:100px"></td><td><input name="tiers[]" style="width:160px"></td></tr>
        <tr><td><input name="compte[]" placeholder="701" style="width:80px"></td><td><input name="debit[]" type="number" step="0.01" style="width:100px"></td><td><input name="credit[]" type="number" step="0.01" style="width:100px"></td><td><input name="tiers[]" style="width:160px"></td></tr>
      </tbody></table>
      <button type="button" class="btn btn-ghost btn-sm" onclick="const t=document.getElementById('saisie-lignes'); t.insertAdjacentHTML('beforeend', t.rows[0].outerHTML)">+ Ligne</button>
      <div style="text-align:right;margin-top:16px"><button type="submit" class="btn btn-blue">Valider l'écriture</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<form method="GET" style="margin-bottom:12px"><input type="hidden" name="tab" value="journaux">
  <select name="journal" onchange="this.form.submit()" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
    <?php foreach (db_select("SELECT code, libelle FROM compta_journaux ORDER BY code") as $j): ?><option value="<?= h($j['code']) ?>" <?= $journal === $j['code'] ? 'selected' : '' ?>><?= h($j['code']) ?> — <?= h($j['libelle']) ?></option><?php endforeach; ?>
  </select>
</form>
<div class="card"><div class="card-header"><h3>Journal <?= h($journal) ?> (200 dernières écritures)</h3></div>
<table><thead><tr><th>Numéro</th><th>Date</th><th>Libellé</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Tiers</th><th>Source</th></tr></thead><tbody>
<?php foreach ($rows as $e):
  $lignes = db_select("SELECT l.*, c.numero AS compte, c.libelle AS compte_lib FROM compta_ecriture_lignes l JOIN compta_comptes c ON c.id=l.compte_id WHERE l.ecriture_id=?", [$e['id']]);
  foreach ($lignes as $l): ?>
  <tr><td><?= h($e['numero']) ?></td><td><?= fmt_date($e['date_ecriture']) ?></td><td><?= h($e['libelle']) ?></td><td><?= h($l['compte']) ?> <?= h($l['compte_lib']) ?></td><td><?= $l['debit'] > 0 ? fmt_money($l['debit']) : '' ?></td><td><?= $l['credit'] > 0 ? fmt_money($l['credit']) : '' ?></td><td><?= h($l['tiers_libelle'] ?? '') ?></td>
  <td><?php if ($e['source_table']): ?><a href="<?= APP_URL ?>/<?= secure_source_link($e['source_table'], (int)$e['source_id']) ?>"><?= h($e['source_table']) ?>#<?= (int)$e['source_id'] ?></a><?php else: ?>—<?php endif; ?></td></tr>
  <?php endforeach; ?>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;padding:16px;color:var(--text3)">Aucune écriture sur la période.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($tab === 'gl'):
  $compte = get_str('compte');
  $where = $compte ? "AND c.numero=?" : "";
  $params = array_merge([$dateDebut, $dateFin], $compte ? [$compte] : []);
  $mvt = db_select("SELECT e.date_ecriture, e.numero, e.libelle, c.numero AS compte, l.debit, l.credit FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.date_ecriture BETWEEN ? AND ? $where ORDER BY e.date_ecriture, e.id", $params);
  $solde = 0.0;
?>
<div class="card"><div class="card-header"><h3>Grand livre <?= $compte ? 'compte ' . h($compte) : '(tous comptes)' ?></h3></div>
<form method="GET" style="margin-bottom:8px"><input type="hidden" name="tab" value="gl">
  <input name="compte" value="<?= h($compte) ?>" placeholder="ex 571 (vide = tous)" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
  <button class="btn btn-sm btn-blue">Filtrer</button>
</form>
<table><thead><tr><th>Date</th><th>Numéro</th><th>Libellé</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Solde cumulé</th></tr></thead><tbody>
<?php foreach ($mvt as $r): $solde += (float)$r['debit'] - (float)$r['credit']; ?>
  <tr><td><?= fmt_date($r['date_ecriture']) ?></td><td><?= h($r['numero']) ?></td><td><?= h($r['libelle']) ?></td><td><?= h($r['compte']) ?></td><td><?= fmt_money($r['debit']) ?></td><td><?= fmt_money($r['credit']) ?></td><td><?= fmt_money($solde) ?></td></tr>
<?php endforeach; ?>
<?php if (!$mvt): ?><tr><td colspan="7" style="text-align:center;padding:16px;color:var(--text3)">Aucun mouvement.</td></tr><?php endif; ?>
</tbody></table>
<a href="?tab=gl&export=csv&du=<?= h($dateDebut) ?>&au=<?= h($dateFin) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
</div>

<?php elseif ($tab === 'balance'):
  $bal = db_select("SELECT c.numero, c.libelle, c.classe, SUM(l.debit) AS td, SUM(l.credit) AS tc FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.date_ecriture BETWEEN ? AND ? GROUP BY c.id ORDER BY c.numero", [$dateDebut, $dateFin]);
  $totD = 0; $totC = 0;
?>
<div class="card"><div class="card-header"><h3>Balance des comptes</h3></div>
<table><thead><tr><th>Compte</th><th>Libellé</th><th>Classe</th><th>Total débit</th><th>Total crédit</th><th>Solde débiteur</th><th>Solde créditeur</th></tr></thead><tbody>
<?php foreach ($bal as $b):
  $totD += (float)$b['td']; $totC += (float)$b['tc'];
  $sd = (float)$b['td'] - (float)$b['tc']; ?>
  <tr><td><?= h($b['numero']) ?></td><td><?= h($b['libelle']) ?></td><td><?= (int)$b['classe'] ?></td><td><?= fmt_money($b['td']) ?></td><td><?= fmt_money($b['tc']) ?></td><td><?= $sd > 0 ? fmt_money($sd) : '' ?></td><td><?= $sd < 0 ? fmt_money(-$sd) : '' ?></td></tr>
<?php endforeach; ?>
<tr style="font-weight:700;border-top:2px solid var(--border2)"><td colspan="3">TOTAUX</td><td><?= fmt_money($totD) ?></td><td><?= fmt_money($totC) ?></td><td colspan="2"><?= abs($totD - $totC) < 0.01 ? '✅ équilibré' : '❌ déséquilibré' ?></td></tr>
</tbody></table>
<a href="?tab=balance&export=csv&du=<?= h($dateDebut) ?>&au=<?= h($dateFin) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
</div>

<?php elseif ($tab === 'bilan'):
  $actif = db_select("SELECT c.numero, c.libelle, SUM(l.debit)-SUM(l.credit) AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe IN (2,3,5) AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut, $dateFin]);
  $passif = db_select("SELECT c.numero, c.libelle, SUM(l.credit)-SUM(l.debit) AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe IN (1,4) AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut, $dateFin]);
  $totA = 0; $totP = 0;
?>
<div class="card"><div class="card-header"><h3>Bilan</h3></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div><h4>Actif</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($actif as $a): $totA += (float)$a['solde']; ?><tr><td><?= h($a['numero']) ?></td><td><?= h($a['libelle']) ?></td><td><?= fmt_money($a['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total actif</td><td><?= fmt_money($totA) ?></td></tr>
  </tbody></table></div>
  <div><h4>Passif</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($passif as $p): $totP += (float)$p['solde']; ?><tr><td><?= h($p['numero']) ?></td><td><?= h($p['libelle']) ?></td><td><?= fmt_money($p['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total passif</td><td><?= fmt_money($totP) ?></td></tr>
  </tbody></table></div>
</div>
<div class="alert alert-<?= abs($totA - $totP) < 0.01 ? 'green' : 'red' ?>" style="margin-top:12px">Équilibre actif/passif : <?= abs($totA - $totP) < 0.01 ? '✅' : '❌ écart ' . fmt_money($totA - $totP) ?></div>
</div>

<?php elseif ($tab === 'cr'):
  $charges = db_select("SELECT c.numero, c.libelle, SUM(l.debit)-SUM(l.credit) AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=6 AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut, $dateFin]);
  $produits = db_select("SELECT c.numero, c.libelle, SUM(l.credit)-SUM(l.debit) AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=7 AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut, $dateFin]);
  $totC = 0; $totP = 0;
?>
<div class="card"><div class="card-header"><h3>Compte de résultat</h3></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div><h4>Charges (classe 6)</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($charges as $c): $totC += (float)$c['solde']; ?><tr><td><?= h($c['numero']) ?></td><td><?= h($c['libelle']) ?></td><td><?= fmt_money($c['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total charges</td><td><?= fmt_money($totC) ?></td></tr>
  </tbody></table></div>
  <div><h4>Produits (classe 7)</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($produits as $p): $totP += (float)$p['solde']; ?><tr><td><?= h($p['numero']) ?></td><td><?= h($p['libelle']) ?></td><td><?= fmt_money($p['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total produits</td><td><?= fmt_money($totP) ?></td></tr>
  </tbody></table></div>
</div>
<div class="alert alert-<?= ($totP - $totC) >= 0 ? 'green' : 'red' ?>" style="margin-top:12px">Résultat net = <?= fmt_money($totP - $totC) ?> (<?= ($totP - $totC) >= 0 ? 'bénéfice' : 'perte' ?>)</div>
<a href="?tab=cr&export=csv&du=<?= h($dateDebut) ?>&au=<?= h($dateFin) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
</div>

<?php elseif ($tab === 'plan' && can('compta.param_comptes')):
  $comptes = db_select("SELECT * FROM compta_comptes ORDER BY classe, numero");
  $types = ['actif','passif','charge','produit','tresorerie','tiers'];
?>
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
    <h3>Plan comptable SYSCOHADA</h3>
    <button class="btn btn-blue btn-sm" onclick="comptaCompteOpen(0)">+ Ajouter un compte</button>
  </div>
  <table><thead><tr><th>Numéro</th><th>Libellé</th><th>Classe</th><th>Type</th><th>Statut</th><th style="text-align:right">Actions</th></tr></thead><tbody>
  <?php foreach ($comptes as $c): ?>
    <tr>
      <td><strong style="font-variant-numeric:tabular-nums"><?= h($c['numero']) ?></strong></td>
      <td><?= h($c['libelle']) ?></td>
      <td><span class="badge badge-gray">Classe <?= (int)$c['classe'] ?></span></td>
      <td><span class="badge badge-blue"><?= h($c['type']) ?></span></td>
      <td><span class="badge <?= $c['statut'] === 'actif' ? 'badge-green' : 'badge-gray' ?>"><?= h($c['statut']) ?></span></td>
      <td style="text-align:right"><button class="btn btn-sm btn-ghost" onclick="comptaCompteOpen(<?= htmlspecialchars(json_encode(['id'=>(int)$c['id'],'numero'=>$c['numero'],'libelle'=>$c['libelle'],'classe'=>(int)$c['classe'],'type'=>$c['type'],'statut'=>$c['statut']], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES) ?>)">✏️ Modifier</button></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>

<form method="POST" id="form-compte"><?= csrf_field() ?>
  <input type="hidden" name="action" value="compte_save">
  <input type="hidden" name="id" id="cc-id" value="0">
  <div id="modal-compte" class="modal-overlay" style="display:none;align-items:center;justify-content:center;z-index:300" onclick="if(event.target===this)comptaCompteClose()">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(480px,95vw);padding:24px">
      <h3 id="cc-title" style="margin-bottom:16px">Ajouter un compte</h3>
      <div class="form-group"><label>Numéro</label><input name="numero" id="cc-numero" required placeholder="ex 706"></div>
      <div class="form-group"><label>Libellé</label><input name="libelle" id="cc-libelle" required placeholder="Libellé du compte"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Classe</label><select name="classe" id="cc-classe"><?php for ($k = 1; $k <= 8; $k++): ?><option value="<?= $k ?>">Classe <?= $k ?></option><?php endfor; ?></select></div>
        <div class="form-group"><label>Type</label><select name="type" id="cc-type"><?php foreach ($types as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-group"><label>Statut</label><select name="statut" id="cc-statut"><option value="actif">actif</option><option value="inactif">inactif</option></select></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px">
        <button type="button" class="btn btn-ghost" onclick="comptaCompteClose()">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer</button>
      </div>
    </div>
  </div>
</form>
<script>
function comptaCompteOpen(d){
  var data = (typeof d === 'object' && d) ? d : {id:0,numero:'',libelle:'',classe:1,type:'actif',statut:'actif'};
  document.getElementById('cc-title').textContent = data.id ? ('Modifier le compte ' + data.numero) : 'Ajouter un compte';
  document.getElementById('cc-id').value      = data.id || 0;
  document.getElementById('cc-numero').value  = data.numero || '';
  document.getElementById('cc-libelle').value = data.libelle || '';
  document.getElementById('cc-classe').value   = data.classe || 1;
  document.getElementById('cc-type').value     = data.type || 'actif';
  document.getElementById('cc-statut').value   = data.statut || 'actif';
  document.getElementById('modal-compte').style.display = 'flex';
}
function comptaCompteClose(){ document.getElementById('modal-compte').style.display = 'none'; }
</script>
<?php elseif ($tab === 'plan' && !can('compta.param_comptes')): ?>
<div class="alert alert-red">Vous n'avez pas la permission de modifier le plan comptable.</div>
<?php elseif ($tab === 'exercices'):
  $exs = db_select("SELECT ex.*, u.nom AS cloture_nom FROM compta_exercices ex LEFT JOIN utilisateurs u ON u.id=ex.cloture_par ORDER BY ex.annee DESC");
?>
<div class="card"><div class="card-header"><h3>Exercices comptables</h3></div>
<table><thead><tr><th>Année</th><th>Période</th><th>Statut</th><th>Clôturé par</th><th>Date clôture</th><th>Action</th></tr></thead><tbody>
<?php foreach ($exs as $ex): ?>
  <tr><td><?= (int)$ex['annee'] ?></td><td><?= fmt_date($ex['date_debut']) ?> → <?= fmt_date($ex['date_fin']) ?></td>
    <td><span class="badge <?= $ex['statut'] === 'ouvert' ? 'badge-green' : 'badge-gray' ?>"><?= h($ex['statut']) ?></span></td>
    <td><?= h($ex['cloture_nom'] ?? '—') ?></td><td><?= !empty($ex['date_cloture']) ? fmt_date($ex['date_cloture'], true) : '—' ?></td>
    <td><?php if ($ex['statut'] === 'ouvert' && can('compta.cloturer')): ?>
      <form method="POST" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="cloturer_exercice"><input type="hidden" name="exercice_id" value="<?= (int)$ex['id'] ?>">
        <button type="submit" class="btn btn-sm btn-ghost" onclick="return confirm('Clôturer l\'exercice <?= (int)$ex['annee'] ?> ? Aucune nouvelle écriture ne pourra y être ajoutée.')">Clôturer</button>
      </form>
    <?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';