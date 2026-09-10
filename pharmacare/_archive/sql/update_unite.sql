-- ════════════════════════════════════════════════════════════
-- Colonne UNITÉ de conditionnement sur les produits
-- Migration idempotente (relançable).
-- ════════════════════════════════════════════════════════════

-- unite = unité de vente / conditionnement (boîte, comprimé,
-- plaquette, flacon, ampoule, tube, sachet, gélule, injection…).
-- Libre (saisie datalist côté UI), NULL si non renseigné.
ALTER TABLE produits
  ADD COLUMN IF NOT EXISTS unite VARCHAR(30) DEFAULT NULL AFTER reference;