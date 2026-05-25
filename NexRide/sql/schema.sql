-- NEXRIDE - Schéma de base de données
CREATE DATABASE IF NOT EXISTS nexride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexride;

-- 1. UTILISATEURS
CREATE TABLE utilisateurs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(20) DEFAULT NULL,
    role ENUM('admin','caissier','comptable','superviseur') NOT NULL DEFAULT 'caissier',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    avatar VARCHAR(255) DEFAULT NULL,
    derniere_connexion DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email), INDEX idx_role (role)
) ENGINE=InnoDB;

-- 2. CLIENTS
CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    prenom VARCHAR(100) DEFAULT NULL,
    telephone VARCHAR(20) NOT NULL,
    email VARCHAR(180) DEFAULT NULL,
    adresse TEXT DEFAULT NULL,
    piece_identite VARCHAR(50) DEFAULT NULL,
    num_piece VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telephone (telephone), INDEX idx_nom (nom)
) ENGINE=InnoDB;

-- 3. VEHICULES
CREATE TABLE vehicules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    immatriculation VARCHAR(20) NOT NULL UNIQUE,
    marque VARCHAR(50) NOT NULL,
    modele VARCHAR(50) NOT NULL,
    annee YEAR DEFAULT NULL,
    capacite INT UNSIGNED NOT NULL DEFAULT 4,
    type_vehicule ENUM('berline','minibus','bus','van') NOT NULL DEFAULT 'berline',
    couleur VARCHAR(30) DEFAULT NULL,
    assurance_date DATE DEFAULT NULL,
    visite_technique_date DATE DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_immatriculation (immatriculation), INDEX idx_actif (actif)
) ENGINE=InnoDB;

-- 4. CHAUFFEURS
CREATE TABLE chauffeurs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    email VARCHAR(180) DEFAULT NULL,
    permis VARCHAR(30) NOT NULL,
    categorie_permis VARCHAR(10) DEFAULT NULL,
    date_naissance DATE DEFAULT NULL,
    adresse TEXT DEFAULT NULL,
    date_embauche DATE NOT NULL DEFAULT (CURRENT_DATE),
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telephone (telephone), INDEX idx_permis (permis), INDEX idx_actif (actif)
) ENGINE=InnoDB;

-- 5. VOYAGES
CREATE TABLE voyages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    titre VARCHAR(200) NOT NULL,
    type_voyage ENUM('URBAIN','INTERURBAIN','NAVETTE','EXCURSION') NOT NULL DEFAULT 'URBAIN',
    depart VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    date_depart DATETIME NOT NULL,
    date_arrivee DATETIME DEFAULT NULL,
    vehicule_id INT UNSIGNED DEFAULT NULL,
    chauffeur_id INT UNSIGNED DEFAULT NULL,
    tarif_base DECIMAL(12,0) NOT NULL DEFAULT 0,
    nombre_places INT UNSIGNED NOT NULL DEFAULT 4,
    places_disponibles INT UNSIGNED NOT NULL DEFAULT 4,
    statut ENUM('PROGRAMME','EN_COURS','TERMINE','ANNULE') NOT NULL DEFAULT 'PROGRAMME',
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference), INDEX idx_date_depart (date_depart), INDEX idx_statut (statut),
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (chauffeur_id) REFERENCES chauffeurs(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. BILLETS
CREATE TABLE billets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    voyage_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED DEFAULT NULL,
    nom_passager VARCHAR(150) NOT NULL,
    telephone_passager VARCHAR(20) NOT NULL,
    email_passager VARCHAR(180) DEFAULT NULL,
    siege VARCHAR(10) DEFAULT NULL,
    montant DECIMAL(12,0) NOT NULL,
    taxe DECIMAL(12,0) DEFAULT 0,
    remise DECIMAL(12,0) DEFAULT 0,
    montant_total DECIMAL(12,0) NOT NULL,
    mode_paiement ENUM('ESPECES','CARTE','MOBILE_MONEY','VIREMENT','AUTRE') NOT NULL DEFAULT 'ESPECES',
    statut ENUM('EN_ATTENTE','CONFIRME','ANNULE','REMBOURSE') NOT NULL DEFAULT 'EN_ATTENTE',
    date_emission DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    code_validation VARCHAR(20) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    offline_id VARCHAR(50) DEFAULT NULL,
    synced TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference), INDEX idx_voyage (voyage_id), INDEX idx_statut (statut),
    INDEX idx_date_emission (date_emission), INDEX idx_synced (synced), INDEX idx_offline (offline_id),
    FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. BORDEREAUX
