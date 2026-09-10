-- ════════════════════════════════════════════════════════════
-- INDEX DE PERFORMANCE — module Magasin
--
-- La page magasin (onglet stock) filtre sur actif=1 et trie
-- par nom (ORDER BY p.nom). Sans index composite, MySQL
-- parcourt toute la table puis trie => filesort lent.
--
-- L'onglet "État à une date" joint mouvements_magasin sur
-- produit_id et filtre par created_at. L'index simple sur
-- created_at (idx_mmag_date) ne couvre que la date ; un
-- index composite (produit_id, created_at) évite le tri
-- interne lors du GROUP BY.
--
-- Idempotent (IF NOT EXISTS) → relançable.
--
-- EXÉCUTION : mysql -u root --default-character-set=utf8mb4 pharmacare < update_indexes_perf_magasin.sql
-- ════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE produits          ADD INDEX IF NOT EXISTS idx_produits_actif_nom      (actif, nom);
ALTER TABLE mouvements_magasin ADD INDEX IF NOT EXISTS idx_mmag_produit_date      (produit_id, created_at);

-- Vérification
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'pharmacare'
  AND INDEX_NAME IN ('idx_produits_actif_nom','idx_mmag_produit_date')
ORDER BY TABLE_NAME;
