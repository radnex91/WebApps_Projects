<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\User;
class ProfileController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::requireAuth();
    }
    public function index(): void
    {
        $user = Auth::user();
        $this->layout('main', 'profile/index', ['user' => $user]);
    }
    public function update(): void
    {
        $data = ['nom' => $this->post('name'), 'email' => $this->post('email')];
        if ($this->post('password')) {
            $data['mot_de_passe'] = password_hash($this->post('password'), PASSWORD_DEFAULT);
        }
        User::updateRecord(Auth::id(), $data);
        $_SESSION['user_name'] = $data['nom'];
        $this->setFlash('success', 'Profil mis à jour');
        $this->redirect('/gestion-support/profile');
    }
}
