-- ================================================================
-- GESTTRANS PRO — Base de données MySQL
-- Refonte web de l'application Access GESTRANS — DANAY EXPRESS SARL
-- ================================================================
CREATE DATABASE IF NOT EXISTS gesttrans CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gesttrans;

-- ── AGENCES ──────────────────────────────────────────────────
CREATE TABLE agences (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(10) NOT NULL UNIQUE,
    nom           VARCHAR(150) NOT NULL,
    ville         VARCHAR(100) NOT NULL,
    region        VARCHAR(100),
    adresse       TEXT,
    code_postal   VARCHAR(20),
    telephone     VARCHAR(30),
    email         VARCHAR(150),
    type_agence   ENUM('Terminus','Escale','Catégorie A','Catégorie B','Catégorie C','Prestige','Direction','Partenaire') DEFAULT 'Terminus',
    nom_contact   VARCHAR(150),
    titre_contact VARCHAR(100),
    adresse_courrier TEXT,
    actif         TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── GROUPES (ACTIONNAIRES) ─────────────────────────────────────
CREATE TABLE groupes (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(10) NOT NULL UNIQUE,
    nom                   VARCHAR(150) NOT NULL,
    nom_contact           VARCHAR(150),
    titre_contact         VARCHAR(100),
    ville                 VARCHAR(100),
    adresse               TEXT,
    telephone             VARCHAR(30),
    conditions_paiement   VARCHAR(100),
    num_compte_bancaire   VARCHAR(80),
    nom_titulaire_compte  VARCHAR(150),
    banque                VARCHAR(100),
    mode_paiement         ENUM('Virement','Espèces','Chèque','Mobile Money') DEFAULT 'Virement',
    remarques             TEXT,
    actif                 TINYINT(1) DEFAULT 1,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── VEHICULES ─────────────────────────────────────────────────
CREATE TABLE vehicules (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    immatriculation       VARCHAR(20) NOT NULL UNIQUE,
    marque                VARCHAR(80),
    modele                VARCHAR(80),
    description           VARCHAR(200),
    groupe_id             INT,
    date_acquisition      DATE,
    concessionnaire       VARCHAR(150),
    prochaine_maintenance DATE,
    assurance_fin         DATE,
    visite_fin            DATE,
    capacite              INT DEFAULT 70,
    actif                 TINYINT(1) DEFAULT 1,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (groupe_id) REFERENCES groupes(id) ON DELETE SET NULL
);

-- ── PERSONNEL ─────────────────────────────────────────────────
CREATE TABLE personnel (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    ref_employe          VARCHAR(20) UNIQUE,
    nom                  VARCHAR(100) NOT NULL,
    prenom               VARCHAR(100),
    titre                VARCHAR(100),
    adresse              TEXT,
    ville                VARCHAR(100),
    departement          VARCHAR(100),
    telephone            VARCHAR(20),
    agence_id            INT,
    indice_salaire       DECIMAL(12,2) DEFAULT 0,
    actif                TINYINT(1) DEFAULT 1,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE SET NULL
);

-- ── BORDEREAUX ─────────────────────────────────────────────────
CREATE TABLE bordereaux (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    num_bordereau       INT,
    code_bordereau      VARCHAR(30),
    vehicule_id         INT,
    date                DATE NOT NULL,
    agence_depart_id    INT,
    agence_arrivee_id   INT,
    nb_passagers        INT DEFAULT 0,
    nb_billets_gratuits INT DEFAULT 0,
    recette_totale      DECIMAL(12,2) DEFAULT 0,
    carburant           DECIMAL(12,2) DEFAULT 0,
    peage_total         DECIMAL(12,2) DEFAULT 0,
    retenue_agence      DECIMAL(12,2) DEFAULT 0,
    ration_chauffeur    DECIMAL(12,2) DEFAULT 0,
    autres_depenses     DECIMAL(12,2) DEFAULT 0,
    recette_nette       DECIMAL(12,2) GENERATED ALWAYS AS (recette_totale - carburant - peage_total - retenue_agence - ration_chauffeur - autres_depenses) STORED,
    libelle             TEXT,
    saisie_par          INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicule_id)       REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_depart_id)  REFERENCES agences(id)   ON DELETE SET NULL,
    FOREIGN KEY (agence_arrivee_id) REFERENCES agences(id)   ON DELETE SET NULL,
    -- FK saisie_par added via ALTER below
);

