<?php
/**
 * HotelPro Suite - PersonnelController
 */

class PersonnelController
{
    public function index(): void
    {
        if (!has_permission('all') && !has_permission('personnel_view')) {
            set_flash('danger', 'Accès refusé.');
            redirect(APP_URL . '/index.php?page=dashboard');
        }

        $users = Database::query(
            "SELECT u.*, r.nom AS role_nom
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY r.id, u.nom"
        )->fetchAll();

        $roles = Database::query("SELECT * FROM roles ORDER BY id")->fetchAll();

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/personnel/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function create(): void
    {
        if (!has_permission('all')) {
            set_flash('danger', 'Accès refusé.');
            redirect(APP_URL . '/index.php?page=dashboard');
        }

        $roles = Database::query("SELECT * FROM roles ORDER BY id")->fetchAll();
        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/personnel/form.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function store(): void
    {
        if (!has_permission('all') || $_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=personnel');
        }

        $data = sanitize($_POST);

        // Vérifie email unique
        $exists = Database::query("SELECT id FROM users WHERE email = ?", [$data['email']])->fetch();
        if ($exists) {
            set_flash('danger', 'Cet email est déjà utilisé.');
            redirect(APP_URL . '/index.php?page=personnel&action=create');
        }

        if (empty($data['password']) || strlen($data['password']) < 8) {
            set_flash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
            redirect(APP_URL . '/index.php?page=personnel&action=create');
        }

        Database::query(
            "INSERT INTO users (role_id, nom, prenom, email, telephone, password, statut)
             VALUES (?,?,?,?,?,?,?)",
            [
                (int)$data['role_id'],
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $data['telephone'] ?? null,
                hash_password($data['password']),
                'actif',
            ]
        );

        $id = (int) Database::lastInsertId();
        log_action('create', 'personnel', $id, 'user', "Utilisateur {$data['email']} créé");
        set_flash('success', "Compte de {$data['prenom']} {$data['nom']} créé.");
        redirect(APP_URL . '/index.php?page=personnel');
    }

    public function toggleStatut(): void
    {
        if (!has_permission('all') || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=personnel');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id === $_SESSION['user_id']) {
            set_flash('danger', 'Vous ne pouvez pas modifier votre propre statut.');
            redirect(APP_URL . '/index.php?page=personnel');
        }

        Database::query(
            "UPDATE users SET statut = IF(statut='actif','suspendu','actif') WHERE id = ?",
            [$id]
        );

        log_action('toggle_statut', 'personnel', $id, 'user');
        set_flash('success', 'Statut du compte modifié.');
        redirect(APP_URL . '/index.php?page=personnel');
    }
}
