<?php
// modules/itineraires/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('itineraires.manage');
$pageTitle = 'Itinéraires';
$aid = getUserAgenceId();

// Suppression définitive
if (isset($_GET['supprimer']) && isSuperAdmin()) {
    $id = (int)$_GET['supprimer'];
    $nb = $pdo->prepare("SELECT COUNT(*) FROM voyages WHERE itineraire_id=?");
    $nb->execute([$id]);
    if ((int)$nb->fetchColumn() > 0) {
        flash('Impossible : des voyages utilisent cet itinéraire. Modifiez-les d\'abord.','danger');
    } else {
        $pdo->prepare("DELETE FROM correspondances WHERE itineraire_arrivee_id=? OR itineraire_depart_id=?")->execute([$id,$id]);
        $pdo->prepare("DELETE FROM itineraire_escales WHERE itineraire_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM itineraires WHERE id=?")->execute([$id]);
        logAction($pdo,'supprime_itineraire','itineraires',"Itinéraire $id supprimé");
        flash('Itinéraire supprimé définitivement.','warning');
    }
    redirect(BASE_URL.'modules/itineraires/');
}
// Désactivation / Réactivation
if (isset($_GET['desactiver']) && isSuperAdmin()) {
    $id = (int)$_GET['desactiver'];
    $pdo->prepare("UPDATE itineraires SET actif=0 WHERE id=?")->execute([$id]);
    logAction($pdo,'desactive_itineraire','itineraires',"Itinéraire $id désactivé");
    flash('Itinéraire désactivé.','warning');
    redirect(BASE_URL.'modules/itineraires/');
}
if (isset($_GET['activer']) && isSuperAdmin()) {
    $id = (int)$_GET['activer'];
    $pdo->prepare("UPDATE itineraires SET actif=1 WHERE id=?")->execute([$id]);
    logAction($pdo,'active_itineraire','itineraires',"Itinéraire $id activé");
    flash('Itinéraire activé.');
    redirect(BASE_URL.'modules/itineraires/');
}

$itineraires = $pdo->query("SELECT i.*, a1.ville as dep, a2.ville as arr, (SELECT COUNT(*) FROM itineraire_escales WHERE itineraire_id=i.id) as nb_escales FROM itineraires i JOIN agences a1 ON i.agence_depart=a1.id JOIN agences a2 ON i.agence_arrivee=a2.id ORDER BY i.actif DESC, i.nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Itinéraires</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-route"></i> Itinéraires (<?= count($itineraires) ?>)</h3>
    <a href="ajouter.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Nouvel itinéraire</a>
  </div>
</div>

<?php foreach($itineraires as $it): ?>
<div class="card" style="margin-bottom:12px;">
  <div class="card-header" style="cursor:pointer;" onclick="toggleEscales(<?= $it['id'] ?>)">
    <h3>
      <i class="fas fa-route" style="color:var(--primary);"></i>
      <?= sanitize($it['nom']) ?>
      <span style="font-size:11px;color:var(--text3);font-weight:400;margin-left:8px;"><?= $it['code'] ?></span>
      <?php if(!$it['actif']): ?><span class="badge badge-red" style="margin-left:6px;">Inactif</span><?php endif; ?>
      <span class="badge badge-blue" style="margin-left:6px;"><?= $it['nb_escales'] ?> escale(s)</span>
      <span class="badge badge-gray" style="margin-left:4px;"><?= $it['distance_km'] ?> km — <?= ceil($it['duree_minutes']/60) ?>h<?= ($it['duree_minutes']%60)?str_pad($it['duree_minutes']%60,2,'0'):'' ?></span>
    </h3>
    <div style="display:flex;gap:6px;">
      <a href="modifier.php?id=<?= $it['id'] ?>" class="btn btn-xs btn-primary" onclick="event.stopPropagation()"><i class="fas fa-edit"></i></a>
      <?php if($it['actif']): ?>
      <a href="?desactiver=<?= $it['id'] ?>" class="btn btn-xs btn-danger" onclick="event.stopPropagation();return confirm('Désactiver cet itinéraire ?')"><i class="fas fa-times"></i></a>
      <?php else: ?>
      <a href="?activer=<?= $it['id'] ?>" class="btn btn-xs btn-success" onclick="event.stopPropagation();return confirm('Activer cet itinéraire ?')"><i class="fas fa-check"></i></a>
      <?php endif; ?>
      <a href="?supprimer=<?= $it['id'] ?>" class="btn btn-xs btn-danger" onclick="event.stopPropagation();return confirm('Supprimer définitivement cet itinéraire et ses escales ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
      <i class="fas fa-chevron-down" id="chevron-<?= $it['id'] ?>" style="color:var(--text3);margin-left:6px;transition:transform .2s;"></i>
    </div>
  </div>
  <div id="escales-<?= $it['id'] ?>" style="display:none;">
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>N°</th><th>Agence / Ville</th><th>Distance</th><th>Durée</th><th>Type</th></tr></thead>
        <tbody>
          <?php
          $escales = $pdo->prepare("SELECT e.*, a.ville, a.code FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
          $escales->execute([$it['id']]); $escs = $escales->fetchAll(); $last = count($escs);
          foreach($escs as $k => $esc): ?>
          <tr>
            <td style="text-align:center;font-weight:700;"><?= $esc['ordre'] ?></td>
            <td>
              <strong><?= sanitize($esc['ville']) ?></strong>
              <span style="font-size:11px;color:var(--text3);margin-left:6px;"><?= sanitize($esc['code']) ?></span>
            </td>
            <td><?= $esc['distance_debut'] ?> km</td>
            <td><?= $esc['duree_debut'] ?> min</td>
            <td>
              <?php if($k===0): ?><span class="badge badge-green">Départ</span>
              <?php elseif($k===$last-1): ?><span class="badge badge-red">Arrivée</span>
              <?php else: ?><span class="badge badge-amber">Escale</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Correspondances à cette escale -->
      <?php
      $corrs = $pdo->prepare("SELECT c.*, i2.nom as it_depart, i3.nom as it_arrivee FROM correspondances c JOIN itineraires i2 ON c.itineraire_depart_id=i2.id JOIN itineraires i3 ON c.itineraire_arrivee_id=i3.id WHERE c.itineraire_arrivee_id=? OR c.itineraire_depart_id=?");
      $corrs->execute([$it['id'],$it['id']]); $corrList = $corrs->fetchAll();
      if($corrList): ?>
      <div style="padding:12px 18px;border-top:1px solid var(--border);background:#fafbfc;">
        <div style="font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;margin-bottom:8px;"><i class="fas fa-exchange-alt"></i> Correspondances</div>
        <?php foreach($corrList as $c): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:12px;">
          <span class="badge badge-purple"><?= $c['itineraire_arrivee_id']==$it['id']?'Arrivée':'Départ' ?></span>
          <span><?= sanitize($c['it_arrivee']) ?> → <?= sanitize($c['it_depart']) ?></span>
          <span style="color:var(--text3);">délai <?= $c['delai_min'] ?>-<?= $c['delai_max'] ?> min</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if(empty($itineraires)): ?>
<div class="empty card"><i class="fas fa-route"></i><h3>Aucun itinéraire</h3><p>Créez votre premier itinéraire avec ses escales.</p></div>
<?php endif; ?>

<script>
function toggleEscales(id) {
    const el = document.getElementById('escales-'+id);
    const ch = document.getElementById('chevron-'+id);
    if (el.style.display==='none') { el.style.display=''; ch.style.transform='rotate(180deg)'; }
    else { el.style.display='none'; ch.style.transform=''; }
}
</script>
<?php include '../../includes/footer.php'; ?>