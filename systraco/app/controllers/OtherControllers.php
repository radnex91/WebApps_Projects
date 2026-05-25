<?php
require_once ROOT_PATH . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/controllers/Controller.php';

class ChauffeurController extends Controller {
    public function index() {
        auth();
        $chauffeurs = $this->db->fetchAll("SELECT * FROM chauffeurs ORDER BY nom_prenom_chauffeur");
        $this->view('chauffeurs/index', ['chauffeurs' => $chauffeurs]);
    }
    
    public function add() {
        auth();
        $this->view('chauffeurs/add');
    }
    
    public function store() {
        auth();
        $this->db->query("INSERT INTO chauffeurs (nom_prenom_chauffeur, telephone, numero_cni, numero_permis, date_experiration, sur_prenom, statut) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $_POST['nom_prenom_chauffeur'] ?? '',
            $_POST['telephone'] ?? '',
            $_POST['numero_cni'] ?? '',
            $_POST['numero_permis'] ?? '',
            $_POST['date_experiration'] ?? null,
            $_POST['sur_prenom'] ?? '',
            'Actif'
        ]);
        flash('Chauffeur ajoute', 'success');
        redirect('/chauffeurs');
    }
    
    public function edit() {
        auth();
        $id = func_get_arg(0);
        $chauffeur = $this->db->fetch("SELECT * FROM chauffeurs WHERE id = ?", [$id]);
        $this->view('chauffeurs/edit', ['chauffeur' => $chauffeur]);
    }
    
    public function update() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("UPDATE chauffeurs SET nom_prenom_chauffeur = ?, telephone = ?, numero_cni = ?, numero_permis = ?, date_experiration = ?, sur_prenom = ? WHERE id = ?", [
            $_POST['nom_prenom_chauffeur'] ?? '',
            $_POST['telephone'] ?? '',
            $_POST['numero_cni'] ?? '',
            $_POST['numero_permis'] ?? '',
            $_POST['date_experiration'] ?? null,
            $_POST['sur_prenom'] ?? '',
            $id
        ]);
        flash('Chauffeur mis a jour', 'success');
        redirect('/chauffeurs');
    }
    
    public function delete() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM chauffeurs WHERE id = ?", [$id]);
        flash('Chauffeur supprime', 'success');
        redirect('/chauffeurs');
    }
}

class VehiculeController extends Controller {
    public function index() {
        auth();
        $vehicules = $this->db->fetchAll("SELECT v.*, g.nom_groupe FROM vehicules v LEFT JOIN groupes g ON v.id_groupe = g.id ORDER BY v.imatriculation");
        $this->view('vehicules/index', ['vehicules' => $vehicules]);
    }
    
    public function add() {
        auth();
        $groupes = $this->db->fetchAll("SELECT * FROM groupes ORDER BY nom_groupe");
        $this->view('vehicules/add', ['groupes' => $groupes]);
    }
    
    public function store() {
        auth();
        $this->db->query("INSERT INTO vehicules (imatriculation, marque, modele, concessionnaire, nb_place, date_acquisition, statut, nom_groupe, id_groupe) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['imatriculation'] ?? '',
            $_POST['marque'] ?? '',
            $_POST['modele'] ?? '',
            $_POST['concessionnaire'] ?? '',
            $_POST['nb_place'] ?? 0,
            $_POST['date_acquisition'] ?? null,
            'Actif',
            $_POST['nom_groupe'] ?? '',
            $_POST['id_groupe'] ?? null
        ]);
        flash('Vehicule ajoute', 'success');
        redirect('/vehicules');
    }
    
    public function edit() {
        auth();
        $id = func_get_arg(0);
        $vehicule = $this->db->fetch("SELECT * FROM vehicules WHERE id = ?", [$id]);
        $groupes = $this->db->fetchAll("SELECT * FROM groupes ORDER BY nom_groupe");
        $this->view('vehicules/edit', ['vehicule' => $vehicule, 'groupes' => $groupes]);
    }
    
    public function update() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("UPDATE vehicules SET imatriculation = ?, marque = ?, modele = ?, concessionnaire = ?, nb_place = ?, date_acquisition = ?, nom_groupe = ?, id_groupe = ? WHERE id = ?", [
            $_POST['imatriculation'] ?? '',
            $_POST['marque'] ?? '',
            $_POST['modele'] ?? '',
            $_POST['concessionnaire'] ?? '',
            $_POST['nb_place'] ?? 0,
            $_POST['date_acquisition'] ?? null,
            $_POST['nom_groupe'] ?? '',
            $_POST['id_groupe'] ?? null,
            $id
        ]);
        flash('Vehicule mis a jour', 'success');
        redirect('/vehicules');
    }
    
    public function delete() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM vehicules WHERE id = ?", [$id]);
        flash('Vehicule supprime', 'success');
        redirect('/vehicules');
    }
}

