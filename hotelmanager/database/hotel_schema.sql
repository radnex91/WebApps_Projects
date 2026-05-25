-- ============================================================
-- HotelPro Suite - Schéma de base de données complet
-- Compatible MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+01:00";
CREATE DATABASE IF NOT EXISTS `hotelmanager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hotelmanager`;

-- ============================================================
-- TABLE: roles
-- ============================================================
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(50)  NOT NULL,
  `permissions` JSON         NOT NULL COMMENT 'Liste des permissions JSON',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`nom`, `permissions`) VALUES
('admin',      '["all"]'),
('manager',    '["reservations","chambres","clients","facturation","rapports","personnel_view"]'),
('reception',  '["reservations","clients","facturation_view","chambres_view"]'),
('comptable',  '["facturation","paiements","rapports"]');

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`      INT UNSIGNED NOT NULL,
  `nom`          VARCHAR(100) NOT NULL,
  `prenom`       VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL,
  `telephone`    VARCHAR(20)  DEFAULT NULL,
  `password`     VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `statut`       ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
  `last_login`   TIMESTAMP    NULL DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mot de passe par défaut: Admin@2025 (à changer à la première connexion)
INSERT INTO `users` (`role_id`,`nom`,`prenom`,`email`,`telephone`,`password`,`statut`) VALUES
(1,'Admin','Système','admin@hotel.cm','+237600000001','$2y$12$sampleHashedPasswordHere000001','actif'),
(2,'Dupont','Marie','manager@hotel.cm','+237600000002','$2y$12$sampleHashedPasswordHere000002','actif'),
(3,'Biya','Jean','reception@hotel.cm','+237600000003','$2y$12$sampleHashedPasswordHere000003','actif');

-- ============================================================
-- TABLE: types_chambres
-- ============================================================
CREATE TABLE `types_chambres` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`          VARCHAR(80)  NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `capacite`     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `tarif_nuit`   DECIMAL(10,2) NOT NULL,
  `equipements`  JSON         DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `types_chambres` (`nom`,`description`,`capacite`,`tarif_nuit`,`equipements`) VALUES
('Simple',  'Chambre standard pour 1 personne',  1, 15000, '["WiFi","TV","Climatisation","Eau chaude"]'),
('Double',  'Chambre confortable pour 2 personnes',2, 25000, '["WiFi","TV","Climatisation","Eau chaude","Minibar"]'),
('Suite',   'Suite spacieuse avec salon séparé',   2, 55000, '["WiFi","TV","Climatisation","Eau chaude","Minibar","Jacuzzi","Coffre-fort"]'),
('VIP',     'Suite présidentielle luxueuse',       4, 95000, '["WiFi","TV 4K","Climatisation","Eau chaude","Minibar","Jacuzzi","Coffre-fort","Butler","Terrasse"]'),
('Familiale','Grande chambre pour famille',        4, 40000, '["WiFi","TV","Climatisation","Eau chaude","Lit bébé disponible"]');

-- ============================================================
-- TABLE: chambres
-- ============================================================
CREATE TABLE `chambres` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_id`         INT UNSIGNED NOT NULL,
  `numero`          VARCHAR(10)  NOT NULL,
  `etage`           TINYINT      NOT NULL DEFAULT 0,
  `statut`          ENUM('disponible','occupee','nettoyage','maintenance') NOT NULL DEFAULT 'disponible',
  `description`     TEXT         DEFAULT NULL,
  `photo`           VARCHAR(255) DEFAULT NULL,
  `notes_internes`  TEXT         DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chambre_numero` (`numero`),
  KEY `fk_chambre_type` (`type_id`),
  KEY `idx_chambre_statut` (`statut`),
  CONSTRAINT `fk_chambre_type` FOREIGN KEY (`type_id`) REFERENCES `types_chambres` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `chambres` (`type_id`,`numero`,`etage`,`statut`) VALUES
(1,'101',1,'disponible'),(1,'102',1,'occupee'),(1,'103',1,'disponible'),
(2,'104',1,'occupee'),(2,'105',1,'nettoyage'),(2,'106',1,'disponible'),
(2,'107',1,'disponible'),(2,'108',1,'maintenance'),
(3,'201',2,'disponible'),(3,'202',2,'occupee'),(3,'203',2,'disponible'),
(4,'301',3,'disponible'),(4,'302',3,'occupee'),
(5,'401',4,'disponible'),(5,'402',4,'disponible');

