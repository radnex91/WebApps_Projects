<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
requirePermission('comptabilite.voir');
$db = getDB();
$action = $_GET['action'] ?? 'dashboard';

$moisLabels = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

// ── Navigation links (réutilisée partout) ──────────────────
ob_start(); ?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
  <a href="<?= url('comptabilite', ['action'=>'saisie']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">✍️</span> Saisie manuelle</a>
  <a href="<?= url('comptabilite', ['action'=>'journal']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">📖</span> Journal</a>
  <a href="<?= url('comptabilite', ['action'=>'balance']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">⚖️</span> Balance</a>
  <a href="<?= url('comptabilite', ['action'=>'resultat']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">📊</span> Compte de résultat</a>
  <a href="<?= url('comptabilite', ['action'=>'bilan']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">🏦</span> Bilan</a>
  <a href="<?= url('comptabilite', ['action'=>'grand-livre']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">🔍</span> Grand livre</a>
  <?php if (hasPermission('comptabilite.plan')): ?>
  <a href="<?= url('comptabilite', ['action'=>'cloture']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">🔒</span> Clôture</a>
  <a href="<?= url('comptabilite', ['action'=>'plan']) ?>" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:var(--radius-sm);background:var(--bg2);border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;"><span style="font-size:20px;">⚙️</span> Plan comptable</a>
  <?php endif; ?>
</div>
<?php $navLinks = ob_get_clean();

// ── POST : Saisie manuelle ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'saisie_save') {
    verifyCsrf();
    requirePermission('comptabilite.saisie');
    $date   = $_POST['date_ecriture'] ?? date('Y-m-d');
    $libelle = trim($_POST['libelle'] ?? '');
    $comptes = $_POST['compte_id'] ?? [];
    $debits  = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];

    if (!$libelle || !count($comptes)) {
        flash('Libellé obligatoire et au moins une ligne.', 'error');
        header('Location: ' . url('comptabilite', ['action'=>'saisie'])); exit;
    }

    $lignes = [];
    for ($i = 0; $i < count($comptes); $i++) {
        $cid = (int)$comptes[$i];
        if (!$cid) continue;
        $d = (float)str_replace(',', '.', $debits[$i] ?? 0);
        $c = (float)str_replace(',', '.', $credits[$i] ?? 0);
        if ($d <= 0 && $c <= 0) continue;
        $lignes[] = [$cid, $d, $c, ''];
    }

    if (count($lignes) < 2) {
        flash('Minimum 2 lignes (débit et crédit).', 'error');
        header('Location: ' . url('comptabilite', ['action'=>'saisie'])); exit;
    }

    try {
        $db->beginTransaction();
        ecritureCreate($db, $libelle, $date, $lignes, 'manuel', '', currentUser()['id']);
        $db->commit();
        flash('Écriture enregistrée avec succès.', 'success');
    } catch (Exception $e) {
        $db->rollBack();
        flash('Erreur : ' . $e->getMessage(), 'error');
    }
    header('Location: ' . url('comptabilite', ['action'=>'saisie'])); exit;
}

// ── POST : Nouveau compte comptable ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'plan_new') {
    verifyCsrf();
    requirePermission('comptabilite.plan');
    $compte     = trim($_POST['compte'] ?? '');
    $intitule   = trim($_POST['intitule'] ?? '');
    $classe     = (int)($_POST['classe'] ?? 0);
    $nature     = $_POST['nature'] ?? 'debit';
    $parentCode = trim($_POST['compte_parent'] ?? '');

    if (!$compte || !$intitule || !$classe) {
        flash('Compte, intitulé et classe requis.', 'error');
        header('Location: ' . url('comptabilite', ['action'=>'plan_edit'])); exit;
    }

    // Vérifier unicité
    $stmt = $db->prepare("SELECT id FROM plan_comptable WHERE compte = ?");
    $stmt->execute([$compte]);
    if ($stmt->fetch()) {
        flash('Ce code compte existe déjà.', 'error');
        header('Location: ' . url('comptabilite', ['action'=>'plan_edit'])); exit;
    }

    $parentId = null;
    if ($parentCode) {
        $stmtP = $db->prepare("SELECT id FROM plan_comptable WHERE compte = ?");
        $stmtP->execute([$parentCode]);
        $p = $stmtP->fetch();
        if ($p) $parentId = (int)$p['id'];
    }

    $db->prepare("INSERT INTO plan_comptable (compte, intitule, classe, nature, compte_parent) VALUES (?, ?, ?, ?, ?)")
       ->execute([$compte, $intitule, $classe, $nature, $parentId]);
    flash('Compte créé avec succès.', 'success');
    header('Location: ' . url('comptabilite', ['action'=>'plan'])); exit;
}

// ── POST : Modifier intitulé d'un compte ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'plan_update' && isset($_GET['id'])) {
    verifyCsrf();
    requirePermission('comptabilite.plan');
    $editId   = (int)$_GET['id'];
    $intitule = trim($_POST['intitule'] ?? '');
    if (!$intitule) {
        flash('L\'intitulé est requis.', 'error');
        header('Location: ' . url('comptabilite', ['action'=>'plan_edit','id'=>$editId])); exit;
    }
    $db->prepare("UPDATE plan_comptable SET intitule = ? WHERE id = ?")
       ->execute([$intitule, $editId]);
    flash('Intitulé mis à jour.', 'success');
    header('Location: ' . url('comptabilite', ['action'=>'plan'])); exit;
}

