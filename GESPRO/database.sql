-- ============================================================
-- STOCK MOUTOURWA - Schéma Base de Données MySQL
-- Projet: MOUTOURWA - MAROUA | Code: 30
-- ============================================================

CREATE DATABASE IF NOT EXISTS stock_moutourwa 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE stock_moutourwa;

-- ----------------------------------------------------------------
-- Table des utilisateurs
-- ----------------------------------------------------------------
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    login VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','gestionnaire','assistant','directeur','raf') NOT NULL DEFAULT 'assistant',
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Table des projets
-- ----------------------------------------------------------------
CREATE TABLE projets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    actif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO projets (code, nom) VALUES ('30', 'MOUTOURWA - MAROUA');

-- ----------------------------------------------------------------
-- Table des matériaux / articles
-- ----------------------------------------------------------------
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) UNIQUE NOT NULL,
    designation VARCHAR(200) NOT NULL,
    categorie ENUM('ferraillage','boiserie','quincaillerie','carburant','ciment','autre') DEFAULT 'autre',
    unite VARCHAR(20) DEFAULT 'U',
    prix_unitaire DECIMAL(15,2) DEFAULT 0,
    stock_actuel DECIMAL(15,3) DEFAULT 0,
    stock_alerte DECIMAL(15,3) DEFAULT 0,
    cmupace DECIMAL(15,2) DEFAULT 0,
    valeur_stock DECIMAL(15,2) DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Articles pré-chargés depuis les fichiers Excel
INSERT INTO articles (code, designation, categorie, unite, prix_unitaire) VALUES
('FIL_ATTACHE', 'Fil d\'attache', 'quincaillerie', 'Kg', 1000),
('CIMENT', 'Ciment', 'autre', 'Sac', 5300),
('FER_10', 'Fer de 10', 'ferraillage', 'Barre', 856),
('FER_8', 'Fer de 8', 'ferraillage', 'Barre', 856),
('POINTE_150', 'Pointe de 150', 'quincaillerie', 'Kg', 5000),
('POINTE_60', 'Pointe de 60', 'quincaillerie', 'Kg', 1800),
('POINTE_TOC_70', 'Pointe de TOC 70', 'quincaillerie', 'Kg', 1800),
('BOIS_CHEVRON', 'Bois Chevron', 'boiserie', 'Pce', 8500),
('BOIS_PLANCHE', 'Bois Planche', 'boiserie', 'Pce', 7800),
('CARBURANT', 'Carburant', 'carburant', 'L', 856);

-- ----------------------------------------------------------------
-- Table des fournisseurs / affectations
-- ----------------------------------------------------------------
CREATE TABLE fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) UNIQUE NOT NULL,
    nom VARCHAR(200) NOT NULL,
    type ENUM('fournisseur','chantier','interne') DEFAULT 'fournisseur',
    actif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO fournisseurs (code, nom, type) VALUES
('BAKIDJA', 'ETS BAKIDJA', 'fournisseur'),
('RAMADAN', 'ETS RAMADAN', 'fournisseur'),
('NGB', 'ETS NGB', 'fournisseur'),
('HYPOLITE', 'ETS HYPOLITE', 'fournisseur'),
('GENIE_CIVIL', 'GENIE CIVIL', 'chantier'),
('MOBONO', 'MOBONO', 'chantier'),
('MOUTOURWA', 'MOUTOURWA', 'chantier'),
('PDVIR', 'PDVIR', 'chantier'),
('STATION_TOTAL', 'STATION TOTAL', 'fournisseur'),
('STATION_MOBYL', 'STATION MOBYL', 'fournisseur'),
('CAISSE', 'APPRO VIA CAISSE', 'interne');

-- ----------------------------------------------------------------
-- Table des mouvements de stock (fiche principale)
-- ----------------------------------------------------------------
CREATE TABLE mouvements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    projet_id INT NOT NULL DEFAULT 1,
    type_mouvement ENUM('entree','sortie') NOT NULL,
    date_mouvement DATE NOT NULL,
    
    -- Référence documentaire
    da_bc VARCHAR(50) COMMENT 'DA/BC - Demande d\'achat/Bon de commande',
    bcl_fact VARCHAR(50) COMMENT 'BCL/Facture',
    bsm VARCHAR(50) COMMENT 'BSM - Bon de sortie matériel',
    
    -- Données de quantité et valeur
    quantite DECIMAL(15,3) NOT NULL,
    prix_unitaire DECIMAL(15,2) NOT NULL,
    montant DECIMAL(15,2) GENERATED ALWAYS AS (quantite * prix_unitaire) STORED,
    
    -- Stock après mouvement (calculé)
    stock_apres DECIMAL(15,3) DEFAULT 0,
    valeur_stock_apres DECIMAL(15,2) DEFAULT 0,
    cmupace DECIMAL(15,2) DEFAULT 0,
    
    -- Affectation
    fournisseur_id INT,
    affectation_libre VARCHAR(200) COMMENT 'Affectation libre texte',
    
    -- Métadonnées
    mois INT,
    annee INT,
    observations TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (article_id) REFERENCES articles(id),
    FOREIGN KEY (projet_id) REFERENCES projets(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
    
    INDEX idx_article_date (article_id, date_mouvement),
    INDEX idx_type_date (type_mouvement, date_mouvement),
    INDEX idx_mois_annee (annee, mois)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Table d'analyse croisée mensuelle (résumé)
-- ----------------------------------------------------------------
CREATE TABLE analyse_mensuelle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    projet_id INT NOT NULL DEFAULT 1,
    annee INT NOT NULL,
    mois INT NOT NULL,
    
    -- Quantités
    qte_entrees DECIMAL(15,3) DEFAULT 0,
    qte_sorties DECIMAL(15,3) DEFAULT 0,
    qte_stock DECIMAL(15,3) DEFAULT 0,
    
    -- Valorisation
    montant_entrees DECIMAL(15,2) DEFAULT 0,
    montant_sorties DECIMAL(15,2) DEFAULT 0,
    marge DECIMAL(15,2) DEFAULT 0,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_article_periode (article_id, annee, mois),
    FOREIGN KEY (article_id) REFERENCES articles(id),
    FOREIGN KEY (projet_id) REFERENCES projets(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Table de rapports (signatures)
-- ----------------------------------------------------------------
CREATE TABLE validations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    annee INT NOT NULL,
    mois INT NOT NULL,
    signe_assistant TINYINT(1) DEFAULT 0,
    signe_gestionnaire TINYINT(1) DEFAULT 0,
    signe_raf TINYINT(1) DEFAULT 0,
    signe_directeur TINYINT(1) DEFAULT 0,
    date_validation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Utilisateur admin par défaut
-- ----------------------------------------------------------------
INSERT INTO utilisateurs (nom, prenom, login, password, role) VALUES
('ADMIN', 'Système', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('GESTIONNAIRE', 'Stock', 'gestionnaire', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gestionnaire');
-- Mot de passe par défaut: password
