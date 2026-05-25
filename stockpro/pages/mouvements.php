<?php
$page_title = 'Mouvements de stock';
$page_id = 'mouvements';
require_once '../includes/header.php';
requireAuth();
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $type = $_POST['type'];
    $allowed = ['entree'=>canDo('mouvement_entree'),'sortie'=>canDo('mouvement_sortie'),'ajustement'=>canDo('mouvement_ajustement'),'retour'=>canDo('mouvement_retour')];
    if ($allowed[$type] ?? false) {
        $pid  = (int)$_POST['produit_id'];
        $qte  = (int)$_POST['quantite'];
        $motif= trim($_POST['motif']);
        $prix = (float)($_POST['prix_unitaire'] ?? 0);
        $ref  = trim($_POST['reference_doc'] ?? '');
        if ($pid > 0 && $qte > 0) {
            try {
                $db->beginTransaction();
                $prod = $db->prepare("SELECT quantite FROM produits WHERE id=? AND actif=1 FOR UPDATE");
                $prod->execute([$pid]);
                $prod = $prod->fetch();
                if ($prod) {
                    $avant = $prod['quantite'];
                    if ($type === 'entree' || $type === 'retour') {
                        $db->prepare("UPDATE produits SET quantite = quantite + ? WHERE id=?")->execute([$qte, $pid]);
                        $apres = $avant + $qte;
                    } elseif ($type === 'sortie') {
                        if ($qte > $avant) { $db->rollBack(); $msg = '<div class="alert alert-danger">Stock insuffisant (disponible: '.$avant.')</div>'; }
                        else {
                            $db->prepare("UPDATE produits SET quantite = quantite - ? WHERE id=?")->execute([$qte, $pid]);
                            $apres = $avant - $qte;
                        }
                    } else { // ajustement
                        $apres = $qte;
                        $qte = abs($apres - $avant);
                        $db->prepare("UPDATE produits SET quantite=? WHERE id=?")->execute([$apres, $pid]);
                    }
                    if (empty($msg)) {
                        $db->prepare("INSERT INTO mouvements (produit_id,utilisateur_id,type,quantite,quantite_avant,quantite_apres,prix_unitaire,motif,reference_doc) VALUES(?,?,?,?,?,?,?,?,?)")
                           ->execute([$pid,$_SESSION['user']['id'],$type,$qte,$avant,$apres,$prix,$motif,$ref]);
                        $db->commit();
                        logAudit($db, 'create', 'mouvement', $db->lastInsertId(), ['type'=>$type,'produit_id'=>$pid,'quantite'=>$qte]);
                        $msg = '<div class="alert alert-success">Mouvement enregistré avec succès.</div>';
                    }
                } else {
                    $db->rollBack();
                }
            } catch (Exception $e) {
                $db->rollBack();
                $msg = '<div class="alert alert-danger">Erreur lors de l\'enregistrement du mouvement.</div>';
            }
        }
    }
}

$produit_filtre = (int)($_GET['produit'] ?? 0);
$type_f = $_GET['type'] ?? '';
$date_f = $_GET['date'] ?? '';
$page = max(1,(int)($_GET['p']??1));
$per = 20;

$where = "1=1";
$params = [];
if ($produit_filtre) { $where .= " AND m.produit_id=?"; $params[] = $produit_filtre; }
if ($type_f) { $where .= " AND m.type=?"; $params[] = $type_f; }
if ($date_f) { $where .= " AND DATE(m.created_at)=?"; $params[] = $date_f; }

$total_rows = $db->prepare("SELECT COUNT(*) FROM mouvements m WHERE $where");
$total_rows->execute($params);
$total_rows = $total_rows->fetchColumn();
$pages = ceil($total_rows/$per);
$offset = ($page-1)*$per;

