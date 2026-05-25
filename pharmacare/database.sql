-- ============================================================
-- PharmaCare — Base de données complète
-- Compatible MySQL 5.7+ / MariaDB 10+
-- Import unique : mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacare;

-- ════════════════════════════════════════════════════════════
-- TABLES DE BASE
-- ════════════════════════════════════════════════════════════

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

-- ── Paramètres globaux ────────────────────────────────────
CREATE TABLE parametres (
    cle     VARCHAR(60) PRIMARY KEY,
    valeur  TEXT NOT NULL,
    label   VARCHAR(120),
    groupe  VARCHAR(60) DEFAULT 'général'
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
    statut          ENUM('en_attente','en_cours','livrée','annulée') DEFAULT 'en_attente',
    date_commande   DATE,
    date_livraison  DATE,
    note            TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── Lignes de commande ────────────────────────────────────
CREATE TABLE commande_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    commande_id   INT NOT NULL,
    produit_id    INT,
    designation   VARCHAR(200) NOT NULL,
    quantite      INT DEFAULT 1,
    prix_unitaire DECIMAL(10,2) DEFAULT 0,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)  REFERENCES produits(id) ON DELETE SET NULL
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

-- ════════════════════════════════════════════════════════════
-- MODULE CAISSE
-- ════════════════════════════════════════════════════════════

-- ── Postes de caisse ─────────────────────────────────────
CREATE TABLE caisses (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    actif      TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Sessions de caisse (ouverture → fermeture) ───────────
CREATE TABLE sessions_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id       INT NOT NULL,
    caissier_id     INT NOT NULL,
    fond_initial    DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_ouverture  DATETIME NOT NULL,
    date_fermeture  DATETIME NULL,
    solde_attendu   DECIMAL(10,2) NULL,
    solde_reel      DECIMAL(10,2) NULL,
    ecart           DECIMAL(10,2) NULL,
    statut          ENUM('ouverte','fermée') NOT NULL DEFAULT 'ouverte',
    FOREIGN KEY (caisse_id)   REFERENCES caisses(id),
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id)
);

-- ── Mouvements de caisse (entrées / sorties) ─────────────
CREATE TABLE mouvements_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT NOT NULL,
    type            ENUM('entrée','sortie') NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    motif           VARCHAR(255) NOT NULL,
    moyen           ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces',
    reference_vente VARCHAR(20) NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions_caisse(id)
);

-- ════════════════════════════════════════════════════════════
-- MODULE COMPTABILITÉ (OHADA — CEMAC)
-- ════════════════════════════════════════════════════════════

-- ── Plan comptable OHADA ──────────────────────────────────
CREATE TABLE plan_comptable (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    compte      VARCHAR(15) NOT NULL UNIQUE,
    intitule    VARCHAR(200) NOT NULL,
    classe      TINYINT(1) NOT NULL,
    nature      ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    compte_parent INT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_parent) REFERENCES plan_comptable(id)
);

-- ── Exercices comptables ─────────────────────────────────
CREATE TABLE exercices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(9) NOT NULL UNIQUE,
    libelle     VARCHAR(100) NOT NULL,
    date_debut  DATE NOT NULL,
    date_fin    DATE NOT NULL,
    cloture     TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Écritures comptables (entête) ────────────────────────
CREATE TABLE ecritures (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(30) NOT NULL UNIQUE,
    libelle         VARCHAR(255) NOT NULL,
    date_ecriture   DATE NOT NULL,
    exercice_id     INT,
    utilisateur_id  INT,
    source          VARCHAR(30) DEFAULT 'manuel',
    source_ref      VARCHAR(30) NULL,
    verrouillee     TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- ── Lignes d'écriture (double partie) ───────────────────
CREATE TABLE ecriture_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ecriture_id   INT NOT NULL,
    compte_id     INT NOT NULL,
    debit         DECIMAL(12,2) DEFAULT 0,
    credit        DECIMAL(12,2) DEFAULT 0,
    libelle_ligne VARCHAR(255),
    FOREIGN KEY (ecriture_id) REFERENCES ecritures(id) ON DELETE CASCADE,
    FOREIGN KEY (compte_id)   REFERENCES plan_comptable(id)
);

-- ════════════════════════════════════════════════════════════
-- MODULE MARKETING (RadnexMarketer)
-- ════════════════════════════════════════════════════════════

