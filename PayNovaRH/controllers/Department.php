<?php
/**
 * DepartmentController - Gestion des départements
 */
class DepartmentController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('departments', 'view');

        $model = new DepartmentModel();
        $departments = $model->query(
            "SELECT d.*, e.first_name as manager_first_name, e.last_name as manager_last_name,
                    (SELECT COUNT(*) FROM employees WHERE department_id = d.id AND status = 'actif') as employee_count
             FROM departments d
             LEFT JOIN employees e ON d.manager_id = e.id
             WHERE d.is_active = 1
             ORDER BY d.name ASC"
        );

        $this->view('departments.index', [
            'title' => 'Départements',
            'departments' => $departments
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('departments', 'create');

        $employeeModel = new EmployeeModel();
        $deptModel = new DepartmentModel();

        $this->view('departments.create', [
            'title' => 'Nouveau département',
            'employees' => $employeeModel->findBy(['status' => 'actif']),
            'departments' => $deptModel->all('name ASC')
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('departments', 'create');

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'manager_id' => (int)($_POST['manager_id'] ?? 0) ?: null,
            'parent_id' => (int)($_POST['parent_id'] ?? 0) ?: null,
        ];

        $validator = new Validator($data);
        $validator->required('name');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/departments/create');
        }

        $model = new DepartmentModel();
        $model->create($data);
        Flash::success('Département créé avec succès.');
        $this->redirect('/departments');
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('departments', 'edit');

        $model = new DepartmentModel();
        $department = $model->find($params['id']);

        if (!$department) {
            Flash::error('Département non trouvé.');
            $this->redirect('/departments');
        }

        $employeeModel = new EmployeeModel();
        $deptModel = new DepartmentModel();

        $this->view('departments.edit', [
            'title' => 'Modifier le département',
            'department' => $department,
            'employees' => $employeeModel->findBy(['status' => 'actif']),
            'departments' => $deptModel->all('name ASC')
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('departments', 'edit');

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'manager_id' => (int)($_POST['manager_id'] ?? 0) ?: null,
            'parent_id' => (int)($_POST['parent_id'] ?? 0) ?: null,
        ];

        $validator = new Validator($data);
        $validator->required('name');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/departments/' . $params['id'] . '/edit');
        }

        $model = new DepartmentModel();
        $model->update($params['id'], $data);
        Flash::success('Département mis à jour avec succès.');
        $this->redirect('/departments');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('departments', 'delete');

        $model = new DepartmentModel();
        // Soft delete
        $model->update($params['id'], ['is_active' => 0]);
        Flash::success('Département désactivé avec succès.');
        $this->redirect('/departments');
    }
}