<?php
// modules/destinations/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('destinations.manage');
$pageTitle = 'Destinations';

if (isset($_GET['del']) && isSuperAdmin()) {
    $id = (int)$_GET['del'];
    $nb = $pdo->prepare("SELECT COUNT(*) FROM voyages WHERE destination_id=?");
    $nb->execute([$id]);
    if ((int)$nb->fetchColumn() > 0) {
        flash('Impossible : des voyages utilisent cette destination.', 'danger');
    } else {
        $pdo->prepare("DELETE FROM tarifs WHERE destination_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM destinations WHERE id=?")->execute([$id]);
        logAction($pdo, 'supprime_destination', 'destinations', "Destination $id supprimée");
        flash('Destination supprimée.', 'warning');
    }
    redirect(BASE_URL . 'modules/destinations/');
}
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE destinations SET actif=NOT actif WHERE id=?")->execute([$id]);
    flash('Statut modifié.');
    redirect(BASE_URL . 'modules/destinations/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $dep = (int)($_POST['agence_depart'] ?? 0);
    $arr = (int)($_POST['agence_arrivee'] ?? 0);
    $dist = (float)($_POST['distance_km'] ?? 0);
    $duree = (int)($_POST['duree_minutes'] ?? 0);

    if (!$dep || !$arr) {
        flash('Agences départ et arrivée obligatoires.', 'danger');
    } elseif ($dep === $arr) {
        flash('L\'agence de départ et d\'arrivée doivent être différentes.', 'danger');
    } else {
        $ck=$pdo->prepare("SELECT id FROM destinations WHERE agence_depart=? AND agence_arrivee=? AND id!=? LIMIT 1");
        $ck->execute([$dep,$arr,$id]);
        if($ck->fetch()){flash('Cette destination existe déjà.','danger');}
        else{
        if ($id) {
            $pdo->prepare("UPDATE destinations SET agence_depart=?,agence_arrivee=?,distance_km=?,duree_minutes=? WHERE id=?")
                ->execute([$dep, $arr, $dist, $duree, $id]);
            logAction($pdo, 'modifie_destination', 'destinations', "Destination $id modifiée");
            flash('Destination modifiée.');
        } else {
            $pdo->prepare("INSERT INTO destinations (agence_depart,agence_arrivee,distance_km,duree_minutes) VALUES (?,?,?,?)")
                ->execute([$dep, $arr, $dist, $duree]);
            logAction($pdo, 'ajoute_destination', 'destinations', "Destination ajoutée");
            flash('Destination ajoutée.');
        }
        redirect(BASE_URL . 'modules/destinations/');
    }
    }
}

$destinations = $pdo->query("SELECT d.*, a1.nom as dep_nom, a2.nom as arr_nom, (SELECT COUNT(*) FROM tarifs t WHERE t.destination_id=d.id) as nb_tarifs, (SELECT COUNT(*) FROM voyages v WHERE v.destination_id=d.id) as nb_voyages FROM destinations d JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id ORDER BY d.actif DESC, a1.nom, a2.nom")->fetchAll();
$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Destinations</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-map-marked-alt"></i> Destinations (<?= count($destinations) ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="resetDForm();openModal('dest-modal')"><i class="fas fa-plus"></i> Ajouter</button>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Départ</th><th>Arrivée</th><th>Distance</th><th>Durée</th><th>Tarifs</th><th>Voyages</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($destinations as $d): ?>
          <tr>
            <td><strong><?= sanitize($d['dep_nom']) ?></strong></td>
            <td><strong><?= sanitize($d['arr_nom']) ?></strong></td>
            <td><?= $d['distance_km'] ?> km</td>
            <td><?= ceil($d['duree_minutes'] / 60) ?>h<?= ($d['duree_minutes'] % 60) ? str_pad($d['duree_minutes'] % 60, 2, '0') : '' ?></td>
            <td style="text-align:center;"><?= $d['nb_tarifs'] ?></td>
            <td style="text-align:center;"><?= $d['nb_voyages'] ?></td>
            <td><?= $d['actif'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Inactif</span>' ?></td>
            <td>
              <div style="display:flex;gap:3px;">
                <button class="btn btn-xs btn-warning" onclick='editD(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
                <a href="?toggle=<?= $d['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?php echo $d['actif'] ? 'on text-success' : 'off'; ?>"></i></a>
                <?php if (isSuperAdmin()): ?>
                <a href="?del=<?= $d['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette destination ?')"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($destinations)): ?>
          <tr><td colspan="8" class="t-empty"><i class="fas fa-map-marked-alt"></i>Aucune destination</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-over" id="dest-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-map-marked-alt"></i> Destination</h3><button class="modal-x" onclick="closeModal('dest-modal')">✕</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <input type="hidden" name="id" id="d-id">
        <div class="form-grid">
          <div class="fg"><label class="flbl">Agence départ <span class="freq">*</span></label><select name="agence_depart" id="d-dep" class="fc" required><option value="">— Sélectionner —</option><?php foreach ($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option><?php endforeach; ?></select></div>
          <div class="fg"><label class="flbl">Agence arrivée <span class="freq">*</span></label><select name="agence_arrivee" id="d-arr" class="fc" required><option value="">— Sélectionner —</option><?php foreach ($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option><?php endforeach; ?></select></div>
          <div class="fg"><label class="flbl">Distance (km)</label><input type="number" name="distance_km" id="d-dist" class="fc" min="0" step="1"></div>
          <div class="fg"><label class="flbl">Durée (minutes)</label><input type="number" name="duree_minutes" id="d-duree" class="fc" min="0" step="1"></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('dest-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function resetDForm(){document.getElementById('d-id').value='';document.getElementById('d-dep').value='';document.getElementById('d-arr').value='';document.getElementById('d-dist').value='';document.getElementById('d-duree').value='';}
function editD(d) {
  document.getElementById('d-id').value = d.id;
  document.getElementById('d-dep').value = d.agence_depart || '';
  document.getElementById('d-arr').value = d.agence_arrivee || '';
  document.getElementById('d-dist').value = d.distance_km || '';
  document.getElementById('d-duree').value = d.duree_minutes || '';
  openModal('dest-modal');
}
</script>
<?php include '../../includes/footer.php'; ?>