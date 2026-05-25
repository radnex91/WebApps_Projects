<?php
class RoomController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $model = new Room();
        $rooms = $model->allWithType();
        $roomTypes = (new RoomType())->all('', [], 'nom');
        $this->render('hotel/rooms/index', ['rooms' => $rooms, 'roomTypes' => $roomTypes]);
    }

    public function create(): void
    {
        $roomTypes = (new RoomType())->all('', [], 'nom');
        $this->render('hotel/rooms/create', ['roomTypes' => $roomTypes]);
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['numero', 'room_type_id']);
        if (!empty($errors)) {
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Numéro et type de chambre obligatoires.');
            $this->redirectBack();
            return;
        }

        $model = new Room();
        $model->create([
            'numero'       => $_POST['numero'],
            'room_type_id' => $_POST['room_type_id'],
            'etage'        => $_POST['etage'] ?? 0,
            'statut'       => $_POST['statut'] ?? 'disponible',
            'description'  => $_POST['description'] ?? '',
        ]);

        Session::setFlash('success', 'Chambre créée.');
        $this->redirect('/hotel/rooms');
    }

    public function edit(int $id): void
    {
        $model = new Room();
        $room = $model->find($id);
        if (!$room) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/rooms'); return; }
        $roomTypes = (new RoomType())->all('', [], 'nom');
        $this->render('hotel/rooms/edit', ['room' => $room, 'roomTypes' => $roomTypes]);
    }

    public function update(int $id): void
    {
        $model = new Room();
        $model->update($id, [
            'numero'       => $_POST['numero'],
            'room_type_id' => $_POST['room_type_id'],
            'etage'        => $_POST['etage'] ?? 0,
            'statut'       => $_POST['statut'],
            'description'  => $_POST['description'] ?? '',
        ]);
        Session::setFlash('success', 'Chambre mise à jour.');
        $this->redirect('/hotel/rooms');
    }

    public function delete(int $id): void
    {
        (new Room())->delete($id);
        Session::setFlash('success', 'Chambre supprimée.');
        $this->redirect('/hotel/rooms');
    }

    public function show(int $id): void
    {
        $model = new Room();
        $room = $model->findWithType($id);
        if (!$room) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/rooms'); return; }
        $this->render('hotel/rooms/show', ['room' => $room]);
    }
}
