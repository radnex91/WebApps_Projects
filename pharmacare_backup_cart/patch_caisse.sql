-- ============================================================
-- PharmaCare — Patch Caisse Décentralisée
-- Exécuter via : mysql -u root pharmacare < patch_caisse.sql
-- ============================================================

USE pharmacare;

-- ── Postes de caisse ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS caisses (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    actif      TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Sessions de caisse (ouverture → fermeture) ───────────
CREATE TABLE IF NOT EXISTS sessions_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id       INT NOT NULL,
    caissier_id     INT NOT NULL,
    fond_initial    DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_ouverture  DATETIME NOT NULL,
    date_fermeture  DATETIME NULL,
    solde_attendu   DECIMAL(10,2) NULL,
    solde_reel      DECIMAL(10,2) NULL,
    ecart           DECIMAL(10,2) NULL,
    statut          ENUM('ouverte','fermée') NOT NULL DEFAULT 'ouverte',
    FOREIGN KEY (caisse_id)   REFERENCES caisses(id),
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id)
);

-- ── Mouvements de caisse (entrées / sorties) ─────────────
CREATE TABLE IF NOT EXISTS mouvements_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT NOT NULL,
    type            ENUM('entrée','sortie') NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    motif           VARCHAR(255) NOT NULL,
    moyen           ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces',
    reference_vente VARCHAR(20) NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions_caisse(id)
);

-- ── Permissions ──────────────────────────────────────────
INSERT INTO permissions (code, libelle, module) VALUES
('caisse.voir',   'Voir le dashboard des caisses', 'caisse'),
('caisse.gerer',  'Gérer les caisses',              'caisse'),
('caisse.ouvrir', 'Ouvrir une session de caisse',   'caisse');

-- Admin (role_id=1) : toutes les permissions caisse
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE module = 'caisse';

-- Pharmacien (role_id=2) : voir + ouvrir
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE module = 'caisse' AND code IN ('caisse.voir', 'caisse.ouvrir');

-- Caissier (role_id=3) : ouvrir seulement
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE module = 'caisse' AND code = 'caisse.ouvrir';

-- ── Données de démo ──────────────────────────────────────
INSERT INTO caisses (id, nom) VALUES
(1, 'Caisse 1'),
(2, 'Caisse 2'),
(3, 'Caisse 3');

-- Session fermée de démo (Yasmine/caissier sur Caisse 1, hier)
INSERT INTO sessions_caisse (id, caisse_id, caissier_id, fond_initial, date_ouverture, date_fermeture, solde_attendu, solde_reel, ecart, statut)
VALUES (1, 1, 3, 50000, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 8 HOUR, 245000, 242500, -2500, 'fermée');

INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen, reference_vente) VALUES
(1, 'entrée', 150000, 'Vente VNT-2026-001', 'espèces', 'VNT-2026-001'),
(1, 'entrée',  45000, 'Vente VNT-2026-003', 'espèces', 'VNT-2026-003'),
(1, 'sortie',  5000, 'Achat fournitures bureau', 'espèces', NULL);
