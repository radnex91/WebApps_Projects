<?php
// modules/absences.php
require_once '../includes/config.php';
requireLogin(); requirePerm('notes.view');
$pageTitle = 'Absences';
$annee = getAnneeActive($pdo); $aid = $annee['id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('notes.create')) {
    $eid   = (int)($_POST['eleve_id'] ?? 0);
    $date  = $_POST['date_absence'] ?? date('Y-m-d');
    $h     = (int)($_POST['nb_heures'] ?? 1);
    $motif = trim($_POST['motif'] ?? '');
    $justif= isset($_POST['justifie']) ? 1 : 0;
    if ($eid) {
        $pdo->prepare("INSERT INTO absences (eleve_id,annee_id,date_absence,nb_heures,motif,justifie) VALUES (?,?,?,?,?,?)")->execute([$eid,$aid,$date,$h,$motif,$justif]);
        flash("Absence enregistrée.");
    }
    redirect(BASE_URL.'modules/absences.php');
}
if (isset($_GET['del'])) { $pdo->prepare("DELETE FROM absences WHERE id=?")->execute([$_GET['del']]); flash('Absence supprimée.','warning'); redirect(BASE_URL.'modules/absences.php'); }

$cf  = (int)($_GET['classe_id'] ?? 0);
$classes = $pdo->query("SELECT c.id,c.nom FROM classes c WHERE c.annee_id=$aid ORDER BY c.nom")->fetchAll();
$eleves  = [];
if ($cf) {
    $st = $pdo->prepare("SELECT e.id, CONCAT(e.prenom,' ',e.nom) as label FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id WHERE i.classe_id=? AND i.annee_id=? ORDER BY e.nom");
    $st->execute([$cf,$aid]); $eleves = $st->fetchAll();
}
$absences = $pdo->query("SELECT a.*,e.nom,e.prenom,cl.nom as classe_nom FROM absences a JOIN eleves e ON a.eleve_id=e.id LEFT JOIN inscriptions i ON i.eleve_id=e.id AND i.annee_id=$aid LEFT JOIN classes cl ON i.classe_id=cl.id WHERE a.annee_id=$aid ORDER BY a.date_absence DESC LIMIT 60")->fetchAll();
$allEleves = $pdo->query("SELECT e.id,CONCAT(e.prenom,' ',e.nom) as label FROM eleves e JOIN inscriptions i ON i.eleve_id=e.id WHERE i.annee_id=$aid ORDER BY e.nom")->fetchAll();
include '../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Absences</div>
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-user-clock"></i> Absences enregistrées</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Élève</th><th>Classe</th><th>Date</th><th>Heures</th><th>Motif</th><th>Just.</th><th></th></tr></thead>
      <tbody>
        <?php foreach($absences as $a): ?>
        <tr>
          <td><strong><?= sanitize($a['nom'].' '.$a['prenom']) ?></strong></td>
          <td><?= sanitize($a['classe_nom']??'—') ?></td>
          <td><?= formatDate($a['date_absence']) ?></td>
          <td style="text-align:center;font-weight:600;"><?= $a['nb_heures'] ?>h</td>
          <td style="font-size:12px;"><?= sanitize($a['motif']??'—') ?></td>
          <td><?= $a['justifie']?'<span class="badge badge-success">Oui</span>':'<span class="badge badge-danger">Non</span>' ?></td>
          <td><a href="?del=<?= $a['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Supprimer ?')"><i class="fas fa-times"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($absences)): ?><tr><td colspan="7" class="table-empty"><i class="fas fa-check-circle"></i>Aucune absence</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card" style="height:fit-content;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Enregistrer une absence</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Élève</label><select name="eleve_id" class="form-control" required><option value="">— Sélectionner —</option><?php foreach($allEleves as $e): ?><option value="<?= $e['id'] ?>"><?= sanitize($e['label']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Date</label><input type="date" name="date_absence" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Nb heures</label><input type="number" name="nb_heures" class="form-control" value="1" min="1" max="8"></div>
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Motif</label><textarea name="motif" class="form-control" rows="2"></textarea></div>
      <div class="form-group" style="margin-bottom:16px;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;"><input type="checkbox" name="justifie"> <span>Justifiée</span></label></div>
      <button type="submit" class="btn btn-warning btn-block"><i class="fas fa-save"></i> Enregistrer</button>
    </form>
  </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
