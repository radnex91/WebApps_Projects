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
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
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
                header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
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
            header('Location: ' . url('magasin', ['onglet'=>'historique'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur lors du transfert : ' . $e->getMessage(), 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }
    }

    // ── Retour Pharmacie → Magasin (transfert inverse) ─────
    if ($action === 'retour') {
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
            flash('Aucune ligne valide pour le retour.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }

        try {
            $db->beginTransaction();

            // Vérifier la disponibilité du stock pharmacie pour chaque ligne
            $stmtStock = $db->prepare("SELECT nom, stock FROM produits WHERE id=?");
            $insuffisants = [];
            foreach ($lignesValides as [$pid, $qte]) {
                $stmtStock->execute([$pid]);
                $p = $stmtStock->fetch();
                if (!$p) {
                    $insuffisants[] = "Produit #$pid introuvable";
                } elseif ((int)$p['stock'] < $qte) {
                    $insuffisants[] = e($p['nom']) . " (pharmacie: {$p['stock']}, demandé: $qte)";
                }
            }
            if ($insuffisants) {
                $db->rollBack();
                flash('Stock pharmacie insuffisant : ' . implode(' ; ', $insuffisants), 'error');
                header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
            }

            $stmtNom        = $db->prepare("SELECT nom FROM produits WHERE id=?");
            $stmtDecPharm   = $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmtIncMag     = $db->prepare("UPDATE produits SET stock_magasin = stock_magasin + ? WHERE id = ?");
            $stmtMvtPharm   = $db->prepare("INSERT INTO mouvements_stock (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'sortie',?,?,?)");
            $stmtMvtMag     = $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)");

            $libelle = 'Retour pharmacie → magasin' . ($note ? ' — ' . $note : '');

            foreach ($lignesValides as [$pid, $qte]) {
                $stmtDecPharm->execute([$qte, $pid, $qte]);
                if ($stmtDecPharm->rowCount() === 0) {
                    // garde-fou concurrence
                    $db->rollBack();
                    flash('Stock pharmacie modifié entre-temps pour un produit. Réessayez.', 'error');
                    header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
                }
                $stmtIncMag->execute([$qte, $pid]);

                $stmtMvtPharm->execute([$pid, $qte, $libelle, $uid]);
                $stmtMvtMag->execute([$pid, $qte, $libelle, $uid]);
            }

            $db->commit();
            auditLog('magasin.retour', sprintf('Retour pharmacie→magasin : %d ligne(s)%s', count($lignesValides), $note ? ' (' . $note . ')' : ''));
            flash('Retour vers magasin effectué (' . count($lignesValides) . ' produit(s)).');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur lors du retour : ' . $e->getMessage(), 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
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
            header('Location: ' . url('magasin', ['onglet'=>'reception'])); exit;
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
                    header('Location: ' . url('magasin', ['onglet'=>'reception'])); exit;
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
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur : ' . $e->getMessage(), 'error');
            header('Location: ' . url('magasin', ['onglet'=>'reception'])); exit;
        }
    }

    flash('Action inconnue.', 'error');
    header('Location: ' . url('magasin')); exit;
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

<div class="stats-grid no-print" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
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
<div class="card no-print" style="margin-bottom:16px;">
  <div class="card-pad" style="padding:6px 12px;">
    <div class="flex gap-8" style="flex-wrap:wrap;">
      <a href="<?= url('magasin', ['onglet'=>'stock']) ?>"      class="btn btn-sm <?= $onglet==='stock'?'btn-primary':'btn-ghost' ?>"><?= icon('box',14) ?> Stock magasin</a>
      <?php if (hasPermission('magasin.gerer')): ?>
      <a href="<?= url('magasin', ['onglet'=>'reception']) ?>"  class="btn btn-sm <?= $onglet==='reception'?'btn-primary':'btn-ghost' ?>"><?= icon('plus',14) ?> Réception / Ajustement</a>
      <?php endif; ?>
      <a href="<?= url('magasin', ['onglet'=>'historique']) ?>" class="btn btn-sm <?= $onglet==='historique'?'btn-primary':'btn-ghost' ?>"><?= icon('history',14) ?> Historique</a>
    </div>
  </div>
</div>

<?php if ($onglet === 'stock'): ?>
<!-- ═══ Onglet STOCK MAGASIN ═══════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Stock du dépôt central (magasin)</div>
    <div class="flex gap-8" style="flex-wrap:wrap;align-items:center;">
      <div class="search-box" style="min-width:420px;flex:1;max-width:640px;">
        <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
        <input type="text" id="search-mag" placeholder="Rechercher un médicament...">
      </div>
      <?php if (hasPermission('magasin.gerer')): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="openTransfertModal()">
        <?= icon('truck',14) ?> Transfert vers pharmacie
      </button>
      <button type="button" class="btn btn-ghost btn-sm" onclick="openRetourModal()">
        <?= icon('refresh',14) ?> Retour vers magasin
      </button>
      <?php endif; ?>
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
            <button type="button" class="btn btn-ghost btn-xs" onclick="openTransfertModal(<?= (int)$p['id'] ?>)"><?= icon('truck',13) ?> Transférer</button>
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

<?php if (hasPermission('magasin.gerer')): ?>
<!-- ═══ Modale TRANSFERT VERS PHARMACIE (multi-sélection) ═══ -->
<div class="modal-overlay" id="modal-transfert">
  <div class="modal" style="width:920px;max-width:94vw;">
    <div class="modal-header" style="padding:22px 28px;">
      <div class="modal-title"><?= icon('truck',16) ?> Transfert Magasin → Pharmacie</div>
      <button class="modal-close" onclick="closeModal('modal-transfert')">✕</button>
    </div>
    <form method="POST" action="?onglet=stock" id="trf-form" onsubmit="return submitTransfert(event)">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="transfert">
      <div class="card-pad" style="padding:20px 28px;">
        <div class="flex-between" style="margin-bottom:16px;gap:12px;flex-wrap:wrap;">
          <div class="search-box" style="flex:1;min-width:220px;">
            <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
            <input type="text" id="trf-search" placeholder="Filtrer les produits..." oninput="filterTrfList()">
          </div>
          <label class="text-sm" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" id="trf-select-all" onchange="toggleAllTrf(this.checked)">
            <span>Tout sélectionner</span>
          </label>
        </div>
        <style>
          #trf-table th{padding:12px 14px;}
          #trf-table td{padding:11px 14px;}
          #trf-table tbody tr:hover{background:var(--glass);}
          #trf-table .trf-qte{padding:7px 10px;}
        </style>
        <div class="table-wrap" style="max-height:440px;overflow-y:auto;">
          <table id="trf-table">
            <thead>
              <tr>
                <th style="width:42px;"></th><th>Médicament</th>
                <th style="text-align:right;">Dispo magasin</th>
                <th style="text-align:right;">Dispo pharmacie</th>
                <th style="text-align:right;">Qté à transférer</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produits as $p):
                $dispo = (int)$p['stock_magasin'];
              ?>
              <tr data-nom="<?= e(strtolower($p['nom'] . ' ' . $p['reference'])) ?>" data-pid="<?= (int)$p['id'] ?>">
                <td style="text-align:center;">
                  <input type="checkbox" class="trf-check" data-pid="<?= (int)$p['id'] ?>" data-dispo="<?= $dispo ?>" onchange="onTrfCheck(this)" <?= $dispo <= 0 ? 'disabled' : '' ?>>
                </td>
                <td class="td-name"><?= e($p['nom']) ?>
                  <?php if ($p['reference']): ?><div class="text-sm td-mono" style="color:var(--text3);"><?= e($p['reference']) ?></div><?php endif; ?>
                </td>
                <td class="fw-mono text-right" style="text-align:right;<?= $dispo <= 0 ? 'color:var(--text3);' : '' ?>"><?= fmtInt($dispo) ?></td>
                <td class="fw-mono text-right" style="text-align:right;color:var(--text3);"><?= fmtInt((int)$p['stock']) ?></td>
                <td style="text-align:right;">
                  <input type="number" class="trf-qte" data-pid="<?= (int)$p['id'] ?>" data-dispo="<?= $dispo ?>" min="1" max="<?= max(1, $dispo) ?>" value="1" disabled style="width:80px;text-align:right;" oninput="onTrfQte(this)">
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$produits): ?>
              <tr><td colspan="5"><div class="empty">Aucun produit</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="trf-summary" class="text-sm" style="margin-top:14px;color:var(--text3);">0 produit sélectionné.</div>
        <div class="form-group" style="margin-top:14px;">
          <label>Note (optionnel)</label>
          <input type="text" name="note" placeholder="Motif du transfert..." style="width:100%;">
        </div>
      </div>
      <div class="modal-footer" style="padding:16px 28px;">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-transfert')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('truck',14) ?> Valider le transfert</button>
      </div>
    </form>
  </div>
