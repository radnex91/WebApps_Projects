-- ============================================================
-- ATLAS PRIME LOGISTICS - Base de données
-- ============================================================

CREATE DATABASE IF NOT EXISTS atlas_prime_logistics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atlas_prime_logistics;

-- Table des roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    niveau INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (nom, description, niveau) VALUES
('admin', 'Administrateur', 100),
('superviseur', 'Superviseur', 50),
('operateur', 'Opérateur', 10);

-- Table des permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO permissions (nom, description) VALUES
('colis_creer', 'Créer des colis'),
('colis_modifier', 'Modifier des colis'),
('colis_supprimer', 'Supprimer des colis'),
('colis_voir', 'Voir les détails des colis'),
('voyages_gerer', 'Gérer les voyages'),
('rapports_voir', 'Voir les rapports'),
('bordereaux_imprimer', 'Imprimer les bordereaux'),
('agences_gerer', 'Gérer les agences'),
('utilisateurs_gerer', 'Gérer les utilisateurs'),
('parametres', 'Modifier les paramètres'),
('vehicules_gerer', 'Gérer les véhicules'),
('trajets_gerer', 'Gérer les trajets'),
('manifest_gerer', 'Gérer les manifests'),
('voyage_avancer', 'Avancer le statut d''un voyage'),
('colis_livrer', 'Marquer un colis comme livré ou en livraison');

-- Table des permissions par role
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.nom = 'admin'
UNION ALL
SELECT r.id, p.id FROM roles r, permissions p WHERE r.nom = 'superviseur' AND p.nom IN ('colis_creer','colis_modifier','colis_supprimer','colis_voir','voyages_gerer','rapports_voir','bordereaux_imprimer','agences_gerer','vehicules_gerer','trajets_gerer','manifest_gerer','voyage_avancer','colis_livrer')
UNION ALL
SELECT r.id, p.id FROM roles r, permissions p WHERE r.nom = 'operateur' AND p.nom IN ('colis_creer','colis_modifier','colis_voir','voyages_gerer','rapports_voir')
UNION ALL
SELECT r.id, p.id FROM roles r, permissions p WHERE r.nom = 'Guichetier' AND p.nom IN ('colis_creer','colis_voir','bordereaux_imprimer','rapports_voir','voyages_gerer','manifest_gerer','voyage_avancer','colis_livrer');

-- Table des utilisateurs (mise à jour avec role_id FK)
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    username VARCHAR(60) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT DEFAULT 3,
    agence_id INT NULL DEFAULT NULL,
    actif TINYINT(1) DEFAULT 1,
    derniere_connexion DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET DEFAULT,
    FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des agences / points de départ-arrivée
CREATE TABLE IF NOT EXISTS agences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    nom VARCHAR(150) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des vehicules
CREATE TABLE IF NOT EXISTS vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    immatriculation VARCHAR(30) UNIQUE NOT NULL,
    designation VARCHAR(150) NOT NULL,
    statut ENUM('disponible','en_panne') DEFAULT 'disponible',
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des trajets
CREATE TABLE IF NOT EXISTS trajets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_trajet VARCHAR(30) UNIQUE NOT NULL,
    designation VARCHAR(200) NOT NULL,
    agence_depart_id INT NOT NULL,
    agence_arrivee_id INT NOT NULL,
    distance_km DECIMAL(8,2) DEFAULT NULL,
    duree_estimee INT DEFAULT NULL COMMENT 'En minutes',
    statut ENUM('actif','inactif') DEFAULT 'actif',
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_depart_id) REFERENCES agences(id),
    FOREIGN KEY (agence_arrivee_id) REFERENCES agences(id)
) ENGINE=InnoDB;

-- Table des escales (etapes intermediaires d'un trajet)
CREATE TABLE IF NOT EXISTS trajet_escales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trajet_id INT NOT NULL,
    agence_id INT NOT NULL,
    ordre INT NOT NULL,
    duree_arret INT DEFAULT 0 COMMENT 'En minutes',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE CASCADE,
    FOREIGN KEY (agence_id) REFERENCES agences(id),
    UNIQUE KEY uk_trajet_ordre (trajet_id, ordre),
    UNIQUE KEY uk_trajet_agence (trajet_id, agence_id)
) ENGINE=InnoDB;

