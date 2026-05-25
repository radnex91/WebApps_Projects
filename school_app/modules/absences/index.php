<?php
// modules/absences/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Gestion des Absences';
$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $eleve_id=(int)($_POST['eleve_id']??0);
    $date=$_POST['date_absence']??date('Y-m-d');
    $motif=trim($_POST['motif']??'');
    $justifie=(int)isset($_POST['justifie']);
    if(!$eleve_id){flash('Élève obligatoire.','danger');}
    else{
        $pdo->prepare("INSERT INTO absences (eleve_id,date_absence,motif,justifie,annee_id) VALUES (?,?,?,?,?)")
            ->execute([$eleve_id,$date,$motif?:null,$justifie,$annee_id]);
        flash('Absence enregistrée.');
    }
    redirect(BASE_URL.'modules/absences/');
}
if(isset($_GET['del'])){
    $pdo->prepare("DELETE FROM absences WHERE id=?")->execute([$_GET['del']]);
    flash('Absence supprimée.','warning');
    redirect(BASE_URL.'modules/absences/');
}
if(isset($_GET['justifier'])){
    $pdo->prepare("UPDATE absences SET justifie=1 WHERE id=?")->execute([$_GET['justifier']]);
    flash('Absence justifiée.');
    redirect(BASE_URL.'modules/absences/');
}

$search = trim($_GET['search']??'');
$date_f = $_GET['date']??'';
$page=max(1,(int)($_GET['page']??1)); $perPage=25;

$where=["a.annee_id=$annee_id"]; $params=[];
if($search){$where[]="(e.nom LIKE ? OR e.prenom LIKE ?)"; $params=array_merge($params,["%$search%","%$search%"]);}
if($date_f){$where[]="a.date_absence=?"; $params[]=$date_f;}
$ws=implode(' AND ',$where);

$total=$pdo->prepare("SELECT COUNT(*) FROM absences a JOIN eleves e ON a.eleve_id=e.id WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$perPage); $offset=($page-1)*$perPage;
$stmt=$pdo->prepare("SELECT a.*, e.nom, e.prenom, e.matricule, cl.nom as classe FROM absences a JOIN eleves e ON a.eleve_id=e.id LEFT JOIN inscriptions i ON i.eleve_id=e.id AND i.annee_id=$annee_id LEFT JOIN classes cl ON i.classe_id=cl.id WHERE $ws ORDER BY a.date_absence DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $absences=$stmt->fetchAll();

$eleves_list=$pdo->query("SELECT e.id, CONCAT(e.matricule,' - ',e.prenom,' ',e.nom) as label FROM eleves e WHERE e.statut='actif' ORDER BY e.nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Absences</div>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
<div class="card">
  <div class="card-header"><h2><i class="fas fa-user-clock"></i> Absences (<?= $totalRows ?>)</h2></div>
  <div class="card-body">
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control" placeholder="🔍 Nom de l'élève..." value="<?= sanitize($search) ?>">
      <input type="date" name="date" class="form-control" value="<?= sanitize($date_f) ?>">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-responsive">
    <table>
      <thead><tr><th>Date</th><th>Élève</th><th>Classe</th><th>Motif</th><th>Justifiée</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($absences as $a): ?>
        <tr>
          <td><?= date('d/m/Y',strtotime($a['date_absence'])) ?></td>
          <td><strong><?= sanitize($a['prenom'].' '.$a['nom']) ?></strong></td>
          <td><?= sanitize($a['classe']??'—') ?></td>
          <td><?= sanitize($a['motif']??'—') ?></td>
          <td><?= $a['justifie'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-danger">Non</span>' ?></td>
          <td>
            <?php if(!$a['justifie']): ?><a href="?justifier=<?= $a['id'] ?>" class="btn btn-sm btn-success" title="Justifier"><i class="fas fa-check"></i></a><?php endif; ?>
            <a href="?del=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($absences)): ?><tr><td colspan="6"><div class="empty-state"><i class="fas fa-user-clock"></i><p>Aucune absence</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<div class="card" style="height:fit-content;">
  <div class="card-header"><h2><i class="fas fa-plus"></i> Signaler une absence</h2></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group"><label>Élève *</label>
        <select name="eleve_id" class="form-control" required>
          <option value="">-- Chercher --</option>
          <?php foreach($eleves_list as $e): ?><option value="<?= $e['id'] ?>"><?= sanitize($e['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Date</label><input type="date" name="date_absence" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>Motif</label><input type="text" name="motif" class="form-control" placeholder="Motif de l'absence..."></div>
      <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;">
        <input type="checkbox" name="justifie" id="justifie" style="width:auto;">
        <label for="justifie" style="text-transform:none;letter-spacing:0;font-size:14px;">Absence justifiée</label>
      </div>
      <button type="submit" class="btn btn-warning" style="width:100%;justify-content:center;margin-top:8px;"><i class="fas fa-user-clock"></i> Enregistrer</button>
    </form>
  </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