-- ── VERSEMENTS BANCAIRES ───────────────────────────────────────
CREATE TABLE versements (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    ref_versement       VARCHAR(30),
    agence_id           INT NOT NULL,
    date                DATE NOT NULL,
    versement_agence    DECIMAL(12,2) DEFAULT 0,
    decaissement_agence DECIMAL(12,2) DEFAULT 0,
    recette_agence      DECIMAL(12,2) DEFAULT 0,
    quittance           VARCHAR(100),
    statut              ENUM('en_attente','confirme','rejete') DEFAULT 'en_attente',
    saisie_par          INT,
    confirme_par        INT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id)    REFERENCES agences(id) ON DELETE CASCADE,
    FOREIGN KEY (saisie_par)   REFERENCES users(id)   ON DELETE SET NULL,
    FOREIGN KEY (confirme_par) REFERENCES users(id)   ON DELETE SET NULL
);

-- ── DÉPENSES ──────────────────────────────────────────────────
CREATE TABLE depenses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ref_depense   VARCHAR(30),
    agence_id     INT NOT NULL,
    employe_id    INT,
    groupe_id     INT,
    type_depense  ENUM('Salaire','Fournitures','Aménagements','Impôts','Fonctionnements','Avance sur salaire','Téléphone','Dépense Direction','Investissement','Transit','SMS','Location VHL','Reliquat','Loyer Agence','Bon Actionnaire','Autres dépenses') DEFAULT 'Autres dépenses',
    objet         TEXT NOT NULL,
    montant       DECIMAL(12,2) NOT NULL,
    date_depense  DATE NOT NULL,
    mode_paiement ENUM('Espèces','Chèque','Virement','Mobile Money') DEFAULT 'Espèces',
    statut        ENUM('en_attente','approuve','rejete','paye') DEFAULT 'en_attente',
    approuve_par  INT NULL,
    saisie_par    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id)    REFERENCES agences(id)   ON DELETE CASCADE,
    FOREIGN KEY (employe_id)   REFERENCES personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (groupe_id)    REFERENCES groupes(id)   ON DELETE SET NULL,
    FOREIGN KEY (approuve_par) REFERENCES users(id)     ON DELETE SET NULL,
    FOREIGN KEY (saisie_par)   REFERENCES users(id)     ON DELETE SET NULL
);

-- ── BONS ACTIONNAIRES ─────────────────────────────────────────
CREATE TABLE bons_actionnaires (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    groupe_id            INT NOT NULL,
    employe_id           INT,
    nom_payeur           VARCHAR(150),
    date_paiement        DATE,
    montant              DECIMAL(12,2) NOT NULL,
    mode_paiement        ENUM('Espèces','Chèque','Virement','Mobile Money') DEFAULT 'Virement',
    date_expir_delai     DATE,
    statut               ENUM('en_attente','paye','expire') DEFAULT 'en_attente',
    agence_id            INT,
    saisie_par           INT,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (groupe_id)  REFERENCES groupes(id)   ON DELETE CASCADE,
    FOREIGN KEY (employe_id) REFERENCES personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_id)  REFERENCES agences(id)   ON DELETE SET NULL,
    FOREIGN KEY (saisie_par) REFERENCES users(id)     ON DELETE SET NULL
);

-- ── ACOMPTES (AVANCES SUR SALAIRES) ───────────────────────────
CREATE TABLE acomptes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    employe_id INT NOT NULL,
    agence_id  INT,
    montant    DECIMAL(12,2) NOT NULL,
    date_acompte DATE NOT NULL,
    motif      TEXT,
    rembourse  TINYINT(1) DEFAULT 0,
    date_remboursement DATE NULL,
    saisie_par INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employe_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (agence_id)  REFERENCES agences(id)   ON DELETE SET NULL,
    FOREIGN KEY (saisie_par) REFERENCES users(id)     ON DELETE SET NULL
);

