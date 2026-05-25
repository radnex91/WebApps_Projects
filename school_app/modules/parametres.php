<?php
// modules/parametres.php
require_once '../includes/config.php';
requireLogin();
if (!hasRole('admin','directeur')) { flash('Accès non autorisé.','danger'); redirect(BASE_URL); }
$pageTitle = 'Paramètres';

$annees    = $pdo->query("SELECT * FROM annees_scolaires ORDER BY libelle DESC")->fetchAll();
$niveaux   = $pdo->query("SELECT * FROM niveaux ORDER BY ordre")->fetchAll();
$matieres  = $pdo->query("SELECT * FROM matieres ORDER BY nom")->fetchAll();
$users     = $pdo->query("SELECT * FROM utilisateurs ORDER BY nom")->fetchAll();

// Handle delete affectation (avant tout output HTML)
if (isset($_GET['del_af'])) {
    $pdo->prepare("DELETE FROM affectations WHERE id=?")->execute([$_GET['del_af']]);
    flash('Affectation supprimée.', 'warning');
    redirect(BASE_URL . 'modules/parametres.php');
}

// POST handlers
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'annee_active') {
        $id = (int)$_POST['annee_id'];
        $pdo->query("UPDATE annees_scolaires SET active=0");
        $pdo->prepare("UPDATE annees_scolaires SET active=1 WHERE id=?")->execute([$id]);
        flash('Année scolaire activée.');
    }
    elseif ($action === 'add_annee') {
        $lib = trim($_POST['libelle']??'');
        $deb = $_POST['date_debut']??'';
        $fin = $_POST['date_fin']??'';
        if($lib && $deb && $fin){
            try { $pdo->prepare("INSERT INTO annees_scolaires (libelle,date_debut,date_fin) VALUES (?,?,?)")->execute([$lib,$deb,$fin]); flash('Année ajoutée.'); }
            catch(PDOException $e){ flash('Erreur: '.$e->getMessage(),'danger'); }
        } else flash('Tous les champs sont obligatoires.','danger');
    }
    elseif ($action === 'add_user') {
        $un=trim($_POST['username']??''); $pw=$_POST['password']??''; $nom=trim($_POST['nom']??''); $prn=trim($_POST['prenom']??''); $role=$_POST['role']??'secretaire';
        if($un && $pw && $nom){
            try { $pdo->prepare("INSERT INTO utilisateurs (username,password,nom,prenom,role) VALUES (?,?,?,?,?)")->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$nom,$prn,$role]); flash("Utilisateur '$un' créé."); }
            catch(PDOException $e){ flash('Erreur: '.$e->getMessage(),'danger'); }
        } else flash('Username, mot de passe et nom obligatoires.','danger');
    }
    elseif ($action === 'toggle_user') {
        $uid=(int)$_POST['user_id']; $actif=(int)$_POST['actif'];
        $pdo->prepare("UPDATE utilisateurs SET actif=? WHERE id=?")->execute([$actif,$uid]);
        flash('Statut utilisateur modifié.');
    }
    elseif ($action === 'add_affectation') {
        $ens_id=(int)($_POST['enseignant_id']??0); $mat_id=(int)($_POST['matiere_id']??0); $cls_id=(int)($_POST['classe_id']??0);
        $annee_id_sel = getAnneeActive($pdo)['id']??1;
        if($ens_id && $mat_id && $cls_id){
            try{ $pdo->prepare("INSERT INTO affectations (enseignant_id,matiere_id,classe_id,annee_id) VALUES (?,?,?,?)")->execute([$ens_id,$mat_id,$cls_id,$annee_id_sel]); flash('Affectation ajoutée.'); }
            catch(PDOException $e){ flash('Cette affectation existe déjà.','warning'); }
        } else flash('Tous les champs obligatoires.','danger');
    }

    redirect(BASE_URL.'modules/parametres.php');
}

$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;
$enseignants = $pdo->query("SELECT * FROM enseignants WHERE statut='actif' ORDER BY nom")->fetchAll();
$classes = $pdo->query("SELECT c.*, n.nom as niveau_nom FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$annee_id ORDER BY n.ordre")->fetchAll();
$affectations = $pdo->query("SELECT a.*, m.nom as matiere, CONCAT(ens.prenom,' ',ens.nom) as enseignant, cl.nom as classe FROM affectations a JOIN matieres m ON a.matiere_id=m.id JOIN enseignants ens ON a.enseignant_id=ens.id JOIN classes cl ON a.classe_id=cl.id WHERE a.annee_id=$annee_id ORDER BY cl.id, m.nom")->fetchAll();

