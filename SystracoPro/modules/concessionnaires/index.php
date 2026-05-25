<?php // modules/concessionnaires/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('concessionnaires.manage');
$pageTitle='Concessionnaires';

if(isset($_GET['toggle'])){$pdo->prepare("UPDATE concessionnaires SET actif=NOT actif WHERE id=?")->execute([(int)$_GET['toggle']]);flash('Statut modifié.');redirect(BASE_URL.'modules/concessionnaires/');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0); $nom=trim($_POST['nom']??''); $tel=trim($_POST['telephone']??''); $adr=trim($_POST['adresse']??'');
    if(!$nom){flash('Nom obligatoire.','danger');}
    else{
        // Vérifier doublon
        $ck=$pdo->prepare("SELECT id FROM concessionnaires WHERE nom=? AND id!=? LIMIT 1");$ck->execute([$nom,$id]);
        if($ck->fetch()){flash('Ce concessionnaire existe déjà.','danger');}
        else{
            if($id){$pdo->prepare("UPDATE concessionnaires SET nom=?,telephone=?,adresse=? WHERE id=?")->execute([$nom,$tel,$adr,$id]);flash('Concessionnaire modifié.');}
            else{$pdo->prepare("INSERT INTO concessionnaires (nom,telephone,adresse) VALUES (?,?,?)")->execute([$nom,$tel,$adr]);flash('Concessionnaire créé.');}
            redirect(BASE_URL.'modules/concessionnaires/');
        }
    }
}
$concess=$pdo->query("SELECT c.*,COUNT(v.id) as nb_veh FROM concessionnaires c LEFT JOIN vehicules v ON v.concessionnaire_id=c.id GROUP BY c.id ORDER BY c.nom")->fetchAll();
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Concessionnaires</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-store"></i> Concessionnaires (<?= count($concess) ?>)</h3><button class="btn btn-primary btn-sm" onclick="resetCForm();openModal('cm')"><i class="fas fa-plus"></i> Nouveau</button></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Nom</th><th>Téléphone</th><th>Adresse</th><th>Véhicules</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($concess as $c): ?>
        <tr>
          <td><strong><?= sanitize($c['nom']) ?></strong></td>
          <td><?= sanitize($c['telephone']??'—') ?></td>
          <td style="font-size:12px;"><?= sanitize($c['adresse']??'—') ?></td>
          <td style="text-align:center;"><?= $c['nb_veh'] ?></td>
          <td><?= $c['actif']?'<span class="badge badge-green">Actif</span>':'<span class="badge badge-red">Inactif</span>' ?></td>
          <td><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick="editC(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button><a href="?toggle=<?= $c['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?= $c['actif']?'on':'off' ?>"></i></a></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($concess)): ?><tr><td colspan="6" class="t-empty"><i class="fas fa-store"></i> Aucun concessionnaire</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<div class="modal-over" id="cm"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-store"></i> Concessionnaire</h3><button class="modal-x" onclick="closeModal('cm')">✕</button></div>
  <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
    <input type="hidden" name="id" id="c-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="c-nom" class="fc" required></div>
      <div class="fg"><label class="flbl">Téléphone</label><input type="tel" name="telephone" id="c-tel" class="fc"></div>
      <div class="fg full"><label class="flbl">Adresse</label><input type="text" name="adresse" id="c-adr" class="fc"></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('cm')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function resetCForm(){document.getElementById('c-id').value='';document.getElementById('c-nom').value='';document.getElementById('c-tel').value='';document.getElementById('c-adr').value='';}
function editC(c){document.getElementById('c-id').value=c.id;document.getElementById('c-nom').value=c.nom;document.getElementById('c-tel').value=c.telephone||'';document.getElementById('c-adr').value=c.adresse||'';openModal('cm');}</script>
<?php include '../../includes/footer.php'; ?>