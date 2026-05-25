<?php // modules/vehicules/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('vehicules.manage');
$pageTitle='Véhicules'; $aid=getUserAgenceId();
if(isset($_GET['del'])&&isSuperAdmin()){$pdo->prepare("DELETE FROM vehicules WHERE id=?")->execute([$_GET['del']]);flash('Véhicule supprimé.','warning');redirect(BASE_URL.'modules/vehicules/index.php');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0); $immat=trim($_POST['immatriculation']??''); $marque=trim($_POST['marque']??''); $modele=trim($_POST['modele']??''); $cid=(int)($_POST['concessionnaire_id']??0)?:null; $cap=(int)($_POST['capacite']??0); $dateAchat=$_POST['date_achat']??null?:null; $gid=(int)($_POST['groupe_id']??0)?:null; $statut=$_POST['statut']??'actif';
    if(!$immat){flash('Immatriculation obligatoire.','danger');}
    else{
        if($id){$pdo->prepare("UPDATE vehicules SET immatriculation=?,marque=?,modele=?,concessionnaire_id=?,capacite=?,date_achat=?,groupe_id=?,statut=? WHERE id=?")->execute([$immat,$marque,$modele,$cid,$cap,$dateAchat,$gid,$statut,$id]);flash('Véhicule modifié.');}
        else{$pdo->prepare("INSERT INTO vehicules (immatriculation,marque,modele,concessionnaire_id,capacite,date_achat,groupe_id,statut) VALUES (?,?,?,?,?,?,?,?)")->execute([$immat,$marque,$modele,$cid,$cap,$dateAchat,$gid,$statut]);flash('Véhicule ajouté.');}
        redirect(BASE_URL.'modules/vehicules/index.php');
    }
}
$wA=$aid?"AND v.agence_id=$aid":""; $veh=$pdo->query("SELECT v.*,g.nom as groupe_nom,c.nom as concess_nom FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN concessionnaires c ON v.concessionnaire_id=c.id WHERE 1=1 $wA ORDER BY v.immatriculation")->fetchAll();
$groupes=$pdo->query("SELECT g.*, p.nom as prop_nom FROM groupes g LEFT JOIN proprietaires p ON g.proprietaire_id=p.id WHERE g.actif=1 ORDER BY g.nom")->fetchAll();
$concess=$pdo->query("SELECT * FROM concessionnaires WHERE actif=1 ORDER BY nom")->fetchAll();
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Véhicules</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-bus"></i> Véhicules (<?= count($veh) ?>)</h3><div style="display:flex;gap:6px;"><a href="import.php" class="btn btn-info btn-sm"><i class="fas fa-file-import"></i> Importer CSV</a><button class="btn btn-primary btn-sm" onclick="resetVForm();openModal('veh-modal')"><i class="fas fa-plus"></i> Ajouter</button></div></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Immatriculation</th><th>Marque / Modèle</th><th>Concessionnaire</th><th>Places</th><th>Date achat</th><th>Groupe</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($veh as $v): ?>
        <tr>
          <td><strong><?= sanitize($v['immatriculation']) ?></strong></td>
          <td><?= sanitize($v['marque'].' '.$v['modele']) ?></td>
          <td style="font-size:12px;"><?= sanitize($v['concess_nom']??'—') ?></td>
          <td style="text-align:center;"><?= $v['capacite'] ?></td>
          <td style="font-size:12px;"><?= fdate($v['date_achat']??'') ?></td>
          <td><?= sanitize($v['groupe_nom']??'—') ?></td>
          <td><span class="tag-statut st-<?= $v['statut'] ?>"><?= statutLabel($v['statut']) ?></span></td>
          <td><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick="editV(<?= htmlspecialchars(json_encode($v),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button><?php if(isSuperAdmin()): ?><a href="?del=<?= $v['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ce véhicule ?')"><i class="fas fa-trash"></i></a><?php endif; ?></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($veh)): ?><tr><td colspan="8" class="t-empty"><i class="fas fa-bus"></i> Aucun véhicule</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<div class="modal-over" id="veh-modal"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-bus"></i> Véhicule</h3><button class="modal-x" onclick="closeModal('veh-modal')">✕</button></div>
  <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
    <input type="hidden" name="id" id="v-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Immatriculation <span class="freq">*</span></label><input type="text" name="immatriculation" id="v-immat" class="fc" required style="text-transform:uppercase;"></div>
      <div class="fg"><label class="flbl">Marque</label><input type="text" name="marque" id="v-marque" class="fc" placeholder="Mercedes, Toyota..."></div>
      <div class="fg"><label class="flbl">Concessionnaire</label><select name="concessionnaire_id" id="v-concess" class="fc"><option value="">—</option><?php foreach($concess as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nom']) ?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="flbl">Modèle</label><input type="text" name="modele" id="v-modele" class="fc"></div>
      <div class="fg"><label class="flbl">Nombre de places</label><input type="number" name="capacite" id="v-cap" class="fc" min="1"></div>
      <div class="fg"><label class="flbl">Date d'achat</label><input type="date" name="date_achat" id="v-dateachat" class="fc"></div>
      <div class="fg"><label class="flbl">Groupe</label><select name="groupe_id" id="v-groupe" class="fc"><option value="">—</option><?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>"><?= sanitize($g['nom']) ?><?php if(!empty($g['prop_nom'])): ?> (<?= sanitize($g['prop_nom']) ?>)<?php endif; ?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="flbl">Statut</label><select name="statut" id="v-statut" class="fc"><option value="actif">Actif</option><option value="panne">En panne</option><option value="maintenance">Maintenance</option><option value="hors_service">Hors service</option></select></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('veh-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function resetVForm(){document.getElementById('v-id').value='';document.getElementById('v-immat').value='';document.getElementById('v-marque').value='';document.getElementById('v-concess').value='';document.getElementById('v-modele').value='';document.getElementById('v-cap').value='';document.getElementById('v-dateachat').value='';document.getElementById('v-groupe').value='';document.getElementById('v-statut').value='actif';}
function editV(v){document.getElementById('v-id').value=v.id;document.getElementById('v-immat').value=v.immatriculation;document.getElementById('v-marque').value=v.marque||'';document.getElementById('v-concess').value=v.concessionnaire_id||'';document.getElementById('v-modele').value=v.modele||'';document.getElementById('v-cap').value=v.capacite||'';document.getElementById('v-dateachat').value=v.date_achat||'';document.getElementById('v-groupe').value=v.groupe_id||'';document.getElementById('v-statut').value=v.statut||'actif';openModal('veh-modal');}</script>
<?php include '../../includes/footer.php'; ?>