<?php // modules/agences/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('agences.manage');
$pageTitle='Agences';

// Template download
if(isset($_GET['template'])){header('Content-Type:text/csv;charset=utf-8');header('Content-Disposition:attachment;filename=agences_template.csv');echo "code;nom;responsable\nYDE;Gare Routière de Yaoundé;Jean Dupont\nDLA;Gare Routière de Douala;Marie Tchou\n";exit;}

if(isset($_GET['toggle'])){$pdo->prepare("UPDATE agences SET actif=NOT actif WHERE id=?")->execute([(int)$_GET['toggle']]);flash('Statut modifié.');redirect(BASE_URL.'modules/agences/index.php');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0); $code=strtoupper(trim($_POST['code']??'')); $nom=trim($_POST['nom']??''); $resp=trim($_POST['responsable']??'');
    if(!$code||!$nom){flash('Code et nom obligatoires.','danger');}
    else{
        if($id){$pdo->prepare("UPDATE agences SET code=?,nom=?,responsable=? WHERE id=?")->execute([$code,$nom,$resp,$id]);flash('Agence modifiée.');}
        else{$pdo->prepare("INSERT INTO agences (code,nom,responsable) VALUES (?,?,?)")->execute([$code,$nom,$resp]);flash('Agence créée.');}
        redirect(BASE_URL.'modules/agences/index.php');
    }
}
if(isset($_GET['toggle'])){$pdo->prepare("UPDATE agences SET actif=NOT actif WHERE id=?")->execute([(int)$_GET['toggle']]);flash('Statut modifié.');redirect(BASE_URL.'modules/agences/index.php');}
$agences=$pdo->query("SELECT a.*,COUNT(DISTINCT u.id) as nb_users,COUNT(DISTINCT v.id) as nb_veh FROM agences a LEFT JOIN utilisateurs u ON u.agence_id=a.id LEFT JOIN vehicules v ON v.agence_id=a.id GROUP BY a.id ORDER BY a.nom")->fetchAll();
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Agences</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-building"></i> Agences (<?= count($agences) ?>)</h3><div style="display:flex;gap:6px;"><a href="import.php" class="btn btn-info btn-sm"><i class="fas fa-file-import"></i> Importer CSV</a><button class="btn btn-primary btn-sm" onclick="resetAForm();openModal('am')"><i class="fas fa-plus"></i> Nouvelle agence</button></div></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Code</th><th>Nom</th><th>Responsable</th><th>Utilisateurs</th><th>Véhicules</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($agences as $a): ?>
        <tr>
          <td><code><?= sanitize($a['code']) ?></code></td>
          <td><strong><?= sanitize($a['nom']) ?></strong></td>
          <td><?= sanitize($a['responsable']??'—') ?></td>
          <td style="text-align:center;"><?= $a['nb_users'] ?></td>
          <td style="text-align:center;"><?= $a['nb_veh'] ?></td>
          <td><?= $a['actif']?'<span class="badge badge-green">Actif</span>':'<span class="badge badge-red">Inactif</span>' ?></td>
          <td><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick="editA(<?= htmlspecialchars(json_encode($a),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button><a href="?toggle=<?= $a['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?= $a['actif']?'on':'off' ?>"></i></a></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($agences)): ?><tr><td colspan="7" class="t-empty"><i class="fas fa-building"></i> Aucune agence</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<div class="modal-over" id="am"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-building"></i> Agence</h3><button class="modal-x" onclick="closeModal('am')">✕</button></div>
  <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
    <input type="hidden" name="id" id="ag-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Code <span class="freq">*</span></label><input type="text" name="code" id="ag-code" class="fc" maxlength="10" placeholder="YDE,DLA..." required></div>
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="ag-nom" class="fc" required></div>
      <div class="fg"><label class="flbl">Responsable</label><input type="text" name="responsable" id="ag-resp" class="fc"></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('am')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>function resetAForm(){document.getElementById('ag-id').value='';document.getElementById('ag-code').value='';document.getElementById('ag-nom').value='';document.getElementById('ag-resp').value='';}
function editA(a){document.getElementById('ag-id').value=a.id;document.getElementById('ag-code').value=a.code;document.getElementById('ag-nom').value=a.nom;document.getElementById('ag-resp').value=a.responsable||'';openModal('am');}</script>
<?php include '../../includes/footer.php'; ?>