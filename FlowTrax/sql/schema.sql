CREATE DATABASE IF NOT EXISTS dex_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dex_app;

-- ============================================================
-- USERS & AUTH
-- ============================================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    module VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    telephone VARCHAR(20),
    avatar VARCHAR(255),
    active BOOLEAN DEFAULT TRUE,
    role_id INT,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT,
    type VARCHAR(20) DEFAULT 'text',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- AGENCES
-- ============================================================
CREATE TABLE agences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    ville VARCHAR(100),
    region VARCHAR(50),
    contact VARCHAR(50),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- VEHICULES
-- ============================================================
CREATE TABLE vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    immatriculation VARCHAR(20) NOT NULL UNIQUE,
    marque VARCHAR(50),
    modele VARCHAR(50),
    nb_places INT DEFAULT 0,
    proprietaire VARCHAR(100),
    type_vehicule VARCHAR(50),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CHAUFFEURS
-- ============================================================
CREATE TABLE chauffeurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    permis VARCHAR(30),
    telephone VARCHAR(20),
    agence_id INT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- BULLETINS D'EXPLOITATION
-- ============================================================
CREATE TABLE bulletins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_ordre VARCHAR(20),
    date_voyage DATE NOT NULL,
    vehicule_id INT,
    chauffeur_id INT,
    agence_depart_id INT,
    agence_destination_id INT,
    agence_complement_id INT,
    total_passagers INT DEFAULT 0,
    taux_remplissage DECIMAL(5,2) DEFAULT 0,
    heure_depart TIME,
    heure_arrivee TIME,
    numero_bordereau VARCHAR(30),
    observation TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (chauffeur_id) REFERENCES chauffeurs(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_depart_id) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_destination_id) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_complement_id) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE etapes_passagers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulletin_id INT NOT NULL,
    ville VARCHAR(100) NOT NULL,
    passagers_montees INT DEFAULT 0,
    FOREIGN KEY (bulletin_id) REFERENCES bulletins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CAISSE
-- ============================================================
CREATE TABLE caisse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('credit', 'debit') NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    description TEXT,
    date_operation DATE NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- MAINTENANCE IT
-- ============================================================
CREATE TABLE maintenances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resume VARCHAR(255),
    departement VARCHAR(100),
    date_debut DATETIME,
    type_maintenance VARCHAR(50),
    etapes TEXT,
    materiel TEXT,
    personnel TEXT,
    ressources TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SEEDS: Permissions
-- ============================================================
INSERT INTO permissions (code, nom, description, module) VALUES
('dashboard.view', 'Voir le tableau de bord', 'Accès au tableau de bord', 'dashboard'),
('users.view', 'Voir les utilisateurs', 'Lister les utilisateurs', 'users'),
('users.create', 'Créer un utilisateur', 'Ajouter un nouvel utilisateur', 'users'),
('users.edit', 'Modifier un utilisateur', 'Éditer les informations utilisateur', 'users'),
('users.delete', 'Supprimer un utilisateur', 'Supprimer un utilisateur', 'users'),
('roles.view', 'Voir les rôles', 'Lister les rôles', 'roles'),
('roles.create', 'Créer un rôle', 'Ajouter un nouveau rôle', 'roles'),
('roles.edit', 'Modifier un rôle', 'Éditer un rôle', 'roles'),
('roles.delete', 'Supprimer un rôle', 'Supprimer un rôle', 'roles'),
('settings.view', 'Voir les paramètres', 'Accès aux paramètres', 'settings'),
('settings.edit', 'Modifier les paramètres', 'Modifier la configuration', 'settings'),
('vehicules.view', 'Voir les véhicules', 'Lister les véhicules', 'vehicules'),
('vehicules.create', 'Créer un véhicule', 'Ajouter un véhicule', 'vehicules'),
('vehicules.edit', 'Modifier un véhicule', 'Éditer un véhicule', 'vehicules'),
('vehicules.delete', 'Supprimer un véhicule', 'Supprimer un véhicule', 'vehicules'),
('agences.view', 'Voir les agences', 'Lister les agences', 'agences'),
('agences.create', 'Créer une agence', 'Ajouter une agence', 'agences'),
('agences.edit', 'Modifier une agence', 'Éditer une agence', 'agences'),
('agences.delete', 'Supprimer une agence', 'Supprimer une agence', 'agences'),
('chauffeurs.view', 'Voir les chauffeurs', 'Lister les chauffeurs', 'chauffeurs'),
('chauffeurs.create', 'Créer un chauffeur', 'Ajouter un chauffeur', 'chauffeurs'),
('chauffeurs.edit', 'Modifier un chauffeur', 'Éditer un chauffeur', 'chauffeurs'),
('chauffeurs.delete', 'Supprimer un chauffeur', 'Supprimer un chauffeur', 'chauffeurs'),
('bulletins.view', 'Voir les bulletins', 'Lister les bulletins', 'bulletins'),
('bulletins.create', 'Créer un bulletin', 'Ajouter un bulletin', 'bulletins'),
('bulletins.edit', 'Modifier un bulletin', 'Éditer un bulletin', 'bulletins'),
('bulletins.delete', 'Supprimer un bulletin', 'Supprimer un bulletin', 'bulletins'),
('caisse.view', 'Voir la caisse', 'Lister les opérations', 'caisse'),
('caisse.create', 'Ajouter une opération', 'Ajouter crédit/débit', 'caisse'),
('caisse.delete', 'Supprimer opération', 'Supprimer une écriture', 'caisse'),
('maintenances.view', 'Voir maintenances', 'Lister les maintenances', 'maintenances'),
('maintenances.create', 'Créer maintenance', 'Ajouter une maintenance', 'maintenances'),
('maintenances.edit', 'Modifier maintenance', 'Éditer une maintenance', 'maintenances'),
('maintenances.delete', 'Supprimer maintenance', 'Supprimer une maintenance', 'maintenances');