CREATE TABLE bordereaux (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    date_bordereau DATE NOT NULL,
    type ENUM('RECETTE','DEPENSE','VIREMENT') NOT NULL DEFAULT 'RECETTE',
    montant_total DECIMAL(12,0) NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    statut ENUM('BROUILLON','VALIDE','CLOTURE') NOT NULL DEFAULT 'BROUILLON',
    valide_par INT UNSIGNED DEFAULT NULL,
    date_validation DATETIME DEFAULT NULL,
    offline_id VARCHAR(50) DEFAULT NULL,
    synced TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference), INDEX idx_date (date_bordereau), INDEX idx_statut (statut),
    INDEX idx_synced (synced),
    FOREIGN KEY (valide_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. LIGNES BORDEREAUX
CREATE TABLE bordereau_lignes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bordereau_id INT UNSIGNED NOT NULL,
    billet_id INT UNSIGNED DEFAULT NULL,
    libelle VARCHAR(255) NOT NULL,
    montant DECIMAL(12,0) NOT NULL,
    type ENUM('RECETTE','DEPENSE') NOT NULL DEFAULT 'RECETTE',
    categorie VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bordereau (bordereau_id),
    FOREIGN KEY (bordereau_id) REFERENCES bordereaux(id) ON DELETE CASCADE,
    FOREIGN KEY (billet_id) REFERENCES billets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. ECRITURES COMPTABLES
CREATE TABLE ecritures_comptables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    date_ecriture DATE NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    debit DECIMAL(12,0) NOT NULL DEFAULT 0,
    credit DECIMAL(12,0) NOT NULL DEFAULT 0,
    compte VARCHAR(20) NOT NULL,
    compte_label VARCHAR(100) DEFAULT NULL,
    bordereau_id INT UNSIGNED DEFAULT NULL,
    billet_id INT UNSIGNED DEFAULT NULL,
    type_operation ENUM('VENTE','ACHAT','SALAIRE','CARBURANT','MAINTENANCE','ASSURANCE','TAXE','AUTRE') DEFAULT 'AUTRE',
    statut ENUM('BROUILLON','VALIDE','LETTRE') NOT NULL DEFAULT 'BROUILLON',
    notes TEXT DEFAULT NULL,
    offline_id VARCHAR(50) DEFAULT NULL,
    synced TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference), INDEX idx_date (date_ecriture), INDEX idx_compte (compte),
    INDEX idx_statut (statut), INDEX idx_synced (synced), INDEX idx_offline (offline_id),
    FOREIGN KEY (bordereau_id) REFERENCES bordereaux(id) ON DELETE SET NULL,
    FOREIGN KEY (billet_id) REFERENCES billets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 10. CAISSES
CREATE TABLE caisses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL,
    solde_initial DECIMAL(12,0) NOT NULL DEFAULT 0,
    solde_actuel DECIMAL(12,0) NOT NULL DEFAULT 0,
    type ENUM('PRINCIPALE','SECONDAIRE','MOBILE_MONEY') NOT NULL DEFAULT 'PRINCIPALE',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 11. MOUVEMENTS CAISSE
CREATE TABLE mouvements_caisse (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caisse_id INT UNSIGNED NOT NULL,
    type ENUM('ENTREE','SORTIE') NOT NULL,
    montant DECIMAL(12,0) NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    bordereau_id INT UNSIGNED DEFAULT NULL,
    billet_id INT UNSIGNED DEFAULT NULL,
    date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effectue_par INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    offline_id VARCHAR(50) DEFAULT NULL,
    synced TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_caisse (caisse_id), INDEX idx_date (date_operation), INDEX idx_synced (synced),
    FOREIGN KEY (caisse_id) REFERENCES caisses(id) ON DELETE CASCADE,
    FOREIGN KEY (bordereau_id) REFERENCES bordereaux(id) ON DELETE SET NULL,
    FOREIGN KEY (billet_id) REFERENCES billets(id) ON DELETE SET NULL,
    FOREIGN KEY (effectue_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 12. SYNC QUEUE (Offline)
CREATE TABLE sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    action ENUM('CREATE','UPDATE','DELETE') NOT NULL,
    record_id INT UNSIGNED DEFAULT NULL,
    offline_id VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('PENDING','PROCESSED','FAILED') NOT NULL DEFAULT 'PENDING',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_status (status), INDEX idx_offline (offline_id)
) ENGINE=InnoDB;

-- 13. AUDIT LOGS
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id), INDEX idx_action (action), INDEX idx_entity (entity_type,entity_id)
) ENGINE=InnoDB;

-- SEED DATA
INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES
('Administrateur', 'admin@nexride.com', '$2y$12$LJ3m4ys3Lp7g8ys9IPYsQe1iYsXp5qG6i7H8s9k0s9i8u7y6t5r4e3', 'admin'),
('Caisse Principale', 'caissier@nexride.com', '$2y$12$LJ3m4ys3Lp7g8ys9IPYsQe1iYsXp5qG6i7H8s9k0s9i8u7y6t5r4e3', 'caissier'),
('Comptable', 'comptable@nexride.com', '$2y$12$LJ3m4ys3Lp7g8ys9IPYsQe1iYsXp5qG6i7H8s9k0s9i8u7y6t5r4e3', 'comptable');

INSERT INTO caisses (libelle, solde_initial, solde_actuel, type) VALUES
('Caisse Principale', 500000, 500000, 'PRINCIPALE'),
('Mobile Money', 200000, 200000, 'MOBILE_MONEY');
-- Mot de passe pour tous les utilisateurs seed: password123