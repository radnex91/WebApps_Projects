-- ════════════════════════════════════════════════════════════
-- INDEX DE PERFORMANCE — colonnes created_at
--
-- La quasi-totalité des requêtes temporelles (rapports, dashboard,
-- ventes-hist, historique magasin, reconstitution « état à une date »)
-- filtrent ou trient sur created_at. Aucun index n'existait sur ces
-- colonnes → full scans (type=ALL) dont le coût croît linéairement
-- avec le nombre de ventes/mouvements.
--
-- Ce script ajoute un index B-tree sur created_at pour les 4 tables
-- les plus sollicitées. Idempotent (IF NOT EXISTS) → relançable.
--
-- EXÉCUTION : mysql -u root --default-character-set=utf8mb4 pharmacare < update_indexes_dates.sql
-- ════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE ventes             ADD INDEX IF NOT EXISTS idx_ventes_date (created_at);
ALTER TABLE mouvements_magasin ADD INDEX IF NOT EXISTS idx_mmag_date   (created_at);
ALTER TABLE mouvements_stock   ADD INDEX IF NOT EXISTS idx_msto_date   (created_at);
ALTER TABLE transferts_magasin ADD INDEX IF NOT EXISTS idx_trf_date     (created_at);

-- ── Comptabilité : ecritures filtrée/triée par date_ecriture (journal,
--    grand-livre, balance, compte de résultat) et par exercice (clôture).
--    created_at sert les widgets « dernières écritures » (ORDER BY ... LIMIT).
ALTER TABLE ecritures ADD INDEX IF NOT EXISTS idx_ecr_date     (date_ecriture);
ALTER TABLE ecritures ADD INDEX IF NOT EXISTS idx_ecr_created  (created_at);
ALTER TABLE ecritures ADD INDEX IF NOT EXISTS idx_ecr_exercice (exercice_id);

-- Vérification
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'pharmacare'
  AND INDEX_NAME IN ('idx_ventes_date','idx_mmag_date','idx_msto_date','idx_trf_date',
                     'idx_ecr_date','idx_ecr_created','idx_ecr_exercice')
ORDER BY TABLE_NAME;