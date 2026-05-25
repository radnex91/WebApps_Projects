<?php
/**
 * HotelPro Suite - ClientController
 * CRM clients complet
 */

class ClientController
{
    public function index(): void
    {
        $page_num = max(1, (int)($_GET['p'] ?? 1));
        $offset   = ($page_num - 1) * ITEMS_PER_PAGE;
        $search   = trim($_GET['search'] ?? '');
        $type     = $_GET['type'] ?? '';

        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[]  = "(nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR email LIKE ? OR reference LIKE ?)";
            $s = '%' . $search . '%';
            array_push($params, $s, $s, $s, $s, $s);
        }
        if ($type) {
            $where[]  = "type_client = ?";
            $params[] = $type;
        }

        $whereStr = implode(' AND ', $where);
        $total    = (int) Database::query("SELECT COUNT(*) FROM clients WHERE $whereStr", $params)->fetchColumn();

        $clients = Database::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM reservations r WHERE r.client_id = c.id) AS nb_sejours,
                    (SELECT MAX(date_arrivee) FROM reservations r WHERE r.client_id = c.id) AS dernier_sejour
             FROM clients c
             WHERE $whereStr
             ORDER BY c.nom, c.prenom
             LIMIT ? OFFSET ?",
            array_merge($params, [ITEMS_PER_PAGE, $offset])
        )->fetchAll();

        $total_pages = ceil($total / ITEMS_PER_PAGE);

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/clients/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function create(): void
    {
        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/clients/form.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=clients&action=create');
        }

        $data   = sanitize($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            set_flash('danger', implode('<br>', $errors));
            redirect(APP_URL . '/index.php?page=clients&action=create');
        }

        $reference = generate_reference('CLT', 'clients');

        Database::query(
            "INSERT INTO clients (reference, nom, prenom, email, telephone, nationalite, type_piece, numero_piece,
             date_naissance, adresse, ville, pays, type_client, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $reference,
                $data['nom'], $data['prenom'],
                $data['email'] ?? null,
                $data['telephone'],
                $data['nationalite'] ?? null,
                $data['type_piece'] ?? 'CNI',
                $data['numero_piece'] ?? null,
                $data['date_naissance'] ?: null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['pays'] ?? 'Cameroun',
                $data['type_client'] ?? 'standard',
                $data['notes'] ?? null,
            ]
        );

        $id = (int) Database::lastInsertId();
        log_action('create', 'clients', $id, 'client', "Client $reference créé");
        set_flash('success', "Client <strong>{$data['prenom']} {$data['nom']}</strong> créé avec succès.");
        redirect(APP_URL . '/index.php?page=clients&action=show&id=' . $id);
    }

    public function show(): void
    {
        $id     = (int)($_GET['id'] ?? 0);
        $client = Database::query("SELECT * FROM clients WHERE id = ?", [$id])->fetch();

        if (!$client) {
            set_flash('danger', 'Client introuvable.');
            redirect(APP_URL . '/index.php?page=clients');
        }

        $sejours = Database::query(
            "SELECT r.*, ch.numero AS chambre_num, tc.nom AS type_chambre
             FROM reservations r
             JOIN chambres ch ON ch.id = r.chambre_id
             JOIN types_chambres tc ON tc.id = ch.type_id
             WHERE r.client_id = ?
             ORDER BY r.date_arrivee DESC",
            [$id]
        )->fetchAll();

        $total_depenses = (float) Database::query(
            "SELECT COALESCE(SUM(f.total_ttc),0) FROM factures f
             JOIN reservations r ON r.id = f.reservation_id
             WHERE r.client_id = ? AND f.statut = 'payee'",
            [$id]
        )->fetchColumn();

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/clients/show.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function edit(): void
    {
        $id     = (int)($_GET['id'] ?? 0);
        $client = Database::query("SELECT * FROM clients WHERE id = ?", [$id])->fetch();

        if (!$client) {
            set_flash('danger', 'Client introuvable.');
            redirect(APP_URL . '/index.php?page=clients');
        }

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/clients/form.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=clients');
        }

        $id   = (int)($_POST['id'] ?? 0);
        $data = sanitize($_POST);

        Database::query(
            "UPDATE clients SET nom=?, prenom=?, email=?, telephone=?, nationalite=?,
             type_piece=?, numero_piece=?, date_naissance=?, adresse=?, ville=?, pays=?,
             type_client=?, notes=?
             WHERE id=?",
            [
                $data['nom'], $data['prenom'],
                $data['email'] ?? null,
                $data['telephone'],
                $data['nationalite'] ?? null,
                $data['type_piece'] ?? 'CNI',
                $data['numero_piece'] ?? null,
                $data['date_naissance'] ?: null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['pays'] ?? 'Cameroun',
                $data['type_client'] ?? 'standard',
                $data['notes'] ?? null,
                $id,
            ]
        );

        log_action('update', 'clients', $id, 'client');
        set_flash('success', 'Client mis à jour.');
        redirect(APP_URL . '/index.php?page=clients&action=show&id=' . $id);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['nom']))       $errors[] = 'Nom obligatoire.';
        if (empty($data['prenom']))    $errors[] = 'Prénom obligatoire.';
        if (empty($data['telephone'])) $errors[] = 'Téléphone obligatoire.';
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        return $errors;
    }
}
