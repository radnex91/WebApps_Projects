<?php
// modules/eleves/supprimer.php
require_once '../../includes/config.php';
requireLogin();
if (isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM eleves WHERE id=?")->execute([$_GET['id']]);
    flash('Élève supprimé.', 'warning');
}
redirect(BASE_URL . 'modules/eleves/');
