<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

$colisCount = Database::fetchOne("SELECT COUNT(*) as c FROM colis");
$agencesCount = Database::fetchOne("SELECT COUNT(*) as c FROM agences");

echo "Colis: " . $colisCount['c'] . "<br>";
echo "Agences: " . $agencesCount['c'] . "<br><br>";

echo "Agences:<br>";
$agences = Database::fetchAll("SELECT id, code, nom FROM agences");
foreach ($agences as $a) {
    echo "- ID {$a['id']}: {$a['nom']} ({$a['code']})<br>";
}

echo "<br>Colis agency IDs:<br>";
$colisIds = Database::fetchAll("SELECT DISTINCT agence_depart_id, agence_arrivee_id FROM colis");
foreach ($colisIds as $c) {
    echo "- depart: {$c['agence_depart_id']}, arrivee: {$c['agence_arrivee_id']}<br>";
}
