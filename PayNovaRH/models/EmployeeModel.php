<?php

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country',
        'birth_date', 'gender', 'marital_status', 'children_count', 'cin', 'cnss', 'photo',
        'department_id', 'position_id', 'hire_date', 'status', 'emergency_contact_name',
        'emergency_contact_phone', 'bank_name', 'bank_account'
    ];

    public function getAllWithDetails()
    {
        $sql = "SELECT e.*, d.name AS department_name, p.title AS position_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                ORDER BY e.last_name, e.first_name";
        return $this->query($sql);
    }

    public function getWithDetails($id)
    {
        $sql = "SELECT e.*, d.name AS department_name, p.title AS position_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.id = :id";
        return $this->queryOne($sql, ['id' => $id]);
    }

    public function countByStatus()
    {
        $sql = "SELECT status, COUNT(*) AS count FROM employees GROUP BY status";
        $results = $this->query($sql);
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }

    public function countByDepartment()
    {
        $sql = "SELECT d.name AS department, COUNT(e.id) AS count
                FROM departments d
                LEFT JOIN employees e ON e.department_id = d.id
                GROUP BY d.id, d.name";
        $results = $this->query($sql);
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['department']] = (int) $row['count'];
        }
        return $counts;
    }
}