<?php
/**
 * EvaluationController - Gestion des évaluations
 */
class EvaluationController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('evaluations', 'view');

        $model = new EvaluationModel();
        $evaluations = $model->query(
            "SELECT ev.*, ep.name as period_name, e.first_name, e.last_name, e.photo,
                    u.username as evaluator_name
             FROM evaluations ev
             JOIN evaluation_periods ep ON ev.evaluation_period_id = ep.id
             JOIN employees e ON ev.employee_id = e.id
             JOIN users u ON ev.evaluator_id = u.id
             ORDER BY ev.created_at DESC"
        );

        $periodModel = new EvaluationPeriodModel();
        $periods = $periodModel->all('start_date DESC');

        $this->view('evaluations.index', [
            'title' => 'Évaluations',
            'evaluations' => $evaluations,
            'periods' => $periods
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('evaluations', 'create');

        $employeeModel = new EmployeeModel();
        $periodModel = new EvaluationPeriodModel();
        $criteriaModel = new EvaluationCriteriaModel();

        $this->view('evaluations.create', [
            'title' => 'Nouvelle évaluation',
            'employees' => $employeeModel->findBy(['status' => 'actif']),
            'periods' => $periodModel->all('start_date DESC'),
            'criteria' => $criteriaModel->all()
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('evaluations', 'create');

        $data = [
            'evaluation_period_id' => (int)($_POST['evaluation_period_id'] ?? 0),
            'employee_id' => (int)($_POST['employee_id'] ?? 0),
            'evaluator_id' => Auth::id(),
            'status' => 'brouillon',
            'comments' => trim($_POST['comments'] ?? ''),
            'recommendations' => trim($_POST['recommendations'] ?? ''),
        ];

        $model = new EvaluationModel();
        $evalId = $model->create($data);

        // Save criteria scores if provided
        if (isset($_POST['criteria']) && is_array($_POST['criteria'])) {
            $resultModel = new class extends Model {
                protected $table = 'evaluation_results';
                protected $fillable = ['evaluation_id', 'criteria_id', 'score', 'comment'];
                protected $primaryKey = 'id';
                protected $timestamps = true;
            };
            foreach ($_POST['criteria'] as $criteriaId => $score) {
                $resultModel->create([
                    'evaluation_id' => $evalId,
                    'criteria_id' => $criteriaId,
                    'score' => (int)$score,
                    'comment' => trim($_POST['criteria_comments'][$criteriaId] ?? '')
                ]);
            }

            // Calculate overall score
            $criteriaModel = new EvaluationCriteriaModel();
            $allCriteria = $criteriaModel->all();
            $totalWeightedScore = 0;
            $totalWeight = 0;
            foreach ($_POST['criteria'] as $criteriaId => $score) {
                $criterion = null;
                foreach ($allCriteria as $c) {
                    if ($c['id'] == $criteriaId) { $criterion = $c; break; }
                }
                if ($criterion) {
                    $totalWeightedScore += $score * $criterion['weight'];
                    $totalWeight += $criterion['weight'];
                }
            }
            $overallScore = $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 2) : 0;
            $model->update($evalId, ['overall_score' => $overallScore]);
        }

        Flash::success('Évaluation créée avec succès.');
        $this->redirect('/evaluations');
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('evaluations', 'view');

        $model = new EvaluationModel();
        $evaluation = $model->queryOne(
            "SELECT ev.*, ep.name as period_name, ep.start_date, ep.end_date,
                    e.first_name, e.last_name, e.photo, e.email,
                    u.username as evaluator_name
             FROM evaluations ev
             JOIN evaluation_periods ep ON ev.evaluation_period_id = ep.id
             JOIN employees e ON ev.employee_id = e.id
             JOIN users u ON ev.evaluator_id = u.id
             WHERE ev.id = :id",
            ['id' => $params['id']]
        );

        if (!$evaluation) {
            Flash::error('Évaluation non trouvée.');
            $this->redirect('/evaluations');
        }

        $results = $model->query(
            "SELECT er.*, ec.name as criteria_name, ec.category, ec.max_score, ec.weight
             FROM evaluation_results er
             JOIN evaluation_criteria ec ON er.criteria_id = ec.id
             WHERE er.evaluation_id = :eval_id",
            ['eval_id' => $params['id']]
        );

        $this->view('evaluations.show', [
            'title' => 'Détail de l\'évaluation',
            'evaluation' => $evaluation,
            'results' => $results
        ]);
    }

    public function evaluate($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('evaluations', 'edit');

        $model = new EvaluationModel();
        $evaluation = $model->find($params['id']);

        if (!$evaluation) {
            Flash::error('Évaluation non trouvée.');
            $this->redirect('/evaluations');
        }

        $criteriaModel = new EvaluationCriteriaModel();
        $results = $model->query(
            "SELECT er.*, ec.name as criteria_name, ec.category, ec.max_score, ec.weight
             FROM evaluation_results er
             JOIN evaluation_criteria ec ON er.criteria_id = ec.id
             WHERE er.evaluation_id = :eval_id",
            ['eval_id' => $params['id']]
        );

        $this->view('evaluations.evaluate', [
            'title' => 'Évaluer',
            'evaluation' => $evaluation,
            'results' => $results,
            'criteria' => $criteriaModel->all()
        ]);
    }

    public function complete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('evaluations', 'edit');

        $model = new EvaluationModel();
        $model->update($params['id'], ['status' => 'complété']);
        Flash::success('Évaluation complétée.');
        $this->redirect('/evaluations');
    }
}