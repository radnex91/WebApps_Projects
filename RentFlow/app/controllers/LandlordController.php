<?php
class LandlordController extends Controller {
    private $landlordModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('landlords_view');
        $this->landlordModel = new Landlord();
    }

    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        if ($search) {
            $landlords = $this->landlordModel->search($search);
            $total = count($landlords);
        } else {
            $landlords = $this->landlordModel->getAllPaginated($limit, $offset);
            $total = $this->landlordModel->count();
        }

        $totalPages = ceil($total / $limit);

        $this->view('landlords/index', [
            'landlords' => $landlords,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search
        ]);
    }

    public function create() {
        PermissionMiddleware::requirePermission('landlords_create');
        $this->view('landlords/create');
    }

    public function store() {
        PermissionMiddleware::requirePermission('landlords_create');
        $this->validateCsrf();
        $errors = $this->validate($_POST, ['name' => 'required', 'contact' => 'required']);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect('landlords/create');
        }

        $data = [
            'name' => $this->sanitize($_POST['name']),
            'contact' => $this->sanitize($_POST['contact']),
            'contract_details' => $this->sanitize($_POST['contract_details'] ?? '')
        ];

        $id = $this->landlordModel->create($data);
        logInfo('Landlord created', ['id' => $id, 'name' => $data['name']]);
        $_SESSION['success'] = "Bailleur ajouté avec succès";
        $this->redirect('landlords');
    }

    public function edit($id) {
        PermissionMiddleware::requirePermission('landlords_edit');
        $landlord = $this->landlordModel->find($id);
        if (!$landlord) {
            $_SESSION['error'] = "Bailleur introuvable";
            $this->redirect('landlords');
        }
        $this->view('landlords/edit', ['landlord' => $landlord]);
    }

    public function update($id) {
        PermissionMiddleware::requirePermission('landlords_edit');
        $this->validateCsrf();
        $errors = $this->validate($_POST, ['name' => 'required', 'contact' => 'required']);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect("landlords/$id/edit");
        }

        $data = [
            'name' => $this->sanitize($_POST['name']),
            'contact' => $this->sanitize($_POST['contact']),
            'contract_details' => $this->sanitize($_POST['contract_details'] ?? '')
        ];

        $this->landlordModel->update($id, $data);
        logInfo('Landlord updated', ['id' => $id, 'name' => $data['name']]);
        $_SESSION['success'] = "Bailleur modifié avec succès";
        $this->redirect('landlords');
    }

    public function delete($id) {
        PermissionMiddleware::requirePermission('landlords_delete');
        $this->validateCsrf();
        $this->landlordModel->delete($id);
        logInfo('Landlord deleted', ['id' => $id]);
        $_SESSION['success'] = "Bailleur supprimé avec succès";
        $this->redirect('landlords');
    }
}
