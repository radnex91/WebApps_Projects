<?php
// modules/enseignants/ajouter.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Ajouter un enseignant';
$isEdit = isset($_GET['id']);
$ens = ['id'=>'','nom'=>'','prenom'=>'','date_naissance'=>'','sexe'=>'M','telephone'=>'','email'=>'','adresse'=>'','specialite'=>'','diplome'=>'','date_embauche'=>date('Y-m-d'),'statut'=>'actif'];

if ($isEdit) {
    $pageTitle = 'Modifier un enseignant';
    $stmt = $pdo->prepare("SELECT * FROM enseignants WHERE id=?");
    $stmt->execute([$_GET['id']]);
    $ens = $stmt->fetch() ?: $ens;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']??'');
    $prenom = trim($_POST['prenom']??'');
    if (!$nom || !$prenom) { flash('Nom et prénom obligatoires.','danger'); }
    else {
        $fields = ['nom','prenom','date_naissance','sexe','telephone','email','adresse','specialite','diplome','date_embauche','statut'];
        $data = []; foreach($fields as $f) $data[$f] = trim($_POST[$f]??'') ?: null;
        if ($isEdit) {
            $sets = implode('=?,', $fields).'=?';
            $pdo->prepare("UPDATE enseignants SET $sets WHERE id=?")->execute([...array_values($data), $_GET['id']]);
            flash('Enseignant modifié.');
        } else {
            $year = date('Y');
            $prefix = 'ENS' . $year;
            $last = $pdo->query("SELECT MAX(CAST(SUBSTRING(matricule," . (strlen($prefix)+1) . ") AS UNSIGNED)) FROM enseignants WHERE matricule LIKE " . $pdo->quote($prefix . '%'))->fetchColumn();
            $mat = $prefix . str_pad((int)($last ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            $cols = implode(',',array_keys($data));
            $phs = implode(',',array_fill(0,count($data),'?'));
            $pdo->prepare("INSERT INTO enseignants (matricule,$cols) VALUES (?,$phs)")->execute([$mat,...array_values($data)]);
            flash("Enseignant ajouté. Matricule : $mat");
        }
        redirect(BASE_URL.'modules/enseignants/');
    }
}
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span><a href=".">Enseignants</a><span class="breadcrumb-sep"></span><?= $isEdit?'Modifier':'Ajouter' ?></div>
<div class="card" style="max-width:800px;margin:0 auto;">
  <div class="card-header"><h2><i class="fas fa-<?= $isEdit?'edit':'user-plus' ?>"></i> <?= $pageTitle ?></h2></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-grid">
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" value="<?= sanitize($ens['nom']) ?>" required></div>
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" class="form-control" value="<?= sanitize($ens['prenom']) ?>" required></div>
        <div class="form-group"><label>Sexe</label>
          <select name="sexe" class="form-control">
            <option value="M" <?= $ens['sexe']=='M'?'selected':'' ?>>Masculin</option>
            <option value="F" <?= $ens['sexe']=='F'?'selected':'' ?>>Féminin</option>
          </select>
        </div>
        <div class="form-group"><label>Date de naissance</label><input type="date" name="date_naissance" class="form-control" value="<?= sanitize($ens['date_naissance']) ?>"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" class="form-control" value="<?= sanitize($ens['telephone']) ?>"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($ens['email']) ?>"></div>
        <div class="form-group"><label>Spécialité / Matière</label><input type="text" name="specialite" class="form-control" value="<?= sanitize($ens['specialite']) ?>" placeholder="Ex: Mathématiques"></div>
        <div class="form-group"><label>Diplôme</label><input type="text" name="diplome" class="form-control" value="<?= sanitize($ens['diplome']) ?>" placeholder="Ex: CAPES, Licence..."></div>
        <div class="form-group"><label>Date d'embauche</label><input type="date" name="date_embauche" class="form-control" value="<?= sanitize($ens['date_embauche']) ?>"></div>
        <div class="form-group"><label>Statut</label>
          <select name="statut" class="form-control">
            <option value="actif" <?= $ens['statut']=='actif'?'selected':'' ?>>Actif</option>
            <option value="inactif" <?= $ens['statut']=='inactif'?'selected':'' ?>>Inactif</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1;"><label>Adresse</label><input type="text" name="adresse" class="form-control" value="<?= sanitize($ens['adresse']) ?>"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
      </div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
