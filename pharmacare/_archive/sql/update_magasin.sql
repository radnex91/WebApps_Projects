-- ════════════════════════════════════════════════════════════
-- MODULE MAGASIN (dépôt central) — Fournisseur → Magasin → Pharmacie
-- Migration idempotente (relançable).
-- ════════════════════════════════════════════════════════════

-- ── Colonnes stock magasin sur les produits ────────────────
-- stock        = stock en pharmacie (vente au comptoir)
-- stock_magasin = stock du dépôt central (ravitaille la pharmacie)
ALTER TABLE produits
  ADD COLUMN IF NOT EXISTS stock_magasin INT DEFAULT 0 AFTER stock,
  ADD COLUMN IF NOT EXISTS seuil_magasin INT DEFAULT 20 AFTER stock_magasin;

-- ── Mouvements du magasin ──────────────────────────────────
CREATE TABLE IF NOT EXISTS mouvements_magasin (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    produit_id    INT,
    type          ENUM('entrée','sortie','ajustement') NOT NULL,
    quantite      INT NOT NULL,
    motif         VARCHAR(255),
    utilisateur_id INT,
    transfert_id  INT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id)    REFERENCES produits(id)       ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── Transferts Magasin → Pharmacie (entête) ────────────────
CREATE TABLE IF NOT EXISTS transferts_magasin (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    reference     VARCHAR(20) UNIQUE NOT NULL,
    utilisateur_id INT,
    note          TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── Lignes de transfert ────────────────────────────────────
CREATE TABLE IF NOT EXISTS transfert_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    transfert_id  INT NOT NULL,
    produit_id    INT,
    produit_nom   VARCHAR(200),
    quantite      INT NOT NULL,
    FOREIGN KEY (transfert_id) REFERENCES transferts_magasin(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)   REFERENCES produits(id)          ON DELETE SET NULL
);

-- ── Permissions ────────────────────────────────────────────
INSERT IGNORE INTO permissions (code, libelle, module) VALUES
  ('magasin.voir',  'Voir le stock magasin',                   'magasin'),
  ('magasin.gerer', 'Gérer le magasin (réceptions & transferts)', 'magasin');

-- Admin (1) et Pharmacien (2) reçoivent les deux permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE code IN ('magasin.voir','magasin.gerer');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN ('magasin.voir','magasin.gerer');