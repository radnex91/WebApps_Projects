<?php
/**
 * PositionController - Gestion des postes
 */
class PositionController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('positions', 'view');

        $model = new PositionModel();
        $positions = $model->query(
            "SELECT p.*, d.name as department_name
             FROM positions p
             LEFT JOIN departments d ON p.department_id = d.id
             WHERE p.is_active = 1
             ORDER BY p.title ASC"
        );

        $this->view('positions.index', [
            'title' => 'Postes',
            'positions' => $positions
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('positions', 'create');

        $deptModel = new DepartmentModel();
        $this->view('positions.create', [
            'title' => 'Nouveau poste',
            'departments' => $deptModel->all('name ASC')
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('positions', 'create');

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'salary_min' => (float)($_POST['salary_min'] ?? 0),
            'salary_max' => (float)($_POST['salary_max'] ?? 0),
        ];

        $validator = new Validator($data);
        $validator->required('title')->required('department_id');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/positions/create');
        }

        $model = new PositionModel();
        $model->create($data);
        Flash::success('Poste créé avec succès.');
        $this->redirect('/positions');
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('positions', 'edit');

        $model = new PositionModel();
        $position = $model->find($params['id']);

        if (!$position) {
            Flash::error('Poste non trouvé.');
            $this->redirect('/positions');
        }

        $deptModel = new DepartmentModel();
        $this->view('positions.edit', [
            'title' => 'Modifier le poste',
            'position' => $position,
            'departments' => $deptModel->all('name ASC')
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('positions', 'edit');

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'salary_min' => (float)($_POST['salary_min'] ?? 0),
            'salary_max' => (float)($_POST['salary_max'] ?? 0),
        ];

        $validator = new Validator($data);
        $validator->required('title')->required('department_id');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/positions/' . $params['id'] . '/edit');
        }

        $model = new PositionModel();
        $model->update($params['id'], $data);
        Flash::success('Poste mis à jour avec succès.');
        $this->redirect('/positions');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('positions', 'delete');

        $model = new PositionModel();
        $model->update($params['id'], ['is_active' => 0]);
        Flash::success('Poste désactivé avec succès.');
        $this->redirect('/positions');
    }
}