<?php
require_once ROOT_PATH . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/controllers/Controller.php';

class AuthController extends Controller {
    
    public function login() {
        if (isLoggedIn()) {
            redirect('/dashboard');
        }
        $this->view('auth/login');
    }
    
    public function doLogin() {
        $login = $_POST['login'] ?? '';
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        
        if (empty($login) || empty($mot_de_passe)) {
            flash('Veuillez remplir tous les champs', 'error');
            redirect('/login');
        }
        
        $user = $this->db->fetch("SELECT * FROM utilisateurs WHERE login = ?", [$login]);
        
        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['agence_id'] = $user['id_agence'];
            $_SESSION['nom_utilisateur'] = $user['nom_utilisateur'];
            
            $this->db->query("UPDATE utilisateurs SET last_login = NOW() WHERE id = ?", [$user['id']]);
            
            $this->db->query("INSERT INTO historique_connexions (session_id, user_login, login, date_connexion, heure_connexion, adresse_ip, session_statut) VALUES (?, ?, ?, NOW(), NOW(), ?, 'Active')", [
                session_id(), $user['login'], $user['login'], $_SERVER['REMOTE_ADDR']
            ]);
            
            flash('Bienvenue ' . $user['nom_utilisateur'], 'success');
            redirect('/dashboard');
        } else {
            flash('Login ou mot de passe incorrect', 'error');
            redirect('/login');
        }
    }
    
    public function logout() {
        $sessionId = session_id();
        $userId = $_SESSION['user_id'] ?? '';
        
        if ($userId) {
            $user = $this->db->fetch("SELECT login FROM utilisateurs WHERE id = ?", [$userId]);
            $this->db->query("UPDATE historique_connexions SET date_deconnexion = NOW(), heure_deconnexion = NOW(), session_statut = 'Fermee' WHERE session_id = ? AND session_statut = 'Active'", [$sessionId]);
        }
        
        session_destroy();
        redirect('/login');
    }
}