<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
requirePermission('stock.ajuster');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// ── Recherche par code-barres / référence ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode_lookup'])) {
    verifyCsrf();
    $ref = trim($_POST['barcode_lookup'] ?? '');
    if ($ref !== '') {
        $stmtLookup = $db->prepare("SELECT id FROM produits WHERE reference = ? AND actif = 1");
        $stmtLookup->execute([$ref]);
        $found = $stmtLookup->fetch();
        if ($found) {
            header('Location: ' . APP_URL . '/modules/stock_ajust.php?id=' . (int)$found['id']); exit;
        } else {
            flash('Aucun produit trouvé avec la référence : ' . e($ref), 'error');
            header('Location: ' . APP_URL . '/modules/stock_ajust.php'); exit;
        }
    }
    header('Location: ' . APP_URL . '/modules/stock_ajust.php'); exit;
}

// ── Si aucun produit sélectionné, afficher le formulaire de recherche ──
if (!$id) {
    layout_head('Ajustement stock', 'stock');
    showFlash();
    ?>
    <div class="card" style="max-width:520px;margin:40px auto;">
      <div class="card-header">
        <div class="card-title">Recherche par code-barres</div>
        <a href="<?= APP_URL ?>/modules/stock.php" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <div class="card-pad">
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <div class="form-group">
            <label>Scanner ou saisir la référence</label>
            <div class="search-box" style="width:100%;border-color:var(--teal);box-shadow:0 0 10px var(--teal-glow);">
              <span style="color:var(--teal2);display:flex;"><?= icon('search',18) ?></span>
              <input type="text" name="barcode_lookup" placeholder="Scannez ou tapez la référence..." autofocus style="font-family:'DM Mono',monospace;letter-spacing:.5px;">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:12px;">
            <?= icon('search',14) ?> Rechercher
          </button>
        </form>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
          <div class="text-sm" style="color:var(--text3);margin-bottom:8px;">Ou chercher par nom :</div>
          <form method="GET" action="<?= APP_URL ?>/modules/stock.php" style="display:flex;gap:8px;">
            <input type="text" name="q" placeholder="Nom du médicament..." style="flex:1;">
            <button type="submit" class="btn btn-ghost"><?= icon('search',14) ?></button>
          </form>
        </div>
      </div>
    </div>
    <?php layout_foot(); exit;
}

