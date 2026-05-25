<?php
// modules/paiements.php
require_once '../includes/config.php';
requireLogin(); requirePerm('paiements.view');
$pageTitle = 'Paiements';
$annee = getAnneeActive($pdo);
$aid   = $annee['id'] ?? 1;

// SAVE PAIEMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('paiements.manage')) {
    $insc_id  = (int)($_POST['inscription_id'] ?? 0);
    $montant  = (float)($_POST['montant'] ?? 0);
    $date     = $_POST['date_paiement'] ?? date('Y-m-d');
    $mode     = $_POST['mode'] ?? 'especes';
    $ref      = trim($_POST['reference'] ?? '');
    $obs      = trim($_POST['observation'] ?? '');
    $redirect = $_POST['redirect'] ?? BASE_URL . 'modules/paiements.php';

    if ($insc_id && $montant > 0) {
        $pdo->prepare("INSERT INTO paiements (inscription_id, montant, date_paiement, mode, reference, observation) VALUES (?,?,?,?,?,?)")
            ->execute([$insc_id, $montant, $date, $mode, $ref, $obs]);
        logAction($pdo, 'create_paiement', 'paiements', "Montant: $montant FCFA");
        flash("Paiement de " . formatMoney($montant) . " enregistré.");
    } else {
        flash("Données invalides.", 'danger');
    }
    redirect($redirect);
}

// Filtres
$search   = trim($_GET['q']    ?? '');
$classeF  = (int)($_GET['classe_id'] ?? 0);
$modeF    = trim($_GET['mode'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 30;

$where = ["i.annee_id=$aid"]; $params = [];
if ($search)  { $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($classeF) { $where[] = "i.classe_id=?"; $params[] = $classeF; }
if ($modeF)   { $where[] = "p.mode=?"; $params[] = $modeF; }
$ws = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id JOIN eleves e ON i.eleve_id=e.id WHERE $ws");
$total->execute($params); $totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage); $offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT p.*, e.nom, e.prenom, e.matricule, cl.nom as classe_nom, f.couleur FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id JOIN eleves e ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id JOIN filieres f ON cl.filiere_id=f.id WHERE $ws ORDER BY p.date_paiement DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $paiements = $stmt->fetchAll();

// Totaux
$totStmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant),0) as total, COUNT(p.id) as nb FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=$aid");
$totStmt->execute(); $totals = $totStmt->fetch();

$totMois = $pdo->prepare("SELECT COALESCE(SUM(p.montant),0) FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=? AND MONTH(p.date_paiement)=MONTH(CURDATE()) AND YEAR(p.date_paiement)=YEAR(CURDATE())");
$totMois->execute([$aid]); $totalMois = $totMois->fetchColumn();

$classes = $pdo->query("SELECT c.id, c.nom FROM classes c WHERE c.annee_id=$aid ORDER BY c.nom")->fetchAll();
$inscritsAll = $pdo->query("SELECT i.id, CONCAT(e.matricule,' — ',e.prenom,' ',e.nom) as label FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id WHERE i.annee_id=$aid ORDER BY e.nom")->fetchAll();

include '../includes/header.php';
?>

<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Paiements</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-value" style="font-size:16px;"><?= number_format($totals['total'],0,',',' ') ?></div><div class="stat-label">Total encaissé (FCFA)</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-receipt"></i></div>
    <div><div class="stat-value"><?= $totals['nb'] ?></div><div class="stat-label">Nombre de paiements</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-calendar-alt"></i></div>
    <div><div class="stat-value" style="font-size:16px;"><?= number_format($totalMois,0,',',' ') ?></div><div class="stat-label">Ce mois (FCFA)</div></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-money-bill-wave"></i> Historique des paiements (<?= $totalRows ?>)</h3>
    <?php if(can('paiements.manage')): ?>
    <button class="btn btn-success btn-sm" onclick="openModal('pay-modal')"><i class="fas fa-plus"></i> Nouveau paiement</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="🔍 Nom, matricule..." value="<?= sanitize($search) ?>" style="max-width:250px;">
      <select name="classe_id" class="form-control" style="width:auto;">
        <option value="">Toutes classes</option>
        <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classeF==$c['id']?'selected':'' ?>><?= sanitize($c['nom']) ?></option><?php endforeach; ?>
      </select>
      <select name="mode" class="form-control" style="width:auto;">
        <option value="">Tous modes</option>
        <?php foreach(['especes','mobile_money','cheque','virement'] as $m): ?>
        <option value="<?= $m ?>" <?= $modeF===$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>

    <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Élève</th><th>Classe</th><th>Montant</th><th>Mode</th><th>Référence</th></tr></thead>
      <tbody>
        <?php foreach($paiements as $p): ?>
        <tr>
          <td><?= formatDate($p['date_paiement']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>modules/eleves/voir.php?id=<?= $p['id'] ?>" style="font-weight:600;"><?= sanitize($p['nom'].' '.$p['prenom']) ?></a>
            <div style="font-size:11px;color:var(--text3);"><?= sanitize($p['matricule']) ?></div>
          </td>
          <td><span class="filiere-badge" style="background:<?= sanitize($p['couleur']) ?>20;color:<?= sanitize($p['couleur']) ?>"><?= sanitize($p['classe_nom']) ?></span></td>
          <td style="font-weight:700;color:var(--success);font-size:14px;"><?= formatMoney($p['montant']) ?></td>
          <td>
            <?php $micons=['especes'=>'💵','mobile_money'=>'📱','cheque'=>'📄','virement'=>'🏦']; ?>
            <?= $micons[$p['mode']]??'💳' ?> <?= sanitize(str_replace('_',' ',ucfirst($p['mode']))) ?>
          </td>
          <td style="font-size:11px;color:var(--text3);"><?= sanitize($p['reference']??'—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($paiements)): ?><tr><td colspan="6" class="table-empty"><i class="fas fa-money-bill-wave"></i>Aucun paiement</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>

    <?php if($totalPages>1): ?>
    <div class="pagination">
      <?php for($i=1;$i<=$totalPages;$i++): ?>
      <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL PAIEMENT -->
<?php if(can('paiements.manage')): ?>
<div class="modal-overlay" id="pay-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-plus"></i> Nouveau paiement</h3><button class="modal-close" onclick="closeModal('pay-modal')">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px;">
          <label class="form-label">Élève <span class="form-required">*</span></label>
          <select name="inscription_id" class="form-control" required>
            <option value="">— Sélectionner —</option>
            <?php foreach($inscritsAll as $ins): ?>
            <option value="<?= $ins['id'] ?>"><?= sanitize($ins['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Montant (FCFA) <span class="form-required">*</span></label><input type="number" name="montant" class="form-control" required min="1" step="500"></div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Date</label><input type="date" name="date_paiement" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Mode de paiement</label>
          <select name="mode" class="form-control">
            <option value="especes">💵 Espèces</option><option value="mobile_money">📱 Mobile Money</option><option value="cheque">📄 Chèque</option><option value="virement">🏦 Virement</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Référence / N° reçu</label><input type="text" name="reference" class="form-control"></div>
        <div class="form-group"><label class="form-label">Observation</label><textarea name="observation" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('pay-modal')">Annuler</button><button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
