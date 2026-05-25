<?php
$page_title = 'Achats';
$page_id = 'achats';
require_once '../includes/header.php';
requireAuth();
if (!canDo('mouvement_*') && !canDo('produit_view')) { echo '<div class="alert alert-danger">Accès refusé.</div>'; require_once '../includes/footer.php'; exit; }
$db = getDB();
$msg = '';

// Fournisseurs pour le formulaire
$fournisseurs = $db->query("SELECT id,nom FROM fournisseurs WHERE actif=1 ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST' && canDo('mouvement_ajustement')) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $produit_id = (int)($_POST['produit_id'] ?? 0);
        $fournisseur_id = (int)($_POST['fournisseur_id'] ?? 0) ?: null;
        $quantite = (int)($_POST['quantite'] ?? 0);
        $prix_unit = (float)($_POST['prix_unitaire'] ?? 0);
        $motif = trim($_POST['motif'] ?? '');
        $ref_doc = trim($_POST['reference_doc'] ?? '');

        if ($produit_id && $quantite > 0) {
            try {
                $db->beginTransaction();
                $p = $db->prepare("SELECT quantite FROM produits WHERE id=? AND actif=1 FOR UPDATE");
                $p->execute([$produit_id]);
                $prod = $p->fetch();

                if ($prod) {
                    $avant = (int)$prod['quantite'];
                    $db->prepare("UPDATE produits SET quantite = quantite + ? WHERE id=?")->execute([$quantite, $produit_id]);
                    $apres = $avant + $quantite;

                    $db->prepare("INSERT INTO mouvements (produit_id,utilisateur_id,type,quantite,quantite_avant,quantite_apres,prix_unitaire,motif,reference_doc) VALUES(?,?,'entree',?,?,?,?,?,?)")
                       ->execute([$produit_id, $_SESSION['user']['id'], $quantite, $avant, $apres, $prix_unit ?: null, $motif ?: 'Réception stock', $ref_doc ?: null]);

                    // Alerte si produit était en rupture et maintenant en stock
                    if ($avant <= 0 && $apres > 0) {
                        $db->prepare("DELETE FROM alertes WHERE produit_id=? AND type='rupture'")->execute([$produit_id]);
                    }

                    $db->commit();
                    $msg = '<div class="alert alert-success">Réception enregistrée avec succès.</div>';
                } else {
                    $db->rollBack();
                    $msg = '<div class="alert alert-danger">Produit introuvable ou inactif.</div>';
                }
            } catch (Exception $e) {
                $db->rollBack();
                $msg = '<div class="alert alert-danger">Erreur lors de l\'enregistrement.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger">Veuillez sélectionner un produit et saisir une quantité.</div>';
        }
    }
}

// Filtres
$search = trim($_GET['s'] ?? '');
$type_f = $_GET['type'] ?? '';
$date_debut = $_GET['debut'] ?? date('Y-m-01');
$date_fin = $_GET['fin'] ?? date('Y-m-d');
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 20;

$where = "m.type='entree'";
$params = [];

