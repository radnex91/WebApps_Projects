<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('magasin.voir');
$db     = getDB();
$uid    = currentUser()['id'];
$onglet = $_GET['onglet'] ?? 'stock';

// ════════════════════════════════════════════════════════════
// POST — actions de gestion (magasin.gerer)
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('magasin.gerer')) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Transfert Magasin → Pharmacie ───────────────────────
    if ($action === 'transfert') {
        $produits_ids = $_POST['produit_id'] ?? [];
        $quantites    = $_POST['quantite']    ?? [];
        $note         = trim($_POST['note'] ?? '');

        $lignesValides = [];
        for ($i = 0; $i < count($produits_ids); $i++) {
            $pid = (int)$produits_ids[$i];
            $qte = (int)$quantites[$i];
            if ($pid > 0 && $qte > 0) $lignesValides[] = [$pid, $qte];
        }

        if (!$lignesValides) {
            flash('Aucune ligne valide pour le transfert.', 'error');
            header('Location: ' . APP_URL . '/modules/magasin.php?onglet=transfert'); exit;
        }

        try {
            $db->beginTransaction();

            // Vérifier la disponibilité du stock magasin pour chaque ligne
            $stmtStock = $db->prepare("SELECT nom, stock_magasin FROM produits WHERE id=?");
            $insuffisants = [];
            foreach ($lignesValides as [$pid, $qte]) {
                $stmtStock->execute([$pid]);
                $p = $stmtStock->fetch();
                if (!$p) {
                    $insuffisants[] = "Produit #$pid introuvable";
                } elseif ((int)$p['stock_magasin'] < $qte) {
                    $insuffisants[] = e($p['nom']) . " (dispo: {$p['stock_magasin']}, demandé: $qte)";
                }
            }
            if ($insuffisants) {
                $db->rollBack();
                flash('Stock magasin insuffisant : ' . implode(' ; ', $insuffisants), 'error');
                header('Location: ' . APP_URL . '/modules/magasin.php?onglet=transfert'); exit;
            }

            // Créer l'entête de transfert
            $ref = genRef('TRF');
            $db->prepare("INSERT INTO transferts_magasin (reference,utilisateur_id,note) VALUES (?,?,?)")
               ->execute([$ref, $uid, $note]);
            $transfertId = (int)$db->lastInsertId();

            $stmtNom = $db->prepare("SELECT nom FROM produits WHERE id=?");
            $stmtDecMag   = $db->prepare("UPDATE produits SET stock_magasin = stock_magasin - ? WHERE id = ?");
            $stmtIncPharm = $db->prepare("UPDATE produits SET stock = stock + ? WHERE id = ?");
            $stmtLigne    = $db->prepare("INSERT INTO transfert_lignes (transfert_id,produit_id,produit_nom,quantite) VALUES (?,?,?,?)");
            $stmtMvtMag   = $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id,transfert_id) VALUES (?,'sortie',?,?,?,?)");
            $stmtMvtPharm = $db->prepare("INSERT INTO mouvements_stock (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)");

            foreach ($lignesValides as [$pid, $qte]) {
                $stmtNom->execute([$pid]);
                $nom = $stmtNom->fetchColumn() ?: ('Produit #' . $pid);

                // Décrémenter le magasin, incrémenter la pharmacie
                $stmtDecMag->execute([$qte, $pid]);
                $stmtIncPharm->execute([$qte, $pid]);

                // Ligne de transfert
                $stmtLigne->execute([$transfertId, $pid, $nom, $qte]);

                // Mouvement magasin (sortie)
                $stmtMvtMag->execute([$pid, $qte, 'Transfert ' . $ref . ' vers pharmacie', $uid, $transfertId]);
                // Mouvement pharmacie (entrée — traçabilité)
                $stmtMvtPharm->execute([$pid, $qte, 'Transfert ' . $ref . ' du magasin', $uid]);
            }

            $db->commit();
            auditLog('magasin.transfert', sprintf('Transfert %s : %d ligne(s) vers pharmacie', $ref, count($lignesValides)));
            flash('Transfert ' . $ref . ' effectué — stock pharmacie approvisionné.');
            header('Location: ' . APP_URL . '/modules/magasin.php?onglet=historique'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur lors du transfert : ' . $e->getMessage(), 'error');
            header('Location: ' . APP_URL . '/modules/magasin.php?onglet=transfert'); exit;
        }
    }

    // ── Réception / Ajustement manuel du magasin ───────────
    if ($action === 'reception') {
        $pid  = (int)($_POST['produit_id'] ?? 0);
        $qte  = (int)($_POST['quantite'] ?? 0);
        $type = ($_POST['type'] ?? 'entrée') === 'ajustement' ? 'ajustement' : 'entrée';
        $motif = trim($_POST['motif'] ?? '');

        if ($pid <= 0 || $qte === 0) {
            flash('Produit ou quantité invalide.', 'error');
            header('Location: ' . APP_URL . '/modules/magasin.php?onglet=reception'); exit;
        }

        try {
            $db->beginTransaction();
            // Quantité négative autorisée pour un ajustement de retrait
            $delta = $type === 'ajustement' ? $qte : abs($qte);
            if ($delta < 0) {
                // Vérifier qu'on ne descend pas sous zéro
                $stmtStock = $db->prepare("SELECT stock_magasin FROM produits WHERE id=?");
                $stmtStock->execute([$pid]);
                $cur = (int)$stmtStock->fetchColumn();
                if ($cur + $delta < 0) {
                    $db->rollBack();
                    flash('Ajustement impossible : stock magasin négatif.', 'error');
                    header('Location: ' . APP_URL . '/modules/magasin.php?onglet=reception'); exit;
                }
            }
            $db->prepare("UPDATE produits SET stock_magasin = stock_magasin + ? WHERE id = ?")->execute([$delta, $pid]);
            $libelle = $type === 'ajustement'
                ? ('Ajustement magasin' . ($motif ? ' — ' . $motif : ''))
                : ('Réception magasin' . ($motif ? ' — ' . $motif : ''));
            $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,?,?,?,?)")
               ->execute([$pid, $type, $delta, $libelle, $uid]);
            $db->commit();
            auditLog('magasin.' . $type, sprintf('Produit #%d, delta %+d (%s)', $pid, $delta, $libelle));
            flash('Mouvement magasin enregistré (' . $type . ').');
            header('Location: ' . APP_URL . '/modules/magasin.php?onglet=stock'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur : ' . $e->getMessage(), 'error');
            header('Location: ' . APP_URL . '/modules/magasin.php?onglet=reception'); exit;
        }
    }

    flash('Action inconnue.', 'error');
    header('Location: ' . APP_URL . '/modules/magasin.php'); exit;
}