</div>
<script>
function openTransfertModal(pid) {
  // réinitialiser la sélection
  document.querySelectorAll('#trf-table .trf-check').forEach(function(c){ c.checked = false; });
  document.querySelectorAll('#trf-table .trf-qte').forEach(function(q){ q.value = '1'; q.disabled = true; q.style.borderColor = ''; });
  document.getElementById('trf-select-all').checked = false;
  // pré-cocher le produit demandé (bouton « Transférer » d'une ligne)
  if (pid) {
    var cb = document.querySelector('#trf-table .trf-check[data-pid="' + pid + '"]');
    if (cb && !cb.disabled) {
      cb.checked = true; onTrfCheck(cb);
      var row = cb.closest('tr'); if (row) row.scrollIntoView({block:'center'});
    }
  }
  updateTrfSummary();
  openModal('modal-transfert');
}

function onTrfCheck(cb) {
  var qte = document.querySelector('#trf-table .trf-qte[data-pid="' + cb.getAttribute('data-pid') + '"]');
  if (qte) {
    qte.disabled = !cb.checked;
    if (cb.checked) { if (!qte.value) qte.value = '1'; qte.focus(); onTrfQte(qte); }
    else qte.style.borderColor = '';
  }
  updateTrfSummary();
}

function onTrfQte(inp) {
  var dispo = parseInt(inp.getAttribute('data-dispo'), 10);
  var q = parseInt(inp.value, 10) || 0;
  if (q > dispo) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Dépasse le stock disponible'); }
  else if (q <= 0) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Quantité invalide'); }
  else { inp.style.borderColor = ''; inp.setCustomValidity(''); }
  updateTrfSummary();
}

