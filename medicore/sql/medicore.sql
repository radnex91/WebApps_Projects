-- ============================================================
--  MediCore ERP Hospitalier — Base de données MySQL
--  Compatible XAMPP / MySQL 5.7+
--  Importez ce fichier via phpMyAdmin ou la ligne de commande
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `medicore` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `medicore`;

-- ─── UTILISATEURS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  UNIQUE KEY `uq_username` (`username`),
  `mot_de_passe` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'infirmier',
  `specialite` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `extension` varchar(10) DEFAULT NULL,
  `statut` enum('actif','inactif','conge') NOT NULL DEFAULT 'actif',
  `planning` varchar(50) DEFAULT 'Journée (08h-18h)',
  `avatar_initiales` varchar(3) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── PATIENTS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL UNIQUE,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F','Autre') NOT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `num_secu` varchar(20) DEFAULT NULL,
  `groupe_sanguin` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `antecedents` text DEFAULT NULL,
  `contact_urgence_nom` varchar(200) DEFAULT NULL,
  `contact_urgence_tel` varchar(20) DEFAULT NULL,
  `assurance` enum('CPAM','Mutuelle','Non assuré','Étranger') DEFAULT 'CPAM',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── DEPARTEMENTS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `capacite_lits` int(11) NOT NULL DEFAULT 20,
  `chef_service_id` int(11) DEFAULT NULL,
  `etage` varchar(20) DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#3b82f6',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── CHAMBRES / LITS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `departement_id` int(11) NOT NULL,
  `statut` enum('libre','occupe','nettoyage','hors_service') NOT NULL DEFAULT 'libre',
  `type` enum('standard','soins_intensifs','reanimation','isolement') NOT NULL DEFAULT 'standard',
  PRIMARY KEY (`id`),
  KEY `fk_lit_dept` (`departement_id`),
  CONSTRAINT `fk_lit_dept` FOREIGN KEY (`departement_id`) REFERENCES `departements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── HOSPITALISATIONS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `hospitalisations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `lit_id` int(11) NOT NULL,
  `medecin_id` int(11) NOT NULL,
  `departement_id` int(11) NOT NULL,
  `date_admission` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_sortie` datetime DEFAULT NULL,
  `motif` text NOT NULL,
  `priorite` enum('normal','urgent','critique') NOT NULL DEFAULT 'normal',
  `statut` enum('en_cours','sorti','transfere') NOT NULL DEFAULT 'en_cours',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_hosp_patient` (`patient_id`),
  KEY `fk_hosp_lit` (`lit_id`),
  KEY `fk_hosp_med` (`medecin_id`),
  CONSTRAINT `fk_hosp_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_hosp_lit` FOREIGN KEY (`lit_id`) REFERENCES `lits` (`id`),
  CONSTRAINT `fk_hosp_med` FOREIGN KEY (`medecin_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── RENDEZ-VOUS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rendez_vous` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `medecin_id` int(11) NOT NULL,
  `date_heure` datetime NOT NULL,
  `duree_minutes` int(11) NOT NULL DEFAULT 30,
  `motif` varchar(255) NOT NULL,
  `type` enum('consultation','suivi','urgence','chirurgie','bilan') NOT NULL DEFAULT 'consultation',
  `salle` varchar(50) DEFAULT NULL,
  `statut` enum('planifie','confirme','complete','annule','absent') NOT NULL DEFAULT 'planifie',
  `notes` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rdv_patient` (`patient_id`),
  KEY `fk_rdv_medecin` (`medecin_id`),
  CONSTRAINT `fk_rdv_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_rdv_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── MEDICAMENTS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `medicaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) NOT NULL,
  `categorie` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `unite` varchar(20) DEFAULT NULL,
  `stock_actuel` int(11) NOT NULL DEFAULT 0,
  `stock_minimum` int(11) NOT NULL DEFAULT 100,
  `fournisseur` varchar(100) DEFAULT NULL,
  `prix_unitaire` decimal(10,2) DEFAULT 0.00,
  `date_expiration` date DEFAULT NULL,
  `statut` enum('normal','bas','critique','expire') NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ORDONNANCES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ordonnances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `medecin_id` int(11) NOT NULL,
  `date_prescription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('active','terminee','annulee') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `fk_ord_patient` (`patient_id`),
  KEY `fk_ord_medecin` (`medecin_id`),
  CONSTRAINT `fk_ord_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_ord_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── LIGNES ORDONNANCES ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ordonnance_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordonnance_id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `frequence` varchar(100) NOT NULL,
  `duree` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ol_ord` (`ordonnance_id`),
  KEY `fk_ol_med` (`medicament_id`),
  CONSTRAINT `fk_ol_ord` FOREIGN KEY (`ordonnance_id`) REFERENCES `ordonnances` (`id`),
  CONSTRAINT `fk_ol_med` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ANALYSES LABORATOIRE ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL UNIQUE,
  `patient_id` int(11) NOT NULL,
  `prescripteur_id` int(11) NOT NULL,
  `type_examen` varchar(200) NOT NULL,
  `date_prelevement` datetime DEFAULT NULL,
  `date_resultat` datetime DEFAULT NULL,
  `statut` enum('prescrit','en_cours','disponible','archive') NOT NULL DEFAULT 'prescrit',
  `resultat` text DEFAULT NULL,
  `fichier_resultat` varchar(255) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ana_patient` (`patient_id`),
  KEY `fk_ana_prescr` (`prescripteur_id`),
  CONSTRAINT `fk_ana_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_ana_prescr` FOREIGN KEY (`prescripteur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── FACTURES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `factures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL UNIQUE,
  `patient_id` int(11) NOT NULL,
  `hospitalisation_id` int(11) DEFAULT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_assurance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_patient` decimal(10,2) NOT NULL DEFAULT 0.00,
  `assurance_type` varchar(100) DEFAULT NULL,
  `statut` enum('en_attente','reglee','partielle','impayee','annulee') NOT NULL DEFAULT 'en_attente',
  `date_emission` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_paiement` datetime DEFAULT NULL,
  `date_reglement` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fac_patient` (`patient_id`),
  CONSTRAINT `fk_fac_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── STOCKS MATERIEL ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) NOT NULL,
  `categorie` enum('consommable','equipement','protection','autre') NOT NULL DEFAULT 'consommable',
  `quantite` int(11) NOT NULL DEFAULT 0,
  `unite` varchar(30) DEFAULT 'unités',
  `valeur_unitaire` decimal(10,2) DEFAULT 0.00,
  `fournisseur` varchar(100) DEFAULT NULL,
  `seuil_alerte` int(11) DEFAULT 50,
  `statut` enum('normal','bas','critique') NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ACTIVITE LOG ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activite_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entite` varchar(50) DEFAULT NULL,
  `entite_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `couleur` enum('green','blue','yellow','red') NOT NULL DEFAULT 'blue',
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_user` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DONNÉES DE DÉMONSTRATION
-- ============================================================

-- Utilisateurs (mot de passe: admin123 hashé en bcrypt)
INSERT INTO `utilisateurs` (`nom`, `prenom`, `username`, `email`, `mot_de_passe`, `role`, `specialite`, `telephone`, `extension`, `statut`, `planning`, `avatar_initiales`) VALUES
('Admin', 'Système', 'admin', 'admin@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administration', '01 23 45 67 89', '2100', 'actif', 'Journée (08h-18h)', 'AD'),
('Bernard', 'Patrick', 'dr.martin', 'p.bernard@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medecin', 'Cardiologie', '01 23 45 67 01', '2201', 'actif', 'Journée (08h-18h)', 'PB'),
('Martin', 'Carole', 'c.martin', 'c.martin@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medecin', 'Médecine générale', '01 23 45 67 02', '2315', 'actif', 'Matin (07h-15h)', 'CM'),
('Leroy', 'François', 'f.leroy', 'f.leroy@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medecin', 'Neurologie', '01 23 45 67 03', '2408', 'actif', 'Journée (09h-19h)', 'FL'),
('Rousseau', 'Marie', 'm.rousseau', 'm.rousseau@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medecin', 'Pédiatrie', '01 23 45 67 04', '2512', 'actif', 'Matin (08h-16h)', 'MR'),
('Simon', 'Antoine', 'a.simon', 'a.simon@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medecin', 'Chirurgie orthopédique', '01 23 45 67 05', '2617', 'actif', 'Matin (06h-14h)', 'AS'),
('Dupont', 'Isabelle', 'pharmacie', 'i.dupont@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'infirmier', 'Soins généraux', '01 23 45 67 10', '3001', 'actif', 'Matin (07h-15h)', 'ID'),
('Lefebvre', 'Sophie', 'compta', 's.lefebvre@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacien', 'Pharmacie hospitalière', '01 23 45 67 20', '4001', 'actif', 'Journée (08h-18h)', 'SL'),
('Moreau', 'Jean', 'infirmier', 'j.moreau@medicore.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'comptable', 'Finance', '01 23 45 67 30', '5001', 'actif', 'Journée (08h-18h)', 'JM');

-- Départements
INSERT INTO `departements` (`nom`, `code`, `capacite_lits`, `chef_service_id`, `etage`, `couleur`) VALUES
('Cardiologie', 'CARD', 24, 2, 'Étage 3', '#ef4444'),
('Neurologie', 'NEUR', 20, 4, 'Étage 4', '#8b5cf6'),
('Pédiatrie', 'PEDI', 30, 5, 'Étage 2', '#06b6d4'),
('Chirurgie', 'CHIR', 16, 6, 'Étage 5', '#10b981'),
('Urgences', 'URG', 10, 3, 'Rez-de-chaussée', '#f59e0b'),
('Maternité', 'MATER', 20, 5, 'Étage 1', '#ec4899');

-- Lits
INSERT INTO `lits` (`numero`, `departement_id`, `statut`, `type`) VALUES
('12A', 1, 'occupe', 'soins_intensifs'), ('12B', 1, 'libre', 'standard'),
('11A', 1, 'occupe', 'standard'), ('11B', 1, 'occupe', 'standard'),
('10A', 1, 'nettoyage', 'standard'), ('10B', 1, 'libre', 'standard'),
('3A', 2, 'occupe', 'standard'), ('3B', 2, 'occupe', 'standard'),
('3C', 2, 'occupe', 'soins_intensifs'), ('3D', 2, 'libre', 'standard'),
('8A', 4, 'occupe', 'standard'), ('8B', 4, 'occupe', 'standard'),
('8C', 4, 'libre', 'standard'), ('8D', 4, 'nettoyage', 'standard'),
('U1', 5, 'occupe', 'reanimation'), ('U2', 5, 'occupe', 'reanimation'),
('U3', 5, 'occupe', 'standard'), ('U4', 5, 'libre', 'standard'),
('21A', 6, 'occupe', 'standard'), ('21B', 6, 'libre', 'standard'),
('6A', 4, 'libre', 'standard'), ('6B', 4, 'occupe', 'standard'),
('6C', 4, 'occupe', 'standard'), ('6D', 4, 'occupe', 'standard');

-- Patients
INSERT INTO `patients` (`numero`, `nom`, `prenom`, `date_naissance`, `sexe`, `adresse`, `telephone`, `email`, `num_secu`, `groupe_sanguin`, `allergies`, `antecedents`, `assurance`) VALUES
('P-2026-0891', 'Lefebvre', 'Martin', '1972-03-14', 'M', '12 rue Victor Hugo, 75014 Paris', '06 12 34 56 78', 'martin.lefebvre@email.fr', '1 72 03 75 014 042 85', 'A+', 'Pénicilline', 'HTA depuis 2018, Diabète type 2, Hypercholestérolémie', 'CPAM'),
('P-2026-0890', 'Blanc', 'Sophie', '1994-07-22', 'F', '5 avenue Montaigne, 75008 Paris', '06 98 76 54 32', 'sophie.blanc@email.fr', '2 94 07 75 008 015 42', 'B+', 'Aspirine', 'Appendicectomie 2015', 'Mutuelle'),
('P-2026-0889', 'Dubois', 'Jean', '1955-11-08', 'M', '88 boulevard Haussmann, 75009 Paris', '06 55 44 33 22', NULL, '1 55 11 75 009 088 63', 'O-', 'Aucune connue', 'Tabagisme 30 ans, HTA', 'CPAM'),
('P-2026-0888', 'Moreau', 'Camille', '1998-01-30', 'F', '3 impasse des Lilas, 92100 Boulogne', '06 11 22 33 44', 'camille.moreau@email.fr', '2 98 01 92 100 008 77', 'AB+', 'Latex', '1ère grossesse', 'CPAM'),
('P-2026-0887', 'Lambert', 'Pierre', '1981-05-19', 'M', '47 rue de la Paix, 75001 Paris', '06 77 88 99 00', 'pierre.lambert@email.fr', '1 81 05 75 001 047 22', 'A-', 'Ibuprofène', 'Fracture tibiale 2019', 'Mutuelle'),
('P-2026-0886', 'Petit', 'Isabelle', '1959-08-03', 'F', '15 rue des Fleurs, 75016 Paris', '06 44 55 66 77', NULL, '2 59 08 75 016 015 88', 'B-', 'Aucune connue', 'Fibrillation auriculaire depuis 2020', 'CPAM'),
('P-2026-0885', 'Girard', 'Emma', '2018-12-10', 'F', '22 rue Nationale, 75013 Paris', '06 33 22 11 00', NULL, '2 18 12 75 013 022 44', 'O+', 'Amoxicilline', 'Asthme léger', 'CPAM'),
('P-2026-0884', 'Roux', 'Théo', '2005-04-25', 'M', '9 avenue des Gobelins, 75005 Paris', '06 99 88 77 66', NULL, '1 05 04 75 005 009 33', 'A+', 'Arachides', 'Asthme sévère depuis enfance', 'Mutuelle');
-- Patients supplémentaires de démo
INSERT IGNORE INTO `patients` (`numero`, `nom`, `prenom`, `date_naissance`, `sexe`, `adresse`, `telephone`, `email`, `groupe_sanguin`, `allergies`, `antecedents`, `assurance`, `contact_urgence_nom`, `contact_urgence_tel`) VALUES
('P-2024-00009', 'Biya', 'Paul-Henri', '1978-03-15', 'M', 'Rue de la Liberté, Yaoundé', '+237 699 111 222', 'phbiya@mail.cm', 'O+', '', 'Diabète type 2', 'CNSS', 'Marie Biya', '+237 699 333 444'),
('P-2024-00010', 'Mfoumou', 'Cécile', '1985-07-22', 'F', 'Av. Kennedy, Douala', '+237 677 555 666', 'cmfoumou@mail.cm', 'A+', 'Pénicilline', 'Asthme chronique', 'Mutuelle', 'Jean Mfoumou', '+237 677 777 888'),
('P-2024-00011', 'Abena', 'Georges', '1960-11-30', 'M', 'Quartier Ndogbong, Douala', '+237 655 999 000', '', 'B+', '', 'HTA, Insuffisance rénale', 'CNSS', 'Hélène Abena', '+237 655 111 000'),
('P-2024-00012', 'Essomba', 'Martine', '1992-04-08', 'F', 'Rue Foch, Yaoundé', '+237 699 444 555', 'essomba.m@gmail.cm', 'AB-', 'Aspirine', '', 'Non assuré', 'Pierre Essomba', '+237 699 666 777'),
('P-2024-00013', 'Nguema', 'André', '1955-08-19', 'M', 'BP 1234 Libreville', '+237 622 111 333', '', 'O-', '', 'Cancer prostate stade 2', 'Étranger', 'Sophie Nguema', '+237 622 444 555'),
('P-2024-00014', 'Kotto', 'Delphine', '2001-12-05', 'F', 'Cité des Palmiers, Kribi', '+237 677 222 444', 'dkotto@yahoo.fr', 'A-', '', '', 'Mutuelle', 'Marc Kotto', '+237 677 555 666'),
('P-2024-00015', 'Mbida', 'Emmanuel', '1970-06-14', 'M', 'Rue de Jouvence, Bafoussam', '+237 699 777 888', '', 'B-', 'Sulfamides', 'Drépanocytose', 'CNSS', 'Clarisse Mbida', '+237 699 999 000');

-- Hospitalisations
INSERT INTO `hospitalisations` (`patient_id`, `lit_id`, `medecin_id`, `departement_id`, `date_admission`, `motif`, `priorite`, `statut`, `notes`) VALUES
(1, 3, 2, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Insuffisance cardiaque congestive sévère', 'critique', 'en_cours', 'Patient sous surveillance continue, ECG toutes les 4h'),
(3, 7, 3, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'AVC ischémique - rééducation neurologique', 'urgent', 'en_cours', 'Rééducation motrice en cours, progrès satisfaisants'),
(5, 12, 5, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Accouchement prématuré - 34 semaines', 'urgent', 'en_cours', 'Nouveau-né en couveuse, état stable'),
(7, 18, 4, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Appendicite aiguë - post-opératoire J1', 'normal', 'en_cours', 'Opération réussie, cicatrisation surveillée'),
(2, 5, 2, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'Hypertension artérielle sévère', 'urgent', 'sortie', 'Sortie avec traitement antihypertenseur'),
(4, 9, 3, 3, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Migraine chronique réfractaire', 'normal', 'sortie', 'Protocole antimigraineux mis en place');

-- Rendez-vous
INSERT INTO `rendez_vous` (`patient_id`, `medecin_id`, `date_heure`, `duree_minutes`, `motif`, `type`, `salle`, `statut`) VALUES
(1, 2, DATE_ADD(CURDATE(), INTERVAL '08:00' HOUR_MINUTE), 45, 'Consultation cardiologie urgente', 'consultation', 'Salle A3', 'confirme'),
(2, 2, DATE_ADD(CURDATE(), INTERVAL '09:00' HOUR_MINUTE), 30, 'Contrôle tension artérielle', 'suivi', 'Salle A1', 'confirme'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL '10:30' HOUR_MINUTE), 45, 'Consultation neurologie', 'consultation', 'Salle B2', 'planifie'),
(4, 2, DATE_ADD(CURDATE(), INTERVAL '11:00' HOUR_MINUTE), 30, 'Résultats analyses laboratoire', 'suivi', 'Salle A3', 'confirme'),
(5, 5, DATE_ADD(CURDATE(), INTERVAL '11:30' HOUR_MINUTE), 60, 'Examen pédiatrique complet', 'consultation', 'Salle P1', 'planifie'),
(6, 2, DATE_ADD(CURDATE(), INTERVAL '14:00' HOUR_MINUTE), 30, 'Suivi post-opératoire', 'suivi', 'Salle A2', 'confirme'),
(7, 3, DATE_ADD(CURDATE(), INTERVAL '14:30' HOUR_MINUTE), 45, 'Bilan neurologique complet', 'bilan', 'Labo B1', 'planifie'),
(8, 2, DATE_ADD(CURDATE(), INTERVAL '15:30' HOUR_MINUTE), 30, 'Consultation cardio urgente', 'urgence', 'Urgences', 'planifie'),
(1, 3, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL '09:00' HOUR_MINUTE), 45, 'Suivi neurologie', 'suivi', 'Salle B2', 'planifie'),
(4, 5, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL '10:00' HOUR_MINUTE), 30, 'Suivi pédiatrie', 'suivi', 'Salle P2', 'planifie'),
(2, 2, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL '08:30' HOUR_MINUTE), 45, 'Bilan cardiologique', 'bilan', 'Salle A1', 'planifie'),
(6, 3, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL '11:00' HOUR_MINUTE), 30, 'Contrôle neurologie', 'suivi', 'Salle B1', 'planifie'),
(3, 2, DATE_SUB(CURDATE(), INTERVAL '2 1:00:00' DAY_SECOND), 30, 'Suivi post-opératoire', 'suivi', 'Salle A2', 'complete'),
(5, 3, DATE_SUB(CURDATE(), INTERVAL '2 11:00:00' DAY_SECOND), 45, 'Consultation neurologie', 'consultation', 'Salle B2', 'complete'),
(7, 5, DATE_SUB(CURDATE(), INTERVAL '1 10:00:00' DAY_SECOND), 30, 'Suivi maternité', 'suivi', 'Salle M2', 'complete'),
(8, 2, DATE_SUB(CURDATE(), INTERVAL '1 14:00:00' DAY_SECOND), 30, 'Consultation asthme', 'consultation', 'Salle G4', 'complete');

-- Médicaments
INSERT INTO `medicaments` (`nom`, `categorie`, `dosage`, `unite`, `stock_actuel`, `stock_minimum`, `fournisseur`, `prix_unitaire`, `statut`) VALUES
('Paracétamol', 'Antalgique', '500mg', 'comprimé', 187, 500, 'PharmaCo', 0.05, 'critique'),
('Amoxicilline', 'Antibiotique', '1g', 'comprimé', 320, 400, 'MedLab', 0.45, 'bas'),
('Insuline Glargine', 'Antidiabétique', '100UI/ml', 'flacon', 890, 300, 'Sanofi', 18.50, 'normal'),
('Morphine', 'Analgésique', '10mg', 'ampoule', 156, 200, 'PharmaCo', 2.80, 'bas'),
('Adrénaline', 'Urgence', '1mg', 'ampoule', 445, 100, 'MedLab', 1.20, 'normal'),
('Lisinopril', 'Antihypertenseur', '10mg', 'comprimé', 1240, 300, 'CardioMed', 0.12, 'normal'),
('Metformine', 'Antidiabétique oral', '500mg', 'comprimé', 870, 400, 'DiabMed', 0.08, 'normal'),
('Héparine', 'Anticoagulant', '5000UI', 'ampoule', 340, 150, 'PharmaCo', 1.85, 'normal'),
('Salbutamol', 'Bronchodilatateur', '2.5mg', 'nébulisation', 280, 200, 'RespiMed', 0.95, 'normal'),
('Doliprane IV', 'Antalgique', '1g/100ml', 'poche', 95, 200, 'PharmaCo', 3.20, 'bas');

-- Analyses
INSERT INTO `analyses` (`numero`, `patient_id`, `prescripteur_id`, `type_examen`, `date_prelevement`, `date_resultat`, `statut`, `resultat`) VALUES
('L-2026-0234', 3, 4, 'IRM cérébrale avec injection', '2026-04-05 08:55:00', '2026-04-05 09:30:00', 'disponible', 'Infarctus ischémique territoire ACM droit. Zone pénombre présente.'),
('L-2026-0233', 1, 2, 'Bilan cardiaque complet (troponine, BNP, ECG)', '2026-04-05 08:30:00', NULL, 'en_cours', NULL),
('L-2026-0232', 2, 3, 'Bilan post-opératoire', '2026-04-04 17:00:00', '2026-04-04 20:15:00', 'disponible', 'NFS normale. CRP légèrement élevée. Cicatrisation favorable.'),
('L-2026-0231', 6, 2, 'ECG + Holter 24h', '2026-04-04 14:20:00', '2026-04-04 18:00:00', 'disponible', 'FA persistante documentée. Fréquence ventriculaire 95-140 bpm.'),
('L-2026-0230', 8, 3, 'Spirométrie + DEP', '2026-04-05 10:15:00', NULL, 'en_cours', NULL);

-- Factures
INSERT INTO `factures` (`numero`, `patient_id`, `montant_total`, `montant_assurance`, `montant_patient`, `assurance_type`, `statut`, `date_emission`) VALUES
('F-2026-0894', 1, 2480.00, 1984.00, 496.00, 'CPAM - 80%', 'en_attente', '2026-04-05 09:00:00'),
('F-2026-0893', 2, 1850.00, 1850.00, 0.00, 'Mutuelle - 100%', 'reglee', '2026-04-05 08:00:00'),
('F-2026-0892', 6, 180.00, 126.00, 54.00, 'CPAM - 70%', 'reglee', '2026-04-04 15:00:00'),
('F-2026-0891', 5, 340.00, 0.00, 340.00, 'Non assuré', 'impayee', '2026-04-03 12:00:00'),
('F-2026-0890', 4, 620.00, 620.00, 0.00, 'CPAM - 100%', 'reglee', '2026-04-03 10:00:00');

-- Stocks matériel
INSERT INTO `stocks` (`nom`, `categorie`, `quantite`, `unite`, `valeur_unitaire`, `fournisseur`, `seuil_alerte`, `statut`) VALUES
('Seringues 10ml', 'consommable', 4820, 'unités', 0.05, 'MedSupply', 500, 'normal'),
('Gants latex L', 'protection', 12400, 'paires', 0.05, 'ProMed', 1000, 'normal'),
('Défibrillateur AED', 'equipement', 8, 'appareils', 1800.00, 'Philips Med', 5, 'normal'),
('Masques FFP2', 'protection', 840, 'unités', 0.40, 'SafeGuard', 1000, 'bas'),
('Tensiomètre digital', 'equipement', 24, 'appareils', 150.00, 'Omron Med', 10, 'normal'),
('Cathéters veineux', 'consommable', 230, 'unités', 2.50, 'MedSupply', 500, 'critique'),
('Compresses stériles', 'consommable', 3200, 'unités', 0.12, 'MedSupply', 500, 'normal'),
('Oxymètres de pouls', 'equipement', 45, 'appareils', 85.00, 'OxyMed', 20, 'normal');

-- Logs d'activité
INSERT INTO `activite_log` (`utilisateur_id`, `action`, `entite`, `couleur`) VALUES
(2, 'Admission patient Lefebvre Martin - Cardiologie', 'hospitalisation', 'green'),
(7, 'Alerte stock - Paracétamol 500mg (187 unités)', 'medicament', 'red'),
(4, 'Résultats IRM disponibles - Dubois Jean', 'analyse', 'blue'),
(2, 'Bloc A - Intervention terminée', 'hospitalisation', 'yellow'),
(1, 'Facture F-2026-0893 réglée', 'facture', 'green'),
(3, 'Nouveau RDV - Patient Roux Théo 14h00', 'rdv', 'blue');


-- ─── TABLES CAISSE ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id`              int(11) NOT NULL AUTO_INCREMENT,
  `numero_ticket`   varchar(20) NOT NULL UNIQUE,
  `patient_id`      int(11) DEFAULT NULL,
  `caissier_id`     int(11) NOT NULL,
  `montant_total`   decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_recu`    decimal(10,2) NOT NULL DEFAULT 0.00,
  `monnaie_rendue`  decimal(10,2) NOT NULL DEFAULT 0.00,
  `mode_paiement`   enum('especes','carte','cheque','virement','assurance','mobile_money','gratuit') NOT NULL DEFAULT 'especes',
  `statut`          enum('ouvert','paye','annule','rembourse') NOT NULL DEFAULT 'ouvert',
  `ordonnance_id`   int(11) DEFAULT NULL,
  `notes`           text DEFAULT NULL,
  `date_vente`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vente_patient`   (`patient_id`),
  KEY `fk_vente_caissier`  (`caissier_id`),
  CONSTRAINT `fk_vente_patient`  FOREIGN KEY (`patient_id`)  REFERENCES `patients` (`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_vente_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `caisse_lignes` (
  `id`              int(11) NOT NULL AUTO_INCREMENT,
  `vente_id`        int(11) NOT NULL,
  `medicament_id`   int(11) NOT NULL,
  `quantite`        int(11) NOT NULL DEFAULT 1,
  `prix_unitaire`   decimal(10,2) NOT NULL,
  `remise_pct`      decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_ligne`     decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ligne_vente` (`vente_id`),
  KEY `fk_ligne_med`   (`medicament_id`),
  CONSTRAINT `fk_ligne_vente` FOREIGN KEY (`vente_id`)       REFERENCES `caisse_ventes`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ligne_med`   FOREIGN KEY (`medicament_id`)  REFERENCES `medicaments`    (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ventes caisse de démonstration
INSERT INTO `caisse_ventes` (`numero_ticket`, `patient_id`, `caissier_id`, `montant_total`, `montant_recu`, `monnaie_rendue`, `mode_paiement`, `statut`, `date_vente`) VALUES
('TK-2024-00001', 1, 5, 25000, 30000, 5000, 'especes', 'paye', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('TK-2024-00002', 2, 5, 15000, 15000, 0, 'mobile_money', 'paye', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('TK-2024-00003', 3, 5, 45000, 50000, 5000, 'especes', 'paye', CURDATE()),
('TK-2024-00004', 4, 5, 12000, 12000, 0, 'carte', 'paye', CURDATE()),
('TK-2024-00005', 5, 5, 8000, 10000, 2000, 'especes', 'paye', CURDATE());


-- Lignes de vente caisse
INSERT INTO `caisse_lignes` (`vente_id`, `medicament_id`, `quantite`, `prix_unitaire`, `total_ligne`) VALUES
(1, 1, 2, 5000, 10000),
(1, 3, 1, 15000, 15000),
(2, 2, 3, 5000, 15000),
(3, 4, 1, 20000, 20000),
(3, 5, 5, 5000, 25000),
(4, 6, 2, 6000, 12000),
(5, 7, 1, 8000, 8000);


-- ─── TABLE PARAMÈTRES APPLICATION ───────────────────────────
CREATE TABLE IF NOT EXISTS `app_settings` (
  `cle`    varchar(60)   NOT NULL,
  `valeur` varchar(500)  NOT NULL DEFAULT '',
  `label`  varchar(120)  NOT NULL DEFAULT '',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `app_settings` (`cle`, `valeur`, `label`) VALUES
('font_body',         'DM Sans',           'Police principale'),
('font_heading',      'Playfair Display',  'Police titres'),
('font_size',         '14',                'Taille de base (px)'),
('currency_symbol',   'FCFA',                 'Symbole monétaire'),
('currency_code',     'XAF',               'Code ISO monnaie'),
('currency_position', 'after',             'Position symbole (before/after)'),
('currency_dec_sep',  ',',                 'Séparateur décimal'),
('currency_thou_sep', ' ',                 'Séparateur milliers'),
('currency_decimals', '0',                 'Nombre de décimales'),
('app_name',          'MediCore ERP',      'Nom de l\'application'),
('etablissement',     'Hôpital Universitaire Saint-Charles', 'Nom établissement'),
('date_format',       'd/m/Y',             'Format date');

-- ── Nouveaux parametres de personnalisation
INSERT IGNORE INTO `app_settings` (`cle`, `valeur`, `label`) VALUES
('adresse',       '1 Avenue du General Leclerc, 75014 Paris', 'Adresse etablissement'),
('telephone',     '01 23 45 67 89',   'Telephone'),
('email_contact', 'contact@hopital.fr','Email contact'),
('theme_primary', '1E3A5F',           'Couleur primaire'),
('theme_accent',  '3b82f6',           'Couleur accent'),
('theme_mode',    'dark',             'Mode clair/sombre'),
('logo_base64',   '',                 'Logo etablissement (base64)'),
('logo_nom',      '',                 'Nom du fichier logo'),
('entete_rapport','',                 'En-tête personnalisé des rapports');

-- ══════════════════════════════════════════════════════════════
--  SYSTÈME DE PERMISSIONS DYNAMIQUES
-- ══════════════════════════════════════════════════════════════

-- Permissions pages par rôle (stockées en BDD, éditables par admin)
CREATE TABLE IF NOT EXISTS `role_page_access` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`       VARCHAR(50)  NOT NULL,
  `page`       VARCHAR(50)  NOT NULL,
  `allowed`    TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_page` (`role`,`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions actions par rôle
CREATE TABLE IF NOT EXISTS `role_action_access` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`       VARCHAR(50)  NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `allowed`    TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_action` (`role`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rôles personnalisés (labels, couleurs, description)
CREATE TABLE IF NOT EXISTS `roles_config` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`        VARCHAR(50)  NOT NULL UNIQUE,
  `label`       VARCHAR(100) NOT NULL,
  `couleur`     VARCHAR(20)  NOT NULL DEFAULT '#3b82f6',
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `actif`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rôles de base
INSERT IGNORE INTO `roles_config` (`role`,`label`,`couleur`,`description`) VALUES
('admin',      'Administrateur',  '#ef4444', 'Accès total au système, configuration et gestion'),
('medecin',    'Médecin',         '#3b82f6', 'Accès clinique complet : patients, RDV, dossiers, laboratoire'),
('infirmier',  'Infirmier(ère)',   '#10b981', 'Soins : patients, lits, urgences, laboratoire (lecture)'),
('pharmacien', 'Pharmacien',      '#8b5cf6', 'Pharmacie, caisse, stocks médicaments'),
('comptable',  'Comptable',       '#f59e0b', 'Facturation, rapports financiers, caisse (lecture)');

-- Permissions pages par défaut
INSERT IGNORE INTO `role_page_access` (`role`,`page`,`allowed`) VALUES
-- admin : tout
('admin','dashboard',1),('admin','analytics',1),('admin','patients',1),
('admin','appointments',1),('admin','urgences',1),('admin','dossiers',1),
('admin','medecins',1),('admin','lits',1),('admin','pharmacie',1),
('admin','caisse',1),('admin','laboratoire',1),('admin','facturation',1),
('admin','rh',1),('admin','stocks',1),('admin','rapports',1),
('admin','utilisateurs',1),('admin','parametres',1),
('admin','roles',1),
-- medecin
('medecin','dashboard',1),('medecin','analytics',1),('medecin','patients',1),
('medecin','appointments',1),('medecin','urgences',1),('medecin','dossiers',1),
('medecin','medecins',1),('medecin','lits',1),('medecin','laboratoire',1),
('medecin','rapports',1),
('medecin','pharmacie',0),('medecin','caisse',0),('medecin','facturation',0),
('medecin','rh',0),('medecin','stocks',0),('medecin','utilisateurs',0),('medecin','parametres',0),
('medecin','roles',0),
-- infirmier
('infirmier','dashboard',1),('infirmier','patients',1),('infirmier','appointments',1),
('infirmier','urgences',1),('infirmier','lits',1),('infirmier','laboratoire',1),
('infirmier','medecins',1),
('infirmier','analytics',0),('infirmier','dossiers',0),('infirmier','pharmacie',0),
('infirmier','caisse',0),('infirmier','facturation',0),('infirmier','rh',0),
('infirmier','stocks',0),('infirmier','rapports',0),('infirmier','utilisateurs',0),('infirmier','parametres',0),
('infirmier','roles',0),
-- pharmacien
('pharmacien','dashboard',1),('pharmacien','pharmacie',1),('pharmacien','caisse',1),
('pharmacien','stocks',1),
('pharmacien','analytics',0),('pharmacien','patients',0),('pharmacien','appointments',0),
('pharmacien','urgences',0),('pharmacien','dossiers',0),('pharmacien','medecins',0),
('pharmacien','lits',0),('pharmacien','laboratoire',0),('pharmacien','facturation',0),
('pharmacien','rh',0),('pharmacien','rapports',0),('pharmacien','utilisateurs',0),('pharmacien','parametres',0),
('pharmacien','roles',0),
-- comptable
('comptable','dashboard',1),('comptable','caisse',1),('comptable','facturation',1),
('comptable','rapports',1),('comptable','analytics',1),
('comptable','patients',0),('comptable','appointments',0),('comptable','urgences',0),
('comptable','dossiers',0),('comptable','medecins',0),('comptable','lits',0),
('comptable','pharmacie',0),('comptable','laboratoire',0),('comptable','rh',0),
('comptable','stocks',0),('comptable','utilisateurs',0),('comptable','parametres',0);

-- Permissions actions par défaut
INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`) VALUES
-- admin : tout autorisé
('admin','patients.create',1),('admin','patients.edit',1),('admin','patients.delete',1),
('admin','appointments.create',1),('admin','appointments.edit_statut',1),('admin','appointments.delete',1),
('admin','hospitalisations.create',1),('admin','hospitalisations.update',1),('admin','hospitalisations.sortie',1),
('admin','lits.update_statut',1),('admin','lits.create',1),
('admin','analyses.create',1),('admin','analyses.update_resultat',1),
('admin','medicaments.create',1),('admin','ordonnances.create',1),('admin','ordonnances.encaisser',1),
('admin','caisse.create_vente',1),('admin','caisse.annuler',1),
('admin','stocks.create',1),('admin','stocks.update_qte',1),
('admin','medecins.create',1),('admin','medecins.toggle_statut',1),
-- medecin
('medecin','patients.create',1),('medecin','patients.edit',1),('medecin','patients.delete',0),
('medecin','appointments.create',1),('medecin','appointments.edit_statut',1),('medecin','appointments.delete',1),
('medecin','hospitalisations.create',1),('medecin','hospitalisations.update',1),('medecin','hospitalisations.sortie',1),
('medecin','lits.update_statut',1),('medecin','lits.create',0),
('medecin','analyses.create',1),('medecin','analyses.update_resultat',1),
('medecin','medicaments.create',0),('medecin','ordonnances.create',1),('medecin','ordonnances.encaisser',0),
('medecin','caisse.create_vente',0),('medecin','caisse.annuler',0),
('medecin','stocks.create',0),('medecin','stocks.update_qte',0),
('medecin','medecins.create',0),('medecin','medecins.toggle_statut',0),
-- infirmier
('infirmier','patients.create',0),('infirmier','patients.edit',0),('infirmier','patients.delete',0),
('infirmier','appointments.create',1),('infirmier','appointments.edit_statut',1),('infirmier','appointments.delete',0),
('infirmier','hospitalisations.create',0),('infirmier','hospitalisations.update',1),('infirmier','hospitalisations.sortie',0),
('infirmier','lits.update_statut',1),('infirmier','lits.create',0),
('infirmier','analyses.create',0),('infirmier','analyses.update_resultat',1),
('infirmier','medicaments.create',0),('infirmier','ordonnances.create',0),('infirmier','ordonnances.encaisser',0),
('infirmier','caisse.create_vente',0),('infirmier','caisse.annuler',0),
('infirmier','stocks.create',0),('infirmier','stocks.update_qte',0),
('infirmier','medecins.create',0),('infirmier','medecins.toggle_statut',0),
-- pharmacien
('pharmacien','patients.create',0),('pharmacien','patients.edit',0),('pharmacien','patients.delete',0),
('pharmacien','appointments.create',0),('pharmacien','appointments.edit_statut',0),('pharmacien','appointments.delete',0),
('pharmacien','hospitalisations.create',0),('pharmacien','hospitalisations.update',0),('pharmacien','hospitalisations.sortie',0),
('pharmacien','lits.update_statut',0),('pharmacien','lits.create',0),
('pharmacien','analyses.create',0),('pharmacien','analyses.update_resultat',0),
('pharmacien','medicaments.create',1),('pharmacien','ordonnances.create',0),('pharmacien','ordonnances.encaisser',1),
('pharmacien','caisse.create_vente',1),('pharmacien','caisse.annuler',1),
('pharmacien','stocks.create',1),('pharmacien','stocks.update_qte',1),
('pharmacien','medecins.create',0),('pharmacien','medecins.toggle_statut',0),
-- comptable
('comptable','patients.create',0),('comptable','patients.edit',0),('comptable','patients.delete',0),
('comptable','appointments.create',0),('comptable','appointments.edit_statut',0),('comptable','appointments.delete',0),
('comptable','hospitalisations.create',0),('comptable','hospitalisations.update',0),('comptable','hospitalisations.sortie',0),
('comptable','lits.update_statut',0),('comptable','lits.create',0),
('comptable','analyses.create',0),('comptable','analyses.update_resultat',0),
('comptable','medicaments.create',0),('comptable','ordonnances.create',0),('comptable','ordonnances.encaisser',0),
('comptable','caisse.create_vente',0),('comptable','caisse.annuler',0),
('comptable','stocks.create',0),('comptable','stocks.update_qte',0),
('comptable','medecins.create',0),('comptable','medecins.toggle_statut',0);

-- Ordonnances de démonstration
INSERT INTO `ordonnances` (`patient_id`, `medecin_id`, `date_prescription`, `statut`) VALUES
(1, 2, DATE_SUB(NOW(), INTERVAL 3 DAY), 'terminee'),
(2, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 'active'),
(3, 3, NOW(), 'active'),
(4, 2, DATE_SUB(NOW(), INTERVAL 7 DAY), 'terminee'),
(5, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), 'active');

-- Lignes d'ordonnances (liant ordonnances aux médicaments)
INSERT INTO `ordonnance_lignes` (`ordonnance_id`, `medicament_id`, `dosage`, `frequence`, `duree`) VALUES
(1, 1, '500mg', '3x/jour', '7 jours'),
(1, 3, '10mg',  '1x/soir', '30 jours'),
(2, 2, '250mg', '2x/jour', '5 jours'),
(2, 4, '20mg',  '1x/matin', '14 jours'),
(3, 1, '1000mg','2x/jour', '10 jours'),
(3, 5, '5mg',   '1x/jour', '21 jours'),
(4, 6, '40mg',  '1x/jour', '30 jours'),
(5, 2, '500mg', '3x/jour', '7 jours'),
(5, 7, '250mg', '1x/soir', '14 jours');

-- ══════════════════════════════════════════════════════════════
--  TABLES COMPTABILITE STOCK (entrees/sorties)
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `stock_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `fournisseur` varchar(200) DEFAULT NULL,
  `type` enum('entree','sortie','transfert','ajustement') NOT NULL DEFAULT 'entree',
  `departement_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `notes` text,
  `date_entry` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entry_date` (`date_entry`),
  KEY `idx_entry_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_entry_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_entry_id` int(11) NOT NULL,
  `medicament_id` int(11) DEFAULT NULL,
  `produit` varchar(200) DEFAULT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `prix_unitaire` decimal(12,2) DEFAULT NULL,
  `prix_total` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ligne_entry` (`stock_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modules v3 : lancer aussi sql/update_v3.sql pour les nouveaux modules

COMMIT;
