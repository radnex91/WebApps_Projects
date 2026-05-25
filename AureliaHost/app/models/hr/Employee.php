<?php
class Employee extends Model
{
    protected string $table = 'employees';

    public function allWithDepartment(): array
    {
        return $this->query(
            "SELECT e.*, d.nom as department_nom
             FROM employees e
             LEFT JOIN departments d ON e.department_id = d.id
             ORDER BY e.nom, e.prenom"
        );
    }

    public function findWithDepartment(int $id): ?array
    {
        return $this->queryOne(
            "SELECT e.*, d.nom as department_nom
             FROM employees e
             LEFT JOIN departments d ON e.department_id = d.id
             WHERE e.id = :id",
            ['id' => $id]
        );
    }
}