// ── POST : Clôture d'exercice (détermination du résultat) ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cloture_exec') {
    verifyCsrf();
    requirePermission('comptabilite.plan');
    $exId = (int)($_POST['exercice_id'] ?? 0);
    if (!$exId) {
        flash('Exercice invalide.', 'error');
        header('Location: ' . url('comptabilite', ['action'=>'cloture'])); exit;
    }
    try {
        $db->beginTransaction();
        $r = clotureExercice($db, $exId, currentUser()['id']);
        $db->commit();
        auditLog('comptabilite.cloture',
            sprintf('Clôture exercice #%d : produits %s, charges %s, résultat %s (%d lignes ; next=%s)',
                $exId, fmtMoney($r['produits']), fmtMoney($r['charges']), fmtMoney($r['resultat']),
                $r['nb_lignes'], $r['next_code'] ?? '—'),
            $exId, 'CLO-' . $exId);
        flash(sprintf('Exercice clôturé. Résultat : %s (%s). %s',
            fmtMoney($r['resultat']),
            $r['resultat'] >= 0 ? 'bénéfice' : 'perte',
            $r['next_code'] ? 'Exercice ' . $r['next_code'] . ' créé.' : 'Exercice suivant déjà existant.'),
            'success');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('Clôture impossible : ' . $e->getMessage(), 'error');
    }
    header('Location: ' . url('comptabilite', ['action'=>'cloture'])); exit;
}

// ── Exercice courant ───────────────────────────────────────
$exCourant = null;
$exAll = exercicesAll($db);
foreach ($exAll as $ex) {
    if (!$ex['cloture']) { $exCourant = $ex; break; }
}
$debutEx = $exCourant ? $exCourant['date_debut'] : date('Y-01-01');
$finEx   = $exCourant ? $exCourant['date_fin']   : date('Y-12-31');

// ══════════════════════════════════════════════════════════════
// VUES
// ══════════════════════════════════════════════════════════════

// ── Plan comptable ──────────────────────────────────────────
if ($action === 'plan'):
    requirePermission('comptabilite.plan');
    $plan = planComptableAll($db);
    $classes = [1=>'Capitaux', 2=>'Immobilisations', 3=>'Stocks', 4=>'Tiers', 5=>'Trésorerie', 6=>'Charges', 7=>'Produits'];
    $natureLabels = ['debit'=>'Débit', 'credit'=>'Crédit'];
    layout_head('Plan comptable', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Plan comptable OHADA</div>
    <a href="<?= url('comptabilite', ['action'=>'plan_edit']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Nouveau compte</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Compte</th><th>Intitulé</th><th>Classe</th><th>Nature</th><th style="width:40px;"></th></tr></thead>
      <tbody>
        <?php foreach ($plan as $c):
          $cl = (int)$c['classe'];
          $isParent = in_array(substr($c['compte'], -1), ['0', '']) && strlen($c['compte']) <= 3;
        ?>
        <tr>
          <td class="td-mono" style="font-weight:<?= $isParent ? '700' : '400' ?>;padding-left:<?= (strlen($c['compte'])-1)*12 ?>px;">
            <?= e($c['compte']) ?>
          </td>
          <td><?= e($c['intitule']) ?></td>
          <td><span class="badge badge-gray"><?= $classes[$cl] ?? $cl ?></span></td>
          <td><?= $natureLabels[$c['nature']] ?? $c['nature'] ?></td>
          <td>
            <a href="<?= url('comptabilite', ['action'=>'plan_edit','id'=>$c['id']]) ?>" class="btn btn-ghost btn-xs" title="Modifier l'intitulé"><?= icon('edit',13) ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); exit; endif;

