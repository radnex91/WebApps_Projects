<?php

class ContractModel extends Model
{
    protected $table = 'contracts';
    protected $primaryKey = 'id';
    protected $fillable = ['employee_id', 'type', 'start_date', 'end_date', 'salary', 'renewal_date', 'description', 'is_active'];

    public function getWithEmployee()
    {
        $sql = "SELECT c.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
                FROM contracts c
                LEFT JOIN employees e ON c.employee_id = e.id
                ORDER BY c.start_date DESC";
        return $this->query($sql);
    }

    public function getActiveByEmployee($employeeId)
    {
        $sql = "SELECT * FROM contracts
                WHERE employee_id = :employee_id AND is_active = 1
                ORDER BY start_date DESC";
        return $this->query($sql, ['employee_id' => $employeeId]);
    }
}