-- Table des voyages (pour colis accompagnés)
CREATE TABLE IF NOT EXISTS voyages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_voyage VARCHAR(30) UNIQUE NOT NULL,
    transporteur VARCHAR(150) NULL DEFAULT NULL,
    matricule_vehicule VARCHAR(30) NULL DEFAULT NULL,
    vehicule_id INT NULL DEFAULT NULL,
    trajet_id INT NULL DEFAULT NULL,
    chauffeur VARCHAR(150),
    telephone_chauffeur VARCHAR(20),
    agence_depart_id INT NOT NULL,
    agence_arrivee_id INT NOT NULL,
    date_depart DATETIME NOT NULL,
    date_arrivee_prevue DATETIME,
    statut ENUM('planifie','en_cours','arrive','annule') DEFAULT 'planifie',
    escale_actuelle_id INT NULL DEFAULT NULL,  -- Position actuelle dans les escales
    date_derniere_escale DATETIME NULL DEFAULT NULL,
    notes TEXT,
    cree_par INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_depart_id) REFERENCES agences(id),
    FOREIGN KEY (agence_arrivee_id) REFERENCES agences(id),
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE SET NULL,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Table des expéditeurs / destinataires (contacts)
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_complet VARCHAR(200) NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    telephone2 VARCHAR(20),
    email VARCHAR(150),
    ville VARCHAR(100),
    adresse TEXT,
    type ENUM('expediteur','destinataire','les_deux') DEFAULT 'les_deux',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table principale des colis
CREATE TABLE IF NOT EXISTS colis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_colis VARCHAR(30) UNIQUE NOT NULL,
    type_expedition ENUM('normal','accompagne') DEFAULT 'normal',
    
    -- Expéditeur
    expediteur_nom VARCHAR(200) NOT NULL,
    expediteur_telephone VARCHAR(20) NOT NULL,
    expediteur_ville VARCHAR(100),
    expediteur_adresse TEXT,
    
    -- Destinataire
    destinataire_nom VARCHAR(200) NOT NULL,
    destinataire_telephone VARCHAR(20) NOT NULL,
    destinataire_ville VARCHAR(100),
    destinataire_adresse TEXT,
    
    -- Détails colis
    description TEXT NOT NULL,
    poids DECIMAL(8,2) DEFAULT 0,
    nombre_pieces INT DEFAULT 1,
    valeur_declaree DECIMAL(12,2) DEFAULT 0,
    
    -- Tarification
    tarif DECIMAL(12,2) NOT NULL DEFAULT 0,
    remise DECIMAL(12,2) DEFAULT 0,
    montant_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    mode_paiement ENUM('especes','mobile_money','virement','a_la_livraison') DEFAULT 'especes',
    statut_paiement ENUM('paye','en_attente','partiel') DEFAULT 'en_attente',
    
    -- Trajet
    agence_depart_id INT NOT NULL,
    agence_arrivee_id INT NOT NULL,
    voyage_id INT NULL,  -- NULL si expédition normale
    escale_actuelle_id INT NULL DEFAULT NULL,  -- Agence où le colis se trouve actuellement (transit multi-étapes)
    
    -- Statut et suivi
    statut ENUM('enregistre','en_transit','en_livraison','livre','retourne','perdu') DEFAULT 'enregistre',
    date_expedition DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_livraison_prevue DATE,
    date_livraison_reelle DATETIME NULL,
    signature_destinataire VARCHAR(255) NULL,
    
    notes TEXT,
    cree_par INT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (agence_depart_id) REFERENCES agences(id),
    FOREIGN KEY (agence_arrivee_id) REFERENCES agences(id),
    FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE SET NULL,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Table des lignes de détail des colis
