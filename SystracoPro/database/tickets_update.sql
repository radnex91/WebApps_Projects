-- ================================================================
-- Mise à jour des tickets existants : tarifs + trajets
-- À exécuter dans phpMyAdmin (base transport_db)
-- ================================================================

-- 1) Mettre à jour l'agence de départ/arrivée et le tarif pour les
--    tickets liés à un voyage (via la destination du voyage)
UPDATE tickets t
JOIN voyages v ON t.voyage_id = v.id
JOIN destinations d ON v.destination_id = d.id
SET
    t.agence_depart_id  = COALESCE(t.agence_depart_id, d.agence_depart),
    t.agence_arrivee_id = COALESCE(t.agence_arrivee_id, d.agence_arrivee)
WHERE t.voyage_id IS NOT NULL
  AND (t.agence_depart_id IS NULL OR t.agence_arrivee_id IS NULL);

-- 2) Affecter le tarif_id et ajuster le montant selon le tarif
--    correspondant (destination du voyage + classe du ticket)
UPDATE tickets t
JOIN voyages v ON t.voyage_id = v.id
JOIN tarifs tr ON tr.destination_id = v.destination_id
              AND tr.classe = t.classe
              AND tr.actif = 1
SET
    t.tarif_id      = tr.id,
    t.montant       = tr.prix,
    t.montant_total = tr.prix + COALESCE(t.montant_bagages, 0)
WHERE t.voyage_id IS NOT NULL
  AND t.tarif_id IS NULL;

-- 3) Pour les tickets en vente libre (sans voyage_id), essayer de
--    rattraper via agence_depart_id / agence_arrivee_id déjà présents
--    (cette étape suppose que les colonnes sont déjà renseignées)
UPDATE tickets t
JOIN destinations d ON d.agence_depart = t.agence_depart_id
                   AND d.agence_arrivee = t.agence_arrivee_id
JOIN tarifs tr ON tr.destination_id = d.id
              AND tr.classe = t.classe
              AND tr.actif = 1
SET
    t.tarif_id      = tr.id,
    t.montant       = tr.prix,
    t.montant_total = tr.prix + COALESCE(t.montant_bagages, 0)
WHERE t.voyage_id IS NULL
  AND t.tarif_id IS NULL
  AND t.agence_depart_id IS NOT NULL
  AND t.agence_arrivee_id IS NOT NULL;

-- 4) Vérification : tickets encore sans tarif
SELECT COUNT(*) AS tickets_sans_tarif
FROM tickets
WHERE tarif_id IS NULL;
