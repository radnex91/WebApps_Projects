<?php
/**
 * RecruitmentController - Gestion du recrutement
 */
class RecruitmentController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'view');

        $model = new JobPostingModel();
        $jobs = $model->query(
            "SELECT jp.*, d.name as department_name, p.title as position_title,
                    (SELECT COUNT(*) FROM applications WHERE job_posting_id = jp.id) as application_count
             FROM job_postings jp
             LEFT JOIN departments d ON jp.department_id = d.id
             LEFT JOIN positions p ON jp.position_id = p.id
             ORDER BY jp.created_at DESC"
        );

        $this->view('recruitment.index', [
            'title' => 'Recrutement',
            'jobs' => $jobs
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'create');

        $deptModel = new DepartmentModel();
        $posModel = new PositionModel();

        $this->view('recruitment.create', [
            'title' => 'Nouvelle offre d\'emploi',
            'departments' => $deptModel->all('name ASC'),
            'positions' => $posModel->all('title ASC')
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'create');

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'department_id' => (int)($_POST['department_id'] ?? 0) ?: null,
            'position_id' => (int)($_POST['position_id'] ?? 0) ?: null,
            'description' => $_POST['description'] ?? '',
            'requirements' => $_POST['requirements'] ?? '',
            'salary_range' => trim($_POST['salary_range'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'type' => $_POST['type'] ?? 'CDI',
            'status' => $_POST['status'] ?? 'brouillon',
            'deadline' => $_POST['deadline'] ?? null,
        ];

        if ($data['deadline'] === '') $data['deadline'] = null;

        $validator = new Validator($data);
        $validator->required('title')->required('description');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/recruitment/create');
        }

        $model = new JobPostingModel();
        $model->create($data);
        Flash::success('Offre d\'emploi créée avec succès.');
        $this->redirect('/recruitment');
    }

    public function show($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'view');

        $model = new JobPostingModel();
        $job = $model->queryOne(
            "SELECT jp.*, d.name as department_name, p.title as position_title
             FROM job_postings jp
             LEFT JOIN departments d ON jp.department_id = d.id
             LEFT JOIN positions p ON jp.position_id = p.id
             WHERE jp.id = :id",
            ['id' => $params['id']]
        );

        if (!$job) {
            Flash::error('Offre non trouvée.');
            $this->redirect('/recruitment');
        }

        $appModel = new ApplicationModel();
        $applications = $appModel->findBy(['job_posting_id' => $params['id']]);

        $this->view('recruitment.show', [
            'title' => $job['title'],
            'job' => $job,
            'applications' => $applications
        ]);
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'edit');

        $model = new JobPostingModel();
        $job = $model->find($params['id']);

        if (!$job) {
            Flash::error('Offre non trouvée.');
            $this->redirect('/recruitment');
        }

        $deptModel = new DepartmentModel();
        $posModel = new PositionModel();

        $this->view('recruitment.edit', [
            'title' => 'Modifier l\'offre',
            'job' => $job,
            'departments' => $deptModel->all('name ASC'),
            'positions' => $posModel->all('title ASC')
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'edit');

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'department_id' => (int)($_POST['department_id'] ?? 0) ?: null,
            'position_id' => (int)($_POST['position_id'] ?? 0) ?: null,
            'description' => $_POST['description'] ?? '',
            'requirements' => $_POST['requirements'] ?? '',
            'salary_range' => trim($_POST['salary_range'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'type' => $_POST['type'] ?? 'CDI',
            'status' => $_POST['status'] ?? 'brouillon',
            'deadline' => $_POST['deadline'] ?? null,
        ];

        if ($data['deadline'] === '') $data['deadline'] = null;

        $model = new JobPostingModel();
        $model->update($params['id'], $data);
        Flash::success('Offre mise à jour avec succès.');
        $this->redirect('/recruitment');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'delete');

        $model = new JobPostingModel();
        $model->delete($params['id']);
        Flash::success('Offre supprimée avec succès.');
        $this->redirect('/recruitment');
    }

    public function apply($params = [])
    {
        $data = [
            'job_posting_id' => (int)($_POST['job_posting_id'] ?? 0),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'cover_letter' => trim($_POST['cover_letter'] ?? ''),
            'status' => 'nouveau',
        ];

        // Handle resume upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['resume'], 'resumes', ALLOWED_DOC_TYPES);
            if (isset($result['success'])) {
                $data['resume'] = $result['success'];
            }
        }

        $model = new ApplicationModel();
        $model->create($data);
        Flash::success('Candidature soumise avec succès.');
        $this->redirect('/recruitment/' . $data['job_posting_id']);
    }

    public function updateApplicationStatus($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('recruitment', 'edit');

        $status = $_POST['status'] ?? '';
        $interviewNotes = trim($_POST['interview_notes'] ?? '');

        $model = new ApplicationModel();
        $updateData = ['status' => $status];

        if ($interviewNotes) $updateData['interview_notes'] = $interviewNotes;
        if ($status === 'entretien') $updateData['interview_date'] = $_POST['interview_date'] ?? null;
        if (isset($_POST['score'])) $updateData['score'] = (int)$_POST['score'];

        // If hired, create employee
        if ($status === 'embauché') {
            $app = $model->find($params['id']);
            if ($app) {
                $employeeModel = new EmployeeModel();
                $employeeModel->create([
                    'first_name' => $app['first_name'],
                    'last_name' => $app['last_name'],
                    'email' => $app['email'],
                    'phone' => $app['phone'],
                    'hire_date' => date('Y-m-d'),
                    'status' => 'actif',
                ]);
                $newEmployee = $employeeModel->findOneBy(['email' => $app['email']]);
                $updateData['employee_id'] = $newEmployee['id'];
            }
        }

        $model->update($params['id'], $updateData);
        Flash::success('Statut de la candidature mis à jour.');
        $this->redirect('/recruitment/' . ($_POST['job_posting_id'] ?? 0));
    }
}