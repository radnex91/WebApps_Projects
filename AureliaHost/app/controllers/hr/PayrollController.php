<?php
class PayrollController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $model = new Payroll();
        $payrolls = $model->allWithEmployee();
        $this->render('hr/payroll/index', ['payrolls' => $payrolls]);
    }

    public function create(): void
    {
        $employees = (new Employee())->all("statut = 'actif'", [], 'nom, prenom');
        $this->render('hr/payroll/create', ['employees' => $employees, 'defaultMonth' => date('Y-m')]);
    }

    public function store(): void
    {
        $employeeIds = $_POST['employee_ids'] ?? [];
        $month = $_POST['mois'] ?? date('Y-m-01');

        if (empty($employeeIds)) {
            Session::setFlash('error', 'Sélectionnez au moins un employé.');
            $this->redirectBack();
            return;
        }

        $payrollModel = new Payroll();
        $count = 0;

        foreach ($employeeIds as $eid) {
            $employee = (new Employee())->find($eid);
            if (!$employee) continue;

            $exists = $payrollModel->queryOne(
                "SELECT id FROM payrolls WHERE employee_id = :eid AND DATE_FORMAT(mois, '%Y-%m') = DATE_FORMAT(:m, '%Y-%m')",
                ['eid' => $eid, 'm' => $month]
            );
            if ($exists) continue;

            $salaireNet = $employee['salaire_base'];
            $payrollModel->create([
                'employee_id' => $eid,
                'mois'        => $month,
                'salaire_base' => $employee['salaire_base'],
                'primes'       => 0,
                'deductions'   => 0,
                'salaire_net'  => $salaireNet,
                'statut'       => 'brouillon',
            ]);
            $count++;
        }

        Session::setFlash('success', "$count bulletin(s) généré(s).");
        $this->redirect('/hr/payroll');
    }

    public function edit(int $id): void
    {
        $payroll = (new Payroll())->findWithEmployee($id);
        if (!$payroll) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hr/payroll'); return; }
        $this->render('hr/payroll/edit', ['payroll' => $payroll]);
    }

    public function update(int $id): void
    {
        $base   = (float)($_POST['salaire_base'] ?? 0);
        $primes = (float)($_POST['primes'] ?? 0);
        $deduct = (float)($_POST['deductions'] ?? 0);
        $net    = $base + $primes - $deduct;

        (new Payroll())->update($id, [
            'salaire_base' => $base,
            'primes'       => $primes,
            'deductions'   => $deduct,
            'salaire_net'  => $net,
            'statut'       => $_POST['statut'] ?? 'brouillon',
        ]);

        Session::setFlash('success', 'Bulletin mis à jour.');
        $this->redirect('/hr/payroll');
    }

    public function delete(int $id): void
    {
        (new Payroll())->delete($id);
        Session::setFlash('success', 'Bulletin supprimé.');
        $this->redirect('/hr/payroll');
    }

    public function payslip(int $id): void
    {
        $payroll = (new Payroll())->findWithEmployee($id);
        if (!$payroll) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hr/payroll'); return; }
        $this->render('hr/payroll/payslip', ['payroll' => $payroll], 'main');
    }
}
