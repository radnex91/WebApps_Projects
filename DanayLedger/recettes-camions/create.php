<?php
// Rediriger vers la page index avec le modal de création
require_once __DIR__ . '/../config/database.php';
header('Location: ' . APP_URL . '/recettes-camions/index.php');
exit;