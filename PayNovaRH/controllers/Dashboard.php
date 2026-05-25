<?php
/**
 * DashboardController - Tableau de bord
 */
class DashboardController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();

        $employeeModel = new EmployeeModel();
        $leaveModel = new LeaveRequestModel();
        $payrollModel = new PayrollEntryModel();
        $departmentModel = new DepartmentModel();
        $attendanceModel = new AttendanceModel();
        $notificationModel = new NotificationModel();

        $data = [
            'title' => 'Tableau de bord',
            'totalEmployees' => $employeeModel->count(['status' => 'actif']),
            'pendingLeaves' => $leaveModel->count(['status' => 'en_attente']),
            'departments' => $departmentModel->count(),
            'employeesByStatus' => $employeeModel->countByStatus(),
            'employeesByDepartment' => $employeeModel->countByDepartment(),
            'recentLeaves' => $leaveModel->query(
                "SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type
                 FROM leave_requests lr
                 JOIN employees e ON lr.employee_id = e.id
                 JOIN leave_types lt ON lr.leave_type_id = lt.id
                 ORDER BY lr.created_at DESC LIMIT 5"
            ),
            'recentNotifications' => $notificationModel->getUnreadByUser(Auth::id()),
        ];

        // Payroll info
        $currentMonth = date('n');
        $currentYear = date('Y');
        $payrollPeriod = $payrollModel->queryOne(
            "SELECT * FROM payroll_periods WHERE month = :month AND year = :year",
            ['month' => $currentMonth, 'year' => $currentYear]
        );
        $data['payrollPeriod'] = $payrollPeriod;

        // Attendance today
        $todayCount = $attendanceModel->queryOne(
            "SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'présent'"
        );
        $data['presentToday'] = $todayCount['total'] ?? 0;

        // Gender stats
        $genderStats = $employeeModel->query(
            "SELECT gender, COUNT(*) as count FROM employees WHERE status = 'actif' GROUP BY gender"
        );
        $data['genderStats'] = $genderStats;

        $this->view('dashboard.index', $data);
    }
}