function updateTrfSummary() {
  var checks = document.querySelectorAll('#trf-table .trf-check:checked');
  var total = 0, bad = 0;
  checks.forEach(function(c){
    var qte = document.querySelector('#trf-table .trf-qte[data-pid="' + c.getAttribute('data-pid') + '"]');
    var q = parseInt(qte.value, 10) || 0;
    total += q;
    if (q <= 0 || q > parseInt(c.getAttribute('data-dispo'), 10)) bad++;
  });
  var s = document.getElementById('trf-summary');
  s.textContent = checks.length + ' produit(s) sélectionné(s) — ' + total + ' unité(s)';
  s.style.color = bad > 0 ? 'var(--red)' : 'var(--text3)';
}

function toggleAllTrf(checked) {
  document.querySelectorAll('#trf-table .trf-check').forEach(function(c){
    if (c.disabled) return;
    c.checked = checked; onTrfCheck(c);
  });
  updateTrfSummary();
}

function filterTrfList() {
  var q = document.getElementById('trf-search').value.toLowerCase();
  document.querySelectorAll('#trf-table tbody tr').forEach(function(r){
    r.style.display = r.getAttribute('data-nom').indexOf(q) > -1 ? '' : 'none';
  });
}

function submitTransfert(e) {
  e.preventDefault();
  var form = document.getElementById('trf-form');
  var checks = document.querySelectorAll('#trf-table .trf-check:checked');
  if (checks.length === 0) { alert('Sélectionnez au moins un produit à transférer.'); return false; }
  var bad = false;
  checks.forEach(function(c){
    var pid = c.getAttribute('data-pid');
    var dispo = parseInt(c.getAttribute('data-dispo'), 10);
    var qte = document.querySelector('#trf-table .trf-qte[data-pid="' + pid + '"]');
    var q = parseInt(qte.value, 10) || 0;
    if (q <= 0 || q > dispo) bad = true;
    else {
      // construire les champs envoyés au serveur (alignés produit_id[] / quantite[])
      var h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = 'produit_id[]'; h1.value = pid; form.appendChild(h1);
      var h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = 'quantite[]'; h2.value = q; form.appendChild(h2);
    }
  });
  if (bad) { alert('Une ou plusieurs quantités sont invalides ou dépassent le stock disponible.'); return false; }
  if (!confirm('Confirmer le transfert de ' + checks.length + ' produit(s) vers la pharmacie ?')) return false;
  form.submit();
  return false;
}
</script>
<?php endif; ?>

