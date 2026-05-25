<?php
/**
 * HotelPro Suite - DashboardController
 * Statistiques et indicateurs clés du tableau de bord
 */

class DashboardController
{
    public function index(): void
    {
        $stats  = $this->getStats();
        $recent = $this->getRecentReservations();
        $chart  = $this->getRevenueChart();
        $occ    = $this->getOccupancyByType();

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/dashboard/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    private function getStats(): array
    {
        $today = date('Y-m-d');

        // Taux d'occupation
        $total = (int) Database::query("SELECT COUNT(*) FROM chambres")->fetchColumn();
        $occ   = (int) Database::query("SELECT COUNT(*) FROM chambres WHERE statut = 'occupee'")->fetchColumn();
        $taux  = $total > 0 ? round(($occ / $total) * 100) : 0;

        // Réservations actives (check-in aujourd'hui)
        $checkins = (int) Database::query(
            "SELECT COUNT(*) FROM reservations WHERE statut IN ('checkin','confirmee') AND date_arrivee <= ? AND date_depart >= ?",
            [$today, $today]
        )->fetchColumn();

        // Chiffre d'affaires du jour
        $ca_jour = (float) Database::query(
            "SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE DATE(date_paiement) = ?",
            [$today]
        )->fetchColumn();

        // CA du mois
        $ca_mois = (float) Database::query(
            "SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE YEAR(date_paiement) = YEAR(NOW()) AND MONTH(date_paiement) = MONTH(NOW())"
        )->fetchColumn();

        // Chambres libres
        $libres = (int) Database::query("SELECT COUNT(*) FROM chambres WHERE statut = 'disponible'")->fetchColumn();

        // Check-in aujourd'hui
        $checkin_auj = (int) Database::query(
            "SELECT COUNT(*) FROM reservations WHERE statut = 'confirmee' AND date_arrivee = ?",
            [$today]
        )->fetchColumn();

        // Check-out aujourd'hui
        $checkout_auj = (int) Database::query(
            "SELECT COUNT(*) FROM reservations WHERE statut = 'checkin' AND date_depart = ?",
            [$today]
        )->fetchColumn();

        // Factures impayées
        $impayees = (int) Database::query(
            "SELECT COUNT(*) FROM factures WHERE statut = 'emise'"
        )->fetchColumn();

        return compact('taux', 'checkins', 'ca_jour', 'ca_mois', 'libres',
                       'checkin_auj', 'checkout_auj', 'impayees', 'total', 'occ');
    }

    private function getRecentReservations(): array
    {
        return Database::query(
            "SELECT r.reference, r.statut, r.date_arrivee, r.date_depart, r.nb_nuits, r.montant_total,
                    CONCAT(c.prenom,' ',c.nom) AS client_nom, c.telephone,
                    ch.numero AS chambre_num, tc.nom AS type_chambre
             FROM reservations r
             JOIN clients c ON c.id = r.client_id
             JOIN chambres ch ON ch.id = r.chambre_id
             JOIN types_chambres tc ON tc.id = ch.type_id
             ORDER BY r.created_at DESC
             LIMIT 8"
        )->fetchAll();
    }

    private function getRevenueChart(): array
    {
        // Revenus des 7 derniers jours
        $rows = Database::query(
            "SELECT DATE(date_paiement) AS jour, SUM(montant) AS total
             FROM paiements
             WHERE date_paiement >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(date_paiement)
             ORDER BY jour ASC"
        )->fetchAll();

        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d/m', strtotime($d));
            $found = array_filter($rows, fn($r) => $r['jour'] === $d);
            $values[] = $found ? (float) reset($found)['total'] : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function getOccupancyByType(): array
    {
        return Database::query(
            "SELECT tc.nom, COUNT(c.id) AS total,
                    SUM(c.statut = 'occupee') AS occupees,
                    tc.tarif_nuit
             FROM types_chambres tc
             LEFT JOIN chambres c ON c.type_id = tc.id
             GROUP BY tc.id, tc.nom, tc.tarif_nuit
             ORDER BY tc.tarif_nuit DESC"
        )->fetchAll();
    }
}
