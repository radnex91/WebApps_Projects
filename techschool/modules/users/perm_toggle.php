<?php
// modules/users/perm_toggle.php
require_once '../../includes/config.php';
requireLogin(); if(!isSuperAdmin()) redirect(BASE_URL.'modules/users/');
$rid=(int)($_POST['role_id']??0); $pid=(int)($_POST['perm_id']??0);
$exists=$pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id=? AND permission_id=?");
$exists->execute([$rid,$pid]);
if($exists->fetch()) $pdo->prepare("DELETE FROM role_permissions WHERE role_id=? AND permission_id=?")->execute([$rid,$pid]);
else $pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)")->execute([$rid,$pid]);
redirect(BASE_URL.'modules/users/');
