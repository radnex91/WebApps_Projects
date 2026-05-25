<?php
class LeaveController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $model = new Leave();
        $leaves = $model->allWithDetails();
        $this->render('hr/leaves/index', ['leaves' => $leaves]);
    }

    public function create(): void
    {
        $employees = (new Employee())->all("statut = 'actif'", [], 'nom, prenom');
        $this->render('hr/leaves/create', ['employees' => $employees]);
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['employee_id', 'date_debut', 'date_fin']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Employé, date début et date fin obligatoires.');
            $this->redirectBack();
            return;
        }

        (new Leave())->create([
            'employee_id' => $_POST['employee_id'],
            'type' => $_POST['type'] ?? 'conge_paye',
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin'],
            'motif' => $_POST['motif'] ?? '',
            'statut' => 'en_attente',
        ]);

        Session::setFlash('success', 'Demande de congé créée.');
        $this->redirect('/hr/leaves');
    }

    public function approve(int $id): void
    {
        (new Leave())->update($id, [
            'statut' => 'approuve',
            'approuve_par' => Session::get('user_id'),
        ]);
        Session::setFlash('success', 'Congé approuvé.');
        $this->redirect('/hr/leaves');
    }

    public function reject(int $id): void
    {
        (new Leave())->update($id, [
            'statut' => 'refuse',
            'approuve_par' => Session::get('user_id'),
        ]);
        Session::setFlash('success', 'Congé refusé.');
        $this->redirect('/hr/leaves');
    }

    public function delete(int $id): void
    {
        (new Leave())->delete($id);
        Session::setFlash('success', 'Congé supprimé.');
        $this->redirect('/hr/leaves');
    }
}