-- ── RÔLES & PERMISSIONS ────────────────────────────────────────
CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,
    nom         VARCHAR(100) NOT NULL,
    description TEXT,
    couleur     VARCHAR(7) DEFAULT '#1e3a8a',
    niveau      INT DEFAULT 1
);

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100),
    email         VARCHAR(150),
    telephone     VARCHAR(20),
    role_id       INT NOT NULL,
    agence_id     INT,
    avatar_color  VARCHAR(7) DEFAULT '#1e3a8a',
    actif         TINYINT(1) DEFAULT 1,
    last_login    TIMESTAMP NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id)   REFERENCES roles(id),
    FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE SET NULL
);

CREATE TABLE permissions (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    code   VARCHAR(80) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL,
    nom    VARCHAR(150) NOT NULL
);

CREATE TABLE role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ── LOGS ──────────────────────────────────────────────────────
CREATE TABLE logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(100),
    module     VARCHAR(50),
    details    TEXT,
    ip         VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── PARAMÈTRES ────────────────────────────────────────────────
CREATE TABLE parametres (
    cle        VARCHAR(100) PRIMARY KEY,
    valeur     TEXT,
    description VARCHAR(300)
);

-- ================================================================
-- DONNÉES INITIALES
-- ================================================================

-- Rôles
INSERT INTO roles (code, nom, description, couleur, niveau) VALUES
('super_admin', 'Super Administrateur', 'Accès total — maintenance et configuration', '#dc2626', 6),
('admin',       'Administrateur/Directeur', 'Synthèse globale, rapports direction', '#7c3aed', 5),
('chef_agence', 'Chef d\'Agence', 'Gestion agence, dépenses, versements', '#1e40af', 4),
('comptable',   'Comptable', 'Rapprochement bancaire, bons, acomptes', '#0891b2', 3),
('operateur',   'Opérateur de Saisie', 'Saisie bordereaux, dépenses, versements', '#16a34a', 2),
('consultant',  'Consultant', 'Consultation rapports uniquement', '#6b7280', 1);

-- Permissions
INSERT INTO permissions (code, module, nom) VALUES
('bordereaux.view',   'bordereaux', 'Voir les bordereaux'),
('bordereaux.create', 'bordereaux', 'Créer des bordereaux'),
('bordereaux.edit',   'bordereaux', 'Modifier les bordereaux'),
('bordereaux.delete', 'bordereaux', 'Supprimer des bordereaux'),
('versements.view',   'versements', 'Voir les versements'),
('versements.create', 'versements', 'Créer des versements'),
('versements.confirm','versements', 'Confirmer des versements'),
('depenses.view',     'depenses',   'Voir les dépenses'),
('depenses.create',   'depenses',   'Créer des dépenses'),
('depenses.approve',  'depenses',   'Approuver les dépenses'),
('rapports.agence',   'rapports',   'Rapports agence'),
('rapports.direction','rapports',   'Rapports direction'),
('agences.view',      'agences',    'Voir les agences'),
('agences.manage',    'agences',    'Gérer les agences'),
('groupes.view',      'groupes',    'Voir les groupes'),
('groupes.manage',    'groupes',    'Gérer les groupes'),
('vehicules.view',    'vehicules',  'Voir les véhicules'),
('vehicules.manage',  'vehicules',  'Gérer les véhicules'),
('personnel.view',    'personnel',  'Voir le personnel'),
('personnel.manage',  'personnel',  'Gérer le personnel'),
('bons.view',         'bons',       'Voir les bons actionnaires'),
('bons.manage',       'bons',       'Gérer les bons actionnaires'),
('acomptes.manage',   'acomptes',   'Gérer les acomptes'),
('users.manage',      'users',      'Gérer les utilisateurs'),
('export.access',     'export',     'Exporter les données'),
('parametres.manage', 'parametres', 'Gérer les paramètres'),
('dashboard.view',    'dashboard',  'Tableau de bord');

