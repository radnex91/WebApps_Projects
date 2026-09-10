<?php
/**
 * Bench des requêtes cibles — avant/après tuning MySQL.
 * Usage : php tools/bench_perf.php
 */

require __DIR__ . '/../config/env.php';

define('Q_RUNS', 7);

function q(PDO $pdo, string $label, string $sql, array $params = []): void {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stmt->fetchAll();
        $stmt->closeCursor();
        $times = [];
        for ($i = 0; $i < Q_RUNS; $i++) {
            $t = microtime(true);
            $stmt->execute($params);
            $stmt->fetchAll();
            $times[] = (microtime(true) - $t) * 1000;
            $stmt->closeCursor();
        }
        sort($times);
        $median = $times[intdiv(count($times), 2)];
        printf("%-38s median %8.2f ms   max %8.2f ms\n", $label, $median, $times[count($times) - 1]);
    } catch (Throwable $e) {
        printf("%-38s ERREUR : %s\n", $label, $e->getMessage());
    }
}

$pdo = new PDO('mysql:host=localhost;dbname=pharmacare;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

printf("== %s | rows ventes=%s vente_lignes=%s mouvements_magasin=%s ==\n",
    date('Y-m-d H:i:s'),
    $pdo->query("SELECT COUNT(*) FROM ventes")->fetchColumn(),
    $pdo->query("SELECT COUNT(*) FROM vente_lignes")->fetchColumn(),
    $pdo->query("SELECT COUNT(*) FROM mouvements_magasin")->fetchColumn()
);

// Dashboard admin (includes/dashboard-admin.php)
q($pdo, 'CA mois', "SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND created_at < DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')");
q($pdo, 'CA annee', "SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= MAKEDATE(YEAR(NOW()),1) AND created_at < MAKEDATE(YEAR(NOW())+1,1)");
q($pdo, 'Ventes 7j (group by jour)', "SELECT DATE(created_at) AS jour, SUM(total) AS total, COUNT(*) AS nb FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY jour");
q($pdo, 'Top 5 produits 30j', "SELECT vl.produit_nom, SUM(vl.quantite) AS qte FROM vente_lignes vl JOIN ventes v ON vl.vente_id=v.id WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY vl.produit_nom ORDER BY qte DESC LIMIT 5");
q($pdo, 'Stock critique', "SELECT p.nom, p.stock, p.seuil_alerte FROM produits p WHERE p.stock <= p.seuil_alerte AND p.actif=1 ORDER BY p.stock ASC LIMIT 6");
q($pdo, 'Dernieres ventes', "SELECT v.*, u.prenom FROM ventes v LEFT JOIN utilisateurs u ON v.caissier_id=u.id ORDER BY v.created_at DESC LIMIT 8");

// Magasin historique (modules/magasin.php)
q($pdo, 'COUNT transferts_magasin', "SELECT COUNT(*) FROM transferts_magasin");
q($pdo, 'COUNT mouvements_magasin', "SELECT COUNT(*) FROM mouvements_magasin");
q($pdo, 'Historique mouvements page 25', "SELECT m.*, p.nom AS pnom, u.prenom FROM mouvements_magasin m LEFT JOIN produits p ON m.produit_id=p.id LEFT JOIN utilisateurs u ON m.utilisateur_id=u.id ORDER BY m.created_at DESC LIMIT 25 OFFSET 0");
q($pdo, 'Stats produits actifs', "SELECT COUNT(*) AS refs, SUM(CASE WHEN stock_magasin <= seuil_magasin THEN 1 ELSE 0 END) AS alerte, COALESCE(SUM(stock_magasin),0) AS total_mag FROM produits WHERE actif=1");

// Historique ventes (modules/ventes_hist.php)
q($pdo, 'Ventes hist 30j + COUNT', "SELECT COUNT(*) FROM ventes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

// Audit
q($pdo, 'Audit log recent', "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 50");