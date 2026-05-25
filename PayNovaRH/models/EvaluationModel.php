<?php

class EvaluationModel extends Model
{
    protected $table = 'evaluations';
    protected $primaryKey = 'id';
    protected $fillable = ['evaluation_period_id', 'employee_id', 'evaluator_id', 'status', 'overall_score', 'comments', 'recommendations'];

    public function getWithDetails()
    {
        $sql = "SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                ep.name AS period_name, ep.start_date, ep.end_date,
                CONCAT(u.first_name, ' ', u.last_name) AS evaluator_name
                FROM evaluations ev
                LEFT JOIN employees e ON ev.employee_id = e.id
                LEFT JOIN evaluation_periods ep ON ev.evaluation_period_id = ep.id
                LEFT JOIN users u ON ev.evaluator_id = u.id
                ORDER BY ep.end_date DESC, e.last_name, e.first_name";
        return $this->query($sql);
    }

    public function getResults($evaluationId)
    {
        $sql = "SELECT er.*, ec.name AS criteria_name, ec.category, ec.max_score, ec.weight
                FROM evaluation_results er
                LEFT JOIN evaluation_criteria ec ON er.criteria_id = ec.id
                WHERE er.evaluation_id = :evaluation_id
                ORDER BY ec.category, ec.name";
        return $this->query($sql, ['evaluation_id' => $evaluationId]);
    }
}