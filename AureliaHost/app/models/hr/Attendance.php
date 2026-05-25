<?php
class Attendance extends Model
{
    protected string $table = 'attendance';

    public function allWithEmployee(string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        return $this->query(
            "SELECT a.*, e.nom, e.prenom, e.poste
             FROM attendance a
             JOIN employees e ON a.employee_id = e.id
             WHERE a.date = :date
             ORDER BY e.nom",
            ['date' => $date]
        );
    }

    public function getReport(string $month = null): array
    {
        $month = $month ?? date('Y-m');
        return $this->query(
            "SELECT e.id, e.nom, e.prenom, e.poste,
                    COUNT(CASE WHEN a.statut = 'present' THEN 1 END) as jours_presents,
                    COUNT(CASE WHEN a.statut = 'absent' THEN 1 END) as jours_absents,
                    COUNT(CASE WHEN a.statut = 'retard' THEN 1 END) as jours_retard,
                    COUNT(CASE WHEN a.statut = 'congé' THEN 1 END) as jours_conge
             FROM employees e
             LEFT JOIN attendance a ON e.id = a.employee_id AND DATE_FORMAT(a.date, '%Y-%m') = :m
             WHERE e.statut = 'actif'
             GROUP BY e.id
             ORDER BY e.nom",
            ['m' => $month]
        );
    }
}
