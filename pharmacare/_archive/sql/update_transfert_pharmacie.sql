-- ════════════════════════════════════════════════════════════
-- BON DE RAVITAILLEMENT — pharmacie destinataire du transfert magasin
-- Migration idempotente (relançable). Conçu pour MariaDB (XAMPP).
-- ════════════════════════════════════════════════════════════

-- On mémorise la pharmacie destinataire sur l'entête du transfert.
-- (Avant, elle n'était encodée que dans la note « → Nom pharmacie ».)
ALTER TABLE transferts_magasin
  ADD COLUMN IF NOT EXISTS pharmacie_id INT NULL;

-- FK tolérante aux ré-exécutions (MariaDB >= 10.5).
ALTER TABLE transferts_magasin
  ADD FOREIGN KEY IF NOT EXISTS (pharmacie_id) REFERENCES pharmacies(id);