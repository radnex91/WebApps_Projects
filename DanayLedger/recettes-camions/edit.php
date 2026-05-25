<?php
// Rediriger vers la page index (l'édition se fait maintenant en modal)
require_once __DIR__ . '/../config/database.php';
header('Location: ' . APP_URL . '/recettes-camions/index.php');
exit;