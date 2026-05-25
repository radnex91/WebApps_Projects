<?php
require_once ROOT_PATH . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/controllers/Controller.php';

class ItineraireController extends Controller {
    
    public function index() {
        auth();
        
        $itineraires = $this->db->fetchAll("SELECT i.*, g.nom_groupe FROM itineraires i LEFT JOIN groupes g ON i.id_groupe = g.id ORDER BY i.nom_itineraire");
        $groupes = $this->db->fetchAll("SELECT * FROM groupes ORDER BY nom_groupe");
        $this->view('itineraires/index', ['itineraires' => $itineraires, 'groupes' => $groupes]);
    }
    
    public function add() {
        auth();
        
        $groupes = $this->db->fetchAll("SELECT * FROM groupes ORDER BY nom_groupe");
        $this->view('itineraires/add', ['groupes' => $groupes]);
    }
    
    public function store() {
        auth();
        
        $data = [
            $_POST['classe_itineraire'] ?? '',
            $_POST['agence_depart'] ?? '',
            $_POST['agence_arrivee'] ?? '',
            $_POST['terminal'] ?? '',
            $_POST['nom_itineraire'] ?? '',
            $_POST['tarif'] ?? 0,
            $_POST['statut'] ?? 'Actif',
            $_POST['trajet'] ?? '',
            $_POST['groupe_itineraire'] ?? '',
            $_POST['id_groupe'] ?? null
        ];
        
        $this->db->query("INSERT INTO itineraires (classe_itineraire, agence_depart, agencia_arrivee, terminal, nom_itineraire, tarif, statut, trajet, groupe_itineraire, id_groupe) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $data);
        
        flash('Itineraire ajoute', 'success');
        redirect('/itineraires');
    }
    
    public function edit() {
        auth();
        
        $id = func_get_arg(0);
        $itineraire = $this->db->fetch("SELECT * FROM itineraires WHERE id = ?", [$id]);
        $groupes = $this->db->fetchAll("SELECT * FROM groupes ORDER BY nom_groupe");
        
        if (!$itineraire) {
            flash('Itineraire non trouve', 'error');
            redirect('/itineraires');
        }
        
        $this->view('itineraires/edit', ['itineraire' => $itineraire, 'groupes' => $groupes]);
    }
    
    public function update() {
        auth();
        
        $id = func_get_arg(0);
        
        $data = [
            $_POST['classe_itineraire'] ?? '',
            $_POST['agence_depart'] ?? '',
            $_POST['agence_arrivee'] ?? '',
            $_POST['terminal'] ?? '',
            $_POST['nom_itineraire'] ?? '',
            $_POST['tarif'] ?? 0,
            $_POST['statut'] ?? 'Actif',
            $_POST['trajet'] ?? '',
            $_POST['groupe_itineraire'] ?? '',
            $_POST['id_groupe'] ?? null,
            $id
        ];
        
        $this->db->query("UPDATE itineraires SET classe_itineraire = ?, agence_depart = ?, agencia_arrivee = ?, terminal = ?, nom_itineraire = ?, tarif = ?, statut = ?, trajet = ?, groupe_itineraire = ?, id_groupe = ? WHERE id = ?", array_slice($data, 0, -1));
        
        flash('Itineraire mis a jour', 'success');
        redirect('/itineraires');
    }
    
    public function delete() {
        auth();
        
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM itineraires WHERE id = ?", [$id]);
        
        flash('Itineraire supprime', 'success');
        redirect('/itineraires');
    }
}