-- ── Campagnes promotionnelles ─────────────────────────────
CREATE TABLE campagnes_promo (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(200) NOT NULL,
    description TEXT,
    type        ENUM('pourcentage','montant_fixe') NOT NULL DEFAULT 'pourcentage',
    valeur      DECIMAL(10,2) NOT NULL,
    date_debut  DATE NOT NULL,
    date_fin    DATE NOT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Produits liés à une campagne promo ────────────────────
CREATE TABLE promo_produits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    produit_id  INT NOT NULL,
    FOREIGN KEY (campagne_id) REFERENCES campagnes_promo(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)  REFERENCES produits(id) ON DELETE CASCADE
);

-- ── Points fidélité clients ───────────────────────────────
CREATE TABLE fidelite_points (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    points      INT NOT NULL,
    type        ENUM('gagné','utilisé') NOT NULL DEFAULT 'gagné',
    reference   VARCHAR(100),
    note        VARCHAR(255),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- ════════════════════════════════════════════════════════════
-- DONNÉES DE RÉFÉRENCE
-- ════════════════════════════════════════════════════════════

-- ── Rôles système ─────────────────────────────────────────
INSERT INTO roles (id, code, libelle, est_systeme) VALUES
(1, 'admin',      'Administrateur', 1),
(2, 'pharmacien', 'Pharmacien',     1),
(3, 'caissier',   'Caissier',       1);

-- ── Permissions (tous modules) ────────────────────────────
INSERT INTO permissions (code, libelle, module) VALUES
-- Dashboard
('dashboard.voir',       'Voir le tableau de bord',       'dashboard'),
-- Vente
('vente.creer',          'Créer des ventes (Point de Vente)', 'vente'),
-- Stock
('stock.voir',           'Voir le stock',                 'stock'),
('stock.ajuster',        'Ajuster le stock',              'stock'),
-- Produits
('produits.voir',        'Voir les médicaments',          'produits'),
('produits.ajouter',     'Ajouter des médicaments',       'produits'),
('produits.modifier',    'Modifier des médicaments',      'produits'),
('produits.archiver',    'Archiver des médicaments',      'produits'),
-- Fournisseurs
('fournisseurs.voir',    'Voir les fournisseurs',         'fournisseurs'),
('fournisseurs.ajouter', 'Ajouter des fournisseurs',      'fournisseurs'),
('fournisseurs.modifier','Modifier des fournisseurs',     'fournisseurs'),
('fournisseurs.supprimer','Supprimer des fournisseurs',   'fournisseurs'),
-- Commandes
('commandes.voir',       'Voir les commandes',            'commandes'),
('commandes.creer',      'Créer des commandes',           'commandes'),
('commandes.modifier',   'Modifier des commandes',        'commandes'),
-- Historique ventes
('ventes_hist.voir',     'Voir l''historique des ventes', 'ventes_hist'),
-- Rapports
('rapports.voir',        'Voir les rapports',             'rapports'),
('rapports_caissier.voir','Voir ses rapports personnels',  'rapports'),
-- Utilisateurs
('utilisateurs.voir',    'Voir les utilisateurs',         'utilisateurs'),
('utilisateurs.gerer',   'Gérer les utilisateurs',        'utilisateurs'),
-- Catégories
('categories.voir',      'Voir les catégories',           'categories'),
('categories.gerer',     'Gérer les catégories',          'categories'),
-- Paramètres
('parametres.voir',      'Voir les paramètres',           'parametres'),
('parametres.gerer',     'Gérer les paramètres',          'parametres'),
-- Rôles
('roles.voir',           'Voir les rôles & permissions',  'roles'),
('roles.gerer',          'Gérer les rôles & permissions', 'roles'),
-- Clients
('clients.voir',         'Voir la liste des clients',     'clients'),
('clients.ajouter',      'Créer un client',               'clients'),
('clients.modifier',     'Modifier une fiche client',     'clients'),
('clients.supprimer',    'Désactiver un client',          'clients'),
('clients.paiements',    'Enregistrer des règlements',    'clients'),
-- Caisse
('caisse.voir',          'Voir le dashboard des caisses', 'caisse'),
('caisse.gerer',         'Gérer les caisses',             'caisse'),
('caisse.ouvrir',        'Ouvrir une session de caisse',  'caisse'),
-- Comptabilité
('comptabilite.voir',    'Accéder à la comptabilité',     'comptabilite'),
('comptabilite.saisie',  'Saisir des écritures manuelles','comptabilite'),
('comptabilite.plan',    'Gérer le plan comptable',       'comptabilite'),
-- Marketing
('marketing.voir',       'Voir le marketing',             'marketing'),
('marketing.promos',     'Gérer les promotions',          'marketing'),
('marketing.fidelite',   'Gérer la fidélité clients',     'marketing');

-- ── Permissions par rôle ──────────────────────────────────

-- Admin : TOUTES les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Pharmacien : 17 permissions + clients.voir
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN (
    'dashboard.voir', 'vente.creer', 'stock.voir', 'stock.ajuster',
    'produits.voir', 'produits.ajouter', 'produits.modifier', 'produits.archiver',
    'fournisseurs.voir',
    'commandes.voir', 'commandes.creer', 'commandes.modifier',
    'ventes_hist.voir', 'rapports.voir',
    'clients.voir',
    'marketing.voir', 'marketing.promos'
);

-- Caissier : 9 permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN (
    'dashboard.voir', 'vente.creer', 'stock.voir',
    'ventes_hist.voir', 'rapports.voir',
    'rapports_caissier.voir',
    'clients.voir', 'clients.ajouter', 'clients.paiements'
);

-- ── Utilisateurs (mot de passe : password) ────────────────
INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id) VALUES
('Administrateur', 'Système', 'admin@pharmacare.dz', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Maaref', 'Sabrina', 's.maaref@pharmacare.dz', 'pharmacien', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('Belkacemi', 'Yasmine', 'y.belkacemi@pharmacare.dz', 'caissier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);

