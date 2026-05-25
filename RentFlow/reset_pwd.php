<?php
require_once 'includes/db.php';
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE username IN ('admin', 'manager')");
$stmt->execute([$hash]);
echo "Mots de passe réinitialisés pour admin et manager";