<?php
class DashboardController extends Controller {
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
        $stats = [
            'total_landlords' => $this->landlordModel->count(),
            'total_agencies' => $this->agencyModel->count(),
            'total_batches' => $this->batchModel->count(),
            'total_payments' => $this->paymentModel->count(),
            'total_rent' => $this->paymentModel->getTotalRent(),
            'late_payments' => count($this->paymentModel->getLatePayments()),
            'early_payments' => count($this->paymentModel->getEarlyPayments()),
            'by_agency' => $this->paymentModel->getStatsByAgency(),
            'by_landlord' => $this->paymentModel->getStatsByLandlord()
        ];

        $this->view('dashboard/index', ['stats' => $stats]);
    }

    public function exportCSV() {
        PermissionMiddleware::requirePermission('payments_view');
        $payments = $this->paymentModel->getAllWithDetails();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=payments_' . date('Y-m-d') . '.csv');

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
                $payment['payment_status']
            ]);
        }
        fclose($output);
        exit;
    }
}