-- ── Catégories ────────────────────────────────────────────
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

-- ── Fournisseurs ──────────────────────────────────────────
INSERT INTO fournisseurs (nom, contact, telephone, email, ville) VALUES
('PharmaDist Algérie', 'Mohamed Amine Khelif', '0555 12 34 56', 'contact@pharmadist.dz', 'Alger'),
('MediSupply', 'Fatima Zahra Benali', '0666 78 90 12', 'info@medisupply.dz', 'Oran'),
('SantéDist', 'Karim Boudiaf', '0777 34 56 78', 'sante@santedist.dz', 'Constantine'),
('PharmaGros', 'Nadia Rouibet', '0551 22 33 44', 'contact@pharmagros.dz', 'Blida');

-- ── Paramètres ────────────────────────────────────────────
INSERT INTO parametres (cle, valeur, label, groupe) VALUES
('devise',        'XAF',                  'Devise',               'général'),
('devise_symbole','FCFA',                 'Symbole devise',       'général'),
('devise_pos',    'after',                'Position symbole',     'général'),
('tva',           '19.25',                'Taux TVA (%)',         'général'),
('app_nom',       'PharmaCare',           'Nom de la pharmacie',  'général'),
('theme',         'dark-cyan',            'Thème couleur',        'apparence'),
('police',        'DM Sans',              'Police principale',    'apparence'),
('police_titre',  'Cormorant Garamond',   'Police titres',        'apparence'),
('caisse_fermeture_mode', 'manuel',       'Mode fermeture caisse','caisse'),
('caisse_heure_fermeture','22:00',        'Heure fermeture auto', 'caisse');

-- ── Postes de caisse ──────────────────────────────────────
INSERT INTO caisses (id, nom) VALUES
(1, 'Caisse 1'),
(2, 'Caisse 2'),
(3, 'Caisse 3');

-- ── Plan comptable OHADA (Pharmacie CEMAC) ────────────────

-- Classe 1 : Capitaux
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('1',     'Comptes de capitaux',       1, 'credit'),
('101',   'Capital social',            1, 'credit'),
('1011',  'Capital individuel',        1, 'credit'),
('106',   'Réserves',                  1, 'credit'),
('1061',  'Réserves légales',          1, 'credit'),
('1063',  'Réserves libres',           1, 'credit'),
('12',    'Résultat de l''exercice',   1, 'credit'),
('129',   'Résultat en instance d''affectation', 1, 'credit');

-- Classe 2 : Immobilisations
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('2',     'Comptes d''immobilisations', 2, 'debit'),
('218',   'Autres immobilisations',    2, 'debit'),
('2183',  'Matériel et outillage',     2, 'debit'),
('2184',  'Mobilier de bureau',        2, 'debit'),
('2185',  'Matériel informatique',     2, 'debit'),
('281',   'Amortissements',            2, 'credit');

-- Classe 3 : Stocks
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('3',     'Comptes de stocks',         3, 'debit'),
('311',   'Marchandises en stock',     3, 'debit'),
('3111',  'Médicaments en stock',      3, 'debit'),
('3112',  'Produits parapharmaceutiques', 3, 'debit');