CREATE TABLE IF NOT EXISTS colis_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colis_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    poids DECIMAL(8,2) DEFAULT 0,
    nombre_pieces INT DEFAULT 1,
    valeur_declaree DECIMAL(12,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table du suivi/historique des colis
CREATE TABLE IF NOT EXISTS suivi_colis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colis_id INT NOT NULL,
    statut VARCHAR(80) NOT NULL,
    localisation VARCHAR(200),
    commentaire TEXT,
    cree_par INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE CASCADE,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Table des sessions utilisateurs
CREATE TABLE IF NOT EXISTS sessions_utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    derniere_activite DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Logs d'activité
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(100) NOT NULL,
    table_cible VARCHAR(60),
    enregistrement_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

-- Admin par défaut (password: password)
INSERT INTO utilisateurs (nom, prenom, email, username, password_hash, role_id) VALUES
('Admin', 'Système', 'admin@atlasprime.cm', 'admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Dupont', 'Marie', 'marie@atlasprime.cm', 'marie.d', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('Martin', 'Jean', 'jean@atlasprime.cm', 'jean.m', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2);

-- Agences
INSERT INTO agences (code, nom, adresse, telephone) VALUES
('YDE', 'Agence Yaoundé Central', 'Av. Kennedy, Centre Ville', '+237 222 11 22 33'),
('DLA', 'Agence Douala Port', 'Rue Joss, Akwa', '+237 233 44 55 66'),
('BFM', 'Agence Bafoussam', 'Carrefour Commercial', '+237 233 77 88 99'),
('GMA', 'Agence Garoua', 'Quartier Administratif', '+237 222 33 44 55'),
('NGD', 'Agence Ngaoundéré', 'Centre Commercial', '+237 222 55 66 77'),
('KSL', 'Agence Kribi', 'Boulevard du Littoral', '+237 233 88 99 00'),
('EBD', 'Agence Ebolowa', 'Quartier Nkol-Melen', '+237 222 44 55 66'),
('BER', 'Agence Bertoua', 'Rue Principale', '+237 222 66 77 88');

-- Quelques contacts
INSERT INTO contacts (nom_complet, telephone, ville, type) VALUES
('Paul Mbarga', '+237 699 11 22 33', 'Yaoundé', 'les_deux'),
('Fatima Ndiaye', '+237 677 44 55 66', 'Douala', 'les_deux'),
('Emmanuel Tchamba', '+237 655 77 88 99', 'Bafoussam', 'les_deux');

-- Procédure de génération de numéro colis (obsolète, générée en PHP désormais)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS generer_numero_colis(OUT numero VARCHAR(30))
BEGIN
    DECLARE seq INT;
    SELECT COALESCE(MAX(id), 0) + 1 INTO seq FROM colis;
    SET numero = CONCAT('APL', DATE_FORMAT(NOW(), '%Y%m'), LPAD(seq, 5, '0'));
END //
DELIMITER ;

-- Vue rapide pour les colis
CREATE OR REPLACE VIEW vue_colis AS
SELECT
    c.id, c.numero_colis, c.type_expedition,
    c.expediteur_nom, c.expediteur_telephone,
    c.destinataire_nom, c.destinataire_telephone,
    c.description, c.poids, c.nombre_pieces,
    c.tarif, c.montant_total, c.mode_paiement, c.statut_paiement,
    c.agence_depart_id, c.agence_arrivee_id,
    ad.nom AS nom_depart, ad.nom AS agence_depart,
    aa.nom AS nom_arrivee, aa.nom AS agence_arrivee,
    v.numero_voyage, v.transporteur, v.date_depart AS voyage_depart,
    c.statut, c.date_expedition, c.date_livraison_reelle,
    CONCAT(u.prenom, ' ', u.nom) AS cree_par_nom,
    c.created_at
FROM colis c
JOIN agences ad ON c.agence_depart_id = ad.id
JOIN agences aa ON c.agence_arrivee_id = aa.id
LEFT JOIN voyages v ON c.voyage_id = v.id
JOIN utilisateurs u ON c.cree_par = u.id;
