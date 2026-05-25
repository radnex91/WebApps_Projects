<?php
/**
 * PayrollController - Gestion de la paie
 */
class PayrollController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'view');

        $periodModel = new PayrollPeriodModel();
        $periods = $periodModel->all('year DESC, month DESC');

        $this->view('payroll.index', [
            'title' => 'Gestion de la paie',
            'periods' => $periods
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'create');

        $this->view('payroll.create', [
            'title' => 'Nouvelle période de paie'
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'create');

        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);

        if (!$month || !$year) {
            Flash::error('Veuillez sélectionner un mois et une année.');
            $this->redirect('/payroll/create');
        }

        $periodModel = new PayrollPeriodModel();
        $existing = $periodModel->findOneBy(['month' => $month, 'year' => $year]);
        if ($existing) {
            Flash::error('Une période de paie existe déjà pour ce mois.');
            $this->redirect('/payroll/create');
        }

        $periodId = $periodModel->create([
            'month' => $month,
            'year' => $year,
            'status' => 'brouillon',
            'payment_date' => $_POST['payment_date'] ?? null
        ]);

        // Generate payroll entries for all active employees
        $employeeModel = new EmployeeModel();
        $contractModel = new ContractModel();
        $employees = $employeeModel->findBy(['status' => 'actif']);

        $entryModel = new PayrollEntryModel();
        foreach ($employees as $emp) {
            $contract = $contractModel->findOneBy(['employee_id' => $emp['id'], 'is_active' => 1]);
            $baseSalary = $contract ? $contract['salary'] : 0;

            if ($baseSalary > 0) {
                $entryModel->create([
                    'payroll_period_id' => $periodId,
                    'employee_id' => $emp['id'],
                    'base_salary' => $baseSalary,
                    'gross_salary' => $baseSalary,
                    'net_salary' => $baseSalary,
                    'total_deductions' => 0,
                    'status' => 'brouillon'
                ]);
            }
        }

        Flash::success('Période de paie créée avec succès.');
        $this->redirect('/payroll/' . $periodId);
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'view');

        $periodModel = new PayrollPeriodModel();
        $period = $periodModel->find($params['id']);

        if (!$period) {
            Flash::error('Période non trouvée.');
            $this->redirect('/payroll');
        }

        $entryModel = new PayrollEntryModel();
        $entries = $entryModel->query(
            "SELECT pe.*, e.first_name, e.last_name, e.photo, d.name as department_name, p.title as position_name
             FROM payroll_entries pe
             JOIN employees e ON pe.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             LEFT JOIN positions p ON e.position_id = p.id
             WHERE pe.payroll_period_id = :period_id
             ORDER BY e.last_name, e.first_name",
            ['period_id' => $params['id']]
        );

        // Calculate totals
        $totals = [
            'gross' => array_sum(array_column($entries, 'gross_salary')),
            'deductions' => array_sum(array_column($entries, 'total_deductions')),
            'net' => array_sum(array_column($entries, 'net_salary')),
        ];

        $this->view('payroll.show', [
            'title' => getMonthName($period['month']) . ' ' . $period['year'],
            'period' => $period,
            'entries' => $entries,
            'totals' => $totals
        ]);
    }

    public function calculate($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'edit');

        $entryModel = new PayrollEntryModel();
        $entry = $entryModel->find($params['id']);

        if (!$entry) {
            Flash::error('Entrée non trouvée.');
            $this->redirect('/payroll');
        }

        $baseSalary = (float)($_POST['base_salary'] ?? $entry['base_salary']);
        $overtimeAmount = (float)($_POST['overtime_amount'] ?? 0);
        $bonus = (float)($_POST['bonus'] ?? 0);
        $otherAllowances = (float)($_POST['other_allowances'] ?? 0);
        $advance = (float)($_POST['advance'] ?? 0);
        $otherDeductions = (float)($_POST['other_deductions'] ?? 0);

        $grossSalary = $baseSalary + $overtimeAmount + $bonus + $otherAllowances;

        // Moroccan payroll calculations
        $cnssEmployee = min($grossSalary * 0.0448, 7000 * 0.0448);
        $amoEmployee = $grossSalary * 0.0226;
        $cimr = $grossSalary * 0.06;

        $taxableIncome = $grossSalary - $cnssEmployee - $amoEmployee - $cimr;

        // ITR progressive brackets
        $itr = 0;
        if ($taxableIncome <= 28000 / 12) $itr = 0;
        elseif ($taxableIncome <= 50000 / 12) $itr = ($taxableIncome - 28000 / 12) * 0.12;
        elseif ($taxableIncome <= 84000 / 12) $itr = ($taxableIncome - 50000 / 12) * 0.24 + (22000 / 12) * 0.12;
        elseif ($taxableIncome <= 120000 / 12) $itr = ($taxableIncome - 84000 / 12) * 0.34 + (34000 / 12) * 0.24 + (22000 / 12) * 0.12;
        else $itr = ($taxableIncome - 120000 / 12) * 0.38 + (36000 / 12) * 0.34 + (34000 / 12) * 0.24 + (22000 / 12) * 0.12;

        $cnssEmployer = min($grossSalary * 0.0878, 7000 * 0.0878);
        $amoEmployer = $grossSalary * 0.0411;

        $totalDeductions = $cnssEmployee + $amoEmployee + $cimr + $itr + $advance + $otherDeductions;
        $netSalary = $grossSalary - $totalDeductions;

        $entryModel->update($params['id'], [
            'base_salary' => $baseSalary,
            'overtime_amount' => $overtimeAmount,
            'bonus' => $bonus,
            'other_allowances' => $otherAllowances,
            'gross_salary' => $grossSalary,
            'cnss_employee' => $cnssEmployee,
            'cnss_employer' => $cnssEmployer,
            'amo_employee' => $amoEmployee,
            'amo_employer' => $amoEmployer,
            'cimr' => $cimr,
            'itr' => $itr,
            'advance' => $advance,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
        ]);

        Flash::success('Paie calculée avec succès.');
        $this->redirect('/payroll/' . $entry['payroll_period_id']);
    }

    public function validate($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'edit');

        $entryModel = new PayrollEntryModel();
        $entryModel->update($params['id'], ['status' => 'validé']);
        Flash::success('Entrée de paie validée.');
        $this->redirect('/payroll');
    }

    public function close($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'edit');

        $periodModel = new PayrollPeriodModel();
        $periodModel->update($params['id'], ['status' => 'clôturé']);

        // Mark all entries as paid
        $entryModel = new PayrollEntryModel();
        $entries = $entryModel->findBy(['payroll_period_id' => $params['id']]);
        foreach ($entries as $entry) {
            $entryModel->update($entry['id'], ['status' => 'payé']);
        }

        Flash::success('Période de paie clôturée avec succès.');
        $this->redirect('/payroll');
    }

    public function payslip($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('payroll', 'view');

        $entryModel = new PayrollEntryModel();
        $entry = $entryModel->queryOne(
            "SELECT pe.*, e.*, d.name as department_name, p.title as position_name,
                    pp.month, pp.year, pp.payment_date
             FROM payroll_entries pe
             JOIN employees e ON pe.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             LEFT JOIN positions p ON e.position_id = p.id
             JOIN payroll_periods pp ON pe.payroll_period_id = pp.id
             WHERE pe.id = :id",
            ['id' => $params['id'] ?? 0]
        );

        if (!$entry) {
            Flash::error('Fiche de paie non trouvée.');
            $this->redirect('/payroll');
        }

        $this->layout = null;
        $this->view('payroll.payslip', [
            'entry' => $entry
        ]);
    }
}