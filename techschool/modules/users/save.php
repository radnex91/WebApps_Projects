<?php
// modules/users/save.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('users.manage');

$id       = (int)($_POST['id']??0);
$nom      = trim($_POST['nom']??'');
$prenom   = trim($_POST['prenom']??'');
$username = trim($_POST['username']??'');
$email    = trim($_POST['email']??'');
$role_id  = (int)($_POST['role_id']??0);
$password = $_POST['password']??'';
$color    = trim($_POST['avatar_color']??'#2563eb');

if(!$nom||!$username||!$role_id){ flash('Champs obligatoires manquants.','danger'); redirect(BASE_URL.'modules/users/'); }

if($id){
    if($password){ $pdo->prepare("UPDATE users SET nom=?,prenom=?,username=?,email=?,role_id=?,avatar_color=?,password=? WHERE id=?")->execute([$nom,$prenom,$username,$email,$role_id,$color,password_hash($password,PASSWORD_DEFAULT),$id]); }
    else { $pdo->prepare("UPDATE users SET nom=?,prenom=?,username=?,email=?,role_id=?,avatar_color=? WHERE id=?")->execute([$nom,$prenom,$username,$email,$role_id,$color,$id]); }
    flash('Utilisateur modifié.');
} else {
    if(!$password){ flash('Mot de passe requis pour un nouvel utilisateur.','danger'); redirect(BASE_URL.'modules/users/'); }
    try {
        $pdo->prepare("INSERT INTO users (username,password,nom,prenom,email,role_id,avatar_color) VALUES (?,?,?,?,?,?,?)")
            ->execute([$username,password_hash($password,PASSWORD_DEFAULT),$nom,$prenom,$email,$role_id,$color]);
        flash('Utilisateur créé.');
    } catch(PDOException $e){ flash('Username ou email déjà utilisé.','danger'); }
}
redirect(BASE_URL.'modules/users/');
