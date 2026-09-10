-- ============================================================
--  MediCore ERP — Migration v7
--  Résultat de consultation : liaison notes_cliniques ↔ arrivees_patients.
--  Permet de rattacher un compte-rendu d'entretien médecin à une arrivée
--  (pour le gating strict de « Terminer » et le pré-remplissage du modal).
--
--  Idempotent : chaque ALTER est gardé par un contrôle INFORMATION_SCHEMA.
--  Re-exécutable sans erreur.
-- ============================================================

-- ── 1.1  notes_cliniques : colonne arrivee_id (liaison résultat de consultation) ──
SET @v := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'notes_cliniques'
             AND column_name = 'arrivee_id');
SET @s := IF(@v = 0,
  'ALTER TABLE `notes_cliniques` ADD COLUMN `arrivee_id` INT(11) DEFAULT NULL AFTER `hospitalisation_id`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 1.2  Index sur arrivee_id (lookup du gating Terminer + chargement résultats) ──
SET @v := (SELECT COUNT(*) FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'notes_cliniques'
             AND index_name = 'idx_notes_arrivee');
SET @s := IF(@v = 0,
  'ALTER TABLE `notes_cliniques` ADD KEY `idx_notes_arrivee` (`arrivee_id`)',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 1.3  Clé étrangère arrivee_id → arrivees_patients (ON DELETE SET NULL) ──
SET @v := (SELECT COUNT(*) FROM information_schema.referential_constraints
           WHERE constraint_schema = DATABASE() AND table_name = 'notes_cliniques'
             AND constraint_name = 'fk_notes_arrivee');
SET @s := IF(@v = 0,
  'ALTER TABLE `notes_cliniques` ADD CONSTRAINT `fk_notes_arrivee` FOREIGN KEY (`arrivee_id`) REFERENCES `arrivees_patients` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;