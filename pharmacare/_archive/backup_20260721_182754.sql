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
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-06-02 19:25:05'),(2,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-06-02 19:27:21'),(3,1,'vente.create','Vente VNT-2026-9910 : 1 articles, 4 500 FCFA espèces (—)','::1',32,'VNT-2026-9910','2026-06-02 19:33:55'),(4,1,'vente.create','Vente VNT-2026-9911 : 1 articles, 1 800 FCFA espèces (—)','::1',33,'VNT-2026-9911','2026-06-02 19:37:12'),(5,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-06-02 19:43:37'),(6,1,'vente.create','Vente VNT-2026-9912 : 1 articles, 13 000 FCFA espèces (—)','::1',34,'VNT-2026-9912','2026-06-02 19:44:09'),(7,1,'caisse.close','Clôture caisse #12 : attendu=65 200 FCFA, réel=65 200 FCFA, écart=0 FCFA','::1',12,NULL,'2026-06-02 19:50:58'),(8,1,'caisse.open','Ouverture caisse #13 : fond 0 FCFA','::1',13,NULL,'2026-06-02 19:51:04'),(9,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-06-02 19:59:56'),(10,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-06-02 22:30:02'),(11,1,'vente.create','Vente VNT-2026-9913 : 1 articles, 5 000 FCFA espèces (ibrahima)','::1',35,'VNT-2026-9913','2026-06-02 22:31:26'),(12,1,'vente.create','Vente VNT-2026-9914 : 1 articles, 9 600 FCFA espèces (saidou)','::1',36,'VNT-2026-9914','2026-06-02 22:35:29'),(13,1,'vente.create','Vente VNT-2026-9915 : 1 articles, 4 000 FCFA espèces (rachid)','::1',37,'VNT-2026-9915','2026-06-02 22:38:53'),(14,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-06-06 12:13:04'),(15,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 13:44:28'),(16,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 14:10:17'),(17,1,'caisse.close','Clôture caisse #13 : attendu=18 600 FCFA, réel=18 700 FCFA, écart=100 FCFA','::1',13,NULL,'2026-07-16 14:18:34'),(18,1,'caisse.open','Ouverture caisse #14 : fond 0 FCFA','::1',14,NULL,'2026-07-16 14:18:46'),(19,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 14:26:21'),(20,1,'vente.create','Vente VNT-2026-9916 : 3 articles, 1 900 FCFA espèces (bello)','::1',38,'VNT-2026-9916','2026-07-16 14:26:51'),(21,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 15:06:18'),(22,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 15:22:09'),(23,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 17:02:00'),(24,1,'caisse.close','Clôture caisse #14 : attendu=1 900 FCFA, réel=2 000 FCFA, écart=100 FCFA','::1',14,NULL,'2026-07-16 17:02:17'),(25,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-16 17:02:45'),(26,4,'caisse.open','Ouverture caisse #15 : fond 0 FCFA','::1',15,NULL,'2026-07-16 17:04:29'),(27,4,'vente.create','Vente VNT-2026-9917 : 1 articles, 19 000 FCFA espèces (Yasser)','::1',39,'VNT-2026-9917','2026-07-16 17:06:32'),(28,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 17:16:08'),(29,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 18:25:34'),(30,1,'magasin.retour','Retour pharmacie→magasin : 3 ligne(s)','::1',NULL,NULL,'2026-07-16 18:37:04'),(31,1,'magasin.retour','Retour pharmacie→magasin : 1 ligne(s)','::1',NULL,NULL,'2026-07-16 18:37:38'),(32,1,'magasin.retour','Retour pharmacie→magasin : 3 ligne(s)','::1',NULL,NULL,'2026-07-16 18:40:02'),(33,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 18:42:31'),(34,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 18:59:42'),(35,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 19:18:06'),(36,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-16 20:20:27'),(37,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 10:18:32'),(38,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 11:09:27'),(39,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 11:25:11'),(40,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 11:44:47'),(41,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 12:23:46'),(42,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 12:38:57'),(43,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 13:28:05'),(44,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 13:49:13'),(45,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 15:42:13'),(46,1,'magasin.transfert','Transfert TRF-2026-0001 : 1 ligne(s) vers pharmacie','::1',NULL,NULL,'2026-07-17 15:49:38'),(47,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 16:07:44'),(48,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 16:31:58'),(49,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 16:50:46'),(50,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 17:49:32'),(51,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-17 18:04:58'),(52,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 12:21:29'),(53,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 12:22:22'),(54,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 13:23:35'),(55,1,'vente.create','Vente VNT-2026-9918 : 2 articles, 1 450 FCFA espèces (hassan)','::1',40,'VNT-2026-9918','2026-07-18 13:24:01'),(56,1,'vente.create','Vente VNT-2026-9919 : 1 articles, 750 FCFA espèces (hassan oumar)','::1',41,'VNT-2026-9919','2026-07-18 13:31:11'),(57,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 15:52:53'),(58,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 16:10:02'),(59,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 16:42:49'),(60,1,'retour.create','Retour RET-2026-0001 sur vente VNT-2026-9917 : 1 ligne(s), 10 000 FCFA (Espèces)','::1',1,'RET-2026-0001','2026-07-18 16:43:40'),(61,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 16:49:00'),(62,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 17:28:51'),(63,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 17:50:23'),(64,1,'remise.approbateur.add','Ajout approbateur de remise : SAID AHMAD','::1',5,NULL,'2026-07-18 17:50:47'),(65,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-18 17:52:21'),(66,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-18 18:34:54'),(67,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 18:58:08'),(68,1,'caisse.close','Clôture caisse #15 : attendu=19 000 FCFA, réel=19 000 FCFA, écart=0 FCFA','::1',15,NULL,'2026-07-18 18:59:00'),(69,1,'caisse.close','Clôture caisse #16 : attendu=-7 800 FCFA, réel=0 FCFA, écart=7 800 FCFA','::1',16,NULL,'2026-07-18 18:59:16'),(70,1,'caisse.open','Ouverture caisse #17 : fond 0 FCFA','::1',17,NULL,'2026-07-18 18:59:28'),(71,1,'remise.code','Génération code remise 5DGK8L (validité 15 min)','::1',NULL,'5DGK8L','2026-07-18 19:01:31'),(72,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 19:01:53'),(73,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-18 19:02:29'),(74,4,'caisse.open','Ouverture caisse #18 : fond 0 FCFA','::1',18,NULL,'2026-07-18 19:02:35'),(75,4,'vente.create','Vente VNT-2026-9920 : 2 articles, 2 650 FCFA espèces (ADJI)','::1',42,'VNT-2026-9920','2026-07-18 19:04:03'),(76,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 19:20:46'),(77,1,'remise.code','Génération code remise CZL4RV (validité 15 min)','::1',NULL,'CZL4RV','2026-07-18 19:28:12'),(78,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-18 19:28:34'),(79,4,'vente.create','Vente VNT-2026-9921 : 3 articles, 3 750 FCFA espèces (RABIAH)','::1',43,'VNT-2026-9921','2026-07-18 19:29:36'),(80,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-18 19:58:48'),(81,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-19 07:07:35'),(82,1,'remise.code','Génération code remise YJLXGM (validité 15 min)','::1',NULL,'YJLXGM','2026-07-19 07:11:54'),(83,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-19 10:39:37'),(84,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-19 12:43:42'),(85,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-19 14:10:40'),(86,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-19 18:17:27'),(87,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 11:31:58'),(88,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 15:22:25'),(89,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 17:51:39'),(90,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 18:13:36'),(91,1,'vente.create','Vente VNT-2026-9922 : 1 articles, 6 500 FCFA espèces (OUMAR)','::1',44,'VNT-2026-9922','2026-07-20 18:14:32'),(92,1,'caisse.close','Clôture caisse #18 : attendu=4 513 FCFA, réel=4 513 FCFA, écart=1 FCFA','::1',18,NULL,'2026-07-20 18:19:50'),(93,1,'caisse.close','Clôture caisse #17 : attendu=6 500 FCFA, réel=6 500 FCFA, écart=0 FCFA','::1',17,NULL,'2026-07-20 18:20:15'),(94,1,'remise.approbateur.remove','Retrait approbateur de remise : Marie-Claire Ngassa','::1',2,NULL,'2026-07-20 18:21:11'),(95,1,'remise.code','Génération code remise E46TKW (validité 15 min)','::1',NULL,'E46TKW','2026-07-20 18:21:21'),(96,1,'caisse.open','Ouverture caisse #19 : fond 0 FCFA','::1',19,NULL,'2026-07-20 18:22:00'),(97,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-20 18:22:38'),(98,4,'caisse.open','Ouverture caisse #20 : fond 0 FCFA','::1',20,NULL,'2026-07-20 18:22:44'),(99,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 18:25:50'),(100,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 19:19:44'),(101,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-20 19:41:12'),(102,1,'remise.code','Génération code remise W82U7J (10%, validité 15 min)','::1',NULL,'W82U7J','2026-07-20 19:41:25'),(103,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 12:26:19'),(104,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 12:50:25'),(105,1,'remise.code','Génération code remise XENZTS (45%, validité 15 min)','::1',NULL,'XENZTS','2026-07-21 12:57:02'),(106,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-21 12:57:19'),(107,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-21 13:01:02'),(108,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-21 13:50:49'),(109,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 14:03:16'),(110,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-21 14:03:45'),(111,4,'vente.create','Vente VNT-2026-9923 : 6 articles, 2 840 FCFA espèces (OLIVER)','::1',47,'VNT-2026-9923','2026-07-21 14:04:20'),(112,4,'auth.login','Connexion : yasmine (caissier)','::1',NULL,NULL,'2026-07-21 14:36:29'),(113,4,'caisse.close','Clôture caisse #20 : attendu=2 840 FCFA, réel=2 900 FCFA, écart=60 FCFA','::1',20,NULL,'2026-07-21 14:36:43'),(114,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 14:37:14'),(115,1,'caisse.close','Clôture caisse #21 : attendu=0 FCFA, réel=0 FCFA, écart=0 FCFA','::1',21,NULL,'2026-07-21 14:37:26'),(116,1,'caisse.close','Clôture caisse #19 : attendu=0 FCFA, réel=0 FCFA, écart=0 FCFA','::1',19,NULL,'2026-07-21 14:37:37'),(117,1,'caisse.open','Ouverture caisse #22 (Pharmacie B) : fond 0 FCFA','::1',22,NULL,'2026-07-21 14:39:10'),(118,1,'magasin.transfert','Transfert TRF-2026-0002 : 1 ligne(s) vers pharmacie','::1',NULL,NULL,'2026-07-21 14:46:16'),(119,1,'magasin.transfert','Transfert TRF-2026-0003 : 2 ligne(s) vers Pharmacie B','::1',NULL,NULL,'2026-07-21 14:54:10'),(120,1,'vente.create','Vente VNT-2026-9924 : 1 articles, 4 800 FCFA espèces (ILHAM)','::1',48,'VNT-2026-9924','2026-07-21 14:54:45'),(121,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 15:02:31'),(122,1,'vente.create','Vente VNT-2026-9925 : 1 articles, 3 600 FCFA espèces (SABRINA)','::1',49,'VNT-2026-9925','2026-07-21 15:03:19'),(123,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 15:28:49'),(124,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 16:21:01'),(125,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 16:46:14'),(126,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 17:11:39'),(127,1,'auth.login','Connexion : admin (admin)','::1',NULL,NULL,'2026-07-21 17:35:06');
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Antalgiques','#00c9a7'),(2,'Antibiotiques','#4895ef'),(3,'Anti-inflammatoires','#f0b429'),(4,'Antipaludiques','#ef4444'),(5,'Vitamines & Compléments','#9b59b6'),(6,'Cardiologie & Hypertension','#e74c3c'),(7,'Diabétologie','#e67e22'),(8,'Respiratoire','#1abc9c'),(9,'Gastro-entérologie','#3498db'),(10,'Allergologie','#e91e63'),(11,'Dermatologie','#795548'),(12,'Antiparasitaires','#2ecc71');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'SEMRY','670198963',1,'2026-05-14 13:25:18'),(2,'VIVA LOGONE','653250114',1,'2026-05-14 13:25:54'),(3,'RACHID','693353299',1,'2026-05-16 12:14:44');
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `codes_remise`
--

LOCK TABLES `codes_remise` WRITE;
/*!40000 ALTER TABLE `codes_remise` DISABLE KEYS */;
INSERT INTO `codes_remise` VALUES (1,'5DGK8L',1,'2026-07-18 19:01:31','2026-07-18 19:16:31',0.00,1,'2026-07-18 19:04:02',42,50.00),(2,'CZL4RV',1,'2026-07-18 19:28:12','2026-07-18 19:43:12',0.00,1,'2026-07-18 19:29:36',43,15.00),(3,'YJLXGM',1,'2026-07-19 07:11:54','2026-07-19 07:26:54',0.00,0,NULL,NULL,NULL),(4,'E46TKW',1,'2026-07-20 18:21:21','2026-07-20 18:36:21',0.00,0,NULL,NULL,NULL),(5,'W82U7J',1,'2026-07-20 19:41:25','2026-07-20 19:56:25',10.00,0,NULL,NULL,NULL),(7,'XENZTS',1,'2026-07-21 12:57:02','2026-07-21 13:12:02',45.00,0,NULL,NULL,NULL),(8,'TB7B8',1,'2026-07-21 13:02:39','2026-07-22 13:02:39',20.00,1,'2026-07-21 14:04:20',47,20.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_lignes`
--

LOCK TABLES `commande_lignes` WRITE;
/*!40000 ALTER TABLE `commande_lignes` DISABLE KEYS */;
INSERT INTO `commande_lignes` VALUES (2,1,15,'Azithromycine 250mg (boîte de 6)',890,950.00,'2026-05-09 22:46:42'),(5,5,33,'Calcium 500mg + Vitamine D3 (boîte de 30)',1000,450.00,'2026-05-09 23:05:39'),(6,5,38,'Atorvastatine 20mg (boîte de 28)',1000,800.00,'2026-05-09 23:05:39'),(7,6,1,'Paracétamol 500mg (boîte de 20)',1000,180.00,'2026-05-09 23:16:19'),(8,7,59,'Polysilane (flacon 250ml)',1000,350.00,'2026-05-13 18:36:14'),(9,8,9,'Amoxicilline 1g (boîte de 8)',1000,650.00,'2026-06-02 13:37:43'),(10,9,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1000,750.00,'2026-07-16 17:18:06'),(11,10,37,'Amlodipine 5mg (boîte de 28)',75,500.00,'2026-07-16 19:19:07'),(12,10,68,'Acide fusidique crème 2% (tube 15g)',50,400.00,'2026-07-16 19:19:07'),(13,10,69,'Albendazole 400mg (boîte de 4)',1000,200.00,'2026-07-16 19:19:07'),(14,11,51,'Ambroxol 30mg (boîte de 20)',400,250.00,'2026-07-20 18:28:06'),(15,11,62,'Aerius (Desloratadine) 5mg (boîte de 10)',250,750.00,'2026-07-20 18:28:06'),(16,11,68,'Acide fusidique crème 2% (tube 15g)',500,400.00,'2026-07-20 18:28:06'),(17,11,69,'Albendazole 400mg (boîte de 4)',300,200.00,'2026-07-20 18:28:06');
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commandes`
--

LOCK TABLES `commandes` WRITE;
/*!40000 ALTER TABLE `commandes` DISABLE KEYS */;
INSERT INTO `commandes` VALUES (1,'CMD-2026-001',1,1,'livrée','2026-03-15','2026-03-22','','2026-05-05 14:12:07',0.00),(2,'CMD-2026-002',2,2,'livrée','2026-04-20','2026-05-09',NULL,'2026-05-05 14:12:07',0.00),(3,'CMD-2026-003',4,1,'livrée','2026-05-01','2026-05-09',NULL,'2026-05-05 14:12:07',0.00),(5,'CMD-2026-1449',5,1,'livrée','2026-05-10','2026-05-09','\n[Validation] Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé','2026-05-09 23:02:12',0.00),(6,'CMD-2026-6029',2,1,'livrée','2026-05-10','2026-05-09','\n[Validation] Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue','2026-05-09 23:16:19',0.00),(7,'CMD-2026-9743',5,1,'livrée','2026-05-13','2026-05-13','\n[Validation] Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue','2026-05-13 18:36:14',0.00),(8,'CMD-2026-9744',5,1,'livrée','2026-06-02','2026-06-02','\n[Validation] Colis en bon état ; Quantité conforme ; Produits conformes','2026-06-02 13:37:43',0.00),(9,'CMD-2026-9745',2,1,'livrée','2026-07-16','2026-07-16','\n[Validation] Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue','2026-07-16 17:18:06',0.00),(10,'CMD-2026-9746',5,1,'en_attente','2026-07-16','2026-07-30','','2026-07-16 19:19:07',0.00),(11,'CMD-2026-9747',3,1,'livrée','2026-07-20','2026-07-20','commande du 20 juillet 2026\n[Validation] Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue','2026-07-20 18:28:06',0.00);
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
INSERT INTO `compteurs_ref` VALUES ('CMD',2026,9747),('TRF',2026,3),('VNT',2026,9925);
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
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ecriture_lignes`
--

LOCK TABLES `ecriture_lignes` WRITE;
/*!40000 ALTER TABLE `ecriture_lignes` DISABLE KEYS */;
INSERT INTO `ecriture_lignes` VALUES (1,1,97,79664.57,0.00,'Achat médicaments CMD-2026-002'),(2,1,87,15335.43,0.00,'TVA récupérable CMD-2026-002'),(3,1,79,0.00,95000.00,'Fournisseur Laborex Cameroun'),(4,2,97,51991.61,0.00,'Achat médicaments CMD-2026-003'),(5,2,87,10008.39,0.00,'TVA récupérable CMD-2026-003'),(6,2,79,0.00,62000.00,'Fournisseur Ubipharm Cameroun'),(13,5,97,1048218.03,0.00,'Achat médicaments CMD-2026-1449'),(14,5,87,201781.97,0.00,'TVA récupérable CMD-2026-1449'),(15,5,79,0.00,1250000.00,'Fournisseur CAMPHARM'),(16,6,97,150943.40,0.00,'Achat médicaments CMD-2026-6029'),(17,6,87,29056.60,0.00,'TVA récupérable CMD-2026-6029'),(18,6,79,0.00,180000.00,'Fournisseur Laborex Cameroun'),(19,7,93,7989.75,0.00,'Vente VNT-2026-2742'),(20,7,112,0.00,6700.00,'Vente médicaments VNT-2026-2742'),(21,7,85,0.00,1289.75,'TVA collectée VNT-2026-2742'),(22,8,93,2504.25,0.00,'Vente VNT-2026-7045'),(23,8,112,0.00,2100.00,'Vente médicaments VNT-2026-7045'),(24,8,85,0.00,404.25,'TVA collectée VNT-2026-7045'),(25,9,93,2921.63,0.00,'Vente VNT-2026-4525'),(26,9,112,0.00,2450.00,'Vente médicaments VNT-2026-4525'),(27,9,85,0.00,471.63,'TVA collectée VNT-2026-4525'),(28,10,97,293501.05,0.00,'Achat médicaments CMD-2026-9743'),(29,10,87,56498.95,0.00,'TVA récupérable CMD-2026-9743'),(30,10,79,0.00,350000.00,'Fournisseur CAMPHARM'),(31,11,93,7453.13,0.00,'Vente VNT-2026-2889'),(32,11,112,0.00,6250.00,'Vente médicaments VNT-2026-2889'),(33,11,85,0.00,1203.13,'TVA collectée VNT-2026-2889'),(34,12,93,6499.13,0.00,'Vente VNT-2026-1909'),(35,12,112,0.00,5450.00,'Vente médicaments VNT-2026-1909'),(36,12,85,0.00,1049.13,'TVA collectée VNT-2026-1909'),(37,13,117,7870.50,0.00,'Crédit client VIVA LOGONE - VNT-2026-9758'),(38,13,112,0.00,6600.00,'Vente médicaments VNT-2026-9758'),(39,13,85,0.00,1270.50,'TVA collectée VNT-2026-9758'),(40,14,117,7155.00,0.00,'Crédit client SEMRY - VNT-2026-1284'),(41,14,112,0.00,6000.00,'Vente médicaments VNT-2026-1284'),(42,14,85,0.00,1155.00,'TVA collectée VNT-2026-1284'),(43,15,117,9063.00,0.00,'Crédit client VIVA LOGONE - VNT-2026-8969'),(44,15,112,0.00,7600.00,'Vente médicaments VNT-2026-8969'),(45,15,85,0.00,1463.00,'TVA collectée VNT-2026-8969'),(46,16,117,1600.00,0.00,'Crédit client SEMRY - VNT-2026-9234'),(47,16,112,0.00,1600.00,'Vente médicaments VNT-2026-9234'),(48,16,85,0.00,0.00,'TVA collectée VNT-2026-9234'),(49,17,117,6000.00,0.00,'Crédit client SEMRY - VNT-2026-7976'),(50,17,112,0.00,6000.00,'Vente médicaments VNT-2026-7976'),(51,17,85,0.00,0.00,'TVA collectée VNT-2026-7976'),(52,18,117,1250.00,0.00,'Crédit client SEMRY - VNT-2026-4231'),(53,18,112,0.00,1250.00,'Vente médicaments VNT-2026-4231'),(54,18,85,0.00,0.00,'TVA collectée VNT-2026-4231'),(55,19,117,8400.00,0.00,'Crédit client SEMRY - VNT-2026-9904'),(56,19,112,0.00,8400.00,'Vente médicaments VNT-2026-9904'),(57,19,85,0.00,0.00,'TVA collectée VNT-2026-9904'),(58,20,117,10000.00,0.00,'Crédit client VIVA LOGONE - VNT-2026-5697'),(59,20,112,0.00,10000.00,'Vente médicaments VNT-2026-5697'),(60,20,85,0.00,0.00,'TVA collectée VNT-2026-5697'),(61,21,117,12000.00,0.00,'Crédit client SEMRY - VNT-2026-1213'),(62,21,112,0.00,12000.00,'Vente médicaments VNT-2026-1213'),(63,21,85,0.00,0.00,'TVA collectée VNT-2026-1213'),(64,22,117,10000.00,0.00,'Crédit client VIVA LOGONE - VNT-2026-4455'),(65,22,112,0.00,10000.00,'Vente médicaments VNT-2026-4455'),(66,22,85,0.00,0.00,'TVA collectée VNT-2026-4455'),(67,23,117,4500.00,0.00,'Crédit client SEMRY - VNT-2026-4968'),(68,23,112,0.00,4500.00,'Vente médicaments VNT-2026-4968'),(69,23,85,0.00,0.00,'TVA collectée VNT-2026-4968'),(70,24,75,750000.00,0.00,'Entrée stock Aerius (Desloratadine) 5mg (boîte de 10)'),(71,24,99,0.00,750000.00,'Variation stock Aerius (Desloratadine) 5mg (boîte de 10)'),(72,25,117,7150.00,0.00,'Crédit client Rachid - VNT-2026-6775'),(73,25,112,0.00,7150.00,'Vente médicaments VNT-2026-6775'),(74,25,85,0.00,0.00,'TVA collectée VNT-2026-6775'),(75,26,117,9700.00,0.00,'Crédit client SEMRY - VNT-2026-9905'),(76,26,112,0.00,9700.00,'Vente médicaments VNT-2026-9905'),(77,26,85,0.00,0.00,'TVA collectée VNT-2026-9905'),(78,27,117,10200.00,0.00,'Crédit client SEMRY - VNT-2026-9906'),(79,27,112,0.00,10200.00,'Vente médicaments VNT-2026-9906'),(80,27,85,0.00,0.00,'TVA collectée VNT-2026-9906'),(81,28,93,9500.00,0.00,'Vente VNT-2026-9907'),(82,28,112,0.00,9500.00,'Vente médicaments VNT-2026-9907'),(83,28,85,0.00,0.00,'TVA collectée VNT-2026-9907'),(84,29,117,14000.00,0.00,'Crédit client VIVA LOGONE - VNT-2026-9908'),(85,29,112,0.00,14000.00,'Vente médicaments VNT-2026-9908'),(86,29,85,0.00,0.00,'TVA collectée VNT-2026-9908'),(87,30,93,36400.00,0.00,'Vente VNT-2026-9909'),(88,30,112,0.00,36400.00,'Vente médicaments VNT-2026-9909'),(89,30,85,0.00,0.00,'TVA collectée VNT-2026-9909'),(90,31,93,4500.00,0.00,'Vente VNT-2026-9910'),(91,31,112,0.00,4500.00,'Vente médicaments VNT-2026-9910'),(92,31,85,0.00,0.00,'TVA collectée VNT-2026-9910'),(93,32,93,1800.00,0.00,'Vente VNT-2026-9911'),(94,32,112,0.00,1800.00,'Vente médicaments VNT-2026-9911'),(95,32,85,0.00,0.00,'TVA collectée VNT-2026-9911'),(96,33,93,13000.00,0.00,'Vente VNT-2026-9912'),(97,33,112,0.00,13000.00,'Vente médicaments VNT-2026-9912'),(98,33,85,0.00,0.00,'TVA collectée VNT-2026-9912'),(99,34,97,650000.00,0.00,'Achat médicaments CMD-2026-9744'),(100,34,87,0.00,0.00,'TVA récupérable CMD-2026-9744'),(101,34,79,0.00,650000.00,'Fournisseur CAMPHARM'),(102,35,93,5000.00,0.00,'Vente VNT-2026-9913'),(103,35,112,0.00,5000.00,'Vente médicaments VNT-2026-9913'),(104,35,85,0.00,0.00,'TVA collectée VNT-2026-9913'),(105,36,93,9600.00,0.00,'Vente VNT-2026-9914'),(106,36,112,0.00,9600.00,'Vente médicaments VNT-2026-9914'),(107,36,85,0.00,0.00,'TVA collectée VNT-2026-9914'),(108,37,93,4000.00,0.00,'Vente VNT-2026-9915'),(109,37,112,0.00,4000.00,'Vente médicaments VNT-2026-9915'),(110,37,85,0.00,0.00,'TVA collectée VNT-2026-9915'),(111,38,93,1900.00,0.00,'Vente VNT-2026-9916'),(112,38,112,0.00,1900.00,'Vente médicaments VNT-2026-9916'),(113,38,85,0.00,0.00,'TVA collectée VNT-2026-9916'),(114,39,93,19000.00,0.00,'Vente VNT-2026-9917'),(115,39,112,0.00,19000.00,'Vente médicaments VNT-2026-9917'),(116,39,85,0.00,0.00,'TVA collectée VNT-2026-9917'),(117,40,97,750000.00,0.00,'Achat médicaments CMD-2026-9745'),(118,40,87,0.00,0.00,'TVA récupérable CMD-2026-9745'),(119,40,79,0.00,750000.00,'Fournisseur Laborex Cameroun'),(120,41,93,1450.00,0.00,'Vente VNT-2026-9918'),(121,41,112,0.00,1450.00,'Vente médicaments VNT-2026-9918'),(122,41,85,0.00,0.00,'TVA collectée VNT-2026-9918'),(123,42,99,880.00,0.00,'Sortie stock VNT-2026-9918'),(124,42,75,0.00,880.00,'Stock vendu VNT-2026-9918'),(125,43,93,750.00,0.00,'Vente VNT-2026-9919'),(126,43,112,0.00,750.00,'Vente médicaments VNT-2026-9919'),(127,43,85,0.00,0.00,'TVA collectée VNT-2026-9919'),(128,44,99,450.00,0.00,'Sortie stock VNT-2026-9919'),(129,44,75,0.00,450.00,'Stock vendu VNT-2026-9919'),(130,45,112,10000.00,0.00,'Annulation vente VNT-2026-9917 - retour RET-2026-0001'),(131,45,85,0.00,0.00,'Annulation TVA VNT-2026-9917 - retour RET-2026-0001'),(132,45,93,0.00,10000.00,'Remboursement VNT-2026-9917 - retour RET-2026-0001'),(133,46,75,6500.00,0.00,'Retour stock RET-2026-0001'),(134,46,99,0.00,6500.00,'Annulation variation RET-2026-0001'),(135,47,93,1325.00,0.00,'Vente VNT-2026-9920'),(136,47,112,0.00,2650.00,'Vente médicaments VNT-2026-9920'),(137,47,85,0.00,0.00,'TVA collectée VNT-2026-9920'),(138,47,118,1325.00,0.00,'Remise VNT-2026-9920 (50%) - autorisé par SAIDOU MOHAMMADOU RACHID'),(139,48,99,1720.00,0.00,'Sortie stock VNT-2026-9920'),(140,48,75,0.00,1720.00,'Stock vendu VNT-2026-9920'),(141,49,93,3187.50,0.00,'Vente VNT-2026-9921'),(142,49,112,0.00,3750.00,'Vente médicaments VNT-2026-9921'),(143,49,85,0.00,0.00,'TVA collectée VNT-2026-9921'),(144,49,118,562.50,0.00,'Remise VNT-2026-9921 (15%) - autorisé par SAIDOU MOHAMMADOU RACHID'),(145,50,99,2420.00,0.00,'Sortie stock VNT-2026-9921'),(146,50,75,0.00,2420.00,'Stock vendu VNT-2026-9921'),(147,51,93,6500.00,0.00,'Vente VNT-2026-9922'),(148,51,112,0.00,6500.00,'Vente médicaments VNT-2026-9922'),(149,51,85,0.00,0.00,'TVA collectée VNT-2026-9922'),(150,52,99,4000.00,0.00,'Sortie stock VNT-2026-9922'),(151,52,75,0.00,4000.00,'Stock vendu VNT-2026-9922'),(152,53,97,547500.00,0.00,'Achat médicaments CMD-2026-9747'),(153,53,87,0.00,0.00,'TVA récupérable CMD-2026-9747'),(154,53,79,0.00,547500.00,'Fournisseur Pharmacie Principale CMR'),(155,54,75,547500.00,0.00,'Entrée stock CMD-2026-9747'),(156,54,99,0.00,547500.00,'Variation stock CMD-2026-9747'),(157,55,93,2840.00,0.00,'Vente VNT-2026-9923'),(158,55,112,0.00,3550.00,'Vente médicaments VNT-2026-9923'),(159,55,85,0.00,0.00,'TVA collectée VNT-2026-9923'),(160,55,118,710.00,0.00,'Remise 20% VNT-2026-9923'),(161,56,99,2250.00,0.00,'Sortie stock VNT-2026-9923'),(162,56,75,0.00,2250.00,'Stock vendu VNT-2026-9923'),(163,57,93,4800.00,0.00,'Vente VNT-2026-9924'),(164,57,112,0.00,4800.00,'Vente médicaments VNT-2026-9924'),(165,57,85,0.00,0.00,'TVA collectée VNT-2026-9924'),(166,58,99,3000.00,0.00,'Sortie stock VNT-2026-9924'),(167,58,75,0.00,3000.00,'Stock vendu VNT-2026-9924'),(168,59,93,3600.00,0.00,'Vente VNT-2026-9925'),(169,59,112,0.00,3600.00,'Vente médicaments VNT-2026-9925'),(170,59,85,0.00,0.00,'TVA collectée VNT-2026-9925'),(171,60,99,2250.00,0.00,'Sortie stock VNT-2026-9925'),(172,60,75,0.00,2250.00,'Stock vendu VNT-2026-9925');
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
  CONSTRAINT `ecritures_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ecritures`
--

LOCK TABLES `ecritures` WRITE;
/*!40000 ALTER TABLE `ecritures` DISABLE KEYS */;
INSERT INTO `ecritures` VALUES (1,'EC-20260509-2759','Achat CMD CMD-2026-002 - Laborex Cameroun','2026-05-09',1,1,'commande','CMD-2026-002',0,'2026-05-09 22:00:17'),(2,'EC-20260509-3366','Achat CMD CMD-2026-003 - Ubipharm Cameroun','2026-05-09',1,1,'commande','CMD-2026-003',0,'2026-05-09 22:00:21'),(5,'EC-20260510-9255','Achat CMD CMD-2026-1449 - CAMPHARM','2026-05-10',1,1,'commande','CMD-2026-1449',0,'2026-05-09 23:05:59'),(6,'EC-20260510-9764','Achat CMD CMD-2026-6029 - Laborex Cameroun','2026-05-10',1,1,'commande','CMD-2026-6029',0,'2026-05-09 23:16:45'),(7,'EC-20260511-6121','Vente VNT-2026-2742 - Espèces','2026-05-11',1,3,'vente','VNT-2026-2742',0,'2026-05-11 16:55:20'),(8,'EC-20260511-5652','Vente VNT-2026-7045 - Espèces','2026-05-11',1,3,'vente','VNT-2026-7045',0,'2026-05-11 16:56:15'),(9,'EC-20260511-9219','Vente VNT-2026-4525 - Espèces','2026-05-11',1,4,'vente','VNT-2026-4525',0,'2026-05-11 16:58:13'),(10,'EC-20260513-7058','Achat CMD CMD-2026-9743 - CAMPHARM','2026-05-13',1,1,'commande','CMD-2026-9743',0,'2026-05-13 18:37:05'),(11,'EC-20260513-2932','Vente VNT-2026-2889 - Espèces','2026-05-13',1,4,'vente','VNT-2026-2889',0,'2026-05-13 18:43:39'),(12,'EC-20260513-6921','Vente VNT-2026-1909 - Espèces','2026-05-13',1,4,'vente','VNT-2026-1909',0,'2026-05-13 18:44:16'),(13,'EC-20260514-2938','Vente VNT-2026-9758 - Crédit VIVA LOGONE','2026-05-14',1,4,'vente','VNT-2026-9758',0,'2026-05-14 13:26:57'),(14,'EC-20260514-5133','Vente VNT-2026-1284 - Crédit SEMRY','2026-05-14',1,4,'vente','VNT-2026-1284',0,'2026-05-14 13:29:40'),(15,'EC-20260515-5678','Vente VNT-2026-8969 - Crédit VIVA LOGONE','2026-05-15',1,1,'vente','VNT-2026-8969',0,'2026-05-15 12:07:18'),(16,'EC-20260515-8768','Vente VNT-2026-9234 - Crédit SEMRY','2026-05-15',1,1,'vente','VNT-2026-9234',0,'2026-05-15 14:01:16'),(17,'EC-20260515-9988','Vente VNT-2026-7976 - Crédit SEMRY','2026-05-15',1,1,'vente','VNT-2026-7976',0,'2026-05-15 15:15:59'),(18,'EC-20260515-0724','Vente VNT-2026-4231 - Crédit SEMRY','2026-05-15',1,1,'vente','VNT-2026-4231',0,'2026-05-15 15:16:11'),(19,'EC-20260515-5479','Vente VNT-2026-9904 - Crédit SEMRY','2026-05-15',1,1,'vente','VNT-2026-9904',0,'2026-05-15 15:24:08'),(20,'EC-20260515-1625','Vente VNT-2026-5697 - Crédit VIVA LOGONE','2026-05-15',1,1,'vente','VNT-2026-5697',0,'2026-05-15 15:24:29'),(21,'EC-20260515-5652','Vente VNT-2026-1213 - Crédit SEMRY','2026-05-15',1,1,'vente','VNT-2026-1213',0,'2026-05-15 15:28:16'),(22,'EC-20260515-5705','Vente VNT-2026-4455 - Crédit VIVA LOGONE','2026-05-15',1,1,'vente','VNT-2026-4455',0,'2026-05-15 15:28:29'),(23,'EC-20260515-3463','Vente VNT-2026-4968 - Crédit SEMRY','2026-05-15',1,1,'vente','VNT-2026-4968',0,'2026-05-15 15:59:39'),(24,'EC-20260515-6441','Stock entrée - Aerius (Desloratadine) 5mg (boîte de 10)','2026-05-15',1,1,'stock',NULL,0,'2026-05-15 18:11:30'),(25,'EC-20260516-4817','Vente VNT-2026-6775 - Crédit Rachid','2026-05-16',1,1,'vente','VNT-2026-6775',0,'2026-05-16 12:15:24'),(26,'EC-2026-0001','Vente VNT-2026-9905 - Crédit SEMRY','2026-05-18',1,1,'vente','VNT-2026-9905',0,'2026-05-18 12:22:05'),(27,'EC-2026-0002','Vente VNT-2026-9906 - Crédit SEMRY','2026-05-18',1,1,'vente','VNT-2026-9906',0,'2026-05-18 12:23:34'),(28,'EC-2026-0003','Vente VNT-2026-9907 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9907',0,'2026-06-02 13:35:37'),(29,'EC-2026-0004','Vente VNT-2026-9908 - Crédit VIVA LOGONE','2026-06-02',1,1,'vente','VNT-2026-9908',0,'2026-06-02 15:14:56'),(30,'EC-2026-0005','Vente VNT-2026-9909 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9909',0,'2026-06-02 18:56:41'),(31,'EC-2026-0006','Vente VNT-2026-9910 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9910',0,'2026-06-02 19:33:55'),(32,'EC-2026-0007','Vente VNT-2026-9911 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9911',0,'2026-06-02 19:37:12'),(33,'EC-2026-0008','Vente VNT-2026-9912 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9912',0,'2026-06-02 19:44:09'),(34,'EC-2026-0009','Achat CMD CMD-2026-9744 - CAMPHARM','2026-06-02',1,1,'commande','CMD-2026-9744',0,'2026-06-02 20:01:52'),(35,'EC-2026-0010','Vente VNT-2026-9913 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9913',0,'2026-06-02 22:31:26'),(36,'EC-2026-0011','Vente VNT-2026-9914 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9914',0,'2026-06-02 22:35:29'),(37,'EC-2026-0012','Vente VNT-2026-9915 - Espèces','2026-06-02',1,1,'vente','VNT-2026-9915',0,'2026-06-02 22:38:53'),(38,'EC-2026-0013','Vente VNT-2026-9916 - Espèces','2026-07-16',1,1,'vente','VNT-2026-9916',0,'2026-07-16 14:26:51'),(39,'EC-2026-0014','Vente VNT-2026-9917 - Espèces','2026-07-16',1,4,'vente','VNT-2026-9917',0,'2026-07-16 17:06:32'),(40,'EC-2026-0015','Achat CMD CMD-2026-9745 - Laborex Cameroun','2026-07-16',1,1,'commande','CMD-2026-9745',0,'2026-07-16 17:18:45'),(41,'EC-2026-0016','Vente VNT-2026-9918 - Espèces','2026-07-18',1,1,'vente','VNT-2026-9918',0,'2026-07-18 13:24:01'),(42,'EC-2026-0017','Sortie stock vente VNT-2026-9918','2026-07-18',1,1,'vente','VNT-2026-9918',0,'2026-07-18 13:24:01'),(43,'EC-2026-0018','Vente VNT-2026-9919 - Espèces','2026-07-18',1,1,'vente','VNT-2026-9919',0,'2026-07-18 13:31:11'),(44,'EC-2026-0019','Sortie stock vente VNT-2026-9919','2026-07-18',1,1,'vente','VNT-2026-9919',0,'2026-07-18 13:31:11'),(45,'EC-2026-0020','Retour RET-2026-0001 - Espèces','2026-07-18',1,1,'retour','RET-2026-0001',0,'2026-07-18 16:43:40'),(46,'EC-2026-0021','Retour stock vente RET-2026-0001','2026-07-18',1,1,'retour','RET-2026-0001',0,'2026-07-18 16:43:40'),(47,'EC-2026-0022','Vente VNT-2026-9920 - Espèces','2026-07-18',1,4,'vente','VNT-2026-9920',0,'2026-07-18 19:04:03'),(48,'EC-2026-0023','Sortie stock vente VNT-2026-9920','2026-07-18',1,4,'vente','VNT-2026-9920',0,'2026-07-18 19:04:03'),(49,'EC-2026-0024','Vente VNT-2026-9921 - Espèces','2026-07-18',1,4,'vente','VNT-2026-9921',0,'2026-07-18 19:29:36'),(50,'EC-2026-0025','Sortie stock vente VNT-2026-9921','2026-07-18',1,4,'vente','VNT-2026-9921',0,'2026-07-18 19:29:36'),(51,'EC-2026-0026','Vente VNT-2026-9922 - Espèces','2026-07-20',1,1,'vente','VNT-2026-9922',0,'2026-07-20 18:14:32'),(52,'EC-2026-0027','Sortie stock vente VNT-2026-9922','2026-07-20',1,1,'vente','VNT-2026-9922',0,'2026-07-20 18:14:32'),(53,'EC-2026-0028','Achat CMD CMD-2026-9747 - Pharmacie Principale CMR','2026-07-20',1,1,'commande','CMD-2026-9747',0,'2026-07-20 18:30:59'),(54,'EC-2026-0029','Entrée stock CMD CMD-2026-9747','2026-07-20',1,1,'commande','CMD-2026-9747',0,'2026-07-20 18:30:59'),(55,'EC-2026-0030','Vente VNT-2026-9923 - Espèces','2026-07-21',1,4,'vente','VNT-2026-9923',0,'2026-07-21 14:04:20'),(56,'EC-2026-0031','Sortie stock vente VNT-2026-9923','2026-07-21',1,4,'vente','VNT-2026-9923',0,'2026-07-21 14:04:20'),(57,'EC-2026-0032','Vente VNT-2026-9924 - Espèces','2026-07-21',1,1,'vente','VNT-2026-9924',0,'2026-07-21 14:54:45'),(58,'EC-2026-0033','Sortie stock vente VNT-2026-9924','2026-07-21',1,1,'vente','VNT-2026-9924',0,'2026-07-21 14:54:45'),(59,'EC-2026-0034','Vente VNT-2026-9925 - Espèces','2026-07-21',1,1,'vente','VNT-2026-9925',0,'2026-07-21 15:03:19'),(60,'EC-2026-0035','Sortie stock vente VNT-2026-9925','2026-07-21',1,1,'vente','VNT-2026-9925',0,'2026-07-21 15:03:19');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fournisseurs`
--

LOCK TABLES `fournisseurs` WRITE;
/*!40000 ALTER TABLE `fournisseurs` DISABLE KEYS */;
INSERT INTO `fournisseurs` VALUES (1,'Copharmacie SA','Paul Essomba','+237 699 12 34 56','appro@copharmacie.cm','Bonapriso, Rue Joss','Douala',1,'2026-05-05 14:12:07'),(2,'Laborex Cameroun','Chantal Atangana','+237 677 98 76 54','info@laborex.cm','Akwa, Boulevard de la Liberte','Douala',1,'2026-05-05 14:12:07'),(3,'Pharmacie Principale CMR','Andre Nganou','+237 655 44 33 22','contact@pharmaprincipale.cm','Bastos, Rue de l\'Hopital','Yaounde',1,'2026-05-05 14:12:07'),(4,'Ubipharm Cameroun','Lucie Mbang','+237 688 77 66 55','ubipharm@ubipharm.cm','Bonaberi, Avenue de la Reunification','Douala',1,'2026-05-05 14:12:07'),(5,'CAMPHARM','Victor Kamga','+237 666 11 22 33','campharm@campharm.cm','Quartier du Lac, Boulevard Mitterrand','Yaounde',1,'2026-05-05 14:12:07');
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
  `moyen` enum('esp?ces','carte','ch?que','assurance','cr?dit') NOT NULL DEFAULT 'esp?ces',
  `reference_vente` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  CONSTRAINT `mouvements_caisse_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions_caisse` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_caisse`
--

LOCK TABLES `mouvements_caisse` WRITE;
/*!40000 ALTER TABLE `mouvements_caisse` DISABLE KEYS */;
INSERT INTO `mouvements_caisse` VALUES (1,1,'entrée',150000.00,'Vente VNT-2026-001','','VNT-2026-001','2026-05-06 19:59:26'),(2,1,'entrée',45000.00,'Vente VNT-2026-003','','VNT-2026-003','2026-05-06 19:59:26'),(3,1,'sortie',5000.00,'Achat fournitures bureau','',NULL,'2026-05-06 19:59:26'),(4,2,'entrée',1431.00,'Vente VNT-2026-7389','','VNT-2026-7389','2026-05-06 22:07:10'),(5,3,'entrée',7989.75,'Vente VNT-2026-2742','','VNT-2026-2742','2026-05-11 16:55:20'),(6,3,'entrée',2504.25,'Vente VNT-2026-7045','','VNT-2026-7045','2026-05-11 16:56:15'),(7,4,'entrée',2921.63,'Vente VNT-2026-4525','','VNT-2026-4525','2026-05-11 16:58:13'),(8,3,'sortie',0.00,'Fermeture forcée admin : fin','',NULL,'2026-05-11 17:02:33'),(9,6,'entrée',7453.13,'Vente VNT-2026-2889','','VNT-2026-2889','2026-05-13 18:43:39'),(10,6,'entrée',6499.13,'Vente VNT-2026-1909','','VNT-2026-1909','2026-05-13 18:44:16'),(11,7,'sortie',150000.00,'Retrait : facture achat ordinateur','',NULL,'2026-05-13 19:59:23'),(12,8,'entrée',0.00,'Vente crédit VNT-2026-8969','','VNT-2026-8969','2026-05-15 12:07:18'),(13,8,'entrée',0.00,'Vente crédit VNT-2026-9234','','VNT-2026-9234','2026-05-15 14:01:16'),(14,7,'sortie',0.00,'Fermeture forcée admin : fin journée','',NULL,'2026-05-15 15:14:40'),(15,9,'entrée',0.00,'Vente crédit VNT-2026-7976','','VNT-2026-7976','2026-05-15 15:15:59'),(16,9,'entrée',0.00,'Vente crédit VNT-2026-4231','','VNT-2026-4231','2026-05-15 15:16:11'),(17,9,'entrée',0.00,'Vente crédit VNT-2026-9904','','VNT-2026-9904','2026-05-15 15:24:08'),(18,9,'entrée',0.00,'Vente crédit VNT-2026-5697','','VNT-2026-5697','2026-05-15 15:24:29'),(19,9,'entrée',0.00,'Vente crédit VNT-2026-1213','','VNT-2026-1213','2026-05-15 15:28:16'),(20,9,'entrée',0.00,'Vente crédit VNT-2026-4455','','VNT-2026-4455','2026-05-15 15:28:29'),(21,9,'entrée',0.00,'Vente crédit VNT-2026-4968','','VNT-2026-4968','2026-05-15 15:59:39'),(22,9,'entrée',0.00,'Vente crédit VNT-2026-6775','','VNT-2026-6775','2026-05-16 12:15:24'),(23,10,'entrée',0.00,'Vente crédit VNT-2026-9905','','VNT-2026-9905','2026-05-18 12:22:05'),(24,10,'entrée',0.00,'Vente crédit VNT-2026-9906','','VNT-2026-9906','2026-05-18 12:23:34'),(25,12,'entrée',9500.00,'Vente VNT-2026-9907','','VNT-2026-9907','2026-06-02 13:35:37'),(26,12,'entrée',0.00,'Vente crédit VNT-2026-9908','','VNT-2026-9908','2026-06-02 15:14:56'),(27,12,'entrée',36400.00,'Vente VNT-2026-9909','','VNT-2026-9909','2026-06-02 18:56:41'),(28,12,'entrée',4500.00,'Vente VNT-2026-9910','','VNT-2026-9910','2026-06-02 19:33:55'),(29,12,'entrée',1800.00,'Vente VNT-2026-9911','','VNT-2026-9911','2026-06-02 19:37:12'),(30,12,'entrée',13000.00,'Vente VNT-2026-9912','','VNT-2026-9912','2026-06-02 19:44:09'),(31,13,'entrée',5000.00,'Vente VNT-2026-9913','','VNT-2026-9913','2026-06-02 22:31:26'),(32,13,'entrée',9600.00,'Vente VNT-2026-9914','','VNT-2026-9914','2026-06-02 22:35:29'),(33,13,'entrée',4000.00,'Vente VNT-2026-9915','','VNT-2026-9915','2026-06-02 22:38:53'),(34,14,'entrée',1900.00,'Vente VNT-2026-9916','','VNT-2026-9916','2026-07-16 14:26:51'),(35,15,'entrée',19000.00,'Vente VNT-2026-9917','','VNT-2026-9917','2026-07-16 17:06:32'),(36,16,'entrée',1450.00,'Vente VNT-2026-9918','','VNT-2026-9918','2026-07-18 13:24:01'),(37,16,'entrée',750.00,'Vente VNT-2026-9919','','VNT-2026-9919','2026-07-18 13:31:11'),(38,16,'sortie',10000.00,'Remboursement retour RET-2026-0001','','RET-2026-0001','2026-07-18 16:43:40'),(39,15,'sortie',0.00,'Fermeture forcée admin : fin de journée','',NULL,'2026-07-18 18:59:00'),(40,18,'entrée',1325.00,'Vente VNT-2026-9920','','VNT-2026-9920','2026-07-18 19:04:02'),(41,18,'entrée',3187.50,'Vente VNT-2026-9921','','VNT-2026-9921','2026-07-18 19:29:36'),(42,17,'entrée',6500.00,'Vente VNT-2026-9922','','VNT-2026-9922','2026-07-20 18:14:32'),(43,18,'sortie',0.00,'Fermeture forcée admin : cloture','',NULL,'2026-07-20 18:19:50'),(46,20,'entrée',2840.00,'Vente VNT-2026-9923','','VNT-2026-9923','2026-07-21 14:04:20'),(47,22,'entrée',4800.00,'Vente VNT-2026-9924','','VNT-2026-9924','2026-07-21 14:54:45'),(48,22,'entrée',3600.00,'Vente VNT-2026-9925','','VNT-2026-9925','2026-07-21 15:03:19');
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
  CONSTRAINT `mouvements_magasin_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mouvements_magasin_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_magasin`
--

LOCK TABLES `mouvements_magasin` WRITE;
/*!40000 ALTER TABLE `mouvements_magasin` DISABLE KEYS */;
INSERT INTO `mouvements_magasin` VALUES (3,68,'entrée',45,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:37:04'),(4,62,'entrée',2000,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:37:04'),(5,69,'entrée',130,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:37:04'),(6,62,'entrée',7,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:37:38'),(7,51,'entrée',125,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:40:02'),(8,37,'entrée',110,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:40:02'),(9,9,'entrée',1059,'Retour pharmacie → magasin',1,NULL,'2026-07-16 18:40:02'),(10,68,'sortie',25,'Transfert TRF-2026-0001 vers pharmacie',1,1,'2026-07-17 15:49:38'),(11,51,'entrée',400,'Livraison CMD CMD-2026-9747 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,NULL,'2026-07-20 18:30:59'),(12,62,'entrée',250,'Livraison CMD CMD-2026-9747 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,NULL,'2026-07-20 18:30:59'),(13,68,'entrée',500,'Livraison CMD CMD-2026-9747 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,NULL,'2026-07-20 18:30:59'),(14,69,'entrée',300,'Livraison CMD CMD-2026-9747 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,NULL,'2026-07-20 18:30:59'),(15,68,'sortie',100,'Transfert TRF-2026-0002 vers pharmacie',1,2,'2026-07-21 14:46:16'),(16,68,'sortie',100,'Transfert TRF-2026-0003 vers Pharmacie B',1,3,'2026-07-21 14:54:10'),(17,62,'sortie',1000,'Transfert TRF-2026-0003 vers Pharmacie B',1,3,'2026-07-21 14:54:10');
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
  CONSTRAINT `mouvements_stock_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mouvements_stock_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_stock`
--

LOCK TABLES `mouvements_stock` WRITE;
/*!40000 ALTER TABLE `mouvements_stock` DISABLE KEYS */;
INSERT INTO `mouvements_stock` VALUES (1,1,'entrée',100,'Commande CMD-2026-001',1,'2026-05-05 14:12:07'),(2,22,'entrée',50,'Commande CMD-2026-001',1,'2026-05-05 14:12:07'),(3,8,'entrée',50,'Commande CMD-2026-001',1,'2026-05-05 14:12:07'),(4,55,'entrée',60,'Commande CMD-2026-001',1,'2026-05-05 14:12:07'),(5,30,'entrée',80,'Commande CMD-2026-001',1,'2026-05-05 14:12:07'),(6,1,'sortie',3,'Vente VNT-2026-001',3,'2026-05-05 14:12:07'),(7,22,'sortie',1,'Vente VNT-2026-001',3,'2026-05-05 14:12:07'),(8,55,'sortie',2,'Vente VNT-2026-001',3,'2026-05-05 14:12:07'),(9,8,'sortie',2,'Vente VNT-2026-002',3,'2026-05-05 14:12:07'),(10,38,'sortie',1,'Vente VNT-2026-002',3,'2026-05-05 14:12:07'),(11,49,'sortie',1,'Vente VNT-2026-002',3,'2026-05-05 14:12:07'),(12,44,'sortie',2,'Vente VNT-2026-003',2,'2026-05-05 14:12:07'),(13,10,'sortie',1,'Vente VNT-2026-004',2,'2026-05-05 14:12:07'),(14,30,'sortie',3,'Vente VNT-2026-004',2,'2026-05-05 14:12:07'),(15,37,'sortie',1,'Vente VNT-2026-004',2,'2026-05-05 14:12:07'),(16,62,'sortie',1,'Vente VNT-2026-7196',1,'2026-05-05 17:37:51'),(17,63,'sortie',1,'Vente VNT-2026-7196',1,'2026-05-05 17:37:51'),(18,62,'sortie',1,'Vente VNT-2026-9323',1,'2026-05-06 18:13:59'),(19,63,'sortie',1,'Vente VNT-2026-9323',1,'2026-05-06 18:13:59'),(20,62,'sortie',1,'Vente VNT-2026-7389',1,'2026-05-06 22:07:10'),(21,33,'entrée',1000,'Livraison CMD CMD-2026-1449 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé',1,'2026-05-09 23:05:59'),(22,38,'entrée',1000,'Livraison CMD CMD-2026-1449 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé',1,'2026-05-09 23:05:59'),(23,1,'entrée',1000,'Livraison CMD CMD-2026-6029 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,'2026-05-09 23:16:45'),(24,9,'sortie',1,'Vente VNT-2026-2742',3,'2026-05-11 16:55:20'),(25,16,'sortie',1,'Vente VNT-2026-2742',3,'2026-05-11 16:55:20'),(26,62,'sortie',1,'Vente VNT-2026-2742',3,'2026-05-11 16:55:20'),(27,63,'sortie',1,'Vente VNT-2026-2742',3,'2026-05-11 16:55:20'),(28,7,'sortie',1,'Vente VNT-2026-7045',3,'2026-05-11 16:56:15'),(29,34,'sortie',1,'Vente VNT-2026-7045',3,'2026-05-11 16:56:15'),(30,60,'sortie',1,'Vente VNT-2026-7045',3,'2026-05-11 16:56:15'),(31,24,'sortie',1,'Vente VNT-2026-4525',4,'2026-05-11 16:58:13'),(32,29,'sortie',1,'Vente VNT-2026-4525',4,'2026-05-11 16:58:13'),(33,71,'sortie',1,'Vente VNT-2026-4525',4,'2026-05-11 16:58:13'),(34,59,'entrée',1000,'Livraison CMD CMD-2026-9743 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,'2026-05-13 18:37:05'),(35,3,'sortie',1,'Vente VNT-2026-2889',4,'2026-05-13 18:43:39'),(36,7,'sortie',1,'Vente VNT-2026-2889',4,'2026-05-13 18:43:39'),(37,60,'sortie',1,'Vente VNT-2026-2889',4,'2026-05-13 18:43:39'),(38,62,'sortie',1,'Vente VNT-2026-2889',4,'2026-05-13 18:43:39'),(39,63,'sortie',1,'Vente VNT-2026-2889',4,'2026-05-13 18:43:39'),(40,2,'sortie',2,'Vente VNT-2026-1909',4,'2026-05-13 18:44:16'),(41,7,'sortie',5,'Vente VNT-2026-1909',4,'2026-05-13 18:44:16'),(42,21,'sortie',3,'Vente VNT-2026-1909',4,'2026-05-13 18:44:16'),(43,3,'sortie',1,'Vente VNT-2026-9758',4,'2026-05-14 13:26:57'),(44,4,'sortie',1,'Vente VNT-2026-9758',4,'2026-05-14 13:26:57'),(45,60,'sortie',1,'Vente VNT-2026-9758',4,'2026-05-14 13:26:57'),(46,61,'sortie',1,'Vente VNT-2026-9758',4,'2026-05-14 13:26:57'),(47,62,'sortie',1,'Vente VNT-2026-9758',4,'2026-05-14 13:26:57'),(48,63,'sortie',1,'Vente VNT-2026-9758',4,'2026-05-14 13:26:57'),(49,7,'sortie',10,'Vente VNT-2026-1284',4,'2026-05-14 13:29:40'),(50,62,'sortie',4,'Vente VNT-2026-8969',1,'2026-05-15 12:07:18'),(51,63,'sortie',1,'Vente VNT-2026-8969',1,'2026-05-15 12:07:18'),(52,3,'sortie',1,'Vente VNT-2026-9234',1,'2026-05-15 14:01:16'),(53,5,'sortie',2,'Vente VNT-2026-9234',1,'2026-05-15 14:01:16'),(54,61,'sortie',1,'Vente VNT-2026-9234',1,'2026-05-15 14:01:16'),(55,62,'sortie',5,'Vente VNT-2026-7976',1,'2026-05-15 15:15:59'),(56,5,'sortie',5,'Vente VNT-2026-4231',1,'2026-05-15 15:16:11'),(57,62,'sortie',7,'Vente VNT-2026-9904',1,'2026-05-15 15:24:08'),(58,6,'sortie',10,'Vente VNT-2026-5697',1,'2026-05-15 15:24:29'),(59,61,'sortie',10,'Vente VNT-2026-5697',1,'2026-05-15 15:24:29'),(60,62,'sortie',10,'Vente VNT-2026-1213',1,'2026-05-15 15:28:16'),(61,60,'sortie',10,'Vente VNT-2026-4455',1,'2026-05-15 15:28:29'),(62,61,'sortie',10,'Vente VNT-2026-4968',1,'2026-05-15 15:59:39'),(63,62,'entrée',1000,'',1,'2026-05-15 18:11:30'),(64,1,'sortie',4,'Vente VNT-2026-6775',1,'2026-05-16 12:15:24'),(65,3,'sortie',3,'Vente VNT-2026-6775',1,'2026-05-16 12:15:24'),(66,5,'sortie',5,'Vente VNT-2026-6775',1,'2026-05-16 12:15:24'),(67,6,'sortie',5,'Vente VNT-2026-6775',1,'2026-05-16 12:15:24'),(68,5,'sortie',5,'Vente VNT-2026-9905',1,'2026-05-18 12:22:05'),(69,60,'sortie',4,'Vente VNT-2026-9905',1,'2026-05-18 12:22:05'),(70,61,'sortie',1,'Vente VNT-2026-9905',1,'2026-05-18 12:22:05'),(71,62,'sortie',1,'Vente VNT-2026-9905',1,'2026-05-18 12:22:05'),(72,63,'sortie',1,'Vente VNT-2026-9905',1,'2026-05-18 12:22:05'),(73,3,'sortie',8,'Vente VNT-2026-9906',1,'2026-05-18 12:23:34'),(74,60,'sortie',5,'Vente VNT-2026-9906',1,'2026-05-18 12:23:34'),(75,20,'sortie',10,'Vente VNT-2026-9907',1,'2026-06-02 13:35:37'),(76,63,'sortie',5,'Vente VNT-2026-9908',1,'2026-06-02 15:14:56'),(77,63,'sortie',13,'Vente VNT-2026-9909',1,'2026-06-02 18:56:41'),(78,61,'sortie',10,'Vente VNT-2026-9910',1,'2026-06-02 19:33:55'),(79,61,'sortie',4,'Vente VNT-2026-9911',1,'2026-06-02 19:37:12'),(80,60,'sortie',13,'Vente VNT-2026-9912',1,'2026-06-02 19:44:09'),(81,9,'entrée',1000,'Livraison CMD CMD-2026-9744 — Colis en bon état ; Quantité conforme ; Produits conformes',1,'2026-06-02 20:01:52'),(82,5,'sortie',20,'Vente VNT-2026-9913',1,'2026-06-02 22:31:26'),(83,7,'sortie',16,'Vente VNT-2026-9914',1,'2026-06-02 22:35:29'),(84,4,'sortie',8,'Vente VNT-2026-9915',1,'2026-06-02 22:38:53'),(85,3,'sortie',1,'Vente VNT-2026-9916',1,'2026-07-16 14:26:51'),(86,5,'sortie',1,'Vente VNT-2026-9916',1,'2026-07-16 14:26:51'),(87,60,'sortie',1,'Vente VNT-2026-9916',1,'2026-07-16 14:26:51'),(88,60,'sortie',19,'Vente VNT-2026-9917',4,'2026-07-16 17:06:32'),(89,62,'entrée',1000,'Livraison CMD CMD-2026-9745 — Colis en bon état ; Quantité conforme ; Produits conformes ; Dates de validité correctes ; Bon de réception signé ; Facture reçue',1,'2026-07-16 17:18:45'),(91,68,'sortie',45,'Retour pharmacie → magasin',1,'2026-07-16 18:37:04'),(92,62,'sortie',2000,'Retour pharmacie → magasin',1,'2026-07-16 18:37:04'),(93,69,'sortie',130,'Retour pharmacie → magasin',1,'2026-07-16 18:37:04'),(94,62,'sortie',7,'Retour pharmacie → magasin',1,'2026-07-16 18:37:38'),(95,51,'sortie',125,'Retour pharmacie → magasin',1,'2026-07-16 18:40:02'),(96,37,'sortie',110,'Retour pharmacie → magasin',1,'2026-07-16 18:40:02'),(97,9,'sortie',1059,'Retour pharmacie → magasin',1,'2026-07-16 18:40:02'),(98,68,'entrée',25,'Transfert TRF-2026-0001 du magasin',1,'2026-07-17 15:49:38'),(99,5,'sortie',4,'Vente VNT-2026-9918',1,'2026-07-18 13:24:01'),(100,61,'sortie',1,'Vente VNT-2026-9918',1,'2026-07-18 13:24:01'),(101,5,'sortie',3,'Vente VNT-2026-9919',1,'2026-07-18 13:31:11'),(102,60,'entrée',10,'Retour vente VNT-2026-9917 → RET-2026-0001',1,'2026-07-18 16:43:39'),(103,3,'sortie',1,'Vente VNT-2026-9920',4,'2026-07-18 19:04:02'),(104,60,'sortie',2,'Vente VNT-2026-9920',4,'2026-07-18 19:04:02'),(105,3,'sortie',2,'Vente VNT-2026-9921',4,'2026-07-18 19:29:36'),(106,60,'sortie',2,'Vente VNT-2026-9921',4,'2026-07-18 19:29:36'),(107,61,'sortie',1,'Vente VNT-2026-9921',4,'2026-07-18 19:29:36'),(108,68,'sortie',10,'Vente VNT-2026-9922',1,'2026-07-20 18:14:32'),(111,2,'sortie',1,'Vente VNT-2026-9923',4,'2026-07-21 14:04:20'),(112,3,'sortie',1,'Vente VNT-2026-9923',4,'2026-07-21 14:04:20'),(113,6,'sortie',1,'Vente VNT-2026-9923',4,'2026-07-21 14:04:20'),(114,18,'sortie',1,'Vente VNT-2026-9923',4,'2026-07-21 14:04:20'),(115,20,'sortie',1,'Vente VNT-2026-9923',4,'2026-07-21 14:04:20'),(116,21,'sortie',1,'Vente VNT-2026-9923',4,'2026-07-21 14:04:20'),(117,68,'entrée',100,'Transfert TRF-2026-0002 du magasin',1,'2026-07-21 14:46:16'),(118,68,'entrée',100,'Transfert TRF-2026-0003 du magasin (Pharmacie B)',1,'2026-07-21 14:54:10'),(119,62,'entrée',1000,'Transfert TRF-2026-0003 du magasin (Pharmacie B)',1,'2026-07-21 14:54:10'),(120,62,'sortie',4,'Vente VNT-2026-9924',1,'2026-07-21 14:54:45'),(121,62,'sortie',3,'Vente VNT-2026-9925',1,'2026-07-21 15:03:19');
/*!40000 ALTER TABLE `mouvements_stock` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `parametres` WRITE;
/*!40000 ALTER TABLE `parametres` DISABLE KEYS */;
INSERT INTO `parametres` VALUES ('app_nom','PharmaCare','Nom de la pharmacie','général'),('caisse_fermeture_mode','manuel','Mode fermeture caisse','caisse'),('caisse_heure_fermeture','18:00','Heure fermeture auto','caisse'),('delai_inactivite_min','20',NULL,'general'),('devise','XAF','Devise','général'),('devise_pos','after','Position symbole','général'),('devise_symbole','FCFA','Symbole devise','général'),('fidelite_active','1',NULL,'general'),('pharmacie_adresse','',NULL,'général'),('pharmacie_nif','',NULL,'général'),('pharmacie_telephone','',NULL,'général'),('police','Manrope','Police principale','général'),('police_titre','Manrope','Police titres','général'),('prefix_vente','VNT',NULL,'general'),('remise_code_ttl_min','15','Validité code remise (min)','ventes'),('remise_max_pct','100','Remise max (%)','ventes'),('theme','dark-rose','Thème couleur','général'),('ticket_pied','Merci pour votre achat ! pharmaCare (c) 2026',NULL,'général'),('ticket_sous_titre','PharmaCare',NULL,'général'),('tva','0.00','Taux TVA (%)','général');
/*!40000 ALTER TABLE `parametres` ENABLE KEYS */;
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
INSERT INTO `permissions` VALUES (1,'dashboard.voir','Voir le tableau de bord','dashboard','2026-05-04 16:56:45'),(2,'vente.creer','Créer des ventes (Point de Vente)','vente','2026-05-04 16:56:45'),(3,'stock.voir','Voir le stock','stock','2026-05-04 16:56:45'),(4,'stock.ajuster','Ajuster le stock','stock','2026-05-04 16:56:45'),(5,'produits.voir','Voir les médicaments','produits','2026-05-04 16:56:45'),(6,'produits.ajouter','Ajouter des médicaments','produits','2026-05-04 16:56:45'),(7,'produits.modifier','Modifier des médicaments','produits','2026-05-04 16:56:45'),(8,'produits.archiver','Archiver des médicaments','produits','2026-05-04 16:56:45'),(9,'fournisseurs.voir','Voir les fournisseurs','fournisseurs','2026-05-04 16:56:45'),(10,'fournisseurs.ajouter','Ajouter des fournisseurs','fournisseurs','2026-05-04 16:56:45'),(11,'fournisseurs.modifier','Modifier des fournisseurs','fournisseurs','2026-05-04 16:56:45'),(12,'fournisseurs.supprimer','Supprimer des fournisseurs','fournisseurs','2026-05-04 16:56:45'),(13,'commandes.voir','Voir les commandes','commandes','2026-05-04 16:56:45'),(14,'commandes.creer','Créer des commandes','commandes','2026-05-04 16:56:45'),(15,'commandes.modifier','Modifier des commandes','commandes','2026-05-04 16:56:45'),(16,'ventes_hist.voir','Voir l\'historique des ventes','ventes_hist','2026-05-04 16:56:45'),(17,'rapports.voir','Voir les rapports','rapports','2026-05-04 16:56:45'),(18,'utilisateurs.voir','Voir les utilisateurs','utilisateurs','2026-05-04 16:56:45'),(19,'utilisateurs.gerer','Gérer les utilisateurs','utilisateurs','2026-05-04 16:56:45'),(20,'categories.voir','Voir les catégories','categories','2026-05-04 16:56:45'),(21,'categories.gerer','Gérer les catégories','categories','2026-05-04 16:56:45'),(22,'parametres.voir','Voir les paramètres','parametres','2026-05-04 16:56:45'),(23,'parametres.gerer','Gérer les paramètres','parametres','2026-05-04 16:56:45'),(24,'roles.voir','Voir les rôles & permissions','roles','2026-05-04 16:56:45'),(25,'roles.gerer','Gérer les rôles & permissions','roles','2026-05-04 16:56:45'),(26,'caisse.voir','Voir le dashboard des caisses','caisse','2026-05-06 19:59:26'),(27,'caisse.gerer','Gérer les caisses','caisse','2026-05-06 19:59:26'),(28,'caisse.ouvrir','Ouvrir une session de caisse','caisse','2026-05-06 19:59:26'),(29,'comptabilite.voir','Accéder à la comptabilité','comptabilite','2026-05-09 17:25:41'),(30,'comptabilite.saisie','Saisir des écritures manuelles','comptabilite','2026-05-09 17:25:41'),(31,'comptabilite.plan','Gérer le plan comptable','comptabilite','2026-05-09 17:25:41'),(32,'clients.voir','Voir la liste des clients','clients','2026-05-14 12:44:54'),(33,'clients.ajouter','Créer un client','clients','2026-05-14 12:44:54'),(34,'clients.modifier','Modifier une fiche client','clients','2026-05-14 12:44:54'),(35,'clients.supprimer','Désactiver un client','clients','2026-05-14 12:44:54'),(36,'clients.paiements','Enregistrer des règlements','clients','2026-05-14 12:44:54'),(37,'magasin.voir','Voir le stock magasin','magasin','2026-07-16 18:22:19'),(38,'magasin.gerer','Gérer le magasin (réceptions & transferts)','magasin','2026-07-16 18:22:19'),(39,'retours.gerer','Gérer les retours de ventes','retours','2026-07-18 16:37:29'),(41,'remise.approuver','Approuver une remise (générer un code)','vente','2026-07-18 17:09:18'),(44,'remise.approbateurs.gerer','Gérer la liste des approbateurs de remise','remise','2026-07-18 17:48:32'),(54,'pharmacies.voir','Voir les pharmacies','pharmacies','2026-07-21 14:26:47'),(55,'pharmacies.gerer','G├®rer les pharmacies','pharmacies','2026-07-21 14:26:47'),(58,'menus.voir','Voir les menus','menus','2026-07-21 15:15:53'),(59,'menus.gerer','G├®rer les menus','menus','2026-07-21 15:15:53');
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
INSERT INTO `produit_pharmacie` VALUES (1,1,1276,30),(1,3,0,10),(1,4,0,10),(2,1,192,25),(2,3,0,10),(2,4,0,10),(3,1,121,20),(3,3,0,10),(3,4,0,10),(4,1,301,30),(4,3,0,10),(4,4,0,10),(5,1,175,25),(5,3,0,10),(5,4,0,10),(6,1,159,20),(6,3,0,10),(6,4,0,10),(7,1,97,20),(7,3,0,10),(7,4,0,10),(8,1,85,15),(8,3,0,10),(8,4,0,10),(9,1,0,15),(9,3,0,10),(9,4,0,10),(10,1,42,10),(10,3,0,10),(10,4,0,10),(11,1,55,10),(11,3,0,10),(11,4,0,10),(12,1,90,15),(12,3,0,10),(12,4,0,10),(13,1,150,20),(13,3,0,10),(13,4,0,10),(14,1,75,10),(14,3,0,10),(14,4,0,10),(15,1,38,10),(15,3,0,10),(15,4,0,10),(16,1,27,10),(16,3,0,10),(16,4,0,10),(17,1,20,8),(17,3,0,10),(17,4,0,10),(18,1,119,15),(18,3,0,10),(18,4,0,10),(19,1,65,10),(19,3,0,10),(19,4,0,10),(20,1,19,8),(20,3,0,10),(20,4,0,10),(21,1,41,10),(21,3,0,10),(21,4,0,10),(22,1,200,30),(22,3,0,10),(22,4,0,10),(23,1,180,25),(23,3,0,10),(23,4,0,10),(24,1,94,15),(24,3,0,10),(24,4,0,10),(25,1,40,10),(25,3,0,10),(25,4,0,10),(26,1,110,15),(26,3,0,10),(26,4,0,10),(27,1,65,10),(27,3,0,10),(27,4,0,10),(28,1,150,20),(28,3,0,10),(28,4,0,10),(29,1,24,8),(29,3,0,10),(29,4,0,10),(30,1,320,30),(30,3,0,10),(30,4,0,10),(31,1,180,20),(31,3,0,10),(31,4,0,10),(32,1,95,15),(32,3,0,10),(32,4,0,10),(33,1,1140,15),(33,3,0,10),(33,4,0,10),(34,1,209,25),(34,3,0,10),(34,4,0,10),(35,1,160,20),(35,3,0,10),(35,4,0,10),(36,1,120,15),(36,3,0,10),(36,4,0,10),(37,1,0,15),(37,3,0,10),(37,4,0,10),(38,1,1075,10),(38,3,0,10),(38,4,0,10),(39,1,60,10),(39,3,0,10),(39,4,0,10),(40,1,90,12),(40,3,0,10),(40,4,0,10),(41,1,80,10),(41,3,0,10),(41,4,0,10),(42,1,100,15),(42,3,0,10),(42,4,0,10),(43,1,40,8),(43,3,0,10),(43,4,0,10),(44,1,165,20),(44,3,0,10),(44,4,0,10),(45,1,120,15),(45,3,0,10),(45,4,0,10),(46,1,80,10),(46,3,0,10),(46,4,0,10),(47,1,55,10),(47,3,0,10),(47,4,0,10),(48,1,30,8),(48,3,0,10),(48,4,0,10),(49,1,42,10),(49,3,0,10),(49,4,0,10),(50,1,70,15),(50,3,0,10),(50,4,0,10),(51,1,0,15),(51,3,0,10),(51,4,0,10),(52,1,35,8),(52,3,0,10),(52,4,0,10),(53,1,90,12),(53,3,0,10),(53,4,0,10),(54,1,165,20),(54,3,0,10),(54,4,0,10),(55,1,200,25),(55,3,0,10),(55,4,0,10),(56,1,85,10),(56,3,0,10),(56,4,0,10),(57,1,60,10),(57,3,0,10),(57,4,0,10),(58,1,95,12),(58,3,0,10),(58,4,0,10),(59,1,1075,10),(59,3,0,10),(59,4,0,10),(60,1,6,10),(60,3,0,10),(60,4,0,10),(61,1,41,12),(61,3,0,10),(61,4,0,10),(62,1,0,8),(62,3,993,10),(62,4,0,10),(63,1,0,8),(63,3,0,10),(63,4,0,10),(64,1,70,10),(64,3,0,10),(64,4,0,10),(65,1,120,15),(65,3,0,10),(65,4,0,10),(66,1,55,10),(66,3,0,10),(66,4,0,10),(67,1,30,8),(67,3,0,10),(67,4,0,10),(68,1,15,10),(68,3,100,10),(68,4,0,10),(69,1,0,15),(69,3,0,10),(69,4,0,10),(70,1,110,15),(70,3,0,10),(70,4,0,10),(71,1,39,8),(71,3,0,10),(71,4,0,10),(72,1,25,8),(72,3,0,10),(72,4,0,10),(73,1,35,8),(73,3,0,10),(73,4,0,10);
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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produits`
--

LOCK TABLES `produits` WRITE;
/*!40000 ALTER TABLE `produits` DISABLE KEYS */;
INSERT INTO `produits` VALUES (1,'Paracétamol 500mg (boîte de 20)','MED-001',1,1,NULL,1276,0,20,30,180.00,300.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(2,'Paracétamol 1g (boîte de 10)','MED-002',1,1,NULL,192,0,20,25,250.00,400.00,9.00,'2027-03-31',1,'2026-05-05 14:12:07'),(3,'Doliprane 1000mg (boîte de 8)','MED-003',1,2,NULL,121,0,20,20,420.00,650.00,9.00,'2027-09-30',1,'2026-05-05 14:12:07'),(4,'Doliprane 500mg (boîte de 16)','MED-004',1,1,NULL,301,0,20,30,320.00,500.00,9.00,'2027-08-31',1,'2026-05-05 14:12:07'),(5,'Aspirine 500mg (boîte de 20)','MED-005',1,1,NULL,175,0,20,25,150.00,250.00,9.00,'2028-01-31',1,'2026-05-05 14:12:07'),(6,'Ibuprofène 400mg (boîte de 20)','MED-006',1,3,NULL,159,0,20,20,350.00,550.00,9.00,'2027-05-31',1,'2026-05-05 14:12:07'),(7,'Efferalgan 500mg (boîte de 16)','MED-007',1,2,NULL,97,0,20,20,380.00,600.00,9.00,'2027-04-30',1,'2026-05-05 14:12:07'),(8,'Amoxicilline 500mg (boîte de 12)','MED-008',2,1,NULL,85,0,20,15,480.00,750.00,9.00,'2026-12-31',1,'2026-05-05 14:12:07'),(9,'Amoxicilline 1g (boîte de 8)','MED-009',2,2,NULL,0,1059,20,15,650.00,1000.00,9.00,'2026-11-30',1,'2026-05-05 14:12:07'),(10,'Augmentin 1g (boîte de 8)','MED-010',2,1,NULL,42,0,20,10,1200.00,1850.00,9.00,'2026-09-30',1,'2026-05-05 14:12:07'),(11,'Ciprofloxacine 500mg (boîte de 10)','MED-011',2,2,NULL,55,0,20,10,550.00,850.00,9.00,'2027-02-28',1,'2026-05-05 14:12:07'),(12,'Métronidazole 500mg (boîte de 14)','MED-012',2,4,NULL,90,0,20,15,420.00,650.00,9.00,'2027-07-31',1,'2026-05-05 14:12:07'),(13,'Cotrimoxazole 480mg (boîte de 20)','MED-013',2,3,NULL,150,0,20,20,280.00,450.00,9.00,'2027-10-31',1,'2026-05-05 14:12:07'),(14,'Doxycycline 100mg (boîte de 10)','MED-014',2,1,NULL,75,0,20,10,380.00,600.00,9.00,'2027-01-31',1,'2026-05-05 14:12:07'),(15,'Azithromycine 250mg (boîte de 6)','MED-015',2,2,NULL,38,0,20,10,950.00,1500.00,9.00,'2026-10-31',1,'2026-05-05 14:12:07'),(16,'Céfixime 200mg (boîte de 8)','MED-016',2,4,NULL,27,0,20,10,1100.00,1700.00,9.00,'2027-03-31',1,'2026-05-05 14:12:07'),(17,'Nitrofurantoïne 100mg (boîte de 14)','MED-017',2,5,NULL,20,0,20,8,700.00,1100.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(18,'Diclofénac 50mg (boîte de 20)','MED-018',3,1,NULL,119,0,20,15,280.00,450.00,9.00,'2027-08-31',1,'2026-05-05 14:12:07'),(19,'Kétoprofène 100mg (boîte de 15)','MED-019',3,3,NULL,65,0,20,10,420.00,650.00,9.00,'2027-05-31',1,'2026-05-05 14:12:07'),(20,'Cortisone 20mg (boîte de 20)','MED-020',3,2,NULL,19,0,20,8,600.00,950.00,9.00,'2027-04-30',1,'2026-05-05 14:12:07'),(21,'Prednisone 5mg (boîte de 30)','MED-021',3,4,NULL,41,0,20,10,350.00,550.00,9.00,'2027-12-31',1,'2026-05-05 14:12:07'),(22,'Coartem (Artéméther/Luméfantrine) 20/120mg (boîte de 24)','MED-022',4,1,NULL,200,0,20,30,500.00,800.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(23,'ASAQ (Artésunate/Amodiaquine) 100/270mg (boîte de 24)','MED-023',4,2,NULL,180,0,20,25,400.00,650.00,9.00,'2027-09-30',1,'2026-05-05 14:12:07'),(24,'Quinine 500mg (boîte de 20)','MED-024',4,3,NULL,94,0,20,15,350.00,550.00,9.00,'2027-03-31',1,'2026-05-05 14:12:07'),(25,'Malarone (Atovaquone/Proguanil) 250/100mg (boîte de 12)','MED-025',4,1,NULL,40,0,20,10,2200.00,3500.00,9.00,'2027-11-30',1,'2026-05-05 14:12:07'),(26,'Fansidar (Sulfadoxine/Pyriméthamine) 500/25mg (boîte de 8)','MED-026',4,4,NULL,110,0,20,15,300.00,500.00,9.00,'2027-01-31',1,'2026-05-05 14:12:07'),(27,'Artésunate 50mg (boîte de 24)','MED-027',4,2,NULL,65,0,20,10,450.00,700.00,9.00,'2026-12-31',1,'2026-05-05 14:12:07'),(28,'Nivaquine (Chloroquine) 100mg (boîte de 30)','MED-028',4,5,NULL,150,0,20,20,200.00,350.00,9.00,'2027-07-31',1,'2026-05-05 14:12:07'),(29,'Primaquine 15mg (boîte de 14)','MED-029',4,3,NULL,24,0,20,8,600.00,950.00,9.00,'2027-02-28',1,'2026-05-05 14:12:07'),(30,'Vitamine C 1000mg (boîte de 20)','MED-030',5,1,NULL,320,0,20,30,200.00,350.00,9.00,'2028-03-31',1,'2026-05-05 14:12:07'),(31,'Vitamine B Complex (boîte de 30)','MED-031',5,1,NULL,180,0,20,20,250.00,400.00,9.00,'2028-02-28',1,'2026-05-05 14:12:07'),(32,'Fer/Folate 200mg/400µg (boîte de 30)','MED-032',5,2,NULL,95,0,20,15,350.00,550.00,9.00,'2027-10-31',1,'2026-05-05 14:12:07'),(33,'Calcium 500mg + Vitamine D3 (boîte de 30)','MED-033',5,3,NULL,1140,0,20,15,450.00,700.00,9.00,'2028-04-30',1,'2026-05-05 14:12:07'),(34,'Multivitamines (boîte de 30)','MED-034',5,1,NULL,209,0,20,25,300.00,500.00,9.00,'2028-01-31',1,'2026-05-05 14:12:07'),(35,'Oro Vitamine C (tube de 20)','MED-035',5,4,NULL,160,0,20,20,180.00,300.00,9.00,'2027-12-31',1,'2026-05-05 14:12:07'),(36,'Folate 5mg (boîte de 50)','MED-036',5,5,NULL,120,0,20,15,120.00,200.00,9.00,'2027-11-30',1,'2026-05-05 14:12:07'),(37,'Amlodipine 5mg (boîte de 28)','MED-037',6,1,NULL,0,110,20,15,500.00,800.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(38,'Atorvastatine 20mg (boîte de 28)','MED-038',6,2,NULL,1075,0,20,10,800.00,1250.00,9.00,'2027-09-30',1,'2026-05-05 14:12:07'),(39,'Losartan 50mg (boîte de 28)','MED-039',6,3,NULL,60,0,20,10,650.00,1000.00,9.00,'2027-03-31',1,'2026-05-05 14:12:07'),(40,'Hydrochlorothiazide 25mg (boîte de 30)','MED-040',6,4,NULL,90,0,20,12,350.00,550.00,9.00,'2027-08-31',1,'2026-05-05 14:12:07'),(41,'Captopril 25mg (boîte de 30)','MED-041',6,1,NULL,80,0,20,10,450.00,700.00,9.00,'2027-05-31',1,'2026-05-05 14:12:07'),(42,'Aténolol 50mg (boîte de 28)','MED-042',6,5,NULL,100,0,20,15,380.00,600.00,9.00,'2027-12-31',1,'2026-05-05 14:12:07'),(43,'Furosémide 40mg (boîte de 20)','MED-043',6,2,NULL,40,0,20,8,300.00,500.00,9.00,'2027-07-31',1,'2026-05-05 14:12:07'),(44,'Metformine 500mg (boîte de 30)','MED-044',7,1,NULL,165,0,20,20,350.00,550.00,9.00,'2027-04-30',1,'2026-05-05 14:12:07'),(45,'Metformine 850mg (boîte de 30)','MED-045',7,1,NULL,120,0,20,15,480.00,750.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(46,'Glibenclamide 5mg (boîte de 30)','MED-046',7,3,NULL,80,0,20,10,280.00,450.00,9.00,'2027-09-30',1,'2026-05-05 14:12:07'),(47,'Glicazide 80mg (boîte de 30)','MED-047',7,2,NULL,55,0,20,10,550.00,850.00,9.00,'2027-02-28',1,'2026-05-05 14:12:07'),(48,'Insuline Humaine NPH (flacon 10ml)','MED-048',7,4,NULL,30,0,20,8,3500.00,5500.00,9.00,'2026-08-31',1,'2026-05-05 14:12:07'),(49,'Ventoline (Salbutamol) 100µg inhalateur','MED-049',8,1,NULL,42,0,20,10,1800.00,2800.00,9.00,'2027-11-30',1,'2026-05-05 14:12:07'),(50,'Codéine prométhazine sirop (flacon 125ml)','MED-050',8,2,NULL,70,0,20,15,350.00,550.00,9.00,'2027-05-31',1,'2026-05-05 14:12:07'),(51,'Ambroxol 30mg (boîte de 20)','MED-051',8,3,NULL,0,525,20,15,250.00,400.00,9.00,'2027-10-31',1,'2026-05-05 14:12:07'),(52,'Célestamine (Bétaméthasone) (boîte de 20)','MED-052',8,4,NULL,35,0,20,8,480.00,750.00,9.00,'2027-03-31',1,'2026-05-05 14:12:07'),(53,'Rhinadvil (Ibuprofène/Pseudoéphédrine) (boîte de 12)','MED-053',8,1,NULL,90,0,20,12,400.00,650.00,9.00,'2027-08-31',1,'2026-05-05 14:12:07'),(54,'Oméprazole 20mg (boîte de 14)','MED-054',9,1,NULL,165,0,20,20,320.00,500.00,9.00,'2027-07-31',1,'2026-05-05 14:12:07'),(55,'Smecta (Diosmectite) sachet x10','MED-055',9,2,NULL,200,0,20,25,280.00,450.00,9.00,'2027-09-30',1,'2026-05-05 14:12:07'),(56,'Imodium (Lopéramide) 2mg (boîte de 12)','MED-056',9,3,NULL,85,0,20,10,450.00,700.00,9.00,'2027-04-30',1,'2026-05-05 14:12:07'),(57,'Pantoprazole 40mg (boîte de 14)','MED-057',9,4,NULL,60,0,20,10,550.00,850.00,9.00,'2027-12-31',1,'2026-05-05 14:12:07'),(58,'Flagyl (Métronidazole) 250mg (boîte de 20)','MED-058',9,5,NULL,95,0,20,12,380.00,600.00,9.00,'2027-01-31',1,'2026-05-05 14:12:07'),(59,'Polysilane (flacon 250ml)','MED-059',9,1,NULL,1075,0,20,10,350.00,550.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)','MED-060',10,1,NULL,6,0,20,10,650.00,1000.00,9.00,'2027-10-31',1,'2026-05-05 14:12:07'),(61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)','MED-061',10,2,NULL,41,0,20,12,280.00,450.00,9.00,'2027-05-31',1,'2026-05-05 14:12:07'),(62,'Aerius (Desloratadine) 5mg (boîte de 10)','MED-062',10,3,NULL,0,1257,20,8,750.00,1200.00,9.00,'2027-03-31',1,'2026-05-05 14:12:07'),(63,'Béclométhasone spray nasal (flacon 200 doses)','MED-063',10,4,NULL,0,0,20,8,1800.00,2800.00,9.00,'2027-08-31',1,'2026-05-05 14:12:07'),(64,'Hydrocortisone crème 1% (tube 15g)','MED-064',11,1,NULL,70,0,20,10,250.00,400.00,9.00,'2027-09-30',1,'2026-05-05 14:12:07'),(65,'Bétadine (Povidone iodée) solution 100ml','MED-065',11,2,NULL,120,0,20,15,350.00,550.00,9.00,'2027-11-30',1,'2026-05-05 14:12:07'),(66,'Miconazole crème 2% (tube 15g)','MED-066',11,3,NULL,55,0,20,10,280.00,450.00,9.00,'2027-06-30',1,'2026-05-05 14:12:07'),(67,'Clobétasol crème 0,05% (tube 15g)','MED-067',11,4,NULL,30,0,20,8,650.00,1000.00,9.00,'2027-04-30',1,'2026-05-05 14:12:07'),(68,'Acide fusidique crème 2% (tube 15g)','MED-068',11,5,NULL,115,320,20,10,400.00,650.00,9.00,'2027-12-31',1,'2026-05-05 14:12:07'),(69,'Albendazole 400mg (boîte de 4)','MED-069',12,1,NULL,0,430,20,15,200.00,350.00,9.00,'2027-08-31',1,'2026-05-05 14:12:07'),(70,'Mébendazole 100mg (boîte de 6)','MED-070',12,2,NULL,110,0,20,15,180.00,300.00,9.00,'2027-05-31',1,'2026-05-05 14:12:07'),(71,'Ivermectine 3mg (boîte de 4)','MED-071',12,3,NULL,39,0,20,8,600.00,950.00,9.00,'2027-10-31',1,'2026-05-05 14:12:07'),(72,'Praziquantel 600mg (boîte de 4)','MED-072',12,4,NULL,25,0,20,8,550.00,900.00,9.00,'2027-02-28',1,'2026-05-05 14:12:07'),(73,'Niclosamide 500mg (boîte de 6)','MED-073',12,5,NULL,35,0,20,8,300.00,500.00,9.00,'2027-07-31',1,'2026-05-05 14:12:07');
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
  `mode_paiement` enum('esp├¿ces','carte','ch├¿que','mobile') NOT NULL DEFAULT 'esp├¿ces',
  `note` varchar(255) DEFAULT NULL,
  `date_reglement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `vente_id` (`vente_id`),
  CONSTRAINT `reglements_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `reglements_ibfk_2` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reglements`
--

LOCK TABLES `reglements` WRITE;
/*!40000 ALTER TABLE `reglements` DISABLE KEYS */;
INSERT INTO `reglements` VALUES (1,1,14,7155.00,'','','2026-05-15 19:56:08'),(2,1,16,1600.00,'','','2026-05-15 19:56:15'),(3,1,17,6000.00,'','','2026-05-15 19:56:18'),(4,1,NULL,26150.00,'','','2026-05-15 20:58:06'),(5,2,NULL,36934.00,'','','2026-05-15 21:55:56'),(6,1,NULL,9700.00,'','','2026-05-18 12:23:17');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remise_approbateurs`
--

LOCK TABLES `remise_approbateurs` WRITE;
/*!40000 ALTER TABLE `remise_approbateurs` DISABLE KEYS */;
INSERT INTO `remise_approbateurs` VALUES (1,1,1,1,'2026-07-18 17:48:32'),(5,5,1,1,'2026-07-18 17:50:47');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retour_vente_lignes`
--

LOCK TABLES `retour_vente_lignes` WRITE;
/*!40000 ALTER TABLE `retour_vente_lignes` DISABLE KEYS */;
INSERT INTO `retour_vente_lignes` VALUES (1,1,79,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',10,1000.00,0.00,10000.00,6500.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retours_vente`
--

LOCK TABLES `retours_vente` WRITE;
/*!40000 ALTER TABLE `retours_vente` DISABLE KEYS */;
INSERT INTO `retours_vente` VALUES (1,'RET-2026-0001',39,1,'2026-07-18',10000.00,0.00,10000.00,6500.00,'espèces','trop excessif','2026-07-18 16:43:39');
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
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,41),(1,44),(1,54),(1,55),(1,58),(1,59),(2,3),(2,16),(2,17),(2,26),(2,39),(3,1),(3,2),(3,16),(3,17),(3,28),(3,32),(3,33),(3,36),(3,39),(4,1),(4,3),(4,4),(4,5),(4,6),(4,7),(4,8),(4,9),(4,10),(4,11),(4,12),(4,13),(4,14),(4,15),(4,16),(4,17),(4,18),(4,19),(4,20),(4,21),(4,22),(4,24),(4,25),(4,26),(4,27),(4,29),(4,30),(4,31),(4,33),(4,34),(4,35),(4,36),(4,39),(4,41),(4,44),(5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,7),(5,8),(5,9),(5,10),(5,11),(5,12),(5,13),(5,14),(5,15),(5,16),(5,17),(5,18),(5,19),(5,20),(5,21),(5,22),(5,23),(5,24),(5,25),(5,26),(5,29),(5,30),(5,31),(5,32),(5,33),(5,34),(5,35),(5,36);
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrateur',1,'2026-05-04 16:56:45'),(2,'pharmacien','Pharmacien',1,'2026-05-04 16:56:45'),(3,'caissier','Caissier',1,'2026-05-04 16:56:45'),(4,'manager','Manager',1,'2026-05-09 21:40:38'),(5,'superviseur','Superviseur',1,'2026-05-25 18:42:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions_caisse`
--

LOCK TABLES `sessions_caisse` WRITE;
/*!40000 ALTER TABLE `sessions_caisse` DISABLE KEYS */;
INSERT INTO `sessions_caisse` VALUES (1,1,3,50000.00,'2026-05-05 19:59:26','2026-05-06 03:59:26',245000.00,242500.00,-2500.00,'fermée',NULL),(2,1,1,0.00,'2026-05-06 22:07:03','2026-05-07 12:03:57',1431.00,1400.00,-31.00,'fermée',NULL),(3,1,3,0.00,'2026-05-11 16:54:54','2026-05-11 17:02:33',10494.00,10500.00,6.00,'fermée',NULL),(4,2,4,20000.00,'2026-05-11 16:57:39','2026-05-11 16:58:52',22921.63,25000.00,2078.37,'fermée',NULL),(5,1,4,0.00,'2026-05-13 12:30:26','2026-05-13 18:42:56',0.00,0.00,0.00,'fermée',NULL),(6,1,4,0.00,'2026-05-13 18:43:06','2026-05-13 18:45:02',13952.26,14000.00,47.74,'fermée',NULL),(7,1,4,0.00,'2026-05-13 19:58:47','2026-05-15 15:14:40',-150000.00,0.00,150000.00,'fermée',NULL),(8,2,1,0.00,'2026-05-15 11:38:09','2026-05-15 15:14:47',0.00,0.00,0.00,'fermée',NULL),(9,1,1,0.00,'2026-05-15 15:14:54','2026-05-18 07:56:42',0.00,0.00,0.00,'fermée',NULL),(10,1,1,0.00,'2026-05-18 07:57:55','2026-05-25 17:40:30',0.00,0.00,0.00,'fermée',NULL),(11,1,1,0.00,'2026-05-25 18:09:23','2026-06-02 13:32:24',0.00,0.00,0.00,'fermée',NULL),(12,1,1,0.00,'2026-06-02 13:32:39','2026-06-02 19:50:58',65200.00,65200.00,0.00,'fermée',NULL),(13,1,1,0.00,'2026-06-02 19:51:04','2026-07-16 14:18:34',18600.00,18700.00,100.00,'fermée',NULL),(14,1,1,0.00,'2026-07-16 14:18:46','2026-07-16 17:02:17',1900.00,2000.00,100.00,'fermée',NULL),(15,1,4,0.00,'2026-07-16 17:04:29','2026-07-18 18:59:00',19000.00,19000.00,0.00,'fermée',NULL),(16,1,1,0.00,'2026-07-16 00:00:00','2026-07-18 18:59:16',-7800.00,0.00,7800.00,'fermée',NULL),(17,2,1,0.00,'2026-07-18 18:59:28','2026-07-20 18:20:15',6500.00,6500.00,0.00,'fermée',NULL),(18,1,4,0.00,'2026-07-18 19:02:35','2026-07-20 18:19:50',4512.50,4513.00,0.50,'fermée',NULL),(19,1,1,0.00,'2026-07-20 18:22:00','2026-07-21 14:37:37',0.00,0.00,0.00,'fermée',NULL),(20,2,4,0.00,'2026-07-20 18:22:44','2026-07-21 14:36:43',2840.00,2900.00,60.00,'fermée',NULL),(21,1,1,0.00,'2026-07-21 13:49:45','2026-07-21 14:37:26',0.00,0.00,0.00,'fermée',NULL),(22,1,1,0.00,'2026-07-21 14:39:10',NULL,NULL,NULL,NULL,'ouverte',3);
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transfert_lignes`
--

LOCK TABLES `transfert_lignes` WRITE;
/*!40000 ALTER TABLE `transfert_lignes` DISABLE KEYS */;
INSERT INTO `transfert_lignes` VALUES (1,1,68,'Acide fusidique crème 2% (tube 15g)',25),(2,2,68,'Acide fusidique crème 2% (tube 15g)',100),(3,3,68,'Acide fusidique crème 2% (tube 15g)',100),(4,3,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1000);
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
  CONSTRAINT `transferts_magasin_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transferts_magasin_ibfk_2` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transferts_magasin`
--

LOCK TABLES `transferts_magasin` WRITE;
/*!40000 ALTER TABLE `transferts_magasin` DISABLE KEYS */;
INSERT INTO `transferts_magasin` VALUES (1,'TRF-2026-0001',1,'','2026-07-17 15:49:38',NULL),(2,'TRF-2026-0002',1,'','2026-07-21 14:46:16',NULL),(3,'TRF-2026-0003',1,'→ Pharmacie B','2026-07-21 14:54:10',NULL);
/*!40000 ALTER TABLE `transferts_magasin` ENABLE KEYS */;
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
INSERT INTO `utilisateurs` VALUES (1,'MOHAMMADOU RACHID','SAIDOU','rachidradnex@gmail.com','admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',1,1,'2026-07-21 17:35:06','2026-05-04 16:56:45'),(2,'Ngassa','Marie-Claire','mc.ngassa@pharmacare.cm','ngassa','$2y$10$GNRHSZQM3IEXQv2jAdSpW.xk1.ILsLkCqWtaixs3HlxijTZ7H9QeK',2,1,'2026-05-13 18:45:14','2026-05-04 16:56:45'),(3,'Fotso','Emmanuel','e.fotso@pharmacare.cm','fotso','$2y$10$l5AU2MtXwMdyh71z9HDGhOdGJzDbMeqx/tzoZfioVmJQiXsMzw4Iy',3,1,'2026-05-11 16:54:44','2026-05-04 16:56:45'),(4,'Bouba','Yasmine','yas@test.com','yasmine','$2y$10$5FEUBIGDNRkBRUdFDyg9nemg6tGtLVDhuLAaM18Z1a/JROSe5U2ZK',3,1,'2026-07-21 14:36:29','2026-05-07 09:35:23'),(5,'AHMAD','SAID','said@test.com','said','$2y$10$DNLjKclzHYE/.sEUEnPZ7ODCPYQYv1x.2xd0r.6gKI59/x.DVb3du',5,1,NULL,'2026-07-18 17:30:09');
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
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vente_lignes`
--

LOCK TABLES `vente_lignes` WRITE;
/*!40000 ALTER TABLE `vente_lignes` DISABLE KEYS */;
INSERT INTO `vente_lignes` VALUES (1,1,1,'Paracétamol 500mg (boîte de 20)',3,300.00,19.25,900.00),(2,1,22,'Coartem (Artéméther/Luméfantrine) 20/120mg (boîte de 24)',1,800.00,19.25,800.00),(3,1,55,'Smecta (Diosmectite) sachet x10',2,450.00,19.25,900.00),(4,2,8,'Amoxicilline 500mg (boîte de 12)',2,750.00,19.25,1500.00),(5,2,38,'Atorvastatine 20mg (boîte de 28)',1,1250.00,19.25,1250.00),(6,2,49,'Ventoline (Salbutamol) 100µg inhalateur',1,2800.00,19.25,2800.00),(7,3,44,'Metformine 500mg (boîte de 30)',2,550.00,19.25,1100.00),(8,4,10,'Augmentin 1g (boîte de 8)',1,1850.00,19.25,1850.00),(9,4,30,'Vitamine C 1000mg (boîte de 20)',3,350.00,19.25,1050.00),(10,4,37,'Amlodipine 5mg (boîte de 28)',1,800.00,19.25,800.00),(11,5,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,19.25,1200.00),(12,5,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,19.25,2800.00),(13,6,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,19.25,1200.00),(14,6,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,19.25,2800.00),(15,7,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,19.25,1200.00),(16,8,9,'Amoxicilline 1g (boîte de 8)',1,1000.00,19.25,1000.00),(17,8,16,'Céfixime 200mg (boîte de 8)',1,1700.00,19.25,1700.00),(18,8,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,19.25,1200.00),(19,8,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,19.25,2800.00),(20,9,7,'Efferalgan 500mg (boîte de 16)',1,600.00,19.25,600.00),(21,9,34,'Multivitamines (boîte de 30)',1,500.00,19.25,500.00),(22,9,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',1,1000.00,19.25,1000.00),(23,10,24,'Quinine 500mg (boîte de 20)',1,550.00,19.25,550.00),(24,10,29,'Primaquine 15mg (boîte de 14)',1,950.00,19.25,950.00),(25,10,71,'Ivermectine 3mg (boîte de 4)',1,950.00,19.25,950.00),(26,11,3,'Doliprane 1000mg (boîte de 8)',1,650.00,19.25,650.00),(27,11,7,'Efferalgan 500mg (boîte de 16)',1,600.00,19.25,600.00),(28,11,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',1,1000.00,19.25,1000.00),(29,11,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,19.25,1200.00),(30,11,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,19.25,2800.00),(31,12,2,'Paracétamol 1g (boîte de 10)',2,400.00,19.25,800.00),(32,12,7,'Efferalgan 500mg (boîte de 16)',5,600.00,19.25,3000.00),(33,12,21,'Prednisone 5mg (boîte de 30)',3,550.00,19.25,1650.00),(34,13,3,'Doliprane 1000mg (boîte de 8)',1,650.00,19.25,650.00),(35,13,4,'Doliprane 500mg (boîte de 16)',1,500.00,19.25,500.00),(36,13,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',1,1000.00,19.25,1000.00),(37,13,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',1,450.00,19.25,450.00),(38,13,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,19.25,1200.00),(39,13,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,19.25,2800.00),(40,14,7,'Efferalgan 500mg (boîte de 16)',10,600.00,19.25,6000.00),(41,15,62,'Aerius (Desloratadine) 5mg (boîte de 10)',4,1200.00,19.25,4800.00),(42,15,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,19.25,2800.00),(43,16,3,'Doliprane 1000mg (boîte de 8)',1,650.00,0.00,650.00),(44,16,5,'Aspirine 500mg (boîte de 20)',2,250.00,0.00,500.00),(45,16,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',1,450.00,0.00,450.00),(46,17,62,'Aerius (Desloratadine) 5mg (boîte de 10)',5,1200.00,0.00,6000.00),(47,18,5,'Aspirine 500mg (boîte de 20)',5,250.00,0.00,1250.00),(48,19,62,'Aerius (Desloratadine) 5mg (boîte de 10)',7,1200.00,0.00,8400.00),(49,20,6,'Ibuprofène 400mg (boîte de 20)',10,550.00,0.00,5500.00),(50,20,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',10,450.00,0.00,4500.00),(51,21,62,'Aerius (Desloratadine) 5mg (boîte de 10)',10,1200.00,0.00,12000.00),(52,22,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',10,1000.00,0.00,10000.00),(53,23,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',10,450.00,0.00,4500.00),(54,24,1,'Paracétamol 500mg (boîte de 20)',4,300.00,0.00,1200.00),(55,24,3,'Doliprane 1000mg (boîte de 8)',3,650.00,0.00,1950.00),(56,24,5,'Aspirine 500mg (boîte de 20)',5,250.00,0.00,1250.00),(57,24,6,'Ibuprofène 400mg (boîte de 20)',5,550.00,0.00,2750.00),(58,25,5,'Aspirine 500mg (boîte de 20)',5,250.00,0.00,1250.00),(59,25,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',4,1000.00,0.00,4000.00),(60,25,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',1,450.00,0.00,450.00),(61,25,62,'Aerius (Desloratadine) 5mg (boîte de 10)',1,1200.00,0.00,1200.00),(62,25,63,'Béclométhasone spray nasal (flacon 200 doses)',1,2800.00,0.00,2800.00),(63,26,3,'Doliprane 1000mg (boîte de 8)',8,650.00,0.00,5200.00),(64,26,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',5,1000.00,0.00,5000.00),(65,27,20,'Cortisone 20mg (boîte de 20)',10,950.00,0.00,9500.00),(66,28,63,'Béclométhasone spray nasal (flacon 200 doses)',5,2800.00,0.00,14000.00),(69,31,63,'Béclométhasone spray nasal (flacon 200 doses)',13,2800.00,0.00,36400.00),(70,32,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',10,450.00,0.00,4500.00),(71,33,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',4,450.00,0.00,1800.00),(72,34,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',13,1000.00,0.00,13000.00),(73,35,5,'Aspirine 500mg (boîte de 20)',20,250.00,0.00,5000.00),(74,36,7,'Efferalgan 500mg (boîte de 16)',16,600.00,0.00,9600.00),(75,37,4,'Doliprane 500mg (boîte de 16)',8,500.00,0.00,4000.00),(76,38,3,'Doliprane 1000mg (boîte de 8)',1,650.00,0.00,650.00),(77,38,5,'Aspirine 500mg (boîte de 20)',1,250.00,0.00,250.00),(78,38,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',1,1000.00,0.00,1000.00),(79,39,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',19,1000.00,0.00,19000.00),(80,40,5,'Aspirine 500mg (boîte de 20)',4,250.00,0.00,1000.00),(81,40,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',1,450.00,0.00,450.00),(82,41,5,'Aspirine 500mg (boîte de 20)',3,250.00,0.00,750.00),(83,42,3,'Doliprane 1000mg (boîte de 8)',1,650.00,0.00,650.00),(84,42,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',2,1000.00,0.00,2000.00),(85,43,3,'Doliprane 1000mg (boîte de 8)',2,650.00,0.00,1300.00),(86,43,60,'Xyzall (Lévocétirizine) 5mg (boîte de 15)',2,1000.00,0.00,2000.00),(87,43,61,'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)',1,450.00,0.00,450.00),(88,44,68,'Acide fusidique crème 2% (tube 15g)',10,650.00,0.00,6500.00),(91,47,2,'Paracétamol 1g (boîte de 10)',1,400.00,0.00,400.00),(92,47,3,'Doliprane 1000mg (boîte de 8)',1,650.00,0.00,650.00),(93,47,6,'Ibuprofène 400mg (boîte de 20)',1,550.00,0.00,550.00),(94,47,18,'Diclofénac 50mg (boîte de 20)',1,450.00,0.00,450.00),(95,47,20,'Cortisone 20mg (boîte de 20)',1,950.00,0.00,950.00),(96,47,21,'Prednisone 5mg (boîte de 30)',1,550.00,0.00,550.00),(97,48,62,'Aerius (Desloratadine) 5mg (boîte de 10)',4,1200.00,0.00,4800.00),(98,49,62,'Aerius (Desloratadine) 5mg (boîte de 10)',3,1200.00,0.00,3600.00);
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
  `statut_paiement` enum('pay├®','en_attente','partiel') DEFAULT 'pay├®',
  `montant_recu` decimal(10,2) DEFAULT 0.00,
  `monnaie` decimal(10,2) DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `est_annulee` tinyint(1) DEFAULT 0,
  `remise_pct` decimal(5,2) DEFAULT 0.00,
  `remise_montant` decimal(10,2) DEFAULT 0.00,
  `autorise_par` int(11) DEFAULT NULL,
  `pharmacie_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `caissier_id` (`caissier_id`),
  KEY `client_id` (`client_id`),
  KEY `fk_ventes_autorise_par` (`autorise_par`),
  KEY `pharmacie_id` (`pharmacie_id`),
  CONSTRAINT `fk_ventes_autorise_par` FOREIGN KEY (`autorise_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`caissier_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventes_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `ventes_ibfk_3` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`),
  CONSTRAINT `ventes_ibfk_4` FOREIGN KEY (`pharmacie_id`) REFERENCES `pharmacies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventes`
--

LOCK TABLES `ventes` WRITE;
/*!40000 ALTER TABLE `ventes` DISABLE KEYS */;
INSERT INTO `ventes` VALUES (1,'VNT-2026-001','Mme Fouda','+237 699 11 22 33',NULL,3,2150.00,413.88,2563.88,'','pay├®',3000.00,436.12,NULL,'2026-05-05 11:12:07',0,0.00,0.00,NULL,NULL),(2,'VNT-2026-002','M. Nkoulou','+237 677 44 55 66',NULL,3,4250.00,818.13,5068.13,'carte','pay├®',5068.13,0.00,NULL,'2026-05-05 09:12:07',0,0.00,0.00,NULL,NULL),(3,'VNT-2026-003','','',NULL,2,1000.00,192.50,1192.50,'','pay├®',1200.00,7.50,NULL,'2026-05-05 06:12:07',0,0.00,0.00,NULL,NULL),(4,'VNT-2026-004','Mme Biyong','+237 655 77 88 99',NULL,2,3800.00,731.50,4531.50,'assurance','pay├®',0.00,0.00,NULL,'2026-05-05 13:12:07',0,0.00,0.00,NULL,NULL),(5,'VNT-2026-7196','','',NULL,1,4000.00,770.00,4770.00,'','pay├®',0.00,0.00,'','2026-05-05 17:37:51',0,0.00,0.00,NULL,NULL),(6,'VNT-2026-9323','','',NULL,1,4000.00,770.00,4770.00,'','pay├®',0.00,0.00,'','2026-05-06 18:13:59',0,0.00,0.00,NULL,NULL),(7,'VNT-2026-7389','','',NULL,1,1200.00,231.00,1431.00,'','pay├®',0.00,0.00,'','2026-05-06 22:07:10',0,0.00,0.00,NULL,NULL),(8,'VNT-2026-2742','','',NULL,3,6700.00,1289.75,7989.75,'','pay├®',10000.00,2010.25,'','2026-05-11 16:55:20',0,0.00,0.00,NULL,NULL),(9,'VNT-2026-7045','','',NULL,3,2100.00,404.25,2504.25,'','pay├®',3000.00,495.75,'','2026-05-11 16:56:15',0,0.00,0.00,NULL,NULL),(10,'VNT-2026-4525','','',NULL,4,2450.00,471.63,2921.63,'','pay├®',3000.00,78.38,'','2026-05-11 16:58:13',0,0.00,0.00,NULL,NULL),(11,'VNT-2026-2889','BelMamon','',NULL,4,6250.00,1203.13,7453.13,'','pay├®',8000.00,546.88,'','2026-05-13 18:43:39',0,0.00,0.00,NULL,NULL),(12,'VNT-2026-1909','Charles','',NULL,4,5450.00,1049.13,6499.13,'','pay├®',6500.00,0.88,'','2026-05-13 18:44:16',0,0.00,0.00,NULL,NULL),(13,'VNT-2026-9758','','',2,4,6600.00,1270.50,7870.50,'crédit','',0.00,0.00,'','2026-05-14 13:26:57',0,0.00,0.00,NULL,NULL),(14,'VNT-2026-1284','','',1,4,6000.00,1155.00,7155.00,'crédit','',0.00,0.00,'','2026-05-14 13:29:40',0,0.00,0.00,NULL,NULL),(15,'VNT-2026-8969','','',2,1,7600.00,1463.00,9063.00,'crédit','',0.00,0.00,'','2026-05-15 12:07:18',0,0.00,0.00,NULL,NULL),(16,'VNT-2026-9234','','',1,1,1600.00,0.00,1600.00,'crédit','',0.00,0.00,'','2026-05-15 14:01:16',0,0.00,0.00,NULL,NULL),(17,'VNT-2026-7976','','',1,1,6000.00,0.00,6000.00,'crédit','',0.00,0.00,'','2026-05-15 15:15:59',0,0.00,0.00,NULL,NULL),(18,'VNT-2026-4231','','',1,1,1250.00,0.00,1250.00,'crédit','',0.00,0.00,'','2026-05-15 15:16:11',0,0.00,0.00,NULL,NULL),(19,'VNT-2026-9904','SEMRY','670198963',1,1,8400.00,0.00,8400.00,'crédit','',0.00,0.00,'','2026-05-15 15:24:08',0,0.00,0.00,NULL,NULL),(20,'VNT-2026-5697','VIVA LOGONE','653250114',2,1,10000.00,0.00,10000.00,'crédit','',0.00,0.00,'','2026-05-15 15:24:29',0,0.00,0.00,NULL,NULL),(21,'VNT-2026-1213','SEMRY','670198963',1,1,12000.00,0.00,12000.00,'crédit','',0.00,0.00,'','2026-05-15 15:28:16',0,0.00,0.00,NULL,NULL),(22,'VNT-2026-4455','VIVA LOGONE','653250114',2,1,10000.00,0.00,10000.00,'crédit','',0.00,0.00,'','2026-05-15 15:28:29',0,0.00,0.00,NULL,NULL),(23,'VNT-2026-4968','SEMRY','670198963',1,1,4500.00,0.00,4500.00,'crédit','',0.00,0.00,'','2026-05-15 15:59:39',0,0.00,0.00,NULL,NULL),(24,'VNT-2026-6775','Rachid','693353299',3,1,7150.00,0.00,7150.00,'crédit','en_attente',0.00,0.00,'','2026-05-16 12:15:24',0,0.00,0.00,NULL,NULL),(25,'VNT-2026-9905','SEMRY','670198963',1,1,9700.00,0.00,9700.00,'crédit','',0.00,0.00,'','2026-05-18 12:22:05',0,0.00,0.00,NULL,NULL),(26,'VNT-2026-9906','SEMRY','670198963',1,1,10200.00,0.00,10200.00,'crédit','en_attente',0.00,0.00,'','2026-05-18 12:23:34',0,0.00,0.00,NULL,NULL),(27,'VNT-2026-9907','','',NULL,1,9500.00,0.00,9500.00,'espèces','',10000.00,500.00,'','2026-06-02 13:35:37',0,0.00,0.00,NULL,NULL),(28,'VNT-2026-9908','VIVA LOGONE','653250114',2,1,14000.00,0.00,14000.00,'crédit','en_attente',0.00,0.00,'','2026-06-02 15:14:56',0,0.00,0.00,NULL,NULL),(31,'VNT-2026-9909','','',NULL,1,36400.00,0.00,36400.00,'espèces','',40000.00,3600.00,'','2026-06-02 18:56:41',0,0.00,0.00,NULL,NULL),(32,'VNT-2026-9910','','',NULL,1,4500.00,0.00,4500.00,'espèces','',5000.00,500.00,'','2026-06-02 19:33:55',0,0.00,0.00,NULL,NULL),(33,'VNT-2026-9911','','',NULL,1,1800.00,0.00,1800.00,'espèces','',2000.00,200.00,'','2026-06-02 19:37:12',0,0.00,0.00,NULL,NULL),(34,'VNT-2026-9912','','',NULL,1,13000.00,0.00,13000.00,'espèces','',130000.00,117000.00,'','2026-06-02 19:44:09',0,0.00,0.00,NULL,NULL),(35,'VNT-2026-9913','ibrahima','',NULL,1,5000.00,0.00,5000.00,'espèces','',5000.00,0.00,'','2026-06-02 22:31:26',0,0.00,0.00,NULL,NULL),(36,'VNT-2026-9914','saidou','',NULL,1,9600.00,0.00,9600.00,'espèces','',10000.00,400.00,'','2026-06-02 22:35:29',0,0.00,0.00,NULL,NULL),(37,'VNT-2026-9915','rachid','',NULL,1,4000.00,0.00,4000.00,'espèces','',4000.00,0.00,'','2026-06-02 22:38:53',0,0.00,0.00,NULL,NULL),(38,'VNT-2026-9916','bello','',NULL,1,1900.00,0.00,1900.00,'espèces','',2000.00,100.00,'','2026-07-16 14:26:51',0,0.00,0.00,NULL,NULL),(39,'VNT-2026-9917','Yasser','',NULL,4,19000.00,0.00,19000.00,'espèces','',20000.00,1000.00,'','2026-07-16 17:06:32',0,0.00,0.00,NULL,NULL),(40,'VNT-2026-9918','hassan','',NULL,1,1450.00,0.00,1450.00,'espèces','',1500.00,50.00,'','2026-07-18 13:24:01',0,0.00,0.00,NULL,NULL),(41,'VNT-2026-9919','hassan oumar','',NULL,1,750.00,0.00,750.00,'espèces','',1000.00,250.00,'','2026-07-18 13:31:11',0,0.00,0.00,NULL,NULL),(42,'VNT-2026-9920','ADJI','',NULL,4,2650.00,0.00,2650.00,'espèces','',1500.00,175.00,'','2026-07-18 19:04:02',0,50.00,1325.00,1,NULL),(43,'VNT-2026-9921','RABIAH','',NULL,4,3750.00,0.00,3750.00,'espèces','',3200.00,12.50,'','2026-07-18 19:29:36',0,15.00,562.50,1,NULL),(44,'VNT-2026-9922','OUMAR','',NULL,1,6500.00,0.00,6500.00,'espèces','',7000.00,500.00,'','2026-07-20 18:14:32',0,0.00,0.00,NULL,NULL),(47,'VNT-2026-9923','OLIVER','',NULL,4,3550.00,0.00,2840.00,'espèces','',2900.00,60.00,'','2026-07-21 14:04:19',0,20.00,710.00,1,NULL),(48,'VNT-2026-9924','ILHAM','',NULL,1,4800.00,0.00,4800.00,'espèces','',5000.00,200.00,'','2026-07-21 14:54:45',0,0.00,0.00,NULL,3),(49,'VNT-2026-9925','SABRINA','',NULL,1,3600.00,0.00,3600.00,'espèces','',3600.00,0.00,'','2026-07-21 15:03:19',0,0.00,0.00,NULL,3);
/*!40000 ALTER TABLE `ventes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-21 18:27:55
