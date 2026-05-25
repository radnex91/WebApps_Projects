-- ============================================================
-- PharmaCare — Base de données
-- Compatible MySQL 5.7+ / MariaDB 10+
-- Importer via phpMyAdmin ou : mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacare;

-- ── Rôles ──────────────────────────────────────────────────
CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(60)  NOT NULL UNIQUE,
    libelle     VARCHAR(100) NOT NULL,
    est_systeme TINYINT(1)   DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Permissions ────────────────────────────────────────────
CREATE TABLE permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(80)  NOT NULL UNIQUE,
    libelle     VARCHAR(150) NOT NULL,
    module      VARCHAR(60)  NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Rôle ↔ Permission ─────────────────────────────────────
CREATE TABLE role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ── Utilisateurs ──────────────────────────────────────────
CREATE TABLE utilisateurs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    login       VARCHAR(60) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role_id     INT NOT NULL DEFAULT 3,
    actif       TINYINT(1) DEFAULT 1,
    derniere_connexion DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ── Catégories ────────────────────────────────────────────
CREATE TABLE categories (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nom     VARCHAR(100) NOT NULL UNIQUE,
    couleur VARCHAR(7) DEFAULT '#00c9a7'
);

-- ── Fournisseurs ──────────────────────────────────────────
CREATE TABLE fournisseurs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    contact     VARCHAR(100),
    telephone   VARCHAR(20),
    email       VARCHAR(150),
    adresse     TEXT,
    ville       VARCHAR(100),
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Clients ────────────────────────────────────────────────
CREATE TABLE clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    telephone   VARCHAR(20) DEFAULT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Règlements des dettes clients ─────────────────────────
CREATE TABLE reglements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    vente_id        INT DEFAULT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mode_paiement   ENUM('espèces','carte','chèque','mobile') NOT NULL DEFAULT 'espèces',
    note            VARCHAR(255) DEFAULT NULL,
    date_reglement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id)
);

-- ── Médicaments / Produits ────────────────────────────────
CREATE TABLE produits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(200) NOT NULL,
    reference       VARCHAR(50) UNIQUE,
    categorie_id    INT,
    fournisseur_id  INT,
    description     TEXT,
    stock           INT DEFAULT 0,
    seuil_alerte    INT DEFAULT 10,
    prix_achat      DECIMAL(10,2) DEFAULT 0,
    prix_vente      DECIMAL(10,2) DEFAULT 0,
    tva             DECIMAL(5,2) DEFAULT 9.00,
    date_expiration DATE,
    actif           TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL
);

-- ── Ventes (entête) ────────────────────────────────────────
CREATE TABLE ventes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20) UNIQUE NOT NULL,
    client_nom      VARCHAR(150),
    client_telephone VARCHAR(20),
    client_id       INT NULL,
    caissier_id     INT,
    sous_total      DECIMAL(10,2) DEFAULT 0,
    tva_total       DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2) DEFAULT 0,
    mode_paiement   ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces',
    statut_paiement ENUM('payé','en_attente','partiel') DEFAULT 'payé',
    montant_recu    DECIMAL(10,2) DEFAULT 0,
    monnaie         DECIMAL(10,2) DEFAULT 0,
    note            TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- ── Lignes de vente ────────────────────────────────────────
CREATE TABLE vente_lignes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    vente_id    INT NOT NULL,
    produit_id  INT,
    produit_nom VARCHAR(200),
    quantite    INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    tva         DECIMAL(5,2) DEFAULT 9.00,
    total_ligne DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL
);

-- ── Commandes fournisseurs ────────────────────────────────
CREATE TABLE commandes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20) UNIQUE NOT NULL,
    fournisseur_id  INT,
    utilisateur_id  INT,
    montant_total   DECIMAL(10,2) DEFAULT 0,
    statut          ENUM('en_attente','en_cours','livrée','annulée') DEFAULT 'en_attente',
    date_commande   DATE,
    date_livraison  DATE,
    note            TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── Mouvements de stock ────────────────────────────────────
