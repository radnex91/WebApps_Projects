-- ================================================================
-- TECHSCHOOL — Base de données établissement technique
-- Première Année → Terminale | Gestion complète + Bulletins
-- ================================================================
CREATE DATABASE IF NOT EXISTS techschool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE techschool;

-- ── ANNÉES SCOLAIRES ─────────────────────────────────────────
CREATE TABLE annees_scolaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(20) NOT NULL UNIQUE,
    date_debut DATE NOT NULL,
    date_fin   DATE NOT NULL,
    active     TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── FILIÈRES / SPÉCIALITÉS ────────────────────────────────────
CREATE TABLE filieres (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    nom  VARCHAR(150) NOT NULL,
    description TEXT,
    couleur VARCHAR(7) DEFAULT '#2563eb',
    actif TINYINT(1) DEFAULT 1
);

-- ── NIVEAUX ───────────────────────────────────────────────────
CREATE TABLE niveaux (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    code   VARCHAR(20) NOT NULL UNIQUE,
    nom    VARCHAR(100) NOT NULL,
    ordre  INT NOT NULL,
    cycle  ENUM('Secondaire','BTS','Autre') DEFAULT 'Secondaire'
);

-- ── CLASSES ───────────────────────────────────────────────────
CREATE TABLE classes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(80) NOT NULL,
    niveau_id   INT NOT NULL,
    filiere_id  INT NOT NULL,
    annee_id    INT NOT NULL,
    capacite    INT DEFAULT 35,
    salle       VARCHAR(50),
    FOREIGN KEY (niveau_id)  REFERENCES niveaux(id),
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (annee_id)   REFERENCES annees_scolaires(id) ON DELETE CASCADE
);

-- ── PARENTS / TUTEURS ────────────────────────────────────────
CREATE TABLE parents (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    prenom     VARCHAR(100) NOT NULL,
    telephone  VARCHAR(20),
    email      VARCHAR(150),
    adresse    TEXT,
    profession VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── ÉLÈVES ───────────────────────────────────────────────────
CREATE TABLE eleves (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    matricule       VARCHAR(20) NOT NULL UNIQUE,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    date_naissance  DATE,
    lieu_naissance  VARCHAR(100),
    sexe            ENUM('M','F') NOT NULL DEFAULT 'M',
    nationalite     VARCHAR(80) DEFAULT 'Camerounaise',
    adresse         TEXT,
    telephone       VARCHAR(20),
    email           VARCHAR(150),
    photo           VARCHAR(255),
    parent_id       INT,
    statut          ENUM('actif','inactif','transfere','diplome') DEFAULT 'actif',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL
);

-- ── INSCRIPTIONS ─────────────────────────────────────────────
CREATE TABLE inscriptions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id        INT NOT NULL,
    classe_id       INT NOT NULL,
    annee_id        INT NOT NULL,
    date_inscription DATE DEFAULT (CURRENT_DATE),
    frais_scolarite  DECIMAL(12,2) DEFAULT 0,
    statut          ENUM('inscrit','actif','abandon') DEFAULT 'actif',
    UNIQUE KEY uk_eleve_annee (eleve_id, annee_id),
    FOREIGN KEY (eleve_id)  REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id)  REFERENCES annees_scolaires(id)
);

-- ── ENSEIGNANTS ───────────────────────────────────────────────
CREATE TABLE enseignants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    matricule     VARCHAR(20) NOT NULL UNIQUE,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    date_naissance DATE,
    sexe          ENUM('M','F') DEFAULT 'M',
    telephone     VARCHAR(20),
    email         VARCHAR(150),
    adresse       TEXT,
    specialite    VARCHAR(150),
    diplome       VARCHAR(200),
    grade         VARCHAR(100),
    date_embauche DATE,
    photo         VARCHAR(255),
    statut        ENUM('actif','inactif') DEFAULT 'actif',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── MATIÈRES ─────────────────────────────────────────────────
CREATE TABLE matieres (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20) NOT NULL UNIQUE,
    nom         VARCHAR(150) NOT NULL,
    type        ENUM('generale','technique','pratique','option') DEFAULT 'generale',
    description TEXT,
    actif       TINYINT(1) DEFAULT 1
);

