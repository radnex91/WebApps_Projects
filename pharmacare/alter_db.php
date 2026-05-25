<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE ventes ADD COLUMN est_annulee TINYINT(1) DEFAULT 0");
    echo "Column est_annulee added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ── Paramètres de fermeture caisse ──────────────────────────
try {
    $db->exec("INSERT IGNORE INTO parametres (cle, valeur, label, groupe) VALUES
        ('caisse_fermeture_mode', 'manuel', 'Mode fermeture caisse', 'caisse'),
        ('caisse_heure_fermeture', '22:00', 'Heure fermeture auto', 'caisse')");
    echo "<br>Paramètres caisse ajoutés.";
} catch (Exception $e) {
    echo "<br>Paramètres caisse: " . $e->getMessage();
}
?>