CREATE TABLE mouvements_stock (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    produit_id  INT,
    type        ENUM('entrée','sortie','ajustement') NOT NULL,
    quantite    INT NOT NULL,
    motif       VARCHAR(255),
    utilisateur_id INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ══════════════════════════════════════════════════════════
-- DONNÉES DE DÉMONSTRATION
-- ══════════════════════════════════════════════════════════

-- Rôles système
INSERT INTO roles (id, code, libelle, est_systeme) VALUES
(1, 'admin',      'Administrateur', 1),
(2, 'pharmacien', 'Pharmacien',     1),
(3, 'caissier',   'Caissier',       1);

-- Permissions (25)
INSERT INTO permissions (id, code, libelle, module) VALUES
(1,  'dashboard.voir',       'Voir le tableau de bord',       'dashboard'),
(2,  'vente.creer',         'Créer des ventes (Point de Vente)',        'vente'),
(3,  'stock.voir',           'Voir le stock',                 'stock'),
(4,  'stock.ajuster',        'Ajuster le stock',              'stock'),
(5,  'produits.voir',        'Voir les médicaments',          'produits'),
(6,  'produits.ajouter',     'Ajouter des médicaments',       'produits'),
(7,  'produits.modifier',    'Modifier des médicaments',      'produits'),
(8,  'produits.archiver',    'Archiver des médicaments',       'produits'),
(9,  'fournisseurs.voir',    'Voir les fournisseurs',         'fournisseurs'),
(10, 'fournisseurs.ajouter', 'Ajouter des fournisseurs',      'fournisseurs'),
(11, 'fournisseurs.modifier','Modifier des fournisseurs',     'fournisseurs'),
(12, 'fournisseurs.supprimer','Supprimer des fournisseurs',   'fournisseurs'),
(13, 'commandes.voir',       'Voir les commandes',            'commandes'),
(14, 'commandes.creer',      'Créer des commandes',           'commandes'),
(15, 'commandes.modifier',   'Modifier des commandes',        'commandes'),
(16, 'ventes_hist.voir',     'Voir l''historique des ventes', 'ventes_hist'),
(17, 'rapports.voir',        'Voir les rapports',             'rapports'),
(18, 'utilisateurs.voir',    'Voir les utilisateurs',         'utilisateurs'),
(19, 'utilisateurs.gerer',   'Gérer les utilisateurs',        'utilisateurs'),
(20, 'categories.voir',      'Voir les catégories',           'categories'),
(21, 'categories.gerer',     'Gérer les catégories',           'categories'),
(22, 'parametres.voir',      'Voir les paramètres',           'parametres'),
(23, 'parametres.gerer',     'Gérer les paramètres',           'parametres'),
(24, 'roles.voir',           'Voir les rôles & permissions',  'roles'),
(25, 'roles.gerer',          'Gérer les rôles & permissions',  'roles'),
(26, 'clients.voir',       'Voir la liste des clients',   'clients'),
(27, 'clients.ajouter',    'Créer un client',              'clients'),
(28, 'clients.modifier',   'Modifier une fiche client',    'clients'),
(29, 'clients.supprimer',  'Désactiver un client',         'clients'),
(30, 'clients.paiements',  'Enregistrer des règlements',   'clients');

-- Admin : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Pharmacien : 14 permissions (fournisseurs = lecture seule, rôles = admin uniquement)
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),
(2,9),
(2,13),(2,14),(2,15),
(2,16),(2,17);

-- Caissier : 5 permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3,1),(3,2),(3,3),(3,16),(3,17),
(3,26),(3,27),(3,30);

-- Utilisateurs (mot de passe par défaut : password)
INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id) VALUES
('Administrateur', 'Système', 'admin@pharmacare.dz', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Maaref', 'Sabrina', 's.maaref@pharmacare.dz', 'pharmacien', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('Belkacemi', 'Yasmine', 'y.belkacemi@pharmacare.dz', 'caissier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);
-- Mot de passe pour tous : password

-- Catégories
INSERT INTO categories (nom, couleur) VALUES
('Antalgiques', '#00c9a7'),
('Antibiotiques', '#4895ef'),
('Anti-inflammatoires', '#f0b429'),
('Vitamines & Compléments', '#9b59b6'),
('Cardiologie', '#e74c3c'),
('Diabétologie', '#e67e22'),
('Respiratoire', '#1abc9c'),
('Gastro-entérologie', '#3498db'),
('Allergologie', '#e91e63'),
('Dermatologie', '#795548');

