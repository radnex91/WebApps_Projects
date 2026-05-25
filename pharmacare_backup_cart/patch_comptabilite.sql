-- ============================================================
-- PharmaCare — Patch Comptabilité (Plan OHADA)
-- Exécuter : mysql -u root pharmacare < patch_comptabilite.sql
-- ============================================================

-- ── Plan comptable OHADA ──────────────────────────────────
CREATE TABLE IF NOT EXISTS plan_comptable (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    compte      VARCHAR(15) NOT NULL UNIQUE,
    intitule    VARCHAR(200) NOT NULL,
    classe      TINYINT(1) NOT NULL,
    nature      ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    compte_parent INT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_parent) REFERENCES plan_comptable(id)
);

-- ── Exercices comptables ─────────────────────────────────
CREATE TABLE IF NOT EXISTS exercices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(9) NOT NULL UNIQUE,
    libelle     VARCHAR(100) NOT NULL,
    date_debut  DATE NOT NULL,
    date_fin    DATE NOT NULL,
    cloture     TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Écritures comptables (entête) ────────────────────────
CREATE TABLE IF NOT EXISTS ecritures (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(30) NOT NULL UNIQUE,
    libelle         VARCHAR(255) NOT NULL,
    date_ecriture   DATE NOT NULL,
    exercice_id     INT,
    utilisateur_id  INT,
    source          VARCHAR(30) DEFAULT 'manuel',
    source_ref      VARCHAR(30) NULL,
    verrouillee     TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- ── Lignes d'écriture (double partie) ───────────────────
CREATE TABLE IF NOT EXISTS ecriture_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ecriture_id   INT NOT NULL,
    compte_id     INT NOT NULL,
    debit         DECIMAL(12,2) DEFAULT 0,
    credit        DECIMAL(12,2) DEFAULT 0,
    libelle_ligne VARCHAR(255),
    FOREIGN KEY (ecriture_id) REFERENCES ecritures(id) ON DELETE CASCADE,
    FOREIGN KEY (compte_id)   REFERENCES plan_comptable(id)
);

-- ══════════════════════════════════════════════════════════
-- PLAN COMPTABLE OHADA — Pharmacie (CEMAC)
-- ══════════════════════════════════════════════════════════

-- ── Classe 1 : Capitaux ──────────────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('1',     'Comptes de capitaux',       1, 'credit'),
('101',   'Capital social',            1, 'credit'),
('1011',  'Capital individuel',        1, 'credit'),
('106',   'Réserves',                  1, 'credit'),
('1061',  'Réserves légales',          1, 'credit'),
('1063',  'Réserves libres',           1, 'credit'),
('12',    'Résultat de l''exercice',   1, 'credit'),
('129',   'Résultat en instance d''affectation', 1, 'credit');

-- ── Classe 2 : Immobilisations ───────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('2',     'Comptes d''immobilisations', 2, 'debit'),
('218',   'Autres immobilisations',    2, 'debit'),
('2183',  'Matériel et outillage',     2, 'debit'),
('2184',  'Mobilier de bureau',        2, 'debit'),
('2185',  'Matériel informatique',     2, 'debit'),
('281',   'Amortissements',            2, 'credit');

-- ── Classe 3 : Stocks ────────────────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('3',     'Comptes de stocks',         3, 'debit'),
('311',   'Marchandises en stock',     3, 'debit'),
('3111',  'Médicaments en stock',      3, 'debit'),
('3112',  'Produits parapharmaceutiques', 3, 'debit');

-- ── Classe 4 : Tiers ─────────────────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('4',     'Comptes de tiers',          4, 'debit'),
('401',   'Fournisseurs',              4, 'credit'),
('4011',  'Fournisseurs achats',       4, 'credit'),
('411',   'Clients',                   4, 'debit'),
('4111',  'Clients - Assurance',       4, 'debit'),
('421',   'Personnel rémunérations',   4, 'credit'),
('431',   'Sécurité sociale',          4, 'credit'),
('441',   'État - TVA collectée',      4, 'credit'),
('4411',  'TVA collectée 19.25%',      4, 'credit'),
('445',   'État - TVA récupérable',    4, 'debit'),
('4451',  'TVA récupérable 19.25%',    4, 'debit'),
('471',   'Compte d''attente',         4, 'credit');

-- ── Classe 5 : Trésorerie ────────────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('5',     'Comptes de trésorerie',     5, 'debit'),
('511',   'Chèques à encaisser',       5, 'debit'),
('512',   'Banque',                    5, 'debit'),
('571',   'Caisse',                    5, 'debit'),
('5711',  'Caisse principale',         5, 'debit'),
('581',   'Virements internes',        5, 'debit');

-- ── Classe 6 : Charges ───────────────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('6',     'Comptes de charges',        6, 'debit'),
('601',   'Achats de marchandises',    6, 'debit'),
('6011',  'Achats de médicaments',     6, 'debit'),
('603',   'Variation des stocks',      6, 'debit'),
('6031',  'Variation stocks marchandises', 6, 'debit'),
('611',   'Transports sur achats',     6, 'debit'),
('623',   'Publicité et publications', 6, 'debit'),
('625',   'Déplacements',              6, 'debit'),
('626',   'Frais postaux',             6, 'debit'),
('627',   'Services bancaires',        6, 'debit'),
('641',   'Salaires',                  6, 'debit'),
('645',   'Charges sociales',          6, 'debit'),
('658',   'Charges diverses',          6, 'debit'),
('681',   'Dotations aux amortissements', 6, 'debit'),
('695',   'Impôts et taxes',           6, 'debit');

-- ── Classe 7 : Produits ──────────────────────────────────
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('7',     'Comptes de produits',       7, 'credit'),
('701',   'Ventes de marchandises',    7, 'credit'),
('7011',  'Ventes de médicaments',     7, 'credit'),
('708',   'Produits des activités annexes', 7, 'credit'),
('751',   'Produits financiers',       7, 'credit'),
('758',   'Produits divers',           7, 'credit'),
('771',   'Produits exceptionnels',    7, 'credit');

-- ══════════════════════════════════════════════════════════
-- EXERCICE 2026
-- ══════════════════════════════════════════════════════════
INSERT INTO exercices (code, libelle, date_debut, date_fin)
SELECT '2026', 'Exercice 2026', '2026-01-01', '2026-12-31'
WHERE NOT EXISTS (SELECT 1 FROM exercices WHERE code = '2026');

-- ══════════════════════════════════════════════════════════
-- PERMISSIONS
-- ══════════════════════════════════════════════════════════
INSERT IGNORE INTO permissions (code, libelle, module) VALUES
('comptabilite.voir',   'Accéder à la comptabilité',              'comptabilite'),
('comptabilite.saisie', 'Saisir des écritures manuelles',        'comptabilite'),
('comptabilite.plan',   'Gérer le plan comptable',               'comptabilite');

-- Admin : toutes les permissions comptabilité
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE module = 'comptabilite'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = 1 AND rp.permission_id = permissions.id);

-- Pharmacien : voir + saisie
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE module = 'comptabilite' AND code IN ('comptabilite.voir', 'comptabilite.saisie')
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = 2 AND rp.permission_id = permissions.id);
