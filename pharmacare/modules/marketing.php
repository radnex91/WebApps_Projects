<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('marketing.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'dashboard';
$id     = (int)($_GET['id'] ?? 0);

// Module « client fidèle » désactivé partout pour l'instant.
$fideliteActive = fideliteActive();
if (!$fideliteActive && in_array($action, ['fidelite', 'add-points'], true)) {
    flash('Le module fidélité client est désactivé.', 'error');
    header('Location: ' . url('marketing')); exit;
}

// ── POST : créer / modifier une campagne promo ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add-promo','edit-promo'], true)) {
    requirePermission('marketing.promos');
    verifyCsrf();
    $nom        = trim($_POST['nom'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $type       = $_POST['type'] ?? 'pourcentage';
    $valeur     = (float)($_POST['valeur'] ?? 0);
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin   = $_POST['date_fin'] ?? '';
    $produits   = $_POST['produits'] ?? [];

    if ($nom === '' || $valeur <= 0 || !$date_debut || !$date_fin) {
        flash('Tous les champs obligatoires sont requis.', 'error');
        header('Location: ' . url('marketing', $action === 'edit-promo' ? ['action'=>'edit-promo','id'=>$id] : ['action'=>'add-promo'])); exit;
    }

    try {
        $db->beginTransaction();
        if ($action === 'edit-promo' && $id) {
            $db->prepare("UPDATE campagnes_promo SET nom=?, description=?, type=?, valeur=?, date_debut=?, date_fin=? WHERE id=?")
               ->execute([$nom, $desc, $type, $valeur, $date_debut, $date_fin, $id]);
            $db->prepare("DELETE FROM promo_produits WHERE campagne_id=?")->execute([$id]);
            $campagneId = $id;
        } else {
            $db->prepare("INSERT INTO campagnes_promo (nom, description, type, valeur, date_debut, date_fin) VALUES (?,?,?,?,?,?)")
               ->execute([$nom, $desc, $type, $valeur, $date_debut, $date_fin]);
            $campagneId = (int)$db->lastInsertId();
        }
        foreach ($produits as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) {
                $db->prepare("INSERT INTO promo_produits (campagne_id, produit_id) VALUES (?,?)")->execute([$campagneId, $pid]);
            }
        }
        $db->commit();
        flash('Campagne ' . ($action === 'edit-promo' ? 'modifiée' : 'créée') . '.', 'success');
        header('Location: ' . url('marketing')); exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flashError($e, 'promotion');
        header('Location: ' . url('marketing', ['action'=>'add-promo'])); exit;
    }
}

// ── POST : ajouter des points fidélité ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add-points') {
    requirePermission('marketing.fidelite');
    verifyCsrf();
    $clientId = (int)($_POST['client_id'] ?? 0);
    $points   = (int)($_POST['points'] ?? 0);
    $note     = trim($_POST['note'] ?? '');

    if ($clientId <= 0 || $points <= 0) {
        flash('Client et points requis.', 'error');
        header('Location: ' . url('marketing', ['action'=>'fidelite'])); exit;
    }
    $db->prepare("INSERT INTO fidelite_points (client_id, points, type, note) VALUES (?,?, 'gagné',?)")
       ->execute([$clientId, $points, $note]);
    flash($points . ' points ajoutés au client.', 'success');
    header('Location: ' . url('marketing', ['action'=>'fidelite'])); exit;
}

