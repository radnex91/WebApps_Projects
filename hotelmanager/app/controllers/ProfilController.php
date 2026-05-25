<?php
/**
 * HotelPro Suite - ProfilController
 * Gestion du profil utilisateur et changement de mot de passe
 */

class ProfilController
{
    public function index(): void
    {
        $user = Database::query(
            "SELECT u.*, r.nom AS role_nom FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
            [$_SESSION['user_id']]
        )->fetch();

        $recent_logs = Database::query(
            "SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
            [$_SESSION['user_id']]
        )->fetchAll();

        $page_title = 'Mon profil';
        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/personnel/profil.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    public function updatePassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=profil');
        }

        $data        = sanitize($_POST);
        $current     = $_POST['current_password'] ?? '';
        $new_pass    = $_POST['new_password']     ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        $user = Database::query(
            "SELECT password FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        )->fetch();

        if (!verify_password($current, $user['password'])) {
            set_flash('danger', 'Mot de passe actuel incorrect.');
            redirect(APP_URL . '/index.php?page=profil');
        }

        if (strlen($new_pass) < 8) {
            set_flash('danger', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            redirect(APP_URL . '/index.php?page=profil');
        }

        if ($new_pass !== $confirm) {
            set_flash('danger', 'Les mots de passe ne correspondent pas.');
            redirect(APP_URL . '/index.php?page=profil');
        }

        Database::query(
            "UPDATE users SET password = ? WHERE id = ?",
            [hash_password($new_pass), $_SESSION['user_id']]
        );

        log_action('change_password', 'profil', $_SESSION['user_id'], 'user');
        set_flash('success', 'Mot de passe mis à jour avec succès.');
        redirect(APP_URL . '/index.php?page=profil');
    }

    public function updateInfo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=profil');
        }

        $data = sanitize($_POST);

        Database::query(
            "UPDATE users SET nom = ?, prenom = ?, telephone = ? WHERE id = ?",
            [$data['nom'], $data['prenom'], $data['telephone'] ?? null, $_SESSION['user_id']]
        );

        // Met à jour la session
        $_SESSION['user_nom'] = $data['prenom'] . ' ' . $data['nom'];

        log_action('update_profile', 'profil', $_SESSION['user_id'], 'user');
        set_flash('success', 'Profil mis à jour.');
        redirect(APP_URL . '/index.php?page=profil');
    }
}