-- ── COEFFICIENTS PAR MATIÈRE/NIVEAU/FILIÈRE ──────────────────
CREATE TABLE matiere_classe (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    matiere_id    INT NOT NULL,
    classe_id     INT NOT NULL,
    enseignant_id INT,
    coefficient   DECIMAL(4,2) DEFAULT 1.00,
    heures_semaine INT DEFAULT 2,
    UNIQUE KEY uk_mat_classe (matiere_id, classe_id),
    FOREIGN KEY (matiere_id)    REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id)     REFERENCES classes(id)  ON DELETE CASCADE,
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE SET NULL
);

-- ── PÉRIODES / SÉQUENCES ─────────────────────────────────────
CREATE TABLE periodes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    annee_id   INT NOT NULL,
    nom        VARCHAR(80) NOT NULL,
    type       ENUM('sequence','trimestre','semestre','annuel') DEFAULT 'sequence',
    ordre      INT DEFAULT 1,
    date_debut DATE,
    date_fin   DATE,
    cloturee   TINYINT(1) DEFAULT 0,
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id) ON DELETE CASCADE
);

-- ── NOTES ────────────────────────────────────────────────────
CREATE TABLE notes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id      INT NOT NULL,
    matiere_id    INT NOT NULL,
    classe_id     INT NOT NULL,
    periode_id    INT NOT NULL,
    annee_id      INT NOT NULL,
    note          DECIMAL(5,2) NOT NULL,
    note_max      DECIMAL(5,2) DEFAULT 20.00,
    type_eval     ENUM('devoir1','devoir2','composition','examen','tp','oral','projet') DEFAULT 'devoir1',
    date_eval     DATE DEFAULT (CURRENT_DATE),
    observation   TEXT,
    saisie_par    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_note (eleve_id, matiere_id, periode_id, type_eval),
    FOREIGN KEY (eleve_id)   REFERENCES eleves(id)   ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id)  REFERENCES classes(id),
    FOREIGN KEY (periode_id) REFERENCES periodes(id),
    FOREIGN KEY (annee_id)   REFERENCES annees_scolaires(id)
);

-- ── ABSENCES ─────────────────────────────────────────────────
CREATE TABLE absences (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id     INT NOT NULL,
    annee_id     INT NOT NULL,
    date_absence DATE NOT NULL,
    nb_heures    INT DEFAULT 1,
    motif        TEXT,
    justifie     TINYINT(1) DEFAULT 0,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- ── PAIEMENTS ────────────────────────────────────────────────
CREATE TABLE paiements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    inscription_id  INT NOT NULL,
    montant         DECIMAL(12,2) NOT NULL,
    date_paiement   DATE DEFAULT (CURRENT_DATE),
    mode            ENUM('especes','cheque','virement','mobile_money','autre') DEFAULT 'especes',
    reference       VARCHAR(100),
    observation     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inscription_id) REFERENCES inscriptions(id) ON DELETE CASCADE
);

-- ── UTILISATEURS & RÔLES ─────────────────────────────────────
CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,
    nom         VARCHAR(100) NOT NULL,
    description TEXT,
    couleur     VARCHAR(7) DEFAULT '#2563eb'
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
    enseignant_id INT,
    avatar_color  VARCHAR(7) DEFAULT '#2563eb',
    actif         TINYINT(1) DEFAULT 1,
    last_login    TIMESTAMP NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id)       REFERENCES roles(id),
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE SET NULL
);

-- ── PERMISSIONS ───────────────────────────────────────────────
CREATE TABLE permissions (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(80) NOT NULL UNIQUE,
    module  VARCHAR(50) NOT NULL,
    nom     VARCHAR(150) NOT NULL
);

