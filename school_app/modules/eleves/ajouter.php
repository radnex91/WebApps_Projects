<?php
// modules/eleves/ajouter.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Ajouter un élève';
$isEdit = false;
$eleve = ['id'=>'','nom'=>'','prenom'=>'','date_naissance'=>'','lieu_naissance'=>'','sexe'=>'M','adresse'=>'','parent_id'=>'','statut'=>'actif'];

if (isset($_GET['id'])) {
    $isEdit = true;
    $pageTitle = 'Modifier un élève';
    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE id=?");
    $stmt->execute([$_GET['id']]);
    $eleve = $stmt->fetch() ?: $eleve;
}

$parents = $pdo->query("SELECT id, CONCAT(prenom,' ',nom) as fullname, telephone FROM parents ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $dob = $_POST['date_naissance'] ?? '';
    $lieu = trim($_POST['lieu_naissance'] ?? '');
    $sexe = $_POST['sexe'] ?? 'M';
    $adresse = trim($_POST['adresse'] ?? '');
    $parent_id = $_POST['parent_id'] ?: null;
    $statut = $_POST['statut'] ?? 'actif';

    if (!$nom || !$prenom || !$dob) {
        flash('Nom, prénom et date de naissance sont obligatoires.', 'danger');
    } else {
        if ($isEdit) {
            $stmt = $pdo->prepare("UPDATE eleves SET nom=?,prenom=?,date_naissance=?,lieu_naissance=?,sexe=?,adresse=?,parent_id=?,statut=? WHERE id=?");
            $stmt->execute([$nom,$prenom,$dob,$lieu,$sexe,$adresse,$parent_id,$statut,$_GET['id']]);
            flash("Élève modifié avec succès.");
            redirect(BASE_URL.'modules/eleves/');
        } else {
            // Générer matricule
            $year = date('Y');
            $prefix = 'EL' . $year;
            $last = $pdo->query("SELECT MAX(CAST(SUBSTRING(matricule," . (strlen($prefix)+1) . ") AS UNSIGNED)) FROM eleves WHERE matricule LIKE " . $pdo->quote($prefix . '%'))->fetchColumn();
            $num = str_pad((int)($last ?? 0) + 1, 4, '0', STR_PAD_LEFT);
            $matricule = $prefix . $num;

            $stmt = $pdo->prepare("INSERT INTO eleves (matricule,nom,prenom,date_naissance,lieu_naissance,sexe,adresse,parent_id,statut) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$matricule,$nom,$prenom,$dob,$lieu,$sexe,$adresse,$parent_id,$statut]);
            flash("Élève ajouté avec succès. Matricule : $matricule");
            redirect(BASE_URL.'modules/eleves/');
        }
    }
}

include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a>
  <span class="breadcrumb-sep"></span> <a href="<?= BASE_URL ?>modules/eleves/">Élèves</a>
  <span class="breadcrumb-sep"></span> <?= $isEdit ? 'Modifier' : 'Ajouter' ?>
</div>

<div class="card" style="max-width:800px;margin:0 auto;">
  <div class="card-header">
    <h2><i class="fas fa-<?= $isEdit?'edit':'user-plus' ?>"></i> <?= $pageTitle ?></h2>
  </div>
  <div class="card-body">
    <form method="POST">
      <div class="form-grid">
        <div class="form-group">
          <label>Nom <span style="color:red">*</span></label>
          <input type="text" name="nom" class="form-control" value="<?= sanitize($eleve['nom']) ?>" required placeholder="Nom de famille">
        </div>
        <div class="form-group">
          <label>Prénom <span style="color:red">*</span></label>
          <input type="text" name="prenom" class="form-control" value="<?= sanitize($eleve['prenom']) ?>" required placeholder="Prénom(s)">
        </div>
        <div class="form-group">
          <label>Date de naissance <span style="color:red">*</span></label>
          <input type="date" name="date_naissance" class="form-control" value="<?= sanitize($eleve['date_naissance']) ?>" required>
        </div>
        <div class="form-group">
          <label>Lieu de naissance</label>
          <input type="text" name="lieu_naissance" class="form-control" value="<?= sanitize($eleve['lieu_naissance']) ?>" placeholder="Ville / Village">
        </div>
        <div class="form-group">
          <label>Sexe <span style="color:red">*</span></label>
          <select name="sexe" class="form-control" required>
            <option value="M" <?= $eleve['sexe']=='M'?'selected':'' ?>>Masculin</option>
            <option value="F" <?= $eleve['sexe']=='F'?'selected':'' ?>>Féminin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Statut</label>
          <select name="statut" class="form-control">
            <?php foreach(['actif','inactif','transfere','diplome'] as $s): ?>
            <option value="<?= $s ?>" <?= $eleve['statut']==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Adresse</label>
          <input type="text" name="adresse" class="form-control" value="<?= sanitize($eleve['adresse']) ?>" placeholder="Adresse complète">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Parent / Tuteur</label>
          <select name="parent_id" class="form-control">
            <option value="">— Sélectionner un parent —</option>
            <?php foreach($parents as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $eleve['parent_id']==$p['id']?'selected':'' ?>>
              <?= sanitize($p['fullname']) ?> (<?= sanitize($p['telephone']??'') ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <small style="color:var(--text-muted);">Si le parent n'existe pas, <a href="../parents/ajouter.php">créez-le d'abord</a>.</small>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $isEdit?'Enregistrer':'Ajouter l\'élève' ?></button>
        <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
