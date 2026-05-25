<?php
/**
 * LeaveController - Gestion des congés
 */
class LeaveController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'view');

        $model = new LeaveRequestModel();
        $user = Auth::user();

        if (Auth::isAdmin() || Auth::isManager()) {
            $leaves = $model->query(
                "SELECT lr.*, e.first_name, e.last_name, e.photo, lt.name as leave_type, lt.color,
                        u.username as approver_name
                 FROM leave_requests lr
                 JOIN employees e ON lr.employee_id = e.id
                 JOIN leave_types lt ON lr.leave_type_id = lt.id
                 LEFT JOIN users u ON lr.approved_by = u.id
                 ORDER BY lr.created_at DESC"
            );
        } else {
            $employee = $user['employee'] ?? null;
            if ($employee) {
                $leaves = $model->query(
                    "SELECT lr.*, lt.name as leave_type, lt.color,
                            u.username as approver_name
                     FROM leave_requests lr
                     JOIN leave_types lt ON lr.leave_type_id = lt.id
                     LEFT JOIN users u ON lr.approved_by = u.id
                     WHERE lr.employee_id = :employee_id
                     ORDER BY lr.created_at DESC",
                    ['employee_id' => $employee['id']]
                );
            } else {
                $leaves = [];
            }
        }

        $this->view('leaves.index', [
            'title' => 'Congés',
            'leaves' => $leaves
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'create');

        $typeModel = new LeaveTypeModel();
        $employeeModel = new EmployeeModel();
        $user = Auth::user();

        $this->view('leaves.create', [
            'title' => 'Nouvelle demande de congé',
            'leaveTypes' => $typeModel->all(),
            'employees' => Auth::isAdmin() ? $employeeModel->findBy(['status' => 'actif']) : [],
            'isEmployee' => !Auth::isAdmin() && !Auth::isManager()
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'create');

        $user = Auth::user();
        $employee = $user['employee'] ?? null;

        $employeeId = Auth::isAdmin() ? (int)($_POST['employee_id'] ?? 0) : ($employee ? $employee['id'] : 0);
        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';

        if (!$employeeId || !$leaveTypeId || !$startDate || !$endDate) {
            Flash::error('Veuillez remplir tous les champs requis.');
            $this->redirect('/leaves/create');
        }

        $totalDays = calculateBusinessDays($startDate, $endDate);

        $data = [
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => trim($_POST['reason'] ?? ''),
            'status' => 'en_attente',
        ];

        $model = new LeaveRequestModel();
        $model->create($data);

        // Create notification for managers
        $notificationModel = new NotificationModel();
        $managers = (new UserModel())->getByRoleId(2); // managers
        foreach ($managers as $manager) {
            $notificationModel->create([
                'user_id' => $manager['id'],
                'title' => 'Nouvelle demande de congé',
                'message' => "Une demande de congé de {$totalDays} jour(s) a été soumise.",
                'type' => 'info',
                'link' => '/leaves'
            ]);
        }

        Flash::success('Demande de congé soumise avec succès.');
        $this->redirect('/leaves');
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'view');

        $model = new LeaveRequestModel();
        $leave = $model->queryOne(
            "SELECT lr.*, e.first_name, e.last_name, e.photo, e.email,
                    lt.name as leave_type, lt.days_allowed, lt.is_paid,
                    u.username as approver_name
             FROM leave_requests lr
             JOIN employees e ON lr.employee_id = e.id
             JOIN leave_types lt ON lr.leave_type_id = lt.id
             LEFT JOIN users u ON lr.approved_by = u.id
             WHERE lr.id = :id",
            ['id' => $params['id']]
        );

        if (!$leave) {
            Flash::error('Demande non trouvée.');
            $this->redirect('/leaves');
        }

        $balanceModel = new LeaveBalanceModel();
        $balance = $balanceModel->queryOne(
            "SELECT * FROM leave_balances
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND year = :year",
            ['employee_id' => $leave['employee_id'], 'leave_type_id' => $leave['leave_type_id'], 'year' => date('Y')]
        );

        $this->view('leaves.show', [
            'title' => 'Détail du congé',
            'leave' => $leave,
            'balance' => $balance
        ]);
    }

    public function approve($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'approve');

        $model = new LeaveRequestModel();
        $leave = $model->find($params['id']);

        if (!$leave) {
            Flash::error('Demande non trouvée.');
            $this->redirect('/leaves');
        }

        $model->update($params['id'], [
            'status' => 'approuvé',
            'approved_by' => Auth::id(),
            'approved_at' => date('Y-m-d H:i:s')
        ]);

        // Update leave balance
        $balanceModel = new LeaveBalanceModel();
        $balanceModel->updateUsedDays($leave['employee_id'], $leave['leave_type_id'], date('Y'), $leave['total_days']);

        Flash::success('Congé approuvé avec succès.');
        $this->redirect('/leaves');
    }

    public function reject($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'approve');

        $model = new LeaveRequestModel();
        $model->update($params['id'], [
            'status' => 'refusé',
            'approved_by' => Auth::id(),
            'approved_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => trim($_POST['rejection_reason'] ?? '')
        ]);

        Flash::success('Congé refusé.');
        $this->redirect('/leaves');
    }

    public function cancel($params = [])
    {
        $this->requireAuth();

        $model = new LeaveRequestModel();
        $leave = $model->find($params['id']);

        if ($leave && ($leave['status'] === 'en_attente' || Auth::isAdmin())) {
            $model->update($params['id'], ['status' => 'annulé']);
            Flash::success('Congé annulé.');
        }

        $this->redirect('/leaves');
    }

    public function balances($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'view');

        $balanceModel = new LeaveBalanceModel();
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

        $balances = $balanceModel->query(
            "SELECT lb.*, e.first_name, e.last_name, lt.name as leave_type
             FROM leave_balances lb
             JOIN employees e ON lb.employee_id = e.id
             JOIN leave_types lt ON lb.leave_type_id = lt.id
             WHERE lb.year = :year AND e.status = 'actif'
             ORDER BY e.last_name, lt.name",
            ['year' => $year]
        );

        $this->view('leaves.balances', [
            'title' => 'Soldes de congés',
            'balances' => $balances,
            'year' => $year
        ]);
    }

    public function calendar($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('leaves', 'view');

        $model = new LeaveRequestModel();
        $leaves = $model->query(
            "SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type, lt.color
             FROM leave_requests lr
             JOIN employees e ON lr.employee_id = e.id
             JOIN leave_types lt ON lr.leave_type_id = lt.id
             WHERE lr.status = 'approuvé'
             ORDER BY lr.start_date DESC"
        );

        $this->view('leaves.calendar', [
            'title' => 'Calendrier des congés',
            'leaves' => $leaves
        ]);
    }
}