<?php
class EmployeeController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $model = new Employee();
        $employees = $model->allWithDepartment();
        $this->render('hr/employees/index', ['employees' => $employees]);
    }

    public function create(): void
    {
        $departments = (new Department())->all('', [], 'nom');
        $this->render('hr/employees/create', ['departments' => $departments]);
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['nom', 'prenom']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Nom et prénom obligatoires.');
            $this->redirectBack();
            return;
        }

        (new Employee())->create([
            'nom'           => $_POST['nom'],
            'prenom'        => $_POST['prenom'],
            'email'         => $_POST['email'] ?? '',
            'telephone'     => $_POST['telephone'] ?? '',
            'adresse'       => $_POST['adresse'] ?? '',
            'department_id' => $_POST['department_id'] ?: null,
            'poste'         => $_POST['poste'] ?? '',
            'date_embauche' => $_POST['date_embauche'] ?: null,
            'salaire_base'  => $_POST['salaire_base'] ?? 0,
            'statut'        => $_POST['statut'] ?? 'actif',
            'type_contrat'  => $_POST['type_contrat'] ?? 'cdi',
        ]);

        Session::setFlash('success', 'Employé créé.');
        $this->redirect('/hr/employees');
    }

    public function edit(int $id): void
    {
        $employee = (new Employee())->find($id);
        if (!$employee) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hr/employees'); return; }
        $departments = (new Department())->all('', [], 'nom');
        $this->render('hr/employees/edit', ['employee' => $employee, 'departments' => $departments]);
    }

    public function update(int $id): void
    {
        (new Employee())->update($id, [
            'nom'           => $_POST['nom'],
            'prenom'        => $_POST['prenom'],
            'email'         => $_POST['email'] ?? '',
            'telephone'     => $_POST['telephone'] ?? '',
            'adresse'       => $_POST['adresse'] ?? '',
            'department_id' => $_POST['department_id'] ?: null,
            'poste'         => $_POST['poste'] ?? '',
            'date_embauche' => $_POST['date_embauche'] ?: null,
            'salaire_base'  => $_POST['salaire_base'] ?? 0,
            'statut'        => $_POST['statut'],
            'type_contrat'  => $_POST['type_contrat'],
        ]);
        Session::setFlash('success', 'Employé mis à jour.');
        $this->redirect('/hr/employees');
    }

    public function delete(int $id): void
    {
        (new Employee())->delete($id);
        Session::setFlash('success', 'Employé supprimé.');
        $this->redirect('/hr/employees');
    }

    public function show(int $id): void
    {
        $employee = (new Employee())->findWithDepartment($id);
        if (!$employee) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hr/employees'); return; }

        $attendance = (new Attendance())->query(
            "SELECT * FROM attendance WHERE employee_id = :eid ORDER BY date DESC LIMIT 20",
            ['eid' => $id]
        );
        $leaves = (new Leave())->query(
            "SELECT * FROM `leaves` WHERE employee_id = :eid ORDER BY created_at DESC LIMIT 10",
            ['eid' => $id]
        );

        $this->render('hr/employees/show', ['employee' => $employee, 'attendance' => $attendance, 'leaves' => $leaves]);
    }
}