-- ============================================================
-- TABLE: clients
-- ============================================================
CREATE TABLE `clients` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(20)  NOT NULL COMMENT 'CLT-YYYY-NNNNN',
  `nom`             VARCHAR(100) NOT NULL,
  `prenom`          VARCHAR(100) NOT NULL,
  `email`           VARCHAR(150) DEFAULT NULL,
  `telephone`       VARCHAR(20)  NOT NULL,
  `nationalite`     VARCHAR(60)  DEFAULT NULL,
  `type_piece`      ENUM('CNI','Passeport','Permis','Carte_sejour','Autre') DEFAULT 'CNI',
  `numero_piece`    VARCHAR(50)  DEFAULT NULL,
  `date_naissance`  DATE         DEFAULT NULL,
  `adresse`         TEXT         DEFAULT NULL,
  `ville`           VARCHAR(80)  DEFAULT NULL,
  `pays`            VARCHAR(60)  DEFAULT 'Cameroun',
  `type_client`     ENUM('standard','fidele','vip','professionnel') NOT NULL DEFAULT 'standard',
  `points_fidelite` INT UNSIGNED NOT NULL DEFAULT 0,
  `notes`           TEXT         DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_ref` (`reference`),
  KEY `idx_client_telephone` (`telephone`),
  KEY `idx_client_nom` (`nom`,`prenom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `clients` (`reference`,`nom`,`prenom`,`email`,`telephone`,`nationalite`,`type_piece`,`numero_piece`,`type_client`) VALUES
('CLT-2025-00001','Mbarga','Jean-Paul','jean.mbarga@email.cm','+237677001001','Camerounaise','CNI','123456789','fidele'),
('CLT-2025-00002','Ngono','Alice','alice.ngono@email.cm','+237677002002','Camerounaise','Passeport','CM1234567','standard'),
('CLT-2025-00003','Kamga','Pierre','pierre.kamga@email.cm','+237677003003','Camerounaise','CNI','987654321','vip'),
('CLT-2025-00004','Mvondo','Sophie','s.mvondo@corp.cm','+237677004004','Camerounaise','CNI','456789123','professionnel');

