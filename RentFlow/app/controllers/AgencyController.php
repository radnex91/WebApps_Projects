<?php
class AgencyController extends Controller {
    private $agencyModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('agencies_view');
        $this->agencyModel = new Agency();
    }

    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $agencies = $this->agencyModel->getAllPaginated($limit, $offset);
        $total = $this->agencyModel->count();
        $totalPages = ceil($total / $limit);

        $this->view('agencies/index', [
            'agencies' => $agencies,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function create() {
        PermissionMiddleware::requirePermission('agencies_create');
        $this->view('agencies/create');
    }

    public function store() {
        PermissionMiddleware::requirePermission('agencies_create');
        $this->validateCsrf();
        $errors = $this->validate($_POST, ['name' => 'required', 'contact' => 'required']);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect('agencies/create');
        }

        $data = [
            'name' => $this->sanitize($_POST['name']),
            'contact' => $this->sanitize($_POST['contact'])
        ];

        $id = $this->agencyModel->create($data);
        logInfo('Agency created', ['id' => $id, 'name' => $data['name']]);
        $_SESSION['success'] = "Agence ajoutée avec succès";
        $this->redirect('agencies');
    }

    public function edit($id) {
        PermissionMiddleware::requirePermission('agencies_edit');
        $agency = $this->agencyModel->find($id);
        if (!$agency) {
            $_SESSION['error'] = "Agence introuvable";
            $this->redirect('agencies');
        }
        $this->view('agencies/edit', ['agency' => $agency]);
    }

    public function update($id) {
        PermissionMiddleware::requirePermission('agencies_edit');
        $this->validateCsrf();
        $errors = $this->validate($_POST, ['name' => 'required', 'contact' => 'required']);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect("agencies/$id/edit");
        }

        $data = [
            'name' => $this->sanitize($_POST['name']),
            'contact' => $this->sanitize($_POST['contact'])
        ];

        $this->agencyModel->update($id, $data);
        logInfo('Agency updated', ['id' => $id, 'name' => $data['name']]);
        $_SESSION['success'] = "Agence modifiée avec succès";
        $this->redirect('agencies');
    }

    public function delete($id) {
        PermissionMiddleware::requirePermission('agencies_delete');
        $this->validateCsrf();
        $agency = $this->agencyModel->find($id);
        $this->agencyModel->delete($id);
        logInfo('Agency deleted', ['id' => $id, 'name' => $agency['name'] ?? 'unknown']);
        $_SESSION['success'] = "Agence supprimée avec succès";
        $this->redirect('agencies');
    }
}
