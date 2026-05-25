<?php
/**
 * AuthController - Authentification et profil
 */
class AuthController extends Controller
{
    public function login($params = [])
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->layout = null;
        $this->view('auth.login', ['title' => 'Connexion']);
    }

    public function loginPost($params = [])
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            Flash::error('Veuillez remplir tous les champs.');
            $this->redirect('/login');
        }

        if (Auth::attempt($username, $password)) {
            Flash::success('Bienvenue !');
            $this->redirect('/dashboard');
        }

        Flash::error('Identifiants incorrects.');
        $this->redirect('/login');
    }

    public function logout($params = [])
    {
        Auth::logout();
        Flash::success('Vous avez été déconnecté.');
        $this->redirect('/login');
    }

    public function profile($params = [])
    {
        $this->requireAuth();
        $user = Auth::user();
        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->findOneBy(['user_id' => $user['id']]);

        $this->view('profile.index', [
            'title' => 'Mon Profil',
            'user' => $user,
            'employee' => $employee
        ]);
    }

    public function profilePost($params = [])
    {
        $this->requireAuth();
        $user = Auth::user();

        $data = [
            'email' => trim($_POST['email'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('email')->email('email');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            $this->redirect('/profile');
        }

        $userModel = new UserModel();
        $userModel->update($user['id'], $data);
        Flash::success('Profil mis à jour avec succès.');
        $this->redirect('/profile');
    }

    public function changePassword($params = [])
    {
        $this->requireAuth();
        $user = Auth::user();

        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            Flash::error('Mot de passe actuel incorrect.');
            $this->redirect('/profile');
        }

        if (strlen($new) < 6) {
            Flash::error('Le nouveau mot de passe doit contenir au moins 6 caractères.');
            $this->redirect('/profile');
        }

        if ($new !== $confirm) {
            Flash::error('Les mots de passe ne correspondent pas.');
            $this->redirect('/profile');
        }

        $userModel = new UserModel();
        $userModel->update($user['id'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
        Flash::success('Mot de passe modifié avec succès.');
        $this->redirect('/profile');
    }
}