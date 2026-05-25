<?php
class BatchController extends Controller {
    private $batchModel;
    private $landlordModel;
    private $agencyModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('batches_view');
        $this->batchModel = new Batch();
        $this->landlordModel = new Landlord();
        $this->agencyModel = new Agency();
    }

    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page -1) * $limit;

        $batches = $this->batchModel->getAllPaginated($limit, $offset);
        $total = $this->batchModel->count();
        $totalPages = ceil($total / $limit);

        $this->view('batches/index', [
            'batches' => $batches,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function create() {
        PermissionMiddleware::requirePermission('batches_create');
        $landlords = $this->landlordModel->getAll();
        $agencies = $this->agencyModel->getAll();
        $this->view('batches/create', [
            'landlords' => $landlords,
            'agencies' => $agencies
        ]);
    }

    public function store() {
        PermissionMiddleware::requirePermission('batches_create');
        $this->validateCsrf();
        $errors = $this->validate($_POST, [
            'name' => 'required',
            'landlord_id' => 'required',
            'agency_id' => 'required',
            'monthly_price' => 'required'
        ]);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect('batches/create');
        }

        $data = [
            'name' => $this->sanitize($_POST['name']),
            'landlord_id' => (int)$_POST['landlord_id'],
            'agency_id' => (int)$_POST['agency_id'],
            'monthly_price' => (float)$_POST['monthly_price']
        ];

        $id = $this->batchModel->create($data);
        logInfo('Batch created', ['id' => $id, 'name' => $data['name']]);
        $_SESSION['success'] = "Lot ajouté avec succès";
        $this->redirect('batches');
    }

    public function edit($id) {
        PermissionMiddleware::requirePermission('batches_edit');
        $batch = $this->batchModel->find($id);
        if (!$batch) {
            $_SESSION['error'] = "Lot introuvable";
            $this->redirect('batches');
        }
        $landlords = $this->landlordModel->getAll();
        $agencies = $this->agencyModel->getAll();
        $this->view('batches/edit', [
            'batch' => $batch,
            'landlords' => $landlords,
            'agencies' => $agencies
        ]);
    }

    public function update($id) {
        PermissionMiddleware::requirePermission('batches_edit');
        $this->validateCsrf();
        $errors = $this->validate($_POST, [
            'name' => 'required',
            'landlord_id' => 'required',
            'agency_id' => 'required',
            'monthly_price' => 'required'
        ]);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect("batches/$id/edit");
        }

        $data = [
            'name' => $this->sanitize($_POST['name']),
            'landlord_id' => (int)$_POST['landlord_id'],
            'agency_id' => (int)$_POST['agency_id'],
            'monthly_price' => (float)$_POST['monthly_price']
        ];

        $this->batchModel->update($id, $data);
        logInfo('Batch updated', ['id' => $id, 'name' => $data['name']]);
        $_SESSION['success'] = "Lot modifié avec succès";
        $this->redirect('batches');
    }

    public function delete($id) {
        PermissionMiddleware::requirePermission('batches_delete');
        $this->validateCsrf();
        $batch = $this->batchModel->find($id);
        $this->batchModel->delete($id);
        logInfo('Batch deleted', ['id' => $id, 'name' => $batch['name'] ?? 'unknown']);
        $_SESSION['success'] = "Lot supprimé avec succès";
        $this->redirect('batches');
    }
}
