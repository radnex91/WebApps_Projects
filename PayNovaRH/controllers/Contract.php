<?php
/**
 * ContractController - Gestion des contrats
 */
class ContractController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('contracts', 'view');

        $model = new ContractModel();
        $contracts = $model->query(
            "SELECT c.*, e.first_name, e.last_name
             FROM contracts c
             JOIN employees e ON c.employee_id = e.id
             ORDER BY c.is_active DESC, c.start_date DESC"
        );

        $this->view('contracts.index', [
            'title' => 'Contrats',
            'contracts' => $contracts
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('contracts', 'create');

        $employeeModel = new EmployeeModel();
        $this->view('contracts.create', [
            'title' => 'Nouveau contrat',
            'employees' => $employeeModel->findBy(['status' => 'actif'])
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('contracts', 'create');

        $data = [
            'employee_id' => (int)($_POST['employee_id'] ?? 0),
            'type' => $_POST['type'] ?? 'CDI',
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? null,
            'salary' => (float)($_POST['salary'] ?? 0),
            'renewal_date' => $_POST['renewal_date'] ?? null,
            'description' => trim($_POST['description'] ?? ''),
            'is_active' => 1,
        ];

        $validator = new Validator($data);
        $validator->required('employee_id')->required('start_date')->required('salary')->numeric('salary');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/contracts/create');
        }

        if ($data['end_date'] === '') $data['end_date'] = null;
        if ($data['renewal_date'] === '') $data['renewal_date'] = null;

        $model = new ContractModel();
        $model->create($data);
        Flash::success('Contrat créé avec succès.');
        $this->redirect('/contracts');
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('contracts', 'edit');

        $model = new ContractModel();
        $contract = $model->find($params['id']);

        if (!$contract) {
            Flash::error('Contrat non trouvé.');
            $this->redirect('/contracts');
        }

        $employeeModel = new EmployeeModel();
        $this->view('contracts.edit', [
            'title' => 'Modifier le contrat',
            'contract' => $contract,
            'employees' => $employeeModel->findBy(['status' => 'actif'])
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('contracts', 'edit');

        $data = [
            'employee_id' => (int)($_POST['employee_id'] ?? 0),
            'type' => $_POST['type'] ?? 'CDI',
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? null,
            'salary' => (float)($_POST['salary'] ?? 0),
            'renewal_date' => $_POST['renewal_date'] ?? null,
            'description' => trim($_POST['description'] ?? ''),
            'is_active' => (int)($_POST['is_active'] ?? 1),
        ];

        if ($data['end_date'] === '') $data['end_date'] = null;
        if ($data['renewal_date'] === '') $data['renewal_date'] = null;

        $model = new ContractModel();
        $model->update($params['id'], $data);
        Flash::success('Contrat mis à jour avec succès.');
        $this->redirect('/contracts');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('contracts', 'delete');

        $model = new ContractModel();
        $model->delete($params['id']);
        Flash::success('Contrat supprimé avec succès.');
        $this->redirect('/contracts');
    }
}