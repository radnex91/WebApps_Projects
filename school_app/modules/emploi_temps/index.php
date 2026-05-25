<?php
// modules/emploi_temps/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Emploi du Temps';
$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

$classes_list = $pdo->query("SELECT c.id, CONCAT(c.nom,' (',n.nom,')') as label FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$annee_id ORDER BY n.ordre")->fetchAll();
$sel_classe = $_GET['classe_id'] ?? ($classes_list[0]['id'] ?? '');

// ADD
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $af_id=(int)($_POST['affectation_id']??0);
    $jour=$_POST['jour']??'';
    $deb=$_POST['heure_debut']??'';
    $fin=$_POST['heure_fin']??'';
    $salle=trim($_POST['salle']??'');
    $cls=(int)($_POST['classe_id']??0);
    if(!$af_id||!$jour||!$deb||!$fin){flash('Tous les champs sont obligatoires.','danger');}
    else{
        $pdo->prepare("INSERT INTO emploi_temps (classe_id,annee_id,affectation_id,jour,heure_debut,heure_fin,salle) VALUES (?,?,?,?,?,?,?)")
            ->execute([$cls,$annee_id,$af_id,$jour,$deb,$fin,$salle?:null]);
        flash('Créneau ajouté.');
    }
    redirect(BASE_URL."modules/emploi_temps/?classe_id=$cls");
}
if(isset($_GET['del'])){
    $pdo->prepare("DELETE FROM emploi_temps WHERE id=?")->execute([$_GET['del']]);
    flash('Créneau supprimé.','warning');
    redirect(BASE_URL."modules/emploi_temps/?classe_id=$sel_classe");
}

$jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$heures = ['07:00','07:30','08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30','15:00','15:30','16:00','16:30','17:00','17:30','18:00'];

// Emploi du temps courant
$emploi = [];
if ($sel_classe) {
    $stmt = $pdo->prepare("SELECT et.*, m.nom as matiere, m.coefficient, CONCAT(ens.prenom,' ',ens.nom) as enseignant FROM emploi_temps et JOIN affectations a ON et.affectation_id=a.id JOIN matieres m ON a.matiere_id=m.id JOIN enseignants ens ON a.enseignant_id=ens.id WHERE et.classe_id=? AND et.annee_id=? ORDER BY FIELD(et.jour,'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), et.heure_debut");
    $stmt->execute([$sel_classe,$annee_id]);
    foreach($stmt->fetchAll() as $row) $emploi[$row['jour']][] = $row;
}

// Affectations disponibles pour la classe
$affectations = [];
if ($sel_classe) {
    $stmt = $pdo->prepare("SELECT a.id, m.nom as matiere, CONCAT(ens.prenom,' ',ens.nom) as enseignant FROM affectations a JOIN matieres m ON a.matiere_id=m.id JOIN enseignants ens ON a.enseignant_id=ens.id WHERE a.classe_id=? AND a.annee_id=? ORDER BY m.nom");
    $stmt->execute([$sel_classe,$annee_id]);
    $affectations = $stmt->fetchAll();
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Emploi du temps</div>

<div class="card no-print">
  <div class="card-header">
    <h2><i class="fas fa-calendar-week"></i> Emploi du temps</h2>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Imprimer</button>
  </div>
  <div class="card-body">
    <form method="GET" class="search-bar" style="margin-bottom:0;">
      <select name="classe_id" class="form-control" onchange="this.form.submit()" style="max-width:280px;">
        <option value="">-- Sélectionner une classe --</option>
        <?php foreach($classes_list as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $sel_classe==$c['id']?'selected':'' ?>><?= sanitize($c['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php if($sel_classe): ?>
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
<!-- GRILLE EMPLOI DU TEMPS -->
<div class="card">
  <div class="card-body" style="padding:0;overflow-x:auto;">
    <table class="timetable" style="min-width:700px;">
      <thead>
        <tr>
          <th style="width:80px;">Heure</th>
          <?php foreach($jours as $j): ?>
          <th><?= $j ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php for($i=0;$i<count($heures)-1;$i++): 
          $h_deb = $heures[$i]; $h_fin = $heures[$i+1];
        ?>
        <tr>
          <td style="text-align:center;font-size:11px;color:var(--text-muted);font-weight:600;"><?= $h_deb ?><br><span style="font-size:10px;">→ <?= $h_fin ?></span></td>
          <?php foreach($jours as $j): ?>
          <td>
            <?php
            if(isset($emploi[$j])) foreach($emploi[$j] as $slot) {
              if($slot['heure_debut'] >= $h_deb && $slot['heure_debut'] < $h_fin) {
                $colors = ['Math'=>'math','Français'=>'fr','Sciences'=>'sci','default'=>''];
                $cls_tt = 'tt-slot';
                foreach($colors as $k=>$v) if(stripos($slot['matiere'],$k)!==false){$cls_tt.=' '.$v;break;}
                echo '<div class="'.$cls_tt.'" title="'.sanitize($slot['enseignant']).'">';
                echo '<strong>'.sanitize($slot['matiere']).'</strong><br>';
                echo '<span style="font-size:11px;">'.sanitize($slot['heure_debut']).' - '.sanitize($slot['heure_fin']).'</span><br>';
                if($slot['salle']) echo '<span style="font-size:10px;opacity:.7;">Salle: '.sanitize($slot['salle']).'</span><br>';
                echo '<span style="font-size:10px;opacity:.8;">'.sanitize($slot['enseignant']).'</span>';
                echo ' <a href="?classe_id='.$sel_classe.'&del='.$slot['id'].'" onclick="return confirm(\'Supprimer ce créneau ?\')" style="color:red;font-size:11px;float:right;">✕</a>';
                echo '</div>';
              }
            }
            ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- AJOUT CRÉNEAU -->
<div class="card" style="height:fit-content;">
  <div class="card-header"><h2><i class="fas fa-plus"></i> Ajouter un créneau</h2></div>
  <div class="card-body">
    <?php if(empty($affectations)): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Aucune affectation enseignant pour cette classe. <a href="<?= BASE_URL ?>modules/enseignants/">Gérer les affectations</a></div>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="classe_id" value="<?= $sel_classe ?>">
      <div class="form-group"><label>Matière / Enseignant *</label>
        <select name="affectation_id" class="form-control" required>
          <option value="">-- Choisir --</option>
          <?php foreach($affectations as $a): ?>
          <option value="<?= $a['id'] ?>"><?= sanitize($a['matiere'].' — '.$a['enseignant']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Jour *</label>
        <select name="jour" class="form-control" required>
          <?php foreach($jours as $j): ?><option><?= $j ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Heure début *</label>
        <select name="heure_debut" class="form-control" required>
          <?php foreach($heures as $h): ?><option><?= $h ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Heure fin *</label>
        <select name="heure_fin" class="form-control" required>
          <?php foreach($heures as $h): ?><option><?= $h ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Salle</label>
        <input type="text" name="salle" class="form-control" placeholder="Ex: Salle 3, Labo...">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;"><i class="fas fa-plus"></i> Ajouter</button>
    </form>
    <?php endif; ?>
  </div>
</div>
</div>
<?php else: ?>
<div class="empty-state card" style="padding:40px;"><i class="fas fa-calendar-week"></i><p>Sélectionnez une classe pour voir son emploi du temps.</p></div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
