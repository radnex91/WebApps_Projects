<?php
// modules/notes/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Gestion des Notes';

$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

$classes  = $pdo->query("SELECT c.*, n.nom as niveau_nom FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$annee_id ORDER BY n.ordre")->fetchAll();
$periodes = $pdo->query("SELECT * FROM periodes WHERE annee_id=$annee_id")->fetchAll();

$sel_classe  = $_GET['classe_id'] ?? ($classes[0]['id'] ?? '');
$sel_periode = $_GET['periode_id'] ?? ($periodes[0]['id'] ?? '');
$sel_matiere = $_GET['matiere_id'] ?? '';

$matieres = [];
$eleves_notes = [];

if ($sel_classe) {
    $matieres = $pdo->prepare("SELECT DISTINCT m.* FROM affectations a JOIN matieres m ON a.matiere_id=m.id WHERE a.classe_id=? AND a.annee_id=? ORDER BY m.nom");
    $matieres->execute([$sel_classe, $annee_id]);
    $matieres = $matieres->fetchAll();
    if (!$sel_matiere && !empty($matieres)) $sel_matiere = $matieres[0]['id'];
}

if ($sel_classe && $sel_periode && $sel_matiere) {
    $stmt = $pdo->prepare("
        SELECT e.id, e.nom, e.prenom, e.matricule,
               n.note, n.id as note_id, n.type_eval, n.observation
        FROM inscriptions i
        JOIN eleves e ON i.eleve_id=e.id
        LEFT JOIN notes n ON n.eleve_id=e.id AND n.matiere_id=? AND n.periode_id=? AND n.annee_id=?
        WHERE i.classe_id=? AND i.annee_id=?
        ORDER BY e.nom, e.prenom");
    $stmt->execute([$sel_matiere, $sel_periode, $annee_id, $sel_classe, $annee_id]);
    $eleves_notes = $stmt->fetchAll();
}

// SAUVEGARDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notes'])) {
    $periode_id = $_POST['periode_id'];
    $matiere_id = $_POST['matiere_id'];
    $classe_id  = $_POST['classe_id'];
    $type_eval  = $_POST['type_eval'];
    $count = 0;
    foreach ($_POST['notes'] as $eleve_id => $note_val) {
        if ($note_val === '' || $note_val === null) continue;
        $note_val = (float)$note_val;
        if ($note_val < 0 || $note_val > 20) continue;
        $obs = $_POST['obs'][$eleve_id] ?? '';
        // Upsert
        $check = $pdo->prepare("SELECT id FROM notes WHERE eleve_id=? AND matiere_id=? AND periode_id=? AND annee_id=?");
        $check->execute([$eleve_id,$matiere_id,$periode_id,$annee_id]);
        if ($existing = $check->fetchColumn()) {
            $pdo->prepare("UPDATE notes SET note=?,type_eval=?,observation=? WHERE id=?")->execute([$note_val,$type_eval,$obs,$existing]);
        } else {
            $pdo->prepare("INSERT INTO notes (eleve_id,matiere_id,classe_id,periode_id,annee_id,note,type_eval,observation) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$eleve_id,$matiere_id,$classe_id,$periode_id,$annee_id,$note_val,$type_eval,$obs]);
        }
        $count++;
    }
    flash("$count note(s) enregistrée(s) avec succès.");
    redirect(BASE_URL."modules/notes/?classe_id=$classe_id&periode_id=$periode_id&matiere_id=$matiere_id");
}

include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a>
  <span class="breadcrumb-sep"></span> Notes
</div>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-star-half-alt"></i> Saisie des notes</h2>
  </div>
  <div class="card-body">
    <!-- FILTRES -->
    <form method="GET" class="search-bar" style="margin-bottom:20px;">
      <select name="classe_id" class="form-control" onchange="this.form.submit()">
        <option value="">-- Classe --</option>
        <?php foreach($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $sel_classe==$c['id']?'selected':'' ?>><?= sanitize($c['nom'].' ('.$c['niveau_nom'].')') ?></option>
        <?php endforeach; ?>
      </select>
      <select name="matiere_id" class="form-control" onchange="this.form.submit()">
        <option value="">-- Matière --</option>
        <?php foreach($matieres as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $sel_matiere==$m['id']?'selected':'' ?>><?= sanitize($m['nom']) ?> (coeff. <?= $m['coefficient'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <select name="periode_id" class="form-control" onchange="this.form.submit()">
        <option value="">-- Période --</option>
        <?php foreach($periodes as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $sel_periode==$p['id']?'selected':'' ?>><?= sanitize($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if ($sel_classe && $sel_matiere && $sel_periode && !empty($eleves_notes)): ?>
    <form method="POST">
      <input type="hidden" name="classe_id" value="<?= $sel_classe ?>">
      <input type="hidden" name="matiere_id" value="<?= $sel_matiere ?>">
      <input type="hidden" name="periode_id" value="<?= $sel_periode ?>">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <label style="font-weight:600;">Type d'évaluation :</label>
        <select name="type_eval" class="form-control" style="width:auto;">
          <?php foreach(['devoir','composition','examen','oral','pratique'] as $t): ?>
          <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Matricule</th>
            <th>Élève</th>
            <th style="width:120px;">Note /20 <span style="color:red">*</span></th>
            <th>Observation</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($eleves_notes as $i => $en): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><code><?= sanitize($en['matricule']) ?></code></td>
            <td><?= sanitize($en['nom'].' '.$en['prenom']) ?></td>
            <td>
              <input type="number" name="notes[<?= $en['id'] ?>]" class="form-control" 
                     value="<?= $en['note'] !== null ? $en['note'] : '' ?>"
                     min="0" max="20" step="0.25" placeholder="—"
                     style="width:90px;<?= $en['note']!==null && $en['note']<10 ? 'border-color:var(--danger);' : '' ?>">
            </td>
            <td>
              <input type="text" name="obs[<?= $en['id'] ?>]" class="form-control" 
                     value="<?= sanitize($en['observation']??'') ?>" placeholder="Observation...">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer les notes</button>
        <button type="button" class="btn btn-secondary" onclick="fillAll()"><i class="fas fa-fill"></i> Remplir tous avec...</button>
      </div>
    </form>
    <?php elseif($sel_classe && $sel_matiere && $sel_periode && empty($eleves_notes)): ?>
    <div class="empty-state"><i class="fas fa-users"></i><p>Aucun élève inscrit dans cette classe.</p></div>
    <?php else: ?>
    <div class="empty-state"><i class="fas fa-filter"></i><p>Sélectionnez une classe, une matière et une période pour saisir les notes.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- TABLEAU DE MOYENNES -->
<?php if($sel_classe && $sel_periode): 
$moyennes = $pdo->prepare("
  SELECT e.id, e.nom, e.prenom,
    ROUND(SUM(n.note * m.coefficient) / NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN m.coefficient ELSE 0 END),0), 2) as moyenne,
    COUNT(n.id) as nb_notes
  FROM inscriptions i
  JOIN eleves e ON i.eleve_id=e.id
  LEFT JOIN notes n ON n.eleve_id=e.id AND n.periode_id=? AND n.annee_id=?
  LEFT JOIN matieres m ON n.matiere_id=m.id
  WHERE i.classe_id=? AND i.annee_id=?
  GROUP BY e.id ORDER BY moyenne DESC");
$moyennes->execute([$sel_periode, $annee_id, $sel_classe, $annee_id]);
$moyennes = $moyennes->fetchAll();
?>
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-trophy"></i> Classement général de la période</h2>
  </div>
  <div class="card-body" style="padding:0;">
  <table>
    <thead><tr><th>Rang</th><th>Élève</th><th>Moyenne</th><th>Mention</th><th>Notes</th></tr></thead>
    <tbody>
      <?php foreach($moyennes as $rang => $m): 
        $moy = $m['moyenne'];
        $mention = $moy === null ? '—' : ($moy >= 16 ? 'Très Bien' : ($moy >= 14 ? 'Bien' : ($moy >= 12 ? 'Assez Bien' : ($moy >= 10 ? 'Passable' : 'Insuffisant'))));
        $color = $moy === null ? 'badge-secondary' : ($moy >= 16 ? 'badge-success' : ($moy >= 12 ? 'badge-info' : ($moy >= 10 ? 'badge-warning' : 'badge-danger')));
      ?>
      <tr>
        <td><?php
          if($rang==0) echo '<i class="fas fa-trophy" style="color:gold"></i> 1er';
          elseif($rang==1) echo '<i class="fas fa-medal" style="color:silver"></i> 2ème';
          elseif($rang==2) echo '<i class="fas fa-medal" style="color:#cd7f32"></i> 3ème';
          else echo ($rang+1).'ème';
        ?></td>
        <td><strong><?= sanitize($m['nom'].' '.$m['prenom']) ?></strong></td>
        <td><strong style="font-size:16px;color:<?= ($moy>=10?'var(--success)':'var(--danger)') ?>"><?= $moy ?? '—' ?>/20</strong></td>
        <td><span class="badge <?= $color ?>"><?= $moy ? $mention : '—' ?></span></td>
        <td><?= $m['nb_notes'] ?> matière(s)</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<script>
function fillAll() {
  const val = prompt('Note à saisir pour tous (0-20) :');
  if (val !== null && val !== '') {
    document.querySelectorAll('input[name^="notes["]').forEach(i => i.value = val);
  }
}
</script>

<?php include '../../includes/footer.php'; ?>
