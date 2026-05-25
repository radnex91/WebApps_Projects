<?php
require_once '../../includes/config.php';
requireLogin();
if (isset($_GET['id'])) { $pdo->prepare("DELETE FROM enseignants WHERE id=?")->execute([$_GET['id']]); flash('Enseignant supprimé.','warning'); }
redirect(BASE_URL.'modules/enseignants/');
