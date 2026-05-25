<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('agences.view');
$pageTitle='Agences du Réseau';
if($_SERVER['REQUEST_METHOD']==='POST'&&can('agences.manage')){
    $id=(int)($_POST['id']??0); $code=strtoupper(trim($_POST['code']??'')); $nom=trim($_POST['nom']??''); $ville=trim($_POST['ville']??'');
    $type=$_POST['type_agence']??'Terminus'; $tel=trim($_POST['telephone']??''); $email=trim($_POST['email']??''); $contact=trim($_POST['nom_contact']??''); $reg=trim($_POST['region']??''); $adr=trim($_POST['adresse']??'');
    if($nom&&$ville){
        if($id){$pdo->prepare("UPDATE agences SET code=?,nom=?,ville=?,region=?,adresse=?,telephone=?,email=?,type_agence=?,nom_contact=? WHERE id=?")->execute([$code,$nom,$ville,$reg,$adr,$tel,$email,$type,$contact,$id]);flash('Agence modifiée.');}
        else{$pdo->prepare("INSERT INTO agences (code,nom,ville,region,adresse,telephone,email,type_agence,nom_contact) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$code,$nom,$ville,$reg,$adr,$tel,$email,$type,$contact]);flash('Agence créée.');}
        redirect(BASE_URL.'modules/agences/');
    } else {flash('Nom et ville obligatoires.','danger');}
}
if(isset($_GET['toggle'])&&can('agences.manage')){$pdo->prepare("UPDATE agences SET actif=NOT actif WHERE id=?")->execute([$_GET['toggle']]);flash('Statut modifié.');redirect(BASE_URL.'modules/agences/');}
$agences=$pdo->query("SELECT a.*,COUNT(DISTINCT v.id) as nb_veh,COUNT(DISTINCT p.id) as nb_pers FROM agences a LEFT JOIN vehicules v ON v.actif=1 LEFT JOIN personnel p ON p.agence_id=a.id GROUP BY a.id ORDER BY a.nom")->fetchAll();
$types=['Terminus','Escale','Catégorie A','Catégorie B','Catégorie C','Prestige','Direction','Partenaire'];
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Agences</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-building"></i> Agences (<?=count($agences)?>)</h3><?php if(can('agences.manage')): ?><button class="btn btn-primary btn-sm" onclick="openModal('ag-modal');document.getElementById('ag-id').value=''"><i class="fas fa-plus"></i> Ajouter</button><?php endif; ?></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Nom</th><th>Ville</th><th>Région</th><th>Type</th><th>Téléphone</th><th>Contact</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($agences as $a): ?>
      <tr>
        <td><code><?=h($a['code'])?></code></td>
        <td><strong><?=h($a['nom'])?></strong></td>
        <td><?=h($a['ville'])?></td>
        <td><?=h($a['region']??'—')?></td>
        <td><span class="badge b-blue"><?=h($a['type_agence'])?></span></td>
        <td style="font-size:12px;"><?=h($a['telephone']??'—')?></td>
        <td style="font-size:12px;"><?=h($a['nom_contact']??'—')?></td>
        <td><?=$a['actif']?'<span class="badge b-green">Active</span>':'<span class="badge b-gray">Inactive</span>'?></td>
        <td><?php if(can('agences.manage')): ?><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick="editAg(<?=htmlspecialchars(json_encode($a),ENT_QUOTES)?>)"><i class="fas fa-edit"></i></button><a href="?toggle=<?=$a['id']?>" class="btn btn-xs btn-ghost"><i class="fas fa-toggle-<?=$a['actif']?'on':'off'?>"></i></a></div><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
<div class="modal-over" id="ag-modal"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-building"></i> Agence</h3><button class="modal-x" onclick="closeModal('ag-modal')">✕</button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="id" id="ag-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Code</label><input type="text" name="code" id="ag-code" class="fc" maxlength="10" placeholder="YA001" style="text-transform:uppercase;"></div>
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="ag-nom" class="fc" required></div>
      <div class="fg"><label class="flbl">Ville <span class="freq">*</span></label><input type="text" name="ville" id="ag-ville" class="fc" required></div>
      <div class="fg"><label class="flbl">Région</label><input type="text" name="region" id="ag-reg" class="fc"></div>
      <div class="fg"><label class="flbl">Type</label><select name="type_agence" id="ag-type" class="fc"><?php foreach($types as $t): ?><option value="<?=h($t)?>"><?=h($t)?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="flbl">Téléphone</label><input type="tel" name="telephone" id="ag-tel" class="fc"></div>
      <div class="fg"><label class="flbl">Email</label><input type="email" name="email" id="ag-email" class="fc"></div>
      <div class="fg"><label class="flbl">Responsable</label><input type="text" name="nom_contact" id="ag-contact" class="fc"></div>
      <div class="fg full"><label class="flbl">Adresse</label><input type="text" name="adresse" id="ag-adr" class="fc"></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('ag-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function editAg(a){document.getElementById('ag-id').value=a.id;document.getElementById('ag-code').value=a.code||'';document.getElementById('ag-nom').value=a.nom;document.getElementById('ag-ville').value=a.ville;document.getElementById('ag-reg').value=a.region||'';document.getElementById('ag-type').value=a.type_agence||'Terminus';document.getElementById('ag-tel').value=a.telephone||'';document.getElementById('ag-email').value=a.email||'';document.getElementById('ag-contact').value=a.nom_contact||'';document.getElementById('ag-adr').value=a.adresse||'';openModal('ag-modal');}</script>
<?php include '../../includes/footer.php'; ?>