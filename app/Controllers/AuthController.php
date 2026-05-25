<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Helpers\Auth;
class AuthController extends Controller
{
    public function showLoginForm(): void
    {
        if (Auth::check()) {
            $this->redirect('/gestion-support/' . Auth::role() . '/dashboard');
        }
        $this->render('auth/login');
    }
    public function login(): void
    {
        $email = $this->post('email');
        $password = $this->post('password');

        $throttle = Auth::isThrottled($email);
        if ($throttle['throttled']) {
            $mins = ceil($throttle['retryAfter'] / 60);
            $this->setFlash('danger', "Trop de tentatives. Réessayez dans {$mins} minute(s).");
            $this->redirect('/gestion-support/login');
            return;
        }

        if (Auth::login($email, $password)) {
            $this->redirect('/gestion-support/' . Auth::role() . '/dashboard');
        }
        $remaining = $throttle['remaining'] ?? 5;
        $this->setFlash('danger', "Email ou mot de passe incorrect ({$remaining} tentative(s) restante(s)).");
        $this->redirect('/gestion-support/login');
    }
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/gestion-support/login');
    }
}
