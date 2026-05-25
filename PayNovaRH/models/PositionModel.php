<?php

class PositionModel extends Model
{
    protected $table = 'positions';
    protected $primaryKey = 'id';
    protected $fillable = ['title', 'department_id', 'description', 'salary_min', 'salary_max', 'is_active'];

    public function getWithDepartment()
    {
        $sql = "SELECT p.*, d.name AS department_name
                FROM positions p
                LEFT JOIN departments d ON p.department_id = d.id
                ORDER BY p.title";
        return $this->query($sql);
    }
}