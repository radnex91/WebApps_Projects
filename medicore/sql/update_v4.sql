-- ============================================================
--  MediCore ERP — Migration v4 : username comme identifiant de connexion
--  Remplace email par username pour l'authentification
--  L'email devient un champ optionnel (contact uniquement)
-- ============================================================

-- Étape 1 : Ajouter la colonne username
ALTER TABLE `utilisateurs`
  ADD COLUMN `username` VARCHAR(50) NOT NULL DEFAULT '' AFTER `id`;

-- Étape 2 : Peupler username à partir du préfixe email
UPDATE `utilisateurs`
SET `username` = SUBSTRING_INDEX(`email`, '@', 1)
WHERE `username` = '';

-- Étape 3 : Rendre email optionnel (plus UNIQUE)
ALTER TABLE `utilisateurs`
  MODIFY COLUMN `email` VARCHAR(150) DEFAULT NULL,
  DROP INDEX `email`;

-- Étape 4 : Ajouter UNIQUE sur username
ALTER TABLE `utilisateurs`
  ADD UNIQUE INDEX `uq_username` (`username`);
