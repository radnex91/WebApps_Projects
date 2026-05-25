<?php
// modules/eleves/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('eleves.view');
$isEdit=isset($_GET['id']); $pageTitle=$isEdit?'Modifier un élève':'Ajouter un élève';
$e=['id'=>'','nom'=>'','prenom'=>'','date_naissance'=>'','lieu_naissance'=>'','sexe'=>'M','nationalite'=>'Camerounaise','adresse'=>'','telephone'=>'','email'=>'','statut'=>'actif','parent_id'=>''];
if($isEdit){$st=$pdo->prepare("SELECT * FROM eleves WHERE id=?");$st->execute([$_GET['id']]);$e=$st->fetch()?:$e;}
$parents=$pdo->query("SELECT id,CONCAT(prenom,' ',nom) as n,telephone FROM parents ORDER BY nom")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
    requirePerm($isEdit?'eleves.edit':'eleves.create');
    $nom=trim($_POST['nom']??'');$prenom=trim($_POST['prenom']??'');
    if(!$nom||!$prenom){flash('Nom et prénom obligatoires.','danger');}
    else{
        $d=['nom'=>$nom,'prenom'=>$prenom,'date_naissance'=>$_POST['date_naissance']??null,'lieu_naissance'=>trim($_POST['lieu_naissance']??''),'sexe'=>$_POST['sexe']??'M','nationalite'=>trim($_POST['nationalite']??'Camerounaise'),'adresse'=>trim($_POST['adresse']??''),'telephone'=>trim($_POST['telephone']??''),'email'=>trim($_POST['email']??''),'statut'=>$_POST['statut']??'actif','parent_id'=>$_POST['parent_id']?:null];
        if($isEdit){$sets=implode('=?,',array_keys($d)).'=?';$pdo->prepare("UPDATE eleves SET $sets WHERE id=?")->execute([...array_values($d),$_GET['id']]);flash('Élève modifié.');}
        else{$mat=genMatricule($pdo,'EL','eleves');$pdo->prepare("INSERT INTO eleves (matricule,nom,prenom,date_naissance,lieu_naissance,sexe,nationalite,adresse,telephone,email,statut,parent_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$mat,...array_values($d)]);flash("Élève ajouté — Matricule : $mat");}
        logAction($pdo,$isEdit?'update_eleve':'create_eleve','eleves',$nom.' '.$prenom);
        redirect(BASE_URL.'modules/eleves/');
    }
}
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Élèves</a><span class="breadcrumb-sep">/</span><?= $isEdit?'Modifier':'Ajouter' ?></div>
<div class="card" style="max-width:860px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-<?= $isEdit?'edit':'user-plus' ?>"></i> <?= $pageTitle ?></h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-section"><div class="form-section-title"><i class="fas fa-user"></i> Informations personnelles</div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="nom" class="form-control" value="<?= sanitize($e['nom']) ?>" required></div>
          <div class="form-group"><label class="form-label">Prénom(s) <span class="form-required">*</span></label><input type="text" name="prenom" class="form-control" value="<?= sanitize($e['prenom']) ?>" required></div>
          <div class="form-group"><label class="form-label">Date de naissance</label><input type="date" name="date_naissance" class="form-control" value="<?= sanitize($e['date_naissance']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Lieu de naissance</label><input type="text" name="lieu_naissance" class="form-control" value="<?= sanitize($e['lieu_naissance']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Sexe</label><select name="sexe" class="form-control"><option value="M" <?= $e['sexe']==='M'?'selected':'' ?>>Masculin</option><option value="F" <?= $e['sexe']==='F'?'selected':'' ?>>Féminin</option></select></div>
          <div class="form-group"><label class="form-label">Nationalité</label><input type="text" name="nationalite" class="form-control" value="<?= sanitize($e['nationalite']??'Camerounaise') ?>"></div>
          <div class="form-group"><label class="form-label">Téléphone</label><input type="tel" name="telephone" class="form-control" value="<?= sanitize($e['telephone']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= sanitize($e['email']??'') ?>"></div>
          <div class="form-group full"><label class="form-label">Adresse</label><input type="text" name="adresse" class="form-control" value="<?= sanitize($e['adresse']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Statut</label><select name="statut" class="form-control"><?php foreach(['actif','inactif','transfere','diplome'] as $s): ?><option value="<?= $s ?>" <?= $e['statut']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">Parent/Tuteur</label><select name="parent_id" class="form-control"><option value="">— Aucun —</option><?php foreach($parents as $p): ?><option value="<?= $p['id'] ?>" <?= $e['parent_id']==$p['id']?'selected':'' ?>><?= sanitize($p['n'].' ('.$p['telephone'].')') ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
        <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $isEdit?'Modifier':'Ajouter' ?></button>
      </div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
