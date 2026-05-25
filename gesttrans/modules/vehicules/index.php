<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('vehicules.view');
$pageTitle='Parc Automobile';
if($_SERVER['REQUEST_METHOD']==='POST'&&can('vehicules.manage')){
    $id=(int)($_POST['id']??0); $immat=strtoupper(trim($_POST['immatriculation']??'')); $marque=trim($_POST['marque']??''); $modele=trim($_POST['modele']??''); $desc=trim($_POST['description']??'');
    $grp=(int)($_POST['groupe_id']??0)?:null; $cap=(int)($_POST['capacite']??70);
    if($immat){
        if($id){$pdo->prepare("UPDATE vehicules SET immatriculation=?,marque=?,modele=?,description=?,groupe_id=?,capacite=? WHERE id=?")->execute([$immat,$marque,$modele,$desc,$grp,$cap,$id]);flash('Véhicule modifié.');}
        else{$pdo->prepare("INSERT INTO vehicules (immatriculation,marque,modele,description,groupe_id,capacite) VALUES (?,?,?,?,?,?)")->execute([$immat,$marque,$modele,$desc,$grp,$cap]);flash('Véhicule ajouté.');}
        redirect(BASE_URL.'modules/vehicules/');
    } else {flash('Immatriculation obligatoire.','danger');}
}
if(isset($_GET['toggle'])&&can('vehicules.manage')){$pdo->prepare("UPDATE vehicules SET actif=NOT actif WHERE id=?")->execute([$_GET['toggle']]);flash('Statut modifié.');redirect(BASE_URL.'modules/vehicules/');}
$vehicules=$pdo->query("SELECT v.*,g.nom as groupe_nom FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id ORDER BY v.immatriculation")->fetchAll();
$groupes=$pdo->query("SELECT id,nom FROM groupes WHERE actif=1 ORDER BY nom")->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Véhicules</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-bus"></i> Parc automobile (<?=count($vehicules)?>)</h3><?php if(can('vehicules.manage')): ?><button class="btn btn-primary btn-sm" onclick="openModal('vm');document.getElementById('v-id').value=''"><i class="fas fa-plus"></i> Ajouter</button><?php endif; ?></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Immatriculation</th><th>Marque</th><th>Modèle</th><th>Description</th><th>Groupe</th><th>Capacité</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($vehicules as $v): ?>
      <tr>
        <td><code style="font-size:13px;font-weight:700;"><?=h($v['immatriculation'])?></code></td>
        <td><?=h($v['marque']??'—')?></td>
        <td><?=h($v['modele']??'—')?></td>
        <td style="font-size:12px;"><?=h($v['description']??'—')?></td>
        <td><span class="badge b-purple"><?=h($v['groupe_nom']??'—')?></span></td>
        <td style="text-align:center;"><?=$v['capacite']?></td>
        <td><?=$v['actif']?'<span class="badge b-green">Actif</span>':'<span class="badge b-gray">Inactif</span>'?></td>
        <td><div style="display:flex;gap:3px;"><?php if(can('vehicules.manage')): ?><button class="btn btn-xs btn-warning" onclick="editV(<?=htmlspecialchars(json_encode($v),ENT_QUOTES)?>)"><i class="fas fa-edit"></i></button><a href="?toggle=<?=$v['id']?>" class="btn btn-xs btn-ghost"><i class="fas fa-toggle-<?=$v['actif']?'on':'off'?>"></i></a><?php endif; ?></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
<div class="modal-over" id="vm"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-bus"></i> Véhicule</h3><button class="modal-x" onclick="closeModal('vm')">✕</button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="id" id="v-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Immatriculation <span class="freq">*</span></label><input type="text" name="immatriculation" id="v-immat" class="fc" required style="text-transform:uppercase;font-family:monospace;" placeholder="LT 264 MFl"></div>
      <div class="fg"><label class="flbl">Marque</label><input type="text" name="marque" id="v-marque" class="fc" placeholder="Mercedes, Toyota..."></div>
      <div class="fg"><label class="flbl">Modèle</label><input type="text" name="modele" id="v-modele" class="fc"></div>
      <div class="fg"><label class="flbl">Groupe propriétaire</label><select name="groupe_id" id="v-grp" class="fc"><option value="">— Aucun —</option><?php foreach($groupes as $g): ?><option value="<?=$g['id']?>"><?=h($g['nom'])?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="flbl">Capacité (places)</label><input type="number" name="capacite" id="v-cap" class="fc" value="70" min="1" max="200"></div>
      <div class="fg full"><label class="flbl">Description</label><input type="text" name="description" id="v-desc" class="fc"></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('vm')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function editV(v){document.getElementById('v-id').value=v.id;document.getElementById('v-immat').value=v.immatriculation;document.getElementById('v-marque').value=v.marque||'';document.getElementById('v-modele').value=v.modele||'';document.getElementById('v-grp').value=v.groupe_id||'';document.getElementById('v-cap').value=v.capacite||70;document.getElementById('v-desc').value=v.description||'';openModal('vm');}</script>
<?php include '../../includes/footer.php'; ?>