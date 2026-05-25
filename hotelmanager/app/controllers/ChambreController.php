<?php
/**
 * HotelPro Suite - ChambreController
 */

class ChambreController
{
    public function index(): void
    {
        $statut = $_GET['statut'] ?? '';
        $type   = $_GET['type']   ?? '';

        $where  = ['1=1'];
        $params = [];

        if ($statut) { $where[] = 'c.statut = ?'; $params[] = $statut; }
        if ($type)   { $where[] = 'c.type_id = ?'; $params[] = (int)$type; }

        $whereStr = implode(' AND ', $where);

        $chambres = Database::query(
            "SELECT c.*, tc.nom AS type_nom, tc.tarif_nuit, tc.capacite,
                    (SELECT CONCAT(cl.prenom,' ',cl.nom)
                     FROM reservations r JOIN clients cl ON cl.id = r.client_id
                     WHERE r.chambre_id = c.id AND r.statut = 'checkin'
                     LIMIT 1) AS client_actuel
             FROM chambres c
             JOIN types_chambres tc ON tc.id = c.type_id
             WHERE $whereStr
             ORDER BY CAST(c.numero AS UNSIGNED)",
            $params
        )->fetchAll();

        $types = Database::query("SELECT * FROM types_chambres ORDER BY tarif_nuit")->fetchAll();

        // Stats rapides
        $stats_statuts = Database::query(
            "SELECT statut, COUNT(*) AS n FROM chambres GROUP BY statut"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/chambres/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function create(): void
    {
        $types = Database::query("SELECT * FROM types_chambres ORDER BY nom")->fetchAll();
        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/chambres/form.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=chambres&action=create');
        }

        $data = sanitize($_POST);

        // Vérifie que le numéro n'existe pas
        $exists = Database::query("SELECT id FROM chambres WHERE numero = ?", [$data['numero']])->fetch();
        if ($exists) {
            set_flash('danger', "Le numéro de chambre {$data['numero']} existe déjà.");
            redirect(APP_URL . '/index.php?page=chambres&action=create');
        }

        Database::query(
            "INSERT INTO chambres (type_id, numero, etage, statut, description, notes_internes)
             VALUES (?,?,?,?,?,?)",
            [
                (int)$data['type_id'],
                $data['numero'],
                (int)($data['etage'] ?? 0),
                $data['statut'] ?? 'disponible',
                $data['description'] ?? null,
                $data['notes_internes'] ?? null,
            ]
        );

        $id = (int) Database::lastInsertId();
        log_action('create', 'chambres', $id, 'chambre', "Chambre {$data['numero']} créée");
        set_flash('success', "Chambre {$data['numero']} créée.");
        redirect(APP_URL . '/index.php?page=chambres');
    }

    public function updateStatut(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=chambres');
        }

        $id     = (int)($_POST['id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $valid  = ['disponible', 'occupee', 'nettoyage', 'maintenance'];

        if (!in_array($statut, $valid)) {
            set_flash('danger', 'Statut invalide.');
            redirect(APP_URL . '/index.php?page=chambres');
        }

        Database::query("UPDATE chambres SET statut = ? WHERE id = ?", [$statut, $id]);
        log_action('update_statut', 'chambres', $id, 'chambre', "Statut → $statut");
        set_flash('success', 'Statut de la chambre mis à jour.');
        redirect(APP_URL . '/index.php?page=chambres');
    }
}
