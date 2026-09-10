<?php
/**
 * Migration : ajoute la colonne remise_pct à codes_remise (taux lié au code).
 *
 * Usage : ouvrir une fois  http://localhost/pharmacare/_archive/add_remise_pct.php
 * Idempotent. Supprimer ce fichier apres execution.
 */
require_once __DIR__ . '/../config/database.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE codes_remise ADD COLUMN remise_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER expires_at");
    echo "OK : colonne codes_remise.remise_pct ajoutee.<br>";
} catch (Exception $e) {
    echo "IGNORER : " . $e->getMessage() . "<br>";
}
echo "<p><b>Done.</b> Vous pouvez supprimer ce fichier.</p>";