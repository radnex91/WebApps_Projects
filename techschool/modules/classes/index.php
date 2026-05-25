<?php
// modules/classes/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('classes.manage');
$pageTitle = 'Gestion des Classes';
$annee = getAnneeActive($pdo); $aid = $annee['id'] ?? 1;

if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$_GET['del']]);
    flash('Classe supprimée.', 'warning'); redirect(BASE_URL.'modules/classes/');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_class') {
        $id  = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $niv = (int)($_POST['niveau_id'] ?? 0);
        $fil = (int)($_POST['filiere_id'] ?? 0);
        $cap = (int)($_POST['capacite'] ?? 35);
        $salle = trim($_POST['salle'] ?? '');
        if (!$nom || !$niv || !$fil) { flash('Champs obligatoires.','danger'); }
        else {
            if ($id) { $pdo->prepare("UPDATE classes SET nom=?,niveau_id=?,filiere_id=?,capacite=?,salle=? WHERE id=?")->execute([$nom,$niv,$fil,$cap,$salle,$id]); flash('Classe modifiée.'); }
            else      { $pdo->prepare("INSERT INTO classes (nom,niveau_id,filiere_id,annee_id,capacite,salle) VALUES (?,?,?,?,?,?)")->execute([$nom,$niv,$fil,$aid,$cap,$salle]); flash('Classe créée.'); }
        }
    } elseif ($action === 'assign_matiere') {
        $cid = (int)($_POST['classe_id'] ?? 0);
        $mid = (int)($_POST['matiere_id'] ?? 0);
        $coef = (float)($_POST['coefficient'] ?? 1);
        $eid = (int)($_POST['enseignant_id'] ?? 0) ?: null;
        $h   = (int)($_POST['heures_semaine'] ?? 2);
        if ($cid && $mid) {
            $exists = $pdo->prepare("SELECT id FROM matiere_classe WHERE matiere_id=? AND classe_id=?");
            $exists->execute([$mid,$cid]);
            if ($exists->fetch()) $pdo->prepare("UPDATE matiere_classe SET coefficient=?,enseignant_id=?,heures_semaine=? WHERE matiere_id=? AND classe_id=?")->execute([$coef,$eid,$h,$mid,$cid]);
            else $pdo->prepare("INSERT INTO matiere_classe (matiere_id,classe_id,coefficient,enseignant_id,heures_semaine) VALUES (?,?,?,?,?)")->execute([$mid,$cid,$coef,$eid,$h]);
            flash('Matière assignée.');
        }
    } elseif ($action === 'del_matiere') {
        $pdo->prepare("DELETE FROM matiere_classe WHERE id=?")->execute([(int)$_POST['mc_id']]);
        flash('Matière retirée.','warning');
    }
    redirect(BASE_URL.'modules/classes/'.(isset($_POST['classe_id'])?'?view='.$_POST['classe_id']:''));
}

$classes = $pdo->query("SELECT c.*,n.nom as niveau_nom,n.cycle,f.nom as filiere_nom,f.couleur,COUNT(DISTINCT i.id) as nb_inscrits FROM classes c JOIN niveaux n ON c.niveau_id=n.id JOIN filieres f ON c.filiere_id=f.id LEFT JOIN inscriptions i ON i.classe_id=c.id AND i.annee_id=$aid WHERE c.annee_id=$aid GROUP BY c.id ORDER BY n.ordre,c.nom")->fetchAll();
$niveaux   = $pdo->query("SELECT * FROM niveaux ORDER BY ordre")->fetchAll();
$filieres  = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$matieres  = $pdo->query("SELECT * FROM matieres WHERE actif=1 ORDER BY type,nom")->fetchAll();
$enseignants = $pdo->query("SELECT id,CONCAT(prenom,' ',nom) as nom FROM enseignants WHERE statut='actif' ORDER BY nom")->fetchAll();

