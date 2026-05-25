-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: rentflow
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
-- Current Database: `rentflow`
--

/*!40000 DROP DATABASE IF EXISTS `rentflow`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `rentflow` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `rentflow`;

--
-- Table structure for table `agencies`
--

DROP TABLE IF EXISTS `agencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agencies`
--

LOCK TABLES `agencies` WRITE;
/*!40000 ALTER TABLE `agencies` DISABLE KEYS */;
INSERT INTO `agencies` VALUES (1,'Abong-Mbang',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(2,'Bertoua',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(3,'Douala',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(4,'Garoua',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(5,'Guidiguis',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(6,'Maroua',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(7,'Mokolo',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(8,'Ngaoundéré',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(9,'Ngong',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(10,'Toubouro',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(11,'Yagoua',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(12,'Yaoundé',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(13,'Kalfou',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(14,'Mbe',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38');
/*!40000 ALTER TABLE `agencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `batches`
--

DROP TABLE IF EXISTS `batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `landlord_id` int(11) NOT NULL,
  `agency_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `monthly_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batches_landlord` (`landlord_id`),
  KEY `idx_batches_agency` (`agency_id`),
  CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `landlords` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batches_ibfk_2` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `batches`
--

LOCK TABLES `batches` WRITE;
/*!40000 ALTER TABLE `batches` DISABLE KEYS */;
INSERT INTO `batches` VALUES (1,1,1,'622DEX01',50000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(2,2,2,'622DEX03',200000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(3,3,2,'622DEX04',75000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(4,4,3,'622DEX01',728000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(5,5,4,'622DEX04',400000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(6,6,4,'622DEX04',150000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(7,7,5,'622DEX07',50000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(8,8,6,'622DEX11',300000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(9,9,7,'622DEX21',100000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(10,10,8,'622DEX13',600000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(11,11,8,'622DEX13',600000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(12,12,9,'622DEX17',200000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(13,13,10,'622DEX17',50000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(14,14,11,'622DEX19',200000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(15,15,12,'622DEX16',700000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(16,16,12,'622DEX16',300000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(17,17,12,'622DEX16',400000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(18,18,12,'622DEX16',300000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(19,19,12,'622DEX16',83333.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(20,20,13,'622DEX16',700000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(21,21,14,'622DEX23',25000.00,'2026-05-05 16:34:38','2026-05-05 16:34:38');
/*!40000 ALTER TABLE `batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landlords`
--

DROP TABLE IF EXISTS `landlords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `landlords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact` text NOT NULL,
  `contract_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landlords`
--

LOCK TABLES `landlords` WRITE;
/*!40000 ALTER TABLE `landlords` DISABLE KEYS */;
INSERT INTO `landlords` VALUES (1,'Oval Charmant','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(2,'Famille Bazocke','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(3,'Epoke François','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(4,'Famille Bongo','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(5,'Famille Ahmadou','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(6,'Idrissou Abbo','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(7,'Aminatou Abdou','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(8,'Hamidou Issoufa','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(9,'Abbo Kella','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(10,'Aboubakar Alifa Lot 1','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(11,'Aboubakar Alifa Lot 2','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(12,'Garrage','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(13,'Ahmadou','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(14,'Mohamadou Djouga Djoddou','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(15,'Kemoune Gervais 1','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(16,'Kemoune Gervais 2','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(17,'Société Reference 1','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(18,'Société Reference 2','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(19,'Société Reference 3','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(20,'Sarl Express','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38'),(21,'Point de vente','',NULL,'2026-05-05 16:34:38','2026-05-05 16:34:38');
/*!40000 ALTER TABLE `landlords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `status` enum('EARLY','ON_TIME','LATE','PENDING') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_payments_status` (`status`),
  KEY `idx_payments_due_date` (`due_date`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,14,200000.00,'2026-08-30','2026-05-05','EARLY','2026-05-05 18:48:19','2026-05-05 18:48:19'),(2,4,120000.00,'2026-07-31','2026-05-10','EARLY','2026-05-05 19:53:26','2026-05-05 19:53:26'),(3,1,50000.00,'2026-05-31','2026-05-06','EARLY','2026-05-06 08:18:23','2026-05-06 08:18:23');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission_role` (`permission_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `permission_role_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_role`
--

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` VALUES (130,7,2,'2026-05-05 20:21:33'),(131,9,2,'2026-05-05 20:21:33'),(132,8,2,'2026-05-05 20:21:33'),(133,6,2,'2026-05-05 20:21:33'),(134,11,2,'2026-05-05 20:21:33'),(135,13,2,'2026-05-05 20:21:33'),(136,12,2,'2026-05-05 20:21:33'),(137,10,2,'2026-05-05 20:21:33'),(138,1,2,'2026-05-05 20:21:33'),(139,3,2,'2026-05-05 20:21:33'),(140,5,2,'2026-05-05 20:21:33'),(141,4,2,'2026-05-05 20:21:33'),(142,2,2,'2026-05-05 20:21:33'),(143,15,2,'2026-05-05 20:21:33'),(144,17,2,'2026-05-05 20:21:33'),(145,16,2,'2026-05-05 20:21:33'),(146,18,2,'2026-05-05 20:21:33'),(147,14,2,'2026-05-05 20:21:33'),(148,22,2,'2026-05-05 20:21:33'),(149,24,2,'2026-05-05 20:21:33'),(150,23,2,'2026-05-05 20:21:33'),(151,25,2,'2026-05-05 20:21:33'),(152,21,2,'2026-05-05 20:21:33'),(153,7,3,'2026-05-05 20:21:33'),(154,8,3,'2026-05-05 20:21:33'),(155,6,3,'2026-05-05 20:21:33'),(156,11,3,'2026-05-05 20:21:33'),(157,12,3,'2026-05-05 20:21:33'),(158,10,3,'2026-05-05 20:21:33'),(159,1,3,'2026-05-05 20:21:33'),(160,3,3,'2026-05-05 20:21:33'),(161,4,3,'2026-05-05 20:21:33'),(162,2,3,'2026-05-05 20:21:33'),(163,15,3,'2026-05-05 20:21:33'),(164,16,3,'2026-05-05 20:21:33'),(165,14,3,'2026-05-05 20:21:33'),(166,6,4,'2026-05-05 20:21:33'),(167,10,4,'2026-05-05 20:21:33'),(168,1,4,'2026-05-05 20:21:33'),(169,2,4,'2026-05-05 20:21:33'),(170,15,4,'2026-05-05 20:21:33'),(171,14,4,'2026-05-05 20:21:33'),(172,6,5,'2026-05-05 20:21:33'),(173,10,5,'2026-05-05 20:21:33'),(174,1,5,'2026-05-05 20:21:33'),(175,2,5,'2026-05-05 20:21:33'),(176,14,5,'2026-05-05 20:21:33'),(177,19,5,'2026-05-05 20:21:33'),(178,21,5,'2026-05-05 20:21:33'),(179,7,1,'2026-05-06 09:30:34'),(180,9,1,'2026-05-06 09:30:34'),(181,8,1,'2026-05-06 09:30:34'),(182,6,1,'2026-05-06 09:30:34'),(183,11,1,'2026-05-06 09:30:34'),(184,13,1,'2026-05-06 09:30:34'),(185,12,1,'2026-05-06 09:30:34'),(186,10,1,'2026-05-06 09:30:34'),(187,1,1,'2026-05-06 09:30:34'),(188,3,1,'2026-05-06 09:30:34'),(189,5,1,'2026-05-06 09:30:34'),(190,4,1,'2026-05-06 09:30:34'),(191,2,1,'2026-05-06 09:30:34'),(192,15,1,'2026-05-06 09:30:34'),(193,17,1,'2026-05-06 09:30:34'),(194,16,1,'2026-05-06 09:30:34'),(195,18,1,'2026-05-06 09:30:34'),(196,14,1,'2026-05-06 09:30:34'),(197,20,1,'2026-05-06 09:30:34'),(198,19,1,'2026-05-06 09:30:34'),(199,22,1,'2026-05-06 09:30:34'),(200,24,1,'2026-05-06 09:30:34'),(201,23,1,'2026-05-06 09:30:34'),(202,25,1,'2026-05-06 09:30:34'),(203,21,1,'2026-05-06 09:30:34');
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard_view','Voir le dashboard','Accès au tableau de bord','dashboard','2026-05-04 16:12:03','2026-05-04 16:12:03'),(2,'landlords_view','Voir les bailleurs','Consulter la liste des bailleurs','landlords','2026-05-04 16:12:03','2026-05-04 16:12:03'),(3,'landlords_create','Créer un bailleur','Ajouter un nouveau bailleur','landlords','2026-05-04 16:12:03','2026-05-05 16:24:15'),(4,'landlords_edit','Modifier un bailleur','Modifier les informations d\'un bailleur','landlords','2026-05-04 16:12:03','2026-05-04 16:12:03'),(5,'landlords_delete','Supprimer un bailleur','Supprimer un bailleur','landlords','2026-05-04 16:12:03','2026-05-04 16:12:03'),(6,'agencies_view','Voir les agences','Consulter la liste des agences','agencies','2026-05-04 16:12:03','2026-05-04 16:12:03'),(7,'agencies_create','Créer une agence','Ajouter une nouvelle agence','agencies','2026-05-04 16:12:03','2026-05-05 16:24:15'),(8,'agencies_edit','Modifier une agence','Modifier les informations d\'une agence','agencies','2026-05-04 16:12:03','2026-05-04 16:12:03'),(9,'agencies_delete','Supprimer une agence','Supprimer une agence','agencies','2026-05-04 16:12:03','2026-05-04 16:12:03'),(10,'batches_view','Voir les lots','Consulter la liste des lots','batches','2026-05-04 16:12:03','2026-05-04 16:12:03'),(11,'batches_create','Créer un lot','Ajouter un nouveau lot','batches','2026-05-04 16:12:03','2026-05-05 16:24:15'),(12,'batches_edit','Modifier un lot','Modifier les informations d\'un lot','batches','2026-05-04 16:12:03','2026-05-04 16:12:03'),(13,'batches_delete','Supprimer un lot','Supprimer un lot','batches','2026-05-04 16:12:03','2026-05-04 16:12:03'),(14,'payments_view','Voir les paiements','Consulter la liste des paiements','payments','2026-05-04 16:12:03','2026-05-04 16:12:03'),(15,'payments_create','Créer un paiement','Ajouter un nouveau paiement','payments','2026-05-04 16:12:03','2026-05-05 16:24:15'),(16,'payments_edit','Modifier un paiement','Modifier un paiement','payments','2026-05-04 16:12:03','2026-05-04 16:12:03'),(17,'payments_delete','Supprimer un paiement','Supprimer un paiement','payments','2026-05-04 16:12:03','2026-05-04 16:12:03'),(18,'payments_validate','Valider un paiement','Valider ou rejeter un paiement','payments','2026-05-04 16:12:03','2026-05-05 16:24:15'),(19,'settings_view','Voir les paramètres','Accéder aux paramètres','settings','2026-05-04 16:12:03','2026-05-05 16:24:15'),(20,'settings_edit','Modifier les paramètres','Modifier la configuration','settings','2026-05-04 16:12:03','2026-05-05 16:24:15'),(21,'users_view','Voir les utilisateurs','Consulter la liste des utilisateurs','users','2026-05-04 16:12:03','2026-05-05 16:24:15'),(22,'users_create','Créer un utilisateur','Ajouter un nouvel utilisateur','users','2026-05-04 16:12:03','2026-05-05 16:24:15'),(23,'users_edit','Modifier un utilisateur','Modifier un utilisateur','users','2026-05-04 16:12:03','2026-05-05 16:24:15'),(24,'users_delete','Supprimer un utilisateur','Supprimer un utilisateur','users','2026-05-04 16:12:03','2026-05-05 16:24:15'),(25,'users_roles','Gérer les rôles','Affecter des rôles aux utilisateurs','users','2026-05-04 16:12:03','2026-05-05 16:24:36');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reminders`
--

DROP TABLE IF EXISTS `reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reminders`
--

LOCK TABLES `reminders` WRITE;
/*!40000 ALTER TABLE `reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_user` (`role_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `role_user_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (3,3,2,'2026-05-05 20:05:27'),(4,1,1,'2026-05-06 09:42:58');
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Super Administrateur','Accès complet à toutes les fonctionnalités','2026-05-04 16:12:03','2026-05-04 16:12:03'),(2,'admin','Administrateur','Gestion complète sauf paramètres système','2026-05-04 16:12:03','2026-05-04 16:12:03'),(3,'manager','Gestionnaire','Gestion des paiements, lots et bailleurs','2026-05-04 16:12:03','2026-05-04 16:12:03'),(4,'agent','Agent','Consultation et saisie des paiements','2026-05-04 16:12:03','2026-05-04 16:12:03'),(5,'viewer','Observateur','Lecture seule','2026-05-04 16:12:03','2026-05-04 16:12:03');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'app_name','-RentFlow - Gestion Immobilière','2026-05-04 12:30:39','2026-05-06 10:27:46'),(2,'currency','XAF','2026-05-04 12:30:39','2026-05-06 10:27:46'),(3,'timezone','Africa/Douala','2026-05-04 12:30:39','2026-05-06 10:27:46'),(4,'language','fr','2026-05-04 12:30:39','2026-05-06 10:27:46'),(5,'notifications_enabled','1','2026-05-04 12:30:39','2026-05-06 10:27:46'),(6,'email_notifications','1','2026-05-04 12:30:39','2026-05-06 10:27:46'),(7,'items_per_page','25','2026-05-04 12:30:39','2026-05-06 10:27:46'),(8,'date_format','d/m/Y','2026-05-04 12:30:39','2026-05-06 10:27:46'),(9,'logo','assets/images/logo_1778060632.jpeg','2026-05-06 09:43:52','2026-05-06 09:43:52');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrateur','admin@rentflow.com','$2y$10$/uLY92fDRQAsezlDcCiebepxpy0FPnH.sRtOh4LOt4a3dnbi96u52','admin','2026-05-04 11:10:07','2026-05-04 11:40:38'),(2,'Abdoul Azizi Sali Bello','azizi@test.com','$2y$10$3XoqCLbJfRcRXztfXUpbIOBA9X4W0e..OpR7vaHVmeJf2bd.IEtOi','user','2026-05-04 16:24:06','2026-05-04 16:24:06');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-06 13:14:08
