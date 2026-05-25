-- Migration : ajouter type_operation_id à demandes_engagement
-- À exécuter dans phpMyAdmin ou via : mysql -u root brenfinance < sql/migration_type_operation_engagement.sql

-- 1. Ajouter la colonne
ALTER TABLE demandes_engagement ADD COLUMN type_operation_id INT NOT NULL DEFAULT 3 AFTER mode_paiement_id;

-- 2. Ajouter la contrainte de clé étrangère
ALTER TABLE demandes_engagement ADD FOREIGN KEY (type_operation_id) REFERENCES types_operations(id);