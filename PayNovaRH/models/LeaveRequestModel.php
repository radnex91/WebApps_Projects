<?php

class LeaveRequestModel extends Model
{
    protected $table = 'leave_requests';
    protected $primaryKey = 'id';
    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date', 'total_days',
        'reason', 'status', 'approved_by', 'approved_at', 'rejection_reason'
    ];

    public function getWithDetails()
    {
        $sql = "SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                lt.name AS leave_type_name, CONCAT(u.first_name, ' ', u.last_name) AS approver_name
                FROM leave_requests lr
                LEFT JOIN employees e ON lr.employee_id = e.id
                LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users u ON lr.approved_by = u.id
                ORDER BY lr.start_date DESC";
        return $this->query($sql);
    }

    public function getByEmployee($employeeId)
    {
        $sql = "SELECT lr.*, lt.name AS leave_type_name
                FROM leave_requests lr
                LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.employee_id = :employee_id
                ORDER BY lr.start_date DESC";
        return $this->query($sql, ['employee_id' => $employeeId]);
    }

    public function countByStatus()
    {
        $sql = "SELECT status, COUNT(*) AS count FROM leave_requests GROUP BY status";
        $results = $this->query($sql);
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }
}