-- ============================================================
-- SEEDS: Rôles
-- ============================================================
INSERT INTO roles (nom, description) VALUES
('Super Admin', 'Accès complet à toutes les fonctionnalités'),
('Admin', 'Gestion opérationnelle sans suppression'),
('Opérateur', 'Saisie des bulletins et consultation'),
('Observateur', 'Consultation uniquement');

-- Super Admin gets all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Admin gets everything except delete
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code NOT LIKE '%.delete';

-- Opérateur gets bulletins, vehicules, chauffeurs, dashboard
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN (
    'dashboard.view',
    'bulletins.view', 'bulletins.create',
    'vehicules.view',
    'chauffeurs.view',
    'agences.view',
    'caisse.view', 'caisse.create'
);

-- Observateur gets only view
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE code LIKE '%.view';

-- ============================================================
-- SEEDS: Super Admin user (password: admin123)
-- ============================================================
INSERT INTO users (username, email, password, nom, prenom, role_id, active)
VALUES ('admin', 'admin@dex-transport.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Admin', 'Super', 1, 1);

-- ============================================================
-- SEEDS: Agences (depuis le fichier Excel)
-- ============================================================
INSERT INTO agences (nom, ville, region) VALUES
('Douala', 'Douala', 'Littoral'),
('Yaoundé', 'Yaoundé', 'Centre'),
('Abong-mbang', 'Abong-mbang', 'Est'),
('Bertoua', 'Bertoua', 'Est'),
('Ndokayo', 'Ndokayo', 'Est'),
('Garoua-boulai', 'Garoua-boulai', 'Est'),
('Meiganga', 'Meiganga', 'Adamaoua'),
('Ngaoundéré I', 'Ngaoundéré', 'Adamaoua'),
('Ngaoundéré II', 'Ngaoundéré', 'Adamaoua'),
('Mbé', 'Mbé', 'Adamaoua'),
('Ngong', 'Ngong', 'Nord'),
('Garoua I', 'Garoua', 'Nord'),
('Garoua II', 'Garoua', 'Nord'),
('Touboro', 'Touboro', 'Nord'),
('Guider', 'Guider', 'Nord'),
('Kaélé', 'Kaélé', 'Extrême-Nord'),
('Guidiguis', 'Guidiguis', 'Extrême-Nord'),
('Kalfou', 'Kalfou', 'Extrême-Nord'),
('Doukoula', 'Doukoula', 'Extrême-Nord'),
('Yagoua', 'Yagoua', 'Extrême-Nord'),
('Maroua I', 'Maroua', 'Extrême-Nord'),
('Maroua II', 'Maroua', 'Extrême-Nord'),
('Mokolo', 'Mokolo', 'Extrême-Nord');

-- ============================================================
-- SEEDS: Settings
-- ============================================================
INSERT INTO settings (cle, valeur, type, description) VALUES
('app_name', 'DEX Transport', 'text', 'Nom de l\'application'),
('app_short_name', 'DEX', 'text', 'Nom abrégé'),
('app_logo', '', 'text', 'URL du logo'),
('app_company', 'DEX Transport Voyageur', 'text', 'Raison sociale'),
('app_email', 'contact@dex-transport.com', 'text', 'Email de contact'),
('app_phone', '+237 6XX XXX XXX', 'text', 'Téléphone'),
('app_address', 'Yaoundé, Cameroun', 'text', 'Adresse'),
('app_currency', 'XAF', 'text', 'Monnaie'),
('app_timezone', 'Africa/Douala', 'text', 'Fuseau horaire'),
('app_date_format', 'd/m/Y', 'text', 'Format de date'),
('pagination_per_page', '20', 'number', 'Nombre d\'éléments par page');
