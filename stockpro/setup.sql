-- ============================================
-- StockPro - Base de données
-- Compatible XAMPP / MySQL
-- ============================================

CREATE DATABASE IF NOT EXISTS stockpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stockpro;

-- Table configuration entreprise
CREATE TABLE IF NOT EXISTS entreprise (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    slogan VARCHAR(255),
    logo VARCHAR(255),
    couleur_primaire VARCHAR(7) DEFAULT '#6366f1',
    couleur_secondaire VARCHAR(7) DEFAULT '#0f172a',
    adresse TEXT,
    telephone VARCHAR(30),
    email VARCHAR(100),
    site_web VARCHAR(150),
    devise VARCHAR(10) DEFAULT 'FCFA',
    police VARCHAR(20) DEFAULT 'dmsans',
    taille_texte INT DEFAULT 14,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','gestionnaire','caissier','lecteur') DEFAULT 'lecteur',
    actif TINYINT(1) DEFAULT 1,
    avatar VARCHAR(255),
    derniere_connexion TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table catégories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    couleur VARCHAR(7) DEFAULT '#6366f1',
    icone VARCHAR(50) DEFAULT 'box',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table fournisseurs
CREATE TABLE IF NOT EXISTS fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    contact VARCHAR(100),
    telephone VARCHAR(30),
    email VARCHAR(100),
    adresse TEXT,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table produits
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    categorie_id INT,
    fournisseur_id INT,
    prix_achat DECIMAL(15,2) DEFAULT 0,
    prix_vente DECIMAL(15,2) DEFAULT 0,
    quantite INT DEFAULT 0,
    quantite_min INT DEFAULT 5,
    unite VARCHAR(30) DEFAULT 'pcs',
    image VARCHAR(255),
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL
);

-- Table mouvements de stock
CREATE TABLE IF NOT EXISTS mouvements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    type ENUM('entree','sortie','ajustement','retour') NOT NULL,
    quantite INT NOT NULL,
    quantite_avant INT NOT NULL,
    quantite_apres INT NOT NULL,
    prix_unitaire DECIMAL(15,2),
    motif TEXT,
    reference_doc VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Table alertes
CREATE TABLE IF NOT EXISTS alertes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    type ENUM('stock_faible','rupture','peremption') DEFAULT 'stock_faible',
    lue TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
);

-- ============================================
-- Données initiales
-- ============================================

INSERT INTO entreprise (nom, slogan, couleur_primaire, couleur_secondaire, devise) 
VALUES ('Mon Entreprise', 'Gestion de stock intelligente', '#6366f1', '#0f172a', 'FCFA');

-- Mot de passe: password (hash bcrypt)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) 
VALUES ('Administrateur', 'Super', 'admin@stockpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Créer utilisateurs de démonstration supplémentaires
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES
('Gestionnaire', 'Marie', 'gestionnaire@stockpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gestionnaire'),
('Caissier', 'Paul', 'caissier@stockpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'caissier');

INSERT INTO categories (nom, couleur, icone) VALUES 
('Informatique', '#6366f1', 'monitor'),
('Fournitures', '#f59e0b', 'clipboard'),
('Électronique', '#10b981', 'zap'),
('Mobilier', '#ef4444', 'home'),
('Alimentaire', '#3b82f6', 'shopping-bag');

INSERT INTO fournisseurs (nom, contact, telephone, email) VALUES 
('Tech Supply Sarl', 'Jean Martin', '+237 690 000 001', 'contact@techsupply.cm'),
('Bureau Plus', 'Alice Dupont', '+237 677 000 002', 'vente@bureauplus.cm');

INSERT INTO produits (reference, nom, description, categorie_id, fournisseur_id, prix_achat, prix_vente, quantite, quantite_min) VALUES
('INFO-001', 'Ordinateur portable HP', 'HP ProBook 450 G8, i5, 8GB RAM', 1, 1, 350000, 480000, 12, 3),
('INFO-002', 'Souris sans fil Logitech', 'Logitech M185, USB', 1, 1, 8000, 15000, 45, 10),
('FOUR-001', 'Ramette papier A4', '500 feuilles, 80g/m²', 2, 2, 3500, 6000, 8, 20),
('FOUR-002', 'Stylos bille bleu (boîte)', 'Boîte de 50 stylos BIC', 2, 2, 2500, 4500, 30, 15),
('ELEC-001', 'Onduleur APC 650VA', 'Protection contre les coupures', 3, 1, 45000, 75000, 6, 2),
('INFO-003', 'Clé USB 64GB Kingston', 'USB 3.0, lecture 100MB/s', 1, 1, 5000, 10000, 3, 10);

-- Mouvements de démonstration
INSERT INTO mouvements (produit_id, utilisateur_id, type, quantite, quantite_avant, quantite_apres, motif) VALUES
(1, 1, 'entree', 12, 0, 12, 'Réception initiale'),
(2, 1, 'entree', 50, 0, 50, 'Réception initiale'),
(3, 1, 'entree', 20, 0, 20, 'Réception initiale'),
(4, 1, 'entree', 35, 0, 35, 'Réception initiale'),
(5, 1, 'entree', 6, 0, 6, 'Réception initiale'),
(6, 1, 'entree', 10, 0, 10, 'Réception initiale'),
(2, 1, 'sortie', 5, 50, 45, 'Distribution bureaux'),
(3, 1, 'sortie', 12, 20, 8, 'Usage mensuel'),
(4, 1, 'sortie', 5, 35, 30, 'Service comptabilité'),
(6, 1, 'sortie', 7, 10, 3, 'Vente comptoir');
