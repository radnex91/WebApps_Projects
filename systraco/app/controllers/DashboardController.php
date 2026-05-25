<?php
require_once ROOT_PATH . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/controllers/Controller.php';

class DashboardController extends Controller {
    
    public function index() {
        auth();
        
        $agenceId = getAgenceId();
        
        $stats = [
            'tickets_aujourdhui' => 0,
            'recette_aujourdhui' => 0,
            'passagers' => 0,
            'depart_aujourdhui' => 0
        ];
        
        if ($agenceId) {
            $stats['tickets_aujourdhui'] = $this->db->fetch("SELECT COUNT(*) as total FROM tickets WHERE DATE(date_ticket) = CURDATE() AND id_agence = ?", [$agenceId])['total'] ?? 0;
            $stats['recette_aujourdhui'] = $this->db->fetch("SELECT COALESCE(SUM(somme_percue), 0) as total FROM tickets WHERE DATE(date_ticket) = CURDATE() AND id_agence = ?", [$agenceId])['total'] ?? 0;
        } else {
            $stats['tickets_aujourdhui'] = $this->db->fetch("SELECT COUNT(*) as total FROM tickets WHERE DATE(date_ticket) = CURDATE()")['total'] ?? 0;
            $stats['recette_aujourdhui'] = $this->db->fetch("SELECT COALESCE(SUM(somme_percue), 0) as total FROM tickets WHERE DATE(date_ticket) = CURDATE()")['total'] ?? 0;
        }
        
        $stats['depart_aujourdhui'] = $this->db->fetch("SELECT COUNT(*) as total FROM bordereaux WHERE DATE(date_bordereau) = CURDATE()")['total'] ?? 0;
        $stats['agences'] = $this->db->fetch("SELECT COUNT(*) as total FROM agences")['total'] ?? 0;
        
        $this->view('dashboard/index', ['stats' => $stats]);
    }
}