// ════════════════════════════════════════════════════════════
// Données pour les vues
// ════════════════════════════════════════════════════════════
$produits = $db->query("
    SELECT p.id, p.nom, p.reference, p.stock, p.stock_magasin, p.seuil_magasin,
           c.nom AS cat
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    WHERE p.actif = 1
    ORDER BY p.nom
")->fetchAll();

$stats = [
    'refs'      => $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn(),
    'alerte'    => $db->query("SELECT COUNT(*) FROM produits WHERE stock_magasin <= seuil_magasin AND actif=1")->fetchColumn(),
    'total_mag' => $db->query("SELECT COALESCE(SUM(stock_magasin),0) FROM produits WHERE actif=1")->fetchColumn(),
    'total_ph'  => $db->query("SELECT COALESCE(SUM(stock),0) FROM produits WHERE actif=1")->fetchColumn(),
];

// Historique des transferts
$transferts = $db->query("
    SELECT t.*, u.prenom, u.nom AS u_nom,
           (SELECT COUNT(*) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS nb_lignes,
           (SELECT COALESCE(SUM(quantite),0) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS total_qte
    FROM transferts_magasin t
    LEFT JOIN utilisateurs u ON t.utilisateur_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 100
")->fetchAll();

// Mouvements magasin récents
$mouvements = $db->query("
    SELECT m.*, p.nom AS pnom, u.prenom, u.nom AS u_nom
    FROM mouvements_magasin m
    LEFT JOIN produits p ON m.produit_id = p.id
    LEFT JOIN utilisateurs u ON m.utilisateur_id = u.id
    ORDER BY m.created_at DESC
    LIMIT 100
")->fetchAll();

// Lignes des transferts (pour le détail dépliable)
$trfIds = array_column($transferts, 'id');
$trfLignes = [];
if ($trfIds) {
    $ph = implode(',', array_fill(0, count($trfIds), '?'));
    $stmtL = $db->prepare("SELECT * FROM transfert_lignes WHERE transfert_id IN ($ph) ORDER BY id");
    $stmtL->execute($trfIds);
    foreach ($stmtL->fetchAll() as $l) {
        $trfLignes[$l['transfert_id']][] = $l;
    }
}

layout_head('Magasin — dépôt central', 'magasin');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Références</div>
    <div class="stat-value c-blue"><?= fmtInt((int)$stats['refs']) ?></div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('truck',28) ?></div>
    <div class="stat-label">Total unités magasin</div>
    <div class="stat-value c-purple"><?= fmtInt((int)$stats['total_mag']) ?></div>
  </div>
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('pill',28) ?></div>
    <div class="stat-label">Total unités pharmacie</div>
    <div class="stat-value c-teal"><?= fmtInt((int)$stats['total_ph']) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Alertes magasin</div>
    <div class="stat-value c-gold"><?= fmtInt((int)$stats['alerte']) ?></div>
  </div>
</div>

<!-- Onglets -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-pad" style="padding:6px 12px;">
    <div class="flex gap-8" style="flex-wrap:wrap;">
      <a href="?onglet=stock"      class="btn btn-sm <?= $onglet==='stock'?'btn-primary':'btn-ghost' ?>"><?= icon('box',14) ?> Stock magasin</a>
      <a href="?onglet=transfert"  class="btn btn-sm <?= $onglet==='transfert'?'btn-primary':'btn-ghost' ?>"><?= icon('truck',14) ?> Transfert vers pharmacie</a>
      <?php if (hasPermission('magasin.gerer')): ?>
      <a href="?onglet=reception"  class="btn btn-sm <?= $onglet==='reception'?'btn-primary':'btn-ghost' ?>"><?= icon('plus',14) ?> Réception / Ajustement</a>
      <?php endif; ?>
      <a href="?onglet=historique" class="btn btn-sm <?= $onglet==='historique'?'btn-primary':'btn-ghost' ?>"><?= icon('history',14) ?> Historique</a>
    </div>
  </div>
</div>

<?php if ($onglet === 'stock'): ?>
<!-- ═══ Onglet STOCK MAGASIN ═══════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Stock du dépôt central (magasin)</div>
    <div class="search-box" style="min-width:240px;">
      <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
      <input type="text" id="search-mag" placeholder="Rechercher un médicament...">
    </div>
  </div>
  <div class="table-wrap">
    <table id="table-mag">
      <thead>
        <tr>
          <th>Médicament</th><th>Catégorie</th>
          <th style="text-align:right;">Stock magasin</th>
          <th style="text-align:right;">Seuil mag.</th>
          <th style="text-align:right;">Stock pharmacie</th>
          <th>État</th>
          <?php if (hasPermission('magasin.gerer')): ?><th style="text-align:right;">Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($produits as $p):
          $alerte = (int)$p['stock_magasin'] <= (int)$p['seuil_magasin'];
        ?>
        <tr data-nom="<?= e(strtolower($p['nom'] . ' ' . $p['reference'])) ?>">
          <td class="td-name"><?= e($p['nom']) ?>
            <?php if ($p['reference']): ?><div class="text-sm td-mono" style="color:var(--text3);"><?= e($p['reference']) ?></div><?php endif; ?>
          </td>
          <td class="text-sm"><?= e($p['cat'] ?? '—') ?></td>
          <td class="fw-mono text-right <?= $alerte ? 'c-gold' : '' ?>" style="text-align:right;"><?= fmtInt((int)$p['stock_magasin']) ?></td>
          <td class="fw-mono text-sm" style="text-align:right;color:var(--text3);"><?= fmtInt((int)$p['seuil_magasin']) ?></td>
          <td class="fw-mono" style="text-align:right;"><?= fmtInt((int)$p['stock']) ?></td>
          <td>
            <?php if ($alerte): ?>
              <span class="badge badge-gold"><?= (int)$p['stock_magasin'] === 0 ? 'Rupture mag.' : 'Stock bas' ?></span>
            <?php else: ?>
              <span class="badge badge-green">OK</span>
            <?php endif; ?>
          </td>
          <?php if (hasPermission('magasin.gerer')): ?>
          <td style="text-align:right;">
            <a href="?onglet=transfert&pid=<?= $p['id'] ?>" class="btn btn-ghost btn-xs"><?= icon('truck',13) ?> Transférer</a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$produits): ?>
        <tr><td colspan="7">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('box',36) ?></div>
            <div>Aucun produit</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.getElementById('search-mag').addEventListener('input', function(){
  var q = this.value.toLowerCase();
  document.querySelectorAll('#table-mag tbody tr').forEach(function(r){
    r.style.display = r.getAttribute('data-nom').indexOf(q) > -1 ? '' : 'none';
  });
});
</script>

<?php elseif ($onglet === 'transfert' && hasPermission('magasin.gerer')):
  $pidPreset = (int)($_GET['pid'] ?? 0);
?>
<!-- ═══ Onglet TRANSFERT VERS PHARMACIE ════════════════════ -->
<div class="card" style="max-width:820px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Transfert Magasin → Pharmacie</div>
    <span class="text-sm">Le stock magasin diminue, le stock pharmacie augmente.</span>
  </div>
  <div class="card-pad">
    <form method="POST" action="?onglet=transfert" id="trf-form">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="transfert">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text3);">Produits à transférer</div>
        <button type="button" class="btn btn-ghost btn-xs" onclick="addTrfLigne()"><?= icon('plus',13) ?> Ajouter une ligne</button>
      </div>
      <div id="trf-lignes">
        <div class="trf-ligne" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
          <select name="produit_id[]" style="flex:3;min-width:0;" onchange="updateDispo(this)">
            <option value="">— Sélectionner un produit —</option>
            <?php foreach ($produits as $p): ?>
            <option value="<?= $p['id'] ?>" data-dispo="<?= (int)$p['stock_magasin'] ?>" <?= $pidPreset===$p['id']?'selected':'' ?>><?= e($p['nom']) ?> (mag: <?= (int)$p['stock_magasin'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="quantite[]" placeholder="Qté" min="1" value="1" style="flex:1;min-width:0;width:90px;" oninput="checkQte(this)">
          <span class="trf-dispo text-sm" style="color:var(--text3);min-width:90px;"></span>
          <button type="button" class="btn btn-ghost btn-xs" onclick="this.parentElement.remove()" style="flex-shrink:0;">✕</button>
        </div>
      </div>
      <div class="form-group" style="margin-top:8px;">
        <label>Note (optionnel)</label>
        <input type="text" name="note" placeholder="Motif du transfert..." style="width:100%;">
      </div>
      <div class="modal-footer" style="padding:0;">
        <a href="?onglet=stock" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary"><?= icon('truck',14) ?> Valider le transfert</button>
      </div>
    </form>
  </div>
</div>
<script>
var trfProduits = <?= json_encode(array_map(function($p){ return ['id'=>(int)$p['id'],'nom'=>$p['nom'],'dispo'=>(int)$p['stock_magasin']]; }, $produits), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function updateDispo(sel) {
  var opt = sel.options[sel.selectedIndex];
  var span = sel.parentElement.querySelector('.trf-dispo');
  if (opt.value) {
    span.textContent = 'dispo: ' + opt.getAttribute('data-dispo');
  } else {
    span.textContent = '';
  }
  checkQte(sel.parentElement.querySelector('input[name="quantite[]"]'));
}
function checkQte(inp) {
  var sel = inp.parentElement.querySelector('select');
  var span = inp.parentElement.querySelector('.trf-dispo');
  if (!sel.value) return;
  var dispo = parseInt(sel.options[sel.selectedIndex].getAttribute('data-dispo'), 10);
  var q = parseInt(inp.value, 10) || 0;
  if (q > dispo) {
    inp.style.borderColor = 'var(--red)';
    span.style.color = 'var(--red)';
    span.textContent = '✕ dispo: ' + dispo + ' (insuffisant)';
  } else {
    inp.style.borderColor = '';
    span.style.color = 'var(--text3)';
    span.textContent = 'dispo: ' + dispo;
  }
}
function addTrfLigne() {
  var c = document.getElementById('trf-lignes');
  var proto = c.querySelector('.trf-ligne');
  var d = proto.cloneNode(true);
  d.querySelector('select').value = '';
  d.querySelector('input[name="quantite[]"]').value = '1';
  d.querySelector('.trf-dispo').textContent = '';
  d.querySelector('.trf-dispo').style.color = 'var(--text3)';
  d.querySelector('input[name="quantite[]"]').style.borderColor = '';
  c.appendChild(d);
}
document.querySelectorAll('#trf-lignes .trf-ligne').forEach(function(l){
  updateDispo(l.querySelector('select'));
});
document.getElementById('trf-form').addEventListener('submit', function(e){
  var lignes = document.querySelectorAll('#trf-lignes .trf-ligne');
  var ok = false, bad = false;
  lignes.forEach(function(l){
    var sel = l.querySelector('select');
    var q = parseInt(l.querySelector('input[name="quantite[]"]').value, 10) || 0;
    if (sel.value && q > 0) {
      ok = true;
      var dispo = parseInt(sel.options[sel.selectedIndex].getAttribute('data-dispo'), 10);
      if (q > dispo) bad = true;
    }
  });
  if (!ok) { e.preventDefault(); alert('Ajoutez au moins une ligne valide.'); return; }
  if (bad) { e.preventDefault(); alert('Une quantité dépasse le stock magasin disponible.'); return; }
  if (!confirm('Confirmer le transfert vers la pharmacie ?')) e.preventDefault();
});
</script>

<?php elseif ($onglet === 'reception' && hasPermission('magasin.gerer')): ?>
<!-- ═══ Onglet RÉCEPTION / AJUSTEMENT ══════════════════════ -->
<div class="card" style="max-width:620px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Réception / Ajustement magasin</div>
    <span class="text-sm">Entrée manuelle (hors commande) ou correction d'inventaire.</span>
  </div>
  <div class="card-pad">
    <form method="POST" action="?onglet=reception">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="reception">
      <div class="form-group">
        <label>Produit *</label>
        <select name="produit_id" required>
          <option value="">— Sélectionner —</option>
          <?php foreach ($produits as $p): ?>
          <option value="<?= $p['id'] ?>" data-stock="<?= (int)$p['stock_magasin'] ?>"><?= e($p['nom']) ?> (mag actuel: <?= (int)$p['stock_magasin'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Type</label>
          <select name="type">
            <option value="entrée">Entrée (réception)</option>
            <option value="ajustement">Ajustement (+/-)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Quantité *</label>
          <input type="number" name="quantite" required placeholder="ex: 50 (ou -5 pour un retrait d'ajustement)" min="-99999">
        </div>
      </div>
      <div class="form-group">
        <label>Motif</label>
        <input type="text" name="motif" placeholder="Origine de la réception / raison de l'ajustement" style="width:100%;">
      </div>
      <div class="modal-footer" style="padding:0;">
        <a href="?onglet=stock" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php elseif ($onglet === 'historique'): ?>
<!-- ═══ Onglet HISTORIQUE ══════════════════════════════════ -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <div class="card-title">Transferts Magasin → Pharmacie</div>
    <span class="text-sm"><?= count($transferts) ?> transfert(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Référence</th><th>Date</th><th>Opérateur</th>
        <th style="text-align:right;">Lignes</th><th style="text-align:right;">Unités</th><th>Note</th><th>Détail</th></tr>
      </thead>
      <tbody>
        <?php foreach ($transferts as $t): ?>
        <tr>
          <td class="td-mono"><?= e($t['reference']) ?></td>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
          <td class="text-sm"><?= e(trim($t['prenom'] . ' ' . $t['u_nom'])) ?: '—' ?></td>
          <td style="text-align:right;"><span class="badge badge-blue"><?= (int)$t['nb_lignes'] ?></span></td>
          <td class="fw-mono" style="text-align:right;"><?= fmtInt((int)$t['total_qte']) ?></td>
          <td class="text-sm"><?= e($t['note'] ?? '—') ?></td>
          <td>
            <button class="btn btn-ghost btn-xs" onclick="showTrfDetail(<?= (int)$t['id'] ?>)"><?= icon('eye',13) ?> Voir</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$transferts): ?>
        <tr><td colspan="7">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',36) ?></div>
            <div>Aucun transfert</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Mouvements du magasin</div>
    <span class="text-sm"><?= count($mouvements) ?> mouvement(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Produit</th><th>Type</th>
        <th style="text-align:right;">Quantité</th><th>Motif</th><th>Opérateur</th></tr>
      </thead>
      <tbody>
        <?php foreach ($mouvements as $m):
          $typeBadge = ['entrée'=>'badge-green','sortie'=>'badge-gold','ajustement'=>'badge-purple'];
        ?>
        <tr>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td class="td-name"><?= e($m['pnom'] ?? '—') ?></td>
          <td><span class="badge <?= $typeBadge[$m['type']] ?? 'badge-gray' ?>"><?= e($m['type']) ?></span></td>
          <td class="fw-mono" style="text-align:right;color:<?= $m['type']==='entrée'?'var(--teal2)':($m['type']==='sortie'?'var(--gold)':'var(--purple,#9b59b6)') ?>;"><?= ($m['quantite'] > 0 ? '+' : '') . fmtInt((int)$m['quantite']) ?></td>
          <td class="text-sm"><?= e($m['motif'] ?? '—') ?></td>
          <td class="text-sm"><?= e(trim($m['prenom'] . ' ' . $m['u_nom'])) ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$mouvements): ?>
        <tr><td colspan="6">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',36) ?></div>
            <div>Aucun mouvement</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal détail transfert -->
<div class="modal-overlay" id="modal-trf">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="trf-ref">Détail transfert</div>
      <button class="modal-close" onclick="closeModal('modal-trf')">✕</button>
    </div>
    <div id="trf-body" class="card-pad"></div>
    <div class="modal-footer" style="padding:0;">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modal-trf')">Fermer</button>
    </div>
  </div>
</div>
<script>
var trfLignes = <?= json_encode($trfLignes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var trfData = <?= json_encode(array_column($transferts, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
function showTrfDetail(tid) {
  var t = trfData[tid];
  if (!t) return;
  document.getElementById('trf-ref').textContent = t.reference;
  var lignes = trfLignes[tid] || [];
  var rows = lignes.length ? lignes.map(function(l){
    return '<tr style="border-bottom:1px solid var(--border)"><td style="padding:8px 7px;">'+(l.produit_nom||'—')+'</td><td style="padding:8px 7px;text-align:right;font-family:var(--font-mono,monospace);">'+l.quantite+'</td></tr>';
  }).join('') : '<tr><td colspan="2" style="padding:16px;text-align:center;color:var(--text3);">Aucune ligne</td></tr>';
  document.getElementById('trf-body').innerHTML =
    '<div style="font-size:12px;color:var(--text3);margin-bottom:10px;">' + new Date(t.created_at).toLocaleString('fr-FR') + (t.note ? ' — ' + t.note : '') + '</div>' +
    '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="border-bottom:1px solid var(--border)"><th style="padding:7px;text-align:left;color:var(--text3);font-size:10px;text-transform:uppercase;">Produit</th><th style="padding:7px;text-align:right;color:var(--text3);font-size:10px;text-transform:uppercase;">Qté</th></tr></thead><tbody>'+rows+'</tbody></table>';
  openModal('modal-trf');
}
</script>
<?php endif; ?>

<?php layout_foot(); ?>