<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/Controller.php';

class BilletController extends Controller {
    
    public function index() {
        auth();
        
        $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
        $date_fin = $_GET['date_fin'] ?? date('Y-m-d');
        
        $sql = "SELECT t.*, a.nom_agence as Agence_nom, i.nom_itineraire 
               FROM tickets t 
               LEFT JOIN agences a ON t.id_agence = a.id 
               LEFT JOIN itineraires i ON t.id_itineraire = i.id 
               WHERE DATE(t.date_ticket) BETWEEN ? AND ?
               ORDER BY t.date_ticket DESC, t.heure_ticket DESC";
        
        $billets = $this->db->fetchAll($sql, [$date_debut, $date_fin]);
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $itineraires = $this->db->fetchAll("SELECT * FROM itineraires ORDER BY nom_itineraire");
        
        $this->view('billets/index', ['billets' => $billets, 'agences' => $agences, 'itineraires' => $itineraires, 'date_debut' => $date_debut, 'date_fin' => $date_fin]);
    }
    
    public function add() {
        auth();
        
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $itineraires = $this->db->fetchAll("SELECT * FROM itineraires ORDER BY nom_itineraire");
        $agences_prefix = $this->db->fetchAll("SELECT * FROM agences_prefix ORDER BY nom_prefix");
        
        $lastNum = $this->db->fetch("SELECT MAX(numero_ticket) as lastNum FROM tickets");
        $nextNum = ($lastNum['lastNum'] ?? 0) + 1;
        
        $this->view('billets/add', [
            'agences' => $agences, 
            'itineraires' => $itineraires,
            'agences_prefix' => $agences_prefix,
            'nextNum' => $nextNum
        ]);
    }
    
    public function store() {
        auth();
        
        $tarif = floatval($_POST['tarif_voyage'] ?? 0);
        $sommePercue = floatval($_POST['somme_percue'] ?? 0);
        $reliquat = $sommePercue - $tarif;
        
        $data = [
            $_POST['date_ticket'] ?? date('Y-m-d'),
            $_POST['heure_ticket'] ?? date('H:i:s'),
            $_POST['numero_ticket'] ?? 0,
            $_POST['nom_prenom_passager'] ?? '',
            $_POST['tarif_itineraire'] ?? 0,
            $tarif,
            0,
            $_POST['agence_depart'] ?? '',
            $_POST['agence_arrivee'] ?? '',
            $_POST['itineraire'] ?? '',
            $_POST['numero_cni_passager'] ?? '',
            $_POST['telephone_passager'] ?? '',
            $sommePercue,
            $reliquat,
            $_POST['classe_voyage'] ?? 'Classique',
            $_POST['type_passager'] ?? 'Direct',
            $_POST['mode_paiement'] ?? 'Especes',
            'Vendu',
            $_POST['numero_bordereau'] ?? '',
            $_POST['trajet'] ?? '',
            'En voyage',
            $_POST['observation'] ?? '',
            getUserId(),
            $_POST['numero_siege'] ?? '',
            getAgenceId(),
            $_POST['id_agence'] ?? null,
            $_POST['id_itineraire'] ?? null
        ];
        
        $this->db->query("INSERT INTO tickets (date_ticket, heure_ticket, numero_ticket, nom_prenom_passager, tarif_itineraire, tarif_voyage_decide, tarif_transitaire, agencia_depart, agencia_arrivee, itineraires, numero_cni_passager, telephone_passager, somme_percue, reliquat, classe_voyage, type_passager, mode_paiement, statut_ticket, numero_bordereau, trajet, statut_voyageur, observation, saisie_par, numero_siege, id_agence, id_itineraire) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $data);
        
        flash('Billet vendu avec succes', 'success');
        redirect('/billets');
    }
    
    public function edit() {
        auth();
        
        $id = func_get_arg(0);
        $billet = $this->db->fetch("SELECT * FROM tickets WHERE id = ?", [$id]);
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $itineraires = $this->db->fetchAll("SELECT * FROM itineraires ORDER BY nom_itineraire");
        
        if (!$billet) {
            flash('Billet non trouve', 'error');
            redirect('/billets');
        }
        
        $this->view('billets/edit', ['billet' => $billet, 'agences' => $agences, 'itineraires' => $itineraires]);
    }
    
    public function delete() {
        auth();
        
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM tickets WHERE id = ?", [$id]);
        
        flash('Billet annule', 'success');
        redirect('/billets');
    }
}