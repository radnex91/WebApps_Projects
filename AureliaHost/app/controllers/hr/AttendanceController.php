<?php
class AttendanceController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        $model = new Attendance();
        $attendances = $model->allWithEmployee($date);
        $employees = (new Employee())->all("statut = 'actif'", [], 'nom, prenom');
        $this->render('hr/attendance/index', ['attendances' => $attendances, 'employees' => $employees, 'date' => $date]);
    }

    public function report(): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        $model = new Attendance();
        $report = $model->getReport($month);
        $this->render('hr/attendance/report', ['report' => $report, 'month' => $month]);
    }

    public function store(): void
    {
        $model = new Attendance();

        // Check duplicate
        $exists = $model->queryOne(
            "SELECT id FROM attendance WHERE employee_id = :eid AND date = :d",
            ['eid' => $_POST['employee_id'], 'd' => $_POST['date']]
        );

        if ($exists) {
            $model->update($exists['id'], [
                'heure_entree' => $_POST['heure_entree'] ?? null,
                'heure_sortie' => $_POST['heure_sortie'] ?? null,
                'statut' => $_POST['statut'],
                'notes' => $_POST['notes'] ?? '',
            ]);
            Session::setFlash('success', 'Présence mise à jour.');
        } else {
            $model->create([
                'employee_id' => $_POST['employee_id'],
                'date' => $_POST['date'],
                'heure_entree' => $_POST['heure_entree'] ?? null,
                'heure_sortie' => $_POST['heure_sortie'] ?? null,
                'statut' => $_POST['statut'],
                'notes' => $_POST['notes'] ?? '',
            ]);
            Session::setFlash('success', 'Présence enregistrée.');
        }
        $this->redirectBack();
    }
}