class BordereauController extends Controller {
    public function index() {
        auth();
        $date = $_GET['date'] ?? date('Y-m-d');
        $bordereaux = $this->db->fetchAll("SELECT b.*, a.nom_agence, v.imatriculation, c.nom_prenom_chauffeur FROM bordereaux b LEFT JOIN agences a ON b.id_agence = a.id LEFT JOIN vehicules v ON b.id_vehicule = v.id LEFT JOIN chauffeurs c ON b.id_chauffeur = c.id WHERE DATE(b.date_bordereau) = ? ORDER BY b.heure_bordereau", [$date]);
        $this->view('bordereaux/index', ['bordereaux' => $bordereaux, 'date' => $date]);
    }
    
    public function add() {
        auth();
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $vehicules = $this->db->fetchAll("SELECT * FROM vehicules WHERE statut = 'Actif' ORDER BY imatriculation");
        $chauffeurs = $this->db->fetchAll("SELECT * FROM chauffeurs WHERE statut = 'Actif' ORDER BY nom_prenom_chauffeur");
        $this->view('bordereaux/add', ['agences' => $agences, 'vehicules' => $vehicules, 'chauffeurs' => $chauffeurs]);
    }
    
    public function store() {
        auth();
        $this->db->query("INSERT INTO bordereaux (numero, date_bordereau, heure_bordereau, vehicules, chauffeur, nombre_place, destination, type_voyage, origine, saisie_par, numero_comptable, id_vehicule, id_chauffeur, id_agence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['numero'] ?? '',
            $_POST['date_bordereau'] ?? date('Y-m-d'),
            $_POST['heure_bordereau'] ?? date('H:i'),
            $_POST['vehicules'] ?? '',
            $_POST['chauffeur'] ?? '',
            $_POST['nombre_place'] ?? 0,
            $_POST['destination'] ?? '',
            $_POST['type_voyage'] ?? 'Direct',
            $_POST['origine'] ?? '',
            getUserId(),
            $_POST['numero_comptable'] ?? '',
            $_POST['id_vehicule'] ?? null,
            $_POST['id_chauffeur'] ?? null,
            $_POST['id_agence'] ?? null
        ]);
        flash('Bordereau cree', 'success');
        redirect('/bordereaux');
    }
}

class EncaissementController extends Controller {
    public function index() {
        auth();
        $date = $_GET['date'] ?? date('Y-m-d');
        $encaissements = $this->db->fetchAll("SELECT * FROM encaissements WHERE DATE(date_encaissement) = ? ORDER BY heure_encaiss", [$date]);
        $this->view('encaissements/index', ['encaissements' => $encaissements, 'date' => $date]);
    }
    
    public function add() {
        auth();
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $this->view('encaissements/add', ['agences' => $agences]);
    }
    
    public function store() {
        auth();
        $this->db->query("INSERT INTO encaissements (nom_agence, Caisse, type_operation, libelle, montant_encaissement, date_encaissement, heure_encaiss, Caisse_par, id_agence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['nom_agence'] ?? '',
            $_POST['caisse'] ?? 'Caisse',
            $_POST['type_operation'] ?? 'Vente billet',
            $_POST['libelle'] ?? '',
            $_POST['montant_encaissement'] ?? 0,
            $_POST['date_encaissement'] ?? date('Y-m-d'),
            $_POST['heure_encaiss'] ?? date('H:i'),
            getUserId(),
            $_POST['id_agence'] ?? null
        ]);
        flash('Encaissement enregistre', 'success');
        redirect('/encaissements');
    }
}

