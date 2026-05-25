<?php
namespace Controllers;

use Core\Controller;
use Models\Utilisateur;
use Models\AuditLog;

class AuthController extends Controller
{
    public function login(): void
    {
        if ($this->session->has('user_id')) {
            $this->redirect(BASE_URL);
        }

        if ($this->isPost()) {
            if (!$this->csrf->validate($this->post('_csrf_token'))) {
                $this->setFlash('error', 'Token CSRF invalide');
                $this->render('auth.login', ['csrf' => $this->csrf]);
                return;
            }

            $email = $this->post('email');
            $password = $this->post('password');

            $user = Utilisateur::findByEmail($email);
            if ($user && $user->verifyPassword($password) && $user->actif) {
                $this->session->set('user_id', $user->id);
                $this->session->set('user_nom', $user->nom);
                $this->session->set('user_email', $user->email);
                $this->session->set('user_role', $user->role);
                $this->session->regenerate();

                $user->derniere_connexion = date('Y-m-d H:i:s');
                $user->save();

                AuditLog::log($user->id, 'LOGIN', 'utilisateur', $user->id);
                $this->redirect(BASE_URL);
            } else {
                $this->setFlash('error', 'Identifiants incorrects ou compte désactivé');
            }
        }

        $this->render('auth.login', ['csrf' => $this->csrf], 'auth');
    }

    public function logout(): void
    {
        AuditLog::log($this->session->get('user_id'), 'LOGOUT', 'utilisateur', $this->session->get('user_id'));
        $this->session->destroy();
        $this->redirect(BASE_URL . '/auth/login');
    }
}