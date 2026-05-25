<?php

class DepartmentModel extends Model
{
    protected $table = 'departments';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'description', 'manager_id', 'parent_id', 'is_active'];

    public function getWithManager()
    {
        $sql = "SELECT d.*, CONCAT(e.first_name, ' ', e.last_name) AS manager_name
                FROM departments d
                LEFT JOIN employees e ON d.manager_id = e.id
                ORDER BY d.name";
        return $this->query($sql);
    }

    public function getEmployeeCount($departmentId)
    {
        return $this->count(['department_id' => $departmentId]);
    }
}