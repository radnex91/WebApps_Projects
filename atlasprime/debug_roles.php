<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

echo "Roles dans la base:<br>";
$roles = Database::fetchAll("SELECT * FROM roles ORDER BY niveau DESC");
foreach ($roles as $r) {
    echo "- ID {$r['id']}: {$r['nom']} (niveau: {$r['niveau']}, actif: " . ($r['actif'] ?? 'N/A') . ")<br>";
}

echo "<br>Roles avec actif=1:<br>";
$rolesActif = Database::fetchAll("SELECT * FROM roles WHERE actif = 1 ORDER BY niveau DESC");
foreach ($rolesActif as $r) {
    echo "- ID {$r['id']}: {$r['nom']}<br>";
}
