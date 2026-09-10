-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: pharmacare
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` varchar(500) DEFAULT '',
  `ip` varchar(45) DEFAULT '0.0.0.0',
  `target_id` int(11) DEFAULT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_user` (`utilisateur_id`),
  KEY `idx_audit_date` (`created_at`),
  KEY `idx_audit_ref` (`reference`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
-- [PROD CLEAN] donnees dev de `audit_log` videes (historique/transactionnel de test)
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caisses`
--

DROP TABLE IF EXISTS `caisses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caisses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caisses`
--

LOCK TABLES `caisses` WRITE;
/*!40000 ALTER TABLE `caisses` DISABLE KEYS */;
INSERT INTO `caisses` VALUES (1,'Caisse 1',1,'2026-05-06 19:59:26'),(2,'Caisse 2',1,'2026-05-06 19:59:26'),(3,'Caisse 3',1,'2026-05-06 19:59:26');
/*!40000 ALTER TABLE `caisses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campagnes_promo`
--

DROP TABLE IF EXISTS `campagnes_promo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campagnes_promo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('pourcentage','montant_fixe') NOT NULL DEFAULT 'pourcentage',
  `valeur` decimal(10,2) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campagnes_promo`
--

LOCK TABLES `campagnes_promo` WRITE;
/*!40000 ALTER TABLE `campagnes_promo` DISABLE KEYS */;
/*!40000 ALTER TABLE `campagnes_promo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `couleur` varchar(7) DEFAULT '#00c9a7',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Antalgiques','#00c9a7'),(2,'Antibiotiques','#4895ef'),(3,'Anti-inflammatoires','#f0b429'),(4,'Antipaludiques','#ef4444'),(5,'Vitamines & Compléments','#9b59b6'),(6,'Cardiologie & Hypertension','#e74c3c'),(7,'Diabétologie','#e67e22'),(8,'Respiratoire','#1abc9c'),(9,'Gastro-entérologie','#3498db'),(10,'Allergologie','#e91e63'),(11,'Dermatologie','#795548'),(12,'Antiparasitaires','#2ecc71'),(15,'Examen','#00c9a7');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `codes_remise`
--

DROP TABLE IF EXISTS `codes_remise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `codes_remise` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `remise_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `used` tinyint(1) DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `used_vente_id` int(11) DEFAULT NULL,
  `used_remise_pct` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `created_by` (`created_by`),
  KEY `used_vente_id` (`used_vente_id`),
  CONSTRAINT `codes_remise_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `codes_remise_ibfk_2` FOREIGN KEY (`used_vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `codes_remise`
--

LOCK TABLES `codes_remise` WRITE;
/*!40000 ALTER TABLE `codes_remise` DISABLE KEYS */;
INSERT INTO `codes_remise` VALUES (1,'E33442',1,'2026-07-21 19:44:09','2026-07-21 19:59:09',5.00,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `codes_remise` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commande_lignes`
--

DROP TABLE IF EXISTS `commande_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commande_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commande_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `designation` varchar(200) NOT NULL,
  `quantite` int(11) DEFAULT 1,
  `prix_unitaire` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `commande_lignes_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commande_lignes_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_lignes`
--

LOCK TABLES `commande_lignes` WRITE;
/*!40000 ALTER TABLE `commande_lignes` DISABLE KEYS */;
/*!40000 ALTER TABLE `commande_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) NOT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `statut` enum('en_attente','en_cours','livrée','annulée') DEFAULT 'en_attente',
  `date_commande` date DEFAULT NULL,
  `date_livraison` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `montant_total` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `fournisseur_id` (`fournisseur_id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commandes`
--

LOCK TABLES `commandes` WRITE;
/*!40000 ALTER TABLE `commandes` DISABLE KEYS */;
/*!40000 ALTER TABLE `commandes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compteurs_ref`
--

DROP TABLE IF EXISTS `compteurs_ref`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compteurs_ref` (
  `prefix` varchar(8) NOT NULL,
  `annee` smallint(6) NOT NULL,
  `compteur` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`prefix`,`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compteurs_ref`
--

LOCK TABLES `compteurs_ref` WRITE;
/*!40000 ALTER TABLE `compteurs_ref` DISABLE KEYS */;
INSERT INTO `compteurs_ref` VALUES ('CMD',2026,0),('TRF',2026,0),('TVP',2026,0),('VNT',2026,0);
/*!40000 ALTER TABLE `compteurs_ref` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ecriture_lignes`
--

DROP TABLE IF EXISTS `ecriture_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ecriture_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ecriture_id` int(11) NOT NULL,
  `compte_id` int(11) NOT NULL,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `libelle_ligne` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ecriture_id` (`ecriture_id`),
  KEY `compte_id` (`compte_id`),
  CONSTRAINT `ecriture_lignes_ibfk_1` FOREIGN KEY (`ecriture_id`) REFERENCES `ecritures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ecriture_lignes_ibfk_2` FOREIGN KEY (`compte_id`) REFERENCES `plan_comptable` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ecriture_lignes`
--

LOCK TABLES `ecriture_lignes` WRITE;
/*!40000 ALTER TABLE `ecriture_lignes` DISABLE KEYS */;
-- [PROD CLEAN] donnees dev de `ecriture_lignes` videes (historique/transactionnel de test)
/*!40000 ALTER TABLE `ecriture_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ecritures`
--

DROP TABLE IF EXISTS `ecritures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ecritures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `date_ecriture` date NOT NULL,
  `exercice_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `source` varchar(30) DEFAULT 'manuel',
  `source_ref` varchar(30) DEFAULT NULL,
  `verrouillee` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `idx_ecr_date` (`date_ecriture`),
  KEY `idx_ecr_created` (`created_at`),
  KEY `idx_ecr_exercice` (`exercice_id`),
  CONSTRAINT `ecritures_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ecritures`
--

LOCK TABLES `ecritures` WRITE;
/*!40000 ALTER TABLE `ecritures` DISABLE KEYS */;
-- [PROD CLEAN] donnees dev de `ecritures` videes (historique/transactionnel de test)
/*!40000 ALTER TABLE `ecritures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exercices`
--

DROP TABLE IF EXISTS `exercices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exercices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(9) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `cloture` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exercices`
--

LOCK TABLES `exercices` WRITE;
/*!40000 ALTER TABLE `exercices` DISABLE KEYS */;
INSERT INTO `exercices` VALUES (1,'2026','Exercice 2026','2026-01-01','2026-12-31',0,'2026-05-09 17:25:41');
/*!40000 ALTER TABLE `exercices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fidelite_points`
--

DROP TABLE IF EXISTS `fidelite_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fidelite_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `type` enum('gagné','utilisé') NOT NULL DEFAULT 'gagné',
  `reference` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `fidelite_points_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fidelite_points`
--

LOCK TABLES `fidelite_points` WRITE;
/*!40000 ALTER TABLE `fidelite_points` DISABLE KEYS */;
/*!40000 ALTER TABLE `fidelite_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fournisseurs`
--

DROP TABLE IF EXISTS `fournisseurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fournisseurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fournisseurs`
--

LOCK TABLES `fournisseurs` WRITE;
/*!40000 ALTER TABLE `fournisseurs` DISABLE KEYS */;
INSERT INTO `fournisseurs` VALUES (1,'CENAME','Service commercial','+237 222 20 60 00','contact@cename.cm',NULL,'Yaoundé',1,'2026-07-21 18:37:56'),(2,'Laborex Cameroun','Service commercial','+237 233 42 30 00','contact@laborex.cm',NULL,'Douala',1,'2026-07-21 18:37:56'),(3,'Ubapharm','Service commercial','+237 233 42 11 11','contact@ubapharm.cm',NULL,'Douala',1,'2026-07-21 18:37:56'),(4,'Medipro Cameroun','Service commercial','+237 222 23 45 67','contact@medipro.cm',NULL,'Yaoundé',1,'2026-07-21 18:37:56'),(5,'Sipharm','Service commercial','+237 233 43 22 22','contact@sipharm.cm',NULL,'Douala',1,'2026-07-21 18:37:56');
/*!40000 ALTER TABLE `fournisseurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `code` varchar(60) NOT NULL,
  `libelle` varchar(120) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `position` int(11) DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menus`
--

LOCK TABLES `menus` WRITE;
/*!40000 ALTER TABLE `menus` DISABLE KEYS */;
INSERT INTO `menus` VALUES ('caisse','Caisses',1,4),('categories','Catégories',1,20),('clients','Clients',1,8),('commandes','Commandes',1,9),('comptabilite','Comptabilité',1,16),('dashboard','Tableau de bord',1,1),('fournisseurs','Fournisseurs',1,7),('magasin','Magasin',1,11),('marketing','Marketing',1,12),('parametres','Paramètres',1,22),('pharmacies','Pharmacies',1,21),('produits','Médicaments',1,6),('rapports','Rapports',1,14),('rapports_caissier','Mes Rapports',1,15),('remise_approbateurs','Approbateurs de remise',1,18),('remise_codes','Codes de remise',1,3),('retours','Retours caisse',0,10),('roles','Rôles & Permissions',1,19),('stock','Stock',1,5),('utilisateurs','Utilisateurs',1,17),('vente','Point de Vente',1,2),('ventes_hist','Historique ventes',1,13);
/*!40000 ALTER TABLE `menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mouvements_caisse`
--

DROP TABLE IF EXISTS `mouvements_caisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mouvements_caisse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `type` enum('entrée','sortie') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `motif` varchar(255) NOT NULL,
  `moyen` enum('espèces','carte','chèque','assurance','crédit') NOT NULL DEFAULT 'espèces',
  `reference_vente` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  CONSTRAINT `mouvements_caisse_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions_caisse` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_caisse`
--

LOCK TABLES `mouvements_caisse` WRITE;
/*!40000 ALTER TABLE `mouvements_caisse` DISABLE KEYS */;
-- [PROD CLEAN] donnees dev de `mouvements_caisse` videes (historique/transactionnel de test)
/*!40000 ALTER TABLE `mouvements_caisse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mouvements_magasin`
--

DROP TABLE IF EXISTS `mouvements_magasin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mouvements_magasin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produit_id` int(11) DEFAULT NULL,
  `type` enum('entrée','sortie','ajustement') NOT NULL,
  `quantite` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `transfert_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produit_id` (`produit_id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `idx_mmag_date` (`created_at`),
  CONSTRAINT `mouvements_magasin_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mouvements_magasin_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_magasin`
--

LOCK TABLES `mouvements_magasin` WRITE;
/*!40000 ALTER TABLE `mouvements_magasin` DISABLE KEYS */;
/*!40000 ALTER TABLE `mouvements_magasin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mouvements_stock`
--

DROP TABLE IF EXISTS `mouvements_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mouvements_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produit_id` int(11) DEFAULT NULL,
  `type` enum('entrée','sortie','ajustement') NOT NULL,
  `quantite` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produit_id` (`produit_id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `idx_msto_date` (`created_at`),
  CONSTRAINT `mouvements_stock_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mouvements_stock_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_stock`
--

LOCK TABLES `mouvements_stock` WRITE;
/*!40000 ALTER TABLE `mouvements_stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `mouvements_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `libelle` varchar(150) NOT NULL,
  `module` varchar(60) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.voir','Voir le tableau de bord','dashboard','2026-05-04 16:56:45'),(2,'vente.creer','Créer des ventes (Point de Vente)','vente','2026-05-04 16:56:45'),(3,'stock.voir','Voir le stock','stock','2026-05-04 16:56:45'),(4,'stock.ajuster','Ajuster le stock','stock','2026-05-04 16:56:45'),(5,'produits.voir','Voir les médicaments','produits','2026-05-04 16:56:45'),(6,'produits.ajouter','Ajouter des médicaments','produits','2026-05-04 16:56:45'),(7,'produits.modifier','Modifier des médicaments','produits','2026-05-04 16:56:45'),(8,'produits.archiver','Archiver des médicaments','produits','2026-05-04 16:56:45'),(9,'fournisseurs.voir','Voir les fournisseurs','fournisseurs','2026-05-04 16:56:45'),(10,'fournisseurs.ajouter','Ajouter des fournisseurs','fournisseurs','2026-05-04 16:56:45'),(11,'fournisseurs.modifier','Modifier des fournisseurs','fournisseurs','2026-05-04 16:56:45'),(12,'fournisseurs.supprimer','Supprimer des fournisseurs','fournisseurs','2026-05-04 16:56:45'),(13,'commandes.voir','Voir les commandes','commandes','2026-05-04 16:56:45'),(14,'commandes.creer','Créer des commandes','commandes','2026-05-04 16:56:45'),(15,'commandes.modifier','Modifier des commandes','commandes','2026-05-04 16:56:45'),(16,'ventes_hist.voir','Voir l\'historique des ventes','ventes_hist','2026-05-04 16:56:45'),(17,'rapports.voir','Voir les rapports','rapports','2026-05-04 16:56:45'),(18,'utilisateurs.voir','Voir les utilisateurs','utilisateurs','2026-05-04 16:56:45'),(19,'utilisateurs.gerer','Gérer les utilisateurs','utilisateurs','2026-05-04 16:56:45'),(20,'categories.voir','Voir les catégories','categories','2026-05-04 16:56:45'),(21,'categories.gerer','Gérer les catégories','categories','2026-05-04 16:56:45'),(22,'parametres.voir','Voir les paramètres','parametres','2026-05-04 16:56:45'),(23,'parametres.gerer','Gérer les paramètres','parametres','2026-05-04 16:56:45'),(24,'roles.voir','Voir les rôles & permissions','roles','2026-05-04 16:56:45'),(25,'roles.gerer','Gérer les rôles & permissions','roles','2026-05-04 16:56:45'),(26,'caisse.voir','Voir le dashboard des caisses','caisse','2026-05-06 19:59:26'),(27,'caisse.gerer','Gérer les caisses','caisse','2026-05-06 19:59:26'),(28,'caisse.ouvrir','Ouvrir une session de caisse','caisse','2026-05-06 19:59:26'),(29,'comptabilite.voir','Accéder à la comptabilité','comptabilite','2026-05-09 17:25:41'),(30,'comptabilite.saisie','Saisir des écritures manuelles','comptabilite','2026-05-09 17:25:41'),(31,'comptabilite.plan','Gérer le plan comptable','comptabilite','2026-05-09 17:25:41'),(32,'clients.voir','Voir la liste des clients','clients','2026-05-14 12:44:54'),(33,'clients.ajouter','Créer un client','clients','2026-05-14 12:44:54'),(34,'clients.modifier','Modifier une fiche client','clients','2026-05-14 12:44:54'),(35,'clients.supprimer','Désactiver un client','clients','2026-05-14 12:44:54'),(36,'clients.paiements','Enregistrer des règlements','clients','2026-05-14 12:44:54'),(37,'magasin.voir','Voir le stock magasin','magasin','2026-07-16 18:22:19'),(38,'magasin.gerer','Gérer le magasin (réceptions & transferts)','magasin','2026-07-16 18:22:19'),(39,'retours.gerer','Gérer les retours de ventes','retours','2026-07-18 16:37:29'),(41,'remise.approuver','Approuver une remise (générer un code)','vente','2026-07-18 17:09:18'),(44,'remise.approbateurs.gerer','Gérer la liste des approbateurs de remise','remise','2026-07-18 17:48:32'),(54,'pharmacies.voir','Voir les pharmacies','pharmacies','2026-07-21 14:26:47'),(55,'pharmacies.gerer','Gérer les pharmacies','pharmacies','2026-07-21 14:26:47'),(58,'menus.voir','Voir les menus','menus','2026-07-21 15:15:53'),(59,'menus.gerer','Gérer les menus','menus','2026-07-21 15:15:53');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pharmacies`
--

DROP TABLE IF EXISTS `pharmacies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pharmacies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pharmacies`
--

LOCK TABLES `pharmacies` WRITE;
/*!40000 ALTER TABLE `pharmacies` DISABLE KEYS */;
INSERT INTO `pharmacies` VALUES (1,'Pharmacie A','','',1,'2026-07-21 14:26:47'),(3,'Pharmacie B','','',1,'2026-07-21 14:38:05'),(4,'Pharmacie C','','',1,'2026-07-21 14:38:41');
/*!40000 ALTER TABLE `pharmacies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plan_comptable`
--

DROP TABLE IF EXISTS `plan_comptable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_comptable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compte` varchar(15) NOT NULL,
  `intitule` varchar(200) NOT NULL,
  `classe` tinyint(1) NOT NULL,
  `nature` enum('debit','credit') NOT NULL DEFAULT 'debit',
  `compte_parent` int(11) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `compte` (`compte`),
  KEY `compte_parent` (`compte_parent`),
  CONSTRAINT `plan_comptable_ibfk_1` FOREIGN KEY (`compte_parent`) REFERENCES `plan_comptable` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_comptable`
--

LOCK TABLES `plan_comptable` WRITE;
/*!40000 ALTER TABLE `plan_comptable` DISABLE KEYS */;
INSERT INTO `plan_comptable` VALUES (59,'1','Comptes de capitaux',1,'credit',NULL,1,'2026-05-13 17:06:05'),(60,'101','Capital social',1,'credit',NULL,1,'2026-05-13 17:06:05'),(61,'1011','Capital individuel',1,'credit',NULL,1,'2026-05-13 17:06:05'),(62,'106','Réserves',1,'credit',NULL,1,'2026-05-13 17:06:05'),(63,'1061','Réserves légales',1,'credit',NULL,1,'2026-05-13 17:06:05'),(64,'1063','Réserves libres',1,'credit',NULL,1,'2026-05-13 17:06:05'),(65,'12','Résultat de l\'exercice',1,'credit',NULL,1,'2026-05-13 17:06:05'),(66,'129','Résultat en instance d\'affectation',1,'credit',NULL,1,'2026-05-13 17:06:05'),(67,'2','Comptes d\'immobilisations',2,'debit',NULL,1,'2026-05-13 17:06:05'),(68,'218','Autres immobilisations',2,'debit',NULL,1,'2026-05-13 17:06:05'),(69,'2183','Matériel et outillage',2,'debit',NULL,1,'2026-05-13 17:06:05'),(70,'2184','Mobilier de bureau',2,'debit',NULL,1,'2026-05-13 17:06:05'),(71,'2185','Matériel informatique',2,'debit',NULL,1,'2026-05-13 17:06:05'),(72,'281','Amortissements',2,'credit',NULL,1,'2026-05-13 17:06:05'),(73,'3','Comptes de stocks',3,'debit',NULL,1,'2026-05-13 17:06:05'),(74,'311','Marchandises en stock',3,'debit',NULL,1,'2026-05-13 17:06:05'),(75,'3111','Médicaments en stock',3,'debit',NULL,1,'2026-05-13 17:06:05'),(76,'3112','Produits parapharmaceutiques',3,'debit',NULL,1,'2026-05-13 17:06:05'),(77,'4','Comptes de tiers',4,'debit',NULL,1,'2026-05-13 17:06:05'),(78,'401','Fournisseurs',4,'credit',NULL,1,'2026-05-13 17:06:05'),(79,'4011','Fournisseurs achats',4,'credit',NULL,1,'2026-05-13 17:06:05'),(80,'411','Clients',4,'debit',NULL,1,'2026-05-13 17:06:05'),(81,'4111','Clients - Assurance',4,'debit',NULL,1,'2026-05-13 17:06:05'),(82,'421','Personnel rémunérations',4,'credit',NULL,1,'2026-05-13 17:06:05'),(83,'431','Sécurité sociale',4,'credit',NULL,1,'2026-05-13 17:06:05'),(84,'441','État - TVA collectée',4,'credit',NULL,1,'2026-05-13 17:06:05'),(85,'4411','TVA collectée 19.25%',4,'credit',NULL,1,'2026-05-13 17:06:05'),(86,'445','État - TVA récupérable',4,'debit',NULL,1,'2026-05-13 17:06:05'),(87,'4451','TVA récupérable 19.25%',4,'debit',NULL,1,'2026-05-13 17:06:05'),(88,'471','Compte d\'attente',4,'credit',NULL,1,'2026-05-13 17:06:05'),(89,'5','Comptes de trésorerie',5,'debit',NULL,1,'2026-05-13 17:06:05'),(90,'511','Chèques à encaisser',5,'debit',NULL,1,'2026-05-13 17:06:05'),(91,'512','Banque',5,'debit',NULL,1,'2026-05-13 17:06:05'),(92,'571','Caisse',5,'debit',NULL,1,'2026-05-13 17:06:05'),(93,'5711','Caisse principale',5,'debit',NULL,1,'2026-05-13 17:06:05'),(94,'581','Virements internes',5,'debit',NULL,1,'2026-05-13 17:06:05'),(95,'6','Comptes de charges',6,'debit',NULL,1,'2026-05-13 17:06:05'),(96,'601','Achats de marchandises',6,'debit',NULL,1,'2026-05-13 17:06:05'),(97,'6011','Achats de médicaments',6,'debit',NULL,1,'2026-05-13 17:06:05'),(98,'603','Variation des stocks',6,'debit',NULL,1,'2026-05-13 17:06:05'),(99,'6031','Variation stocks marchandises',6,'debit',NULL,1,'2026-05-13 17:06:05'),(100,'611','Transports sur achats',6,'debit',NULL,1,'2026-05-13 17:06:05'),(101,'623','Publicité et publications',6,'debit',NULL,1,'2026-05-13 17:06:05'),(102,'625','Déplacements',6,'debit',NULL,1,'2026-05-13 17:06:05'),(103,'626','Frais postaux',6,'debit',NULL,1,'2026-05-13 17:06:05'),(104,'627','Services bancaires',6,'debit',NULL,1,'2026-05-13 17:06:05'),(105,'641','Salaires',6,'debit',NULL,1,'2026-05-13 17:06:05'),(106,'645','Charges sociales',6,'debit',NULL,1,'2026-05-13 17:06:05'),(107,'658','Charges diverses',6,'debit',NULL,1,'2026-05-13 17:06:05'),(108,'681','Dotations aux amortissements',6,'debit',NULL,1,'2026-05-13 17:06:05'),(109,'695','Impôts et taxes',6,'debit',NULL,1,'2026-05-13 17:06:05'),(110,'7','Comptes de produits',7,'credit',NULL,1,'2026-05-13 17:06:05'),(111,'701','Ventes de marchandises',7,'credit',NULL,1,'2026-05-13 17:06:05'),(112,'7011','Ventes de médicaments',7,'credit',NULL,1,'2026-05-13 17:06:05'),(113,'708','Produits des activités annexes',7,'credit',NULL,1,'2026-05-13 17:06:05'),(114,'751','Produits financiers',7,'credit',NULL,1,'2026-05-13 17:06:05'),(115,'758','Produits divers',7,'credit',NULL,1,'2026-05-13 17:06:05'),(116,'771','Produits exceptionnels',7,'credit',NULL,1,'2026-05-13 17:06:05'),(117,'4112','Clients - Crédit',4,'debit',NULL,1,'2026-05-14 13:26:57'),(118,'7119','Rabais, remises et ristournes accordés',7,'debit',NULL,1,'2026-07-18 17:09:18');
/*!40000 ALTER TABLE `plan_comptable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produit_pharmacie`
--

DROP TABLE IF EXISTS `produit_pharmacie`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produit_pharmacie` (
  `produit_id` int(11) NOT NULL,
  `pharmacie_id` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `seuil_alerte` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`produit_id`,`pharmacie_id`),
  KEY `pharmacie_id` (`pharmacie_id`),
  CONSTRAINT `produit_pharmacie_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `produit_pharmacie_ibfk_2` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produit_pharmacie`
--

LOCK TABLES `produit_pharmacie` WRITE;
/*!40000 ALTER TABLE `produit_pharmacie` DISABLE KEYS */;
/*!40000 ALTER TABLE `produit_pharmacie` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produits`
--

DROP TABLE IF EXISTS `produits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `unite` varchar(30) DEFAULT NULL,
  `categorie_id` int(11) DEFAULT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `stock_magasin` int(11) DEFAULT 0,
  `seuil_magasin` int(11) DEFAULT 20,
  `seuil_alerte` int(11) DEFAULT 10,
  `prix_achat` decimal(10,2) DEFAULT 0.00,
  `prix_vente` decimal(10,2) DEFAULT 0.00,
  `tva` decimal(5,2) DEFAULT 9.00,
  `date_expiration` date DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `categorie_id` (`categorie_id`),
  KEY `fournisseur_id` (`fournisseur_id`),
  CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produits_ibfk_2` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produits`
--

LOCK TABLES `produits` WRITE;
/*!40000 ALTER TABLE `produits` DISABLE KEYS */;
/*!40000 ALTER TABLE `produits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_produits`
--

DROP TABLE IF EXISTS `promo_produits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campagne_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `campagne_id` (`campagne_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `promo_produits_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `campagnes_promo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promo_produits_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_produits`
--

LOCK TABLES `promo_produits` WRITE;
/*!40000 ALTER TABLE `promo_produits` DISABLE KEYS */;
/*!40000 ALTER TABLE `promo_produits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reglements`
--

DROP TABLE IF EXISTS `reglements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reglements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `vente_id` int(11) DEFAULT NULL,
  `montant` decimal(10,2) NOT NULL,
  `mode_paiement` enum('espèces','carte','chèque','mobile') NOT NULL DEFAULT 'espèces',
  `note` varchar(255) DEFAULT NULL,
  `date_reglement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `vente_id` (`vente_id`),
  CONSTRAINT `reglements_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `reglements_ibfk_2` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reglements`
--

LOCK TABLES `reglements` WRITE;
/*!40000 ALTER TABLE `reglements` DISABLE KEYS */;
/*!40000 ALTER TABLE `reglements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remise_approbateurs`
--

DROP TABLE IF EXISTS `remise_approbateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remise_approbateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_utilisateur` (`utilisateur_id`),
  CONSTRAINT `remise_approbateurs_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remise_approbateurs`
--

LOCK TABLES `remise_approbateurs` WRITE;
/*!40000 ALTER TABLE `remise_approbateurs` DISABLE KEYS */;
-- [PROD CLEAN] donnees dev de `remise_approbateurs` videes (historique/transactionnel de test)
/*!40000 ALTER TABLE `remise_approbateurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retour_vente_lignes`
--

DROP TABLE IF EXISTS `retour_vente_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retour_vente_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retour_id` int(11) NOT NULL,
  `vente_ligne_id` int(11) DEFAULT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `produit_nom` varchar(200) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `tva` decimal(5,2) DEFAULT 0.00,
  `total_ligne` decimal(10,2) NOT NULL,
  `cout_achat` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `retour_id` (`retour_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `retour_vente_lignes_ibfk_1` FOREIGN KEY (`retour_id`) REFERENCES `retours_vente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `retour_vente_lignes_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retour_vente_lignes`
--

LOCK TABLES `retour_vente_lignes` WRITE;
/*!40000 ALTER TABLE `retour_vente_lignes` DISABLE KEYS */;
/*!40000 ALTER TABLE `retour_vente_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retours_vente`
--

DROP TABLE IF EXISTS `retours_vente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retours_vente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) NOT NULL,
  `vente_id` int(11) NOT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `date_retour` date NOT NULL,
  `montant_ht` decimal(10,2) DEFAULT 0.00,
  `montant_tva` decimal(10,2) DEFAULT 0.00,
  `montant_total` decimal(10,2) DEFAULT 0.00,
  `cout_achat_total` decimal(10,2) DEFAULT 0.00,
  `mode_remboursement` enum('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `vente_id` (`vente_id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `retours_vente_ibfk_1` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`),
  CONSTRAINT `retours_vente_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retours_vente`
--

LOCK TABLES `retours_vente` WRITE;
/*!40000 ALTER TABLE `retours_vente` DISABLE KEYS */;
/*!40000 ALTER TABLE `retours_vente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,41),(1,44),(1,54),(1,55),(1,58),(1,59),(2,3),(2,16),(2,17),(2,26),(2,39),(3,1),(3,2),(3,16),(3,17),(3,28),(3,32),(3,33),(3,36),(3,39),(4,1),(4,3),(4,4),(4,5),(4,6),(4,7),(4,8),(4,9),(4,10),(4,11),(4,12),(4,13),(4,14),(4,15),(4,16),(4,17),(4,18),(4,19),(4,20),(4,21),(4,22),(4,24),(4,25),(4,26),(4,27),(4,29),(4,30),(4,31),(4,33),(4,34),(4,35),(4,36),(4,39),(4,41),(4,44),(5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,7),(5,8),(5,9),(5,10),(5,11),(5,12),(5,13),(5,14),(5,15),(5,16),(5,17),(5,18),(5,19),(5,20),(5,21),(5,22),(5,23),(5,24),(5,25),(5,26),(5,29),(5,30),(5,31),(5,32),(5,33),(5,34),(5,35),(5,36),(6,1),(6,2),(6,3),(6,4),(6,5),(6,6),(6,7),(6,8),(6,9),(6,10),(6,11),(6,12),(6,13),(6,14),(6,15),(6,16),(6,17),(6,18),(6,19),(6,20),(6,21),(6,22),(6,23),(6,24),(6,25),(6,26),(6,27),(6,28),(6,29),(6,30),(6,31),(6,32),(6,33),(6,34),(6,35),(6,36),(6,37),(6,38),(6,39),(6,41),(6,44),(6,54),(6,55),(6,58),(6,59),(7,1),(7,2),(7,3),(7,4),(7,5),(7,6),(7,7),(7,8),(7,9),(7,10),(7,11),(7,12),(7,13),(7,14),(7,15),(7,16),(7,17),(7,18),(7,19),(7,20),(7,21),(7,22),(7,23),(7,24),(7,25),(7,26),(7,27),(7,28),(7,29),(7,30),(7,31),(7,32),(7,33),(7,34),(7,35),(7,36),(7,37),(7,38),(7,39),(7,41),(7,44),(7,54),(7,55),(7,58),(7,59),(8,1),(8,2),(8,3),(8,16),(8,17),(8,26),(8,27),(8,28),(8,29),(8,32),(8,33),(8,36),(8,37),(8,39);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `est_systeme` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrateur',1,'2026-05-04 16:56:45'),(2,'pharmacien','Pharmacien',1,'2026-05-04 16:56:45'),(3,'caissier','Caissier',1,'2026-05-04 16:56:45'),(4,'manager','Manager',1,'2026-05-09 21:40:38'),(5,'superviseur','Superviseur',1,'2026-05-25 18:42:23'),(6,'directeur','Directeur de la pharmacie',0,'2026-07-21 18:36:03'),(7,'informaticien','Informaticien',0,'2026-07-21 18:36:03'),(8,'regisseur','Régisseur de recette',0,'2026-07-21 18:36:03');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions_caisse`
--

DROP TABLE IF EXISTS `sessions_caisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions_caisse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caisse_id` int(11) NOT NULL,
  `caissier_id` int(11) NOT NULL,
  `fond_initial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_ouverture` datetime NOT NULL,
  `date_fermeture` datetime DEFAULT NULL,
  `solde_attendu` decimal(10,2) DEFAULT NULL,
  `solde_reel` decimal(10,2) DEFAULT NULL,
  `ecart` decimal(10,2) DEFAULT NULL,
  `statut` enum('ouverte','fermée') NOT NULL DEFAULT 'ouverte',
  `pharmacie_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `caisse_id` (`caisse_id`),
  KEY `caissier_id` (`caissier_id`),
  KEY `pharmacie_id` (`pharmacie_id`),
  CONSTRAINT `sessions_caisse_ibfk_1` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`),
  CONSTRAINT `sessions_caisse_ibfk_2` FOREIGN KEY (`caissier_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `sessions_caisse_ibfk_3` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`),
  CONSTRAINT `sessions_caisse_ibfk_4` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions_caisse`
--

LOCK TABLES `sessions_caisse` WRITE;
/*!40000 ALTER TABLE `sessions_caisse` DISABLE KEYS */;
-- [PROD CLEAN] donnees dev de `sessions_caisse` videes (historique/transactionnel de test)
/*!40000 ALTER TABLE `sessions_caisse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transfert_lignes`
--

DROP TABLE IF EXISTS `transfert_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfert_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfert_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `produit_nom` varchar(200) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transfert_id` (`transfert_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `transfert_lignes_ibfk_1` FOREIGN KEY (`transfert_id`) REFERENCES `transferts_magasin` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfert_lignes_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transfert_lignes`
--

LOCK TABLES `transfert_lignes` WRITE;
/*!40000 ALTER TABLE `transfert_lignes` DISABLE KEYS */;
/*!40000 ALTER TABLE `transfert_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transferts_magasin`
--

DROP TABLE IF EXISTS `transferts_magasin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transferts_magasin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) NOT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `pharmacie_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `pharmacie_id` (`pharmacie_id`),
  KEY `idx_trf_date` (`created_at`),
  CONSTRAINT `transferts_magasin_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transferts_magasin_ibfk_2` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transferts_magasin`
--

LOCK TABLES `transferts_magasin` WRITE;
/*!40000 ALTER TABLE `transferts_magasin` DISABLE KEYS */;
/*!40000 ALTER TABLE `transferts_magasin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transferts_pharmacies`
--

DROP TABLE IF EXISTS `transferts_pharmacies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transferts_pharmacies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) NOT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `pharmacie_source_id` int(11) NOT NULL,
  `pharmacie_dest_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `pharmacie_source_id` (`pharmacie_source_id`),
  KEY `pharmacie_dest_id` (`pharmacie_dest_id`),
  KEY `idx_tvp_date` (`created_at`),
  CONSTRAINT `transferts_pharmacies_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transferts_pharmacies_ibfk_2` FOREIGN KEY (`pharmacie_source_id`) REFERENCES `pharmacies` (`id`),
  CONSTRAINT `transferts_pharmacies_ibfk_3` FOREIGN KEY (`pharmacie_dest_id`) REFERENCES `pharmacies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transferts_pharmacies`
--

LOCK TABLES `transferts_pharmacies` WRITE;
/*!40000 ALTER TABLE `transferts_pharmacies` DISABLE KEYS */;
/*!40000 ALTER TABLE `transferts_pharmacies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transfert_pharmacie_lignes`
--

DROP TABLE IF EXISTS `transfert_pharmacie_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfert_pharmacie_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfert_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `produit_nom` varchar(200) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transfert_id` (`transfert_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `transfert_pharmacie_lignes_ibfk_1` FOREIGN KEY (`transfert_id`) REFERENCES `transferts_pharmacies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfert_pharmacie_lignes_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transfert_pharmacie_lignes`
--

LOCK TABLES `transfert_pharmacie_lignes` WRITE;
/*!40000 ALTER TABLE `transfert_pharmacie_lignes` DISABLE KEYS */;
/*!40000 ALTER TABLE `transfert_pharmacie_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `login` varchar(60) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `actif` tinyint(1) DEFAULT 1,
  `derniere_connexion` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `login` (`login`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `utilisateurs_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateurs`
--

LOCK TABLES `utilisateurs` WRITE;
/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES (1,'Administrateur','','admin@pharmacare.local','admin','LOCKED_INSTALL',1,1,NULL,'2026-01-01 00:00:00'),(2,'Pharmacien','','pharmacien@pharmacare.local','pharmacien','LOCKED_INSTALL',2,1,NULL,'2026-01-01 00:00:00'),(3,'Caissier','','caissier@pharmacare.local','caissier','LOCKED_INSTALL',3,1,NULL,'2026-01-01 00:00:00');
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vente_lignes`
--

DROP TABLE IF EXISTS `vente_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vente_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vente_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `produit_nom` varchar(200) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `tva` decimal(5,2) DEFAULT 9.00,
  `total_ligne` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `vente_id` (`vente_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `vente_lignes_ibfk_1` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vente_lignes_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vente_lignes`
--

LOCK TABLES `vente_lignes` WRITE;
/*!40000 ALTER TABLE `vente_lignes` DISABLE KEYS */;
/*!40000 ALTER TABLE `vente_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventes`
--

DROP TABLE IF EXISTS `ventes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ventes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) NOT NULL,
  `client_nom` varchar(150) DEFAULT NULL,
  `client_telephone` varchar(20) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `caissier_id` int(11) DEFAULT NULL,
  `sous_total` decimal(10,2) DEFAULT 0.00,
  `tva_total` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT 0.00,
  `mode_paiement` varchar(50) NOT NULL,
  `statut_paiement` enum('payé','en_attente','partiel') DEFAULT 'payé',
  `montant_recu` decimal(10,2) DEFAULT 0.00,
  `monnaie` decimal(10,2) DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `est_annulee` tinyint(1) DEFAULT 0,
  `remise_pct` decimal(5,2) DEFAULT 0.00,
  `remise_montant` decimal(10,2) DEFAULT 0.00,
  `autorise_par` int(11) DEFAULT NULL,
  `pharmacie_id` int(11) DEFAULT NULL,
  `client_ref` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  UNIQUE KEY `client_ref` (`client_ref`),
  KEY `caissier_id` (`caissier_id`),
  KEY `client_id` (`client_id`),
  KEY `fk_ventes_autorise_par` (`autorise_par`),
  KEY `pharmacie_id` (`pharmacie_id`),
  KEY `idx_ventes_date` (`created_at`),
  CONSTRAINT `fk_ventes_autorise_par` FOREIGN KEY (`autorise_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`caissier_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventes_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `ventes_ibfk_3` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`),
  CONSTRAINT `ventes_ibfk_4` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventes`
--

LOCK TABLES `ventes` WRITE;
/*!40000 ALTER TABLE `ventes` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'pharmacare'
--

--
-- Dumping routines for database 'pharmacare'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-22 12:26:19

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: pharmacare
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `parametres`
--

DROP TABLE IF EXISTS `parametres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parametres` (
  `cle` varchar(60) NOT NULL,
  `valeur` text NOT NULL,
  `label` varchar(120) DEFAULT NULL,
  `groupe` varchar(60) DEFAULT 'general',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parametres`
--
-- WHERE:  cle NOT LIKE 'licence_%'

LOCK TABLES `parametres` WRITE;
/*!40000 ALTER TABLE `parametres` DISABLE KEYS */;
INSERT INTO `parametres` VALUES ('app_nom','PharmaCare','Nom de la pharmacie','général'),('caisse_fermeture_mode','manuel','Mode fermeture caisse','caisse'),('caisse_heure_fermeture','18:00','Heure fermeture auto','caisse'),('credit_active','1','Vente à crédit activée','ventes'),('delai_inactivite_min','15','Délai d''inactivité (min)','general'),('devise','XAF','Devise','général'),('devise_pos','after','Position symbole','général'),('devise_symbole','FCFA','Symbole devise','général'),('fidelite_active','1',NULL,'general'),('pharmacie_adresse','',NULL,'général'),('pharmacie_nif','',NULL,'général'),('pharmacie_telephone','',NULL,'général'),('police','Manrope','Police principale','général'),('police_titre','Manrope','Police titres','général'),('prefix_vente','VNT',NULL,'general'),('remise_code_ttl_min','15','Validité code remise (min)','ventes'),('remise_max_pct','100','Remise max (%)','ventes'),('theme','dark-rose','Thème couleur','général'),('ticket_pied','Merci pour votre achat ! pharmaCare (c) 2026',NULL,'général'),('ticket_sous_titre','PharmaCare',NULL,'général'),('tva','0.00','Taux TVA (%)','général');
/*!40000 ALTER TABLE `parametres` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-22 12:26:19

-- ── PharmaCare 1.3.1 — Permissions manquantes (patch idempotent) ────────────
-- Permissions vérifiées dans le code (requirePermission/hasPermission) mais non
-- insérées dans ce dump : invisibles dans l'éditeur de rôles, non attribuables.
-- INSERT IGNORE + clé UNIQUE sur code → ré-exécution sûre.
-- Audit : php tools/audit_permissions.php
INSERT IGNORE INTO `permissions` (`code`, `libelle`, `module`) VALUES
('marketing.voir', 'Voir le module marketing (promos & fidélité)', 'marketing'),
('marketing.promos', 'Créer et gérer les promotions', 'marketing'),
('marketing.fidelite', 'Gérer le programme de fidélité', 'marketing'),
('suivi_caissiers.voir', 'Voir le suivi des caissiers', 'suivi_caissiers'),
('suivi_caissiers.recompenser', 'Attribuer des récompenses aux caissiers', 'suivi_caissiers'),
('enligne.voir', 'Voir les utilisateurs en ligne', 'en_ligne'),
('rapports_caissier.voir', 'Voir les rapports caissier', 'rapports_caissier'),
('assistant.utiliser', 'Utiliser l''assistant intégré', 'assistant');

-- ── PharmaCare 1.3.1 — Attributions marketing + rôle Magasinier ─────────────
-- Marketing accordé aux rôles de direction (leur seul trou selon l'audit).
-- Rôle magasinier (réceptionnaire) : magasin + suivi/livraison des commandes.
-- Idempotent : INSERT IGNORE + PK (role_id, permission_id) + clé UNIQUE code.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
  ON p.`code` IN ('marketing.voir','marketing.promos','marketing.fidelite')
WHERE r.`code` IN ('directeur','informaticien');

INSERT IGNORE INTO `roles` (`code`, `libelle`, `est_systeme`) VALUES ('magasinier', 'Magasinier', 0);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
  ON p.`code` IN ('dashboard.voir','magasin.voir','magasin.gerer','stock.voir',
                  'produits.voir','commandes.voir','commandes.modifier','assistant.utiliser')
WHERE r.`code` = 'magasinier';
