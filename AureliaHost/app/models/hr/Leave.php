<?php
class Leave extends Model
{
    protected string $table = 'leaves';

    public function allWithDetails(): array
    {
        return $this->query(
            "SELECT l.*, e.nom, e.prenom, e.poste,
                    u.nom as approver_nom, u.prenom as approver_prenom
             FROM `leaves` l
             JOIN employees e ON l.employee_id = e.id
             LEFT JOIN users u ON l.approuve_par = u.id
             ORDER BY l.created_at DESC"
        );
    }

    public function findWithDetails(int $id): ?array
    {
        return $this->queryOne(
            "SELECT l.*, e.nom, e.prenom, e.poste, d.nom as department_nom
             FROM `leaves` l
             JOIN employees e ON l.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             WHERE l.id = :id",
            ['id' => $id]
        );
    }
}
