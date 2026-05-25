<?php
/**
 * AttendanceController - Gestion du pointage
 */
class AttendanceController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('attendance', 'view');

        $model = new AttendanceModel();
        $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

        $attendance = $model->query(
            "SELECT a.*, e.first_name, e.last_name, e.photo
             FROM attendance a
             JOIN employees e ON a.employee_id = e.id
             WHERE MONTH(a.date) = :month AND YEAR(a.date) = :year
             ORDER BY a.date DESC, e.last_name",
            ['month' => $month, 'year' => $year]
        );

        $summary = $model->getSummary($month, $year);

        $this->view('attendance.index', [
            'title' => 'Pointage',
            'attendance' => $attendance,
            'summary' => $summary,
            'month' => $month,
            'year' => $year
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('attendance', 'create');

        $employeeModel = new EmployeeModel();
        $this->view('attendance.create', [
            'title' => 'Enregistrer un pointage',
            'employees' => $employeeModel->findBy(['status' => 'actif'])
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('attendance', 'create');

        $data = [
            'employee_id' => (int)($_POST['employee_id'] ?? 0),
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'time_in' => $_POST['time_in'] ?? null,
            'time_out' => $_POST['time_out'] ?? null,
            'status' => $_POST['status'] ?? 'présent',
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        // Calculate late minutes
        if ($data['time_in'] && $data['time_in'] > '09:00:00') {
            $in = new DateTime($data['time_in']);
            $reference = new DateTime('09:00:00');
            $data['late_minutes'] = ($in->getTimestamp() - $reference->getTimestamp()) / 60;
        }

        // Calculate early leave
        if ($data['time_out'] && $data['time_out'] < '17:00:00') {
            $out = new DateTime($data['time_out']);
            $reference = new DateTime('17:00:00');
            $data['early_leave_minutes'] = ($reference->getTimestamp() - $out->getTimestamp()) / 60;
        }

        // Calculate overtime
        if ($data['time_out'] && $data['time_out'] > '17:00:00') {
            $out = new DateTime($data['time_out']);
            $reference = new DateTime('17:00:00');
            $data['overtime_minutes'] = ($out->getTimestamp() - $reference->getTimestamp()) / 60;
        }

        $model = new AttendanceModel();

        // Check if already exists
        $existing = $model->queryOne(
            "SELECT * FROM attendance WHERE employee_id = :employee_id AND date = :date",
            ['employee_id' => $data['employee_id'], 'date' => $data['date']]
        );

        if ($existing) {
            Flash::error('Un pointage existe déjà pour cet employé à cette date.');
            $this->redirect('/attendance/create');
        }

        $model->create($data);
        Flash::success('Pointage enregistré avec succès.');
        $this->redirect('/attendance');
    }

    public function clockIn($params = [])
    {
        $this->requireAuth();
        $user = Auth::user();
        $employee = $user['employee'] ?? null;

        if (!$employee) {
            $this->json(['error' => 'Employé non trouvé'], 400);
        }

        $model = new AttendanceModel();
        $today = date('Y-m-d');
        $existing = $model->queryOne(
            "SELECT * FROM attendance WHERE employee_id = :employee_id AND date = :date",
            ['employee_id' => $employee['id'], 'date' => $today]
        );

        if ($existing) {
            $model->execute(
                "UPDATE attendance SET time_in = :time_in WHERE id = :id",
                ['time_in' => date('H:i:s'), 'id' => $existing['id']]
            );
        } else {
            $model->create([
                'employee_id' => $employee['id'],
                'date' => $today,
                'time_in' => date('H:i:s'),
                'status' => 'présent'
            ]);
        }

        $this->json(['success' => true, 'time' => date('H:i:s')]);
    }

    public function clockOut($params = [])
    {
        $this->requireAuth();
        $user = Auth::user();
        $employee = $user['employee'] ?? null;

        if (!$employee) {
            $this->json(['error' => 'Employé non trouvé'], 400);
        }

        $model = new AttendanceModel();
        $today = date('Y-m-d');
        $existing = $model->queryOne(
            "SELECT * FROM attendance WHERE employee_id = :employee_id AND date = :date",
            ['employee_id' => $employee['id'], 'date' => $today]
        );

        if ($existing) {
            $timeOut = date('H:i:s');
            $overtime = 0;
            if ($timeOut > '17:00:00') {
                $out = new DateTime($timeOut);
                $ref = new DateTime('17:00:00');
                $overtime = ($out->getTimestamp() - $ref->getTimestamp()) / 60;
            }
            $model->execute(
                "UPDATE attendance SET time_out = :time_out, overtime_minutes = :overtime WHERE id = :id",
                ['time_out' => $timeOut, 'overtime' => $overtime, 'id' => $existing['id']]
            );
        }

        $this->json(['success' => true, 'time' => date('H:i:s')]);
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('attendance', 'edit');

        $model = new AttendanceModel();
        $record = $model->queryOne(
            "SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.id = :id",
            ['id' => $params['id']]
        );

        if (!$record) {
            Flash::error('Enregistrement non trouvé.');
            $this->redirect('/attendance');
        }

        $this->view('attendance.edit', [
            'title' => 'Modifier le pointage',
            'record' => $record
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('attendance', 'edit');

        $data = [
            'time_in' => $_POST['time_in'] ?? null,
            'time_out' => $_POST['time_out'] ?? null,
            'status' => $_POST['status'] ?? 'présent',
            'notes' => trim($_POST['notes'] ?? ''),
            'late_minutes' => (int)($_POST['late_minutes'] ?? 0),
            'early_leave_minutes' => (int)($_POST['early_leave_minutes'] ?? 0),
            'overtime_minutes' => (int)($_POST['overtime_minutes'] ?? 0),
        ];

        $model = new AttendanceModel();
        $model->update($params['id'], $data);
        Flash::success('Pointage mis à jour avec succès.');
        $this->redirect('/attendance');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('attendance', 'delete');

        $model = new AttendanceModel();
        $model->delete($params['id']);
        Flash::success('Pointage supprimé avec succès.');
        $this->redirect('/attendance');
    }
}