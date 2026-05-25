<?php // modules/personnel/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('personnel.manage');
$pageTitle='Personnel (Chauffeurs & Convoyeurs)'; $aid=getUserAgenceId();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0); $nom=mb_strtoupper(trim($_POST['nom']??'')); $prenom=mb_strtoupper(trim($_POST['prenom']??'')); $fn=$_POST['fonction']??'chauffeur'; $tel=trim($_POST['telephone']??''); $permis=trim($_POST['permis']??''); $pe=$_POST['permis_exp']??null?:null; $cni=trim($_POST['cni']??''); $agid=(int)($_POST['agence_id']??$aid??0)?:null; $statut=$_POST['statut']??'actif';
    if(!$nom){flash('Nom obligatoire.','danger');}
    else{
        if($id){$pdo->prepare("UPDATE personnel SET nom=?,prenom=?,fonction=?,telephone=?,permis=?,permis_exp=?,cni=?,agence_id=?,statut=? WHERE id=?")->execute([$nom,$prenom,$fn,$tel,$permis,$pe,$cni,$agid,$statut,$id]);flash('Personnel modifié.');}
        else{
            $fnCount=$pdo->prepare("SELECT COUNT(*)+1 FROM personnel WHERE fonction=?");$fnCount->execute([$fn]);$mat=strtoupper(substr($fn,0,3)).str_pad($fnCount->fetchColumn(),3,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO personnel (matricule,nom,prenom,fonction,telephone,permis,permis_exp,cni,agence_id,statut) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$mat,$nom,$prenom,$fn,$tel,$permis,$pe,$cni,$agid,$statut]);
            flash('Personnel ajouté.');
        }
        redirect(BASE_URL.'modules/personnel/index.php');
    }
}
if(isset($_GET['del'])&&isSuperAdmin()){$pdo->prepare("DELETE FROM personnel WHERE id=?")->execute([$_GET['del']]);flash('Supprimé.','warning');redirect(BASE_URL.'modules/personnel/index.php');}
$wA=$aid&&!isAdmin()?"AND agence_id=$aid":"";
$perso=$pdo->query("SELECT p.*,a.nom as agence_nom FROM personnel p LEFT JOIN agences a ON p.agence_id=a.id WHERE 1=1 $wA ORDER BY p.fonction,p.nom")->fetchAll();
$agences=$pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Personnel</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-id-badge"></i> Personnel (<?= count($perso) ?>)</h3><button class="btn btn-primary btn-sm" onclick="resetPForm();openModal('pm')"><i class="fas fa-plus"></i> Ajouter</button></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Matricule</th><th>Nom & Prénom</th><th>Fonction</th><th>Téléphone</th><th>N° Permis</th><th>Exp. permis</th><th>N°CNI</th><th>Agence</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($perso as $p): $today=date('Y-m-d'); ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($p['matricule']??'—') ?></code></td>
          <td><strong><?= sanitize($p['nom'].' '.($p['prenom']??'')) ?></strong></td>
          <td><span class="badge <?= $p['fonction']==='chauffeur'?'badge-blue':'badge-amber' ?>"><?= sanitize($p['fonction']) ?></span></td>
          <td><?= sanitize($p['telephone']??'—') ?></td>
          <td><?= sanitize($p['permis']??'—') ?></td>
          <td style="font-size:12px;<?= $p['permis_exp']&&$p['permis_exp']<$today?'color:var(--danger);font-weight:600;':'' ?>"><?= fdate($p['permis_exp']??'') ?></td>
          <td style="font-size:12px;"><?= sanitize($p['cni']??'—') ?></td>
          <td><?= sanitize($p['agence_nom']??'—') ?></td>
          <td><span class="tag-statut st-<?= $p['statut'] ?>"><?= statutLabel($p['statut']) ?></span></td>
          <td><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick="editP(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button><?php if(isSuperAdmin()): ?><a href="?del=<?= $p['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ce personnel ?')"><i class="fas fa-trash"></i></a><?php endif; ?></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($perso)): ?><tr><td colspan="10" class="t-empty"><i class="fas fa-id-badge"></i> Aucun personnel</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<div class="modal-over" id="pm"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-id-badge"></i> Personnel</h3><button class="modal-x" onclick="closeModal('pm')">✕</button></div>
  <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
    <input type="hidden" name="id" id="pe-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="pe-nom" class="fc" required></div>
      <div class="fg"><label class="flbl">Prénom(s)</label><input type="text" name="prenom" id="pe-prenom" class="fc"></div>
      <div class="fg"><label class="flbl">Téléphone</label><input type="tel" name="telephone" id="pe-tel" class="fc"></div>
      <div class="fg"><label class="flbl">N° Permis</label><input type="text" name="permis" id="pe-permis" class="fc"></div>
      <div class="fg"><label class="flbl">Expiration permis</label><input type="date" name="permis_exp" id="pe-exp" class="fc"></div>
      <div class="fg"><label class="flbl">Numéro CNI</label><input type="text" name="cni" id="pe-cni" class="fc"></div>
      <div class="fg"><label class="flbl">Fonction</label><select name="fonction" id="pe-fn" class="fc"><option value="chauffeur">Chauffeur</option><option value="convoyeur">Convoyeur</option><option value="autre">Autre</option></select></div>
      <div class="fg"><label class="flbl">Statut</label><select name="statut" id="pe-statut" class="fc"><option value="actif">Actif</option><option value="inactif">Inactif</option><option value="conge">En congé</option></select></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('pm')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function resetPForm(){document.getElementById('pe-id').value='';document.getElementById('pe-nom').value='';document.getElementById('pe-prenom').value='';document.getElementById('pe-tel').value='';document.getElementById('pe-permis').value='';document.getElementById('pe-exp').value='';document.getElementById('pe-cni').value='';document.getElementById('pe-fn').value='chauffeur';document.getElementById('pe-statut').value='actif';}
function editP(p){document.getElementById('pe-id').value=p.id;document.getElementById('pe-nom').value=p.nom;document.getElementById('pe-prenom').value=p.prenom||'';document.getElementById('pe-tel').value=p.telephone||'';document.getElementById('pe-permis').value=p.permis||'';document.getElementById('pe-exp').value=p.permis_exp||'';document.getElementById('pe-cni').value=p.cni||'';document.getElementById('pe-fn').value=p.fonction||'chauffeur';document.getElementById('pe-statut').value=p.statut||'actif';openModal('pm');}</script>
<?php include '../../includes/footer.php'; ?>