$viewId = (int)($_GET['view'] ?? 0);
$viewClasse = null; $viewMatieres = [];
if ($viewId) {
    $vc = $pdo->prepare("SELECT c.*,n.nom as niv,f.nom as fil,f.couleur FROM classes c JOIN niveaux n ON c.niveau_id=n.id JOIN filieres f ON c.filiere_id=f.id WHERE c.id=?"); $vc->execute([$viewId]); $viewClasse=$vc->fetch();
    $vm = $pdo->prepare("SELECT mc.*,m.nom as mat_nom,m.type as mat_type,CONCAT(e.prenom,' ',e.nom) as ens_nom FROM matiere_classe mc JOIN matieres m ON mc.matiere_id=m.id LEFT JOIN enseignants e ON mc.enseignant_id=e.id WHERE mc.classe_id=? ORDER BY m.type,m.nom"); $vm->execute([$viewId]); $viewMatieres=$vm->fetchAll();
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Classes</div>

<?php if ($viewClasse): ?>
<!-- VUE DÉTAIL CLASSE -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h3><i class="fas fa-chalkboard"></i> <?= sanitize($viewClasse['nom']) ?>
      <span class="filiere-badge" style="background:<?= sanitize($viewClasse['couleur']) ?>20;color:<?= sanitize($viewClasse['couleur']) ?>;margin-left:8px;"><?= sanitize($viewClasse['fil']) ?></span>
    </h3>
    <div style="display:flex;gap:8px;">
      <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
  </div>
  <div class="card-body">
    <!-- Matières de la classe -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h4 style="font-size:13px;font-weight:600;">Matières assignées (<?= count($viewMatieres) ?>)</h4>
      <button class="btn btn-primary btn-sm" onclick="openModal('mat-modal')"><i class="fas fa-plus"></i> Ajouter matière</button>
    </div>
    <table>
      <thead><tr><th>Matière</th><th>Type</th><th>Coeff.</th><th>H/semaine</th><th>Enseignant</th><th></th></tr></thead>
      <tbody>
        <?php $typeColors=['generale'=>'badge-primary','technique'=>'badge-info','pratique'=>'badge-warning']; foreach($viewMatieres as $m): ?>
        <tr>
          <td><strong><?= sanitize($m['mat_nom']) ?></strong></td>
          <td><span class="badge <?= $typeColors[$m['mat_type']]??'badge-secondary' ?>"><?= sanitize($m['mat_type']) ?></span></td>
          <td style="text-align:center;font-weight:700;"><?= $m['coefficient'] ?></td>
          <td style="text-align:center;"><?= $m['heures_semaine'] ?>h</td>
          <td><?= sanitize($m['ens_nom']??'Non assigné') ?></td>
          <td><form method="POST" style="display:inline;"><input type="hidden" name="action" value="del_matiere"><input type="hidden" name="mc_id" value="<?= $m['id'] ?>"><input type="hidden" name="classe_id" value="<?= $viewId ?>"><button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Retirer cette matière ?')"><i class="fas fa-times"></i></button></form></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($viewMatieres)): ?><tr><td colspan="6" class="table-empty">Aucune matière assignée</td></tr><?php endif; ?>
      </tbody>
    </table>

    <!-- Modal assigner matière -->
    <div class="modal-overlay" id="mat-modal">
      <div class="modal modal-sm">
        <div class="modal-head"><h3><i class="fas fa-plus"></i> Assigner une matière</h3><button class="modal-close" onclick="closeModal('mat-modal')">✕</button></div>
        <form method="POST">
          <input type="hidden" name="action" value="assign_matiere">
          <input type="hidden" name="classe_id" value="<?= $viewId ?>">
          <div class="modal-body">
            <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Matière</label><select name="matiere_id" class="form-control" required><option value="">— Sélectionner —</option><?php foreach($matieres as $m): ?><option value="<?= $m['id'] ?>">[<?= sanitize($m['type']) ?>] <?= sanitize($m['nom']) ?></option><?php endforeach; ?></select></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
              <div class="form-group"><label class="form-label">Coefficient</label><input type="number" name="coefficient" class="form-control" value="2" min="0.5" step="0.5"></div>
              <div class="form-group"><label class="form-label">H/semaine</label><input type="number" name="heures_semaine" class="form-control" value="2" min="1"></div>
            </div>
            <div class="form-group"><label class="form-label">Enseignant</label><select name="enseignant_id" class="form-control"><option value="">— Non assigné —</option><?php foreach($enseignants as $e): ?><option value="<?= $e['id'] ?>"><?= sanitize($e['nom']) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('mat-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Assigner</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php else: ?>

<!-- LISTE CLASSES -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-chalkboard"></i> Classes — <?= sanitize($annee['libelle']??'') ?> (<?= count($classes) ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal('class-modal');document.getElementById('cl-id').value=''"><i class="fas fa-plus"></i> Nouvelle classe</button>
  </div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Classe</th><th>Niveau</th><th>Filière</th><th>Salle</th><th>Capacité</th><th>Inscrits</th><th>Taux</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($classes as $c):
          $pct = $c['capacite']>0 ? min(100,round($c['nb_inscrits']/$c['capacite']*100)) : 0;
          $col = $pct>=90?'var(--danger)':($pct>=70?'var(--warning)':'var(--success)');
        ?>
        <tr>
          <td><strong><?= sanitize($c['nom']) ?></strong></td>
          <td><?= sanitize($c['niveau_nom']) ?></td>
          <td><span class="filiere-badge" style="background:<?= sanitize($c['couleur']) ?>20;color:<?= sanitize($c['couleur']) ?>"><?= sanitize($c['filiere_nom']) ?></span></td>
          <td><?= sanitize($c['salle']??'—') ?></td>
          <td style="text-align:center;"><?= $c['capacite'] ?></td>
          <td style="text-align:center;font-weight:600;"><?= $c['nb_inscrits'] ?></td>
          <td style="min-width:80px;"><div style="display:flex;align-items:center;gap:6px;"><div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div><span style="font-size:11px;color:var(--text3);"><?= $pct ?>%</span></div></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="?view=<?= $c['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Matières"><i class="fas fa-book"></i></a>
              <button class="btn btn-sm btn-warning btn-icon" onclick="editClass(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
              <a href="?del=<?= $c['id'] ?>" class="btn btn-sm btn-danger btn-icon" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($classes)): ?><tr><td colspan="8"><div class="table-empty"><i class="fas fa-chalkboard"></i>Aucune classe</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CLASSE -->
<div class="modal-overlay" id="class-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-chalkboard"></i> Classe</h3><button class="modal-close" onclick="closeModal('class-modal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="save_class">
      <input type="hidden" name="id" id="cl-id">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="nom" id="cl-nom" class="form-control" required placeholder="1ère INFO A"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div class="form-group"><label class="form-label">Niveau <span class="form-required">*</span></label><select name="niveau_id" id="cl-niv" class="form-control" required><option value="">—</option><?php foreach($niveaux as $n): ?><option value="<?= $n['id'] ?>"><?= sanitize($n['nom']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">Filière <span class="form-required">*</span></label><select name="filiere_id" id="cl-fil" class="form-control" required><option value="">—</option><?php foreach($filieres as $f): ?><option value="<?= $f['id'] ?>"><?= sanitize($f['nom']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label class="form-label">Capacité</label><input type="number" name="capacite" id="cl-cap" class="form-control" value="35" min="1"></div>
          <div class="form-group"><label class="form-label">Salle</label><input type="text" name="salle" id="cl-salle" class="form-control" placeholder="Salle A1..."></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('class-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<script>
function editClass(c) {
    document.getElementById('cl-id').value   = c.id;
    document.getElementById('cl-nom').value  = c.nom;
    document.getElementById('cl-niv').value  = c.niveau_id;
    document.getElementById('cl-fil').value  = c.filiere_id;
    document.getElementById('cl-cap').value  = c.capacite;
    document.getElementById('cl-salle').value= c.salle||'';
    openModal('class-modal');
}
</script>
<?php include '../../includes/footer.php'; ?>
