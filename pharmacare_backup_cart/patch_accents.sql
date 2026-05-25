-- ============================================================
-- PharmaCare — Patch accents français
-- Exécuter AVANT de déployer les PHP mis à jour :
--   mysql -u root pharmacare < patch_accents.sql
-- ============================================================

USE pharmacare;

-- ── Mode de paiement ──────────────────────────────────────
ALTER TABLE ventes MODIFY COLUMN mode_paiement ENUM('espèces','carte','chèque','assurance') DEFAULT 'espèces';
UPDATE ventes SET mode_paiement = 'espèces' WHERE mode_paiement = 'especes';
UPDATE ventes SET mode_paiement = 'chèque'  WHERE mode_paiement = 'cheque';

-- ── Statut commandes ──────────────────────────────────────
ALTER TABLE commandes MODIFY COLUMN statut ENUM('en_attente','en_cours','livrée','annulée') DEFAULT 'en_attente';
UPDATE commandes SET statut = 'livrée'  WHERE statut = 'livree';
UPDATE commandes SET statut = 'annulée' WHERE statut = 'annulee';

-- ── Type mouvements stock ─────────────────────────────────
ALTER TABLE mouvements_stock MODIFY COLUMN type ENUM('entrée','sortie','ajustement') NOT NULL;
UPDATE mouvements_stock SET type = 'entrée' WHERE type = 'entree';

-- ── Statut sessions caisse ────────────────────────────────
ALTER TABLE sessions_caisse MODIFY COLUMN statut ENUM('ouverte','fermée') NOT NULL DEFAULT 'ouverte';
UPDATE sessions_caisse SET statut = 'fermée' WHERE statut = 'fermee';

-- ── Type mouvements caisse ────────────────────────────────
ALTER TABLE mouvements_caisse MODIFY COLUMN type ENUM('entrée','sortie') NOT NULL;
UPDATE mouvements_caisse SET type = 'entrée' WHERE type = 'entree';

-- ── Moyen mouvements caisse ───────────────────────────────
ALTER TABLE mouvements_caisse MODIFY COLUMN moyen ENUM('espèces','carte','chèque','assurance') DEFAULT 'espèces';
UPDATE mouvements_caisse SET moyen = 'espèces' WHERE moyen = 'especes';
UPDATE mouvements_caisse SET moyen = 'chèque'  WHERE moyen = 'cheque';

-- ── Groupe paramètres ─────────────────────────────────────
UPDATE parametres SET groupe = 'général' WHERE groupe = 'general';
