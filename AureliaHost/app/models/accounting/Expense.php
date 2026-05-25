<?php
class Expense extends Model
{
    protected string $table = 'expenses';

    public function allWithUser(): array
    {
        return $this->query(
            "SELECT e.*, u.nom as user_nom, u.prenom as user_prenom
             FROM expenses e
             LEFT JOIN users u ON e.user_id = u.id
             ORDER BY e.date_depense DESC"
        );
    }
}