-- super_admin = tout
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
-- admin
INSERT INTO role_permissions (role_id, permission_id) SELECT 2, id FROM permissions WHERE code NOT IN ('parametres.manage','users.manage');
INSERT INTO role_permissions (role_id, permission_id) SELECT 2, id FROM permissions WHERE code IN ('users.manage');
-- chef_agence
INSERT INTO role_permissions (role_id, permission_id) SELECT 3, id FROM permissions WHERE code IN ('bordereaux.view','bordereaux.create','bordereaux.edit','versements.view','versements.create','versements.confirm','depenses.view','depenses.create','depenses.approve','rapports.agence','agences.view','groupes.view','vehicules.view','vehicules.manage','personnel.view','personnel.manage','bons.view','acomptes.manage','dashboard.view','export.access');
-- comptable
INSERT INTO role_permissions (role_id, permission_id) SELECT 4, id FROM permissions WHERE code IN ('bordereaux.view','versements.view','versements.create','versements.confirm','depenses.view','depenses.create','depenses.approve','rapports.agence','rapports.direction','agences.view','groupes.view','vehicules.view','personnel.view','bons.view','bons.manage','acomptes.manage','dashboard.view','export.access');
-- opérateur
INSERT INTO role_permissions (role_id, permission_id) SELECT 5, id FROM permissions WHERE code IN ('bordereaux.view','bordereaux.create','bordereaux.edit','versements.view','versements.create','depenses.view','depenses.create','rapports.agence','agences.view','groupes.view','vehicules.view','personnel.view','dashboard.view');
-- consultant
INSERT INTO role_permissions (role_id, permission_id) SELECT 6, id FROM permissions WHERE code IN ('bordereaux.view','versements.view','depenses.view','rapports.agence','rapports.direction','agences.view','groupes.view','vehicules.view','personnel.view','bons.view','dashboard.view','export.access');

