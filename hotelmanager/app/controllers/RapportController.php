<?php
/**
 * HotelPro Suite - RapportController
 */

class RapportController
{
    public function index(): void
    {
        $periode   = $_GET['periode']  ?? 'mois';
        $date_from = $_GET['date_from'] ?? date('Y-m-01');
        $date_to   = $_GET['date_to']   ?? date('Y-m-d');

        $ca_total = (float) Database::query(
            "SELECT COALESCE(SUM(p.montant),0) FROM paiements p
             WHERE DATE(p.date_paiement) BETWEEN ? AND ?",
            [$date_from, $date_to]
        )->fetchColumn();

        $nb_reservations = (int) Database::query(
            "SELECT COUNT(*) FROM reservations WHERE date_arrivee BETWEEN ? AND ?",
            [$date_from, $date_to]
        )->fetchColumn();

        $nb_clients_uniques = (int) Database::query(
            "SELECT COUNT(DISTINCT client_id) FROM reservations WHERE date_arrivee BETWEEN ? AND ?",
            [$date_from, $date_to]
        )->fetchColumn();

        // Taux d'occupation moyen sur la période
        $total_chambres = (int) Database::query("SELECT COUNT(*) FROM chambres")->fetchColumn();
        $nb_jours = max(1, (new DateTime($date_from))->diff(new DateTime($date_to))->days + 1);
        $nuits_vendues = (int) Database::query(
            "SELECT COALESCE(SUM(nb_nuits),0) FROM reservations
             WHERE statut NOT IN ('annulee','no_show') AND date_arrivee BETWEEN ? AND ?",
            [$date_from, $date_to]
        )->fetchColumn();
        $taux_occ = $total_chambres > 0
            ? min(100, round(($nuits_vendues / ($total_chambres * $nb_jours)) * 100))
            : 0;

        // Revenus par type de chambre
        $par_type = Database::query(
            "SELECT tc.nom, COUNT(r.id) AS nb_res, SUM(r.montant_total) AS ca
             FROM reservations r
             JOIN chambres ch ON ch.id = r.chambre_id
             JOIN types_chambres tc ON tc.id = ch.type_id
             WHERE r.statut NOT IN ('annulee','no_show')
               AND r.date_arrivee BETWEEN ? AND ?
             GROUP BY tc.id, tc.nom
             ORDER BY ca DESC",
            [$date_from, $date_to]
        )->fetchAll();

        // Évolution CA journalière
        $evolution = Database::query(
            "SELECT DATE(p.date_paiement) AS jour, SUM(p.montant) AS ca
             FROM paiements p
             WHERE DATE(p.date_paiement) BETWEEN ? AND ?
             GROUP BY DATE(p.date_paiement)
             ORDER BY jour",
            [$date_from, $date_to]
        )->fetchAll();

        // Top clients
        $top_clients = Database::query(
            "SELECT CONCAT(c.prenom,' ',c.nom) AS client_nom, COUNT(r.id) AS nb_sejours,
                    SUM(r.montant_total) AS total_depense
             FROM reservations r JOIN clients c ON c.id = r.client_id
             WHERE r.statut NOT IN ('annulee','no_show')
               AND r.date_arrivee BETWEEN ? AND ?
             GROUP BY r.client_id
             ORDER BY total_depense DESC
             LIMIT 10",
            [$date_from, $date_to]
        )->fetchAll();

        // Journal d'activité récent
        $logs = Database::query(
            "SELECT l.*, CONCAT(u.prenom,' ',u.nom) AS user_nom
             FROM logs l LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC LIMIT 20"
        )->fetchAll();

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/rapports/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }
}
