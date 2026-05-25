<?php
require_once 'config.php';
require_once 'includes/db.php';

$hash = password_hash('password', PASSWORD_DEFAULT);
echo "Nouveau hash: $hash\n";

$result = query("UPDATE utilisateurs SET password = ? WHERE login IN ('admin', 'gestionnaire')", [$hash]);
echo "Lignes affectées: " . $result . "\n";

// Vérifier
$users = query("SELECT login, password FROM utilisateurs");
foreach ($users as $u) {
    echo "{$u['login']}: " . substr($u['password'], 0, 20) . "...\n";
}
