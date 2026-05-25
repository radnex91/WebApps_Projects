<?php
// modules/correspondances/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('correspondances.manage');
$pageTitle = 'Correspondances';

if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM correspondances WHERE id=?")->execute([$id]);
    flash('Correspondance supprimée.','warning');
    redirect(BASE_URL.'modules/correspondances/');
}
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE correspondances SET actif=IF(actif=1,0,1) WHERE id=?")->execute([$id]);
    flash('Statut modifié.');
    redirect(BASE_URL.'modules/correspondances/');
}

$correspondances = $pdo->query("SELECT c.*, a.ville as agence_ville, i1.nom as arrivee_nom, i1.code as arrivee_code, i2.nom as depart_nom, i2.code as depart_code FROM correspondances c JOIN agences a ON c.agence_id=a.id JOIN itineraires i1 ON c.itineraire_arrivee_id=i1.id JOIN itineraires i2 ON c.itineraire_depart_id=i2.id ORDER BY a.ville, i1.nom")->fetchAll();

$parAgence = [];
foreach ($correspondances as $c) {
    $parAgence[$c['agence_ville']][] = $c;
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Correspondances</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-exchange-alt"></i> Correspondances (<?= count($correspondances) ?>)</h3>
    <a href="ajouter.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Nouvelle correspondance</a>
  </div>
</div>

<?php foreach($parAgence as $ville => $corrs): ?>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header"><h3><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> <?= sanitize($ville) ?></h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Itinéraire arrivée</th><th>Itinéraire départ</th><th>Délai min</th><th>Délai max</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($corrs as $c): ?>
        <tr>
          <td><strong><?= sanitize($c['arrivee_nom']) ?></strong> <span style="font-size:11px;color:var(--text3);"><?= $c['arrivee_code'] ?></span></td>
          <td><strong><?= sanitize($c['depart_nom']) ?></strong> <span style="font-size:11px;color:var(--text3);"><?= $c['depart_code'] ?></span></td>
          <td style="text-align:center;"><?= $c['delai_min'] ?> min</td>
          <td style="text-align:center;"><?= $c['delai_max'] ?> min</td>
          <td><span class="tag-statut <?= $c['actif']?'st-actif':'st-inactif' ?>"><?= statutLabel($c['actif']?'actif':'inactif') ?></span></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="?toggle=<?= $c['id'] ?>" class="btn btn-xs btn-<?= $c['actif']?'warning':'success' ?>" title="<?= $c['actif']?'Désactiver':'Activer' ?>" onclick="return confirm('Changer le statut ?')"><i class="fas fa-<?= $c['actif']?'pause':'play' ?>"></i></a>
              <a href="?supprimer=<?= $c['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette correspondance ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php if(empty($correspondances)): ?>
<div class="empty card"><i class="fas fa-exchange-alt"></i><h3>Aucune correspondance</h3><p>Définissez des correspondances entre itinéraires pour faciliter les transits.</p></div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>