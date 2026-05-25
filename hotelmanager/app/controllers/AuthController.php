<?php
/**
 * HotelPro Suite - AuthController
 * Gestion de l'authentification (login / logout)
 */

class AuthController
{
    /**
     * Affiche et traite le formulaire de connexion
     */
    public function login(): void
    {
        // Déjà connecté → dashboard
        if (isset($_SESSION['user_id'])) {
            redirect(APP_URL . '/index.php?page=dashboard');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validation CSRF
            if (!verify_csrf()) {
                $error = 'Requête invalide. Actualisez la page et réessayez.';
            } else {
                $data  = sanitize($_POST);
                $email = $data['email']    ?? '';
                $pass  = $data['password'] ?? '';

                if (empty($email) || empty($pass)) {
                    $error = 'Veuillez remplir tous les champs.';
                } else {
                    $user = $this->findUserByEmail($email);

                    if ($user && verify_password($pass, $user['password']) && $user['statut'] === 'actif') {
                        // Régénère l'ID de session (protection fixation)
                        session_regenerate_id(true);

                        $_SESSION['user_id']          = $user['id'];
                        $_SESSION['user_nom']         = $user['prenom'] . ' ' . $user['nom'];
                        $_SESSION['user_email']       = $user['email'];
                        $_SESSION['user_role_id']     = $user['role_id'];
                        $_SESSION['user_role_nom']    = $user['role_nom'];
                        $_SESSION['user_permissions'] = json_decode($user['permissions'], true);
                        $_SESSION['last_activity']    = time();

                        // Met à jour last_login
                        Database::query(
                            "UPDATE users SET last_login = NOW() WHERE id = ?",
                            [$user['id']]
                        );

                        log_action('login', 'auth', $user['id'], 'user', "Connexion depuis {$_SERVER['REMOTE_ADDR']}");

                        redirect(APP_URL . '/index.php?page=dashboard');
                    } else {
                        $error = 'Email ou mot de passe incorrect, ou compte désactivé.';
                        // Petit délai anti-bruteforce
                        sleep(1);
                    }
                }
            }
        }

        require APP_ROOT . '/app/views/auth/login.php';
    }

    /**
     * Déconnexion
     */
    public function logout(): void
    {
        log_action('logout', 'auth', $_SESSION['user_id'] ?? null, 'user');

        session_unset();
        session_destroy();

        // Recrée une session propre pour le message flash
        session_start();
        set_flash('success', 'Vous avez été déconnecté avec succès.');

        redirect(APP_URL . '/index.php?page=login');
    }

    /**
     * Recherche un utilisateur par email avec son rôle
     */
    private function findUserByEmail(string $email): ?array
    {
        $stmt = Database::query(
            "SELECT u.*, r.nom AS role_nom, r.permissions
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1",
            [$email]
        );
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
