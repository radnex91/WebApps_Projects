-- DanayLedger Backup 2026-04-10 19:13:33

-- Table: agences
CREATE TABLE `agences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `statut` enum('actif','inactif') NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `agences` VALUES ('1','DAN-001','DANAY EXPRESS - Siège','Conakry, Guinée','+224 XXX XXX XXX','contact@danayexpress.com','Directeur Général','actif','2026-04-10 16:54:30','2026-04-10 16:54:30');
INSERT INTO `agences` VALUES ('2','LOG15','Yagoua','Yagoua','000000000','yagoua@dsl.com','Sirina','actif','2026-04-10 16:59:57','2026-04-10 16:59:57');

-- Table: audit_logs
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` VALUES ('1','1','login','user','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"username\":\"admin\"}','2026-04-10 16:58:11');
INSERT INTO `audit_logs` VALUES ('2','1','create','agence','2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,'2026-04-10 16:59:57');
INSERT INTO `audit_logs` VALUES ('3','1','update','parametre','0',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,'2026-04-10 17:03:29');
INSERT INTO `audit_logs` VALUES ('4','1','update','parametre','0',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,'2026-04-10 17:08:23');
INSERT INTO `audit_logs` VALUES ('5','1','create','vehicule','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,'2026-04-10 17:14:16');
INSERT INTO `audit_logs` VALUES ('6','1','login','user','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 OPR/129.0.0.0','{\"username\":\"admin\"}','2026-04-10 18:12:46');

-- Table: categories
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` enum('recette','depense') NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('actif','inactif') NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` VALUES ('1','transport_marchandises','Transport de marchandises','recette',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('2','location_vehicule','Location de véhicule','recette',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('3','commission','Commission','recette',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('4','vente','Vente','recette',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('5','autre_recette','Autre recette','recette',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('6','carburant','Carburant','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('7','maintenance','Maintenance & Réparations','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('8','salaires','Salaires & Charges','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('9','assurance','Assurance','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('10','frais_douane','Frais de douane','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('11','loyer','Loyer','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('12','fournitures','Fournitures de bureau','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('13','telecommunications','Télécommunications','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('14','frais_route','Frais de route & Péages','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `categories` VALUES ('15','autre_depense','Autre dépense','depense',NULL,'actif','2026-04-10 16:54:30');

-- Table: depenses
CREATE TABLE `depenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) NOT NULL,
  `date_depense` date NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT 0.00,
  `categorie_id` int(11) DEFAULT NULL,
  `type_operation_id` int(11) DEFAULT NULL,
  `agence_id` int(11) DEFAULT NULL,
  `vehicule_id` int(11) DEFAULT NULL,
  `nature` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `justificatif_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `statut` enum('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `categorie_id` (`categorie_id`),
  KEY `type_operation_id` (`type_operation_id`),
  KEY `vehicule_id` (`vehicule_id`),
  KEY `validated_by` (`validated_by`),
  KEY `idx_date` (`date_depense`),
  KEY `idx_agence` (`agence_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `depenses_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depenses_ibfk_2` FOREIGN KEY (`type_operation_id`) REFERENCES `types_operations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depenses_ibfk_3` FOREIGN KEY (`agence_id`) REFERENCES `agences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depenses_ibfk_4` FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depenses_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `depenses_ibfk_6` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: justificatifs
CREATE TABLE `justificatifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `nom_fichier` varchar(255) NOT NULL,
  `chemin_fichier` varchar(500) NOT NULL,
  `type_mime` varchar(100) DEFAULT NULL,
  `taille` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `justificatifs_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: login_attempts
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_attempt` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: notifications
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'info',
  `titre` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `lien` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: parametres
CREATE TABLE `parametres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cle` varchar(100) NOT NULL,
  `valeur` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_cle` (`cle`),
  CONSTRAINT `parametres_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `parametres` VALUES ('1','app_name','DANAY EXPRESS SARL','Nom de l\'entreprise','2026-04-10 17:03:29','1');
INSERT INTO `parametres` VALUES ('2','app_currency','XAF','Devise par défaut','2026-04-10 17:03:29','1');
INSERT INTO `parametres` VALUES ('3','app_email','contact@danayexpress.com','Email de contact','2026-04-10 17:03:29','1');
INSERT INTO `parametres` VALUES ('4','app_telephone','+237 693353299','Téléphone','2026-04-10 17:03:29','1');
INSERT INTO `parametres` VALUES ('5','backup_auto','0','Sauvegarde automatique (0=non, 1=oui)','2026-04-10 17:03:29','1');
INSERT INTO `parametres` VALUES ('6','backup_frequency','monthly','Fréquence sauvegarde (daily/weekly/monthly)','2026-04-10 17:03:29','1');

-- Table: rapprochement
CREATE TABLE `rapprochement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `agence_id` int(11) DEFAULT NULL,
  `total_recettes` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_versements` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ecart` decimal(15,2) NOT NULL DEFAULT 0.00,
  `statut` enum('en_cours','valide','ecart_detecte') NOT NULL DEFAULT 'en_cours',
  `validated_by` int(11) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `agence_id` (`agence_id`),
  KEY `validated_by` (`validated_by`),
  KEY `idx_dates` (`date_debut`,`date_fin`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `rapprochement_ibfk_1` FOREIGN KEY (`agence_id`) REFERENCES `agences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rapprochement_ibfk_2` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: recettes_camions
CREATE TABLE `recettes_camions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) NOT NULL,
  `date_recette` date NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT 0.00,
  `vehicule_id` int(11) DEFAULT NULL,
  `agence_id` int(11) DEFAULT NULL,
  `categorie_id` int(11) DEFAULT NULL,
  `trajet` varchar(200) DEFAULT NULL,
  `client` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `justificatif_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `statut` enum('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `categorie_id` (`categorie_id`),
  KEY `created_by` (`created_by`),
  KEY `validated_by` (`validated_by`),
  KEY `idx_date` (`date_recette`),
  KEY `idx_vehicule` (`vehicule_id`),
  KEY `idx_agence` (`agence_id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `recettes_camions_ibfk_1` FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_camions_ibfk_2` FOREIGN KEY (`agence_id`) REFERENCES `agences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_camions_ibfk_3` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_camions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `recettes_camions_ibfk_5` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: recettes_journalieres
CREATE TABLE `recettes_journalieres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) NOT NULL,
  `date_recette` date NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT 0.00,
  `categorie_id` int(11) DEFAULT NULL,
  `type_operation_id` int(11) DEFAULT NULL,
  `agence_id` int(11) DEFAULT NULL,
  `vehicule_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `justificatif_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `statut` enum('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `categorie_id` (`categorie_id`),
  KEY `type_operation_id` (`type_operation_id`),
  KEY `vehicule_id` (`vehicule_id`),
  KEY `validated_by` (`validated_by`),
  KEY `idx_date` (`date_recette`),
  KEY `idx_agence` (`agence_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `recettes_journalieres_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_journalieres_ibfk_2` FOREIGN KEY (`type_operation_id`) REFERENCES `types_operations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_journalieres_ibfk_3` FOREIGN KEY (`agence_id`) REFERENCES `agences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_journalieres_ibfk_4` FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recettes_journalieres_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `recettes_journalieres_ibfk_6` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: sauvegardes
CREATE TABLE `sauvegardes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_fichier` varchar(255) NOT NULL,
  `taille` int(11) DEFAULT NULL,
  `type` enum('auto','manuel') NOT NULL DEFAULT 'manuel',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `sauvegardes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: types_operations
CREATE TABLE `types_operations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` enum('recette','depense','les_deux') NOT NULL DEFAULT 'les_deux',
  `description` text DEFAULT NULL,
  `statut` enum('actif','inactif') NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `types_operations` VALUES ('1','transport','Transport','les_deux',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('2','location','Location','recette',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('3','achat_carburant','Achat carburant','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('4','reparation','Réparation','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('5','paiement_salaire','Paiement salaire','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('6','versement_banque','Versement en banque','les_deux',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('7','retrait','Retrait','depense',NULL,'actif','2026-04-10 16:54:30');
INSERT INTO `types_operations` VALUES ('8','encaissement','Encaissement','recette',NULL,'actif','2026-04-10 16:54:30');

-- Table: users
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','utilisateur','visiteur') NOT NULL DEFAULT 'visiteur',
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES ('1','admin','admin@danayexpress.com','$2y$10$gUdSB1CaKdBjQ0sSe/xMyOvjk2f6zWtBFFOEr6eMYRIKGRrnvhDUC','Administrateur','admin',NULL,'1','2026-04-10 18:12:46','2026-04-10 16:54:30','2026-04-10 18:12:46');

-- Table: vehicules
CREATE TABLE `vehicules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `immatriculation` varchar(20) NOT NULL,
  `marque` varchar(50) DEFAULT NULL,
  `modele` varchar(50) DEFAULT NULL,
  `annee` int(11) DEFAULT NULL,
  `type_vehicule` varchar(50) DEFAULT NULL,
  `agence_id` int(11) DEFAULT NULL,
  `statut` enum('actif','inactif','en_maintenance') NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `immatriculation` (`immatriculation`),
  KEY `idx_agence` (`agence_id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `vehicules_ibfk_1` FOREIGN KEY (`agence_id`) REFERENCES `agences` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `vehicules` VALUES ('1','NO 0025 BB','MERCEDES','MCV2044','2025','Camion',NULL,'actif','2026-04-10 17:14:16','2026-04-10 17:14:16');

-- Table: versements_bancaires
CREATE TABLE `versements_bancaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) NOT NULL,
  `date_versement` date NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT 0.00,
  `agence_id` int(11) DEFAULT NULL,
  `banque` varchar(100) DEFAULT NULL,
  `numero_compte` varchar(50) DEFAULT NULL,
  `reference_bancaire` varchar(100) DEFAULT NULL,
  `recette_id` int(11) DEFAULT NULL,
  `recette_camion_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `justificatif_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `statut` enum('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `recette_id` (`recette_id`),
  KEY `recette_camion_id` (`recette_camion_id`),
  KEY `created_by` (`created_by`),
  KEY `validated_by` (`validated_by`),
  KEY `idx_date` (`date_versement`),
  KEY `idx_agence` (`agence_id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `versements_bancaires_ibfk_1` FOREIGN KEY (`agence_id`) REFERENCES `agences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `versements_bancaires_ibfk_2` FOREIGN KEY (`recette_id`) REFERENCES `recettes_journalieres` (`id`) ON DELETE SET NULL,
  CONSTRAINT `versements_bancaires_ibfk_3` FOREIGN KEY (`recette_camion_id`) REFERENCES `recettes_camions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `versements_bancaires_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `versements_bancaires_ibfk_5` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


