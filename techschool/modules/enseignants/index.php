<?php
// modules/enseignants/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('enseignants.manage');
$pageTitle = 'Enseignants';
$annee = getAnneeActive($pdo); $aid = $annee['id'] ?? 1;

// DELETE
if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM enseignants WHERE id=?")->execute([$_GET['del']]);
    flash('Enseignant supprimé.', 'warning'); redirect(BASE_URL.'modules/enseignants/');
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $nom  = trim($_POST['nom']  ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    if (!$nom || !$prenom) { flash('Nom et prénom obligatoires.', 'danger'); }
    else {
        $fields = ['nom','prenom','date_naissance','sexe','telephone','email','adresse','specialite','diplome','grade','date_embauche','statut'];
        $data = []; foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;
        if ($id) {
            $sets = implode('=?,', $fields).'=?';
            $pdo->prepare("UPDATE enseignants SET $sets WHERE id=?")->execute([...array_values($data), $id]);
            flash('Enseignant modifié.');
        } else {
            $mat = genMatricule($pdo, 'ENS', 'enseignants');
            $cols = 'matricule,'.implode(',', $fields);
            $phs  = '?,'.implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO enseignants ($cols) VALUES ($phs)")->execute([$mat, ...array_values($data)]);
            flash("Enseignant ajouté — Matricule : $mat");
        }
        logAction($pdo, $id?'update_ens':'create_ens', 'enseignants', $nom.' '.$prenom);
    }
    redirect(BASE_URL.'modules/enseignants/');
}

$search = trim($_GET['q'] ?? '');
$where = ['1=1']; $params = [];
if ($search) { $where[] = "(nom LIKE ? OR prenom LIKE ? OR matricule LIKE ? OR specialite LIKE ?)"; $params = array_fill(0,4,"%$search%"); }
$ws = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM enseignants WHERE $ws ORDER BY nom"); $stmt->execute($params);
$enseignants = $stmt->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Enseignants</div>
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-chalkboard-teacher"></i> Enseignants (<?= count($enseignants) ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal('ens-modal');document.getElementById('ens-form').reset();document.getElementById('e-id').value=''"><i class="fas fa-plus"></i> Ajouter</button>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="🔍 Nom, matricule, spécialité..." value="<?= sanitize($search) ?>" style="max-width:300px;">
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Matricule</th><th>Enseignant</th><th>Spécialité</th><th>Téléphone</th><th>Grade</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($enseignants as $e): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($e['matricule']) ?></code></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar avatar-sm" style="background:<?= $e['sexe']==='F'?'#ec4899':'var(--primary)' ?>;"><?= initials($e['prenom'].' '.$e['nom']) ?></div>
              <div><strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong><div style="font-size:11px;color:var(--text3);"><?= sanitize($e['email']??'') ?></div></div>
            </div>
          </td>
          <td><?= sanitize($e['specialite']??'—') ?></td>
          <td><?= sanitize($e['telephone']??'—') ?></td>
          <td><?= sanitize($e['grade']??'—') ?></td>
          <td><?= $e['statut']==='actif'?'<span class="badge badge-success">Actif</span>':'<span class="badge badge-secondary">Inactif</span>' ?></td>
          <td>
            <div style="display:flex;gap:3px;">
              <button class="btn btn-sm btn-warning btn-icon" onclick="editEns(<?= htmlspecialchars(json_encode($e),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
              <a href="?del=<?= $e['id'] ?>" class="btn btn-sm btn-danger btn-icon" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($enseignants)): ?><tr><td colspan="7"><div class="table-empty"><i class="fas fa-chalkboard-teacher"></i>Aucun enseignant</div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<div class="modal-overlay" id="ens-modal">
  <div class="modal">
    <div class="modal-head"><h3 id="ens-modal-t"><i class="fas fa-user-plus"></i> Enseignant</h3><button class="modal-close" onclick="closeModal('ens-modal')">✕</button></div>
    <form method="POST" id="ens-form">
      <input type="hidden" name="id" id="e-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="nom" id="e-nom" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Prénom(s) <span class="form-required">*</span></label><input type="text" name="prenom" id="e-prenom" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Sexe</label><select name="sexe" id="e-sexe" class="form-control"><option value="M">Masculin</option><option value="F">Féminin</option></select></div>
          <div class="form-group"><label class="form-label">Date de naissance</label><input type="date" name="date_naissance" id="e-naissance" class="form-control"></div>
          <div class="form-group"><label class="form-label">Téléphone</label><input type="tel" name="telephone" id="e-tel" class="form-control"></div>
          <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="e-email" class="form-control"></div>
          <div class="form-group"><label class="form-label">Spécialité</label><input type="text" name="specialite" id="e-spec" class="form-control" placeholder="Informatique, Maths..."></div>
          <div class="form-group"><label class="form-label">Grade</label><input type="text" name="grade" id="e-grade" class="form-control" placeholder="PLEG, Prof. certifié..."></div>
          <div class="form-group"><label class="form-label">Diplôme</label><input type="text" name="diplome" id="e-diplome" class="form-control"></div>
          <div class="form-group"><label class="form-label">Date d'embauche</label><input type="date" name="date_embauche" id="e-embauche" class="form-control"></div>
          <div class="form-group"><label class="form-label">Statut</label><select name="statut" id="e-statut" class="form-control"><option value="actif">Actif</option><option value="inactif">Inactif</option></select></div>
          <div class="form-group full"><label class="form-label">Adresse</label><input type="text" name="adresse" id="e-adresse" class="form-control"></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('ens-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>
<script>
function editEns(e) {
    document.getElementById('e-id').value       = e.id;
    document.getElementById('e-nom').value      = e.nom;
    document.getElementById('e-prenom').value   = e.prenom;
    document.getElementById('e-sexe').value     = e.sexe||'M';
    document.getElementById('e-naissance').value= e.date_naissance||'';
    document.getElementById('e-tel').value      = e.telephone||'';
    document.getElementById('e-email').value    = e.email||'';
    document.getElementById('e-spec').value     = e.specialite||'';
    document.getElementById('e-grade').value    = e.grade||'';
    document.getElementById('e-diplome').value  = e.diplome||'';
    document.getElementById('e-embauche').value = e.date_embauche||'';
    document.getElementById('e-statut').value   = e.statut||'actif';
    document.getElementById('e-adresse').value  = e.adresse||'';
    document.getElementById('ens-modal-t').innerHTML = '<i class="fas fa-edit"></i> Modifier enseignant';
    openModal('ens-modal');
}
</script>
<?php include '../../includes/footer.php'; ?>