include '../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Paramètres</div>

<!-- ANNÉES SCOLAIRES -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-calendar-alt"></i> Années scolaires</h2></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr auto;gap:20px;align-items:start;">
      <table style="margin:0;">
        <thead><tr><th>Année</th><th>Début</th><th>Fin</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($annees as $a): ?>
          <tr>
            <td><strong><?= sanitize($a['libelle']) ?></strong></td>
            <td><?= date('d/m/Y',strtotime($a['date_debut'])) ?></td>
            <td><?= date('d/m/Y',strtotime($a['date_fin'])) ?></td>
            <td><?= $a['active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
            <td>
              <?php if(!$a['active']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="annee_active">
                <input type="hidden" name="annee_id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-sm btn-primary">Activer</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="POST" style="min-width:260px;">
        <input type="hidden" name="action" value="add_annee">
        <div class="form-group"><label>Libellé (ex: 2025-2026)</label><input type="text" name="libelle" class="form-control" placeholder="2025-2026" required></div>
        <div class="form-group"><label>Date début</label><input type="date" name="date_debut" class="form-control" required></div>
        <div class="form-group"><label>Date fin</label><input type="date" name="date_fin" class="form-control" required></div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;"><i class="fas fa-plus"></i> Ajouter</button>
      </form>
    </div>
  </div>
</div>

<!-- AFFECTATIONS ENSEIGNANTS -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-link"></i> Affectations Enseignant → Matière → Classe</h2></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">
      <table>
        <thead><tr><th>Enseignant</th><th>Matière</th><th>Classe</th><th></th></tr></thead>
        <tbody>
          <?php foreach($affectations as $a): ?>
          <tr>
            <td><?= sanitize($a['enseignant']) ?></td>
            <td><?= sanitize($a['matiere']) ?></td>
            <td><?= sanitize($a['classe']) ?></td>
            <td><a href="?del_af=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($affectations)): ?><tr><td colspan="4" class="empty-state" style="text-align:center;padding:20px;">Aucune affectation</td></tr><?php endif; ?>
        </tbody>
      </table>
      <form method="POST">
        <input type="hidden" name="action" value="add_affectation">
        <div class="form-group"><label>Enseignant *</label>
          <select name="enseignant_id" class="form-control" required>
            <option value="">-- Choisir --</option>
            <?php foreach($enseignants as $e): ?><option value="<?= $e['id'] ?>"><?= sanitize($e['prenom'].' '.$e['nom']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Matière *</label>
          <select name="matiere_id" class="form-control" required>
            <option value="">-- Choisir --</option>
            <?php foreach($matieres as $m): ?><option value="<?= $m['id'] ?>"><?= sanitize($m['nom']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Classe *</label>
          <select name="classe_id" class="form-control" required>
            <option value="">-- Choisir --</option>
            <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nom'].' ('.$c['niveau_nom'].')') ?></option><?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;"><i class="fas fa-plus"></i> Affecter</button>
      </form>
    </div>
  </div>
</div>

<!-- UTILISATEURS -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-users-cog"></i> Utilisateurs du système</h2></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
      <table>
        <thead><tr><th>Nom</th><th>Username</th><th>Rôle</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td><?= sanitize($u['prenom'].' '.$u['nom']) ?></td>
            <td><code><?= sanitize($u['username']) ?></code></td>
            <td><span class="badge badge-primary"><?= sanitize($u['role']) ?></span></td>
            <td><?= $u['actif'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-danger">Inactif</span>' ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="actif" value="<?= $u['actif']?0:1 ?>">
                <button type="submit" class="btn btn-sm <?= $u['actif']?'btn-warning':'btn-success' ?>"><?= $u['actif']?'Désactiver':'Activer' ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="POST">
        <input type="hidden" name="action" value="add_user">
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
        <div class="form-group"><label>Prénom</label><input type="text" name="prenom" class="form-control"></div>
        <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-control" required></div>
        <div class="form-group"><label>Mot de passe *</label><input type="password" name="password" class="form-control" required></div>
        <div class="form-group"><label>Rôle</label>
          <select name="role" class="form-control">
            <?php foreach(['admin','directeur','enseignant','comptable','secretaire'] as $r): ?>
            <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;"><i class="fas fa-user-plus"></i> Créer</button>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
