<?php
class AuthController extends Controller {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function showLogin() {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }
        require __DIR__ . '/../views/auth/login.php';
    }

    public function login() {
        $this->validateCsrf();

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($email, $password)) {
            $_SESSION['success'] = "Connexion réussie";
            $this->redirect('dashboard');
        } else {
            $lockoutMsg = Auth::getLockoutMessage($email);
            $_SESSION['error'] = $lockoutMsg ?? "Email ou mot de passe incorrect";
            $this->redirect('login');
        }
    }

    public function logout() {
        $this->validateCsrf();
        Auth::logout();
        $_SESSION['success'] = "Déconnexion réussie";
        $this->redirect('login');
    }

    public function index() {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }
        $this->redirect('login');
    }
}
