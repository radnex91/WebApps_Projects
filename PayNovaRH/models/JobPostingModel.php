<?php

class JobPostingModel extends Model
{
    protected $table = 'job_postings';
    protected $primaryKey = 'id';
    protected $fillable = ['title', 'department_id', 'position_id', 'description', 'requirements', 'salary_range', 'location', 'type', 'status', 'deadline'];

    public function getWithDepartment()
    {
        $sql = "SELECT jp.*, d.name AS department_name, p.title AS position_name
                FROM job_postings jp
                LEFT JOIN departments d ON jp.department_id = d.id
                LEFT JOIN positions p ON jp.position_id = p.id
                ORDER BY jp.deadline DESC";
        return $this->query($sql);
    }
}