-- Users (password: password)
INSERT INTO users (username, password, nom, prenom, role_id, agence_id, avatar_color) VALUES
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'Système', 1, NULL, '#dc2626'),
('directeur',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mana', 'Yaouba', 2, NULL, '#7c3aed'),
('chef_gb',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bouba', 'Hamadou', 3, 1, '#1e40af'),
('comptable1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Halilou', 'Oumarou', 4, NULL, '#0891b2'),
('operateur1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Saidou', 'Ibrahim', 5, 1, '#16a34a');

-- Agences (données réelles extraites de l'Access)
INSERT INTO agences (code, nom, ville, region, telephone, email, type_agence, nom_contact) VALUES
('DR001', 'Direction Générale',    'Yagoua',       'Mayo-Danay',  '578 44 01', 'danay-express@Laposte.net', 'Direction',    'Yaouba Mana'),
('YA001', 'Agence de Yagoua',      'Yagoua',       'Mayo-Danay',  '578 44 01', 'ag-ngaoundere@laposte.net', 'Prestige',     'Responsable'),
('GA001', 'Agence de Garoua',      'Garoua',       'Bénoué',      '726 35 13', 'ag-garoua@laposte.net',     'Catégorie A',  'Chef Agence'),
('GB001', 'Agence Garoua Boulaï',  'Garoua Boulaï','Djérem',      '',          '',                          'Escale',       'Chef Agence'),
('GR003', 'Agence Garoua II',      'Garoua',       'Bénoué',      '242846271', 'ag-garoua2@laposte.net',    'Catégorie A',  'Chef Agence'),
('MA001', 'Agence Maroua I',       'Maroua',       'Diamaré',     '578 44 02', 'ag-maroua@laposte.net',     'Catégorie A',  'Chef Agence'),
('MA002', 'Agence Maroua II',      'Maroua',       'Diamaré',     '578 44 02', 'ag-maroua2@laposte.net',    'Escale',       'Chef Agence'),
('NG001', 'Agence Ngaoundéré I',   'Ngaoundéré',   'Vina',        '578 44 03', '',                          'Catégorie A',  'Chef Agence'),
('NG002', 'Agence Ngaoundéré II',  'Ngaoundéré',   'Vina',        '578 44 04', '',                          'Prestige',     'Chef Agence'),
('KA001', 'Agence Kaélé',          'Kaélé',        'Mayo-Kani',   '578 44 05', 'ag-kaele@laposte.net',      'Prestige',     'Chef Agence'),
('KA002', 'Agence Guidiguis',      'Guidiguis',     'Mayo-Kani',   '578 44 06', 'ag-guidiguis@laposte.net',  'Terminus',     'Chef Agence'),
('KA003', 'Agence Kalfou',         'Kalfou',        'Mayo-Danay',  '',          '',                          'Catégorie B',  'Chef Agence'),
('KO001', 'Agence Kousseri',       'Kousseri',      'Logone et Chari','',       'ag-kouseri@laposte.net',    'Catégorie A',  'Chef Agence'),
('MO010', 'Agence Mokolo',         'Mokolo',        'Mayo-Tsanaga','33150434',  'ag-mokolo@laposte.net',     'Terminus',     'Chef Agence'),
('GD001', 'Agence Guider',         'Guider',        'Mayo Louti',  '77119272',  '',                          'Terminus',     'Chef Agence'),
('TO001', 'Agence Touboro',        'Touboro',       'Mayo Rey',    '',          'ag-touboro@laposte.net',     'Terminus',     'Chef Agence'),
('DL001', 'Agence Douala',         'Douala',        'Littoral',    '699669490', 'Danay-express@yahoo.fr',    'Terminus',     'Chef Agence'),
('YD001', 'Agence Yaoundé',        'Yaoundé',       'Mfoundi',     '337276 65', 'ag-yaounde@laposte.net',    'Catégorie A',  'Chef Agence'),
('YD002', 'Agence Bafoussam',      'Bafoussam',     'Mfoundi',     '337276 65', '',                          'Terminus',     'Chef Agence'),
('MB001', 'Agence Bertoua',        'Bertoua',       'Lom-et-Djérem','',         '',                          'Catégorie B',  'Chef Agence'),
('GR011', 'ETS ABW Garoua',        'Garoua',        'Bénoué',      '',          '',                          'Partenaire',   'Partenaire'),
('GR002', 'Yaou Auto Garoua',      'Garoua',        'Bénoué',      '',          '',                          'Partenaire',   'Partenaire');

-- Groupes d'actionnaires (données réelles)
INSERT INTO groupes (code, nom, nom_contact, titre_contact, ville, conditions_paiement, num_compte_bancaire, nom_titulaire_compte, banque, mode_paiement) VALUES
('AO001', 'GROUPE 1',     'Ahmat Oumar',    'DAF',                'Maroua',  'Virement', 'SGBC 0508020234-09',       'Ahmat oumar',       'SGBC',              'Virement'),
('AO002', 'GROUPE 1 A',   'Ahmat Oumar',    'DAF',                'Maroua',  'Virement', '',                         'Ahmat oumar',       'SGBC',              'Virement'),
('AO003', 'GROUPE 1 B',   'Ahmat Oumar',    'DAF',                'Maroua',  'Virement', 'SGBC 0508020234-09',       'Ahmat oumar',       'SGBC',              'Virement'),
('AO004', 'GROUPE 1 C',   'Ahmat Oumar',    'DAF',                'Maroua',  'Virement', '',                         'Ahmat oumar',       'SGBC',              'Virement'),
('AO005', 'GROUPE 1 D',   'Ahmat Oumar',    'DAF',                'Maroua',  'Virement', 'SGBC 0508020234-09',       'Ahmat oumar',       'SGBC',              'Virement'),
('AO006', 'GROUPE 1 E',   'Ahmat Oumar',    'DAF',                'Maroua',  'Virement', '',                         'Ahmat oumar',       'SGBC',              'Virement'),
('YA001', 'GROUPE 2',     'Yaouba Mana',    'Directeur Général',  'Yagoua',  'Virement', 'SGBC 06070019452-36',      'Yaouba mana',       'SGBC',              'Virement'),
('YA002', 'GROUPE 2 A',   'Yaouba Mana',    'Directeur Général',  'Yagoua',  'Virement', '',                         'Yaouba mana',       'SGBC',              'Virement'),
('OU001', 'GROUPE 3',     'Oumarou Halilou','Chef du Personnel',  'Yagoua',  'Virement', '',                         'Oumarou halilou',   '',                  'Virement'),
('OU006', 'GROUPE 3 B',   'Oumarou Halilou','Chef Personnel',     'Yagoua',  'Virement', '',                         'Oumarou halilou',   '',                  'Virement'),
('OU008', 'GROUPE 3 C',   'Oumarou Halilou','Chef Personnel',     'Yagoua',  'Virement', '',                         'Oumarou halilou',   '',                  'Virement'),
('HA001', 'GROUPE 4',     'Hamadou',        'PCA',                'Yagoua',  'Virement', 'SC10003 00200 050201528',  'Ahdji aminou',      '',                  'Virement'),
('HA004', 'GROUPE 4 C',   'Hamadou',        'PCA',                'Yagoua',  'Virement', '',                         'Hamadou',           '',                  'Virement'),
('HA008', 'GROUPE 4 F',   'Hamadou',        'Administrateur',     'Yaoundé', 'Virement', '',                         'Copres',            'AFRILAND FIRST BANK','Virement'),
('SA001', 'GROUPE 6',     'Sali Adama',     '',                   'Yagoua',  'Virement', '',                         'Sali adama',        '',                  'Virement'),
('OU002', 'GROUPE 9',     'Mme Oumarou',    '',                   'Garoua',  'Virement', '',                         'Mme oumarou',       '',                  'Virement'),
('BO001', 'GROUPE 10',    'Bouba Wala',     '',                   'Garoua',  'Virement', 'ECOBANK 01011702012-53',   'Bouba wala',        'ECOBANK',           'Virement'),
('IS001', 'GROUPE 11',    'Ismaila',        '',                   'Yagoua',  'Virement', '',                         'Ismaila bouba',     '',                  'Virement'),
('PT001', 'GROUPE 12',    'SOTCOCOB',       '',                   'Bongor',  'Virement', '',                         '',                  'SCBA',              'Virement'),
('SC001', 'GROUPE 13',    'Bouba Wala Ibe', '',                   'Garoua',  'Virement', '',                         'Bouba walaibe',     '',                  'Virement'),
('HS001', 'GROUPE 14',    'Hamadou Saidou', 'Partenaire',         'Guider',  'Virement', 'SGBC N°...',               'Hamadou saidou',    'SGBC',              'Virement'),
('MB001', 'GROUPE 15',    'Mohamadou Bouba','Partenaire',         'Bongor',  'Virement', '',                         'Bouba wala',        '',                  'Virement');

-- Véhicules démo (immatriculations réelles vues dans les données)
INSERT INTO vehicules (immatriculation, groupe_id, actif) VALUES
('LT 264 MFl', 1, 1),
('LT141KDn',   1, 1),
('LT685 HYl',  2, 1),
('LT0794LQl',  2, 1),
('LT0943LFi',  3, 1),
('LT0771KCo',  3, 1),
('LT1234 AB',  4, 1),
('LT5678 CD',  4, 1);

-- Personnel démo
INSERT INTO personnel (ref_employe, nom, prenom, titre, agence_id) VALUES
('EMP001', 'Mana',    'Yaouba',   'Directeur Général', NULL),
('EMP002', 'Halilou', 'Oumarou',  'Chef du Personnel', NULL),
('EMP003', 'Adama',   'Sali',     'Comptable',         1),
('EMP004', 'Bouba',   'Hamadou',  'Chef d''Agence',     1),
('EMP005', 'Ibrahim', 'Saidou',   'Opérateur',         1);

-- Bordereaux démo
INSERT INTO bordereaux (num_bordereau, code_bordereau, vehicule_id, date, agence_depart_id, agence_arrivee_id, nb_passagers, nb_billets_gratuits, recette_totale, carburant, peage_total, retenue_agence, ration_chauffeur, autres_depenses, saisie_par) VALUES
(1001, 'GB-2023-1001', 1, CURDATE()-INTERVAL 3 DAY, 4, 1, 45, 2, 225000, 35000, 12000, 22500, 15000, 5000, 5),
(1002, 'GB-2023-1002', 2, CURDATE()-INTERVAL 2 DAY, 4, 1, 52, 1, 260000, 38000, 12000, 26000, 15000, 8000, 5),
(1003, 'GB-2023-1003', 3, CURDATE()-INTERVAL 2 DAY, 1, 4, 38, 3, 190000, 32000, 10000, 19000, 15000, 4000, 5),
(1004, 'GB-2023-1004', 4, CURDATE()-INTERVAL 1 DAY, 4, 3, 61, 0, 305000, 42000, 15000, 30500, 15000, 7000, 5),
(1005, 'GB-2023-1005', 1, CURDATE(), 4, 1, 48, 2, 240000, 36000, 12000, 24000, 15000, 6000, 5),
(1006, 'GB-2023-1006', 5, CURDATE(), 1, 9, 55, 1, 275000, 40000, 18000, 27500, 15000, 9000, 5);

-- Versements démo
INSERT INTO versements (ref_versement, agence_id, date, versement_agence, decaissement_agence, recette_agence, quittance, statut, saisie_par) VALUES
('VER-001', 4, CURDATE()-INTERVAL 3 DAY, 135500, 0, 22500, 'QTT-001', 'confirme', 5),
('VER-002', 4, CURDATE()-INTERVAL 2 DAY, 166000, 0, 26000, 'QTT-002', 'confirme', 5),
('VER-003', 4, CURDATE()-INTERVAL 1 DAY, 212500, 0, 30500, 'QTT-003', 'en_attente', 5),
('VER-004', 4, CURDATE(),               162000, 0, 24000, 'QTT-004', 'en_attente', 5);

-- Dépenses démo
INSERT INTO depenses (ref_depense, agence_id, employe_id, type_depense, objet, montant, date_depense, mode_paiement, statut, saisie_par) VALUES
('DEP-001', 4, 4, 'Fonctionnements',      'Fournitures de bureau',       15000, CURDATE()-INTERVAL 5 DAY, 'Espèces',  'approuve', 5),
('DEP-002', 4, 4, 'Téléphone',            'Recharge crédit téléphone',    5000, CURDATE()-INTERVAL 4 DAY, 'Espèces',  'approuve', 5),
('DEP-003', 4, 4, 'Loyer Agence',         'Loyer mensuel Garoua Boulaï', 85000, CURDATE()-INTERVAL 3 DAY, 'Virement', 'approuve', 5),
('DEP-004', 4, 5, 'Avance sur salaire',   'Avance Ibrahim',              30000, CURDATE()-INTERVAL 2 DAY, 'Espèces',  'approuve', 5),
('DEP-005', 4, 4, 'Autres dépenses',      'Nettoyage véhicule LT685',     8000, CURDATE()-INTERVAL 1 DAY, 'Espèces',  'en_attente', 5);

-- Paramètres
INSERT INTO parametres (cle, valeur, description) VALUES
('nom_entreprise',  'DANAY EXPRESS SARL',        'Raison sociale'),
('slogan',          'Votre confort, notre fierté', 'Slogan entreprise'),
('adresse_siege',   'B.P. 178 Yagoua, Cameroun',  'Adresse siège'),
('telephone_siege', '+237 578 44 01',              'Téléphone siège'),
('email',           'danay-express@laposte.net',   'Email'),
('monnaie',         'FCFA',                        'Monnaie'),
('logo',            '',                            'Logo entreprise'),
('couleur_primaire','#1e40af',                     'Couleur principale interface');

-- Add FK for bordereaux.saisie_par after users table is created
ALTER TABLE bordereaux ADD CONSTRAINT fk_brd_saisie FOREIGN KEY (saisie_par) REFERENCES users(id) ON DELETE SET NULL;
