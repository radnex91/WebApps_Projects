-- ============================================================
-- PharmaCare — Patch orthographe
-- Corrige les labels de permissions en base existante
-- Exécuter : mysql -u root pharmacare < patch_orthographe.sql
-- ============================================================

USE pharmacare;

UPDATE permissions SET libelle = 'Créer des ventes (Point de Vente)' WHERE code = 'vente.creer';