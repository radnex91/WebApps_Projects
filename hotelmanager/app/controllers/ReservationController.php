<?php
/**
 * HotelPro Suite - ReservationController
 * CRUD complet des réservations + check-in/check-out
 */

class ReservationController
{
    // ── Liste ────────────────────────────────────────────────
    public function index(): void
    {
        $filters = $_GET;
        $page_num = max(1, (int)($_GET['p'] ?? 1));
        $offset   = ($page_num - 1) * ITEMS_PER_PAGE;

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['statut'])) {
            $where[]  = 'r.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'r.date_arrivee >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'r.date_arrivee <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[]  = "(c.nom LIKE ? OR c.prenom LIKE ? OR r.reference LIKE ?)";
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s]);
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM reservations r
             JOIN clients c ON c.id = r.client_id
             WHERE $whereStr",
            $params
        )->fetchColumn();

        $reservations = Database::query(
            "SELECT r.*, CONCAT(c.prenom,' ',c.nom) AS client_nom, c.telephone,
                    ch.numero AS chambre_num, tc.nom AS type_chambre
             FROM reservations r
             JOIN clients c ON c.id = r.client_id
             JOIN chambres ch ON ch.id = r.chambre_id
             JOIN types_chambres tc ON tc.id = ch.type_id
             WHERE $whereStr
             ORDER BY r.date_arrivee DESC, r.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [ITEMS_PER_PAGE, $offset])
        )->fetchAll();

        $total_pages = ceil($total / ITEMS_PER_PAGE);

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/reservations/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    // ── Formulaire création ──────────────────────────────────
    public function create(): void
    {
        $clients  = Database::query("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, telephone FROM clients ORDER BY nom")->fetchAll();
        $chambres = Database::query(
            "SELECT c.id, c.numero, c.statut, tc.nom AS type_nom, tc.tarif_nuit, tc.capacite
             FROM chambres c JOIN types_chambres tc ON tc.id = c.type_id
             WHERE c.statut = 'disponible'
             ORDER BY CAST(c.numero AS UNSIGNED)"
        )->fetchAll();

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/reservations/create.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    // ── Enregistrement ───────────────────────────────────────
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=reservations&action=create');
        }

        $data = sanitize($_POST);

        // Validations
        $errors = $this->validate($data);
        if ($errors) {
            set_flash('danger', implode('<br>', $errors));
            redirect(APP_URL . '/index.php?page=reservations&action=create');
        }

        // Calcul du nombre de nuits et montant
        $d_arrivee = new DateTime($data['date_arrivee']);
        $d_depart  = new DateTime($data['date_depart']);
        $nb_nuits  = $d_arrivee->diff($d_depart)->days;

        // Vérifie disponibilité de la chambre sur la période
        if (!$this->isChambreAvailable((int)$data['chambre_id'], $data['date_arrivee'], $data['date_depart'])) {
            set_flash('danger', 'Cette chambre est déjà réservée pour les dates sélectionnées.');
            redirect(APP_URL . '/index.php?page=reservations&action=create');
        }

        // Récupère le tarif de la chambre
        $chambre = Database::query(
            "SELECT c.*, tc.tarif_nuit FROM chambres c JOIN types_chambres tc ON tc.id = c.type_id WHERE c.id = ?",
            [(int)$data['chambre_id']]
        )->fetch();

        $tarif_nuit  = (float)$chambre['tarif_nuit'];
        $montant_ttc = $tarif_nuit * $nb_nuits;
        $reference   = generate_reference('RES', 'reservations');

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            Database::query(
                "INSERT INTO reservations
                 (reference, client_id, chambre_id, user_id, date_arrivee, date_depart, nb_nuits,
                  nb_adultes, nb_enfants, tarif_nuit, montant_total, statut, source, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $reference,
                    (int)$data['client_id'],
                    (int)$data['chambre_id'],
                    $_SESSION['user_id'],
                    $data['date_arrivee'],
                    $data['date_depart'],
                    $nb_nuits,
                    (int)($data['nb_adultes'] ?? 1),
                    (int)($data['nb_enfants'] ?? 0),
                    $tarif_nuit,
                    $montant_ttc,
                    'confirmee',
                    $data['source'] ?? 'direct',
                    $data['notes'] ?? null,
                ]
            );
            $res_id = (int) Database::lastInsertId();

            $pdo->commit();

            log_action('create', 'reservations', $res_id, 'reservation', "Réservation $reference créée");
            set_flash('success', "Réservation <strong>$reference</strong> créée avec succès !");
            redirect(APP_URL . '/index.php?page=reservations');

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            set_flash('danger', 'Erreur lors de la création de la réservation.');
            redirect(APP_URL . '/index.php?page=reservations&action=create');
        }
    }

    // ── Détail ───────────────────────────────────────────────
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $reservation = $this->getReservationById($id);

        if (!$reservation) {
            set_flash('danger', 'Réservation introuvable.');
            redirect(APP_URL . '/index.php?page=reservations');
        }

        $factures = Database::query(
            "SELECT * FROM factures WHERE reservation_id = ? ORDER BY created_at DESC",
            [$id]
        )->fetchAll();

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/reservations/show.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    // ── Check-in ─────────────────────────────────────────────
    public function checkin(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $res = Database::query("SELECT * FROM reservations WHERE id = ?", [$id])->fetch();

        if (!$res || !in_array($res['statut'], ['confirmee', 'en_attente'])) {
            set_flash('danger', 'Check-in impossible pour cette réservation.');
            redirect(APP_URL . '/index.php?page=reservations');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            Database::query("UPDATE reservations SET statut = 'checkin', checkin_at = NOW() WHERE id = ?", [$id]);
            Database::query("UPDATE chambres SET statut = 'occupee' WHERE id = ?", [$res['chambre_id']]);
            $pdo->commit();

            log_action('checkin', 'reservations', $id, 'reservation');
            set_flash('success', 'Check-in effectué avec succès.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Erreur lors du check-in.');
        }

        redirect(APP_URL . '/index.php?page=reservations&action=show&id=' . $id);
    }

    // ── Check-out ────────────────────────────────────────────
    public function checkout(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $res = Database::query("SELECT * FROM reservations WHERE id = ?", [$id])->fetch();

        if (!$res || $res['statut'] !== 'checkin') {
            set_flash('danger', 'Check-out impossible pour cette réservation.');
            redirect(APP_URL . '/index.php?page=reservations');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            Database::query("UPDATE reservations SET statut = 'checkout', checkout_at = NOW() WHERE id = ?", [$id]);
            Database::query("UPDATE chambres SET statut = 'nettoyage' WHERE id = ?", [$res['chambre_id']]);
            $pdo->commit();

            log_action('checkout', 'reservations', $id, 'reservation');
            set_flash('success', 'Check-out effectué. Chambre mise en nettoyage.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Erreur lors du check-out.');
        }

        redirect(APP_URL . '/index.php?page=reservations&action=show&id=' . $id);
    }

    // ── Annulation ───────────────────────────────────────────
    public function cancel(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $res = Database::query("SELECT * FROM reservations WHERE id = ?", [$id])->fetch();

        if (!$res || in_array($res['statut'], ['checkout', 'annulee'])) {
            set_flash('danger', 'Annulation impossible.');
            redirect(APP_URL . '/index.php?page=reservations');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            Database::query("UPDATE reservations SET statut = 'annulee' WHERE id = ?", [$id]);
            if ($res['statut'] === 'checkin') {
                Database::query("UPDATE chambres SET statut = 'disponible' WHERE id = ?", [$res['chambre_id']]);
            }
            $pdo->commit();

            log_action('cancel', 'reservations', $id, 'reservation');
            set_flash('warning', 'Réservation annulée.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Erreur lors de l\'annulation.');
        }

        redirect(APP_URL . '/index.php?page=reservations');
    }

    // ── Vérification disponibilité (AJAX) ────────────────────
    public function checkAvailability(): void
    {
        header('Content-Type: application/json');

        $chambre_id  = (int)($_GET['chambre_id']   ?? 0);
        $date_debut  = $_GET['date_arrivee'] ?? '';
        $date_fin    = $_GET['date_depart']  ?? '';
        $exclude_id  = (int)($_GET['exclude'] ?? 0);

        if (!$chambre_id || !$date_debut || !$date_fin) {
            echo json_encode(['available' => false, 'error' => 'Paramètres manquants']);
            return;
        }

        $available = $this->isChambreAvailable($chambre_id, $date_debut, $date_fin, $exclude_id);
        echo json_encode(['available' => $available]);
    }

    // ── Helpers privés ───────────────────────────────────────

    private function isChambreAvailable(int $chambre_id, string $date_debut, string $date_fin, int $exclude_id = 0): bool
    {
        $stmt = Database::query(
            "SELECT COUNT(*) FROM reservations
             WHERE chambre_id = ?
               AND id != ?
               AND statut NOT IN ('annulee','no_show','checkout')
               AND date_arrivee < ?
               AND date_depart  > ?",
            [$chambre_id, $exclude_id, $date_fin, $date_debut]
        );
        return (int)$stmt->fetchColumn() === 0;
    }

    private function getReservationById(int $id): ?array
    {
        $res = Database::query(
            "SELECT r.*, CONCAT(c.prenom,' ',c.nom) AS client_nom, c.telephone, c.email AS client_email,
                    ch.numero AS chambre_num, tc.nom AS type_chambre, tc.tarif_nuit,
                    CONCAT(u.prenom,' ',u.nom) AS agent_nom
             FROM reservations r
             JOIN clients c ON c.id = r.client_id
             JOIN chambres ch ON ch.id = r.chambre_id
             JOIN types_chambres tc ON tc.id = ch.type_id
             JOIN users u ON u.id = r.user_id
             WHERE r.id = ?",
            [$id]
        )->fetch();
        return $res ?: null;
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['client_id']))    $errors[] = 'Client obligatoire.';
        if (empty($data['chambre_id']))   $errors[] = 'Chambre obligatoire.';
        if (empty($data['date_arrivee'])) $errors[] = 'Date d\'arrivée obligatoire.';
        if (empty($data['date_depart']))  $errors[] = 'Date de départ obligatoire.';

        if (!empty($data['date_arrivee']) && !empty($data['date_depart'])) {
            if ($data['date_depart'] <= $data['date_arrivee']) {
                $errors[] = 'La date de départ doit être postérieure à la date d\'arrivée.';
            }
            if ($data['date_arrivee'] < date('Y-m-d')) {
                $errors[] = 'La date d\'arrivée ne peut pas être dans le passé.';
            }
        }
        return $errors;
    }
}