class DepenseController extends Controller {
    public function index() {
        auth();
        $date = $_GET['date'] ?? date('Y-m-d');
        $depenses = $this->db->fetchAll("SELECT * FROM depenses WHERE DATE(date_depense) = ? ORDER BY date_depense", [$date]);
        $this->view('depenses/index', ['depenses' => $depenses, 'date' => $date]);
    }
    
    public function add() {
        auth();
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $vehicules = $this->db->fetchAll("SELECT * FROM vehicules ORDER BY imatriculation");
        $this->view('depenses/add', ['agences' => $agences, 'vehicules' => $vehicules]);
    }
    
    public function store() {
        auth();
        $this->db->query("INSERT INTO depenses (nom_agence, Caisse, type_operation, libelle, montant_depenses, date_depense, Caisse_par, id_agence, id_vehicule) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['nom_agence'] ?? '',
            $_POST['caisse'] ?? 'Caisse',
            $_POST['type_operation'] ?? '',
            $_POST['libelle'] ?? '',
            $_POST['montant_depenses'] ?? 0,
            $_POST['date_depense'] ?? date('Y-m-d'),
            getUserId(),
            $_POST['id_agence'] ?? null,
            $_POST['id_vehicule'] ?? null
        ]);
        flash('Depense enregistree', 'success');
        redirect('/depenses');
    }
}

class RapportController extends Controller {
    public function index() {
        auth();
        $this->view('rapports/index');
    }
    
    public function journalier() {
        auth();
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $recettes = $this->db->fetch("SELECT COALESCE(SUM(montant_encaissement), 0) as total FROM encaissements WHERE DATE(date_encaissement) = ?", [$date]);
        $depenses = $this->db->fetch("SELECT COALESCE(SUM(montant_depenses), 0) as total FROM depenses WHERE DATE(date_depense) = ?", [$date]);
        $billets = $this->db->fetch("SELECT COUNT(*) as total, COALESCE(SUM(somme_percue), 0) as recette FROM tickets WHERE DATE(date_ticket) = ?", [$date]);
        
        $this->view('rapports/journalier', ['date' => $date, 'recettes' => $recettes, 'depenses' => $depenses, 'billets' => $billets]);
    }
    
    public function exploitation() {
        auth();
        $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
        $date_fin = $_GET['date_fin'] ?? date('Y-m-d');
        
        $data = $this->db->fetchAll("SELECT DATE(date_bordereau) as date, COUNT(*) as departs, COALESCE(SUM(recette_caisse), 0) as recette FROM bordereaux WHERE DATE(date_bordereau) BETWEEN ? AND ? GROUP BY DATE(date_bordereau) ORDER BY date", [$date_debut, $date_fin]);
        
        $this->view('rapports/exploitation', ['date_debut' => $date_debut, 'date_fin' => $date_fin, 'data' => $data]);
    }
}

class UtilisateurController extends Controller {
    public function index() {
        auth();
        if (getUserRole() !== 'Administrateur') {
            flash('Access refuse', 'error');
            redirect('/dashboard');
        }
        
        $utilisateurs = $this->db->fetchAll("SELECT u.*, a.nom_agence FROM utilisateurs u LEFT JOIN agences a ON u.id_agence = a.id ORDER BY u.nom_utilisateur");
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        
        $this->view('utilisateurs/index', ['utilisateurs' => $utilisateurs, 'agences' => $agences]);
    }
    
    public function add() {
        auth();
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        $this->view('utilisateurs/add', ['agences' => $agences]);
    }
    
    public function store() {
        auth();
        $mot_de_passe = password_hash($_POST['mot_de_passe'] ?? 'password', PASSWORD_DEFAULT);
        
        $this->db->query("INSERT INTO utilisateurs (login, mot_de_passe, nom_utilisateur, prenom_utilisateur, role, id_agence) VALUES (?, ?, ?, ?, ?, ?)", [
            $_POST['login'] ?? '',
            $mot_de_passe,
            $_POST['nom_utilisateur'] ?? '',
            $_POST['prenom_utilisateur'] ?? '',
            $_POST['role'] ?? 'Guichetier',
            $_POST['id_agence'] ?? null
        ]);
        
        flash('Utilisateur ajoute', 'success');
        redirect('/utilisateurs');
    }
    
