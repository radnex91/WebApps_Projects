-- ============================================================================
-- MediCore ERP v3.0 - Mise a jour majeure
-- Nouveaux modules : Observations, MAR, Notes cliniques, Triage, Chirurgie,
-- Imagerie, Assurances, Maternite, Deces, Portail patient, Notifications,
-- Consentements, Audit, API, Stock entries
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Module 1.1 : Observations infirmieres (Signes vitaux)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `observations_infirmieres` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `hospitalisation_id` int(11) DEFAULT NULL,
    `utilisateur_id` int(11) NOT NULL,
    `type_observation` enum('temperature','ta_systolique','ta_diastolique','pouls','spo2','glycemie','douleur_eva','freq_respiratoire','poids','taille','bmi','autres') NOT NULL,
    `valeur` decimal(10,2) NOT NULL,
    `unite` varchar(20) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_observation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_obs_patient` (`patient_id`),
    KEY `idx_obs_hosp` (`hospitalisation_id`),
    KEY `idx_obs_user` (`utilisateur_id`),
    KEY `idx_obs_date` (`date_observation`),
    CONSTRAINT `fk_obs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_obs_hosp` FOREIGN KEY (`hospitalisation_id`) REFERENCES `hospitalisations` (`id`),
    CONSTRAINT `fk_obs_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 1.2 : Administration des medicaments (MAR)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `administration_medicaments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ordonnance_ligne_id` int(11) DEFAULT NULL,
    `medicament_id` int(11) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `utilisateur_id` int(11) NOT NULL,
    `date_administration` date NOT NULL,
    `heure_prevue` time NOT NULL,
    `heure_reelle` time DEFAULT NULL,
    `dosage_admin` varchar(50) DEFAULT NULL,
    `statut` enum('planifie','administre','non_administre','refuse','reporte') NOT NULL DEFAULT 'planifie',
    `motif_non_administration` varchar(255) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mar_patient` (`patient_id`),
    KEY `idx_mar_user` (`utilisateur_id`),
    KEY `idx_mar_date` (`date_administration`),
    KEY `idx_mar_ligne` (`ordonnance_ligne_id`),
    KEY `idx_mar_med` (`medicament_id`),
    CONSTRAINT `fk_mar_ligne` FOREIGN KEY (`ordonnance_ligne_id`) REFERENCES `ordonnance_lignes` (`id`),
    CONSTRAINT `fk_mar_med` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`),
    CONSTRAINT `fk_mar_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_mar_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 1.3 : Notes cliniques
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notes_cliniques` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `hospitalisation_id` int(11) DEFAULT NULL,
    `utilisateur_id` int(11) NOT NULL,
    `type_note` enum('admission','suivi','transmission','cr_sortie','cr_operatoire','consultation','autre') NOT NULL DEFAULT 'suivi',
    `titre` varchar(255) DEFAULT NULL,
    `subjective` text DEFAULT NULL,
    `objective` text DEFAULT NULL,
    `analyse` text DEFAULT NULL,
    `plan` text DEFAULT NULL,
    `contenu` text DEFAULT NULL,
    `date_note` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notes_patient` (`patient_id`),
    KEY `idx_notes_hosp` (`hospitalisation_id`),
    KEY `idx_notes_user` (`utilisateur_id`),
    KEY `idx_notes_date` (`date_note`),
    CONSTRAINT `fk_notes_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_notes_hosp` FOREIGN KEY (`hospitalisation_id`) REFERENCES `hospitalisations` (`id`),
    CONSTRAINT `fk_notes_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 1.4 : Triage des urgences
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `triage_urgences` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `hospitalisation_id` int(11) NOT NULL,
    `categorie_triage` enum('1_immediat','2_tres_urgent','3_urgent','4_standard','5_non_urgent') NOT NULL DEFAULT '4_standard',
    `heure_triage` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `temps_attente_max` int(11) DEFAULT NULL,
    `heure_prise_en_charge` datetime DEFAULT NULL,
    `infirmier_triage_id` int(11) DEFAULT NULL,
    `motif_consultation` text DEFAULT NULL,
    `signes_cliniques` text DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_triage_hosp` (`hospitalisation_id`),
    KEY `idx_triage_infirmier` (`infirmier_triage_id`),
    CONSTRAINT `fk_triage_hosp` FOREIGN KEY (`hospitalisation_id`) REFERENCES `hospitalisations` (`id`),
    CONSTRAINT `fk_triage_infirmier` FOREIGN KEY (`infirmier_triage_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 2.1 : Bloc operatoire / Chirurgie
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `interventions_chirurgicales` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `hospitalisation_id` int(11) DEFAULT NULL,
    `chirurgien_id` int(11) NOT NULL,
    `anesthesiste_id` int(11) DEFAULT NULL,
    `salle` varchar(50) DEFAULT NULL,
    `type_intervention` enum('programmee','urgence','ambulatoire') NOT NULL DEFAULT 'programmee',
    `nom_intervention` varchar(255) NOT NULL,
    `code_ccam` varchar(20) DEFAULT NULL,
    `date_prevue` datetime NOT NULL,
    `duree_prevue` int(11) DEFAULT 120,
    `date_debut` datetime DEFAULT NULL,
    `date_fin` datetime DEFAULT NULL,
    `statut` enum('planifiee','en_preparation','en_cours','terminee','annulee','reportee') NOT NULL DEFAULT 'planifiee',
    `anesthesie_type` enum('generale','locoregionale','locale','sedation') DEFAULT NULL,
    `asa_score` enum('I','II','III','IV','V','VI') DEFAULT NULL,
    `complications` text DEFAULT NULL,
    `notes_postop` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_interv_patient` (`patient_id`),
    KEY `idx_interv_chirurgien` (`chirurgien_id`),
    KEY `idx_interv_date` (`date_prevue`),
    CONSTRAINT `fk_interv_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_interv_chirurgien` FOREIGN KEY (`chirurgien_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `checklist_preop` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `intervention_id` int(11) NOT NULL,
    `utilisateur_id` int(11) NOT NULL,
    `identite_patient` tinyint(1) DEFAULT 0,
    `site_marque` tinyint(1) DEFAULT 0,
    `consentement` tinyint(1) DEFAULT 0,
    `allergies` tinyint(1) DEFAULT 0,
    `jeune` tinyint(1) DEFAULT 0,
    `antibioprophylaxie` tinyint(1) DEFAULT 0,
    `materiel_dispo` tinyint(1) DEFAULT 0,
    `date_checklist` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `validation_chirurgien` tinyint(1) DEFAULT 0,
    `validation_anesthesiste` tinyint(1) DEFAULT 0,
    `validation_ibode` tinyint(1) DEFAULT 0,
    `notes` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_check_interv` (`intervention_id`),
    CONSTRAINT `fk_check_interv` FOREIGN KEY (`intervention_id`) REFERENCES `interventions_chirurgicales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 2.2 : Imagerie medicale
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `examens_imagerie` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `numero` varchar(20) NOT NULL UNIQUE,
    `patient_id` int(11) NOT NULL,
    `prescripteur_id` int(11) NOT NULL,
    `radiologue_id` int(11) DEFAULT NULL,
    `type_examen` enum('radio_standard','scanner','irm','echographie','mammographie','angiographie','scintigraphie','autre') NOT NULL DEFAULT 'radio_standard',
    `zone_anatomique` varchar(100) DEFAULT NULL,
    `urgence` enum('normal','urgent','tres_urgent') NOT NULL DEFAULT 'normal',
    `motif` text DEFAULT NULL,
    `statut` enum('prescrit','planifie','realise','interprete','disponible','archive') NOT NULL DEFAULT 'prescrit',
    `date_realisation` datetime DEFAULT NULL,
    `resultat` text DEFAULT NULL,
    `fichiers_images` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_img_patient` (`patient_id`),
    KEY `idx_img_prescripteur` (`prescripteur_id`),
    UNIQUE KEY `uq_img_numero` (`numero`),
    CONSTRAINT `fk_img_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_img_prescripteur` FOREIGN KEY (`prescripteur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 2.3 : Assurances / Tiers-payant
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compagnies_assurance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nom` varchar(150) NOT NULL,
    `code` varchar(20) NOT NULL UNIQUE,
    `adresse` text DEFAULT NULL,
    `telephone` varchar(30) DEFAULT NULL,
    `email` varchar(150) DEFAULT NULL,
    `taux_prise_en_charge` decimal(5,2) DEFAULT 100.00,
    `delai_accord` int(11) DEFAULT 15,
    `statut` enum('actif','inactif') NOT NULL DEFAULT 'actif',
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prises_en_charge` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `compagnie_id` int(11) NOT NULL,
    `facture_id` int(11) DEFAULT NULL,
    `hospitalisation_id` int(11) DEFAULT NULL,
    `numero_accord` varchar(50) DEFAULT NULL,
    `date_demande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_accord` datetime DEFAULT NULL,
    `montant_demande` decimal(10,2) DEFAULT NULL,
    `montant_accorde` decimal(10,2) DEFAULT NULL,
    `taux_couvert` decimal(5,2) DEFAULT 80.00,
    `statut` enum('demande','en_attente','accorde_partiel','accorde_total','refuse') NOT NULL DEFAULT 'demande',
    `motif_refus` text DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pec_patient` (`patient_id`),
    KEY `idx_pec_compagnie` (`compagnie_id`),
    KEY `idx_pec_facture` (`facture_id`),
    CONSTRAINT `fk_pec_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_pec_compagnie` FOREIGN KEY (`compagnie_id`) REFERENCES `compagnies_assurance` (`id`),
    CONSTRAINT `fk_pec_facture` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 2.4 : Maternite / Obstetrique
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suivi_grossesses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `date_dernieres_regles` date DEFAULT NULL,
    `date_prevue_accouchement` date DEFAULT NULL,
    `type_grossesse` enum('simple','gemellaire','multiple') NOT NULL DEFAULT 'simple',
    `parite` varchar(20) DEFAULT NULL,
    `groupe_risque` enum('bas','moyen','haut') NOT NULL DEFAULT 'bas',
    `consultations` int(11) DEFAULT 0,
    `statut` enum('en_cours','accouchee','complication','terminee') NOT NULL DEFAULT 'en_cours',
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_grossesse_patient` (`patient_id`),
    CONSTRAINT `fk_grossesse_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `accouchements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `suivi_grossesse_id` int(11) DEFAULT NULL,
    `patient_id` int(11) NOT NULL,
    `sage_femme_id` int(11) DEFAULT NULL,
    `obstetricien_id` int(11) DEFAULT NULL,
    `date_accouchement` datetime NOT NULL,
    `type_accouchement` enum('voie_basse','cesarienne_programmee','cesarienne_urgence','assiste') NOT NULL DEFAULT 'voie_basse',
    `presentation` enum('cephalique','siege','transverse') DEFAULT 'cephalique',
    `peridurale` tinyint(1) DEFAULT 0,
    `episiotomie` tinyint(1) DEFAULT 0,
    `complications_mere` text DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_acc_patient` (`patient_id`),
    KEY `idx_acc_sf` (`sage_femme_id`),
    KEY `idx_acc_obst` (`obstetricien_id`),
    CONSTRAINT `fk_acc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `nouveau_nes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `accouchement_id` int(11) DEFAULT NULL,
    `nom` varchar(100) DEFAULT NULL,
    `sexe` enum('M','F','Autre') DEFAULT NULL,
    `date_naissance` datetime NOT NULL,
    `poids_grammes` int(11) DEFAULT NULL,
    `taille_cm` int(11) DEFAULT NULL,
    `perimetre_cranien` int(11) DEFAULT NULL,
    `apgar_1min` int(11) DEFAULT NULL,
    `apgar_5min` int(11) DEFAULT NULL,
    `statut` enum('vivant','decede','transfere') NOT NULL DEFAULT 'vivant',
    `complications` text DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_nn_acc` (`accouchement_id`),
    CONSTRAINT `fk_nn_acc` FOREIGN KEY (`accouchement_id`) REFERENCES `accouchements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 2.5 : Deces / Thanatologie
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificats_deces` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `hospitalisation_id` int(11) DEFAULT NULL,
    `medecin_id` int(11) NOT NULL,
    `date_heure_deces` datetime NOT NULL,
    `cause_deces` varchar(255) NOT NULL,
    `code_cim10` varchar(10) DEFAULT NULL,
    `type_deces` enum('naturel','accidentel','suicide','indetermine','enquete') NOT NULL DEFAULT 'naturel',
    `constat` text DEFAULT NULL,
    `certificat_numero` varchar(30) DEFAULT NULL,
    `sortie_corps` enum('non','famille','pompes_funebres','medecine_legale') NOT NULL DEFAULT 'non',
    `date_sortie_corps` datetime DEFAULT NULL,
    `pompes_funebres` varchar(150) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_deces_patient` (`patient_id`),
    KEY `idx_deces_medecin` (`medecin_id`),
    CONSTRAINT `fk_deces_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
    CONSTRAINT `fk_deces_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 3.3 : Portail patient
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comptes_patients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL UNIQUE,
    `code_secret` varchar(255) NOT NULL,
    `email_portail` varchar(150) DEFAULT NULL,
    `telephone_portail` varchar(30) DEFAULT NULL,
    `derniere_connexion` datetime DEFAULT NULL,
    `statut` enum('actif','inactif','bloque') NOT NULL DEFAULT 'actif',
    `tentatives_echouees` int(11) DEFAULT 0,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_modification` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cpt_patient` (`patient_id`),
    CONSTRAINT `fk_cpt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 3.4 : Notifications et alertes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int(11) DEFAULT NULL,
    `type` enum('info','alerte','critique','succes') NOT NULL DEFAULT 'info',
    `titre` varchar(255) NOT NULL,
    `message` text DEFAULT NULL,
    `lien` varchar(500) DEFAULT NULL,
    `lu` tinyint(1) DEFAULT 0,
    `date_notification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`utilisateur_id`),
    KEY `idx_notif_date` (`date_notification`),
    KEY `idx_notif_lu` (`lu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 3.5 : Consentements patients
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `consentements_patients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `utilisateur_id` int(11) DEFAULT NULL,
    `type_consentement` enum('soins','partage_donnees','recherche','sortie_contre_avis','directives_anticipees') NOT NULL,
    `statut` enum('donne','refuse','retire','inapplicable') NOT NULL DEFAULT 'donne',
    `date_consentement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_expiration` datetime DEFAULT NULL,
    `document_reference` varchar(255) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cons_patient` (`patient_id`),
    KEY `idx_cons_type` (`type_consentement`),
    CONSTRAINT `fk_cons_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Module 3.6 : Audit d'acces aux dossiers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_acces` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int(11) DEFAULT NULL,
    `patient_id` int(11) DEFAULT NULL,
    `type_acces` enum('consultation','modification','creation','suppression','export') NOT NULL DEFAULT 'consultation',
    `entite` varchar(100) DEFAULT NULL,
    `entite_id` int(11) DEFAULT NULL,
    `adresse_ip` varchar(45) DEFAULT NULL,
    `navigateur` varchar(255) DEFAULT NULL,
    `date_acces` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`utilisateur_id`),
    KEY `idx_audit_patient` (`patient_id`),
    KEY `idx_audit_date` (`date_acces`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Correctif 4.3 : Tables stock_entries et stock_entry_lignes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_entries` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int(11) NOT NULL,
    `type_entree` enum('achat','don','retour','inventaire') NOT NULL DEFAULT 'achat',
    `fournisseur` varchar(200) DEFAULT NULL,
    `reference` varchar(100) DEFAULT NULL,
    `montant_total` decimal(10,2) DEFAULT NULL,
    `statut` enum('brouillon','validee','annulee') NOT NULL DEFAULT 'brouillon',
    `notes` text DEFAULT NULL,
    `date_entree` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_user` (`utilisateur_id`),
    CONSTRAINT `fk_entry_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_entry_lignes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entry_id` int(11) NOT NULL,
    `stock_id` int(11) NOT NULL,
    `quantite` int(11) NOT NULL,
    `prix_unitaire` decimal(10,2) DEFAULT NULL,
    `date_expiration` date DEFAULT NULL,
    `total_ligne` decimal(10,2) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entryl_entry` (`entry_id`),
    KEY `idx_entryl_stock` (`stock_id`),
    CONSTRAINT `fk_entryl_entry` FOREIGN KEY (`entry_id`) REFERENCES `stock_entries` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_entryl_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Donnees de configuration
-- ----------------------------------------------------------------------------

-- Cle API pour le module API REST
INSERT IGNORE INTO `app_settings` (`cle`, `valeur`, `label`) VALUES
('api_key', 'mc_api_2026_demo_key', 'Cle API REST'),
('api_enabled', '1', 'API REST activee');

-- Compagnies d'assurance par defaut
INSERT IGNORE INTO `compagnies_assurance` (`id`, `nom`, `code`, `taux_prise_en_charge`, `statut`) VALUES
(1, 'CNPS', 'CNPS', 80.00, 'actif'),
(2, 'Allianz', 'ALLIANZ', 90.00, 'actif'),
(3, 'AXA', 'AXA', 85.00, 'actif'),
(4, 'MSH International', 'MSH', 100.00, 'actif');

-- Ajouter le statut 'decede' a la table hospitalisations
ALTER TABLE `hospitalisations` MODIFY COLUMN `statut` enum('en_cours','sorti','transfere','decede') NOT NULL DEFAULT 'en_cours';

SELECT 'Mise a jour v3.0 terminee avec succes !' AS status;
