<?php

class AttendanceModel extends Model
{
    protected $table = 'attendance';
    protected $primaryKey = 'id';
    protected $fillable = ['employee_id', 'date', 'time_in', 'time_out', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'status', 'notes'];

    public function getByEmployee($employeeId, $month, $year)
    {
        $sql = "SELECT * FROM attendance
                WHERE employee_id = :employee_id
                AND MONTH(date) = :month AND YEAR(date) = :year
                ORDER BY date ASC";
        return $this->query($sql, [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year
        ]);
    }

    public function getSummary($month, $year)
    {
        $sql = "SELECT a.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                COUNT(a.id) AS total_days,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_days,
                SUM(a.late_minutes) AS total_late_minutes,
                SUM(a.early_leave_minutes) AS total_early_leave_minutes,
                SUM(a.overtime_minutes) AS total_overtime_minutes
                FROM attendance a
                LEFT JOIN employees e ON a.employee_id = e.id
                WHERE MONTH(a.date) = :month AND YEAR(a.date) = :year
                GROUP BY a.employee_id, e.first_name, e.last_name
                ORDER BY e.last_name, e.first_name";
        return $this->query($sql, ['month' => $month, 'year' => $year]);
    }

    public function getTodayByEmployee($employeeId)
    {
        $sql = "SELECT * FROM attendance
                WHERE employee_id = :employee_id AND date = CURDATE()";
        return $this->queryOne($sql, ['employee_id' => $employeeId]);
    }
}