<?php if (hasPermission('magasin.gerer')): ?>
<!-- ═══ Modale RETOUR PHARMACIE → MAGASIN (multi-sélection) ═══ -->
<div class="modal-overlay" id="modal-retour">
  <div class="modal" style="width:920px;max-width:94vw;">
    <div class="modal-header" style="padding:22px 28px;">
      <div class="modal-title"><?= icon('refresh',16) ?> Retour Pharmacie → Magasin</div>
      <button class="modal-close" onclick="closeModal('modal-retour')">✕</button>
    </div>
    <form method="POST" action="?onglet=stock" id="ret-form" onsubmit="return submitRetour(event)">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="retour">
      <div class="card-pad" style="padding:20px 28px;">
        <div class="flex-between" style="margin-bottom:16px;gap:12px;flex-wrap:wrap;">
          <div class="search-box" style="flex:1;min-width:220px;">
            <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
            <input type="text" id="ret-search" placeholder="Filtrer les produits..." oninput="filterRetList()">
          </div>
          <label class="text-sm" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" id="ret-select-all" onchange="toggleAllRet(this.checked)">
            <span>Tout sélectionner</span>
          </label>
        </div>
        <style>
          #ret-table th{padding:12px 14px;}
          #ret-table td{padding:11px 14px;}
          #ret-table tbody tr:hover{background:var(--glass);}
          #ret-table .ret-qte{padding:7px 10px;}
        </style>
        <div class="table-wrap" style="max-height:440px;overflow-y:auto;">
          <table id="ret-table">
            <thead>
              <tr>
                <th style="width:42px;"></th><th>Médicament</th>
                <th style="text-align:right;">Stock pharmacie</th>
                <th style="text-align:right;">Qté à retourner</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produits as $p):
                $dispo = (int)$p['stock'];
              ?>
              <tr data-nom="<?= e(strtolower($p['nom'] . ' ' . $p['reference'])) ?>" data-pid="<?= (int)$p['id'] ?>">
                <td style="text-align:center;">
                  <input type="checkbox" class="ret-check" data-pid="<?= (int)$p['id'] ?>" data-dispo="<?= $dispo ?>" onchange="onRetCheck(this)" <?= $dispo <= 0 ? 'disabled' : '' ?>>
                </td>
                <td class="td-name"><?= e($p['nom']) ?>
                  <?php if ($p['reference']): ?><div class="text-sm td-mono" style="color:var(--text3);"><?= e($p['reference']) ?></div><?php endif; ?>
                </td>
                <td class="fw-mono text-right" style="text-align:right;<?= $dispo <= 0 ? 'color:var(--text3);' : '' ?>"><?= fmtInt($dispo) ?></td>
                <td style="text-align:right;">
                  <input type="number" class="ret-qte" data-pid="<?= (int)$p['id'] ?>" data-dispo="<?= $dispo ?>" min="1" max="<?= max(1, $dispo) ?>" value="1" disabled style="width:80px;text-align:right;" oninput="onRetQte(this)">
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$produits): ?>
              <tr><td colspan="4"><div class="empty">Aucun produit</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="ret-summary" class="text-sm" style="margin-top:14px;color:var(--text3);">0 produit sélectionné.</div>
        <div class="form-group" style="margin-top:14px;">
          <label>Note (optionnel)</label>
          <input type="text" name="note" placeholder="Motif du retour..." style="width:100%;">
        </div>
      </div>
      <div class="modal-footer" style="padding:16px 28px;">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-retour')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('refresh',14) ?> Valider le retour</button>
      </div>
    </form>
  </div>
