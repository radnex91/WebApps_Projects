<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('personnel.view');
$pageTitle='Personnel';
if($_SERVER['REQUEST_METHOD']==='POST'&&can('personnel.manage')){
    $id=(int)($_POST['id']??0); $nom=trim($_POST['nom']??''); $prenom=trim($_POST['prenom']??''); $titre=trim($_POST['titre']??'');
    $ville=trim($_POST['ville']??''); $tel=trim($_POST['telephone']??''); $ag=(int)($_POST['agence_id']??0)?:null; $sal=(float)($_POST['indice_salaire']??0);
    if($nom){
        if($id){$pdo->prepare("UPDATE personnel SET nom=?,prenom=?,titre=?,ville=?,telephone=?,agence_id=?,indice_salaire=? WHERE id=?")->execute([$nom,$prenom,$titre,$ville,$tel,$ag,$sal,$id]);flash('Employé modifié.');}
        else{
            $ref='EMP'.str_pad($pdo->query("SELECT COUNT(*)+1 FROM personnel")->fetchColumn(),4,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO personnel (ref_employe,nom,prenom,titre,ville,telephone,agence_id,indice_salaire) VALUES (?,?,?,?,?,?,?,?)")->execute([$ref,$nom,$prenom,$titre,$ville,$tel,$ag,$sal]);flash('Employé ajouté.');
        }
        redirect(BASE_URL.'modules/personnel/');
    } else {flash('Nom obligatoire.','danger');}
}
$personnel=$pdo->query("SELECT p.*,a.nom as agence_nom FROM personnel p LEFT JOIN agences a ON p.agence_id=a.id WHERE p.actif=1 ORDER BY p.nom,p.prenom")->fetchAll();
$agences=$pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Personnel</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-id-badge"></i> Personnel (<?=count($personnel)?>)</h3><?php if(can('personnel.manage')): ?><button class="btn btn-primary btn-sm" onclick="openModal('pm');document.getElementById('pe-id').value=''"><i class="fas fa-plus"></i> Ajouter</button><?php endif; ?></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Réf.</th><th>Nom & Prénom</th><th>Titre/Poste</th><th>Agence</th><th>Téléphone</th><th>Salaire base</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($personnel as $p): ?>
      <tr>
        <td><code style="font-size:11px;"><?=h($p['ref_employe']??'—')?></code></td>
        <td><strong><?=h($p['nom'].' '.($p['prenom']??''))?></strong></td>
        <td><?=h($p['titre']??'—')?></td>
        <td style="font-size:12px;"><?=h($p['agence_nom']??'—')?></td>
        <td><?=h($p['telephone']??'—')?></td>
        <td><?=moneyRaw($p['indice_salaire'])?> FCFA</td>
        <td><?php if(can('personnel.manage')): ?><button class="btn btn-xs btn-warning" onclick="editP(<?=htmlspecialchars(json_encode($p),ENT_QUOTES)?>)"><i class="fas fa-edit"></i></button><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
<div class="modal-over" id="pm"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-id-badge"></i> Employé</h3><button class="modal-x" onclick="closeModal('pm')">✕</button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="id" id="pe-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="pe-nom" class="fc" required></div>
      <div class="fg"><label class="flbl">Prénom(s)</label><input type="text" name="prenom" id="pe-prenom" class="fc"></div>
      <div class="fg"><label class="flbl">Titre / Poste</label><input type="text" name="titre" id="pe-titre" class="fc" placeholder="Chef d'agence, Opérateur..."></div>
      <div class="fg"><label class="flbl">Ville</label><input type="text" name="ville" id="pe-ville" class="fc"></div>
      <div class="fg"><label class="flbl">Téléphone</label><input type="tel" name="telephone" id="pe-tel" class="fc"></div>
      <div class="fg"><label class="flbl">Agence de rattachement</label><select name="agence_id" id="pe-ag" class="fc"><option value="">— Aucune —</option><?php foreach($agences as $a): ?><option value="<?=$a['id']?>"><?=h($a['nom'])?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="flbl">Salaire de base (FCFA)</label><input type="number" name="indice_salaire" id="pe-sal" class="fc" value="0" min="0" step="1000"></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('pm')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function editP(p){document.getElementById('pe-id').value=p.id;document.getElementById('pe-nom').value=p.nom;document.getElementById('pe-prenom').value=p.prenom||'';document.getElementById('pe-titre').value=p.titre||'';document.getElementById('pe-ville').value=p.ville||'';document.getElementById('pe-tel').value=p.telephone||'';document.getElementById('pe-ag').value=p.agence_id||'';document.getElementById('pe-sal').value=p.indice_salaire||0;openModal('pm');}</script>
<?php include '../../includes/footer.php'; ?>