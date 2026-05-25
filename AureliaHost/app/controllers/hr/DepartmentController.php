<?php
class DepartmentController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $model = new Department();
        $departments = $model->employeeCount();
        $this->render('hr/departments/index', ['departments' => $departments]);
    }

    public function create(): void
    {
        $this->render('hr/departments/create');
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['nom']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Le nom est obligatoire.');
            $this->redirectBack();
            return;
        }
        (new Department())->create(['nom' => $_POST['nom'], 'description' => $_POST['description'] ?? '']);
        Session::setFlash('success', 'Département créé.');
        $this->redirect('/hr/departments');
    }

    public function edit(int $id): void
    {
        $dept = (new Department())->find($id);
        if (!$dept) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hr/departments'); return; }
        $this->render('hr/departments/edit', ['dept' => $dept]);
    }

    public function update(int $id): void
    {
        (new Department())->update($id, ['nom' => $_POST['nom'], 'description' => $_POST['description'] ?? '']);
        Session::setFlash('success', 'Département mis à jour.');
        $this->redirect('/hr/departments');
    }

    public function delete(int $id): void
    {
        (new Department())->delete($id);
        Session::setFlash('success', 'Département supprimé.');
        $this->redirect('/hr/departments');
    }
}
