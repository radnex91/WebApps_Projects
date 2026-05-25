<?php
class Department extends Model
{
    protected string $table = 'departments';

    public function employeeCount(): array
    {
        return $this->query(
            "SELECT d.*, COUNT(e.id) as nb_employes
             FROM departments d
             LEFT JOIN employees e ON d.id = e.department_id AND e.statut = 'actif'
             GROUP BY d.id ORDER BY d.nom"
        );
    }
}
