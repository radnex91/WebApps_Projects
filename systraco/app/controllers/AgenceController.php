<?php
require_once ROOT_PATH . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/controllers/Controller.php';

class AgenceController extends Controller {
    
    public function index() {
        auth();
        
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $this->view('agences/index', ['agences' => $agences]);
    }
    
    public function add() {
        auth();
        
        $this->view('agences/add');
    }
    
    public function store() {
        auth();
        
        $data = [
            $_POST['code_agence'] ?? '',
            $_POST['nom_agence'] ?? '',
            $_POST['ville_agence'] ?? '',
            $_POST['cle_agence'] ?? '',
            $_POST['entreprise'] ?? '',
            $_POST['telephone'] ?? '',
            $_POST['responsable'] ?? ''
        ];
        
        $this->db->query("INSERT INTO agences (code_agence, nom_agence, ville_agence, cle_agence, entreprise, telephone, responsable) VALUES (?, ?, ?, ?, ?, ?, ?)", $data);
        
        flash('Agence ajoutee avec succes', 'success');
        redirect('/agences');
    }
    
    public function edit() {
        auth();
        
        $id = func_get_arg(0);
        $agence = $this->db->fetch("SELECT * FROM agences WHERE id = ?", [$id]);
        
        if (!$agence) {
            flash('Agence non trouvee', 'error');
            redirect('/agences');
        }
        
        $this->view('agences/edit', ['agence' => $agence]);
    }
    
    public function update() {
        auth();
        
        $id = func_get_arg(0);
        
        $data = [
            $_POST['code_agence'] ?? '',
            $_POST['nom_agence'] ?? '',
            $_POST['ville_agence'] ?? '',
            $_POST['cle_agence'] ?? '',
            $_POST['entreprise'] ?? '',
            $_POST['telephone'] ?? '',
            $_POST['responsable'] ?? '',
            $id
        ];
        
        $this->db->query("UPDATE agences SET code_agence = ?, nom_agence = ?, ville_agence = ?, cle_agence = ?, entreprise = ?, telephone = ?, responsable = ? WHERE id = ?", array_slice($data, 0, -1));
        
        flash('Agence mise a jour', 'success');
        redirect('/agences');
    }
    
    public function delete() {
        auth();
        
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM agences WHERE id = ?", [$id]);
        
        flash('Agence supprimee', 'success');
        redirect('/agences');
    }
}