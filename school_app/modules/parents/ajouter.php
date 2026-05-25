<?php
// modules/parents/ajouter.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Ajouter un parent';
$isEdit = isset($_GET['id']);
$p = ['id'=>'','nom'=>'','prenom'=>'','telephone'=>'','email'=>'','adresse'=>'','profession'=>''];
if ($isEdit) { $pageTitle='Modifier un parent'; $st=$pdo->prepare("SELECT * FROM parents WHERE id=?"); $st->execute([$_GET['id']]); $p=$st->fetch()?:$p; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nom=trim($_POST['nom']??''); $prenom=trim($_POST['prenom']??'');
    if(!$nom||!$prenom){flash('Nom et prénom obligatoires.','danger');}
    else{
        $d=['nom'=>$nom,'prenom'=>$prenom,'telephone'=>trim($_POST['telephone']??'')?:null,'email'=>trim($_POST['email']??'')?:null,'adresse'=>trim($_POST['adresse']??'')?:null,'profession'=>trim($_POST['profession']??'')?:null];
        if($isEdit){$sets=implode('=?,',array_keys($d)).'=?';$pdo->prepare("UPDATE parents SET $sets WHERE id=?")->execute([...array_values($d),$_GET['id']]);flash('Parent modifié.');}
        else{$pdo->prepare("INSERT INTO parents (nom,prenom,telephone,email,adresse,profession) VALUES (?,?,?,?,?,?)")->execute(array_values($d));flash('Parent ajouté.');}
        redirect(BASE_URL.'modules/parents/');
    }
}
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span><a href=".">Parents</a><span class="breadcrumb-sep"></span><?= $isEdit?'Modifier':'Ajouter' ?></div>
<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header"><h2><i class="fas fa-user-plus"></i> <?= $pageTitle ?></h2></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-grid">
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" value="<?= sanitize($p['nom']) ?>" required></div>
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" class="form-control" value="<?= sanitize($p['prenom']) ?>" required></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" class="form-control" value="<?= sanitize($p['telephone']) ?>"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($p['email']) ?>"></div>
        <div class="form-group"><label>Profession</label><input type="text" name="profession" class="form-control" value="<?= sanitize($p['profession']) ?>"></div>
        <div class="form-group" style="grid-column:1/-1;"><label>Adresse</label><input type="text" name="adresse" class="form-control" value="<?= sanitize($p['adresse']) ?>"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
      </div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
