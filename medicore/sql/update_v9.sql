-- ============================================================
--  MediCore ERP — Migration v9
--  Comptabilité SYSCOHADA + traçabilité financière.
--  Idempotent : chaque création/ALTER/INSERT est gardé.
-- ============================================================

-- ── 1. compta_exercices ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_exercices');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_exercices` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `annee` INT(11) NOT NULL,
     `date_debut` DATE NOT NULL,
     `date_fin` DATE NOT NULL,
     `statut` ENUM(''ouvert'',''cloture'') NOT NULL DEFAULT ''ouvert'',
     `cloture_par` INT(11) DEFAULT NULL,
     `date_cloture` DATETIME DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_exercice_annee` (`annee`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. compta_comptes ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_comptes');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_comptes` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `numero` VARCHAR(10) NOT NULL,
     `libelle` VARCHAR(150) NOT NULL,
     `classe` TINYINT(2) NOT NULL,
     `type` ENUM(''actif'',''passif'',''charge'',''produit'',''tresorerie'',''tiers'') NOT NULL,
     `lettrable` TINYINT(1) DEFAULT 0,
     `parent_id` INT(11) DEFAULT NULL,
     `statut` ENUM(''actif'',''inactif'') NOT NULL DEFAULT ''actif'',
     `position` INT(11) DEFAULT 0,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_compte_numero` (`numero`),
     KEY `idx_compte_classe` (`classe`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. compta_journaux ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_journaux');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_journaux` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `code` VARCHAR(8) NOT NULL,
     `libelle` VARCHAR(100) NOT NULL,
     `type` ENUM(''ventes'',''achats'',''banque'',''caisse'',''od'') NOT NULL,
     `compte_contrepartie_id` INT(11) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_journal_code` (`code`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. compta_ecritures ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_ecritures');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_ecritures` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `numero` VARCHAR(30) NOT NULL,
     `date_ecriture` DATE NOT NULL,
     `journal_id` INT(11) NOT NULL,
     `exercice_id` INT(11) NOT NULL,
     `libelle` VARCHAR(255) NOT NULL,
     `source_table` VARCHAR(50) DEFAULT NULL,
     `source_id` INT(11) DEFAULT NULL,
     `utilisateur_id` INT(11) DEFAULT NULL,
     `statut` ENUM(''brouillon'',''validee'',''annulee'') NOT NULL DEFAULT ''validee'',
     `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_ecriture_numero` (`numero`),
     KEY `idx_ecriture_source` (`source_table`,`source_id`),
     KEY `idx_ecriture_journal` (`exercice_id`,`journal_id`),
     KEY `idx_ecriture_date` (`date_ecriture`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. compta_ecriture_lignes ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_ecriture_lignes');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_ecriture_lignes` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `ecriture_id` INT(11) NOT NULL,
     `compte_id` INT(11) NOT NULL,
     `debit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
     `credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
     `tiers_libelle` VARCHAR(150) DEFAULT NULL,
     PRIMARY KEY (`id`),
     KEY `idx_ligne_ecriture` (`ecriture_id`),
     KEY `idx_ligne_compte` (`compte_id`),
     CONSTRAINT `fk_ligne_ecriture` FOREIGN KEY (`ecriture_id`) REFERENCES `compta_ecritures` (`id`) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 6. Seed : plan comptable SYSCOHADA réduit ──
INSERT IGNORE INTO `compta_comptes` (`numero`,`libelle`,`classe`,`type`,`position`) VALUES
('101','Capital',1,'passif',10),
('120','Résultat de l''exercice',1,'passif',20),
('130','Subventions d''équipement',1,'passif',30),
('200','Immobilisations incorporelles',2,'actif',100),
('210','Terrains',2,'actif',110),
('220','Constructions',2,'actif',120),
('240','Matériel et outillage',2,'actif',130),
('280','Amortissements',2,'passif',140),
('300','Stocks de marchandises',3,'actif',200),
('310','Stocks de matières premières',3,'actif',210),
('360','Stocks de médicaments',3,'actif',220),
('401','Fournisseurs',4,'tiers',300),
('411','Clients',4,'tiers',310),
('420','Personnel',4,'tiers',320),
('426','Assureurs',4,'tiers',330),
('440','État',4,'tiers',340),
('470','Comptes d''attente',4,'tiers',350),
('521','Banque',5,'tresorerie',400),
('571','Caisse',5,'tresorerie',410),
('580','Virements internes',5,'tresorerie',420),
('601','Achats de marchandises',6,'charge',500),
('605','Autres achats',6,'charge',510),
('610','Transports',6,'charge',520),
('630','Charges financières',6,'charge',530),
('658','Autres charges',6,'charge',540),
('701','Ventes de marchandises',7,'produit',600),
('706','Prestations de services',7,'produit',610),
('707','Ventes et produits accessoires',7,'produit',620),
('758','Autres produits',7,'produit',630);

-- ── 7. Seed : journaux ──
INSERT IGNORE INTO `compta_journaux` (`code`,`libelle`,`type`,`compte_contrepartie_id`) VALUES
('VTE','Journal des ventes','ventes',(SELECT id FROM compta_comptes WHERE numero='411')),
('ACH','Journal des achats','achats',(SELECT id FROM compta_comptes WHERE numero='401')),
('BQ','Journal de banque','banque',(SELECT id FROM compta_comptes WHERE numero='521')),
('CA','Journal de caisse','caisse',(SELECT id FROM compta_comptes WHERE numero='571')),
('OD','Opérations diverses','od',NULL);

-- ── 8. Seed : exercices 2024-2026 (ouverts pour backfill) ──
INSERT IGNORE INTO `compta_exercices` (`annee`,`date_debut`,`date_fin`,`statut`) VALUES
(2024,'2024-01-01','2024-12-31','ouvert'),
(2025,'2025-01-01','2025-12-31','ouvert'),
(2026,'2026-01-01','2026-12-31','ouvert');

-- ── 9. RBAC : page + actions (idempotent via NOT EXISTS) ──
INSERT IGNORE INTO `role_page_access` (`role`,`page`,`allowed`,`updated_at`)
SELECT 'admin','comptabilite',1,NOW() WHERE NOT EXISTS (SELECT 1 FROM role_page_access WHERE role='admin' AND page='comptabilite');
INSERT IGNORE INTO `role_page_access` (`role`,`page`,`allowed`,`updated_at`)
SELECT 'comptable','comptabilite',1,NOW() WHERE NOT EXISTS (SELECT 1 FROM role_page_access WHERE role='comptable' AND page='comptabilite');

INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`,`updated_at`)
SELECT 'admin',a.act,1,NOW()
FROM (SELECT 'compta.consulter' AS act UNION SELECT 'compta.saisir' UNION SELECT 'compta.cloturer' UNION SELECT 'compta.param_comptes') a
WHERE NOT EXISTS (SELECT 1 FROM role_action_access ra WHERE ra.role='admin' AND ra.action=a.act);

INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`,`updated_at`)
SELECT 'comptable',a.act,1,NOW()
FROM (SELECT 'compta.consulter' AS act UNION SELECT 'compta.saisir') a
WHERE NOT EXISTS (SELECT 1 FROM role_action_access ra WHERE ra.role='comptable' AND ra.action=a.act);

INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`,`updated_at`)
SELECT 'comptable',a.act,0,NOW()
FROM (SELECT 'compta.cloturer' AS act UNION SELECT 'compta.param_comptes') a
WHERE NOT EXISTS (SELECT 1 FROM role_action_access ra WHERE ra.role='comptable' AND ra.action=a.act);