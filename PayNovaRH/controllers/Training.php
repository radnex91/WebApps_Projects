<?php
/**
 * TrainingController - Gestion des formations
 */
class TrainingController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'view');

        $model = new TrainingProgramModel();
        $programs = $model->query(
            "SELECT tp.*,
                    (SELECT COUNT(*) FROM training_enrollments WHERE training_program_id = tp.id) as enrolled_count
             FROM training_programs tp
             ORDER BY tp.start_date DESC"
        );

        $this->view('training.index', [
            'title' => 'Formations',
            'programs' => $programs
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'create');

        $this->view('training.create', [
            'title' => 'Nouvelle formation'
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'create');

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'trainer' => trim($_POST['trainer'] ?? ''),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
            'location' => trim($_POST['location'] ?? ''),
            'capacity' => (int)($_POST['capacity'] ?? 0),
            'budget' => (float)($_POST['budget'] ?? 0),
            'status' => $_POST['status'] ?? 'planifié',
        ];

        if ($data['start_date'] === '') $data['start_date'] = null;
        if ($data['end_date'] === '') $data['end_date'] = null;

        $validator = new Validator($data);
        $validator->required('title');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/training/create');
        }

        $model = new TrainingProgramModel();
        $model->create($data);
        Flash::success('Formation créée avec succès.');
        $this->redirect('/training');
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'view');

        $model = new TrainingProgramModel();
        $program = $model->find($params['id']);

        if (!$program) {
            Flash::error('Formation non trouvée.');
            $this->redirect('/training');
        }

        $enrollmentModel = new TrainingEnrollmentModel();
        $enrollments = $enrollmentModel->query(
            "SELECT te.*, e.first_name, e.last_name, e.photo, e.email
             FROM training_enrollments te
             JOIN employees e ON te.employee_id = e.id
             WHERE te.training_program_id = :program_id
             ORDER BY e.last_name",
            ['program_id' => $params['id']]
        );

        $employeeModel = new EmployeeModel();

        $this->view('training.show', [
            'title' => $program['title'],
            'program' => $program,
            'enrollments' => $enrollments,
            'employees' => $employeeModel->findBy(['status' => 'actif'])
        ]);
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'edit');

        $model = new TrainingProgramModel();
        $program = $model->find($params['id']);

        if (!$program) {
            Flash::error('Formation non trouvée.');
            $this->redirect('/training');
        }

        $this->view('training.edit', [
            'title' => 'Modifier la formation',
            'program' => $program
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'edit');

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'trainer' => trim($_POST['trainer'] ?? ''),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
            'location' => trim($_POST['location'] ?? ''),
            'capacity' => (int)($_POST['capacity'] ?? 0),
            'budget' => (float)($_POST['budget'] ?? 0),
            'status' => $_POST['status'] ?? 'planifié',
        ];

        if ($data['start_date'] === '') $data['start_date'] = null;
        if ($data['end_date'] === '') $data['end_date'] = null;

        $model = new TrainingProgramModel();
        $model->update($params['id'], $data);
        Flash::success('Formation mise à jour avec succès.');
        $this->redirect('/training');
    }

    public function enroll($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'edit');

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $programId = $params['id'];

        $enrollmentModel = new TrainingEnrollmentModel();

        // Check if already enrolled
        $existing = $enrollmentModel->queryOne(
            "SELECT * FROM training_enrollments WHERE training_program_id = :program_id AND employee_id = :employee_id",
            ['program_id' => $programId, 'employee_id' => $employeeId]
        );

        if ($existing) {
            Flash::error('Cet employé est déjà inscrit à cette formation.');
            $this->redirect('/training/' . $programId);
        }

        $enrollmentModel->create([
            'training_program_id' => $programId,
            'employee_id' => $employeeId,
            'status' => 'inscrit'
        ]);

        Flash::success('Employé inscrit avec succès.');
        $this->redirect('/training/' . $programId);
    }

    public function completeEnrollment($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'edit');

        $enrollmentModel = new TrainingEnrollmentModel();
        $score = isset($_POST['score']) ? (float)$_POST['score'] : null;
        $feedback = trim($_POST['feedback'] ?? '');

        $enrollmentModel->execute(
            "UPDATE training_enrollments SET status = 'terminé', score = :score, feedback = :feedback WHERE id = :id",
            ['score' => $score, 'feedback' => $feedback, 'id' => $params['id']]
        );

        Flash::success('Inscription marquée comme terminée.');
        $this->redirect('/training/' . ($_POST['program_id'] ?? ''));
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('training', 'delete');

        $model = new TrainingProgramModel();
        $model->delete($params['id']);
        Flash::success('Formation supprimée avec succès.');
        $this->redirect('/training');
    }
}