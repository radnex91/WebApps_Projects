<?php

class LeaveBalanceModel extends Model
{
    protected $table = 'leave_balances';
    protected $primaryKey = 'id';
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'total_days', 'used_days', 'remaining_days'];

    public function getByEmployee($employeeId, $year)
    {
        $sql = "SELECT lb.*, lt.name AS leave_type_name
                FROM leave_balances lb
                LEFT JOIN leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.employee_id = :employee_id AND lb.year = :year";
        return $this->query($sql, ['employee_id' => $employeeId, 'year' => $year]);
    }

    public function updateUsedDays($employeeId, $leaveTypeId, $year, $days)
    {
        $sql = "UPDATE leave_balances
                SET used_days = used_days + :days, remaining_days = remaining_days - :days
                WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND year = :year";
        return $this->execute($sql, [
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'year' => $year,
            'days' => $days
        ]);
    }
}