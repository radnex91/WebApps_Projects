<?php

class PayrollEntryModel extends Model
{
    protected $table = 'payroll_entries';
    protected $primaryKey = 'id';
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'base_salary', 'overtime_amount', 'bonus',
        'other_allowances', 'gross_salary', 'cnss_employee', 'cnss_employer',
        'amo_employee', 'amo_employer', 'cimr', 'itr', 'advance',
        'other_deductions', 'total_deductions', 'net_salary', 'status', 'notes'
    ];

    public function getWithEmployee($periodId)
    {
        $sql = "SELECT pe.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                e.bank_name, e.bank_account
                FROM payroll_entries pe
                LEFT JOIN employees e ON pe.employee_id = e.id
                WHERE pe.payroll_period_id = :period_id
                ORDER BY e.last_name, e.first_name";
        return $this->query($sql, ['period_id' => $periodId]);
    }

    public function calculateNet($entry)
    {
        $gross = (float) ($entry['gross_salary'] ?? 0);
        $overtime = (float) ($entry['overtime_amount'] ?? 0);
        $bonus = (float) ($entry['bonus'] ?? 0);
        $otherAllowances = (float) ($entry['other_allowances'] ?? 0);
        $advance = (float) ($entry['advance'] ?? 0);
        $otherDeductions = (float) ($entry['other_deductions'] ?? 0);

        $totalGross = $gross + $overtime + $bonus + $otherAllowances;

        // CNSS Employee: 4.48% on salary up to the plafond (7000 MAD)
        $cnssPlafond = 7000;
        $cnssEmployeeBase = min($totalGross, $cnssPlafond);
        $cnssEmployee = round($cnssEmployeeBase * 0.0448, 2);

        // CNSS Employer: 8.78% on salary up to the plafond
        $cnssEmployer = round($cnssEmployeeBase * 0.0878, 2);

        // AMO Employee: 2.26% on total gross (no cap)
        $amoEmployee = round($totalGross * 0.0226, 2);

        // AMO Employer: 2.26% on total gross (no cap)
        $amoEmployer = round($totalGross * 0.0226, 2);

        // CIMR (retirement) - fixed percentage, typically 6%
        $cimr = round($totalGross * 0.06, 2);

        // Taxable income: gross - CNSS employee - AMO employee - CIMR
        $taxableIncome = $totalGross - $cnssEmployee - $amoEmployee - $cimr;

        // ITR (Impot sur le Revenu) - Moroccan progressive tax brackets (2024)
        $itr = 0;
        if ($taxableIncome <= 2800) {
            $itr = 0;
        } elseif ($taxableIncome <= 5000) {
            $itr = round(($taxableIncome - 2800) * 0.12, 2);
        } elseif ($taxableIncome <= 10000) {
            $itr = round(264 + ($taxableIncome - 5000) * 0.24, 2);
        } elseif ($taxableIncome <= 20000) {
            $itr = round(1464 + ($taxableIncome - 10000) * 0.34, 2);
        } else {
            $itr = round(4864 + ($taxableIncome - 20000) * 0.38, 2);
        }

        $totalDeductions = $cnssEmployee + $amoEmployee + $cimr + $itr + $advance + $otherDeductions;
        $netSalary = round($totalGross - $totalDeductions, 2);

        return [
            'gross_salary' => $totalGross,
            'cnss_employee' => $cnssEmployee,
            'cnss_employer' => $cnssEmployer,
            'amo_employee' => $amoEmployee,
            'amo_employer' => $amoEmployer,
            'cimr' => $cimr,
            'itr' => $itr,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary
        ];
    }
}