CREATE TABLE role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ── PERSONNALISATION BULLETIN ─────────────────────────────────
CREATE TABLE bulletin_config (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    annee_id            INT NOT NULL UNIQUE,
    -- En-tête
    nom_etablissement   VARCHAR(200) DEFAULT 'LYCÉE TECHNIQUE',
    sous_titre          VARCHAR(200) DEFAULT 'Établissement Technique d\'Excellence',
    adresse_etab        TEXT,
    telephone_etab      VARCHAR(50),
    email_etab          VARCHAR(150),
    ville               VARCHAR(100) DEFAULT 'Yaoundé',
    pays                VARCHAR(80) DEFAULT 'Cameroun',
    logo_path           VARCHAR(255),
    logo2_path          VARCHAR(255),
    -- Textes
    titre_bulletin      VARCHAR(200) DEFAULT 'BULLETIN DE NOTES',
    mention_excellent   VARCHAR(100) DEFAULT 'Très Bien',
    mention_tb          VARCHAR(100) DEFAULT 'Bien',
    mention_b           VARCHAR(100) DEFAULT 'Assez Bien',
    mention_ab          VARCHAR(100) DEFAULT 'Passable',
    mention_insuffisant VARCHAR(100) DEFAULT 'Insuffisant',
    seuil_excellent     DECIMAL(4,2) DEFAULT 16.00,
    seuil_tb            DECIMAL(4,2) DEFAULT 14.00,
    seuil_b             DECIMAL(4,2) DEFAULT 12.00,
    seuil_ab            DECIMAL(4,2) DEFAULT 10.00,
    -- Signatures
    titre_sign1         VARCHAR(100) DEFAULT 'Le Directeur',
    titre_sign2         VARCHAR(100) DEFAULT 'Le Professeur Principal',
    titre_sign3         VARCHAR(100) DEFAULT 'Parent / Tuteur',
    -- Couleurs & Style
    couleur_entete      VARCHAR(7)   DEFAULT '#1e3a5f',
    couleur_accent      VARCHAR(7)   DEFAULT '#2563eb',
    couleur_ligne_pair  VARCHAR(7)   DEFAULT '#f0f7ff',
    police              VARCHAR(50)  DEFAULT 'Times New Roman',
    -- Options
    afficher_rang       TINYINT(1)   DEFAULT 1,
    afficher_absences   TINYINT(1)   DEFAULT 1,
    afficher_appreciations TINYINT(1) DEFAULT 1,
    afficher_conseil    TINYINT(1)   DEFAULT 1,
    afficher_decisions  TINYINT(1)   DEFAULT 1,
    watermark_text      VARCHAR(100) DEFAULT '',
    pied_page           TEXT,
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id) ON DELETE CASCADE
);

-- ── DÉCISIONS CONSEIL DE CLASSE ───────────────────────────────
CREATE TABLE conseils_classe (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id      INT NOT NULL,
    classe_id     INT NOT NULL,
    periode_id    INT NOT NULL,
    annee_id      INT NOT NULL,
    appreciation  TEXT,
    decision      ENUM('passage','redoublement','exclusion','avertissement','felicitations','encouragements','tableauhonneur','') DEFAULT '',
    conseil_date  DATE,
    UNIQUE KEY uk_conseil (eleve_id, periode_id),
    FOREIGN KEY (eleve_id)   REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id)  REFERENCES classes(id),
    FOREIGN KEY (periode_id) REFERENCES periodes(id),
    FOREIGN KEY (annee_id)   REFERENCES annees_scolaires(id)
);

-- ── LOGS SYSTÈME ─────────────────────────────────────────────
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

-- ================================================================
-- DONNÉES INITIALES
-- ================================================================

-- Rôles
INSERT INTO roles (code, nom, description, couleur) VALUES
('super_admin', 'Super Administrateur', 'Accès total au système', '#dc2626'),
('admin',       'Administrateur',       'Gestion complète de l\'établissement', '#7c3aed'),
('directeur',   'Directeur',            'Supervision pédagogique et administrative', '#2563eb'),
('censeur',     'Censeur/Adjoint',      'Gestion pédagogique et discipline', '#0891b2'),
('enseignant',  'Enseignant',           'Saisie des notes, consultation bulletins', '#16a34a'),
('secretaire',  'Secrétaire',           'Inscriptions, bulletins, paiements', '#d97706'),
('comptable',   'Comptable',            'Paiements et états financiers', '#b45309'),
('parent',      'Parent/Tuteur',        'Consultation des résultats de son enfant', '#6b7280');

