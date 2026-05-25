-- ============================================================
-- BASE DE DONNÉES : COMPLEXE SCOLAIRE (Maternelle → Terminale)
-- ============================================================
CREATE DATABASE IF NOT EXISTS complexe_scolaire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE complexe_scolaire;

-- NIVEAUX SCOLAIRES
CREATE TABLE niveaux (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    cycle ENUM('Maternelle','Primaire','Collège','Lycée') NOT NULL,
    ordre INT NOT NULL
);

INSERT INTO niveaux (code, nom, cycle, ordre) VALUES
('PS','Petite Section','Maternelle',1),
('MS','Moyenne Section','Maternelle',2),
('GS','Grande Section','Maternelle',3),
('CP','Cours Préparatoire','Primaire',4),
('CE1','Cours Élémentaire 1','Primaire',5),
('CE2','Cours Élémentaire 2','Primaire',6),
('CM1','Cours Moyen 1','Primaire',7),
('CM2','Cours Moyen 2','Primaire',8),
('6eme','Sixième','Collège',9),
('5eme','Cinquième','Collège',10),
('4eme','Quatrième','Collège',11),
('3eme','Troisième','Collège',12),
('2nde','Seconde','Lycée',13),
('1ere','Première','Lycée',14),
('Tle','Terminale','Lycée',15);

-- ANNÉES SCOLAIRES
CREATE TABLE annees_scolaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(20) NOT NULL UNIQUE,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    active TINYINT(1) DEFAULT 0
);
INSERT INTO annees_scolaires (libelle, date_debut, date_fin, active) VALUES ('2024-2025','2024-09-01','2025-07-31',1);

-- CLASSES
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    niveau_id INT NOT NULL,
    annee_id INT NOT NULL,
    capacite INT DEFAULT 30,
    FOREIGN KEY (niveau_id) REFERENCES niveaux(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- PARENTS / TUTEURS
CREATE TABLE parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    email VARCHAR(150),
    adresse TEXT,
    profession VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ÉLÈVES
CREATE TABLE eleves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    lieu_naissance VARCHAR(100),
    sexe ENUM('M','F') NOT NULL,
    photo VARCHAR(255),
    adresse TEXT,
    parent_id INT,
    statut ENUM('actif','inactif','transfere','diplome') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL
);

-- INSCRIPTIONS
CREATE TABLE inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    classe_id INT NOT NULL,
    annee_id INT NOT NULL,
    date_inscription DATE DEFAULT (CURRENT_DATE),
    frais_scolarite DECIMAL(10,2) DEFAULT 0,
    statut ENUM('inscrit','actif','abandon') DEFAULT 'inscrit',
    UNIQUE KEY uk_eleve_annee (eleve_id, annee_id),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- ENSEIGNANTS
CREATE TABLE enseignants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    sexe ENUM('M','F'),
    telephone VARCHAR(20),
    email VARCHAR(150),
    adresse TEXT,
    specialite VARCHAR(100),
    diplome VARCHAR(150),
    date_embauche DATE,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- MATIÈRES
CREATE TABLE matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    cycle ENUM('Maternelle','Primaire','Collège','Lycée','Tous') DEFAULT 'Tous',
    coefficient INT DEFAULT 1
);

INSERT INTO matieres (code, nom, cycle, coefficient) VALUES
('EVEIL','Éveil / Activités','Maternelle',1),
('LECTURE','Lecture','Maternelle',1),
('CALCUL','Calcul','Maternelle',1),
('FR','Français','Primaire',3),('MATH','Mathématiques','Primaire',3),
('HIST','Histoire-Géographie','Primaire',2),('SCI','Sciences','Primaire',2),
('EPS','EPS','Tous',1),('ART','Arts Plastiques','Tous',1),
('FRC','Français','Collège',4),('MATHC','Mathématiques','Collège',4),
('PC','Physique-Chimie','Collège',3),('SVT','SVT','Collège',3),
('HISTG','Histoire-Géo','Collège',2),('ANG','Anglais','Collège',3),
('PHILO','Philosophie','Lycée',4),('MATHL','Mathématiques','Lycée',5),
('PCL','Physique-Chimie','Lycée',5),('SVTL','SVT','Lycée',4),
('FRL','Français','Lycée',4),('ANGL','Anglais','Lycée',3);

-- AFFECTATION ENSEIGNANT → MATIÈRE → CLASSE
CREATE TABLE affectations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enseignant_id INT NOT NULL,
    matiere_id INT NOT NULL,
    classe_id INT NOT NULL,
    annee_id INT NOT NULL,
    heures_semaine INT DEFAULT 2,
    UNIQUE KEY uk_affect (enseignant_id, matiere_id, classe_id, annee_id),
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- PÉRIODES / TRIMESTRES
CREATE TABLE periodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annee_id INT NOT NULL,
    nom VARCHAR(50) NOT NULL,
    date_debut DATE,
    date_fin DATE,
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);
INSERT INTO periodes (annee_id, nom, date_debut, date_fin) VALUES
(1,'1er Trimestre','2024-09-01','2024-12-20'),
(1,'2ème Trimestre','2025-01-06','2025-03-28'),
(1,'3ème Trimestre','2025-04-07','2025-07-04');

-- NOTES
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    matiere_id INT NOT NULL,
    classe_id INT NOT NULL,
    periode_id INT NOT NULL,
    annee_id INT NOT NULL,
    note DECIMAL(5,2) NOT NULL,
    note_max DECIMAL(5,2) DEFAULT 20,
    type_eval ENUM('devoir','composition','examen','oral','pratique') DEFAULT 'devoir',
    date_eval DATE DEFAULT (CURRENT_DATE),
    observation TEXT,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (periode_id) REFERENCES periodes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- EMPLOI DU TEMPS
CREATE TABLE emploi_temps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classe_id INT NOT NULL,
    annee_id INT NOT NULL,
    affectation_id INT NOT NULL,
    jour ENUM('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    salle VARCHAR(50),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id),
    FOREIGN KEY (affectation_id) REFERENCES affectations(id) ON DELETE CASCADE
);

-- ABSENCES
CREATE TABLE absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    date_absence DATE NOT NULL,
    motif TEXT,
    justifie TINYINT(1) DEFAULT 0,
    annee_id INT NOT NULL,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- PAIEMENTS SCOLARITÉ
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inscription_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATE DEFAULT (CURRENT_DATE),
    mode ENUM('especes','chèque','virement','mobile_money') DEFAULT 'especes',
    reference VARCHAR(50),
    observation TEXT,
    FOREIGN KEY (inscription_id) REFERENCES inscriptions(id) ON DELETE CASCADE
);

-- UTILISATEURS
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(150),
    role ENUM('admin','directeur','enseignant','comptable','secretaire') DEFAULT 'secretaire',
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Mot de passe : Admin123
INSERT INTO utilisateurs (username, password, nom, prenom, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur','Système','admin');
