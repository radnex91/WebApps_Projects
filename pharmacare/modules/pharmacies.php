<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';

// Lecture (liste) : pharmacies.voir ; modifications : pharmacies.gerer
if (!hasPermission('pharmacies.voir')) {
    requirePermission('pharmacies.voir'); // redirige proprement
}
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sub = $_POST['action'] ?? '';

    // ── Toggle actif/désactivé ──
    if ($sub === 'toggle') {
        requirePermission('pharmacies.gerer');
        $tid = (int)($_POST['id'] ?? 0);
        if ($tid) {
            // On ne permet pas de désactiver la pharmacie principale (id=1)
            $db->prepare("UPDATE pharmacies SET actif = 1-actif WHERE id=? AND id <> 1")->execute([$tid]);
            flash('Statut de la pharmacie mis à jour.');
        }
        header('Location: ' . url('pharmacies')); exit;
    }

    // ── Mise à jour du stock par pharmacie ──
    if ($sub === 'update_stock') {
        requirePermission('pharmacies.gerer');
        $pid = (int)($_POST['pharmacie_id'] ?? 0);
        $stocks = $_POST['stock'] ?? [];
        if ($pid) {
            $stmtUp = $db->prepare("
                INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                VALUES (?, ?, ?, 10)
                ON DUPLICATE KEY UPDATE stock = VALUES(stock)
            ");
            foreach ($stocks as $produitId => $qte) {
                $stmtUp->execute([(int)$produitId, $pid, max(0, (int)$qte)]);
            }
            // Pour la pharmacie principale, on resynchronise produits.stock
            if ($pid === 1) {
                $db->prepare("UPDATE produits p
                    JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                    SET p.stock = pp.stock")->execute();
            }
            flash('Stock de la pharmacie mis à jour.');
        }
        header('Location: ' . url('pharmacies', ['action' => 'stock', 'id' => $pid])); exit;
    }

    // ── Création / édition ──
    requirePermission('pharmacies.gerer');
    $nom       = trim($_POST['nom'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    if ($nom === '') {
        flash('Le nom de la pharmacie est obligatoire.', 'error');
        header('Location: ' . ($id ? url('pharmacies', ['action' => 'edit', 'id' => $id])
                                   : url('pharmacies', ['action' => 'add']))); exit;
    }

    if ($id) {
        $db->prepare("UPDATE pharmacies SET nom=?, adresse=?, telephone=? WHERE id=?")
           ->execute([$nom, $adresse, $telephone, $id]);
        flash('Pharmacie mise à jour.');
    } else {
        $db->prepare("INSERT INTO pharmacies (nom, adresse, telephone, actif) VALUES (?,?,?,1)")
           ->execute([$nom, $adresse, $telephone]);
        $newId = (int)$db->lastInsertId();
        // Initialise une ligne de stock à 0 pour chaque produit actif (facilite la saisie)
        $db->prepare("INSERT IGNORE INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                      SELECT p.id, ?, 0, 10 FROM produits p WHERE p.actif = 1")->execute([$newId]);
        flash('Pharmacie créée. Pensez à régler son stock initial via l\'onglet Stock.');
    }
    header('Location: ' . url('pharmacies')); exit;
}

// ── Vues : ajout / édition ────────────────────────────────────
if (in_array($action, ['add', 'edit'])) {
    requirePermission('pharmacies.gerer');
    $p = ['nom' => '', 'adresse' => '', 'telephone' => ''];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM pharmacies WHERE id=?");
        $stmt->execute([$id]);
        if ($r = $stmt->fetch()) $p = $r;
    }
    layout_head(($id ? 'Modifier' : 'Ajouter') . ' pharmacie', 'pharmacies');
    showFlash();
    ?>
    <div class="card" style="max-width:620px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier la pharmacie' : 'Nouvelle pharmacie' ?></div>
        <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group"><label>Nom *</label>
            <input type="text" name="nom" value="<?= e($p['nom']) ?>" required></div>
          <div class="form-group"><label>Téléphone</label>
            <input type="text" name="telephone" value="<?= e($p['telephone']) ?>"></div>
          <div class="form-group" style="grid-column:1/-1;"><label>Adresse</label>
            <input type="text" name="adresse" value="<?= e($p['adresse']) ?>"></div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('pharmacies') ?>" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Enregistrer</button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
}

// ── Vue : stock par pharmacie ─────────────────────────────────
if ($action === 'stock') {
    requirePermission('pharmacies.gerer');
    $pharmacies = $db->query("SELECT id, nom FROM pharmacies WHERE actif=1 ORDER BY id")->fetchAll();
    $pid = $id ?: (int)($_GET['pharmacie_id'] ?? ($pharmacies[0]['id'] ?? 0));

    $produits = [];
    if ($pid) {
        $stmt = $db->prepare("
            SELECT p.id, p.nom, p.reference, COALESCE(pp.stock, 0) AS stock
            FROM produits p
            LEFT JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = ?
            WHERE p.actif = 1
            ORDER BY p.nom
        ");
        $stmt->execute([$pid]);
        $produits = $stmt->fetchAll();
    }

    layout_head('Stock par pharmacie', 'pharmacies');
    showFlash();
    ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Stock par pharmacie</div>
        <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <div class="card-pad" style="padding-bottom:0;">
        <div class="form-group" style="max-width:360px;">
          <label>Pharmacie</label>
          <select onchange="if(this.value) window.location='<?= url('pharmacies', ['action' => 'stock']) ?>?id='+this.value">
            <?php foreach ($pharmacies as $ph): ?>
              <option value="<?= (int)$ph['id'] ?>" <?= $pid === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if ($pid): ?>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="pharmacie_id" value="<?= $pid ?>">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Médicament</th><th>Référence</th><th style="width:160px;">Stock</th></tr></thead>
            <tbody>
              <?php foreach ($produits as $pr): ?>
              <tr>
                <td class="td-name"><?= e($pr['nom']) ?></td>
                <td class="td-mono"><?= e($pr['reference']) ?></td>
                <td><input type="number" name="stock[<?= (int)$pr['id'] ?>]" value="<?= (int)$pr['stock'] ?>" min="0" step="1" style="width:110px;"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Enregistrer le stock</button>
        </div>
      </form>
      <?php else: ?>
      <div class="card-pad"><p>Aucune pharmacie active.</p></div>
      <?php endif; ?>
    </div>
    <?php layout_foot(); exit;
}

// ── Vue : liste ───────────────────────────────────────────────
// ── Export Excel ───────────────────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $rowsX = [];
    foreach ($db->query("
        SELECT ph.nom, ph.adresse, ph.telephone, ph.actif, ph.created_at,
               (SELECT COUNT(*) FROM produit_pharmacie pp WHERE pp.pharmacie_id = ph.id AND pp.stock > 0) AS nb_prods_stock,
               (SELECT COUNT(*) FROM sessions_caisse s WHERE s.pharmacie_id = ph.id AND s.statut = 'ouverte') AS nb_sessions_ouvertes
        FROM pharmacies ph
        ORDER BY ph.id
    ")->fetchAll() as $ph) {
        $rowsX[] = [$ph['nom'], $ph['adresse'], $ph['telephone'],
                    (int)$ph['actif'] ? 'Active' : 'Désactivée',
                    (int)$ph['nb_prods_stock'], (int)$ph['nb_sessions_ouvertes'],
                    date('d/m/Y', strtotime($ph['created_at']))];
    }
    export_xlsx_send('pharmacies_' . date('Y-m-d'), 'Pharmacies',
        ['Nom', 'Adresse', 'Téléphone', 'Statut', 'Nb produits en stock', 'Sessions ouvertes', 'Créée le'], $rowsX);
}

$pharmacies = $db->query("
    SELECT ph.*,
           (SELECT COUNT(*) FROM produit_pharmacie pp WHERE pp.pharmacie_id = ph.id AND pp.stock > 0) AS nb_prods_stock,
           (SELECT COUNT(*) FROM sessions_caisse s WHERE s.pharmacie_id = ph.id AND s.statut = 'ouverte') AS nb_sessions_ouvertes
    FROM pharmacies ph
    ORDER BY ph.id
")->fetchAll();

$canGerer = hasPermission('pharmacies.gerer');

layout_head('Pharmacies', 'pharmacies');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Pharmacies</div>
    <div class="flex gap-8">
      <a href="<?= url('pharmacies', ['export'=>'1']) ?>" class="btn btn-ghost btn-sm" title="Exporter au format Excel (.xlsx)"><?= icon('download', 14) ?> Exporter</a>
      <?php if ($canGerer): ?>
      <a href="<?= url('pharmacies', ['action' => 'stock']) ?>" class="btn btn-ghost btn-sm"><?= icon('boxes', 14) ?> Stock par pharmacie</a>
      <a href="<?= url('pharmacies', ['action' => 'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> Ajouter</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Produits en stock</th><th>Sessions ouvertes</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pharmacies as $ph): ?>
        <tr>
          <td class="td-name"><?= e($ph['nom']) ?><?php if ((int)$ph['id'] === 1): ?> <span class="badge badge-gray">principale</span><?php endif; ?></td>
          <td class="text-sm"><?= e($ph['adresse'] ?: '—') ?></td>
          <td class="text-sm"><?= e($ph['telephone'] ?: '—') ?></td>
          <td><span class="badge badge-blue"><?= (int)$ph['nb_prods_stock'] ?></span></td>
          <td><span class="badge badge-gold"><?= (int)$ph['nb_sessions_ouvertes'] ?></span></td>
          <td><span class="badge <?= $ph['actif'] ? 'badge-green' : 'badge-red' ?>"><?= $ph['actif'] ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <div class="flex gap-8">
              <?php if ($canGerer): ?>
              <a href="<?= url('pharmacies', ['action' => 'edit', 'id' => $ph['id']], $ph['nom']) ?>" class="btn btn-ghost btn-xs"><?= icon('edit', 13) ?> Modifier</a>
              <?php if ((int)$ph['id'] !== 1): ?>
              <button type="button" class="btn <?= $ph['actif'] ? 'btn-danger' : 'btn-gold' ?> btn-xs"
                onclick="confirmDeletePost('toggle','<?= (int)$ph['id'] ?>','<?= $ph['actif'] ? 'Désactiver cette pharmacie ?' : 'Activer cette pharmacie ?' ?>')">
                <?= $ph['actif'] ? icon('lock', 13) . ' Désactiver' : icon('unlock', 13) . ' Activer' ?>
              </button>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); ?>