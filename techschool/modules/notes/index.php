<?php
// modules/notes/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('notes.view');
$pageTitle = 'Gestion des Notes';
$annee = getAnneeActive($pdo);
$aid = $annee['id'] ?? 1;

$classes  = $pdo->query("SELECT c.*,n.nom as niveau_nom,f.nom as filiere_nom FROM classes c JOIN niveaux n ON c.niveau_id=n.id JOIN filieres f ON c.filiere_id=f.id WHERE c.annee_id=$aid ORDER BY n.ordre,c.nom")->fetchAll();
$periodes = $pdo->query("SELECT * FROM periodes WHERE annee_id=$aid ORDER BY type,ordre")->fetchAll();

$sel_classe  = (int)($_GET['classe_id']??0);
$sel_periode = (int)($_GET['periode_id']??0);
$sel_matiere = (int)($_GET['matiere_id']??0);

$matieres=[]; $eleves_notes=[];
if($sel_classe){
    $matieres=$pdo->prepare("SELECT mc.*,m.nom,m.type,m.code,CONCAT(e.prenom,' ',e.nom) as enseignant FROM matiere_classe mc JOIN matieres m ON mc.matiere_id=m.id LEFT JOIN enseignants e ON mc.enseignant_id=e.id WHERE mc.classe_id=? ORDER BY m.type,m.nom");
    $matieres->execute([$sel_classe]); $matieres=$matieres->fetchAll();
    if(!$sel_matiere && !empty($matieres)) $sel_matiere=$matieres[0]['matiere_id'];
}

if($sel_classe && $sel_periode && $sel_matiere){
    $stmt=$pdo->prepare("SELECT e.id,e.nom,e.prenom,e.matricule,n.note,n.id as note_id,n.observation,mc.coefficient FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id JOIN matiere_classe mc ON mc.classe_id=i.classe_id AND mc.matiere_id=? LEFT JOIN notes n ON n.eleve_id=e.id AND n.matiere_id=? AND n.periode_id=? AND n.annee_id=? WHERE i.classe_id=? AND i.annee_id=? AND i.statut='actif' ORDER BY e.nom,e.prenom");
    $stmt->execute([$sel_matiere,$sel_matiere,$sel_periode,$aid,$sel_classe,$aid]);
    $eleves_notes=$stmt->fetchAll();
}