-- Permissions
INSERT INTO permissions (code, module, nom) VALUES
('eleves.view',        'eleves',      'Voir les élèves'),
('eleves.create',      'eleves',      'Ajouter un élève'),
('eleves.edit',        'eleves',      'Modifier un élève'),
('eleves.delete',      'eleves',      'Supprimer un élève'),
('notes.view',         'notes',       'Voir les notes'),
('notes.create',       'notes',       'Saisir des notes'),
('notes.edit',         'notes',       'Modifier les notes'),
('notes.delete',       'notes',       'Supprimer des notes'),
('bulletins.view',     'bulletins',   'Voir les bulletins'),
('bulletins.print',    'bulletins',   'Imprimer les bulletins'),
('bulletins.config',   'bulletins',   'Configurer les bulletins'),
('classes.manage',     'classes',     'Gérer les classes'),
('enseignants.manage', 'enseignants', 'Gérer les enseignants'),
('users.manage',       'users',       'Gérer les utilisateurs'),
('paiements.view',     'paiements',   'Voir les paiements'),
('paiements.manage',   'paiements',   'Gérer les paiements'),
('rapports.view',      'rapports',    'Voir les rapports'),
('parametres.manage',  'parametres',  'Gérer les paramètres');

-- Permissions par rôle (super_admin = tout)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- admin = tout sauf users.manage système niveau super
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code != 'users.manage' OR 1=1;

-- directeur
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN ('eleves.view','notes.view','bulletins.view','bulletins.print','classes.manage','enseignants.manage','rapports.view','paiements.view');

-- enseignant
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE code IN ('eleves.view','notes.view','notes.create','notes.edit','bulletins.view','bulletins.print');

-- secrétaire
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE code IN ('eleves.view','eleves.create','eleves.edit','bulletins.view','bulletins.print','paiements.view','paiements.manage','rapports.view');

