-- RentFlow - Gestion des Loyers
-- Base de données MySQL

CREATE DATABASE IF NOT EXISTS rentflow;
USE rentflow;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','user') DEFAULT 'user',
    active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des agences
CREATE TABLE IF NOT EXISTS agences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    adresse VARCHAR(255),
    telephone VARCHAR(20),
    email VARCHAR(100),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des bailleurs
CREATE TABLE IF NOT EXISTS bailleurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    email VARCHAR(100),
    telephone VARCHAR(20),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des lots
CREATE TABLE IF NOT EXISTS lots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bailleur_id INT NOT NULL,
    agence_id INT,
    adresse VARCHAR(255) NOT NULL,
    ville VARCHAR(100),
    loyer_mensuel DECIMAL(10,2) NOT NULL,
    type ENUM('appartement','maison','local') DEFAULT 'appartement',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bailleur_id) REFERENCES bailleurs(id) ON DELETE CASCADE,
    FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE SET NULL
);

-- Table des paiements
CREATE TABLE IF NOT EXISTS paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lot_id INT NOT NULL,
    mois VARCHAR(7) NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATE,
    statut ENUM('avance','normal','retard') DEFAULT 'normal',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lot_mois (lot_id, mois)
);

-- Insertion de données de test
INSERT INTO agences (nom, adresse, telephone, email) VALUES
('Agence Centerville', '15 rue de la Paix, 75001 Paris', '01 23 45 67 89', 'contact@centerville.fr'),
('Agence Immobail', '8 avenue Victor Hugo, 69002 Lyon', '04 78 90 12 34', 'info@immobail.fr'),
('Agence Atlantique', '22 quai de la Douane, 33000 Bordeaux', '05 56 78 90 11', 'contact@atlantique.fr');

INSERT INTO bailleurs (nom, prenom, email, telephone) VALUES
('Dupont', 'Jean', 'jean.dupont@email.fr', '06 12 34 56 78'),
('Martin', 'Sophie', 'sophie.martin@email.fr', '06 23 45 67 89'),
('Bernard', 'Michel', 'michel.bernard@email.fr', '06 34 56 78 90');

INSERT INTO lots (bailleur_id, adresse, ville, loyer_mensuel, type) VALUES
(1, '25 rue Victor Hugo', 'Paris', 1200.00, 'appartement'),
(1, '8 place du Marché', 'Lyon', 950.00, 'appartement'),
(2, '42 avenue des Champs', 'Bordeaux', 1500.00, 'maison'),
(2, '10 rue du Port', 'Lyon', 800.00, 'appartement'),
(3, '5 rue de la Gare', 'Paris', 1100.00, 'appartement'),
(3, '18 avenue de la Mer', 'Bordeaux', 2200.00, 'local');

INSERT INTO utilisateurs (username, email, password, role) VALUES
('admin', 'admin@rentflow.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('manager', 'manager@rentflow.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager');

-- Table des paramètres
CREATE TABLE IF NOT EXISTS parametres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cle VARCHAR(50) UNIQUE NOT NULL,
    valeur VARCHAR(255) NOT NULL
);

INSERT INTO parametres (cle, valeur) VALUES
('devise', 'FCFA'),
('devise_position', 'droite'),
('date_format', 'd/m/Y'),
('jour_avance', '5'),
('jour_retard', '10'),
('nom_entreprise', 'RentFlow'),
('pays', 'Afrique Centrale'),
('theme', 'blue'),
('police', 'system'),
('sidebar_collapsed', '0'),
('rappels_auto', '1'),
('rappels_jours', '3'),
('email_expediteur', 'noreply@rentflow.fr');

-- Table des lots-agences (association par lot)
CREATE TABLE IF NOT EXISTS lots_agences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lot_id INT NOT NULL,
    agencia_id INT NOT NULL,
    date_debut DATE DEFAULT CURRENT_DATE,
    date_fin DATE,
    actif TINYINT(1) DEFAULT 1,
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
    FOREIGN KEY (agencia_id) REFERENCES agences(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lot_agence (lot_id, agencia_id, actif)
);

-- Table des rappels
CREATE TABLE IF NOT EXISTS rappels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    paiement_id INT NOT NULL,
    lot_id INT NOT NULL,
    agencia_id INT NOT NULL,
    date_rappel DATE,
    type ENUM('retard', 'impaye') DEFAULT 'retard',
    statut ENUM('pending', 'sent', 'viewed', 'resolved') DEFAULT 'pending',
    date_envoi DATETIME,
    message TEXT,
    FOREIGN KEY (paiement_id) REFERENCES paiements(id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
    FOREIGN KEY (agencia_id) REFERENCES agences(id) ON DELETE CASCADE
);