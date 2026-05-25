<?php
class ReportController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    private function fetchValue(string $sql): mixed
    {
        $db = Database::getInstance()->getConnection();
        return $db->query($sql)->fetch()['valeur'] ?? $db->query($sql)->fetch()['total'] ?? $db->query($sql)->fetch()['t'] ?? 0;
    }

    public function balanceSheet(): void
    {
        $db = Database::getInstance()->getConnection();

        $actifRooms = $db->query(
            "SELECT COALESCE(SUM(rt.prix_base), 0) as valeur
             FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id"
        )->fetch()['valeur'];

        $actifCreances = $db->query(
            "SELECT COALESCE(SUM(montant_ttc), 0) as total FROM invoices WHERE statut = 'envoyee'"
        )->fetch()['total'];

        $passifPaie = $db->query(
            "SELECT COALESCE(SUM(salaire_net), 0) as total FROM payrolls WHERE statut != 'paye'"
        )->fetch()['total'];

        $this->render('accounting/reports/balance-sheet', [
            'actifRooms' => $actifRooms,
            'actifCreances' => $actifCreances,
            'passifPaie' => $passifPaie,
        ]);
    }

    public function incomeStatement(): void
    {
        $year = $_GET['year'] ?? date('Y');
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            "SELECT MONTH(i.date_emission) as mois, COALESCE(SUM(i.montant_ttc), 0) as total
             FROM invoices i WHERE YEAR(i.date_emission) = :y AND i.statut != 'annulee'
             GROUP BY MONTH(i.date_emission) ORDER BY mois"
        );
        $stmt->execute(['y' => $year]);
        $revenus = $stmt->fetchAll();

        $stmt = $db->prepare(
            "SELECT MONTH(e.date_depense) as mois, COALESCE(SUM(e.montant), 0) as total
             FROM expenses e WHERE YEAR(e.date_depense) = :y
             GROUP BY MONTH(e.date_depense) ORDER BY mois"
        );
        $stmt->execute(['y' => $year]);
        $depenses = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT COALESCE(SUM(montant_ttc),0) as t FROM invoices WHERE YEAR(date_emission) = :y AND statut != 'annulee'");
        $stmt->execute(['y' => $year]);
        $totalRevenus = $stmt->fetch()['t'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) as t FROM expenses WHERE YEAR(date_depense) = :y");
        $stmt->execute(['y' => $year]);
        $totalDepenses = $stmt->fetch()['t'];

        $this->render('accounting/reports/income', [
            'revenus' => $revenus,
            'depenses' => $depenses,
            'totalRevenus' => $totalRevenus,
            'totalDepenses' => $totalDepenses,
            'year' => $year,
        ]);
    }
}
