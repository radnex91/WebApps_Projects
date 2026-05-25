<?php
// ============================================================
// functions.php - Fonctions utilitaires métier
// ============================================================
require_once __DIR__ . '/db.php';

function formatMontant(float $val): string {
    return number_format($val, 0, ',', ' ') . ' FCFA';
}

function formatQte(float $val): string {
    return number_format($val, 3, ',', ' ');
}

/**
 * Recalcule le CMUPACE et stock après mouvement
 * CMUPACE = Coût Moyen Pondéré Pondéré ACtualisé à chaque Entrée
 */
function calculerCMUPACE(int $articleId, string $dateDebut = null): void {
    $sql = "SELECT * FROM mouvements WHERE article_id = ? ORDER BY date_mouvement ASC, id ASC";
    $params = [$articleId];
    if ($dateDebut) {
        $sql = "SELECT * FROM mouvements WHERE article_id = ? AND date_mouvement >= ? ORDER BY date_mouvement ASC, id ASC";
        $params = [$articleId, $dateDebut];
    }
    
    $mouvements = query($sql, $params);
    
    $stockQte = 0;
    $stockVal = 0;
    $cmupace = 0;
    
    // Récupérer stock initial si recalcul partiel
    if ($dateDebut) {
        $prev = queryOne(
            "SELECT stock_apres, valeur_stock_apres, cmupace FROM mouvements 
             WHERE article_id = ? AND date_mouvement < ? ORDER BY date_mouvement DESC, id DESC LIMIT 1",
            [$articleId, $dateDebut]
        );
        if ($prev) {
            $stockQte = (float)$prev['stock_apres'];
            $stockVal = (float)$prev['valeur_stock_apres'];
            $cmupace  = (float)$prev['cmupace'];
        }
    }
    
    foreach ($mouvements as $m) {
        $qte = (float)$m['quantite'];
        $pu  = (float)$m['prix_unitaire'];
        
        if ($m['type_mouvement'] === 'entree') {
            // Recalcul CMUPACE à chaque entrée
            $newStockQte = $stockQte + $qte;
            $newStockVal = $stockVal + ($qte * $pu);
            $cmupace     = $newStockQte > 0 ? round($newStockVal / $newStockQte, 2) : $pu;
            $stockQte    = $newStockQte;
            $stockVal    = $newStockVal;
        } else {
            // Sortie au CMUPACE
            $stockQte -= $qte;
            $stockVal  = $stockQte * $cmupace;
        }
        
        execute(
            "UPDATE mouvements SET stock_apres = ?, valeur_stock_apres = ?, cmupace = ? WHERE id = ?",
            [round($stockQte, 3), round($stockVal, 2), $cmupace, $m['id']]
        );
    }
    
    // Mettre à jour l'article
    execute(
        "UPDATE articles SET stock_actuel = ?, valeur_stock = ?, cmupace = ? WHERE id = ?",
        [round($stockQte, 3), round($stockVal, 2), $cmupace, $articleId]
    );
}

/**
 * Mettre à jour l'analyse mensuelle
 */
function updateAnalyseMensuelle(int $articleId, int $annee, int $mois): void {
    $data = queryOne(
        "SELECT 
            COALESCE(SUM(CASE WHEN type_mouvement='entree' THEN quantite ELSE 0 END),0) AS qte_e,
            COALESCE(SUM(CASE WHEN type_mouvement='sortie' THEN quantite ELSE 0 END),0) AS qte_s,
            COALESCE(SUM(CASE WHEN type_mouvement='entree' THEN quantite*prix_unitaire ELSE 0 END),0) AS mt_e,
            COALESCE(SUM(CASE WHEN type_mouvement='sortie' THEN quantite*prix_unitaire ELSE 0 END),0) AS mt_s
         FROM mouvements WHERE article_id=? AND annee=? AND mois=?",
        [$articleId, $annee, $mois]
    );
    
    $stockPrev = queryOne(
        "SELECT COALESCE(qte_stock,0) AS qs FROM analyse_mensuelle 
         WHERE article_id=? AND (annee*100+mois) < ? ORDER BY annee DESC, mois DESC LIMIT 1",
        [$articleId, $annee*100+$mois]
    );
    
    $qteStock = ($stockPrev['qs'] ?? 0) + $data['qte_e'] - $data['qte_s'];
    $marge    = $data['mt_e'] - $data['mt_s'];
    
    execute(
        "INSERT INTO analyse_mensuelle (article_id, annee, mois, qte_entrees, qte_sorties, qte_stock, montant_entrees, montant_sorties, marge)
         VALUES (?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
         qte_entrees=VALUES(qte_entrees), qte_sorties=VALUES(qte_sorties), qte_stock=VALUES(qte_stock),
         montant_entrees=VALUES(montant_entrees), montant_sorties=VALUES(montant_sorties), marge=VALUES(marge)",
        [$articleId, $annee, $mois, $data['qte_e'], $data['qte_s'], $qteStock, $data['mt_e'], $data['mt_s'], $marge]
    );
}

/**
 * Obtenir les statistiques du dashboard
 */
function getDashboardStats(): array {
    $stats = [];
    
    // Total articles en stock
    $r = queryOne("SELECT COUNT(*) AS nb, SUM(valeur_stock) AS val FROM articles WHERE actif=1");
    $stats['nb_articles']    = (int)$r['nb'];
    $stats['valeur_totale']  = (float)($r['val'] ?? 0);
    
    // Mouvements du mois courant
    $annee = date('Y'); $mois = date('m');
    $r = queryOne(
        "SELECT COUNT(*) AS nb,
         SUM(CASE WHEN type_mouvement='entree' THEN montant ELSE 0 END) AS mt_e,
         SUM(CASE WHEN type_mouvement='sortie' THEN montant ELSE 0 END) AS mt_s
         FROM mouvements WHERE annee=? AND mois=?",
        [$annee, $mois]
    );
    $stats['nb_mvt_mois']       = (int)($r['nb'] ?? 0);
    $stats['entrees_mois']      = (float)($r['mt_e'] ?? 0);
    $stats['sorties_mois']      = (float)($r['mt_s'] ?? 0);
    
    // Alertes stock bas
    $stats['alertes'] = query("SELECT designation, stock_actuel, stock_alerte FROM articles WHERE stock_actuel <= stock_alerte AND actif=1");
    
    return $stats;
}

/**
 * Obtenir les mouvements d'un article pour un mois
 */
function getMouvements(int $articleId, int $annee, int $mois): array {
    return query(
        "SELECT m.*, f.nom AS fournisseur_nom 
         FROM mouvements m
         LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
         WHERE m.article_id = ? AND m.annee = ? AND m.mois = ?
         ORDER BY m.date_mouvement ASC, m.id ASC",
        [$articleId, $annee, $mois]
    );
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
