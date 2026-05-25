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
        if (Auth::login($email, $password)) {
            $this->redirect('/gestion-support/' . Auth::role() . '/dashboard');
        }
        $this->setFlash('danger', 'Email ou mot de passe incorrect');
        $this->redirect('/gestion-support/login');
    }
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/gestion-support/login');
    }
}
