<?php
/**
 * EmployeeController - Gestion des employés
 */
class EmployeeController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'view');

        $model = new EmployeeModel();
        extract($this->getPaginationParams());

        if ($search) {
            $results = $model->query(
                "SELECT e.*, d.name as department_name, p.title as position_name
                 FROM employees e
                 LEFT JOIN departments d ON e.department_id = d.id
                 LEFT JOIN positions p ON e.position_id = p.id
                 WHERE (e.first_name LIKE :search OR e.last_name LIKE :search OR e.email LIKE :search OR e.cin LIKE :search)
                 ORDER BY e.id DESC
                 LIMIT :limit OFFSET :offset",
                ['search' => "%{$search}%", 'limit' => ITEMS_PER_PAGE, 'offset' => ($page - 1) * ITEMS_PER_PAGE]
            );
            $total = $model->queryOne(
                "SELECT COUNT(*) as total FROM employees
                 WHERE first_name LIKE :search OR last_name LIKE :search OR email LIKE :search",
                ['search' => "%{$search}%"]
            )['total'];
        } else {
            $results = $model->query(
                "SELECT e.*, d.name as department_name, p.title as position_name
                 FROM employees e
                 LEFT JOIN departments d ON e.department_id = d.id
                 LEFT JOIN positions p ON e.position_id = p.id
                 ORDER BY e.id DESC
                 LIMIT :limit OFFSET :offset",
                ['limit' => ITEMS_PER_PAGE, 'offset' => ($page - 1) * ITEMS_PER_PAGE]
            );
            $total = $model->count();
        }

        $this->view('employees.index', [
            'title' => 'Employés',
            'employees' => $results,
            'page' => $page,
            'totalPages' => ceil($total / ITEMS_PER_PAGE),
            'search' => $search
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'create');

        $deptModel = new DepartmentModel();
        $posModel = new PositionModel();

        $this->view('employees.create', [
            'title' => 'Nouvel employé',
            'departments' => $deptModel->all('name ASC'),
            'positions' => $posModel->all('title ASC')
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'create');

        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'country' => trim($_POST['country'] ?? 'Maroc'),
            'birth_date' => $_POST['birth_date'] ?? null,
            'gender' => $_POST['gender'] ?? 'homme',
            'marital_status' => $_POST['marital_status'] ?? 'célibataire',
            'children_count' => (int)($_POST['children_count'] ?? 0),
            'cin' => trim($_POST['cin'] ?? ''),
            'cnss' => trim($_POST['cnss'] ?? ''),
            'department_id' => (int)($_POST['department_id'] ?? 0) ?: null,
            'position_id' => (int)($_POST['position_id'] ?? 0) ?: null,
            'hire_date' => $_POST['hire_date'] ?? null,
            'status' => 'actif',
            'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'bank_account' => trim($_POST['bank_account'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('first_name')->required('last_name')->required('email')
            ->email('email')->required('hire_date');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/employees/create');
        }

        $model = new EmployeeModel();

        // Check unique email
        $existing = $model->findOneBy(['email' => $data['email']]);
        if ($existing) {
            Flash::error('Un employé avec cet email existe déjà.');
            $this->redirect('/employees/create');
        }

        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['photo'], 'photos', ALLOWED_IMAGE_TYPES);
            if (isset($result['success'])) {
                $data['photo'] = $result['success'];
            }
        }

        $id = $model->create($data);

        // Create user account if requested
        if (isset($_POST['create_account']) && $_POST['create_account'] === '1') {
            $roleModel = new RoleModel();
            $defaultRole = $roleModel->findOneBy(['is_default' => 1]);
            $userModel = new UserModel();
            $username = strtolower($data['first_name'] . '.' . $data['last_name']);
            $password = generatePassword();
            $userModel->create([
                'username' => $username,
                'email' => $data['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role_id' => $defaultRole ? $defaultRole['id'] : 3,
                'is_active' => 1
            ]);

            // Link user to employee
            $newUser = $userModel->findOneBy(['username' => $username]);
            $model->update($id, ['user_id' => $newUser['id']]);
        }

        Flash::success('Employé créé avec succès.');
        $this->redirect('/employees');
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'view');

        $model = new EmployeeModel();
        $employee = $model->queryOne(
            "SELECT e.*, d.name as department_name, p.title as position_name,
                    c.type as contract_type, c.salary as contract_salary, c.start_date as contract_start, c.end_date as contract_end
             FROM employees e
             LEFT JOIN departments d ON e.department_id = d.id
             LEFT JOIN positions p ON e.position_id = p.id
             LEFT JOIN contracts c ON e.id = c.employee_id AND c.is_active = 1
             WHERE e.id = :id",
            ['id' => $params['id']]
        );

        if (!$employee) {
            Flash::error('Employé non trouvé.');
            $this->redirect('/employees');
        }

        // Get leave balances
        $leaveModel = new LeaveBalanceModel();
        $balances = $leaveModel->getByEmployee($params['id'], date('Y'));

        // Get documents
        $docModel = new DocumentModel();
        $documents = $docModel->findBy(['employee_id' => $params['id']]);

        $this->view('employees.show', [
            'title' => $employee['first_name'] . ' ' . $employee['last_name'],
            'employee' => $employee,
            'balances' => $balances,
            'documents' => $documents
        ]);
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'edit');

        $model = new EmployeeModel();
        $employee = $model->find($params['id']);

        if (!$employee) {
            Flash::error('Employé non trouvé.');
            $this->redirect('/employees');
        }

        $deptModel = new DepartmentModel();
        $posModel = new PositionModel();

        $this->view('employees.edit', [
            'title' => 'Modifier l\'employé',
            'employee' => $employee,
            'departments' => $deptModel->all('name ASC'),
            'positions' => $posModel->all('title ASC')
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'edit');

        $model = new EmployeeModel();
        $employee = $model->find($params['id']);

        if (!$employee) {
            Flash::error('Employé non trouvé.');
            $this->redirect('/employees');
        }

        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'country' => trim($_POST['country'] ?? 'Maroc'),
            'birth_date' => $_POST['birth_date'] ?? null,
            'gender' => $_POST['gender'] ?? 'homme',
            'marital_status' => $_POST['marital_status'] ?? 'célibataire',
            'children_count' => (int)($_POST['children_count'] ?? 0),
            'cin' => trim($_POST['cin'] ?? ''),
            'cnss' => trim($_POST['cnss'] ?? ''),
            'department_id' => (int)($_POST['department_id'] ?? 0) ?: null,
            'position_id' => (int)($_POST['position_id'] ?? 0) ?: null,
            'hire_date' => $_POST['hire_date'] ?? $employee['hire_date'],
            'status' => $_POST['status'] ?? $employee['status'],
            'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'bank_account' => trim($_POST['bank_account'] ?? ''),
        ];

        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['photo'], 'photos', ALLOWED_IMAGE_TYPES);
            if (isset($result['success'])) {
                $data['photo'] = $result['success'];
            }
        }

        $model->update($params['id'], $data);
        Flash::success('Employé mis à jour avec succès.');
        $this->redirect('/employees');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('employees', 'delete');

        $model = new EmployeeModel();
        if ($model->delete($params['id'])) {
            Flash::success('Employé supprimé avec succès.');
        } else {
            Flash::error('Erreur lors de la suppression.');
        }
        $this->redirect('/employees');
    }
}