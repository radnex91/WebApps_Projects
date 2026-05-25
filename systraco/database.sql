-- SYSTRACO Database Schema for MySQL
-- Version: 1.0

CREATE DATABASE IF NOT EXISTS systraco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE systraco;

-- Table: Agence
CREATE TABLE IF NOT EXISTS agences (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code_agence VARCHAR(50) NOT NULL,
    nom_agence VARCHAR(50) NOT NULL,
    ville_agence VARCHAR(50),
    cle_agence VARCHAR(3) DEFAULT '0',
    entreprise VARCHAR(50),
    telephone VARCHAR(20),
    responsable VARCHAR(100),
    statut VARCHAR(20) DEFAULT 'Actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Proprietaire
CREATE TABLE IF NOT EXISTS proprietaires (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code_proprietaire VARCHAR(20) NOT NULL,
    nom_entreprise VARCHAR(100) NOT NULL,
    nom_proprietaire VARCHAR(100),
    telephone VARCHAR(20),
    num_compte VARCHAR(100),
    nom_banque VARCHAR(50),
    niu VARCHAR(20),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Groupe
CREATE TABLE IF NOT EXISTS groupes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code_groupe VARCHAR(50),
    nom_groupe VARCHAR(50) NOT NULL,
    nom_entreprise VARCHAR(100),
    niu_proprietaire VARCHAR(20),
    id_proprietaire BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_proprietaire) REFERENCES proprietaires(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Vehicules
CREATE TABLE IF NOT EXISTS vehicules (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    imatriculation VARCHAR(50) NOT NULL,
    marque VARCHAR(50),
    concessionnaire VARCHAR(100),
    modele VARCHAR(50),
    nb_place INT(2) DEFAULT 0,
    date_acquisition DATE,
    statut VARCHAR(20) DEFAULT 'Actif',
    nom_groupe VARCHAR(50),
    id_groupe BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_groupe) REFERENCES groupes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Chauffeurs
CREATE TABLE IF NOT EXISTS chauffeurs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nom_prenom_chauffeur VARCHAR(250),
    telephone VARCHAR(20),
    statut VARCHAR(20) DEFAULT 'Actif',
    numero_cni VARCHAR(50),
    numero_permis VARCHAR(50),
    date_experiration DATE,
    sur_prenom VARCHAR(50),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Itineraires
CREATE TABLE IF NOT EXISTS itineraires (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    classe_itineraire VARCHAR(50),
    agence_depart VARCHAR(50),
    agence_arrivee VARCHAR(50),
    terminal VARCHAR(50),
    nom_itineraire VARCHAR(250) NOT NULL,
    tarif DECIMAL(10,2) DEFAULT 0,
    statut VARCHAR(20) DEFAULT 'Actif',
    trajet VARCHAR(100),
    groupe_itineraire VARCHAR(50),
    id_groupe BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_groupe) REFERENCES groupes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Destination
CREATE TABLE IF NOT EXISTS destinations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    destination VARCHAR(50) NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Tickets (Billets)
CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    date_ticket DATE,
    heure_ticket TIME,
    numero_ticket BIGINT NOT NULL,
    nom_prenom_passager VARCHAR(50),
    tarif_itineraire DECIMAL(10,2) DEFAULT 0,
    tarif_voyage_decide DECIMAL(10,2) DEFAULT 0,
    tarif_transitaire DECIMAL(10,2) DEFAULT 0,
   Agence_depart VARCHAR(50),
    agence_arrivee VARCHAR(50),
    itineraires VARCHAR(250),
    numero_cni_passager VARCHAR(20),
    telephone_passager VARCHAR(15),
    somme_percue DECIMAL(10,2) DEFAULT 0,
    reliquat DECIMAL(10,2) DEFAULT 0,
    classe_voyage VARCHAR(50),
    type_passager VARCHAR(50),
    mode_paiement VARCHAR(50),
    statut_ticket VARCHAR(20) DEFAULT 'Vendu',
    numero_bordereau VARCHAR(50),
    trajet VARCHAR(100),
    statut_voyageur VARCHAR(50),
    observation TEXT,
    saisie_par VARCHAR(100),
    modifier_par VARCHAR(50),
    numero_siege VARCHAR(2),
    numero_comptable VARCHAR(10),
    vehicule_transite VARCHAR(50),
    provenance_transite VARCHAR(50),
   agerie_transite VARCHAR(50),
    id_agence BIGINT,
    id_itineraire BIGINT,
    id_bordereau BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (id_itineraire) REFERENCES itineraires(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Reservations
CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    date_reservation DATE,
    heure_reservation TIME,
    numero_reservation BIGINT NOT NULL,
    nom_prenom_passager VARCHAR(50),
    telephone_passager VARCHAR(15),
    numero_cni_passager VARCHAR(20),
    itineraire VARCHAR(250),
    classe_voyage VARCHAR(50),
    tarif DECIMAL(10,2) DEFAULT 0,
    statut VARCHAR(20) DEFAULT 'Reserve',
    id_agence BIGINT,
    id_itineraire BIGINT,
   date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (id_itineraire) REFERENCES itineraires(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Bordereaux
CREATE TABLE IF NOT EXISTS bordereaux (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    statut_bordereaux VARCHAR(20),
    numero VARCHAR(50),
    date_bordereau DATE,
    heure_bordereau TIME,
    vehicules VARCHAR(50),
    chauffeur VARCHAR(50),
    nombre_place INT(4),
    destination VARCHAR(50),
    type_voyage VARCHAR(50),
    origine VARCHAR(50),
    convoyeur VARCHAR(100),
    montant_carburant DECIMAL(10,2) DEFAULT 0,
    montant_brute DECIMAL(10,2) DEFAULT 0,
    montant_peage DECIMAL(10,2) DEFAULT 0,
    recette_caisse DECIMAL(10,2) DEFAULT 0,
    itineraires VARCHAR(250),
    prime_chauffeur DECIMAL(10,2) DEFAULT 0,
    ration_chauffeur DECIMAL(10,2) DEFAULT 0,
    statut_carburant VARCHAR(10),
    tarif_itiniraire DECIMAL(10,2) DEFAULT 0,
    saisie_par VARCHAR(100),
    commentaire VARCHAR(500),
    retenu_agence DECIMAL(10,2) DEFAULT 0,
    passager_transitaire INT(4),
    recette_transit DECIMAL(10,2) DEFAULT 0,
    reliquat DECIMAL(10,2) DEFAULT 0,
    etat_bordereau TINYINT(1) DEFAULT 0,
    type_bordereau VARCHAR(20),
    ordonner_par VARCHAR(50),
    recette_transbord DECIMAL(10,2) DEFAULT 0,
    nombre_passager_transborder INT(4),
    recette_vehicule DECIMAL(10,2) DEFAULT 0,
    recette_agence DECIMAL(10,2) DEFAULT 0,
    modifier_par VARCHAR(50),
    intervention VARCHAR(50),
    statut_depart VARCHAR(15),
    effectif_arrive TINYINT(2),
    provenance VARCHAR(50),
    heure_arrivee TIME,
    id_vehicule BIGINT,
    id_chauffeur BIGINT,
    id_agence BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vehicule) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (id_chauffeur) REFERENCES chauffeurs(id) ON DELETE SET NULL,
    FOREIGN KEY (id_agence) REFERENCES agences(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: CaisseDepenses
CREATE TABLE IF NOT EXISTS depenses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nom_agence VARCHAR(50),
    caisse VARCHAR(5),
    type_caisse TINYINT(1) DEFAULT 0,
    cle VARCHAR(3) DEFAULT '0',
    numero_comptable VARCHAR(10),
    type_operation VARCHAR(50),
    imatriculation VARCHAR(50),
    libelle TEXT,
    montant_depenses DECIMAL(10,2) DEFAULT 0,
    date_depense DATE,
    saisie_par VARCHAR(100),
    modifier_par VARCHAR(100),
    id_agence BIGINT,
    id_vehicule BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (id_vehicule) REFERENCES vehicules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: CaisseEncaissements
CREATE TABLE IF NOT EXISTS encaissements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nom_agence VARCHAR(50),
    caisse VARCHAR(5),
    type_caisse TINYINT(1) DEFAULT 0,
    cle VARCHAR(3) DEFAULT '0',
    numero_comptable VARCHAR(10),
    type_operation VARCHAR(50),
    `references` VARCHAR(50),
    libelle TEXT,
    montant_encaissement DECIMAL(10,2) DEFAULT 0,
    date_encaissement DATE,
    saisie_par VARCHAR(100),
    modifier_par VARCHAR(100),
    imatriculation VARCHAR(50),
    num_remb VARCHAR(50),
    destination_prevu VARCHAR(50),
    heure_encaiss TIME,
    class_voyage VARCHAR(50),
    id_agence BIGINT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES agences(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom_utilisateur VARCHAR(100),
    prenom_utilisateur VARCHAR(100),
    role VARCHAR(20) DEFAULT 'Guichetier',
    statut VARCHAR(20) DEFAULT 'Actif',
    id_agence BIGINT,
    last_login DATETIME,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agence) REFERENCES agences(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: HistoriqueDeConnexion
CREATE TABLE IF NOT EXISTS historique_connexions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50),
    user_login VARCHAR(50),
    login VARCHAR(100),
    date_connexion DATE,
    heure_connexion TIME,
    date_deconnexion DATE,
    heure_deconnexion TIME,
    adresse_ip VARCHAR(50),
    session_statut VARCHAR(50),
    nom_pc VARCHAR(50),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: AgencePreRempli
CREATE TABLE IF NOT EXISTS agences_prefix (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code_prefix VARCHAR(50) NOT NULL,
    nom_prefix VARCHAR(50) NOT NULL,
    ville_prefix VARCHAR(50),
    cle_prefix VARCHAR(3),
    entreprise VARCHAR(50),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO utilisateurs (login, mot_de_passe, nom_utilisateur, prenom_utilisateur, role, statut) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'Systraco', 'Administrateur', 'Actif');

-- Insert sample agences
INSERT INTO agences (code_agence, nom_agence, ville_agence, cle_agence, entreprise) VALUES
('YAOUNDE', 'Yaounde', 'Yaounde', 'YAO', 'STraco'),
('DOUALA', 'Douala', 'Douala', 'DOU', 'STraco'),
('GAROUA', 'Garoua', 'Garoua', 'GAR', 'STraco');