$stmt = $db->prepare("SELECT p.*, c.nom AS cat FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.id=?");
$stmt->execute([$id]);
$produit = $stmt->fetch();
if (!$produit) { flash('Produit introuvable.','error'); header('Location: ' . APP_URL . '/modules/stock.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $type  = in_array($_POST['type'],['entrée','sortie','ajustement']) ? $_POST['type'] : 'ajustement';
    $qte   = (int)($_POST['quantite'] ?? 0);
    $motif = trim($_POST['motif'] ?? '');

    if ($qte <= 0) {
        flash('La quantité doit être supérieure à 0.', 'error');
    } else {
        switch ($type) {
            case 'entrée':     $newStock = $produit['stock'] + $qte;         break;
            case 'sortie':     $newStock = max(0, $produit['stock'] - $qte); break;
            case 'ajustement': $newStock = $qte;                              break;
            default:           $newStock = $produit['stock'];
        }
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE produits SET stock=? WHERE id=?")->execute([$newStock, $id]);
            $db->prepare("INSERT INTO mouvements_stock (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,?,?,?,?)")
               ->execute([$id, $type, $qte, $motif, currentUser()['id']]);
            $pa = (float)$produit['prix_achat'];
            if ($pa > 0 && $qte > 0) {
                $valeur = round($pa * $qte, 2);
                $compteStock  = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
                $compteVarStk = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
                if ($type === 'entrée' || ($type === 'ajustement' && $newStock > $produit['stock'])) {
                    $lignes = [[$compteStock, $valeur, 0, 'Entrée stock ' . e($produit['nom'])]];
                    $lignes[] = [$compteVarStk, 0, $valeur, 'Variation stock ' . e($produit['nom'])];
                } elseif ($type === 'sortie' || ($type === 'ajustement' && $newStock < $produit['stock'])) {
                    $lignes = [[$compteVarStk, $valeur, 0, 'Sortie stock ' . e($produit['nom'])]];
                    $lignes[] = [$compteStock, 0, $valeur, 'Variation stock ' . e($produit['nom'])];
                } else {
                    $lignes = [];
                }
                if ($lignes) {
                    ecritureCreate($db, 'Stock ' . $type . ' - ' . e($produit['nom']), date('Y-m-d'), $lignes, 'stock', '', currentUser()['id']);
                }
            }
            $db->commit();
            flash("Stock mis à jour : $newStock unités.");
            header('Location: ' . APP_URL . '/modules/stock.php'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur : ' . $e->getMessage(), 'error');
            header('Location: ' . APP_URL . '/modules/stock_ajust.php?id=' . $id); exit;
        }
    }
}

$stmtMv = $db->prepare("
    SELECT m.*, u.prenom, u.nom AS u_nom
    FROM mouvements_stock m
    LEFT JOIN utilisateurs u ON m.utilisateur_id = u.id
    WHERE m.produit_id = ?
    ORDER BY m.created_at DESC LIMIT 15
");
$stmtMv->execute([$id]);
$mouvements = $stmtMv->fetchAll();

$typeInfo = [
    'entrée'     => ['badge-green','Entrée'],
    'sortie'     => ['badge-red',  'Sortie'],
    'ajustement' => ['badge-blue', 'Correction'],
];

layout_head('Ajustement stock — ' . $produit['nom'], 'stock');
showFlash();
?>
<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Ajuster le stock</div>
      <a href="<?= APP_URL ?>/modules/stock.php" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
    </div>
    <div class="card-pad">
      <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:16px;margin-bottom:18px;">
        <div style="font-size:17px;font-weight:600;margin-bottom:4px;"><?= e($produit['nom']) ?></div>
        <div class="text-sm"><?= e($produit['cat'] ?? '—') ?> · Réf. <?= e($produit['reference'] ?? '—') ?></div>
        <div style="margin-top:14px;display:flex;gap:28px;">
          <div>
            <div class="text-xs">Stock actuel</div>
            <div style="font-family:'DM Mono',monospace;font-size:28px;font-weight:700;color:<?= $produit['stock'] <= $produit['seuil_alerte'] ? 'var(--gold)' : 'var(--teal2)' ?>;">
              <?= $produit['stock'] ?>
            </div>
          </div>
          <div>
            <div class="text-xs">Seuil d'alerte</div>
            <div style="font-family:'DM Mono',monospace;font-size:28px;font-weight:700;color:var(--text2);">
              <?= $produit['seuil_alerte'] ?>
            </div>
          </div>
          <div>
            <div class="text-xs">Prix vente</div>
            <div style="font-family:'DM Mono',monospace;font-size:18px;font-weight:600;color:var(--teal2);margin-top:5px;">
              <?= fmtMoney((float)$produit['prix_vente']) ?>
            </div>
          </div>
        </div>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <div class="form-grid cols-1" style="gap:12px;padding:0;">
          <div class="form-group">
            <label>Type de mouvement *</label>
            <select name="type" required>
              <option value="entrée">Entrée — Réception stock</option>
              <option value="sortie">Sortie — Retrait / perte</option>
              <option value="ajustement">Correction — Remise à niveau</option>
            </select>
          </div>
          <div class="form-group">
            <label>Quantité *</label>
            <input type="number" name="quantite" min="1" required placeholder="0">
          </div>
          <div class="form-group">
            <label>Motif</label>
            <textarea name="motif" placeholder="Réception commande, inventaire, casse, péremption..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary">
            <?= icon('check',14) ?> Enregistrer le mouvement
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Historique des mouvements</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Qté</th><th>Motif</th><th>Par</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($mouvements as $m):
            [$badge, $label] = $typeInfo[$m['type']] ?? ['badge-gray', $m['type']];
          ?>
          <tr>
            <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
            <td class="fw-mono"><?= $m['quantite'] ?></td>
            <td class="text-sm"><?= e($m['motif'] ?: '—') ?></td>
            <td class="text-sm"><?= e(trim($m['prenom'].' '.$m['u_nom'])) ?></td>
            <td class="text-sm"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$mouvements): ?>
          <tr><td colspan="5">
            <div class="empty">
              <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',28) ?></div>
              <div>Aucun mouvement</div>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layout_foot(); ?>
