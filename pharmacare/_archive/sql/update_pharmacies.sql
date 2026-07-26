-- ════════════════════════════════════════════════════════════
-- MODULE MULTI-PHARMACIES — stock par pharmacie + choix à l'ouverture
-- Migration idempotente (relançable). Conçu pour MariaDB (XAMPP).
-- ════════════════════════════════════════════════════════════

-- ── Table des pharmacies ────────────────────────────────────
CREATE TABLE IF NOT EXISTS pharmacies (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(150) NOT NULL,
    adresse    VARCHAR(255) NULL,
    telephone   VARCHAR(30) NULL,
    actif      TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pharmacie principale par défaut (id=1 sert de cible à la migration du stock)
INSERT IGNORE INTO pharmacies (id, nom, adresse, telephone, actif)
VALUES (1, 'Pharmacie principale', NULL, NULL, 1);

-- ── Stock par pharmacie (table jointe) ──────────────────────
-- Une ligne = quantité d'un produit dans une pharmacie donnée.
CREATE TABLE IF NOT EXISTS produit_pharmacie (
    produit_id   INT NOT NULL,
    pharmacie_id  INT NOT NULL,
    stock        INT NOT NULL DEFAULT 0,
    seuil_alerte INT NOT NULL DEFAULT 10,
    PRIMARY KEY (produit_id, pharmacie_id),
    FOREIGN KEY (produit_id)  REFERENCES produits(id)   ON DELETE CASCADE,
    FOREIGN KEY (pharmacie_id) REFERENCES pharmacies(id) ON DELETE CASCADE
);

-- ── Migration du stock existant vers la Pharmacie principale ──
-- On copie produits.stock → produit_pharmacie.stock pour la pharmacie 1.
-- INSERT IGNORE garantit l'idempotence : relancer n'écrase pas les lignes
-- déjà créées (et donc pas le stock modifié depuis par les ventes).
INSERT IGNORE INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
SELECT p.id, 1, COALESCE(p.stock, 0), COALESCE(p.seuil_alerte, 10)
FROM produits p;

-- ── Caisse : la pharmacie choisie à l'ouverture ─────────────
ALTER TABLE sessions_caisse
  ADD COLUMN IF NOT EXISTS pharmacie_id INT NULL;
-- FK ajoutée séparément (tolérante aux ré-exécutions) :
-- NOTE : MariaDB >= 10.5 supporte « ADD FOREIGN KEY IF NOT EXISTS ».
-- Sur une version antérieure, commenter la ligne ci-dessous.
ALTER TABLE sessions_caisse
  ADD FOREIGN KEY IF NOT EXISTS (pharmacie_id) REFERENCES pharmacies(id);

-- ── Ventes : pharmacie dont le stock a été débité ───────────
ALTER TABLE ventes
  ADD COLUMN IF NOT EXISTS pharmacie_id INT NULL;
ALTER TABLE ventes
  ADD FOREIGN KEY IF NOT EXISTS (pharmacie_id) REFERENCES pharmacies(id);

-- ── Permissions ──────────────────────────────────────────────
INSERT IGNORE INTO permissions (code, libelle, module) VALUES
  ('pharmacies.voir',  'Voir les pharmacies',  'pharmacies'),
  ('pharmacies.gerer', 'Gérer les pharmacies', 'pharmacies');

-- Admin (1) reçoit les deux permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE code IN ('pharmacies.voir','pharmacies.gerer');