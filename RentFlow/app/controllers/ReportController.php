<?php
class ReportController extends Controller {
    private $paymentModel;
    private $landlordModel;
    private $agencyModel;
    private $batchModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('dashboard_view');
        $this->paymentModel = new Payment();
        $this->landlordModel = new Landlord();
        $this->agencyModel = new Agency();
        $this->batchModel = new Batch();
    }

    public function index() {
        $year = $_GET['year'] ?? date('Y');

        // Monthly stats for chart
        $monthlyStats = $this->paymentModel->getMonthlyStats($year);

        // Format for Chart.js
        $months = [];
        $amounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = date('M', mktime(0, 0, 0, $i, 1));
            $amounts[] = 0;
        }
        foreach ($monthlyStats as $stat) {
            $amounts[$stat['month'] - 1] = (float)$stat['total'];
        }

        // Yearly stats
        $yearlyStats = $this->paymentModel->getYearlyStats();

        // Status distribution
        $statusStats = $this->paymentModel->getPaymentsByStatus();

        // Top landlords
        $topLandlords = $this->paymentModel->getTopLandlords(10);

        // Top agencies
        $topAgencies = $this->paymentModel->getTopAgencies(10);

        // Stats by agency
        $statsByAgency = $this->paymentModel->getStatsByAgency();

        // Stats by landlord
        $statsByLandlord = $this->paymentModel->getStatsByLandlord();

        $this->view('reports/index', [
            'year' => $year,
            'months' => json_encode($months),
            'amounts' => json_encode($amounts),
            'yearlyStats' => $yearlyStats,
            'statusStats' => $statusStats,
            'topLandlords' => $topLandlords,
            'topAgencies' => $topAgencies,
            'statsByAgency' => $statsByAgency,
            'statsByLandlord' => $statsByLandlord
        ]);
    }

    public function export() {
        PermissionMiddleware::requirePermission('dashboard_view');
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $format = $_GET['format'] ?? 'csv';

        $payments = $this->paymentModel->exportReport($startDate, $endDate);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=rapport_' . $startDate . '_' . $endDate . '.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Lot', 'Bailleur', 'Agence', 'Montant', 'Date échéance', 'Date paiement', 'Statut']);

            foreach ($payments as $payment) {
                fputcsv($output, [
                    $payment['id'],
                    $payment['batch_name'],
                    $payment['landlord_name'],
                    $payment['agency_name'],
                    $payment['amount'],
                    $payment['due_date'],
                    $payment['paid_date'],
                    $payment['status_label']
                ]);
            }
            fclose($output);
            exit;
        }

        // For Excel format (simple HTML table that Excel can open)
        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename=rapport_' . $startDate . '_' . $endDate . '.xls');

            echo '<table border="1">';
            echo '<tr><th>ID</th><th>Lot</th><th>Bailleur</th><th>Agence</th><th>Montant</th><th>Date échéance</th><th>Date paiement</th><th>Statut</th></tr>';
            foreach ($payments as $payment) {
                echo '<tr>';
                echo '<td>' . $payment['id'] . '</td>';
                echo '<td>' . htmlspecialchars($payment['batch_name']) . '</td>';
                echo '<td>' . htmlspecialchars($payment['landlord_name']) . '</td>';
                echo '<td>' . htmlspecialchars($payment['agency_name']) . '</td>';
                echo '<td>' . $payment['amount'] . '</td>';
                echo '<td>' . $payment['due_date'] . '</td>';
                echo '<td>' . $payment['paid_date'] . '</td>';
                echo '<td>' . $payment['status_label'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }

        $_SESSION['error'] = 'Format non supporté';
        $this->redirect('reports');
    }

    public function agency($id) {
        $agency = $this->agencyModel->find($id);
        if (!$agency) {
            $_SESSION['error'] = "Agence introuvable";
            $this->redirect('reports');
        }

        $batchCount = $this->batchModel->countByAgency($id);
        $agency['batches_count'] = $batchCount;

        $year = $_GET['year'] ?? date('Y');
        $monthlyStats = $this->paymentModel->getPaymentsByMonthForAgency($id, $year);

        // Format for Chart.js
        $months = [];
        $amounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = date('M', mktime(0, 0, 0, $i, 1));
            $amounts[] = 0;
        }
        foreach ($monthlyStats as $stat) {
            $amounts[$stat['month'] - 1] = (float)$stat['total'];
        }

        $this->view('reports/agency', [
            'agency' => $agency,
            'year' => $year,
            'months' => json_encode($months),
            'amounts' => json_encode($amounts)
        ]);
    }
}