// ── GET : désactiver / activer une campagne ──────────────────
if ($action === 'toggle' && $id && hasPermission('marketing.promos')) {
    if (($_GET['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) die('Requête invalide (CSRF).');
    $stmt = $db->prepare("SELECT actif FROM campagnes_promo WHERE id=?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();
    $new = $current ? 0 : 1;
    $db->prepare("UPDATE campagnes_promo SET actif=? WHERE id=?")->execute([$new, $id]);
    flash($new ? 'Campagne activée.' : 'Campagne désactivée.', 'success');
    header('Location: ' . url('marketing')); exit;
}

// ── Titre ──────────────────────────────────────────────────
$title = 'Marketing';
if ($action === 'add-promo') $title = 'Nouvelle campagne';
elseif ($action === 'edit-promo' && $id) $title = 'Modifier campagne';
elseif ($action === 'fidelite') $title = 'Fidélité clients';

layout_head($title, 'marketing');
?>

<?php if ($action === 'add-promo' || ($action === 'edit-promo' && $id)): ?>
<?php
$editCamp = null;
$editProduits = [];
if ($action === 'edit-promo' && $id) {
    $stmtE = $db->prepare("SELECT * FROM campagnes_promo WHERE id=?");
    $stmtE->execute([$id]);
    $editCamp = $stmtE->fetch();
    if (!$editCamp) { echo '<div class="alert alert-error">Campagne introuvable.</div>'; layout_foot(); exit; }
    $stmtP = $db->prepare("SELECT produit_id FROM promo_produits WHERE campagne_id=?");
    $stmtP->execute([$id]);
    $editProduits = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}
$isEdit = ($editCamp !== null);

$produitsList = $db->query("SELECT id, nom FROM produits WHERE actif=1 ORDER BY nom ASC")->fetchAll();
?>
<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title"><?= $isEdit ? 'Modifier la campagne' : 'Nouvelle campagne' ?></div>
  </div>
  <form method="POST" action="<?= APP_URL ?>/modules/marketing.php?action=<?= $isEdit ? 'edit-promo&id='.$editCamp['id'] : 'add-promo' ?>">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="card-pad">
      <div class="form-row two-cols">
        <div class="form-group">
          <label>Nom <span style="color:var(--red);">*</span></label>
          <input type="text" name="nom" value="<?= $isEdit ? e($editCamp['nom']) : '' ?>" required>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type">
            <option value="pourcentage" <?= $isEdit && $editCamp['type']==='pourcentage' ? 'selected' : '' ?>>Pourcentage (%)</option>
            <option value="montant_fixe" <?= $isEdit && $editCamp['type']==='montant_fixe' ? 'selected' : '' ?>>Montant fixe (FCFA)</option>
          </select>
        </div>
      </div>
      <div class="form-row two-cols">
        <div class="form-group">
          <label>Valeur <span style="color:var(--red);">*</span></label>
          <input type="number" name="valeur" step="0.01" min="0.01" value="<?= $isEdit ? $editCamp['valeur'] : '' ?>" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <input type="text" name="description" value="<?= $isEdit ? e($editCamp['description']) : '' ?>">
        </div>
      </div>
      <div class="form-row two-cols">
        <div class="form-group">
          <label>Date début <span style="color:var(--red);">*</span></label>
          <input type="date" name="date_debut" value="<?= $isEdit ? $editCamp['date_debut'] : '' ?>" required>
        </div>
        <div class="form-group">
          <label>Date fin <span style="color:var(--red);">*</span></label>
          <input type="date" name="date_fin" value="<?= $isEdit ? $editCamp['date_fin'] : '' ?>" required>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label>Produits concernés</label>
        <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:8px;">
          <?php foreach ($produitsList as $p): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:3px 0;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="produits[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $editProduits) ? 'checked' : '' ?>>
            <?= e($p['nom']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <a href="<?= url('marketing') ?>" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Enregistrer' : 'Créer la campagne' ?></button>
    </div>
  </form>
</div>

<?php elseif ($action === 'fidelite'): ?>
<?php
$clients = $db->query("
    SELECT c.id, c.nom, c.telephone,
           COALESCE(SUM(CASE WHEN fp.type = 'gagné' THEN fp.points ELSE 0 END), 0) AS points_gagnes,
           COALESCE(SUM(CASE WHEN fp.type = 'utilisé' THEN fp.points ELSE 0 END), 0) AS points_utilises
    FROM clients c
    LEFT JOIN fidelite_points fp ON c.id = fp.client_id
    WHERE c.actif = 1
    GROUP BY c.id
    ORDER BY points_gagnes DESC
")->fetchAll();

$totalPointsGagnes = array_sum(array_column($clients, 'points_gagnes'));
$totalPointsUtilises = array_sum(array_column($clients, 'points_utilises'));
?>
<div class="page-header">
  <h1>Fidélité clients</h1>
  <button type="button" class="btn btn-primary" onclick="openModal('modal-add-points')"><?= icon('plus',14) ?> Ajouter des points</button>
</div>

<div class="card-grid">
  <div class="card">
    <div class="card-header"><div class="card-title">Points distribués</div></div>
    <div class="card-pad">
      <div style="font-size:28px;font-weight:700;color:var(--teal);font-family:var(--font-title);"><?= fmtInt($totalPointsGagnes) ?></div>
      <div style="font-size:12px;color:var(--text2);">Utilisés : <?= fmtInt($totalPointsUtilises) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Clients fidélisés</div></div>
    <div class="card-pad">
      <div style="font-size:28px;font-weight:700;color:var(--blue);font-family:var(--font-title);"><?= count($clients) ?></div>
      <div style="font-size:12px;color:var(--text2);">Clients actifs avec points</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">Classement des clients</div></div>
  <?php if (count($clients) === 0): ?>
  <div class="card-pad"><p style="color:var(--text3);">Aucun point de fidélité enregistré.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>#</th><th>Client</th><th>Téléphone</th><th>Points gagnés</th><th>Points utilisés</th><th>Solde</th><th style="width:80px;">Actions</th></tr></thead>
    <tbody>
    <?php $i = 1; foreach ($clients as $c):
        $solde = (int)$c['points_gagnes'] - (int)$c['points_utilises'];
    ?>
      <tr>
        <td style="color:var(--text2);"><?= $i++ ?></td>
        <td><a href="<?= url('clients', ['action'=>'detail','id'=>$c['id']], $c['nom'] ?? null) ?>" style="font-weight:500;text-decoration:none;color:var(--text);"><?= e($c['nom']) ?></a></td>
        <td style="color:var(--text2);"><?= e($c['telephone'] ?: '—') ?></td>
        <td class="fw-mono" style="color:var(--teal);">+<?= fmtInt((int)$c['points_gagnes']) ?></td>
        <td class="fw-mono" style="color:var(--red);"><?= (int)$c['points_utilises'] > 0 ? '-'.fmtInt((int)$c['points_utilises']) : '—' ?></td>
        <td class="fw-mono" style="font-weight:600;color:<?= $solde > 0 ? 'var(--teal)' : 'var(--text2)' ?>;"><?= fmtInt($solde) ?></td>
        <td>
          <button class="btn btn-ghost btn-xs" onclick="openModal('modal-add-points-<?= $c['id'] ?>')" title="Ajouter des points"><?= icon('plus',14) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Modal global ajout points -->
<div class="modal-overlay" id="modal-add-points">
  <div class="modal" style="width:400px;">
    <div class="modal-header">
      <div class="modal-title">Ajouter des points</div>
      <button type="button" class="modal-close" onclick="closeModal('modal-add-points')">✕</button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/modules/marketing.php?action=add-points">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="card-pad">
        <div class="form-group">
          <label>Client</label>
          <select name="client_id" required>
            <option value="">— Sélectionner —</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Points</label>
          <input type="number" name="points" min="1" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Motif</label>
          <input type="text" name="note" placeholder="Ex: Anniversaire, achat...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-points')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('plus',14) ?> Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- Modals individuels pour chaque client -->
<?php foreach ($clients as $c): ?>
<div class="modal-overlay" id="modal-add-points-<?= $c['id'] ?>">
  <div class="modal" style="width:400px;">
    <div class="modal-header">
      <div class="modal-title">Points pour <?= e($c['nom']) ?></div>
      <button type="button" class="modal-close" onclick="closeModal('modal-add-points-<?= $c['id'] ?>')">✕</button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/modules/marketing.php?action=add-points">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
      <div class="card-pad">
        <div class="form-group">
          <label>Points</label>
          <input type="number" name="points" min="1" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Motif</label>
          <input type="text" name="note" placeholder="Ex: Anniversaire, achat...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-points-<?= $c['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('plus',14) ?> Ajouter</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php else: ?>
<?php
// Dashboard marketing
$campagnes = $db->query("
    SELECT cp.*,
           (SELECT COUNT(*) FROM promo_produits WHERE campagne_id = cp.id) AS nb_produits
    FROM campagnes_promo cp
    ORDER BY cp.date_debut DESC
")->fetchAll();

// ── Export Excel des campagnes ────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $typesMap = ['pourcentage' => 'Pourcentage', 'montant' => 'Montant fixe'];
    $rowsX = [];
    foreach ($campagnes as $c) {
        $active = $c['actif'] && $c['date_debut'] <= date('Y-m-d') && $c['date_fin'] >= date('Y-m-d');
        $rowsX[] = [
            $c['nom'], $typesMap[$c['type']] ?? $c['type'], $c['valeur'] !== null ? (float)$c['valeur'] : null,
            date('d/m/Y', strtotime($c['date_debut'])), date('d/m/Y', strtotime($c['date_fin'])),
            $active ? 'Active' : 'Inactive', (int)$c['nb_produits'],
            date('d/m/Y', strtotime($c['created_at'])),
        ];
    }
    export_xlsx_send('campagnes_promo_' . date('Y-m-d'), 'Campagnes',
        ['Nom', 'Type', 'Valeur', 'Début', 'Fin', 'Statut', 'Nb produits', 'Créée le'], $rowsX);
}

$activeCount = 0;
$today = date('Y-m-d');

// ── Variables de pagination de l'onglet « Campagnes » ──
// (le module les consomme mais ne les définissait jamais : warnings + appel
// renderPagination() sur des variables inconnues)
$totalCampagnes = count($campagnes);
$perPage        = 20;
$pageCamp       = max(1, (int)($_GET['page'] ?? 1));
$campagnesPage  = array_slice($campagnes, ($pageCamp - 1) * $perPage, $perPage);

foreach ($campagnes as $c) {
    if ($c['actif'] && $c['date_debut'] <= $today && $c['date_fin'] >= $today) $activeCount++;
}

$topClients = $db->query("
    SELECT c.nom,
           COALESCE(SUM(CASE WHEN fp.type = 'gagné' THEN fp.points ELSE 0 END), 0) -
           COALESCE(SUM(CASE WHEN fp.type = 'utilisé' THEN fp.points ELSE 0 END), 0) AS solde
    FROM clients c
    JOIN fidelite_points fp ON c.id = fp.client_id
    WHERE c.actif = 1
    GROUP BY c.id
    ORDER BY solde DESC
    LIMIT 5
")->fetchAll();
?>

<div class="page-header">
  <h1>Marketing</h1>
  <a href="<?= url('marketing', ['export'=>'1']) ?>" class="btn btn-ghost" title="Exporter les campagnes au format Excel (.xlsx)"><?= icon('download',14) ?> Exporter</a>
  <?php if (hasPermission('marketing.promos')): ?>
  <a href="<?= url('marketing', ['action'=>'add-promo']) ?>" class="btn btn-primary"><?= icon('plus',14) ?> Nouvelle campagne</a>
  <?php endif; ?>
</div>

<div class="card-grid">
  <div class="card">
    <div class="card-header"><div class="card-title">Campagnes actives</div></div>
    <div class="card-pad">
      <div style="font-size:28px;font-weight:700;color:var(--teal);font-family:var(--font-title);"><?= $activeCount ?></div>
      <div style="font-size:12px;color:var(--text2);">Sur <?= $totalCampagnes ?> campagne(s)</div>
    </div>
  </div>
  <?php if ($fideliteActive): ?>
  <div class="card">
    <div class="card-header"><div class="card-title">Fidélité</div></div>
    <div class="card-pad">
      <div style="font-size:28px;font-weight:700;color:var(--blue);font-family:var(--font-title);"><?= count($topClients) ?></div>
      <div style="font-size:12px;color:var(--text2);">Meilleurs clients fidélisés</div>
    </div>
  </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-header"><div class="card-title">Actions</div></div>
    <div class="card-pad" style="display:flex;flex-direction:column;gap:8px;">
      <?php if (hasPermission('marketing.promos')): ?>
      <a href="<?= url('marketing', ['action'=>'add-promo']) ?>" class="btn btn-outline btn-sm" style="justify-content:center;"><?= icon('plus',14) ?> Créer une campagne</a>
      <?php endif; ?>
      <?php if (hasPermission('marketing.fidelite') && $fideliteActive): ?>
      <a href="<?= url('marketing', ['action'=>'fidelite']) ?>" class="btn btn-outline btn-sm" style="justify-content:center;"><?= icon('users',14) ?> Gérer la fidélité</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($fideliteActive && count($topClients) > 0): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Top 5 clients fidélité</div>
    <a href="<?= url('marketing', ['action'=>'fidelite']) ?>" class="btn btn-ghost btn-sm">Voir tout</a>
  </div>
  <table class="table">
    <thead><tr><th>#</th><th>Client</th><th>Solde points</th></tr></thead>
    <tbody>
    <?php $i=1; foreach ($topClients as $tc): ?>
      <tr>
        <td style="color:var(--text2);"><?= $i++ ?></td>
        <td style="font-weight:500;"><?= e($tc['nom']) ?></td>
        <td class="fw-mono"><?= fmtInt((int)$tc['solde']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <div class="card-title">Campagnes promotionnelles</div>
    <?php if (hasPermission('marketing.promos')): ?>
    <a href="<?= url('marketing', ['action'=>'add-promo']) ?>" class="btn btn-ghost btn-sm"><?= icon('plus',14) ?> Ajouter</a>
    <?php endif; ?>
  </div>
  <?php if ($totalCampagnes === 0): ?>
  <div class="card-pad"><p style="color:var(--text3);">Aucune campagne créée.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Nom</th><th>Type</th><th>Valeur</th><th>Période</th><th>Produits</th><th>Statut</th><th style="width:90px;">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($campagnesPage as $c):
        $periode = date('d/m/Y', strtotime($c['date_debut'])) . ' — ' . date('d/m/Y', strtotime($c['date_fin']));
        $typeLabel = $c['type'] === 'pourcentage' ? '%' : 'FCFA';
        $valeurFmt = $c['type'] === 'pourcentage' ? fmt($c['valeur']).'%' : fmtMoney($c['valeur']);

        $isActive = $c['actif'] && $c['date_debut'] <= $today && $c['date_fin'] >= $today;
        $isExpired = $c['date_fin'] < $today;
        if ($isActive) {
            $badge = '<span class="badge badge-green">Active</span>';
        } elseif ($isExpired) {
            $badge = '<span class="badge badge-gray">Expirée</span>';
        } elseif (!$c['actif']) {
            $badge = '<span class="badge badge-gray">Désactivée</span>';
        } else {
            $badge = '<span class="badge badge-orange">À venir</span>';
        }
    ?>
      <tr style="<?= !$c['actif'] ? 'opacity:0.5;' : '' ?>">
        <td style="font-weight:500;"><?= e($c['nom']) ?></td>
        <td><?= $typeLabel ?></td>
        <td class="fw-mono"><?= $valeurFmt ?></td>
        <td style="font-size:12px;color:var(--text2);"><?= $periode ?></td>
        <td><?= (int)$c['nb_produits'] ?> produit(s)</td>
        <td><?= $badge ?></td>
        <td>
          <div style="display:flex;gap:4px;">
            <?php if (hasPermission('marketing.promos')): ?>
            <a href="<?= url('marketing', ['action'=>'edit-promo','id'=>$c['id']]) ?>" class="btn btn-ghost" style="padding:4px 6px;" title="Modifier"><?= icon('edit',14) ?></a>
            <a href="<?= url('marketing', ['action'=>'toggle','id'=>$c['id'],'csrf'=>csrf()]) ?>" class="btn btn-ghost" style="padding:4px 6px;color:var(--text2);" title="<?= $c['actif'] ? 'Désactiver' : 'Activer' ?>"><?= icon($c['actif'] ? 'x' : 'check',14) ?></a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= renderPagination($pageCamp, $perPage, $totalCampagnes, []) ?>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php layout_foot(); ?>
