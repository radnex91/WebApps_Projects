<?php
/**
 * DocumentController - Gestion des documents
 */
class DocumentController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('documents', 'view');

        $model = new DocumentModel();
        $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        $category = $_GET['category'] ?? '';

        $sql = "SELECT d.*, e.first_name, e.last_name FROM documents d JOIN employees e ON d.employee_id = e.id WHERE 1=1";
        $sqlParams = [];

        if ($employeeId) {
            $sql .= " AND d.employee_id = :employee_id";
            $sqlParams['employee_id'] = $employeeId;
        }
        if ($category) {
            $sql .= " AND d.category = :category";
            $sqlParams['category'] = $category;
        }

        $sql .= " ORDER BY d.created_at DESC";
        $documents = $model->query($sql, $sqlParams);

        $employeeModel = new EmployeeModel();
        $this->view('documents.index', [
            'title' => 'Documents',
            'documents' => $documents,
            'employees' => $employeeModel->findBy(['status' => 'actif']),
            'filterEmployee' => $employeeId,
            'filterCategory' => $category
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('documents', 'create');

        $employeeModel = new EmployeeModel();
        $this->view('documents.create', [
            'title' => 'Ajouter un document',
            'employees' => $employeeModel->findBy(['status' => 'actif'])
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('documents', 'create');

        $data = [
            'employee_id' => (int)($_POST['employee_id'] ?? 0),
            'title' => trim($_POST['title'] ?? ''),
            'category' => $_POST['category'] ?? 'autre',
            'description' => trim($_POST['description'] ?? ''),
            'uploaded_by' => Auth::id(),
        ];

        $validator = new Validator($data);
        $validator->required('employee_id')->required('title');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/documents/create');
        }

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['file'], 'documents', ALLOWED_DOC_TYPES);
            if (isset($result['success'])) {
                $data['file_path'] = $result['success'];
                $data['file_type'] = $_FILES['file']['type'];
                $data['file_size'] = $_FILES['file']['size'];
            } else {
                Flash::error($result['error']);
                $this->redirect('/documents/create');
            }
        } else {
            Flash::error('Veuillez sélectionner un fichier.');
            $this->redirect('/documents/create');
        }

        $model = new DocumentModel();
        $model->create($data);
        Flash::success('Document ajouté avec succès.');
        $this->redirect('/documents');
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('documents', 'view');

        $model = new DocumentModel();
        $document = $model->queryOne(
            "SELECT d.*, e.first_name, e.last_name FROM documents d JOIN employees e ON d.employee_id = e.id WHERE d.id = :id",
            ['id' => $params['id']]
        );

        if (!$document) {
            Flash::error('Document non trouvé.');
            $this->redirect('/documents');
        }

        $this->view('documents.show', [
            'title' => $document['title'],
            'document' => $document
        ]);
    }

    public function download($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('documents', 'view');

        $model = new DocumentModel();
        $document = $model->find($params['id']);

        if (!$document) {
            Flash::error('Document non trouvé.');
            $this->redirect('/documents');
        }

        $filePath = UPLOADS_PATH . '/' . $document['file_path'];
        if (file_exists($filePath)) {
            header('Content-Type: ' . $document['file_type']);
            header('Content-Disposition: attachment; filename="' . basename($document['file_path']) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }

        Flash::error('Fichier non trouvé sur le serveur.');
        $this->redirect('/documents');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('documents', 'delete');

        $model = new DocumentModel();
        $document = $model->find($params['id']);

        if ($document) {
            $filePath = UPLOADS_PATH . '/' . $document['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $model->delete($params['id']);
        }

        Flash::success('Document supprimé avec succès.');
        $this->redirect('/documents');
    }
}