</div>
<script>
function openRetourModal(pid) {
  document.querySelectorAll('#ret-table .ret-check').forEach(function(c){ c.checked = false; });
  document.querySelectorAll('#ret-table .ret-qte').forEach(function(q){ q.value = '1'; q.disabled = true; q.style.borderColor = ''; });
  document.getElementById('ret-select-all').checked = false;
  if (pid) {
    var cb = document.querySelector('#ret-table .ret-check[data-pid="' + pid + '"]');
    if (cb && !cb.disabled) {
      cb.checked = true; onRetCheck(cb);
      var row = cb.closest('tr'); if (row) row.scrollIntoView({block:'center'});
    }
  }
  updateRetSummary();
  openModal('modal-retour');
}

function onRetCheck(cb) {
  var qte = document.querySelector('#ret-table .ret-qte[data-pid="' + cb.getAttribute('data-pid') + '"]');
  if (qte) {
    qte.disabled = !cb.checked;
    if (cb.checked) { if (!qte.value) qte.value = '1'; qte.focus(); onRetQte(qte); }
    else qte.style.borderColor = '';
  }
  updateRetSummary();
}

function onRetQte(inp) {
  var dispo = parseInt(inp.getAttribute('data-dispo'), 10);
  var q = parseInt(inp.value, 10) || 0;
  if (q > dispo) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Dépasse le stock pharmacie'); }
  else if (q <= 0) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Quantité invalide'); }
  else { inp.style.borderColor = ''; inp.setCustomValidity(''); }
  updateRetSummary();
}

function updateRetSummary() {
  var checks = document.querySelectorAll('#ret-table .ret-check:checked');
  var total = 0, bad = 0;
  checks.forEach(function(c){
    var qte = document.querySelector('#ret-table .ret-qte[data-pid="' + c.getAttribute('data-pid') + '"]');
    var q = parseInt(qte.value, 10) || 0;
    total += q;
    if (q <= 0 || q > parseInt(c.getAttribute('data-dispo'), 10)) bad++;
  });
  var s = document.getElementById('ret-summary');
  s.textContent = checks.length + ' produit(s) sélectionné(s) — ' + total + ' unité(s)';
  s.style.color = bad > 0 ? 'var(--red)' : 'var(--text3)';
}

function toggleAllRet(checked) {
  document.querySelectorAll('#ret-table .ret-check').forEach(function(c){
    if (c.disabled) return;
    c.checked = checked; onRetCheck(c);
  });
  updateRetSummary();
}

function filterRetList() {
  var q = document.getElementById('ret-search').value.toLowerCase();
  document.querySelectorAll('#ret-table tbody tr').forEach(function(r){
    r.style.display = r.getAttribute('data-nom').indexOf(q) > -1 ? '' : 'none';
  });
}

function submitRetour(e) {
  e.preventDefault();
  var form = document.getElementById('ret-form');
  var checks = document.querySelectorAll('#ret-table .ret-check:checked');
  if (checks.length === 0) { alert('Sélectionnez au moins un produit à retourner.'); return false; }
  var bad = false;
  checks.forEach(function(c){
    var pid = c.getAttribute('data-pid');
    var dispo = parseInt(c.getAttribute('data-dispo'), 10);
    var qte = document.querySelector('#ret-table .ret-qte[data-pid="' + pid + '"]');
    var q = parseInt(qte.value, 10) || 0;
    if (q <= 0 || q > dispo) bad = true;
    else {
      var h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = 'produit_id[]'; h1.value = pid; form.appendChild(h1);
      var h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = 'quantite[]'; h2.value = q; form.appendChild(h2);
    }
  });
  if (bad) { alert('Une ou plusieurs quantités sont invalides ou dépassent le stock pharmacie.'); return false; }
  if (!confirm('Confirmer le retour de ' + checks.length + ' produit(s) vers le magasin ?')) return false;
  form.submit();
  return false;
}
</script>
<?php endif; ?>

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
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px;">
        <a href="<?= url('magasin', ['onglet'=>'stock']) ?>" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php elseif ($onglet === 'historique'): ?>
