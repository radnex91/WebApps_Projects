-- Migration : Exécution partielle des engagements
-- Ajoute les statuts 'execution_partielle' et 'solde', plus les colonnes de suivi

ALTER TABLE demandes_engagement
  MODIFY COLUMN statut ENUM(
    'brouillon','soumis','valide_hierarchie','valide_comptable',
    'valide_daf','approuve','execution_partielle','execute',
    'solde','rejete','renvoye','annule'
  ) DEFAULT 'brouillon';

ALTER TABLE demandes_engagement
  ADD COLUMN montant_execute DECIMAL(15,2) DEFAULT 0 AFTER montant,
  ADD COLUMN montant_restant DECIMAL(15,2) DEFAULT 0 AFTER montant_execute,
  ADD COLUMN motif_solder TEXT NULL AFTER montant_restant,
  ADD COLUMN date_solder TIMESTAMP NULL AFTER motif_solder;

ALTER TABLE demandes_engagement ADD INDEX idx_statut_execution (statut, caisse_id);

ALTER TABLE validations_engagement
  MODIFY COLUMN etape ENUM('hierarchie','comptable','daf','execution','execution_partielle','solder') NOT NULL;