    public function edit() {
        auth();
        $id = func_get_arg(0);
        $utilisateur = $this->db->fetch("SELECT * FROM utilisateurs WHERE id = ?", [$id]);
        $agences = $this->db->fetchAll("SELECT * FROM agences ORDER BY nom_agence");
        
        $this->view('utilisateurs/edit', ['utilisateur' => $utilisateur, 'agences' => $agences]);
    }
    
    public function update() {
        auth();
        $id = func_get_arg(0);
        
        if (!empty($_POST['mot_de_passe'])) {
            $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
            $this->db->query("UPDATE utilisateurs SET login = ?, mot_de_passe = ?, nom_utilisateur = ?, prenom_utilisateur = ?, role = ?, id_agence = ? WHERE id = ?", [
                $_POST['login'] ?? '',
                $mot_de_passe,
                $_POST['nom_utilisateur'] ?? '',
                $_POST['prenom_utilisateur'] ?? '',
                $_POST['role'] ?? 'Guichetier',
                $_POST['id_agence'] ?? null,
                $id
            ]);
        } else {
            $this->db->query("UPDATE utilisateurs SET login = ?, nom_utilisateur = ?, prenom_utilisateur = ?, role = ?, id_agence = ? WHERE id = ?", [
                $_POST['login'] ?? '',
                $_POST['nom_utilisateur'] ?? '',
                $_POST['prenom_utilisateur'] ?? '',
                $_POST['role'] ?? 'Guichetier',
                $_POST['id_agence'] ?? null,
                $id
            ]);
        }
        
        flash('Utilisateur mis a jour', 'success');
        redirect('/utilisateurs');
    }
    
    public function delete() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM utilisateurs WHERE id = ?", [$id]);
        flash('Utilisateur supprime', 'success');
        redirect('/utilisateurs');
    }
}

class DepartController extends Controller {
    public function index() {
        auth();
        $this->view('departements/index');
    }
}

class ProgrammationController extends Controller {
    public function index() {
        auth();
        $this->view('programmation/index');
    }
}

class ReservationController extends Controller {
    public function index() {
        auth();
        $reservations = $this->db->fetchAll("SELECT r.*, i.nom_itineraire FROM reservations r LEFT JOIN itineraires i ON r.id_itineraire = i.id ORDER BY r.date_reservation DESC");
        $this->view('reservations/index', ['reservations' => $reservations]);
    }
    
    public function add() {
        auth();
        $itineraires = $this->db->fetchAll("SELECT * FROM itineraires ORDER BY nom_itineraire");
        $this->view('reservations/add', ['itineraires' => $itineraires]);
    }
    
    public function store() {
        auth();
        $lastNum = $this->db->fetch("SELECT MAX(numero_reservation) as lastNum FROM reservations");
        $nextNum = ($lastNum['lastNum'] ?? 1000) + 1;
        
        $this->db->query("INSERT INTO reservations (date_reservation, heure_reservation, numero_reservation, nom_prenom_passager, telephone_passager, numero_cni_passager, itineraire, classe_voyage, tarif, id_agence, id_itineraire) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['date_reservation'] ?? date('Y-m-d'),
            $_POST['heure_reservation'] ?? date('H:i'),
            $nextNum,
            $_POST['nom_prenom_passager'] ?? '',
            $_POST['telephone_passager'] ?? '',
            $_POST['numero_cni_passager'] ?? '',
            $_POST['itineraire'] ?? '',
            $_POST['classe_voyage'] ?? 'Classique',
            $_POST['tarif'] ?? 0,
            getAgenceId(),
            $_POST['id_itineraire'] ?? null
        ]);
        
        flash('Reservation enregistree', 'success');
        redirect('/reservations');
    }
    
    public function confirmer() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("UPDATE reservations SET statut = 'Confirme' WHERE id = ?", [$id]);
        flash('Reservation confirmee', 'success');
        redirect('/reservations');
    }
    
    public function delete() {
        auth();
        $id = func_get_arg(0);
        $this->db->query("DELETE FROM reservations WHERE id = ?", [$id]);
        flash('Reservation supprimee', 'success');
        redirect('/reservations');
    }
}