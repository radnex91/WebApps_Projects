-- ════════════════════════════════════════════════════════════
-- MODULE MENUS — activation/désactivation des entrées du sidebar
-- Migration idempotente (relançable). Conçu pour MariaDB (XAMPP).
-- ════════════════════════════════════════════════════════════

-- ── Table des menus latéraux ───────────────────────────────
-- code       = identifiant stable référencé dans includes/layout.php
-- libelle    = libellé affiché dans l'écran de gestion
-- actif      = 1 visible / 0 masqué pour tout le monde
-- position   = ordre d'affichage (réservé)
CREATE TABLE IF NOT EXISTS menus (
    code     VARCHAR(60) PRIMARY KEY,
    libelle  VARCHAR(120) NOT NULL,
    actif    TINYINT(1) DEFAULT 1,
    position INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed : toutes les entrées du sidebar, actives par défaut ──
-- ON DUPLICATE KEY UPDATE libelle : relancer ce script corrige les libellés
-- (utile si un premier import a corrompu les accents — encodage client).
-- `actif` n'est PAS touché : les activations/désactivations de l'admin sont préservées.
INSERT INTO menus (code, libelle, position) VALUES
  ('dashboard',            'Tableau de bord',        1),
  ('vente',                'Point de Vente',         2),
  ('remise_codes',         'Codes de remise',        3),
  ('caisse',               'Caisses',                4),
  ('stock',                'Stock',                  5),
  ('produits',             'Médicaments',            6),
  ('fournisseurs',         'Fournisseurs',           7),
  ('clients',              'Clients',                8),
  ('commandes',            'Commandes',              9),
  ('retours',              'Retours caisse',        10),
  ('magasin',              'Magasin',               11),
  ('marketing',            'Marketing',             12),
  ('ventes_hist',          'Historique ventes',     13),
  ('rapports',             'Rapports',              14),
  ('rapports_caissier',    'Mes Rapports',          15),
  ('comptabilite',         'Comptabilité',          16),
  ('utilisateurs',         'Utilisateurs',          17),
  ('remise_approbateurs',  'Approbateurs de remise',18),
  ('roles',                'Rôles & Permissions',   19),
  ('categories',           'Catégories',            20),
  ('pharmacies',           'Pharmacies',            21),
  ('parametres',           'Paramètres',            22)
  ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);

-- ── Permission de gestion des menus ────────────────────────
INSERT IGNORE INTO permissions (code, libelle, module) VALUES
  ('menus.voir',  'Voir les menus',  'menus'),
  ('menus.gerer', 'Gérer les menus', 'menus');

-- Admin (1) reçoit les deux permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE code IN ('menus.voir','menus.gerer');