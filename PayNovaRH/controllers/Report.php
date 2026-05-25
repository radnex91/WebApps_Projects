<?php
/**
 * ReportController - Rapports et statistiques
 */
class ReportController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');

        $employeeModel = new EmployeeModel();
        $leaveModel = new LeaveRequestModel();

        $data = [
            'title' => 'Rapports & Statistiques',
            'totalEmployees' => $employeeModel->count(['status' => 'actif']),
            'employeesByStatus' => $employeeModel->countByStatus(),
            'employeesByDepartment' => $employeeModel->countByDepartment(),
            'leaveStats' => $leaveModel->countByStatus(),
        ];

        $this->view('reports.index', $data);
    }

    public function employees($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');

        $model = new EmployeeModel();
        $employees = $model->query(
            "SELECT e.*, d.name as department_name, p.title as position_name,
                    c.type as contract_type, c.salary as contract_salary
             FROM employees e
             LEFT JOIN departments d ON e.department_id = d.id
             LEFT JOIN positions p ON e.position_id = p.id
             LEFT JOIN contracts c ON e.id = c.employee_id AND c.is_active = 1
             ORDER BY e.last_name, e.first_name"
        );

        // Stats
        $byDept = $model->query(
            "SELECT d.name, COUNT(*) as count FROM employees e JOIN departments d ON e.department_id = d.id WHERE e.status = 'actif' GROUP BY d.name"
        );
        $byGender = $model->query("SELECT gender, COUNT(*) as count FROM employees WHERE status = 'actif' GROUP BY gender");

        $this->view('reports.employees', [
            'title' => 'Rapport Employés',
            'employees' => $employees,
            'byDept' => $byDept,
            'byGender' => $byGender
        ]);
    }

    public function leaves($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');

        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

        $model = new LeaveRequestModel();
        $leaves = $model->query(
            "SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type
             FROM leave_requests lr
             JOIN employees e ON lr.employee_id = e.id
             JOIN leave_types lt ON lr.leave_type_id = lt.id
             WHERE YEAR(lr.start_date) = :year
             ORDER BY lr.start_date DESC",
            ['year' => $year]
        );

        $byType = $model->query(
            "SELECT lt.name, COUNT(*) as count, SUM(lr.total_days) as total_days
             FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.id
             WHERE YEAR(lr.start_date) = :year AND lr.status = 'approuvé'
             GROUP BY lt.name",
            ['year' => $year]
        );

        $byStatus = $model->query(
            "SELECT status, COUNT(*) as count FROM leave_requests WHERE YEAR(created_at) = :year GROUP BY status",
            ['year' => $year]
        );

        $this->view('reports.leaves', [
            'title' => 'Rapport Congés',
            'leaves' => $leaves,
            'byType' => $byType,
            'byStatus' => $byStatus,
            'year' => $year
        ]);
    }

    public function payroll($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');

        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

        $model = new PayrollEntryModel();
        $monthly = $model->query(
            "SELECT pp.month, pp.year, pp.status,
                    SUM(pe.gross_salary) as total_gross,
                    SUM(pe.total_deductions) as total_deductions,
                    SUM(pe.net_salary) as total_net,
                    COUNT(*) as employee_count
             FROM payroll_periods pp
             JOIN payroll_entries pe ON pp.id = pe.payroll_period_id
             WHERE pp.year = :year
             GROUP BY pp.month, pp.year, pp.status
             ORDER BY pp.month",
            ['year' => $year]
        );

        $this->view('reports.payroll', [
            'title' => 'Rapport Paie',
            'monthly' => $monthly,
            'year' => $year
        ]);
    }

    public function attendance($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');

        $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

        $model = new AttendanceModel();
        $summary = $model->getSummary($month, $year);

        $byEmployee = $model->query(
            "SELECT e.first_name, e.last_name, d.name as department_name,
                    COUNT(*) as total_days,
                    SUM(CASE WHEN a.status = 'présent' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN a.status = 'retard' THEN 1 ELSE 0 END) as late_days,
                    SUM(a.late_minutes) as total_late_minutes,
                    SUM(a.overtime_minutes) as total_overtime_minutes
             FROM attendance a
             JOIN employees e ON a.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             WHERE MONTH(a.date) = :month AND YEAR(a.date) = :year
             GROUP BY a.employee_id
             ORDER BY e.last_name",
            ['month' => $month, 'year' => $year]
        );

        $this->view('reports.attendance', [
            'title' => 'Rapport Pointage',
            'summary' => $summary,
            'byEmployee' => $byEmployee,
            'month' => $month,
            'year' => $year
        ]);
    }

    public function recruitment($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');

        $appModel = new ApplicationModel();
        $byStatus = $appModel->query(
            "SELECT status, COUNT(*) as count FROM applications GROUP BY status ORDER BY count DESC"
        );

        $byJob = $appModel->query(
            "SELECT jp.title, COUNT(a.id) as application_count,
                    SUM(CASE WHEN a.status = 'embauché' THEN 1 ELSE 0 END) as hired_count
             FROM job_postings jp
             LEFT JOIN applications a ON jp.id = a.job_posting_id
             GROUP BY jp.id
             ORDER BY application_count DESC"
        );

        $this->view('reports.recruitment', [
            'title' => 'Rapport Recrutement',
            'byStatus' => $byStatus,
            'byJob' => $byJob
        ]);
    }

    public function export($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('reports', 'export');

        $type = $params['type'] ?? '';
        $model = new Model();

        switch ($type) {
            case 'employees':
                $data = $model->query(
                    "SELECT e.first_name as Prenom, e.last_name as Nom, e.email, e.phone as Telephone,
                            d.name as Departement, p.title as Poste, e.status as Statut, e.hire_date as Date_Embauche
                     FROM employees e
                     LEFT JOIN departments d ON e.department_id = d.id
                     LEFT JOIN positions p ON e.position_id = p.id
                     ORDER BY e.last_name"
                );
                $filename = 'employes_' . date('Y-m-d') . '.csv';
                break;

            case 'attendance':
                $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
                $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
                $data = $model->query(
                    "SELECT e.first_name as Prenom, e.last_name as Nom, a.date, a.time_in as Arrivee,
                            a.time_out as Depart, a.status as Statut, a.late_minutes as Retard_Min,
                            a.overtime_minutes as Heures_Supp_Min
                     FROM attendance a
                     JOIN employees e ON a.employee_id = e.id
                     WHERE MONTH(a.date) = :month AND YEAR(a.date) = :year
                     ORDER BY a.date, e.last_name",
                    ['month' => $month, 'year' => $year]
                );
                $filename = 'pointage_' . $month . '_' . $year . '.csv';
                break;

            default:
                Flash::error('Type d\'export invalide.');
                $this->redirect('/reports');
        }

        if (empty($data)) {
            Flash::error('Aucune donnée à exporter.');
            $this->redirect('/reports');
        }

        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel
        fputcsv($output, array_keys($data[0]), ';');
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        fclose($output);
        exit;
    }
}