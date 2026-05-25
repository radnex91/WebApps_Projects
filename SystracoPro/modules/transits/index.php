<?php
// modules/transits/index.php — Tableau des correspondances
require_once '../../includes/config.php';
requireLogin(); requirePerm('transits.manage');
$pageTitle = 'Transits & Correspondances';
$aid = getUserAgenceId();

// Marquer un transit comme embarqué
if (isset($_GET['embarque'])) {
    $tid = (int)$_GET['embarque'];
    $pdo->prepare("UPDATE tickets SET correspondance_statut='embarque' WHERE id=? AND (correspondance_statut='en_attente' OR correspondance_statut IS NULL)")->execute([$tid]);
    logAction($pdo,'embarque_transit','transits',"Transit $tid embarqué");
    flash('Passager marqué comme embarqué.');
    redirect(BASE_URL.'modules/transits/');
}

// Annuler la correspondance
if (isset($_GET['annule_corr'])) {
    $tid = (int)$_GET['annule_corr'];
    $pdo->prepare("UPDATE tickets SET correspondance_statut='annule',transit=0,itineraire_suite_id=NULL WHERE id=?")->execute([$tid]);
    logAction($pdo,'annule_correspondance','transits',"Correspondance ticket $tid annulée");
    flash('Correspondance annulée.','warning');
    redirect(BASE_URL.'modules/transits/');
}

$wA = $aid ? "AND t.agence_id=$aid" : "";
$transits = $pdo->query("SELECT t.*, v.numero as voy_num, v.date_depart, i.nom as itineraire_nom, a1.ville as dep, a2.ville as arr, i2.nom as suite_nom FROM tickets t JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN itineraires i2 ON t.itineraire_suite_id=i2.id WHERE t.transit=1 $wA ORDER BY v.date_depart DESC")->fetchAll();

$en_attente = array_filter($transits, fn($t) => in_array($t['correspondance_statut'] ?? '', ['en_attente', '']) || $t['correspondance_statut'] === null);
$embarques = array_filter($transits, fn($t) => ($t['correspondance_statut'] ?? '') === 'embarque');
$annules = array_filter($transits, fn($t) => ($t['correspondance_statut'] ?? '') === 'annule');

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Transits & Correspondances</div>

<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card"><div class="stat-icon" style="background:var(--warning);"><i class="fas fa-clock"></i></div><div><div class="stat-val"><?= count($en_attente) ?></div><div class="stat-lbl">En attente</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:var(--success);"><i class="fas fa-check-circle"></i></div><div><div class="stat-val"><?= count($embarques) ?></div><div class="stat-lbl">Embarqués</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:var(--danger);"><i class="fas fa-times-circle"></i></div><div><div class="stat-val"><?= count($annules) ?></div><div class="stat-lbl">Annulés</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:var(--primary);"><i class="fas fa-exchange-alt"></i></div><div><div class="stat-val"><?= count($transits) ?></div><div class="stat-lbl">Total transits</div></div></div>
</div>

<!-- EN ATTENTE -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h3><i class="fas fa-clock" style="color:var(--warning);"></i> En attente de correspondance (<?= count($en_attente) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <?php if(empty($en_attente)): ?>
    <div class="t-empty"><i class="fas fa-check-circle" style="color:var(--success);"></i><br>Aucun passager en attente</div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Ticket</th><th>Passager</th><th>Voyage actuel</th><th>Descente à</th><th>Correspondance</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($en_attente as $t):
        $escDesc = '';
        if ($t['escale_descente_id']) {
            $ed = $pdo->prepare("SELECT a.ville FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.id=?");
            $ed->execute([$t['escale_descente_id']]);
            $escDesc = $ed->fetchColumn() ?: '';
        }
        ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code></td>
          <td><strong><?= sanitize($t['passager_nom']) ?></strong></td>
          <td><?= sanitize($t['dep']) ?> → <?= sanitize($t['arr']) ?> <span style="font-size:11px;color:var(--text3);">(<?= sanitize($t['voy_num']) ?>)</span></td>
          <td><?= $escDesc ? '<strong>'.sanitize($escDesc).'</strong>' : sanitize($t['arr']) ?></td>
          <td><?= $t['suite_nom'] ? '<span class="badge badge-purple">'.sanitize($t['suite_nom']).'</span>' : '<span class="badge badge-gray">Non définie</span>' ?></td>
          <td style="font-size:11px;"><?= date('d/m H:i',strtotime($t['date_depart'])) ?></td>
          <td><div style="display:flex;gap:3px;">
            <a href="?embarque=<?= $t['id'] ?>" class="btn btn-xs btn-success" onclick="return confirm('Confirmer l\'embarquement ?')" title="Embarqué"><i class="fas fa-check"></i></a>
            <a href="?annule_corr=<?= $t['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Annuler la correspondance ?')" title="Annuler"><i class="fas fa-times"></i></a>
          </div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php if(!empty($embarques)): ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h3><i class="fas fa-check-circle" style="color:var(--success);"></i> Embarqués (<?= count($embarques) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Ticket</th><th>Passager</th><th>Voyage</th><th>Correspondance</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach($embarques as $t): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code></td>
          <td><strong><?= sanitize($t['passager_nom']) ?></strong></td>
          <td><?= sanitize($t['dep']) ?> → <?= sanitize($t['arr']) ?></td>
          <td><?= $t['suite_nom'] ? '<span class="badge badge-green">'.sanitize($t['suite_nom']).'</span>' : '—' ?></td>
          <td style="font-size:11px;"><?= date('d/m H:i',strtotime($t['date_depart'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>