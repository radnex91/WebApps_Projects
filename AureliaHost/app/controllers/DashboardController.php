<?php
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $db = Database::getInstance()->getConnection();

        $kpis = [
            'rooms_total'     => $db->query("SELECT COUNT(*) as n FROM rooms")->fetch()['n'],
            'rooms_available' => $db->query("SELECT COUNT(*) as n FROM rooms WHERE statut = 'disponible'")->fetch()['n'],
            'rooms_occupied'  => $db->query("SELECT COUNT(*) as n FROM rooms WHERE statut = 'occupee'")->fetch()['n'],
            'reservations_active' => $db->query("SELECT COUNT(*) as n FROM reservations WHERE statut IN ('confirmee','en_cours')")->fetch()['n'],
            'clients_total'   => $db->query("SELECT COUNT(*) as n FROM clients")->fetch()['n'],
            'employees_total' => $db->query("SELECT COUNT(*) as n FROM employees WHERE statut = 'actif'")->fetch()['n'],
            'leave_pending'   => $db->query("SELECT COUNT(*) as n FROM leaves WHERE statut = 'en_attente'")->fetch()['n'],
            'revenue_month'   => $db->query("SELECT COALESCE(SUM(montant_ttc),0) as n FROM invoices WHERE MONTH(date_emission) = MONTH(CURDATE()) AND YEAR(date_emission) = YEAR(CURDATE()) AND statut != 'annulee'")->fetch()['n'],
            'unpaid_invoices' => $db->query("SELECT COUNT(*) as n FROM invoices WHERE statut = 'envoyee'")->fetch()['n'],
            'expenses_month'  => $db->query("SELECT COALESCE(SUM(montant),0) as n FROM expenses WHERE MONTH(date_depense) = MONTH(CURDATE()) AND YEAR(date_depense) = YEAR(CURDATE())")->fetch()['n'],
        ];

        $recentReservations = $db->query(
            "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom
             FROM reservations r
             JOIN clients c ON r.client_id = c.id
             ORDER BY r.created_at DESC LIMIT 5"
        )->fetchAll();

        $recentLeaves = $db->query(
            "SELECT l.*, e.nom, e.prenom
             FROM leaves l
             JOIN employees e ON l.employee_id = e.id
             ORDER BY l.created_at DESC LIMIT 5"
        )->fetchAll();

        $this->render('dashboard/index', [
            'kpis'               => $kpis,
            'recentReservations' => $recentReservations,
            'recentLeaves'       => $recentLeaves,
        ]);
    }
}
