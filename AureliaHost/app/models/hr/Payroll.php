<?php
class Payroll extends Model
{
    protected string $table = 'payrolls';

    public function allWithEmployee(): array
    {
        return $this->query(
            "SELECT p.*, e.nom, e.prenom, e.poste, d.nom as department_nom
             FROM payrolls p
             JOIN employees e ON p.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             ORDER BY p.mois DESC, e.nom"
        );
    }

    public function findWithEmployee(int $id): ?array
    {
        return $this->queryOne(
            "SELECT p.*, e.nom, e.prenom, e.poste, e.salaire_base as emp_salaire_base,
                    e.date_embauche, e.type_contrat, d.nom as department_nom
             FROM payrolls p
             JOIN employees e ON p.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             WHERE p.id = :id",
            ['id' => $id]
        );
    }
}
