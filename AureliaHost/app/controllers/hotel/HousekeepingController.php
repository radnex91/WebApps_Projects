<?php
class HousekeepingController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $model = new Housekeeping();
        $tasks = $model->allWithDetails();
        $rooms = (new Room())->allWithType();
        $users = (new User())->all("statut = 1", [], 'prenom');
        $this->render('hotel/housekeeping/index', [
            'tasks' => $tasks,
            'rooms' => $rooms,
            'users' => $users,
        ]);
    }

    public function create(): void
    {
        $rooms = (new Room())->allWithType();
        $users = (new User())->all("statut = 1", [], 'prenom');
        $this->render('hotel/housekeeping/create', ['rooms' => $rooms, 'users' => $users]);
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['room_id', 'date_nettoyage']);
        if (!empty($errors)) {
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Chambre et date obligatoires.');
            $this->redirectBack();
            return;
        }

        (new Housekeeping())->create([
            'room_id'        => $_POST['room_id'],
            'user_id'        => $_POST['user_id'] ?: null,
            'date_nettoyage' => $_POST['date_nettoyage'],
            'statut'         => $_POST['statut'] ?? 'planifie',
            'notes'          => $_POST['notes'] ?? '',
        ]);

        Session::setFlash('success', 'Tâche d\'entretien créée.');
        $this->redirect('/hotel/housekeeping');
    }

    public function edit(int $id): void
    {
        $task = (new Housekeeping())->find($id);
        if (!$task) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/housekeeping'); return; }
        $rooms = (new Room())->allWithType();
        $users = (new User())->all("statut = 1", [], 'prenom');
        $this->render('hotel/housekeeping/edit', ['task' => $task, 'rooms' => $rooms, 'users' => $users]);
    }

    public function update(int $id): void
    {
        $model = new Housekeeping();
        $model->update($id, [
            'room_id'        => $_POST['room_id'],
            'user_id'        => $_POST['user_id'] ?: null,
            'date_nettoyage' => $_POST['date_nettoyage'],
            'statut'         => $_POST['statut'],
            'notes'          => $_POST['notes'] ?? '',
        ]);

        // Update room status
        $task = $model->find($id);
        if ($task['statut'] === 'termine') {
            (new Room())->update($task['room_id'], ['statut' => 'disponible']);
        }

        Session::setFlash('success', 'Tâche mise à jour.');
        $this->redirect('/hotel/housekeeping');
    }

    public function delete(int $id): void
    {
        (new Housekeeping())->delete($id);
        Session::setFlash('success', 'Tâche supprimée.');
        $this->redirect('/hotel/housekeeping');
    }
}
