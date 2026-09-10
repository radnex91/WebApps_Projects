-- ============================================================
--  MediCore - update_v6.sql : Module Accueil (salle d'attente)
--  Idempotent : ré-exécutable sans erreur.
-- ============================================================

-- 1) Table arrivees_patients (file d'attente du jour)
CREATE TABLE IF NOT EXISTS `arrivees_patients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `patient_id` INT NOT NULL,
  `cree_par` INT NOT NULL,
  `date_arrivee` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` ENUM('arrive','constantes_prises','en_consultation','termine','parti') NOT NULL DEFAULT 'arrive',
  `motif` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `constantes_prises_par` INT NULL,
  `date_constantes` DATETIME NULL,
  `medecin_id` INT NULL,
  `date_prise_en_charge` DATETIME NULL,
  `date_fin` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arrivee_patient_date` (`patient_id`,`date_arrivee`),
  KEY `idx_arrivee_statut` (`statut`),
  KEY `idx_arrivee_date` (`date_arrivee`),
  CONSTRAINT `fk_arrivee_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_arrivee_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_arrivee_constantes_par` FOREIGN KEY (`constantes_prises_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_arrivee_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Setting : avertissement souple dossier/consultation à l'accueil
INSERT IGNORE INTO `app_settings` (`cle`,`valeur`,`label`) VALUES
  ('accueil_verifier_consultation','1','Vérifier dossier/consultation à l''accueil (0/1)');

-- 3) Permissions page : admin + infirmier
INSERT IGNORE INTO `role_page_access` (`role`,`page`,`allowed`) VALUES
  ('admin','accueil',1),
  ('infirmier','accueil',1),
  ('medecin','accueil',0);

-- 4) Permissions actions
INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`) VALUES
  ('admin','accueil.checkin',1),
  ('admin','accueil.vitals',1),
  ('admin','accueil.orienter',1),
  ('infirmier','accueil.checkin',1),
  ('infirmier','accueil.vitals',1),
  ('infirmier','accueil.orienter',0),
  ('medecin','accueil.checkin',0),
  ('medecin','accueil.vitals',0),
  ('medecin','accueil.orienter',0);