// SAVE
if($_SERVER['REQUEST_METHOD']==='POST' && can('notes.create')){
    $pid=(int)$_POST['periode_id']; $mid=(int)$_POST['matiere_id']; $cid=(int)$_POST['classe_id'];
    $type=$_POST['type_eval']??'composition'; $count=0;
    foreach($_POST['notes']??[] as $eid=>$val){
        if($val===''||$val===null) continue;
        $nval=(float)$val; if($nval<0||$nval>20) continue;
        $obs=$_POST['obs'][$eid]??'';
        $check=$pdo->prepare("SELECT id FROM notes WHERE eleve_id=? AND matiere_id=? AND periode_id=? AND annee_id=? AND type_eval=?");
        $check->execute([$eid,$mid,$pid,$aid,$type]);
        if($existing=$check->fetchColumn()){
            $pdo->prepare("UPDATE notes SET note=?,observation=?,saisie_par=? WHERE id=?")->execute([$nval,$obs,$_SESSION['user_id'],$existing]);
        } else {
            $pdo->prepare("INSERT INTO notes (eleve_id,matiere_id,classe_id,periode_id,annee_id,note,type_eval,observation,saisie_par) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$eid,$mid,$cid,$pid,$aid,$nval,$type,$obs,$_SESSION['user_id']]);
        }
        $count++;
    }
    logAction($pdo,'saisie_notes','notes',"$count notes pour matière $mid, période $pid");
    flash("$count note(s) enregistrée(s).");
    redirect(BASE_URL."modules/notes/?classe_id=$cid&periode_id=$pid&matiere_id=$mid");
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Notes</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><h3><i class="fas fa-filter"></i> Sélection</h3></div>
  <div class="card-body">
    <form method="GET" class="form-grid">
      <div class="form-group">
        <label class="form-label">Classe</label>
        <select name="classe_id" class="form-control" onchange="this.form.submit()">
          <option value="">— Classe —</option>
          <?php foreach($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $sel_classe==$c['id']?'selected':'' ?>><?= sanitize($c['nom'].' ('.$c['filiere_nom'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Matière</label>
        <select name="matiere_id" class="form-control" onchange="this.form.submit()">
          <option value="">— Matière —</option>
          <?php foreach($matieres as $m): ?>
          <option value="<?= $m['matiere_id'] ?>" <?= $sel_matiere==$m['matiere_id']?'selected':'' ?>><?= sanitize($m['nom'].' (coeff.'.$m['coefficient'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Période</label>
        <select name="periode_id" class="form-control" onchange="this.form.submit()">
          <option value="">— Période —</option>
          <?php foreach($periodes as $p): ?>
          <optgroup label="<?= sanitize(ucfirst($p['type'])) ?>" style="color:var(--text3)">
          <option value="<?= $p['id'] ?>" <?= $sel_periode==$p['id']?'selected':'' ?>><?= sanitize($p['nom']) ?></option>
          </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if($sel_classe && $sel_periode && $sel_matiere && !empty($eleves_notes)): ?>
<form method="POST">
  <input type="hidden" name="classe_id" value="<?= $sel_classe ?>">
  <input type="hidden" name="matiere_id" value="<?= $sel_matiere ?>">
  <input type="hidden" name="periode_id" value="<?= $sel_periode ?>">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-edit"></i> Saisie des notes — <?= count($eleves_notes) ?> élèves</h3>
      <div style="display:flex;gap:8px;align-items:center;">
        <label class="form-label" style="margin:0;">Type :</label>
        <select name="type_eval" class="form-control" style="width:auto;">
          <?php foreach(['composition'=>'Composition','devoir1'=>'Devoir 1','devoir2'=>'Devoir 2','examen'=>'Examen','tp'=>'TP','oral'=>'Oral','projet'=>'Projet'] as $v=>$l): ?>
          <option value="<?= $v ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <?php if(can('notes.create')): ?>
        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Enregistrer</button>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary btn-sm" onclick="fillAll()"><i class="fas fa-magic"></i> Remplir tous</button>
      </div>
    </div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>#</th><th>Matricule</th><th>Élève</th><th>Note /20</th><th>Observation</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach($eleves_notes as $i=>$e): $hasNote=$e['note']!==null; ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><code style="font-size:11px;"><?= sanitize($e['matricule']) ?></code></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="avatar avatar-sm" style="background:<?= $e['sexe']??'M'==='F'?'#ec4899':'var(--primary)' ?>;"><?= initials($e['prenom'].' '.$e['nom']) ?></div>
                <strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong>
              </div>
            </td>
            <td>
              <?php if(can('notes.create') || can('notes.edit')): ?>
              <input type="number" name="notes[<?= $e['id'] ?>]" class="note-input"
                     value="<?= $hasNote?$e['note']:'' ?>"
                     min="0" max="20" step="0.25" placeholder="—"
                     oninput="colorNote(this)"
                     <?= $hasNote?'class="note-input valid"':'' ?>>
              <?php else: ?>
              <span class="<?= $hasNote?($e['note']>=10?'note-high':'note-low'):'note-none' ?>"><?= $hasNote?$e['note']:'—' ?></span>
              <?php endif; ?>
            </td>
            <td>
              <input type="text" name="obs[<?= $e['id'] ?>]" class="form-control" style="font-size:12px;"
                     value="<?= sanitize($e['observation']??'') ?>" placeholder="Observation...">
            </td>
            <td><?= $hasNote?'<span class="badge badge-success">Saisie</span>':'<span class="badge badge-secondary">En attente</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if(can('notes.create')): ?>
    <div class="card-footer" style="display:flex;justify-content:flex-end;">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer les notes</button>
    </div>
    <?php endif; ?>
  </div>
</form>

<!-- CLASSEMENT -->
<?php
$moyQ=$pdo->prepare("SELECT e.id,e.nom,e.prenom,ROUND(SUM(n.note*mc.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN mc.coefficient ELSE 0 END),0),2) as moy,COUNT(n.id) as nb FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id LEFT JOIN notes n ON n.eleve_id=e.id AND n.periode_id=? AND n.annee_id=? JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=i.classe_id WHERE i.classe_id=? AND i.annee_id=? GROUP BY e.id HAVING moy IS NOT NULL ORDER BY moy DESC");
$moyQ->execute([$sel_periode,$aid,$sel_classe,$aid]);
$classement=$moyQ->fetchAll();
?>
<?php if(!empty($classement)): ?>
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h3><i class="fas fa-trophy"></i> Classement général — <?= count($classement) ?> élèves notés</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Rang</th><th>Élève</th><th>Moyenne</th><th>Mention</th><th>Matières notées</th></tr></thead>
      <tbody>
        <?php foreach($classement as $r=>$row):
          $mention=getMention((float)$row['moy']);
          $medals=['🥇','🥈','🥉'];
        ?>
        <tr>
          <td><?= $medals[$r]??($r+1).'ème' ?></td>
          <td><strong><?= sanitize($row['nom'].' '.$row['prenom']) ?></strong></td>
          <td><span style="font-size:16px;font-weight:800;color:<?= $mention['color'] ?>"><?= $row['moy'] ?>/20</span></td>
          <td><span class="<?= $mention['class'] ?>"><?= $mention['label'] ?></span></td>
          <td><?= $row['nb'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif($sel_classe && $sel_periode && $sel_matiere): ?>
<div class="empty-state card"><i class="fas fa-users"></i><h3>Aucun élève</h3><p>Aucun élève inscrit dans cette classe</p></div>
<?php else: ?>
<div class="empty-state card"><i class="fas fa-filter"></i><h3>Sélectionnez les paramètres</h3><p>Choisissez une classe, une matière et une période pour saisir les notes</p></div>
<?php endif; ?>

<script>
function colorNote(input){
  const v=parseFloat(input.value);
  input.className='note-input'+(isNaN(v)?'':v>=10?' valid':' invalid');
}
function fillAll(){
  const v=prompt('Note à saisir pour tous les élèves (0-20) :');
  if(v!==null) document.querySelectorAll('.note-input').forEach(i=>{i.value=v;colorNote(i);});
}
</script>

<?php include '../../includes/footer.php'; ?>
