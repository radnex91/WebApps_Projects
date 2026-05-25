<?php
class ReservationController extends Controller
{
    public function __construct()
    {
        $this->requireAuth();
    }

    public function index(): void
    {
        $model = new Reservation();
        $reservations = $model->allWithDetails();
        $this->render('hotel/reservations/index', ['reservations' => $reservations]);
    }

    public function calendar(): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        $model = new Reservation();
        $reservations = $model->getForCalendar($month);
        $this->render('hotel/reservations/calendar', [
            'reservations' => $reservations,
            'month' => $month,
        ]);
    }

    public function create(): void
    {
        // JSON-only: return form data for modal
        if ($this->wantsJson()) {
            $clients = (new Client())->all('', [], 'nom, prenom');
            $rooms = (new Room())->getDisponibles();
            $services = (new Service())->all('', [], 'nom');
            $this->json(['clients' => $clients, 'rooms' => $rooms, 'services' => $services]);
        }
        $this->redirect('/hotel/reservations');
    }

    public function availablerooms(): void
    {
        $checkin = $_GET['checkin'] ?? '';
        $checkout = $_GET['checkout'] ?? '';
        $rooms = (new Room())->getDisponibles($checkin, $checkout);
        $this->json(['rooms' => $rooms]);
    }

    public function formData(int $id = 0): void
    {
        if ($id > 0) {
            $model = new Reservation();
            $reservation = $model->find($id);
            if (!$reservation) { $this->json(['error' => 'Introuvable'], 404); return; }
            $rooms = $model->getRooms($id);
            $services = $model->getServices($id);
            $this->json(['reservation' => $reservation, 'rooms' => $rooms, 'services' => $services]);
        } else {
            $clients = (new Client())->all('', [], 'nom, prenom');
            $rooms = (new Room())->getDisponibles();
            $services = (new Service())->all('', [], 'nom');
            $this->json(['clients' => $clients, 'rooms' => $rooms, 'services' => $services]);
        }
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['client_id', 'date_checkin', 'date_checkout']);
        if (!empty($errors)) {
            if ($this->wantsJson()) { $this->json(['success' => false, 'errors' => $errors], 422); return; }
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Client, check-in et check-out obligatoires.');
            $this->redirectBack();
            return;
        }

        $total = 0;
        $roomIds = $_POST['room_ids'] ?? [];
        $serviceIds = $_POST['service_ids'] ?? [];
        $serviceQtys = $_POST['service_quantites'] ?? [];

        foreach ($roomIds as $rid) {
            $room = (new Room())->findWithType($rid);
            if ($room) {
                $nights = max(1, (strtotime($_POST['date_checkout']) - strtotime($_POST['date_checkin'])) / 86400);
                $total += $room['prix_base'] * $nights;
            }
        }

        foreach ($serviceIds as $i => $sid) {
            if (empty($sid)) continue;
            $service = (new Service())->find($sid);
            if ($service) {
                $qty = max(1, (int)($serviceQtys[$i] ?? 1));
                $total += $service['prix'] * $qty;
            }
        }

        $reservationModel = new Reservation();
        $reservationModel->beginTransaction();
        try {
            $reservationId = $reservationModel->create([
                'client_id'     => $_POST['client_id'],
                'user_id'       => Session::get('user_id'),
                'date_checkin'  => $_POST['date_checkin'],
                'date_checkout' => $_POST['date_checkout'],
                'statut'        => 'confirmee',
                'montant_total' => $total,
                'montant_paye'  => 0,
                'notes'         => $_POST['notes'] ?? '',
            ]);

            foreach ($roomIds as $rid) {
                $room = (new Room())->findWithType($rid);
                if ($room) {
                    $reservationModel->query(
                        "INSERT INTO reservation_rooms (reservation_id, room_id, prix_par_nuit) VALUES (:rid, :roomid, :prix)",
                        ['rid' => $reservationId, 'roomid' => $rid, 'prix' => $room['prix_base']]
                    );
                    (new Room())->update($rid, ['statut' => 'occupee']);
                }
            }

            foreach ($serviceIds as $i => $sid) {
                if (empty($sid)) continue;
                $service = (new Service())->find($sid);
                if ($service) {
                    $qty = max(1, (int)($serviceQtys[$i] ?? 1));
                    $reservationModel->query(
                        "INSERT INTO reservation_services (reservation_id, service_id, quantite, prix_unitaire, date_service) VALUES (:rid, :sid, :qty, :prix, :d)",
                        ['rid' => $reservationId, 'sid' => $sid, 'qty' => $qty, 'prix' => $service['prix'], 'd' => $_POST['date_checkin']]
                    );
                }
            }

            $reservationModel->commit();
            if ($this->wantsJson()) { $this->json(['success' => true, 'message' => 'Réservation créée.', 'id' => $reservationId]); return; }
            Session::setFlash('success', 'Réservation créée.');
        } catch (Exception $e) {
            $reservationModel->rollback();
            if ($this->wantsJson()) { $this->json(['success' => false, 'message' => $e->getMessage()], 500); return; }
            Session::setFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
        }

        $this->redirect('/hotel/reservations');
    }

    public function show(int $id): void
    {
        $model = new Reservation();
        $reservation = $model->findWithDetails($id);
        if (!$reservation) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/reservations'); return; }

        $rooms = $model->getRooms($id);
        $services = $model->getServices($id);

        $this->render('hotel/reservations/show', [
            'reservation' => $reservation,
            'rooms'       => $rooms,
            'services'    => $services,
        ]);
    }

    public function edit(int $id): void
    {
        $model = new Reservation();
        $reservation = $model->find($id);
        if (!$reservation) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/hotel/reservations'); return; }
        $this->render('hotel/reservations/edit', ['reservation' => $reservation]);
    }

    public function update(int $id): void
    {
        $model = new Reservation();
        $model->update($id, [
            'date_checkin'  => $_POST['date_checkin'],
            'date_checkout' => $_POST['date_checkout'],
            'statut'        => $_POST['statut'],
            'notes'         => $_POST['notes'] ?? '',
        ]);

        if ($_POST['statut'] === 'en_cours') {
            $rooms = $model->getRooms($id);
            foreach ($rooms as $r) {
                (new Room())->update($r['room_id'], ['statut' => 'occupee']);
            }
        } elseif (in_array($_POST['statut'], ['terminee', 'annulee'])) {
            $rooms = $model->getRooms($id);
            foreach ($rooms as $r) {
                (new Room())->update($r['room_id'], ['statut' => 'nettoyage']);
            }
        }

        if ($this->wantsJson()) { $this->json(['success' => true, 'message' => 'Réservation mise à jour.']); return; }
        Session::setFlash('success', 'Réservation mise à jour.');
        $this->redirect('/hotel/reservations');
    }

    public function delete(int $id): void
    {
        $model = new Reservation();
        // Free rooms
        $rooms = $model->getRooms($id);
        foreach ($rooms as $r) {
            (new Room())->update($r['room_id'], ['statut' => 'disponible']);
        }
        $model->delete($id);
        Session::setFlash('success', 'Réservation supprimée.');
        $this->redirect('/hotel/reservations');
    }
}
