<?php
require_once '../../includes/config.php';
requireLogin();
if (isset($_GET['id'])) { $pdo->prepare("DELETE FROM parents WHERE id=?")->execute([$_GET['id']]); flash('Parent supprimé.','warning'); }
redirect(BASE_URL.'modules/parents/');