if ($search) { $where .= " AND (p.nom LIKE ? OR p.reference LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($type_f === 'commande' || $type_f === 'retour') { $where .= " AND m.motif LIKE ?"; $params[] = '%' . $type_f . '%'; }
if ($date_debut) { $where .= " AND DATE(m.created_at) >= ?"; $params[] = $date_debut; }
if ($date_fin) { $where .= " AND DATE(m.created_at) <= ?"; $params[] = $date_fin; }

$total = $db->prepare("SELECT COUNT(*) FROM mouvements m JOIN produits p ON m.produit_id=p.id WHERE $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $per) ?: 1;
$offset = ($page - 1) * $per;

$st = $db->prepare("SELECT m.*, p.nom as produit_nom, p.reference, p.unite, f.nom as four_nom, u.prenom, u.nom as user_nom
    FROM mouvements m
    JOIN produits p ON m.produit_id=p.id
    LEFT JOIN fournisseurs f ON p.fournisseur_id=f.id
    JOIN utilisateurs u ON m.utilisateur_id=u.id
    WHERE $where ORDER BY m.created_at DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$achats = $st->fetchAll();

// Stats
$st_total = $db->prepare("SELECT COALESCE(SUM(m.quantite * COALESCE(m.prix_unitaire,0)),0) FROM mouvements m JOIN produits p ON m.produit_id=p.id WHERE m.type='entree' AND DATE(m.created_at) BETWEEN ? AND ?");
$st_total->execute([$date_debut, $date_fin]);
$total_achats = $st_total->fetchColumn();

$st_count = $db->prepare("SELECT COUNT(*) FROM mouvements WHERE type='entree' AND DATE(created_at) BETWEEN ? AND ?");
$st_count->execute([$date_debut, $date_fin]);
$nb_achats = $st_count->fetchColumn();

// Produits pour le formulaire
$produits = $db->query("SELECT id,reference,nom,unite,prix_achat,fournisseur_id FROM produits WHERE actif=1 ORDER BY nom")->fetchAll();
?>

<?= $msg ?>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:#dbeafe"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
    <div><div class="stat-val"><?= $nb_achats ?></div><div class="stat-label">Réceptions ce mois</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#dcfce7"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    <div><div class="stat-val" style="font-size:17px"><?= formatMoney($total_achats) ?></div><div class="stat-label">Montant total achats</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fef3c7"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
    <div><div class="stat-val"><?= count($fournisseurs) ?></div><div class="stat-label">Fournisseurs actifs</div></div>
  </div>
</div>

<!-- Filtres + Actions -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <div class="search-bar">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="form-control" name="s" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;width:200px">
    </div>
    <input type="date" name="debut" class="form-control" value="<?= htmlspecialchars($date_debut) ?>" style="width:150px">
    <input type="date" name="fin" class="form-control" value="<?= htmlspecialchars($date_fin) ?>" style="width:150px">
    <button class="btn btn-secondary" type="submit">Filtrer</button>
  </form>
  <?php if (canDo('mouvement_ajustement')): ?>
  <button class="btn btn-primary" onclick="openModal('modal-achat')">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle réception
  </button>
  <?php endif; ?>
</div>

<!-- Tableau des achats -->
<div class="card">
  <div style="overflow-x:auto">
    <?php if (empty($achats)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      <h4>Aucune réception trouvée</h4>
      <p>Aucun achat enregistré pour cette période.</p>
    </div>
    <?php else: ?>
    <table>
      <thead><tr><th>Date</th><th>Produit</th><th>Fournisseur</th><th>Qté</th><th>Prix unit.</th><th>Montant</th><th>Réf. doc</th><th>Motif</th><th>Par</th></tr></thead>
      <tbody>
      <?php foreach ($achats as $a):
        $montant = $a['quantite'] * ($a['prix_unitaire'] ?? 0);
      ?>
      <tr>
        <td style="white-space:nowrap;font-size:13px"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
        <td>
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($a['produit_nom']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= $a['reference'] ?></div>
        </td>
        <td style="font-size:13px"><?= htmlspecialchars($a['four_nom'] ?? '—') ?></td>
        <td style="font-weight:700;color:var(--success)"><?= $a['quantite'] ?> <small style="font-weight:400;color:var(--muted)"><?= $a['unite'] ?></small></td>
        <td style="font-size:13px"><?= $a['prix_unitaire'] ? formatMoney($a['prix_unitaire']) : '—' ?></td>
        <td style="font-weight:600"><?= $a['prix_unitaire'] ? formatMoney($montant) : '—' ?></td>
        <td style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($a['reference_doc'] ?? '—') ?></td>
        <td style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($a['motif'] ?? '—') ?></td>
        <td style="font-size:13px"><?= htmlspecialchars($a['prenom'].' '.$a['user_nom']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <span class="page-info"><?= $total ?> résultat(s) · Page <?= $page ?>/<?= $pages ?></span>
    <?php if ($page > 1): ?><a href="?s=<?= urlencode($search) ?>&debut=<?= $date_debut ?>&fin=<?= $date_fin ?>&p=<?= $page-1 ?>" class="page-btn">←</a><?php endif; ?>
    <?php for ($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
    <a href="?s=<?= urlencode($search) ?>&debut=<?= $date_debut ?>&fin=<?= $date_fin ?>&p=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?s=<?= urlencode($search) ?>&debut=<?= $date_debut ?>&fin=<?= $date_fin ?>&p=<?= $page+1 ?>" class="page-btn">→</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Modal Nouvelle réception -->
<div class="modal-bg" id="modal-achat">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Nouvelle réception</div>
      <button class="modal-close" onclick="closeModal('modal-achat')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Produit *</label>
          <select class="form-control form-select" name="produit_id" required id="select-produit">
            <option value="">— Sélectionner un produit —</option>
            <?php foreach ($produits as $p): ?>
            <option value="<?= $p['id'] ?>" data-prix="<?= $p['prix_achat'] ?>" data-unite="<?= $p['unite'] ?>"><?= htmlspecialchars($p['reference'].' — '.$p['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Quantité *</label>
            <input class="form-control" type="number" name="quantite" min="1" required placeholder="0" id="input-quantite">
          </div>
          <div class="form-group">
            <label class="form-label">Prix unitaire d'achat</label>
            <input class="form-control" type="number" step="0.01" name="prix_unitaire" id="input-prix" placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Fournisseur</label>
          <select class="form-control form-select" name="fournisseur_id">
            <option value="">— Aucun —</option>
            <?php foreach ($fournisseurs as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Référence document</label>
            <input class="form-control" name="reference_doc" placeholder="N° bon de commande...">
          </div>
          <div class="form-group">
            <label class="form-label">Motif</label>
            <input class="form-control" name="motif" placeholder="Réception stock, commande..." value="Réception stock">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-achat')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer la réception</button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-fill prix when product selected
document.getElementById('select-produit').addEventListener('change', function() {
  var opt = this.options[this.selectedIndex];
  if (opt.value) {
    document.getElementById('input-prix').value = opt.dataset.prix || '';
  }
});
</script>

<?php require_once '../includes/footer.php'; ?>