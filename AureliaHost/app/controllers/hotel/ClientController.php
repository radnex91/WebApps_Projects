<?php
class ClientController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $model = new Client();
        $search = $_GET['search'] ?? '';
        $clients = $search ? $model->search($search) : $model->all('', [], 'nom, prenom');
        $this->render('hotel/clients/index', ['clients' => $clients, 'search' => $search]);
    }

    public function create(): void
    {
        $this->render('hotel/clients/create');
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['nom', 'prenom']);
        if (!empty($errors)) {
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Nom et prénom obligatoires.');
            $this->redirectBack();
            return;
        }

        $model = new Client();
        $model->create([
            'nom'            => $_POST['nom'],
            'prenom'         => $_POST['prenom'],
            'email'          => $_POST['email'] ?? '',
            'telephone'      => $_POST['telephone'] ?? '',
            'adresse'        => $_POST['adresse'] ?? '',
            'ville'          => $_POST['ville'] ?? '',
            'pays'           => $_POST['pays'] ?? 'Maroc',
            'document_type'  => $_POST['document_type'] ?? 'cin',
            'document_numero' => $_POST['document_numero'] ?? '',
            'date_naissance' => $_POST['date_naissance'] ?: null,
        ]);

        Session::setFlash('success', 'Client créé.');
        $this->redirect('/hotel/clients');
    }

    public function edit(int $id): void
    {
        $model = new Client();
        $client = $model->find($id);
        if (!$client) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/clients'); return; }
        $this->render('hotel/clients/edit', ['client' => $client]);
    }

    public function update(int $id): void
    {
        $model = new Client();
        $model->update($id, [
            'nom'            => $_POST['nom'],
            'prenom'         => $_POST['prenom'],
            'email'          => $_POST['email'] ?? '',
            'telephone'      => $_POST['telephone'] ?? '',
            'adresse'        => $_POST['adresse'] ?? '',
            'ville'          => $_POST['ville'] ?? '',
            'pays'           => $_POST['pays'] ?? 'Maroc',
            'document_type'  => $_POST['document_type'] ?? 'cin',
            'document_numero' => $_POST['document_numero'] ?? '',
            'date_naissance' => $_POST['date_naissance'] ?: null,
        ]);
        Session::setFlash('success', 'Client mis à jour.');
        $this->redirect('/hotel/clients');
    }

    public function delete(int $id): void
    {
        (new Client())->delete($id);
        Session::setFlash('success', 'Client supprimé.');
        $this->redirect('/hotel/clients');
    }

    public function show(int $id): void
    {
        $client = (new Client())->find($id);
        if (!$client) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/clients'); return; }

        $reservations = (new Reservation())->query(
            "SELECT r.* FROM reservations r WHERE r.client_id = :cid ORDER BY r.date_checkin DESC LIMIT 20",
            ['cid' => $id]
        );

        $this->render('hotel/clients/show', ['client' => $client, 'reservations' => $reservations]);
    }
}
