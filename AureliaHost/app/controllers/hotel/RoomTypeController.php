<?php
class RoomTypeController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $model = new RoomType();
        $roomTypes = $model->all('', [], 'nom');
        $this->render('hotel/room-types/index', ['roomTypes' => $roomTypes]);
    }

    public function create(): void
    {
        $this->render('hotel/room-types/create');
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['nom', 'prix_base']);
        if (!empty($errors)) {
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Veuillez remplir les champs obligatoires.');
            $this->redirectBack();
            return;
        }

        $model = new RoomType();
        $model->create([
            'nom'         => $_POST['nom'],
            'description' => $_POST['description'] ?? '',
            'prix_base'   => $_POST['prix_base'],
            'capacite'    => $_POST['capacite'] ?? 1,
        ]);

        Session::setFlash('success', 'Type de chambre créé.');
        $this->redirect('/hotel/room-types');
    }

    public function edit(int $id): void
    {
        $model = new RoomType();
        $roomType = $model->find($id);
        if (!$roomType) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/room-types'); return; }
        $this->render('hotel/room-types/edit', ['roomType' => $roomType]);
    }

    public function update(int $id): void
    {
        $model = new RoomType();
        $model->update($id, [
            'nom'         => $_POST['nom'],
            'description' => $_POST['description'] ?? '',
            'prix_base'   => $_POST['prix_base'],
            'capacite'    => $_POST['capacite'] ?? 1,
        ]);
        Session::setFlash('success', 'Type de chambre mis à jour.');
        $this->redirect('/hotel/room-types');
    }

    public function delete(int $id): void
    {
        (new RoomType())->delete($id);
        Session::setFlash('success', 'Type de chambre supprimé.');
        $this->redirect('/hotel/room-types');
    }
}
