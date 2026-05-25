-- ============================================================
-- PharmaCare — Patch RBAC (Rôles & Permissions)
-- Appliquer sur une base existante : mysql -u root pharmacare < patch_rbac.sql
-- ============================================================

-- ── 1. Table des rôles ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(60)  NOT NULL UNIQUE,
    libelle     VARCHAR(100) NOT NULL,
    est_systeme TINYINT(1)   DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Table des permissions ────────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(80)  NOT NULL UNIQUE,
    libelle     VARCHAR(150) NOT NULL,
    module      VARCHAR(60)  NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Table pivot rôle ↔ permission ────────────────────────
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════
-- DONNÉES DE RÉFÉRENCE
-- ══════════════════════════════════════════════════════════

-- ── Rôles système ───────────────────────────────────────────
INSERT INTO roles (id, code, libelle, est_systeme) VALUES
(1, 'admin',      'Administrateur', 1),
(2, 'pharmacien', 'Pharmacien',     1),
(3, 'caissier',   'Caissier',       1);

-- ── Permissions (25) ────────────────────────────────────────
INSERT INTO permissions (id, code, libelle, module) VALUES
(1,  'dashboard.voir',       'Voir le tableau de bord',       'dashboard'),
(2,  'vente.creer',         'Créer des ventes (Point de Vente)',        'vente'),
(3,  'stock.voir',           'Voir le stock',                 'stock'),
(4,  'stock.ajuster',        'Ajuster le stock',              'stock'),
(5,  'produits.voir',        'Voir les médicaments',          'produits'),
(6,  'produits.ajouter',     'Ajouter des médicaments',       'produits'),
(7,  'produits.modifier',    'Modifier des médicaments',      'produits'),
(8,  'produits.archiver',    'Archiver des médicaments',       'produits'),
(9,  'fournisseurs.voir',    'Voir les fournisseurs',         'fournisseurs'),
(10, 'fournisseurs.ajouter', 'Ajouter des fournisseurs',      'fournisseurs'),
(11, 'fournisseurs.modifier','Modifier des fournisseurs',     'fournisseurs'),
(12, 'fournisseurs.supprimer','Supprimer des fournisseurs',   'fournisseurs'),
(13, 'commandes.voir',       'Voir les commandes',            'commandes'),
(14, 'commandes.creer',      'Créer des commandes',           'commandes'),
(15, 'commandes.modifier',   'Modifier des commandes',        'commandes'),
(16, 'ventes_hist.voir',     'Voir l''historique des ventes', 'ventes_hist'),
(17, 'rapports.voir',        'Voir les rapports',             'rapports'),
(18, 'utilisateurs.voir',    'Voir les utilisateurs',         'utilisateurs'),
(19, 'utilisateurs.gerer',   'Gérer les utilisateurs',        'utilisateurs'),
(20, 'categories.voir',      'Voir les catégories',           'categories'),
(21, 'categories.gerer',     'Gérer les catégories',           'categories'),
(22, 'parametres.voir',      'Voir les paramètres',           'parametres'),
(23, 'parametres.gerer',     'Gérer les paramètres',           'parametres'),
(24, 'roles.voir',           'Voir les rôles & permissions',  'roles'),
(25, 'roles.gerer',          'Gérer les rôles & permissions',  'roles');

-- ── Permissions par rôle ────────────────────────────────────

-- Admin : TOUTES les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Pharmacien : 14 permissions (fournisseurs = lecture seule, rôles = admin uniquement)
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),
(2,9),
(2,13),(2,14),(2,15),
(2,16),(2,17);

-- Caissier : 5 permissions (même accès qu'avant)
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3,1),(3,2),(3,3),(3,16),(3,17);

-- ── Migration utilisateurs : ENUM → FK ─────────────────────
ALTER TABLE utilisateurs ADD COLUMN role_id INT NULL AFTER role;

UPDATE utilisateurs SET role_id = 1 WHERE role = 'admin';
UPDATE utilisateurs SET role_id = 2 WHERE role = 'pharmacien';
UPDATE utilisateurs SET role_id = 3 WHERE role = 'caissier';

-- Sécurité : aucun utilisateur ne doit avoir role_id NULL
UPDATE utilisateurs SET role_id = 3 WHERE role_id IS NULL;

ALTER TABLE utilisateurs ADD FOREIGN KEY (role_id) REFERENCES roles(id);
ALTER TABLE utilisateurs DROP COLUMN role;