-- ============================================================
-- TABLE: reservations
-- ============================================================
CREATE TABLE `reservations` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(20)  NOT NULL COMMENT 'RES-YYYYMM-NNNNN',
  `client_id`       INT UNSIGNED NOT NULL,
  `chambre_id`      INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL COMMENT 'Créé par',
  `date_arrivee`    DATE         NOT NULL,
  `date_depart`     DATE         NOT NULL,
  `nb_nuits`        TINYINT UNSIGNED NOT NULL,
  `nb_adultes`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `nb_enfants`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tarif_nuit`      DECIMAL(10,2) NOT NULL COMMENT 'Snapshot du tarif',
  `montant_total`   DECIMAL(10,2) NOT NULL,
  `statut`          ENUM('en_attente','confirmee','checkin','checkout','annulee','no_show') NOT NULL DEFAULT 'en_attente',
  `source`          ENUM('direct','telephone','internet','agence') NOT NULL DEFAULT 'direct',
  `notes`           TEXT         DEFAULT NULL,
  `checkin_at`      TIMESTAMP    NULL DEFAULT NULL,
  `checkout_at`     TIMESTAMP    NULL DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_res_reference` (`reference`),
  KEY `fk_res_client` (`client_id`),
  KEY `fk_res_chambre` (`chambre_id`),
  KEY `fk_res_user` (`user_id`),
  KEY `idx_res_dates` (`date_arrivee`,`date_depart`),
  KEY `idx_res_statut` (`statut`),
  CONSTRAINT `fk_res_client`  FOREIGN KEY (`client_id`)  REFERENCES `clients` (`id`),
  CONSTRAINT `fk_res_chambre` FOREIGN KEY (`chambre_id`) REFERENCES `chambres` (`id`),
  CONSTRAINT `fk_res_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `reservations` (`reference`,`client_id`,`chambre_id`,`user_id`,`date_arrivee`,`date_depart`,`nb_nuits`,`tarif_nuit`,`montant_total`,`statut`) VALUES
('RES-202504-00001',1,10,3,'2026-04-24','2026-04-27',3,55000,165000,'checkin'),
('RES-202504-00002',2,4,3, '2026-04-24','2026-04-25',1,25000,25000, 'checkin'),
('RES-202504-00003',3,12,3,'2026-04-24','2026-04-29',5,95000,475000,'checkin'),
('RES-202504-00004',4,6,3, '2026-04-26','2026-04-28',2,25000,50000, 'confirmee');

-- ============================================================
-- TABLE: factures
-- ============================================================
CREATE TABLE `factures` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(20)  NOT NULL COMMENT 'FAC-YYYYMM-NNNNN',
  `reservation_id`  INT UNSIGNED NOT NULL,
  `client_id`       INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL COMMENT 'Émis par',
  `sous_total`      DECIMAL(10,2) NOT NULL,
  `tva_taux`        DECIMAL(5,2)  NOT NULL DEFAULT 19.25,
  `tva_montant`     DECIMAL(10,2) NOT NULL,
  `remise`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_ttc`       DECIMAL(10,2) NOT NULL,
  `statut`          ENUM('brouillon','emise','payee','annulee') NOT NULL DEFAULT 'brouillon',
  `date_emission`   DATE         NOT NULL,
  `date_echeance`   DATE         DEFAULT NULL,
  `notes`           TEXT         DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fac_reference` (`reference`),
  KEY `fk_fac_reservation` (`reservation_id`),
  KEY `fk_fac_client` (`client_id`),
  KEY `fk_fac_user` (`user_id`),
  CONSTRAINT `fk_fac_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  CONSTRAINT `fk_fac_client`      FOREIGN KEY (`client_id`)      REFERENCES `clients` (`id`),
  CONSTRAINT `fk_fac_user`        FOREIGN KEY (`user_id`)        REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: paiements
-- ============================================================
CREATE TABLE `paiements` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `facture_id`    INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL COMMENT 'Encaissé par',
  `montant`       DECIMAL(10,2) NOT NULL,
  `mode`          ENUM('especes','mobile_money','carte','virement','cheque') NOT NULL DEFAULT 'especes',
  `reference_paiement` VARCHAR(100) DEFAULT NULL COMMENT 'Nº transaction mobile/carte',
  `date_paiement` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`         TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pai_facture` (`facture_id`),
  KEY `fk_pai_user` (`user_id`),
  CONSTRAINT `fk_pai_facture` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`),
  CONSTRAINT `fk_pai_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: logs (audit trail)
-- ============================================================
CREATE TABLE `logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `module`      VARCHAR(50)     NOT NULL,
  `objet_type`  VARCHAR(50)     DEFAULT NULL,
  `objet_id`    INT UNSIGNED    DEFAULT NULL,
  `details`     TEXT            DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(255)    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_module` (`module`),
  KEY `idx_log_date` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Vue utilitaire: disponibilité des chambres
-- ============================================================
CREATE OR REPLACE VIEW `v_chambres_disponibles` AS
  SELECT c.*, tc.nom AS type_nom, tc.tarif_nuit, tc.capacite
  FROM chambres c
  JOIN types_chambres tc ON tc.id = c.type_id
  WHERE c.statut = 'disponible';

-- ============================================================
-- Vue utilitaire: réservations avec détails
-- ============================================================
CREATE OR REPLACE VIEW `v_reservations_detail` AS
  SELECT
    r.*,
    CONCAT(cl.nom,' ',cl.prenom) AS client_nom,
    cl.telephone AS client_tel,
    ch.numero AS chambre_numero,
    tc.nom AS type_chambre,
    CONCAT(u.prenom,' ',u.nom) AS agent_nom
  FROM reservations r
  JOIN clients cl      ON cl.id = r.client_id
  JOIN chambres ch     ON ch.id = r.chambre_id
  JOIN types_chambres tc ON tc.id = ch.type_id
  JOIN users u         ON u.id  = r.user_id;