-- Fournisseurs
INSERT INTO fournisseurs (nom, contact, telephone, email, ville) VALUES
('PharmaDist Algérie', 'Mohamed Amine Khelif', '0555 12 34 56', 'contact@pharmadist.dz', 'Alger'),
('MediSupply', 'Fatima Zahra Benali', '0666 78 90 12', 'info@medisupply.dz', 'Oran'),
('SantéDist', 'Karim Boudiaf', '0777 34 56 78', 'sante@santedist.dz', 'Constantine'),
('PharmaGros', 'Nadia Rouibet', '0551 22 33 44', 'contact@pharmagros.dz', 'Blida');

-- Produits
INSERT INTO produits (nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration) VALUES
('Paracétamol 1g', 'MED-001', 1, 1, 145, 20, 45.00, 75.00, '2026-08-31'),
('Amoxicilline 500mg', 'MED-002', 2, 2, 7, 15, 120.00, 195.00, '2025-12-31'),
('Ibuprofène 400mg', 'MED-003', 3, 1, 89, 20, 55.00, 90.00, '2027-03-31'),
('Doliprane 1000mg', 'MED-004', 1, 3, 3, 25, 50.00, 80.00, '2026-06-30'),
('Ventoline 100µg', 'MED-005', 7, 2, 32, 10, 280.00, 420.00, '2026-11-30'),
('Augmentin 1g', 'MED-006', 2, 1, 41, 10, 350.00, 520.00, '2026-09-30'),
('Metformine 500mg', 'MED-007', 6, 3, 6, 15, 85.00, 130.00, '2026-04-30'),
('Atorvastatine 20mg', 'MED-008', 5, 2, 58, 10, 190.00, 290.00, '2027-01-31'),
('Vitamine C 1000mg', 'MED-009', 4, 1, 210, 30, 30.00, 55.00, '2027-06-30'),
('Smecta sachet x10', 'MED-010', 8, 3, 75, 20, 40.00, 68.00, '2026-12-31'),
('Doliprane 500mg', 'MED-011', 1, 1, 180, 30, 35.00, 60.00, '2026-08-31'),
('Xyzall 5mg', 'MED-012', 9, 2, 44, 10, 210.00, 330.00, '2026-10-31'),
('Oméprazole 20mg', 'MED-013', 8, 4, 95, 15, 70.00, 115.00, '2027-02-28'),
('Aspirine 500mg', 'MED-014', 1, 1, 120, 20, 25.00, 45.00, '2027-04-30'),
('Biseptol 480mg', 'MED-015', 2, 3, 0, 10, 95.00, 150.00, '2026-07-31');

-- Commandes
INSERT INTO commandes (reference, fournisseur_id, utilisateur_id, montant_total, statut, date_commande, date_livraison) VALUES
('CMD-2026-001', 1, 1, 45000.00, 'livrée', '2026-04-01', '2026-04-04'),
('CMD-2026-002', 2, 2, 28500.00, 'en_cours', '2026-04-08', NULL),
('CMD-2026-003', 3, 1, 12000.00, 'en_attente', '2026-04-10', NULL);

-- Quelques ventes de démo (TVA 19.25%)
INSERT INTO ventes (reference, client_nom, caissier_id, sous_total, tva_total, total, mode_paiement, montant_recu, monnaie, created_at) VALUES
('VNT-2026-001', 'Fatima Bouhel', 3, 383.00, 73.73, 456.73, 'espèces', 500.00, 43.27, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('VNT-2026-002', 'Ahmed Kouachi', 3, 625.00, 120.31, 745.31, 'carte', 745.31, 0.00, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('VNT-2026-003', '', 2, 130.00, 25.03, 155.03, 'espèces', 200.00, 44.97, DATE_SUB(NOW(), INTERVAL 6 HOUR));

INSERT INTO vente_lignes (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne) VALUES
(1, 1, 'Paracétamol 1g', 2, 75.00, 19.25, 150.00),
(1, 9, 'Vitamine C 1000mg', 3, 55.00, 19.25, 165.00),
(1, 10, 'Smecta sachet x10', 1, 68.00, 19.25, 68.00),
(2, 6, 'Augmentin 1g', 1, 520.00, 19.25, 520.00),
(2, 11, 'Doliprane 500mg', 1, 60.00, 19.25, 60.00),
(2, 14, 'Aspirine 500mg', 1, 45.00, 19.25, 45.00),
(3, 7, 'Metformine 500mg', 1, 130.00, 19.25, 130.00);