// ── Nouveau compte / Modifier intitulé ────────────────────
if ($action === 'plan_edit'):
    requirePermission('comptabilite.plan');
    $editId  = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $editCpt = null;
    if ($editId) {
        $stmtE = $db->prepare("SELECT * FROM plan_comptable WHERE id = ?");
        $stmtE->execute([$editId]);
        $editCpt = $stmtE->fetch();
        if (!$editCpt) { flash('Compte introuvable.', 'error'); header('Location: ' . url('comptabilite', ['action'=>'plan'])); exit; }
    }
    $title  = $editCpt ? 'Modifier le compte' : 'Nouveau compte comptable';
    $actionUrl = $editCpt ? '?action=plan_update&id=' . $editId : '?action=plan_new';
    $classes = [''=>'—', 1=>'1 - Capitaux', 2=>'2 - Immobilisations', 3=>'3 - Stocks', 4=>'4 - Tiers', 5=>'5 - Trésorerie', 6=>'6 - Charges', 7=>'7 - Produits'];
    $natures = ['debit'=>'Débit (actif/charge)', 'credit'=>'Crédit (passif/produit)'];
    layout_head($title, 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card" style="max-width:600px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title"><?= e($title) ?></div>
    <a href="<?= url('comptabilite', ['action'=>'plan']) ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
  </div>
  <form method="POST" action="<?= $actionUrl ?>">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="form-grid">
      <div class="form-group">
        <label>Code compte *</label>
        <input type="text" name="compte" required placeholder="ex: 6012"
               value="<?= e($editCpt['compte'] ?? '') ?>"
               style="font-family:'DM Mono',monospace;"
               <?= $editCpt ? 'readonly style="font-family:&quot;DM Mono&quot;,monospace;background:#1e293b;color:#94a3b8;cursor:not-allowed;"' : '' ?>>
      </div>
      <div class="form-group">
        <label>Classe *</label>
        <select name="classe" required <?= $editCpt ? 'disabled' : '' ?>>
          <?php foreach ($classes as $k => $v): ?>
          <option value="<?= $k ?>" <?= $editCpt && (int)$editCpt['classe'] === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($editCpt): ?>
        <input type="hidden" name="classe" value="<?= (int)$editCpt['classe'] ?>">
        <?php endif; ?>
      </div>
      <div class="form-group full">
        <label>Intitulé *</label>
        <input type="text" name="intitule" required placeholder="ex: Achats de fournitures"
               value="<?= e($editCpt['intitule'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Nature</label>
        <select name="nature" <?= $editCpt ? 'disabled' : '' ?>>
          <?php foreach ($natures as $k => $v): ?>
          <option value="<?= $k ?>" <?= $editCpt && $editCpt['nature'] === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($editCpt): ?>
        <input type="hidden" name="nature" value="<?= e($editCpt['nature']) ?>">
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Compte parent</label>
        <input type="text" name="compte_parent" placeholder="ex: 601" style="font-family:'DM Mono',monospace;"
               <?= $editCpt ? 'readonly style="font-family:&quot;DM Mono&quot;,monospace;background:#1e293b;color:#94a3b8;cursor:not-allowed;"' : '' ?>
               value="<?= e($editCpt['compte_parent'] ?? '') ?>">
      </div>
    </div>
    <div class="modal-footer">
      <a href="<?= url('comptabilite', ['action'=>'plan']) ?>" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> <?= $editCpt ? 'Enregistrer' : 'Créer' ?></button>
    </div>
  </form>
</div>
<?php layout_foot(); exit; endif;

// ── Saisie manuelle ────────────────────────────────────────
if ($action === 'saisie'):
    requirePermission('comptabilite.saisie');
    $plan = planComptableAll($db);
    $lastEntries = $db->query("SELECT * FROM ecritures WHERE source = 'manuel' ORDER BY created_at DESC LIMIT 10")->fetchAll();
    layout_head('Saisie écriture', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="grid-2">
  <div class="card">
    <div class="card-header"><div class="card-title">Nouvelle écriture</div></div>
    <form method="POST" action="?action=saisie_save" id="saisie-form">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="form-grid" style="margin:0;">
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="date_ecriture" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Libellé *</label>
          <input type="text" name="libelle" required placeholder="Libellé de l'écriture">
        </div>
      </div>
      <div style="background:var(--bg2);border-radius:var(--radius-sm);padding:12px;margin:12px 0;">
        <div style="font-size:12px;color:var(--text3);margin-bottom:8px;">LIGNES D'ÉCRITURE</div>
        <div id="lignes-container">
          <div class="ligne" style="display:flex;gap:6px;margin-bottom:6px;">
            <select name="compte_id[]" required style="flex:2;min-width:0;">
              <option value="">— Choisir —</option>
              <?php foreach ($plan as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['compte'] . ' - ' . $c['intitule']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="debit[]" placeholder="Débit" step="1" min="0" style="flex:1;min-width:0;">
            <input type="number" name="credit[]" placeholder="Crédit" step="1" min="0" style="flex:1;min-width:0;">
            <button type="button" class="btn btn-ghost btn-xs" onclick="this.parentElement.remove()" style="flex-shrink:0;">✕</button>
          </div>
          <div class="ligne" style="display:flex;gap:6px;">
            <select name="compte_id[]" required style="flex:2;min-width:0;">
              <option value="">— Choisir —</option>
              <?php foreach ($plan as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['compte'] . ' - ' . $c['intitule']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="debit[]" placeholder="Débit" step="1" min="0" style="flex:1;min-width:0;">
            <input type="number" name="credit[]" placeholder="Crédit" step="1" min="0" style="flex:1;min-width:0;">
            <button type="button" class="btn btn-ghost btn-xs" onclick="this.parentElement.remove()" style="flex-shrink:0;">✕</button>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-xs" style="margin-top:6px;" onclick="addLigne()">
          <?= icon('plus',13) ?> Ajouter une ligne
        </button>
      </div>
      <div id="saisie-balance" style="font-size:14px;font-weight:600;padding:10px;border-radius:var(--radius-sm);margin-bottom:12px;background:var(--teal-dim);color:var(--teal2);text-align:center;">
        Total : <span id="total-debit">0</span> FCFA = <span id="total-credit">0</span> FCFA
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
        <?= icon('save',14) ?> Enregistrer l'écriture
      </button>
    </form>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Dernières saisies</div></div>
    <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
      <table>
        <thead><tr><th>Réf.</th><th>Date</th><th>Libellé</th></tr></thead>
        <tbody>
          <?php foreach ($lastEntries as $e): ?>
          <tr>
            <td class="td-mono"><?= e($e['reference']) ?></td>
            <td class="text-sm"><?= date('d/m/Y', strtotime($e['date_ecriture'])) ?></td>
            <td><?= e(mb_substr($e['libelle'], 0, 50)) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$lastEntries): ?>
          <tr><td colspan="3" class="empty">Aucune saisie manuelle</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function addLigne() {
  var c = document.getElementById('lignes-container');
  var d = document.createElement('div');
  d.className = 'ligne';
  d.style.cssText = 'display:flex;gap:6px;margin-bottom:6px;';
  d.innerHTML = `<select name="compte_id[]" required style="flex:2;min-width:0;">
    <option value="">— Choisir —</option><?php $__o = ''; foreach($plan as $c): $__o .= '<option value="'.(int)$c['id'].'">'.e($c['compte'].' - '.$c['intitule']).'</option>'; endforeach; echo $__o; ?>
  </select>
  <input type="number" name="debit[]" placeholder="Débit" step="1" min="0" style="flex:1;min-width:0;" oninput="calcBalance()">
  <input type="number" name="credit[]" placeholder="Crédit" step="1" min="0" style="flex:1;min-width:0;" oninput="calcBalance()">
  <button type="button" class="btn btn-ghost btn-xs" onclick="this.parentElement.remove();calcBalance();" style="flex-shrink:0;">✕</button>`;
  c.appendChild(d);
  calcBalance();
}
function calcBalance() {
  var d = 0, cr = 0;
  document.querySelectorAll('input[name="debit[]"]').forEach(function(el){ d += parseFloat(el.value) || 0; });
  document.querySelectorAll('input[name="credit[]"]').forEach(function(el){ cr += parseFloat(el.value) || 0; });
  document.getElementById('total-debit').textContent = d.toLocaleString('fr-FR');
  document.getElementById('total-credit').textContent = cr.toLocaleString('fr-FR');
  var bal = document.getElementById('saisie-balance');
  if (Math.abs(d - cr) < 0.01) {
    bal.style.background = 'var(--teal-dim)'; bal.style.color = 'var(--teal2)';
  } else {
    bal.style.background = 'var(--red-dim)'; bal.style.color = 'var(--red)';
  }
}
document.querySelectorAll('input[name="debit[]"], input[name="credit[]"]').forEach(function(el){
  el.addEventListener('input', calcBalance);
});
</script>
<?php layout_foot(); exit; endif;

// ── Journal ────────────────────────────────────────────────
if ($action === 'journal'):
    $debut = $_GET['debut'] ?? $debutEx;
    $fin   = $_GET['fin']   ?? $finEx;
    $src   = $_GET['source'] ?? '';
    $entries = journalGet($db, $debut, $fin, $src);
    $sources = [''=>'Toutes','vente'=>'Ventes','retour'=>'Retours','commande'=>'Commandes','stock'=>'Stock','caisse'=>'Caisse','cloture'=>'Clôtures','manuel'=>'Saisies manuelles'];
    layout_head('Journal comptable', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Journal général</div>
    <div class="flex gap-8">
      <form method="GET" style="display:flex;gap:6px;align-items:end;flex-wrap:wrap;">
        <input type="hidden" name="action" value="journal">
        <div class="form-group" style="margin:0;">
          <label>Du</label>
          <input type="date" name="debut" value="<?= e($debut) ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Au</label>
          <input type="date" name="fin" value="<?= e($fin) ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Source</label>
          <select name="source">
            <?php foreach ($sources as $k => $v): ?>
            <option value="<?= $k ?>" <?= $src===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-ghost btn-xs" style="margin-bottom:2px;"><?= icon('filter',13) ?> Filtrer</button>
      </form>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Réf.</th><th>Date</th><th>Libellé</th><th>Source</th><th>Par</th><th style="text-align:right;">Débit</th><th style="text-align:right;">Crédit</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($entries as $e):
          $lignes = ecritureLignes($db, (int)$e['id']);
          $dTotal = 0; $cTotal = 0;
          foreach ($lignes as $l) { $dTotal += (float)$l['debit']; $cTotal += (float)$l['credit']; }
        ?>
        <tr class="entry-row" onclick="toggleDetail(<?= (int)$e['id'] ?>)">
          <td class="td-mono"><?= e($e['reference']) ?></td>
          <td class="text-sm"><?= date('d/m/Y', strtotime($e['date_ecriture'])) ?></td>
          <td><?= e(mb_substr($e['libelle'], 0, 60)) ?></td>
          <td><span class="badge badge-gray"><?= e($sources[$e['source']] ?? $e['source']) ?></span></td>
          <td class="text-sm"><?= e(trim($e['prenom'].' '.$e['u_nom'])) ?></td>
          <td class="fw-mono" style="text-align:right;color:var(--red);"><?= $dTotal > 0 ? fmtMoney($dTotal) : '' ?></td>
          <td class="fw-mono" style="text-align:right;color:var(--teal2);"><?= $cTotal > 0 ? fmtMoney($cTotal) : '' ?></td>
          <td><span style="color:var(--text3);font-size:11px;">▼</span></td>
        </tr>
        <tr id="detail-<?= (int)$e['id'] ?>" style="display:none;">
          <td colspan="8" style="padding:0;">
            <div style="padding:8px 20px 12px;background:var(--bg2);">
              <table style="font-size:12px;width:auto;min-width:400px;">
                <thead><tr><th>Compte</th><th>Intitulé</th><th style="text-align:right;">Débit</th><th style="text-align:right;">Crédit</th></tr></thead>
                <tbody>
                  <?php foreach ($lignes as $l): ?>
                  <tr>
                    <td class="td-mono"><?= e($l['compte']) ?></td>
                    <td><?= e($l['intitule']) ?></td>
                    <td class="fw-mono" style="text-align:right;color:var(--red);"><?= (float)$l['debit'] > 0 ? fmtMoney((float)$l['debit']) : '' ?></td>
                    <td class="fw-mono" style="text-align:right;color:var(--teal2);"><?= (float)$l['credit'] > 0 ? fmtMoney((float)$l['credit']) : '' ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$entries): ?>
        <tr><td colspan="8" class="empty">Aucune écriture trouvée</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
function toggleDetail(id){
  var el=document.getElementById('detail-'+id);
  if(el) el.style.display=el.style.display==='none'?'':'none';
}
</script>
<?php layout_foot(); exit; endif;

// ── Grand-livre ────────────────────────────────────────────
if ($action === 'grand-livre'):
    $plan = planComptableAll($db);
    $compteId = (int)($_GET['compte_id'] ?? 0);
    $debut = $_GET['debut'] ?? $debutEx;
    $fin   = $_GET['fin']   ?? $finEx;
    $compteInfo = null;
    if ($compteId) {
        $st = $db->prepare("SELECT * FROM plan_comptable WHERE id = ?");
        $st->execute([$compteId]);
        $compteInfo = $st->fetch();
    }
    $lignes = $compteId ? grandLivreGet($db, $compteId, $debut, $fin) : [];
    layout_head('Grand livre', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Grand livre</div>
    <form method="GET" style="display:flex;gap:6px;align-items:end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="grand-livre">
      <div class="form-group" style="margin:0;">
        <label>Compte</label>
        <select name="compte_id" required>
          <option value="">— Choisir —</option>
          <?php foreach ($plan as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $compteId===(int)$c['id']?'selected':'' ?>><?= e($c['compte'].' - '.$c['intitule']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;"><label>Du</label><input type="date" name="debut" value="<?= e($debut) ?>"></div>
      <div class="form-group" style="margin:0;"><label>Au</label><input type="date" name="fin" value="<?= e($fin) ?>"></div>
      <button type="submit" class="btn btn-ghost btn-xs" style="margin-bottom:2px;"><?= icon('filter',13) ?> Voir</button>
    </form>
  </div>
  <?php if ($compteInfo): ?>
  <div style="padding:8px 16px;background:var(--bg2);border-bottom:1px solid var(--border);">
    <strong><?= e($compteInfo['compte']) ?></strong> — <?= e($compteInfo['intitule']) ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Réf.</th><th>Libellé</th><th>Source</th><th style="text-align:right;">Débit</th><th style="text-align:right;">Crédit</th><th style="text-align:right;">Solde</th></tr></thead>
      <tbody>
        <?php
        $solde = 0;
        $nature = $compteInfo['nature'] ?? 'debit';
        foreach ($lignes as $l):
          $d = (float)$l['debit'];
          $c = (float)$l['credit'];
          $solde += $d - $c;
        ?>
        <tr>
          <td class="text-sm"><?= date('d/m/Y', strtotime($l['date_ecriture'])) ?></td>
          <td class="td-mono"><?= e($l['reference']) ?></td>
          <td><?= e(mb_substr($l['ecr_libelle'], 0, 50)) ?></td>
          <td><span class="badge badge-gray"><?= e($l['source']) ?></span></td>
          <td class="fw-mono" style="text-align:right;color:var(--red);"><?= $d > 0 ? fmtMoney($d) : '' ?></td>
          <td class="fw-mono" style="text-align:right;color:var(--teal2);"><?= $c > 0 ? fmtMoney($c) : '' ?></td>
          <td class="fw-mono" style="text-align:right;font-weight:600;"><?= fmtMoney($solde) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$lignes): ?>
        <tr><td colspan="7" class="empty">Aucun mouvement pour ce compte</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:700;background:var(--bg2);">
          <td colspan="4">SOLDE FINAL</td>
          <td style="text-align:right;"><?= fmtMoney($solde) ?></td>
          <td></td><td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php layout_foot(); exit; endif;

// ── Balance ────────────────────────────────────────────────
if ($action === 'balance'):
    $debut = $_GET['debut'] ?? $debutEx;
    $fin   = $_GET['fin']   ?? $finEx;
    $balance = balanceGet($db, $debut, $fin);
    $classes = [1=>'Capitaux', 2=>'Immobilisations', 3=>'Stocks', 4=>'Tiers', 5=>'Trésorerie', 6=>'Charges', 7=>'Produits'];
    layout_head('Balance', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Balance générale</div>
    <form method="GET" style="display:flex;gap:6px;align-items:end;">
      <input type="hidden" name="action" value="balance">
      <div class="form-group" style="margin:0;"><label>Du</label><input type="date" name="debut" value="<?= e($debut) ?>"></div>
      <div class="form-group" style="margin:0;"><label>Au</label><input type="date" name="fin" value="<?= e($fin) ?>"></div>
      <button type="submit" class="btn btn-ghost btn-xs" style="margin-bottom:2px;"><?= icon('filter',13) ?> Filtrer</button>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Compte</th><th>Intitulé</th><th>Classe</th><th style="text-align:right;">Total Débit</th><th style="text-align:right;">Total Crédit</th><th style="text-align:right;">Solde</th></tr></thead>
      <tbody>
        <?php
        $gDebit = 0; $gCredit = 0;
        foreach ($balance as $c):
          $d = (float)$c['total_debit'];
          $cr = (float)$c['total_credit'];
          $solde = $d - $cr;
          $gDebit += $d; $gCredit += $cr;
        ?>
        <tr>
          <td class="td-mono"><?= e($c['compte']) ?></td>
          <td><?= e($c['intitule']) ?></td>
          <td><span class="badge badge-gray"><?= $classes[(int)$c['classe']] ?? $c['classe'] ?></span></td>
          <td class="fw-mono" style="text-align:right;"><?= $d > 0 ? fmtMoney($d) : '' ?></td>
          <td class="fw-mono" style="text-align:right;"><?= $cr > 0 ? fmtMoney($cr) : '' ?></td>
          <td class="fw-mono" style="text-align:right;font-weight:600;color:<?= $solde >= 0 ? 'var(--teal2)' : 'var(--red)' ?>;">
            <?= fmtMoney($solde) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:700;background:var(--bg2);">
          <td colspan="3">TOTAUX</td>
          <td style="text-align:right;"><?= fmtMoney($gDebit) ?></td>
          <td style="text-align:right;"><?= fmtMoney($gCredit) ?></td>
          <td style="text-align:right;"><?= fmtMoney($gDebit - $gCredit) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php layout_foot(); exit; endif;

// ── Compte de résultat ────────────────────────────────────
if ($action === 'resultat'):
    $debut = $_GET['debut'] ?? $debutEx;
    $fin   = $_GET['fin']   ?? $finEx;
    $resultat = compteResultat($db, $debut, $fin);
    $charges = []; $produits = [];
    $totalCharges = 0; $totalProduits = 0;
    foreach ($resultat as $c) {
        if ((int)$c['classe'] === 6) {
            $charges[] = $c;
            $totalCharges += (float)$c['total_debit'] - (float)$c['total_credit'];
        } else {
            $produits[] = $c;
            $totalProduits += (float)$c['total_credit'] - (float)$c['total_debit'];
        }
    }
    $resultatNet = $totalProduits - $totalCharges;
    layout_head('Compte de résultat', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Compte de résultat</div>
    <form method="GET" style="display:flex;gap:6px;align-items:end;">
      <input type="hidden" name="action" value="resultat">
      <div class="form-group" style="margin:0;"><label>Du</label><input type="date" name="debut" value="<?= e($debut) ?>"></div>
      <div class="form-group" style="margin:0;"><label>Au</label><input type="date" name="fin" value="<?= e($fin) ?>"></div>
      <button type="submit" class="btn btn-ghost btn-xs" style="margin-bottom:2px;"><?= icon('filter',13) ?> Filtrer</button>
    </form>
  </div>
  <div class="grid-2" style="gap:16px;padding:16px;">
    <div>
      <div style="font-size:12px;color:var(--red);font-weight:600;margin-bottom:8px;">CHARGES (Classe 6)</div>
      <table>
        <thead><tr><th>Compte</th><th>Intitulé</th><th style="text-align:right;">Montant</th></tr></thead>
        <tbody>
          <?php foreach ($charges as $c):
            $m = (float)$c['total_debit'] - (float)$c['total_credit'];
            if (abs($m) < 0.01) continue;
          ?>
          <tr>
            <td class="td-mono"><?= e($c['compte']) ?></td>
            <td><?= e($c['intitule']) ?></td>
            <td class="fw-mono" style="text-align:right;color:var(--red);"><?= fmtMoney($m) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:700;background:var(--red-dim);">
            <td colspan="2">TOTAL CHARGES</td>
            <td style="text-align:right;"><?= fmtMoney($totalCharges) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div>
      <div style="font-size:12px;color:var(--teal2);font-weight:600;margin-bottom:8px;">PRODUITS (Classe 7)</div>
      <table>
        <thead><tr><th>Compte</th><th>Intitulé</th><th style="text-align:right;">Montant</th></tr></thead>
        <tbody>
          <?php foreach ($produits as $c):
            $m = (float)$c['total_credit'] - (float)$c['total_debit'];
            if (abs($m) < 0.01) continue;
          ?>
          <tr>
            <td class="td-mono"><?= e($c['compte']) ?></td>
            <td><?= e($c['intitule']) ?></td>
            <td class="fw-mono" style="text-align:right;color:var(--teal2);"><?= fmtMoney($m) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:700;background:var(--teal-dim);">
            <td colspan="2">TOTAL PRODUITS</td>
            <td style="text-align:right;"><?= fmtMoney($totalProduits) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div style="padding:16px;border-top:2px solid var(--border);text-align:center;">
    <div style="font-size:14px;color:var(--text2);">RÉSULTAT NET</div>
    <div style="font-size:28px;font-weight:700;<?= $resultatNet >= 0 ? 'color:var(--teal2)' : 'color:var(--red)' ?>">
      <?= fmtMoney($resultatNet) ?> FCFA
    </div>
    <div style="font-size:12px;color:var(--text3);"><?= $resultatNet >= 0 ? 'Bénéfice' : 'Perte' ?></div>
  </div>
</div>
<?php layout_foot(); exit; endif;

// ── Bilan ──────────────────────────────────────────────────
if ($action === 'bilan'):
    $debut = $_GET['debut'] ?? $debutEx;
    $fin   = $_GET['fin']   ?? $finEx;
    $bilan = bilanGet($db, $debut, $fin);
    $diff = $bilan['actif']['total'] - $bilan['passif']['total'];
    layout_head('Bilan comptable', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Bilan comptable</div>
    <form method="GET" style="display:flex;gap:6px;align-items:end;">
      <input type="hidden" name="action" value="bilan">
      <div class="form-group" style="margin:0;"><label>Du</label><input type="date" name="debut" value="<?= e($debut) ?>"></div>
      <div class="form-group" style="margin:0;"><label>Au</label><input type="date" name="fin" value="<?= e($fin) ?>"></div>
      <button type="submit" class="btn btn-ghost btn-xs" style="margin-bottom:2px;"><?= icon('filter',13) ?> Filtrer</button>
    </form>
  </div>
  <div class="grid-2" style="gap:16px;padding:16px;">
    <div>
      <div style="font-size:12px;color:var(--teal2);font-weight:600;margin-bottom:8px;">ACTIF</div>
      <table>
        <thead><tr><th>Compte</th><th>Intitulé</th><th style="text-align:right;">Montant</th></tr></thead>
        <tbody>
          <?php foreach ($bilan['actif']['comptes'] as $c): ?>
          <tr>
            <td class="td-mono"><?= e($c['compte']) ?></td>
            <td><?= e($c['intitule']) ?></td>
            <td class="fw-mono" style="text-align:right;"><?= fmtMoney($c['montant']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$bilan['actif']['comptes']): ?>
          <tr><td colspan="3" class="empty">Aucun actif</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:700;background:var(--teal-dim);">
            <td colspan="2">TOTAL ACTIF</td>
            <td style="text-align:right;"><?= fmtMoney($bilan['actif']['total']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div>
      <div style="font-size:12px;color:var(--red);font-weight:600;margin-bottom:8px;">PASSIF</div>
      <table>
        <thead><tr><th>Compte</th><th>Intitulé</th><th style="text-align:right;">Montant</th></tr></thead>
        <tbody>
          <?php foreach ($bilan['passif']['comptes'] as $c): ?>
          <tr>
            <td class="td-mono"><?= e($c['compte']) ?></td>
            <td><?= e($c['intitule']) ?></td>
            <td class="fw-mono" style="text-align:right;"><?= fmtMoney($c['montant']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$bilan['passif']['comptes']): ?>
          <tr><td colspan="3" class="empty">Aucun passif</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:700;background:var(--red-dim);">
            <td colspan="2">TOTAL PASSIF</td>
            <td style="text-align:right;"><?= fmtMoney($bilan['passif']['total']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div style="padding:16px;border-top:2px solid var(--border);text-align:center;">
    <div style="font-size:14px;color:var(--text2);">ÉCART ACTIF - PASSIF</div>
    <div style="font-size:24px;font-weight:700;<?= abs($diff) < 0.01 ? 'color:var(--teal2)' : 'color:var(--gold)' ?>">
      <?= fmtMoney($diff) ?> FCFA
    </div>
    <div style="font-size:12px;color:var(--text3);"><?= abs($diff) < 0.01 ? 'Équilibré ✓' : 'Écart à vérifier' ?></div>
  </div>
</div>
<?php layout_foot(); exit; endif;

// ── Clôture d'exercice ─────────────────────────────────────
if ($action === 'cloture'):
    requirePermission('comptabilite.plan');
    $exList = exercicesAll($db);
    // Exercice ouvert (non clôturé) le plus ancien = candidat à la clôture
    $exCible = null;
    foreach ($exList as $ex) {
        if (!$ex['cloture']) { $exCible = $ex; break; }
    }
    // Résultat de l'exercice cible
    $resCible = null;
    if ($exCible) {
        $resCible = compteResultat($db, $exCible['date_debut'], $exCible['date_fin']);
        $tp = 0; $tc = 0;
        foreach ($resCible as $c) {
            if ((int)$c['classe'] === 6) $tc += (float)$c['total_debit'] - (float)$c['total_credit'];
            else                          $tp += (float)$c['total_credit'] - (float)$c['total_debit'];
        }
        $resNet = $tp - $tc;
    }
    layout_head('Clôture d\'exercice', 'comptabilite'); showFlash();
?>
<?= $navLinks ?>
<div class="card" style="max-width:720px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Clôture d'exercice — détermination du résultat</div>
  </div>
  <?php if ($exCible): ?>
    <div style="padding:16px;">
      <p style="color:var(--text2);margin-bottom:16px;">
        La clôture solde les comptes de charges (classe 6) et de produits (classe 7)
        dans le compte <strong>12 — Résultat de l'exercice</strong>, verrouille toutes
        les écritures de l'exercice et crée l'exercice suivant.
        <strong style="color:var(--red);">Action irréversible.</strong>
      </p>
      <table>
        <tbody>
          <tr><td style="color:var(--text3);">Exercice</td><td><strong><?= e($exCible['code']) ?></strong> — <?= e($exCible['libelle']) ?></td></tr>
          <tr><td style="color:var(--text3);">Période</td><td><?= date('d/m/Y', strtotime($exCible['date_debut'])) ?> → <?= date('d/m/Y', strtotime($exCible['date_fin'])) ?></td></tr>
          <tr><td style="color:var(--text3);">Total produits (classe 7)</td><td class="fw-mono" style="text-align:right;color:var(--teal2);"><?= fmtMoney($tp ?? 0) ?></td></tr>
          <tr><td style="color:var(--text3);">Total charges (classe 6)</td><td class="fw-mono" style="text-align:right;color:var(--red);"><?= fmtMoney($tc ?? 0) ?></td></tr>
          <tr style="background:var(--bg2);font-weight:700;">
            <td>Résultat net</td>
            <td class="fw-mono" style="text-align:right;color:<?= ($resNet ?? 0) >= 0 ? 'var(--teal2)' : 'var(--red)' ?>;">
              <?= fmtMoney($resNet ?? 0) ?> (<?= ($resNet ?? 0) >= 0 ? 'bénéfice' : 'perte' ?>)
            </td>
          </tr>
        </tbody>
      </table>
      <form method="POST" action="?action=cloture_exec" style="margin-top:20px;">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="exercice_id" value="<?= (int)$exCible['id'] ?>">
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;"
                onclick="return confirm('Confirmer la clôture de l\\'exercice <?= e($exCible['code']) ?> ? Cette action est irréversible.');">
          🔒 Clôturer l'exercice <?= e($exCible['code']) ?>
        </button>
      </form>
    </div>
  <?php else: ?>
    <div style="padding:24px;text-align:center;color:var(--text3);">
      Aucun exercice ouvert à clôturer. Toutes les périodes sont déjà clôturées.
    </div>
  <?php endif; ?>

  <div style="padding:12px 16px;border-top:1px solid var(--border);">
    <div style="font-size:12px;color:var(--text3);margin-bottom:8px;">EXERCICES</div>
    <table>
      <thead><tr><th>Code</th><th>Libellé</th><th>Période</th><th>État</th></tr></thead>
      <tbody>
        <?php foreach ($exList as $ex): ?>
        <tr>
          <td class="td-mono"><?= e($ex['code']) ?></td>
          <td><?= e($ex['libelle']) ?></td>
          <td class="text-sm"><?= date('d/m/Y', strtotime($ex['date_debut'])) ?> → <?= date('d/m/Y', strtotime($ex['date_fin'])) ?></td>
          <td>
            <?php if ($ex['cloture']): ?>
              <span class="badge badge-red">Clôturé</span>
            <?php else: ?>
              <span class="badge badge-green">Ouvert</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$exList): ?>
        <tr><td colspan="4" class="empty">Aucun exercice</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); exit; endif;

// ══════════════════════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════════════════════

$nbEcritures = $db->query("SELECT COUNT(*) FROM ecritures")->fetchColumn();
$nbEcrituresMois = $db->prepare("SELECT COUNT(*) FROM ecritures WHERE MONTH(date_ecriture)=MONTH(CURDATE()) AND YEAR(date_ecriture)=YEAR(CURDATE())");
$nbEcrituresMois->execute();
$nbEcrMois = (int)$nbEcrituresMois->fetchColumn();

$stmtS = $db->prepare("
    SELECT pc.classe, COALESCE(SUM(el.debit), 0) AS deb, COALESCE(SUM(el.credit), 0) AS cred
    FROM plan_comptable pc
    LEFT JOIN ecriture_lignes el ON el.compte_id = pc.id
    LEFT JOIN ecritures e ON el.ecriture_id = e.id
    WHERE pc.actif = 1 AND (e.date_ecriture IS NULL OR (e.date_ecriture >= ? AND e.date_ecriture <= ?))
    GROUP BY pc.classe ORDER BY pc.classe
");
$stmtS->execute([$debutEx, $finEx]);
$soldeClasses = $stmtS->fetchAll();
$classesLabels = [1=>'Capitaux', 2=>'Immobilisations', 3=>'Stocks', 4=>'Tiers', 5=>'Trésorerie', 6=>'Charges', 7=>'Produits'];

$lastEntries = $db->prepare("
    SELECT e.*, u.prenom, u.nom AS u_nom
    FROM ecritures e
    LEFT JOIN utilisateurs u ON e.utilisateur_id = u.id
    ORDER BY e.created_at DESC LIMIT 10
");
$lastEntries->execute();
$lastE = $lastEntries->fetchAll();

layout_head('Comptabilité', 'comptabilite');
showFlash();
?>
<?= $navLinks ?>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
  <div style="flex:1;min-width:160px;padding:12px 16px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);">
    <div style="font-size:12px;color:var(--text3);">📄 Écritures (total)</div>
    <div style="font-size:20px;font-weight:700;color:var(--teal2);"><?= $nbEcritures ?></div>
  </div>
  <div style="flex:1;min-width:160px;padding:12px 16px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);">
    <div style="font-size:12px;color:var(--text3);">📅 Ce mois-ci</div>
    <div style="font-size:20px;font-weight:700;color:var(--gold);"><?= $nbEcrMois ?></div>
  </div>
  <div style="flex:1;min-width:160px;padding:12px 16px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);">
    <div style="font-size:12px;color:var(--text3);">📕 Exercice</div>
    <div style="font-size:20px;font-weight:700;color:var(--blue);"><?= e($exCourant['code'] ?? date('Y')) ?></div>
  </div>
  <div style="flex:1;min-width:160px;padding:12px 16px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);">
    <div style="font-size:12px;color:var(--text3);">🗓️ Période</div>
    <div style="font-size:14px;font-weight:700;color:var(--purple,#9b59b6);"><?= date('d/m/Y', strtotime($debutEx)) ?> — <?= date('d/m/Y', strtotime($finEx)) ?></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><div class="card-title">Soldes par classe</div></div>
    <div class="card-pad" style="padding-bottom:8px;">
      <?php foreach ($soldeClasses as $s):
        $cl = (int)$s['classe'];
        $solde = (float)$s['deb'] - (float)$s['cred'];
      ?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
        <span><span class="badge badge-gray">Classe <?= $cl ?></span> <?= $classesLabels[$cl] ?? '' ?></span>
        <span class="fw-mono" style="font-weight:600;color:<?= $solde >= 0 ? 'var(--teal2)' : 'var(--red)' ?>;"><?= fmtMoney($solde) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Dernières écritures</div></div>
    <div class="table-wrap" style="max-height:280px;overflow-y:auto;">
      <table>
        <thead><tr><th>Date</th><th>Réf.</th><th>Libellé</th><th>Source</th></tr></thead>
        <tbody>
          <?php foreach ($lastE as $e): ?>
          <tr>
            <td class="text-sm"><?= date('d/m/Y', strtotime($e['date_ecriture'])) ?></td>
            <td class="td-mono"><?= e($e['reference']) ?></td>
            <td><?= e(mb_substr($e['libelle'], 0, 40)) ?></td>
            <td><span class="badge badge-gray"><?= e($e['source']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$lastE): ?>
          <tr><td colspan="4" class="empty">Aucune écriture</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layout_foot(); ?>