-- Mot de passe: Admin123 (hash bcrypt)
INSERT INTO users (username, password, nom, prenom, email, role_id, avatar_color) VALUES
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'Système', 'admin@techschool.cm', 1, '#dc2626'),
('directeur',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mbarga', 'Paul', 'directeur@techschool.cm', 3, '#2563eb'),
('prof1',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nkemdirim', 'Alice', 'alice@techschool.cm', 5, '#16a34a'),
('secretaire', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fouda', 'Jean', 'secretaire@techschool.cm', 6, '#d97706');

-- Année scolaire
INSERT INTO annees_scolaires (libelle, date_debut, date_fin, active) VALUES
('2024-2025', '2024-09-01', '2025-07-31', 1);

-- Filières techniques
INSERT INTO filieres (code, nom, description, couleur) VALUES
('INFO',   'Informatique',                  'Développement logiciel, réseaux, bases de données', '#2563eb'),
('ELEC',   'Électronique',                  'Circuits, systèmes embarqués, automatisme', '#7c3aed'),
('MECA',   'Mécanique Industrielle',        'Fabrication, maintenance, mécatronique', '#d97706'),
('GENIE_C','Génie Civil',                   'Construction, topographie, dessin technique', '#16a34a'),
('COMPTA', 'Comptabilité & Gestion',        'Finance, comptabilité, management', '#0891b2'),
('ELECM',  'Électromécanique',              'Moteurs, installations électriques', '#dc2626'),
('FROID',  'Froid & Climatisation',         'Réfrigération, CVC, installations', '#6d28d9'),
('AUTO',   'Automobile',                    'Mécanique auto, diagnostic, carrosserie', '#92400e');

-- Niveaux
INSERT INTO niveaux (code, nom, ordre, cycle) VALUES
('1ERE_A', 'Première Année', 1, 'Secondaire'),
('2EME_A', 'Deuxième Année', 2, 'Secondaire'),
('3EME_A', 'Troisième Année', 3, 'Secondaire'),
('4EME_A', 'Quatrième Année', 4, 'Secondaire'),
('TERM',   'Terminale',       5, 'Secondaire'),
('BTS1',   'BTS Première Année', 6, 'BTS'),
('BTS2',   'BTS Deuxième Année', 7, 'BTS');

-- Classes (exemples)
INSERT INTO classes (nom, niveau_id, filiere_id, annee_id, capacite) VALUES
('1ère INFO A', 1, 1, 1, 35),
('2ème INFO A', 2, 1, 1, 32),
('Terminale INFO A', 5, 1, 1, 30),
('1ère ELEC A', 1, 2, 1, 35),
('Terminale MECA A', 5, 3, 1, 28);

-- Matières
INSERT INTO matieres (code, nom, type) VALUES
('MATH',    'Mathématiques',               'generale'),
('PHYS',    'Physique-Chimie',             'generale'),
('FR',      'Français & Expression',       'generale'),
('ANG',     'Anglais Technique',           'generale'),
('HIST',    'Histoire-Géographie',         'generale'),
('EPS',     'Éducation Physique',          'generale'),
('ALGO',    'Algorithmique & Prog.',       'technique'),
('BD',      'Bases de Données',            'technique'),
('RESEAU',  'Réseaux & Télécoms',          'technique'),
('SYS',     'Systèmes d\'Exploitation',    'technique'),
('WEB',     'Développement Web',           'technique'),
('ELEC_G',  'Électronique Générale',       'technique'),
('MECA_G',  'Mécanique Générale',          'technique'),
('DESSIN',  'Dessin Technique',            'technique'),
('TP_INFO', 'TP Informatique',             'pratique'),
('TP_ELEC', 'TP Électronique',             'pratique'),
('TP_MECA', 'TP Mécanique',               'pratique'),
('PROJET',  'Projet de Fin d\'Études',     'pratique'),
('COMPTA_G','Comptabilité Générale',       'technique'),
('GESTION', 'Gestion & Management',        'technique');

-- Matières assignées à la classe 1 (1ère INFO A)
INSERT INTO matiere_classe (matiere_id, classe_id, coefficient, heures_semaine) VALUES
(1, 1, 4, 4),(2, 1, 3, 3),(3, 1, 3, 4),(4, 1, 2, 3),
(5, 1, 1, 2),(6, 1, 1, 2),(7, 1, 4, 4),(8, 1, 3, 3),
(9, 1, 3, 3),(10,1, 3, 3),(11,1, 4, 4),(15,1, 4, 6);

-- Séquences
INSERT INTO periodes (annee_id, nom, type, ordre, date_debut, date_fin) VALUES
(1,'Séquence 1','sequence',1,'2024-09-02','2024-10-25'),
(1,'Séquence 2','sequence',2,'2024-10-28','2024-12-20'),
(1,'Séquence 3','sequence',3,'2025-01-06','2025-02-28'),
(1,'Séquence 4','sequence',4,'2025-03-03','2025-04-25'),
(1,'Séquence 5','sequence',5,'2025-04-28','2025-06-13'),
(1,'Séquence 6','sequence',6,'2025-06-16','2025-07-18'),
(1,'1er Trimestre','trimestre',1,'2024-09-02','2024-12-20'),
(1,'2ème Trimestre','trimestre',2,'2025-01-06','2025-04-25'),
(1,'3ème Trimestre','trimestre',3,'2025-04-28','2025-07-18');

-- Élèves démo
INSERT INTO eleves (matricule, nom, prenom, date_naissance, sexe, statut) VALUES
('2024001','Kamga','Thomas','2006-03-15','M','actif'),
('2024002','Biya','Marie','2006-07-22','F','actif'),
('2024003','Nguemo','Paul','2005-11-08','M','actif'),
('2024004','Ateba','Sarah','2006-01-30','F','actif'),
('2024005','Mbida','Jean','2005-09-12','M','actif'),
('2024006','Fouda','Alice','2006-05-18','F','actif');

-- Inscriptions
INSERT INTO inscriptions (eleve_id, classe_id, annee_id, frais_scolarite) VALUES
(1,1,1,150000),(2,1,1,150000),(3,1,1,150000),
(4,1,1,150000),(5,1,1,150000),(6,1,1,150000);

-- Enseignants démo
INSERT INTO enseignants (matricule, nom, prenom, sexe, telephone, specialite, statut) VALUES
('ENS001','Nkemdirim','Alice','F','+237 677 100 001','Informatique & Réseaux','actif'),
('ENS002','Mbarga','Robert','M','+237 677 100 002','Mathématiques','actif'),
('ENS003','Fouda','Pierre','M','+237 677 100 003','Physique-Chimie','actif'),
('ENS004','Ateba','Marie','F','+237 677 100 004','Français','actif');

-- Notes démo (Séquence 1)
INSERT INTO notes (eleve_id, matiere_id, classe_id, periode_id, annee_id, note, type_eval) VALUES
-- Kamga Thomas
(1,1,1,1,1,14.5,'composition'),(1,2,1,1,1,13.0,'composition'),
(1,3,1,1,1,15.0,'composition'),(1,4,1,1,1,12.5,'composition'),
(1,7,1,1,1,17.5,'composition'),(1,8,1,1,1,16.0,'composition'),
(1,9,1,1,1,15.5,'composition'),(1,10,1,1,1,14.0,'composition'),
(1,11,1,1,1,16.5,'composition'),(1,15,1,1,1,18.0,'composition'),
-- Biya Marie
(2,1,1,1,1,16.0,'composition'),(2,2,1,1,1,14.5,'composition'),
(2,3,1,1,1,17.0,'composition'),(2,4,1,1,1,15.0,'composition'),
(2,7,1,1,1,15.5,'composition'),(2,8,1,1,1,14.0,'composition'),
(2,9,1,1,1,16.0,'composition'),(2,10,1,1,1,13.5,'composition'),
(2,11,1,1,1,15.0,'composition'),(2,15,1,1,1,17.0,'composition'),
-- Nguemo Paul
(3,1,1,1,1,10.5,'composition'),(3,2,1,1,1,11.0,'composition'),
(3,3,1,1,1,12.0,'composition'),(3,4,1,1,1,09.5,'composition'),
(3,7,1,1,1,13.0,'composition'),(3,8,1,1,1,11.5,'composition'),
(3,9,1,1,1,10.0,'composition'),(3,10,1,1,1,12.0,'composition'),
(3,11,1,1,1,11.0,'composition'),(3,15,1,1,1,13.5,'composition'),
-- Ateba Sarah
(4,1,1,1,1,13.5,'composition'),(4,2,1,1,1,12.0,'composition'),
(4,3,1,1,1,14.5,'composition'),(4,4,1,1,1,13.0,'composition'),
(4,7,1,1,1,14.0,'composition'),(4,8,1,1,1,12.5,'composition'),
(4,9,1,1,1,13.0,'composition'),(4,10,1,1,1,11.5,'composition'),
(4,11,1,1,1,13.5,'composition'),(4,15,1,1,1,15.0,'composition');

-- Configuration bulletin par défaut
INSERT INTO bulletin_config (annee_id, nom_etablissement, sous_titre, adresse_etab,
    telephone_etab, email_etab, ville, titre_bulletin, couleur_entete, couleur_accent, pied_page)
VALUES (1,
    'LYCÉE TECHNIQUE DE YAOUNDÉ',
    'Centre d\'Excellence en Formation Technique et Professionnelle',
    'BP 1234 Yaoundé, Cameroun — Quartier Bastos',
    '+237 222 123 456',
    'contact@lty.cm',
    'Yaoundé',
    'BULLETIN DE NOTES',
    '#1e3a5f',
    '#2563eb',
    'Ce bulletin est un document officiel. Toute falsification est passible de sanctions.'
);

-- Décisions conseil (démo)
INSERT INTO conseils_classe (eleve_id, classe_id, periode_id, annee_id, appreciation, decision) VALUES
(1,1,1,1,'Excellent travail, élève très sérieux et motivé. Continue ainsi.','tableauhonneur'),
(2,1,1,1,'Très bons résultats, participante active en classe.','felicitations'),
(3,1,1,1,'Des efforts supplémentaires sont nécessaires, notamment en anglais.',''),
(4,1,1,1,'Bons résultats, élève régulière et appliquée.','encouragements');
