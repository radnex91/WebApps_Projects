<?php
class ServiceController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $model = new Service();
        $services = $model->all('', [], 'categorie, nom');
        $this->render('hotel/services/index', ['services' => $services]);
    }

    public function create(): void
    {
        $this->render('hotel/services/create');
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['nom', 'prix']);
        if (!empty($errors)) {
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Nom et prix obligatoires.');
            $this->redirectBack();
            return;
        }

        (new Service())->create([
            'nom'         => $_POST['nom'],
            'description' => $_POST['description'] ?? '',
            'prix'        => $_POST['prix'],
            'categorie'   => $_POST['categorie'] ?? 'autre',
        ]);

        Session::setFlash('success', 'Service créé.');
        $this->redirect('/hotel/services');
    }

    public function edit(int $id): void
    {
        $service = (new Service())->find($id);
        if (!$service) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/services'); return; }
        $this->render('hotel/services/edit', ['service' => $service]);
    }

    public function update(int $id): void
    {
        (new Service())->update($id, [
            'nom'         => $_POST['nom'],
            'description' => $_POST['description'] ?? '',
            'prix'        => $_POST['prix'],
            'categorie'   => $_POST['categorie'],
        ]);
        Session::setFlash('success', 'Service mis à jour.');
        $this->redirect('/hotel/services');
    }

    public function delete(int $id): void
    {
        (new Service())->delete($id);
        Session::setFlash('success', 'Service supprimé.');
        $this->redirect('/hotel/services');
    }
}
