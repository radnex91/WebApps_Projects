<?php
class PaymentController extends Controller {
    private $paymentModel;
    private $batchModel;
    private $reminderModel;
    private $agencyModel;
    private $landlordModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('payments_view');
        $this->paymentModel = new Payment();
        $this->batchModel = new Batch();
        $this->reminderModel = new Reminder();
        $this->agencyModel = new Agency();
        $this->landlordModel = new Landlord();
    }

    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $payments = $this->paymentModel->getAllPaginated($limit, $offset);
        $total = $this->paymentModel->count();
        $totalPages = ceil($total / $limit);
        
        // Get agencies ordered by name for the modal
        $agencies = $this->agencyModel->getAllOrderedByName();
        
        $this->view('payments/index', [
            'payments' => $payments,
            'page' => $page,
            'totalPages' => $totalPages,
            'agencies' => $agencies
        ]);
    }

    public function create() {
        PermissionMiddleware::requirePermission('payments_create');
        $agencies = $this->agencyModel->getAllOrderedByName();
        $this->view('payments/create', ['agencies' => $agencies]);
    }

    public function getLandlordsByAgency($agencyId) {
        while (ob_get_level()) ob_end_clean();
        
        $landlords = $this->agencyModel->getLandlordsByAgency($agencyId);
        if (empty($landlords)) {
            $landlords = $this->landlordModel->getAll();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($landlords, JSON_UNESCAPED_UNICODE);
        // Remove BOM if present
        if (strpos($json, "\xEF\xBB\xBF") === 0) {
            $json = substr($json, 3);
        }
        echo $json;
        exit;
    }

    public function getBatchesByAgencyAndLandlord() {
        while (ob_get_level()) ob_end_clean();
        
        $agencyId = $_GET['agency_id'] ?? 0;
        $landlordId = $_GET['landlord_id'] ?? 0;
        $batches = $this->batchModel->getByAgencyAndLandlord($agencyId, $landlordId);
        
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($batches, JSON_UNESCAPED_UNICODE);
        if (strpos($json, "\xEF\xBB\xBF") === 0) {
            $json = substr($json, 3);
        }
        echo $json;
        exit;
    }

    public function store() {
        PermissionMiddleware::requirePermission('payments_create');
        $this->validateCsrf();
        $errors = $this->validate($_POST, ['batch_id' => 'required', 'amount' => 'required', 'due_date' => 'required']);
        
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect('payments/create');
        }
        
        $data = [
            'batch_id' => (int)$_POST['batch_id'],
            'amount' => (float)$_POST['amount'],
            'due_date' => $_POST['due_date'],
            'paid_date' => !empty($_POST['paid_date']) ? $_POST['paid_date'] : null
        ];
        
        $paymentId = $this->paymentModel->createWithStatus($data);
        logInfo('Payment created', ['id' => $paymentId, 'amount' => $data['amount']]);
        
        if ($data['paid_date'] && $data['paid_date'] > $data['due_date']) {
            $this->reminderModel->createReminder($paymentId);
        }
        
        $_SESSION['success'] = "Paiement enregistré avec succès";
        $this->redirect('payments');
    }

    public function edit($id) {
        PermissionMiddleware::requirePermission('payments_edit');
        $payment = $this->paymentModel->find($id);
        if (!$payment) {
            $_SESSION['error'] = "Paiement introuvable";
            $this->redirect('payments');
        }
        $batches = $this->batchModel->getByAgencyAndLandlord($payment['agency_id'], $payment['landlord_id']);
        $this->view('payments/edit', ['payment' => $payment, 'batches' => $batches]);
    }

    public function update($id) {
        PermissionMiddleware::requirePermission('payments_edit');
        $this->validateCsrf();
        $errors = $this->validate($_POST, ['batch_id' => 'required', 'amount' => 'required', 'due_date' => 'required']);
        
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect("payments/$id/edit");
        }
        
        $data = [
            'batch_id' => (int)$_POST['batch_id'],
            'amount' => (float)$_POST['amount'],
            'due_date' => $_POST['due_date'],
            'paid_date' => !empty($_POST['paid_date']) ? $_POST['paid_date'] : null
        ];
        
        $this->paymentModel->updateWithStatus($id, $data);
        logInfo('Payment updated', ['id' => $id, 'amount' => $data['amount']]);
        $_SESSION['success'] = "Paiement modifié avec succès";
        $this->redirect('payments');
    }

    public function delete($id) {
        PermissionMiddleware::requirePermission('payments_delete');
        $this->validateCsrf();
        $payment = $this->paymentModel->find($id);
        $this->paymentModel->delete($id);
        logInfo('Payment deleted', ['id' => $id, 'amount' => $payment['amount'] ?? 0]);
        $_SESSION['success'] = "Paiement supprimé avec succès";
        $this->redirect('payments');
    }

    public function late() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $payments = $this->paymentModel->getLatePaymentsPaginated($limit, $offset);
        $total = count($this->paymentModel->getLatePayments());
        $totalPages = ceil($total / $limit);
        
        $this->view('payments/late', [
            'payments' => $payments,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function early() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $payments = $this->paymentModel->getEarlyPaymentsPaginated($limit, $offset);
        $total = count($this->paymentModel->getEarlyPayments());
        $totalPages = ceil($total / $limit);
        
        $this->view('payments/early', [
            'payments' => $payments,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function sendReminder($paymentId) {
        PermissionMiddleware::requirePermission('payments_validate');
        $this->validateCsrf();
        $this->reminderModel->createReminder($paymentId);
        logInfo('Payment reminder sent', ['payment_id' => $paymentId]);
        $_SESSION['success'] = "Rappel envoyé avec succès";
        $this->redirect('payments');
    }
}