-- Classe 4 : Tiers
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('4',     'Comptes de tiers',          4, 'debit'),
('401',   'Fournisseurs',              4, 'credit'),
('4011',  'Fournisseurs achats',       4, 'credit'),
('411',   'Clients',                   4, 'debit'),
('4111',  'Clients - Assurance',       4, 'debit'),
('421',   'Personnel rémunérations',   4, 'credit'),
('431',   'Sécurité sociale',          4, 'credit'),
('441',   'État - TVA collectée',      4, 'credit'),
('4411',  'TVA collectée 19.25%',      4, 'credit'),
('445',   'État - TVA récupérable',    4, 'debit'),
('4451',  'TVA récupérable 19.25%',    4, 'debit'),
('471',   'Compte d''attente',         4, 'credit');

-- Classe 5 : Trésorerie
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('5',     'Comptes de trésorerie',     5, 'debit'),
('511',   'Chèques à encaisser',       5, 'debit'),
('512',   'Banque',                    5, 'debit'),
('571',   'Caisse',                    5, 'debit'),
('5711',  'Caisse principale',         5, 'debit'),
('581',   'Virements internes',        5, 'debit');

-- Classe 6 : Charges
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('6',     'Comptes de charges',        6, 'debit'),
('601',   'Achats de marchandises',    6, 'debit'),
('6011',  'Achats de médicaments',     6, 'debit'),
('603',   'Variation des stocks',      6, 'debit'),
('6031',  'Variation stocks marchandises', 6, 'debit'),
('611',   'Transports sur achats',     6, 'debit'),
('623',   'Publicité et publications', 6, 'debit'),
('625',   'Déplacements',              6, 'debit'),
('626',   'Frais postaux',             6, 'debit'),
('627',   'Services bancaires',        6, 'debit'),
('641',   'Salaires',                  6, 'debit'),
('645',   'Charges sociales',          6, 'debit'),
('658',   'Charges diverses',          6, 'debit'),
('681',   'Dotations aux amortissements', 6, 'debit'),
('695',   'Impôts et taxes',           6, 'debit');

-- Classe 7 : Produits
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('7',     'Comptes de produits',       7, 'credit'),
('701',   'Ventes de marchandises',    7, 'credit'),
('7011',  'Ventes de médicaments',     7, 'credit'),
('708',   'Produits des activités annexes', 7, 'credit'),
('751',   'Produits financiers',       7, 'credit'),
('758',   'Produits divers',           7, 'credit'),
('771',   'Produits exceptionnels',    7, 'credit');

-- ── Exercice comptable 2026 ───────────────────────────────
INSERT INTO exercices (code, libelle, date_debut, date_fin) VALUES
('2026', 'Exercice 2026', '2026-01-01', '2026-12-31');

-- ── Produits ──────────────────────────────────────────────
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

-- ── Commandes ─────────────────────────────────────────────
INSERT INTO commandes (reference, fournisseur_id, utilisateur_id, statut, date_commande, date_livraison) VALUES
('CMD-2026-001', 1, 1, 'livrée',    '2026-04-01', '2026-04-04'),
('CMD-2026-002', 2, 2, 'en_cours',  '2026-04-08', NULL),
('CMD-2026-003', 3, 1, 'en_attente','2026-04-10', NULL);

-- ── Ventes de démo (TVA 19.25%) ───────────────────────────
INSERT INTO ventes (reference, client_nom, caissier_id, sous_total, tva_total, total, mode_paiement, montant_recu, monnaie, created_at) VALUES
('VNT-2026-001', 'Fatima Bouhel', 3, 383.00, 73.73, 456.73, 'espèces',   500.00, 43.27, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('VNT-2026-002', 'Ahmed Kouachi', 3, 625.00, 120.31, 745.31, 'carte',     745.31,  0.00, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('VNT-2026-003', '',              2, 130.00, 25.03,  155.03, 'espèces',   200.00, 44.97, DATE_SUB(NOW(), INTERVAL 6 HOUR));

INSERT INTO vente_lignes (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne) VALUES
(1, 1,  'Paracétamol 1g',     2, 75.00,  19.25, 150.00),
(1, 9,  'Vitamine C 1000mg',  3, 55.00,  19.25, 165.00),
(1, 10, 'Smecta sachet x10',  1, 68.00,  19.25, 68.00),
(2, 6,  'Augmentin 1g',       1, 520.00, 19.25, 520.00),
(2, 11, 'Doliprane 500mg',    1, 60.00,  19.25, 60.00),
(2, 14, 'Aspirine 500mg',     1, 45.00,  19.25, 45.00),
(3, 7,  'Metformine 500mg',   1, 130.00, 19.25, 130.00);
