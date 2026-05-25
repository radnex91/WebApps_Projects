<?php
class AuthController extends Controller
{
    public function login(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $email    = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $errors = $this->validateRequired($_POST, ['email', 'password']);

            if (!empty($errors)) {
                Session::setFlash('_errors', $errors);
                Session::setFlash('_old_input', $_POST);
                Session::setFlash('error', 'Veuillez remplir tous les champs.');
                $this->redirect('/auth/login');
                return;
            }

            $userModel = new User();
            $user = $userModel->findByEmail($email);

            if (!$user || !$userModel->verifyPassword($password, $user['password_hash'])) {
                Session::setFlash('error', 'Email ou mot de passe incorrect.');
                Session::setFlash('_old_input', $_POST);
                $this->redirect('/auth/login');
                return;
            }

            Session::set('user_id', $user['id']);
            Session::set('user', $user);
            Session::setFlash('success', 'Bienvenue, ' . $user['prenom'] . ' !');
            $this->redirect('/');
        }

        $this->render('auth/login', [], 'auth');
    }

    public function logout(): void
    {
        Session::destroy();
        $this->redirect('/auth/login');
    }

    public function profile(): void
    {
        $this->requireAuth();
        $this->render('auth/profile', ['user' => Session::user()]);
    }

    public function users(): void
    {
        $this->requireRole('admin');
        $userModel = new User();
        $users = $userModel->all('', [], 'created_at DESC');
        $this->render('auth/users', ['users' => $users]);
    }

    public function createUser(): void
    {
        $this->requireRole('admin');
        $errors = $this->validateRequired($_POST, ['nom', 'prenom', 'email', 'password', 'role']);

        if (!empty($errors)) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'errors' => $errors]);
                return;
            }
            Session::setFlash('_errors', $errors);
            Session::setFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->redirectBack();
            return;
        }

        $userModel = new User();
        $existing = $userModel->findByEmail($_POST['email']);
        if ($existing) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'errors' => ['email' => 'Cet email est déjà utilisé.']]);
                return;
            }
            Session::setFlash('error', 'Cet email est déjà utilisé.');
            $this->redirectBack();
            return;
        }

        $userModel->create([
            'nom'           => $_POST['nom'],
            'prenom'        => $_POST['prenom'],
            'email'         => $_POST['email'],
            'telephone'     => $_POST['telephone'] ?? '',
            'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'role'          => $_POST['role'],
        ]);

        if ($this->wantsJson()) {
            $this->json(['success' => true, 'message' => 'Utilisateur créé avec succès.']);
            return;
        }
        Session::setFlash('success', 'Utilisateur créé avec succès.');
        $this->redirectBack();
    }

    public function theme(string $name): void
    {
        $this->requireAuth();
        $allowed = ['dark', 'corporate', 'minimal'];
        if (in_array($name, $allowed)) {
            Session::set('theme', $name);
        }
        $this->redirectBack();
    }

    public function toggleUser(int $id): void
    {
        $this->requireRole('admin');
        $userModel = new User();
        $user = $userModel->find($id);
        if ($user) {
            $userModel->update($id, ['statut' => $user['statut'] ? 0 : 1]);
            Session::setFlash('success', 'Statut mis à jour.');
        }
        $this->redirectBack();
    }
}