$st = $db->prepare("SELECT m.*,p.nom as produit_nom,p.reference,p.unite,p.quantite as stock_actuel,u.prenom,u.nom as user_nom FROM mouvements m JOIN produits p ON m.produit_id=p.id JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE $where ORDER BY m.created_at DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$mouvements = $st->fetchAll();

$produits = $db->query("SELECT id,nom,reference,quantite,quantite_min,unite FROM produits WHERE actif=1 ORDER BY nom")->fetchAll();
$prod_sel = null;
if ($produit_filtre) {
    foreach ($produits as $p) { if ($p['id']==$produit_filtre) { $prod_sel=$p; break; } }
}

// Stats du jour
$today = date('Y-m-d');
$st_entrees = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM mouvements WHERE type IN ('entree','retour') AND DATE(created_at)=?");
$st_entrees->execute([$today]);
$entrees_auj = (int)$st_entrees->fetchColumn();

$st_sorties = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM mouvements WHERE type='sortie' AND DATE(created_at)=?");
$st_sorties->execute([$today]);
$sorties_auj = (int)$st_sorties->fetchColumn();

$st_ajust = $db->prepare("SELECT COUNT(*) FROM mouvements WHERE type='ajustement' AND DATE(created_at)=?");
$st_ajust->execute([$today]);
$ajust_auj = (int)$st_ajust->fetchColumn();

$ent = getEntreprise();
$devise = $ent['devise'] ?? 'FCFA';
?>

<?= $msg ?>

<!-- ========== STATS BAR ========== -->
<div class="mvt-stats">
  <div class="mvt-stat-card mvt-stat-entree">
    <div class="mvt-stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
    </div>
    <div class="mvt-stat-data">
      <div class="mvt-stat-number"><?= number_format($entrees_auj, 0, ',', ' ') ?></div>
      <div class="mvt-stat-label">Entrées aujourd'hui</div>
    </div>
  </div>
  <div class="mvt-stat-card mvt-stat-sortie">
    <div class="mvt-stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 1 3 5 7 9"/><path d="M21 11V9a4 4 0 0 0-4-4H3"/><polyline points="17 23 21 19 17 15"/><path d="M3 13v2a4 4 0 0 0 4 4h14"/></svg>
    </div>
    <div class="mvt-stat-data">
      <div class="mvt-stat-number"><?= number_format($sorties_auj, 0, ',', ' ') ?></div>
      <div class="mvt-stat-label">Sorties aujourd'hui</div>
    </div>
  </div>
  <div class="mvt-stat-card mvt-stat-ajust">
    <div class="mvt-stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
    </div>
    <div class="mvt-stat-data">
      <div class="mvt-stat-number"><?= $ajust_auj ?></div>
      <div class="mvt-stat-label">Ajustements</div>
    </div>
  </div>
  <div class="mvt-stat-card mvt-stat-net">
    <div class="mvt-stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="mvt-stat-data">
      <div class="mvt-stat-number" style="color:<?= ($entrees_auj-$sorties_auj)>=0?'var(--success)':'var(--danger)' ?>"><?= ($entrees_auj-$sorties_auj)>=0?'+':'' ?><?= number_format($entrees_auj-$sorties_auj, 0, ',', ' ') ?></div>
      <div class="mvt-stat-label">Solde net du jour</div>
    </div>
  </div>
</div>

<!-- ========== TOOLBAR ========== -->
<div class="mvt-toolbar">
  <div class="mvt-filters">
    <form method="GET" class="mvt-filters-form">
      <div class="mvt-filter-group">
        <select class="form-control form-select mvt-filter-select" name="type" onchange="this.form.submit()">
          <option value="">Tous les types</option>
          <option value="entree" <?= $type_f==='entree'?'selected':'' ?>>📥 Entrées</option>
          <option value="sortie" <?= $type_f==='sortie'?'selected':'' ?>>📤 Sorties</option>
          <option value="ajustement" <?= $type_f==='ajustement'?'selected':'' ?>>🔧 Ajustements</option>
          <option value="retour" <?= $type_f==='retour'?'selected':'' ?>>↩️ Retours</option>
        </select>
        <input type="date" class="form-control mvt-filter-date" name="date" value="<?= htmlspecialchars($date_f) ?>" placeholder="Date">
        <?php if ($produit_filtre): ?>
        <input type="hidden" name="produit" value="<?= $produit_filtre ?>">
        <?php endif; ?>
      </div>
      <?php if ($type_f||$date_f||$produit_filtre): ?>
      <a href="mouvements.php" class="mvt-clear-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Effacer les filtres
      </a>
      <?php endif; ?>
    </form>
    <div class="mvt-count"><?= $total_rows ?> mouvement(s)</div>
  </div>
  <div class="mvt-action-btns">
    <?php if (canDo('mouvement_entree')): ?>
    <button class="btn mvt-action-btn mvt-action-entree" onclick="openMvtModal('entree')">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
      Entrée
    </button>
    <?php endif; ?>
    <?php if (canDo('mouvement_sortie')): ?>
    <button class="btn mvt-action-btn mvt-action-sortie" onclick="openMvtModal('sortie')">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="7 1 3 5 7 9"/><path d="M21 11V9a4 4 0 0 0-4-4H3"/><polyline points="17 23 21 19 17 15"/><path d="M3 13v2a4 4 0 0 0 4 4h14"/></svg>
      Sortie
    </button>
    <?php endif; ?>
    <?php if (canDo('mouvement_ajustement')): ?>
    <button class="btn mvt-action-btn mvt-action-ajust" onclick="openMvtModal('ajustement')">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
      Ajustement
    </button>
    <?php endif; ?>
    <?php if (canDo('mouvement_retour')): ?>
    <button class="btn mvt-action-btn mvt-action-retour" onclick="openMvtModal('retour')">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
      Retour fournisseur
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if ($prod_sel): ?>
<div class="mvt-product-filter">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  Filtré par : <strong><?= htmlspecialchars($prod_sel['nom']) ?></strong>
  (stock : <?= $prod_sel['quantite'] ?> <?= $prod_sel['unite'] ?>)
</div>
<?php endif; ?>

<!-- ========== MOUVEMENT LIST ========== -->
<div class="card">
  <div style="overflow-x:auto">
    <?php if (empty($mouvements)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
      <h4>Aucun mouvement</h4>
      <p>Les entrées, sorties et ajustements apparaîtront ici.</p>
    </div>
    <?php else: ?>
    <table class="mvt-table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Produit</th>
          <th>Quantité</th>
          <th>Stock</th>
          <th>Par</th>
          <th>Date</th>
          <th>Détails</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $type_config = [
          'entree'     => ['label'=>'Entrée',     'icon'=>'📥', 'color'=>'#10b981', 'bg'=>'#ecfdf5'],
          'sortie'     => ['label'=>'Sortie',      'icon'=>'📤', 'color'=>'#ef4444', 'bg'=>'#fef2f2'],
          'ajustement' => ['label'=>'Ajustement',  'icon'=>'🔧', 'color'=>'#3b82f6', 'bg'=>'#eff6ff'],
          'retour'     => ['label'=>'Retour',       'icon'=>'↩️', 'color'=>'#f59e0b', 'bg'=>'#fffbeb'],
      ];
      foreach ($mouvements as $m):
        $tc = $type_config[$m['type']] ?? ['label'=>$m['type'],'icon'=>'🔄','color'=>'#6b7280','bg'=>'#f9fafb'];
        $is_pos = in_array($m['type'], ['entree','retour']);
      ?>
        <tr class="mvt-row" onclick="window.location='?produit=<?= $m['produit_id'] ?>&type=<?= urlencode($type_f) ?>&date=<?= urlencode($date_f) ?>'">
          <td>
            <span class="mvt-type-pill" style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>">
              <?= $tc['icon'] ?> <?= $tc['label'] ?>
            </span>
          </td>
          <td>
            <div class="mvt-prod-name"><?= htmlspecialchars(mb_substr($m['produit_nom'], 0, 35, 'UTF-8')) ?></div>
            <div class="mvt-prod-ref"><?= $m['reference'] ?></div>
          </td>
          <td>
            <span class="mvt-qty" style="color:<?= $is_pos ? 'var(--success)' : 'var(--danger)' ?>">
              <?= $is_pos ? '+' : '-' ?><?= $m['quantite'] ?>
              <small><?= $m['unite'] ?></small>
            </span>
          </td>
          <td>
            <div class="mvt-stock-flow">
              <span class="mvt-stock-num"><?= $m['quantite_avant'] ?></span>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="9 18 15 12 9 6"/></svg>
              <span class="mvt-stock-num mvt-stock-after" style="<?= $m['quantite_apres'] < $m['quantite_avant'] ? 'color:var(--danger)' : ($m['quantite_apres'] > $m['quantite_avant'] ? 'color:var(--success)' : '') ?>"><?= $m['quantite_apres'] ?></span>
            </div>
          </td>
          <td style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($m['prenom'].' '.mb_substr($m['user_nom'],0,1,'UTF-8').'.') ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td style="font-size:12px;color:var(--muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($m['motif'] ?: $m['reference_doc'] ?: '—') ?>
            <?php if ($m['prix_unitaire'] > 0): ?>
            <div style="font-weight:600;color:var(--text-2)"><?= formatMoney($m['prix_unitaire'] * $m['quantite']) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div class="mvt-pagination">
    <?php if ($page > 1): ?>
    <a href="?p=<?= $page-1 ?>&type=<?= urlencode($type_f) ?>&date=<?= urlencode($date_f) ?>&produit=<?= $produit_filtre ?>" class="mvt-page-btn">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <?php endif; ?>
    <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
    <a href="?p=<?= $i ?>&type=<?= urlencode($type_f) ?>&date=<?= urlencode($date_f) ?>&produit=<?= $produit_filtre ?>" class="mvt-page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
    <a href="?p=<?= $page+1 ?>&type=<?= urlencode($type_f) ?>&date=<?= urlencode($date_f) ?>&produit=<?= $produit_filtre ?>" class="mvt-page-btn">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ========== MODAL NOUVEAU MOUVEMENT ========== -->
<?php if (canDo('mouvement_entree') || canDo('mouvement_sortie') || canDo('mouvement_ajustement') || canDo('mouvement_retour')): ?>
<div class="modal-bg" id="modal-mouvement">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title" id="mvt-modal-title">Nouveau mouvement</div>
      <button class="modal-close" onclick="closeModal('modal-mouvement')">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <!-- TYPE BANNER -->
    <div class="mvt-type-banner" id="mvt-type-banner">
      <span class="mvt-type-banner-icon" id="mvt-type-banner-icon">📥</span>
      <span class="mvt-type-banner-label" id="mvt-type-banner-label">Entrée</span>
    </div>
    <form method="POST" id="mvt-form">
      <?= csrf_field() ?>
      <input type="hidden" name="type" id="mvt-type-input" value="entree">

      <div class="modal-body">
        <!-- PRODUIT -->
        <div class="form-group">
          <label class="form-label">Produit *</label>
          <div class="mvt-product-select-wrap">
            <select class="form-control form-select mvt-product-select" name="produit_id" required id="sel-produit" onchange="updateStockPreview()">
              <option value="">Rechercher un produit...</option>
              <?php foreach ($produits as $p): ?>
              <option value="<?= $p['id'] ?>" data-stock="<?= $p['quantite'] ?>" data-unite="<?= $p['unite'] ?>" data-min="<?= $p['quantite_min'] ?>" <?= $prod_sel&&$prod_sel['id']==$p['id']?'selected':'' ?>>
                <?= htmlspecialchars($p['nom']) ?> — <?= $p['reference'] ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div id="stock-preview" class="mvt-stock-preview" style="display:none">
              <div class="mvt-stock-current">
                <span class="mvt-stock-label">Stock actuel</span>
                <span class="mvt-stock-val" id="stock-val">—</span>
              </div>
              <div class="mvt-stock-bar-wrap">
                <div class="mvt-stock-bar" id="stock-bar"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- QUANTITE + PRIX -->
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label" id="qty-label">Quantité *</label>
            <input class="form-control" type="number" name="quantite" id="qty-input" min="1" required placeholder="0" oninput="updateResultPreview()">
          </div>
          <div class="form-group">
            <label class="form-label">Prix unitaire (<?= $devise ?>)</label>
            <input class="form-control" type="number" name="prix_unitaire" id="prix-input" step="1" placeholder="Optionnel" oninput="updateResultPreview()">
          </div>
        </div>

        <!-- RESULTAT PREVIEW -->
        <div id="result-preview" class="mvt-result-preview" style="display:none">
          <div class="mvt-result-row">
            <span class="mvt-result-label">Stock avant</span>
            <span class="mvt-result-val" id="res-before">—</span>
          </div>
          <div class="mvt-result-row mvt-result-change">
            <span class="mvt-result-label" id="res-change-label">Quantité</span>
            <span class="mvt-result-val" id="res-change" style="color:var(--success)">—</span>
          </div>
          <div class="mvt-result-row">
            <span class="mvt-result-label">Stock après</span>
            <span class="mvt-result-val" id="res-after" style="font-weight:800;font-size:18px">—</span>
          </div>
        </div>

        <!-- DETAILS -->
        <div class="form-group">
          <label class="form-label">Référence document</label>
          <input class="form-control" name="reference_doc" placeholder="N° BL, facture, bon de commande...">
        </div>
        <div class="form-group">
          <label class="form-label">Motif / Commentaire</label>
          <textarea class="form-control" name="motif" rows="2" placeholder="Raison du mouvement..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-mouvement')">Annuler</button>
        <button type="submit" class="btn btn-primary" id="mvt-submit-btn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
          Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
/* ========== STATS BAR ========== */
.mvt-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
  animation: fadeSlideUp .4s ease both;
}
@keyframes fadeSlideUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
.mvt-stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: var(--shadow);
  transition: transform .2s, box-shadow .2s;
}
.mvt-stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.mvt-stat-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mvt-stat-icon svg { width: 22px; height: 22px; }
.mvt-stat-entree .mvt-stat-icon { background: #ecfdf5; }
.mvt-stat-entree .mvt-stat-icon svg { color: #10b981; }
.mvt-stat-entree .mvt-stat-number { color: #10b981; }
.mvt-stat-sortie .mvt-stat-icon { background: #fef2f2; }
.mvt-stat-sortie .mvt-stat-icon svg { color: #ef4444; }
.mvt-stat-sortie .mvt-stat-number { color: #ef4444; }
.mvt-stat-ajust .mvt-stat-icon { background: #eff6ff; }
.mvt-stat-ajust .mvt-stat-icon svg { color: #3b82f6; }
.mvt-stat-ajust .mvt-stat-number { color: #3b82f6; }
.mvt-stat-net .mvt-stat-icon { background: #f0fdf4; }
.mvt-stat-net .mvt-stat-icon svg { color: #10b981; }
.mvt-stat-number {
  font-family: 'Manrope', sans-serif;
  font-weight: 800;
  font-size: 26px;
  line-height: 1;
}
.mvt-stat-label { font-size: 13px; color: var(--text-2); margin-top: 4px; }

/* ========== TOOLBAR ========== */
.mvt-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.mvt-filters { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.mvt-filters-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.mvt-filter-select { width: 180px; }
.mvt-filter-date { width: 150px; }
.mvt-count { font-size: 13px; color: var(--muted); font-weight: 500; }
.mvt-clear-btn {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 13px; color: var(--danger); text-decoration: none;
  padding: 4px 10px; border-radius: 8px;
  transition: background .15s;
}
.mvt-clear-btn:hover { background: #fef2f2; }
.mvt-add-btn { white-space: nowrap; }
.mvt-action-btns {
  display: flex; gap: 8px; flex-wrap: wrap;
}
.mvt-action-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 10px;
  font-size: 13px; font-weight: 700;
  border: 2px solid transparent; cursor: pointer;
  transition: all .2s; white-space: nowrap;
  color: white;
}
.mvt-action-btn svg { flex-shrink: 0; }
.mvt-action-entree {
  background: #10b981; border-color: #10b981;
}
.mvt-action-entree:hover { background: #059669; border-color: #059669; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,.3); }
.mvt-action-sortie {
  background: #ef4444; border-color: #ef4444;
}
.mvt-action-sortie:hover { background: #dc2626; border-color: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,.3); }
.mvt-action-ajust {
  background: #3b82f6; border-color: #3b82f6;
}
.mvt-action-ajust:hover { background: #2563eb; border-color: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,.3); }
.mvt-action-retour {
  background: #f59e0b; border-color: #f59e0b; color: #1e1e1e;
}
.mvt-action-retour:hover { background: #d97706; border-color: #d97706; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245,158,11,.3); }

.mvt-product-filter {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--primary-light); color: var(--primary);
  padding: 8px 16px; border-radius: 10px;
  font-size: 13px; font-weight: 500;
  margin-bottom: 16px;
}
.mvt-product-filter svg { flex-shrink: 0; }

/* ========== TABLE ========== */
.mvt-table { width: 100%; border-collapse: collapse; }
.mvt-table th {
  text-align: left; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  color: var(--muted); padding: 14px 16px;
  border-bottom: 2px solid var(--border);
}
.mvt-table td {
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  font-size: 14px; vertical-align: middle;
}
.mvt-table tr.mvt-row {
  cursor: pointer; transition: background .12s;
}
.mvt-table tr.mvt-row:hover { background: var(--bg); }

/* Type pill */
.mvt-type-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 20px;
  font-size: 12px; font-weight: 700; white-space: nowrap;
}

/* Product cell */
.mvt-prod-name { font-weight: 600; font-size: 14px; line-height: 1.3; }
.mvt-prod-ref {
  font-size: 11px; color: var(--muted);
  background: var(--bg); padding: 1px 7px;
  border-radius: 4px; display: inline-block; margin-top: 2px;
  font-family: 'Manrope', sans-serif;
}

/* Qty */
.mvt-qty {
  font-family: 'Manrope', sans-serif;
  font-weight: 800; font-size: 16px;
  white-space: nowrap;
}
.mvt-qty small { font-weight: 400; font-size: 11px; opacity: .7; }

/* Stock flow in table */
.mvt-stock-flow {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--bg); padding: 5px 10px;
  border-radius: 8px; font-size: 13px;
}
.mvt-stock-num { font-weight: 600; color: var(--text-2); }
.mvt-stock-after { font-weight: 800; }
.mvt-stock-flow svg { color: var(--muted); flex-shrink: 0; }

/* Pagination */
.mvt-pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 6px; padding: 16px 0;
}
.mvt-page-btn {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 600;
  background: var(--card); border: 1px solid var(--border);
  color: var(--text-2); text-decoration: none;
  transition: all .15s;
}
.mvt-page-btn:hover, .mvt-page-btn.active {
  background: var(--primary); color: white; border-color: var(--primary);
}

/* ========== MODAL TYPE BANNER ========== */
.mvt-type-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 24px;
  font-weight: 700; font-size: 15px;
  transition: background .2s;
  margin-top: 16px;
  border-radius: 12px;
  margin-left: 24px;
  margin-right: 24px;
}
.mvt-type-banner-icon { font-size: 22px; }
.mvt-type-banner-label { color: white; }
.mvt-type-banner[data-type="entree"] { background: #10b981; }
.mvt-type-banner[data-type="sortie"] { background: #ef4444; }
.mvt-type-banner[data-type="ajustement"] { background: #3b82f6; }
.mvt-type-banner[data-type="retour"] { background: #f59e0b; }

/* ========== PRODUCT SELECT + STOCK PREVIEW ========== */
.mvt-product-select-wrap { position: relative; }
.mvt-product-select { padding-right: 40px; }

.mvt-stock-preview {
  display: flex; align-items: center; gap: 16px;
  background: var(--bg); border-radius: 12px;
  padding: 12px 16px; margin-top: 10px;
  animation: fadeSlideUp .25s ease both;
}
.mvt-stock-current { display: flex; flex-direction: column; gap: 2px; }
.mvt-stock-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
.mvt-stock-val { font-family: 'Manrope', sans-serif; font-weight: 800; font-size: 22px; color: var(--text); }
.mvt-stock-bar-wrap {
  flex: 1; height: 8px; background: var(--border);
  border-radius: 99px; overflow: hidden; position: relative;
}
.mvt-stock-bar {
  height: 100%; border-radius: 99px;
  transition: width .4s ease, background .4s ease;
  min-width: 4%;
}

/* ========== RESULT PREVIEW ========== */
.mvt-result-preview {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 4px;
  animation: fadeSlideUp .25s ease both;
}
.mvt-result-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 0;
}
.mvt-result-label { font-size: 13px; color: var(--muted); }
.mvt-result-val { font-family: 'Manrope', sans-serif; font-size: 16px; font-weight: 600; }
.mvt-result-change { border-top: 1px dashed var(--border); border-bottom: 1px dashed var(--border); padding: 8px 0; }

/* Dark mode */
[data-theme="dark"] .mvt-type-pill { background: var(--mvt-dark-bg, #1e293b); }

/* Responsive */
@media (max-width: 900px) {
  .mvt-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .mvt-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
  .mvt-stat-card { padding: 14px 16px; }
  .mvt-stat-number { font-size: 20px; }
  .mvt-toolbar { flex-direction: column; align-items: stretch; }
  .mvt-add-btn { width: 100%; justify-content: center; }
  .mvt-action-btns { width: 100%; }
  .mvt-action-btn { flex: 1; justify-content: center; font-size: 12px; padding: 8px 10px; }
  .mvt-filter-select, .mvt-filter-date { width: 100%; }
  .mvt-filters-form { flex-direction: column; }
  .mvt-table { font-size: 13px; }
  .mvt-table th, .mvt-table td { padding: 10px 10px; }
}
</style>

<script>
function updateStockPreview() {
  const sel = document.getElementById('sel-produit');
  const opt = sel.options[sel.selectedIndex];
  const preview = document.getElementById('stock-preview');
  const valEl = document.getElementById('stock-val');
  const barEl = document.getElementById('stock-bar');

  if (opt.value) {
    preview.style.display = 'flex';
    const stock = parseInt(opt.dataset.stock) || 0;
    const min = parseInt(opt.dataset.min) || 5;
    const unit = opt.dataset.unite || '';
    valEl.textContent = stock + ' ' + unit;

    const pct = min > 0 ? Math.min(100, Math.round(stock / min * 100)) : 100;
    const color = stock === 0 ? 'var(--danger)' : (stock <= min ? 'var(--warning)' : 'var(--success)');
    barEl.style.width = pct + '%';
    barEl.style.background = color;
  } else {
    preview.style.display = 'none';
  }
  updateResultPreview();
}

function updateResultPreview() {
  const sel = document.getElementById('sel-produit');
  const opt = sel.options[sel.selectedIndex];
  const qty = parseInt(document.getElementById('qty-input').value) || 0;
  const type = document.getElementById('mvt-type-input').value;
  const preview = document.getElementById('result-preview');
  const resBefore = document.getElementById('res-before');
  const resChange = document.getElementById('res-change');
  const resAfter = document.getElementById('res-after');
  const resChangeLabel = document.getElementById('res-change-label');
  const qtyLabel = document.getElementById('qty-label');

  if (!opt.value) { preview.style.display = 'none'; return; }

  const stock = parseInt(opt.dataset.stock) || 0;
  const unit = opt.dataset.unite || '';
  let before = stock;
  let after = stock;
  let change = qty;
  let changeStr = '';

  if (type === 'entree' || type === 'retour') {
    after = before + qty;
    changeStr = '+' + qty + ' ' + unit;
    resChange.style.color = 'var(--success)';
    qtyLabel.textContent = 'Quantité à ajouter *';
  } else if (type === 'sortie') {
    after = before - qty;
    changeStr = '-' + qty + ' ' + unit;
    resChange.style.color = 'var(--danger)';
    qtyLabel.textContent = 'Quantité à sortir *';
  } else {
    after = qty;
    change = Math.abs(after - before);
    changeStr = (after >= before ? '+' : '') + (after - before) + ' ' + unit;
    resChange.style.color = after >= before ? 'var(--success)' : 'var(--danger)';
    qtyLabel.textContent = 'Nouveau stock *';
  }

  if (qty <= 0 && type !== 'ajustement') { preview.style.display = 'none'; return; }

  preview.style.display = 'block';
  resBefore.textContent = before + ' ' + unit;
  resChange.textContent = changeStr;
  resAfter.textContent = after + ' ' + unit;
  resAfter.style.color = after <= 0 ? 'var(--danger)' : (after <= (parseInt(opt.dataset.min) || 5) ? 'var(--warning)' : 'var(--text)');

  // Update submit button
  const btn = document.getElementById('mvt-submit-btn');
  const typeLabels = {entree:'Entrée',sortie:'Sortie',ajustement:'Ajustement',retour:'Retour'};
  btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> Enregistrer ' + (typeLabels[type] || '');
}

function openMvtModal(type) {
  const typeConfig = {
    entree:      { label: 'Nouvelle entrée',      icon: '📥', color: '#10b981' },
    sortie:      { label: 'Nouvelle sortie',        icon: '📤', color: '#ef4444' },
    ajustement:  { label: 'Nouvel ajustement',      icon: '🔧', color: '#3b82f6' },
    retour:      { label: 'Retour fournisseur',     icon: '↩️', color: '#f59e0b' },
  };
  const cfg = typeConfig[type] || typeConfig.entree;

  document.getElementById('mvt-type-input').value = type;
  document.getElementById('mvt-modal-title').textContent = cfg.label;

  const banner = document.getElementById('mvt-type-banner');
  banner.dataset.type = type;
  document.getElementById('mvt-type-banner-icon').textContent = cfg.icon;
  document.getElementById('mvt-type-banner-label').textContent = cfg.label;

  // Reset form
  document.getElementById('mvt-form').reset();
  document.getElementById('mvt-type-input').value = type;
  document.getElementById('result-preview').style.display = 'none';
  document.getElementById('stock-preview').style.display = 'none';

  updateResultPreview();
  openModal('modal-mouvement');
}

updateStockPreview();
</script>

<?php require_once '../includes/footer.php'; ?>