<!-- ═══ Onglet HISTORIQUE ══════════════════════════════════ -->
<div class="flex-between no-print" style="margin-bottom:16px;align-items:center;flex-wrap:wrap;gap:10px;">
  <div style="font-size:15px;font-weight:600;color:var(--text2);">Historique des mouvements Magasin ↔ Pharmacie</div>
  <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= icon('report',14) ?> Imprimer l'historique</button>
</div>

<div class="card no-print" style="margin-bottom:16px;">
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

<div class="card no-print">
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

<!-- ── Bloc impression A4 ── -->
<div id="print-history" class="print-only">
  <div style="text-align:center;margin-bottom:6px;">
    <div style="font-size:18px;font-weight:700;">Historique des mouvements — Magasin ↔ Pharmacie</div>
    <div style="font-size:12px;color:#555;">Édité le <?= date('d/m/Y à H:i') ?> — <?= e($appNom ?? 'PharmaCare') ?></div>
  </div>

  <h3 style="font-size:14px;margin:18px 0 8px;">Transferts Magasin → Pharmacie (<?= count($transferts) ?>)</h3>
  <table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead>
      <tr>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Référence</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Date</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Opérateur</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Lignes</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Unités</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Note</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($transferts as $t): ?>
      <tr>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($t['reference']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e(trim($t['prenom'] . ' ' . $t['u_nom'])) ?: '—' ?></td>
        <td style="border:1px solid #999;padding:5px 7px;text-align:right;"><?= (int)$t['nb_lignes'] ?></td>
        <td style="border:1px solid #999;padding:5px 7px;text-align:right;"><?= fmtInt((int)$t['total_qte']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($t['note'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$transferts): ?>
      <tr><td colspan="6" style="border:1px solid #999;padding:8px;text-align:center;">Aucun transfert</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 style="font-size:14px;margin:18px 0 8px;">Mouvements du magasin (<?= count($mouvements) ?>)</h3>
  <table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead>
      <tr>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Date</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Produit</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Type</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Quantité</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Motif</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Opérateur</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($mouvements as $m): ?>
      <tr>
        <td style="border:1px solid #999;padding:5px 7px;"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($m['pnom'] ?? '—') ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($m['type']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;text-align:right;"><?= ($m['quantite'] > 0 ? '+' : '') . fmtInt((int)$m['quantite']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($m['motif'] ?? '—') ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e(trim($m['prenom'] . ' ' . $m['u_nom'])) ?: '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$mouvements): ?>
      <tr><td colspan="6" style="border:1px solid #999;padding:8px;text-align:center;">Aucun mouvement</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
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
function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
var trfLignes = <?= json_encode($trfLignes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var trfData = <?= json_encode(array_column($transferts, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
function showTrfDetail(tid) {
  var t = trfData[tid];
  if (!t) return;
  document.getElementById('trf-ref').textContent = t.reference;
  var lignes = trfLignes[tid] || [];
  var rows = lignes.length ? lignes.map(function(l){
    return '<tr style="border-bottom:1px solid var(--border)"><td style="padding:8px 7px;">'+escHtml(l.produit_nom||'—')+'</td><td style="padding:8px 7px;text-align:right;font-family:var(--font-mono,monospace);">'+l.quantite+'</td></tr>';
  }).join('') : '<tr><td colspan="2" style="padding:16px;text-align:center;color:var(--text3);">Aucune ligne</td></tr>';
  document.getElementById('trf-body').innerHTML =
    '<div style="font-size:12px;color:var(--text3);margin-bottom:10px;">' + new Date(t.created_at).toLocaleString('fr-FR') + (t.note ? ' — ' + escHtml(t.note) : '') + '</div>' +
    '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="border-bottom:1px solid var(--border)"><th style="padding:7px;text-align:left;color:var(--text3);font-size:10px;text-transform:uppercase;">Produit</th><th style="padding:7px;text-align:right;color:var(--text3);font-size:10px;text-transform:uppercase;">Qté</th></tr></thead><tbody>'+rows+'</tbody></table>';
  openModal('modal-trf');
}
</script>
<?php endif; ?>

<?php layout_foot(); ?>