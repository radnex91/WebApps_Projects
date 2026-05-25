<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('groupes.view');
$pageTitle='Groupes / Actionnaires';
if($_SERVER['REQUEST_METHOD']==='POST'&&can('groupes.manage')){
    $id=(int)($_POST['id']??0); $code=strtoupper(trim($_POST['code']??'')); $nom=trim($_POST['nom']??''); $contact=trim($_POST['nom_contact']??'');
    $ville=trim($_POST['ville']??''); $banque=trim($_POST['banque']??''); $compte=trim($_POST['num_compte_bancaire']??''); $titulaire=trim($_POST['nom_titulaire_compte']??'');
    if($nom){
        if($id){$pdo->prepare("UPDATE groupes SET code=?,nom=?,nom_contact=?,ville=?,banque=?,num_compte_bancaire=?,nom_titulaire_compte=? WHERE id=?")->execute([$code,$nom,$contact,$ville,$banque,$compte,$titulaire,$id]);flash('Groupe modifié.');}
        else{$pdo->prepare("INSERT INTO groupes (code,nom,nom_contact,ville,banque,num_compte_bancaire,nom_titulaire_compte) VALUES (?,?,?,?,?,?,?)")->execute([$code,$nom,$contact,$ville,$banque,$compte,$titulaire]);flash('Groupe créé.');}
        redirect(BASE_URL.'modules/groupes/');
    } else {flash('Nom obligatoire.','danger');}
}
$groupes=$pdo->query("SELECT g.*,COUNT(v.id) as nb_veh FROM groupes g LEFT JOIN vehicules v ON v.groupe_id=g.id GROUP BY g.id ORDER BY g.nom")->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Groupes</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-layer-group"></i> Groupes / Actionnaires (<?=count($groupes)?>)</h3><?php if(can('groupes.manage')): ?><button class="btn btn-primary btn-sm" onclick="openModal('gm');document.getElementById('g-id').value=''"><i class="fas fa-plus"></i> Ajouter</button><?php endif; ?></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Nom du groupe</th><th>Contact</th><th>Ville</th><th>Banque</th><th>N° Compte</th><th>Titulaire</th><th>Véhicules</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($groupes as $g): ?>
      <tr>
        <td><code><?=h($g['code'])?></code></td>
        <td><strong><?=h($g['nom'])?></strong></td>
        <td><?=h($g['nom_contact']??'—')?></td>
        <td><?=h($g['ville']??'—')?></td>
        <td style="font-size:12px;"><?=h($g['banque']??'—')?></td>
        <td style="font-size:11px;font-family:monospace;"><?=h($g['num_compte_bancaire']??'—')?></td>
        <td style="font-size:12px;"><?=h($g['nom_titulaire_compte']??'—')?></td>
        <td style="text-align:center;"><span class="badge b-blue"><?=$g['nb_veh']?></span></td>
        <td><?php if(can('groupes.manage')): ?><button class="btn btn-xs btn-warning" onclick="editG(<?=htmlspecialchars(json_encode($g),ENT_QUOTES)?>)"><i class="fas fa-edit"></i></button><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
<div class="modal-over" id="gm"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-layer-group"></i> Groupe</h3><button class="modal-x" onclick="closeModal('gm')">✕</button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="id" id="g-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Code</label><input type="text" name="code" id="g-code" class="fc" style="text-transform:uppercase;"></div>
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="g-nom" class="fc" required></div>
      <div class="fg"><label class="flbl">Nom contact</label><input type="text" name="nom_contact" id="g-contact" class="fc"></div>
      <div class="fg"><label class="flbl">Ville</label><input type="text" name="ville" id="g-ville" class="fc"></div>
      <div class="fg"><label class="flbl">Banque</label><input type="text" name="banque" id="g-banque" class="fc" placeholder="SGBC, AFRILAND..."></div>
      <div class="fg"><label class="flbl">N° Compte bancaire</label><input type="text" name="num_compte_bancaire" id="g-compte" class="fc"></div>
      <div class="fg full"><label class="flbl">Titulaire compte</label><input type="text" name="nom_titulaire_compte" id="g-titulaire" class="fc"></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('gm')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function editG(g){document.getElementById('g-id').value=g.id;document.getElementById('g-code').value=g.code||'';document.getElementById('g-nom').value=g.nom;document.getElementById('g-contact').value=g.nom_contact||'';document.getElementById('g-ville').value=g.ville||'';document.getElementById('g-banque').value=g.banque||'';document.getElementById('g-compte').value=g.num_compte_bancaire||'';document.getElementById('g-titulaire').value=g.nom_titulaire_compte||'';openModal('gm');}</script>
<?php include '../../includes/footer.php'; ?>