-- ============================================================
--  MediCore ERP — Script de MISE À JOUR
--  Exécutez ce script si vous avez déjà importé medicore.sql
--  et souhaitez ajouter les nouvelles tables/colonnes
-- ============================================================

-- 1. Table des paramètres application
CREATE TABLE IF NOT EXISTS `app_settings` (
  `cle`    varchar(60)   NOT NULL,
  `valeur` varchar(500)  NOT NULL DEFAULT '',
  `label`  varchar(120)  NOT NULL DEFAULT '',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `app_settings` (`cle`, `valeur`) VALUES
('app_name','MediCore ERP'),('etablissement',''),('adresse',''),
('telephone',''),('email_contact',''),('currency_code','XAF'),
('currency_symbol','FCFA'),('currency_decimals','0'),
('currency_position','after'),('currency_dec_sep',','),
('currency_thou_sep',' '),('font_body','DM Sans'),
('font_heading','Playfair Display'),('font_size','14'),
('date_format','d/m/Y'),('theme_primary','1E3A5F'),
('theme_accent','3b82f6'),('theme_mode','dark'),
('logo_base64',''),('logo_nom',''),('entete_rapport','');

-- 2. Tables de gestion des rôles et permissions
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

INSERT IGNORE INTO `roles_config` (`role`,`label`,`couleur`,`description`) VALUES
('admin','Administrateur','#ef4444','Accès total'),
('medecin','Médecin','#3b82f6','Accès clinique complet'),
('infirmier','Infirmier(ère)','#10b981','Soins et suivi'),
('pharmacien','Pharmacien','#8b5cf6','Pharmacie et stocks'),
('comptable','Comptable','#f59e0b','Facturation et rapports');

CREATE TABLE IF NOT EXISTS `role_page_access` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`    VARCHAR(50)  NOT NULL,
  `page`    VARCHAR(50)  NOT NULL,
  `allowed` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_page` (`role`,`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_action_access` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`    VARCHAR(50)  NOT NULL,
  `action`  VARCHAR(100) NOT NULL,
  `allowed` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_action` (`role`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Colonnes manquantes dans patients
ALTER TABLE `patients`
  ADD COLUMN IF NOT EXISTS `statut` ENUM('actif','inactif','decede') NOT NULL DEFAULT 'actif',
  ADD COLUMN IF NOT EXISTS `departement_id` INT(11) DEFAULT NULL;

-- 4. Colonnes manquantes dans factures
ALTER TABLE `factures`
  ADD COLUMN IF NOT EXISTS `date_reglement` DATETIME DEFAULT NULL;

-- 5. Tables caisse (si pas encore créées)
CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `numero_ticket`  VARCHAR(30)  NOT NULL UNIQUE,
  `patient_id`     INT(11)      DEFAULT NULL,
  `caissier_id`    INT(11)      DEFAULT NULL,
  `montant_total`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `montant_recu`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `monnaie_rendue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `mode_paiement`  ENUM('especes','carte','mobile_money','cheque','virement') NOT NULL DEFAULT 'especes',
  `statut`         ENUM('paye','annule','rembourse') NOT NULL DEFAULT 'paye',
  `ordonnance_id`  INT(11)      DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `date_vente`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `caisse_lignes` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `vente_id`     INT(11)      NOT NULL,
  `medicament_id` INT(11)     DEFAULT NULL,
  `designation`  VARCHAR(200) NOT NULL DEFAULT '',
  `quantite`     INT(11)      NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_ligne`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Colonne avatar_initiales dans utilisateurs (si manquante)
ALTER TABLE `utilisateurs`
  ADD COLUMN IF NOT EXISTS `avatar_initiales` VARCHAR(3) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `derniere_connexion` DATETIME DEFAULT NULL;


-- CORRECTION CRITIQUE : changer role ENUM -> VARCHAR pour les rôles personnalisés
-- Sans ça, MySQL rejette silencieusement les rôles hors de la liste ENUM
ALTER TABLE `utilisateurs` MODIFY COLUMN `role` varchar(50) NOT NULL DEFAULT 'infirmier';

-- 6b. Autoriser NULL sur les FK vers utilisateurs (suppression propre)
ALTER TABLE analyses MODIFY COLUMN prescripteur_id INT(11) DEFAULT NULL;
ALTER TABLE caisse_ventes MODIFY COLUMN caissier_id INT(11) DEFAULT NULL;
ALTER TABLE hospitalisations MODIFY COLUMN medecin_id INT(11) DEFAULT NULL;
ALTER TABLE ordonnances MODIFY COLUMN medecin_id INT(11) DEFAULT NULL;
ALTER TABLE rendez_vous MODIFY COLUMN medecin_id INT(11) DEFAULT NULL;

-- 7. Tables entrée stock (bon de réception)
CREATE TABLE IF NOT EXISTS `stock_entries` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(30)   NOT NULL UNIQUE,
  `type`            ENUM('stock','medicament') NOT NULL DEFAULT 'stock',
  `fournisseur`     VARCHAR(200)  DEFAULT NULL,
  `date_reception`  DATE          NOT NULL,
  `notes`           TEXT          DEFAULT NULL,
  `utilisateur_id`  INT(11)       DEFAULT NULL,
  `statut`          ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
  `montant_total`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_se_statut` (`statut`),
  KEY `idx_se_date` (`date_reception`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_entry_lignes` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `entry_id`       INT(11)       NOT NULL,
  `article_id`     INT(11)       NOT NULL,
  `article_type`   ENUM('stock','medicament') NOT NULL DEFAULT 'stock',
  `quantite`       INT(11)       NOT NULL DEFAULT 0,
  `prix_unitaire`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_ligne`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sel_entry` (`entry_id`),
  CONSTRAINT `fk_sel_entry` FOREIGN KEY (`entry_id`) REFERENCES `stock_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permission entrée stock
INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`) VALUES
('admin','stocks.entry',1), ('pharmacien','stocks.entry',1);

SELECT 'Mise a jour terminee avec succes!' AS status;
