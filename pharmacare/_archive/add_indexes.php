<?php
/**
 * Migration : ajoute les index de performance manquants sur une base existante.
 *
 * Usage : ouvrir une fois dans le navigateur  http://localhost/pharmacare/_archive/add_indexes.php
 *         (ou en CLI : php _archive/add_indexes.php)
 * Idempotent : les index IF NOT EXISTS ne provoquent pas d'erreur s'ils existent deja.
 * Supprimer ce fichier apres execution.
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_ventes_created_at    ON ventes (created_at)",
    "CREATE INDEX IF NOT EXISTS idx_ventes_caissier_date  ON ventes (caissier_id, created_at)",
    "CREATE INDEX IF NOT EXISTS idx_ventes_client_date    ON ventes (client_id, created_at)",
    "CREATE INDEX IF NOT EXISTS idx_ventes_mode_date      ON ventes (mode_paiement, created_at)",
    "CREATE INDEX IF NOT EXISTS idx_ventes_statut_date    ON ventes (statut_paiement, created_at)",
    "CREATE INDEX IF NOT EXISTS idx_vente_lignes_vente    ON vente_lignes (vente_id)",
    "CREATE INDEX IF NOT EXISTS idx_mvt_stock_prod        ON mouvements_stock (produit_id, created_at)",
    "CREATE INDEX IF NOT EXISTS idx_commandes_statut      ON commandes (statut, created_at)",
    "CREATE INDEX IF NOT EXISTS idx_ecritures_date        ON ecritures (date_ecriture)",
    "CREATE INDEX IF NOT EXISTS idx_sessions_caisse_statut ON sessions_caisse (statut)",
    "CREATE INDEX IF NOT EXISTS idx_retours_vente_date   ON retours_vente (date_retour)",
];

echo "<h2>Ajout des index de performance</h2><pre>";
foreach ($indexes as $ddl) {
    try {
        $db->exec($ddl);
        echo "OK : " . substr($ddl, 0, 60) . "...\n";
    } catch (Exception $e) {
        echo "IGNORER : " . $e->getMessage() . "\n";
    }
}
echo "</pre><p><b>Done.</b> Vous pouvez supprimer ce fichier.</p>";