-- =============================================
-- ButcheryPOS: Import Boucherie PP&L Products
-- =============================================
-- Source : catalogue BOUCHERIE PP&L (transcription photos)
-- Cible  : base butcherypos (schema ButcheryPOS)
--
-- CORRECTIONS APPLIQUEES vs source originale :
--   1) AIL_02 (AILERONS DE DINDE) : recatégorisé de Viandes>Chèvre vers Volailles>Dinde
--   2) COT_02 (ECHINE DE PORC) : supprimé — doublon exact de ECH-01
--   3) CARA : "CARCASSE DE POISON" → "CARCASSE DE POISSON" (coquille)
--   4) GIG_01 : "GIGO D'AGNAUX" → "GIGOT D'AGNEAU" (orthographe)
--   5) GIG_02 : "GIGO DE PORC" → "GIGOT DE PORC" (orthographe)
--   6) COT_04, COL_01, EPA_01 : "AGNAUX" → "AGNEAU" (singulier correct)
--   7) BOE001 : "RETOUR ABBATOIRE" → "RETOUR ABATTOIR" (orthographe)
--   8) CAF-1, CHOC-1, CHOC-2, CUR-1, DENT-1, DENT-2 : unité corrigée KG→Pièce
--   9) Sous-catégorie "Dinde" ajoutée (sous Volailles) pour AILERONS DE DINDE
--  10) prix_base NULL → sale_price = 0 (à renseigner ultérieurement)
--
-- PREREQUIS : base butcherypos initialisée via schema.sql
-- ATTENTION : ce script supprime les produits et catégories de démo existants
-- =============================================

USE butcherypos;

-- =============================================
-- NETTOYAGE DES DONNÉES DE DÉMO
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM breakdown_items;
DELETE FROM breakdowns;
DELETE FROM sale_items;
DELETE FROM payments;
DELETE FROM sales;
DELETE FROM pos_sessions;
DELETE FROM stock_movements;
DELETE FROM stock_batches;
DELETE FROM expiry_alerts;
DELETE FROM products;
DELETE FROM categories;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- CATÉGORIES (modèle hiérarchique parent_id)
-- =============================================

-- Catégories de premier niveau
INSERT INTO categories (id, name, description, parent_id, sort_order, is_active) VALUES
(1,  'Volailles',     'Poulet, canard, dinde et volailles diverses',   NULL, 1, 1),
(2,  'Viandes',       'Bœuf, porc, chèvre, mouton, agneau',           NULL, 2, 1),
(3,  'Poissonnerie',  'Poissons, crustacés et produits de la mer',    NULL, 3, 1),
(4,  'Restauration',  'Brochettes, chawarma et plats préparés',        NULL, 4, 1),
(5,  'Épicerie',      'Produits divers, épices et condiments',        NULL, 5, 1),
(6,  'Boissons',      'Boissons alcoolisées et non alcoolisées',       NULL, 6, 1),
(7,  'Charcuterie',   'Charcuterie et produits transformés',           NULL, 7, 1),
(8,  'Consommables',  'Emballages et fournitures',                     NULL, 8, 1);

-- Sous-catégories
INSERT INTO categories (id, name, description, parent_id, sort_order, is_active) VALUES
-- Volailles
(9,  'Poulet Traiteur',  NULL, 1, 1, 1),
(10, 'Poulet',           NULL, 1, 2, 1),
(11, 'Canard',           NULL, 1, 3, 1),
(12, 'Dinde',            NULL, 1, 4, 1),
-- Viandes
(13, 'Bœuf',             NULL, 2, 1, 1),
(14, 'Porc',             NULL, 2, 2, 1),
(15, 'Chèvre',           NULL, 2, 3, 1),
(16, 'Mouton/Ovin',      NULL, 2, 4, 1),
(17, 'Agneau',            NULL, 2, 5, 1),
-- Poissonnerie
(18, 'Poisson',          NULL, 3, 1, 1),
(19, 'Poisson fumé',     NULL, 3, 2, 1),
(20, 'Crustacés',        NULL, 3, 3, 1),
-- Restauration
(21, 'Brochettes / plats',  NULL, 4, 1, 1),
(22, 'Chawarma / plats',    NULL, 4, 2, 1),
-- Épicerie
(23, 'Divers',           NULL, 5, 1, 1),
(24, 'Épices',           NULL, 5, 2, 1),
-- Boissons
(25, 'Boissons',         NULL, 6, 1, 1),
-- Charcuterie
(26, 'Charcuterie',      NULL, 7, 1, 1),
-- Consommables
(27, 'Emballage',        NULL, 8, 1, 1);

-- =============================================
-- PRODUITS
-- =============================================
-- Colonnes : name, sku, category_id, unit_id, cost_price, sale_price,
--            quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days,
--            is_active, is_carcass
--
-- unit_id : 1=Kilogramme, 3=Pièce
-- cost_price = 0 par défaut (à renseigner ultérieurement)
-- is_carcass = 1 pour les animaux entiers (découpe)
-- =============================================

-- ==========================================
-- POULET TRAITEUR (cat 9)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Ailes rôties de poulet (03 pièces)',  'AIL-04',    9, 3, 0, 1000,   0, 3, 1, 3, 1, 0),
('Ailes poulet traiteur',              'AIL-03',    9, 1, 0, 2000,   0, 5, 1, 3, 1, 0),
('Carcasse poulet traiteur',           'CAR-02',    9, 1, 0, 700,    0, 5, 1, 3, 1, 0),
('Cou rôti de poulet',                 'COU-01',    9, 1, 0, 4500,   0, 5, 1, 3, 1, 0),
('Demi-poulet rôti',                   'DEMIROTI',  9, 3, 0, 1750,   0, 3, 1, 3, 1, 0);

-- ==========================================
-- POULET (cat 10)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Ailes de poulet',         'AIL_01',    10, 1, 0, 3690,  0, 5, 1, 3, 1, 0),
('Blanc de poulet',         'BLA_01',    10, 1, 0, 4000,  0, 5, 1, 3, 1, 0),
('Boulettes de poulet',     'BOU_02',    10, 1, 0, 6500,  0, 5, 1, 3, 1, 0),
('Brochette de poulet',     'BRO-02',    10, 1, 0, 0,     0, 5, 1, 3, 1, 0),
('Carcasse de poulet',      'CAR_02',    10, 1, 0, 1000,  0, 5, 1, 3, 1, 0),
('Cuisse de poulet',        'CUI_01',    10, 1, 0, 3590,  0, 5, 1, 3, 1, 0),
('Déchets de poulet',       'DECH-01',   10, 1, 0, 1000,  0, 5, 1, 3, 1, 0),
('Émincé de poulet',        'EMI-02',    10, 1, 0, 5590,  0, 5, 1, 3, 1, 0),
('Foie de poulet',          'FOI_02',    10, 1, 0, 4000,  0, 5, 1, 3, 1, 0),
('Gésier de poulet',        'GES_01',    10, 1, 0, 0,     0, 5, 1, 3, 1, 0),
('Hamburger poulet',        'HAM_01',    10, 1, 0, 0,     0, 5, 1, 3, 1, 0);

-- ==========================================
-- CANARD (cat 11)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Canard entier',  'CAN_01',  11, 1, 0, 2500,  0, 5, 1, 5, 1, 1);

-- ==========================================
-- DINDE (cat 12) — nouvele sous-catégorie
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Ailerons de dinde',  'AIL_02',  12, 1, 0, 0,  0, 5, 1, 5, 1, 0);

-- ==========================================
-- BŒUF (cat 13)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Basse côte',                 'BAS_02',   13, 1, 0, 3490,   0, 5, 1, 5, 1, 0),
('Bavette de bœuf',           'BAV_01',   13, 1, 0, 3790,   0, 5, 1, 5, 1, 0),
('Blanquette de bœuf',        'BLA-01',   13, 1, 0, 4000,   0, 5, 1, 5, 1, 0),
('Retour abattoir',            'BOE001',   13, 1, 0, 20000,  0, 5, 1, 5, 1, 0),
('Bœuf entier en kg',          'BOE_01',   13, 1, 0, 3000,   0, 5, 1, 5, 1, 1),
('Bœuf entier sur pieds',      'BOE_02',   13, 1, 0, 400000, 0, 1, 1, 5, 1, 1),
('Bosse de bœuf',              'BOS_02',   13, 1, 0, 3390,   0, 5, 1, 5, 1, 0),
('Boulette de bœuf',           'BOU_01',   13, 1, 0, 5500,   0, 5, 1, 5, 1, 0),
('Bourguignon',                 'BOU_03',   13, 1, 0, 3150,   0, 5, 1, 5, 1, 0),
('Boyaux de bœuf',             'BOY_01',   13, 1, 0, 1500,   0, 5, 1, 5, 1, 0),
('Brochette de filet de bœuf', 'BRO-01',   13, 1, 0, 5800,   0, 5, 1, 5, 1, 0),
('Chute de bœuf',              'CHU-02',   13, 1, 0, 800,    0, 5, 1, 5, 1, 0),
('Cœur de bœuf',               'COE_01',   13, 1, 0, 2500,   0, 5, 1, 5, 1, 0),
('Collier de bœuf',            'COL-01',   13, 1, 0, 2600,   0, 5, 1, 5, 1, 0),
('Côte de veau',               'COT-01',   13, 1, 0, 4090,   0, 5, 1, 5, 1, 0),
('Côte de bœuf',               'COT_01',   13, 1, 0, 3990,   0, 5, 1, 5, 1, 0),
('Crosse de bœuf',             'CRO_01',   13, 1, 0, 300,    0, 5, 1, 5, 1, 0),
('Émincé de bœuf',             'EMI_01',   13, 1, 0, 3800,   0, 5, 1, 5, 1, 0),
('Entrecôte de bœuf',          'ENT_01',   13, 1, 0, 4190,   0, 5, 1, 5, 1, 0),
('Escalope de bœuf',           'ESC-01',   13, 1, 0, 4000,   0, 5, 1, 5, 1, 0),
('Faux filet de bœuf',         'FAU_01',   13, 1, 0, 3790,   0, 5, 1, 5, 1, 0),
('Filet de bœuf',              'FIL_02',   13, 1, 0, 4990,   0, 5, 1, 5, 1, 0),
('Foie de bœuf',               'FOI_01',   13, 1, 0, 2490,   0, 5, 1, 5, 1, 0),
('Gîte hachée de bœuf',       'GIT-01',   13, 1, 0, 0,      0, 5, 1, 5, 1, 0),
('Gîte de bœuf',               'GIT_01',   13, 1, 0, 3700,   0, 5, 1, 5, 1, 0),
('Gîte à la noix de bœuf',    'GIT_02',   13, 1, 0, 1500,   0, 5, 1, 5, 1, 0),
('Gorge de bœuf',              'GOR-01',   13, 1, 0, 0,      0, 5, 1, 5, 1, 0),
('Bauette',                    'BAU_01',   13, 1, 0, 3500,   0, 5, 1, 5, 1, 0);

-- ==========================================
-- PORC (cat 14)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Boulette PPL',                  'BOU_04',   14, 1, 0, 0,     0, 5, 1, 5, 1, 0),
('Boyaux de porc',                'BOY-01',   14, 1, 0, 7000,  0, 5, 1, 5, 1, 0),
('Chaire à saucisse de porc',     'CHA-01',   14, 1, 0, 3390,  0, 5, 1, 5, 1, 0),
('Saucisse chipolatas de porc',   'CHI_01',   14, 1, 0, 4990,  0, 5, 1, 5, 1, 0),
('Chutes de porc',                'CHU-01',   14, 1, 0, 800,   0, 5, 1, 5, 1, 0),
('Côte échine',                   'COTE_001', 14, 1, 0, 3500,  0, 5, 1, 5, 1, 0),
('Côte à os de porc',             'COT_03',   14, 1, 0, 3890,  0, 5, 1, 5, 1, 0),
('Côtelette de porc',             'COT_06',   14, 1, 0, 3890,  0, 5, 1, 5, 1, 0),
('Côte de porc',                  'COT_07',   14, 1, 0, 0,     0, 5, 1, 5, 1, 0),
('Échine de porc',                'ECH-01',   14, 1, 0, 3890,  0, 5, 1, 5, 1, 0),
('Émincé de porc',                'EMI-01',   14, 1, 0, 4990,  0, 5, 1, 5, 1, 0),
('Escalope de porc',              'ESC_01',   14, 1, 0, 3790,  0, 5, 1, 5, 1, 0),
('Filet mignon',                   'FIL_03',   14, 1, 0, 3890,  0, 5, 1, 5, 1, 0),
('Gigot de porc',                 'GIG_02',   14, 1, 0, 3190,  0, 5, 1, 5, 1, 0),
('Créppinettes',                  'CRE_2',    14, 1, 0, 3000,  0, 5, 1, 5, 1, 0);

-- ==========================================
-- CHEVRE (cat 15)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Chèvre entière en kg',  'CHE_01',  15, 1, 0, 3500,  0, 3, 1, 5, 1, 1);

-- ==========================================
-- MOUTON/OVIN (cat 16)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Carcasse de mouton',  'CAR_03',  16, 1, 0, 0,    0, 5, 1, 5, 1, 0),
('Filet d''agneau',      'COT_05',  16, 1, 0, 3690, 0, 5, 1, 5, 1, 0);

-- ==========================================
-- AGNEAU (cat 17)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Collier d''agneau',  'COL_01',  17, 1, 0, 4000,  0, 5, 1, 5, 1, 0),
('Côte d''agneau',     'COT_04',  17, 1, 0, 0,     0, 5, 1, 5, 1, 0),
('Épaule d''agneau',   'EPA_01',  17, 1, 0, 0,     0, 5, 1, 5, 1, 0),
('Gigot d''agneau',    'GIG_01',  17, 1, 0, 0,     0, 5, 1, 5, 1, 0);

-- ==========================================
-- POISSON (cat 18)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Bar ombine / poisson corvina',  'BAR-02',  18, 1, 0, 2500,   0, 5, 1, 2, 1, 0),
('Bar anglais ou congelé',        'BAR_01',  18, 1, 0, 2440,   0, 5, 1, 2, 1, 0),
('Baracuda',                       'BAR_02',  18, 1, 0, 4500,   0, 5, 1, 2, 1, 0),
('Bar frais',                      'BAR_03',  18, 1, 0, 5500,   0, 5, 1, 2, 1, 0),
('Baracuda petit',                 'BAR_04',  18, 1, 0, 3500,   0, 5, 1, 2, 1, 0),
('Bossu',                          'BOS_01',  18, 1, 0, 5000,   0, 5, 1, 2, 1, 0),
('Capitaine gros',                 'CAP_01',  18, 1, 0, 5500,   0, 5, 1, 2, 1, 0),
('Capitaine petit',                'CPA_01',  18, 1, 0, 5000,   0, 5, 1, 2, 1, 0),
('Carpe',                          'CAR_01',  18, 1, 0, 5000,   0, 5, 1, 2, 1, 0),
('Carcasse de poisson',            'CARA',    18, 1, 0, 1000,   0, 5, 1, 2, 1, 0),
('Déchet capitaine',               'DEC_01',  18, 1, 0, 0,      0, 5, 1, 2, 1, 0),
('Disque',                          'DIS_01',  18, 1, 0, 3000,   0, 5, 1, 2, 1, 0),
('Dorade',                          'DOR_01',  18, 1, 0, 4500,   0, 5, 1, 2, 1, 0),
('Filet de sole',                   'FIL-01',  18, 1, 0, 9000,   0, 5, 1, 2, 1, 0),
('Filet de capitaine',              'FIL_01',  18, 1, 0, 9500,   0, 5, 1, 2, 1, 0),
('Friture',                         'FRI_01',  18, 1, 0, 1000,   0, 5, 1, 2, 1, 0),
('Fricassée de poisson',           'FRI_02',  18, 1, 0, 8500,   0, 5, 1, 2, 1, 0),
('Hareng fumé',                    'HAR_01',  18, 1, 0, 0,      0, 5, 1, 2, 1, 0);

-- ==========================================
-- POISSON FUME (cat 19)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Baracuda fumé',  'BAR_05',  19, 1, 0, 7500,  0, 5, 1, 14, 1, 0);

-- ==========================================
-- CRUSTACES (cat 20)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Corvette rose',          'COR_01',   20, 1, 0, 0,     0, 5, 1, 2, 1, 0),
('Crevettes petits',       'CRE-01',   20, 1, 0, 5000,  0, 5, 1, 2, 1, 0),
('Crevettes gros',         'CRE-02',   20, 1, 0, 7500,  0, 5, 1, 2, 1, 0),
('Crevettes décortiquées', 'CREV_01',  20, 1, 0, 0,     0, 5, 1, 2, 1, 0),
('Crevettes',              'CRE_01',   20, 1, 0, 4000,  0, 5, 1, 2, 1, 0),
('Écrevisse',              'ECR_01',   20, 1, 0, 0,     0, 5, 1, 2, 1, 0),
('Gambas',                  'GAM_01',   20, 1, 0, 17000, 0, 5, 1, 2, 1, 0),
('Gambas décortiquées',    'GAM_02',   20, 1, 0, 4490,  0, 5, 1, 2, 1, 0);

-- ==========================================
-- BROCHETTES / PLATS (cat 21)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Brochette de porc',  'BRO_01',  21, 3, 0, 200,  0, 10, 1, 3, 1, 0);

-- ==========================================
-- CHAWARMA / PLATS (cat 22)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Chawarma + 1 petit jus SP',  'CHA-02',  22, 3, 0, 1500,  0, 10, 1, 3, 1, 0),
('Chawarma',                    'CHA_01',  22, 1, 0, 1000,  0, 5,  1, 3, 1, 0);

-- ==========================================
-- EPICERIE - DIVERS (cat 23)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Beta Margarine 900g',   'BUT-1',    23, 3, 0, 2500,   0, 10, 1, 30, 1, 0),
('Nescafé 3 in 1 25g',   'CAF-1',    23, 3, 0, 250,    0, 10, 1, 90, 1, 0),
('Matinal Chocolat 400g', 'CHOC-1',   23, 3, 0, 2100,   0, 10, 1, 90, 1, 0),
('Matinal Chocolat 200g', 'CHOC-2',   23, 3, 0, 1290,   0, 10, 1, 90, 1, 0),
('Cure-dent',             'CUR-1',    23, 3, 0, 100,    0, 10, 0, NULL, 1, 0),
('Signal',                'DENT-1',   23, 3, 0, 350,    0, 10, 0, NULL, 1, 0),
('Signal 123',            'DENT-2',   23, 3, 0, 1200,   0, 10, 0, NULL, 1, 0);

-- ==========================================
-- EPICERIE - EPICES (cat 24)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
-- Épices 50g
('Épices poulet 50g',              'EPIC001',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Paprika doux 50g',              'EPIC002',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Épices viandes 50g',            'EPIC003',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Poivre blanc moulu 50g',        'EPIC004',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Poivre noir moulu 50g',         'EPIC005',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Poivre gris moulu 50g',         'EPIC006',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Herbes de Provence 25g',        'EPIC007',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Thym amandes entier 15g',      'EPIC008',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Thym & lauriers 25g',           'EPIC009',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Épices bouillon 50g',           'EPIC010',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Curry powder 50g',              'EPIC011',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Anis vert 25g',                  'EPIC012',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Noix de muscade 50g',           'EPIC013',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Rondelles moulu 50g',           'EPIC014',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Cannelle moulu 50g',            'EPIC015',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Gingembre moulu 50g',           'EPIC016',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
('Cannelle bâtons 25g',           'EPIC017',  24, 3, 0, 600,   0, 10, 0, NULL, 1, 0),
-- Épices 100g (prix 1000)
('Épices poulet 100g',             'EPIC018',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Épices poisson 100g',            'EPIC019',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Épices viandes 100g',            'EPIC020',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Poivre blanc moulu 100g',        'EPIC021',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Poivre gris moulu 100g',         'EPIC022',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Poivre noir moulu 100g',         'EPIC023',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Rondelles moulu 100g',           'EPIC024',  24, 3, 0, 0,     0, 10, 0, NULL, 1, 0),
('Gingembre moulu 100g',           'EPIC025',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Cannelle moulu 100g',            'EPIC026',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Curcuma safran 100g',            'EPIC027',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Cannelle bâtons 100g',           'EPIC028',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Curry powder 100g',              'EPIC029',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
-- Épices 100g (prix 1500)
('Rondelle moulu',                  'EPIC033',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Ail moulu 100g',                  'EPIC034',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Anis vert grain 100g',           'EPIC035',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Cannelle bâtonnet 100g',         'EPIC036',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Clou de girofle grain 100g',    'EPIC037',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Clou de girofle moulu 100g',    'EPIC038',  24, 3, 0, 2000,  0, 10, 0, NULL, 1, 0),
('Curcuma moulu 100g',             'EPIC039',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Curry 100g',                      'EPIC040',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Djansang grain 100g',            'EPIC041',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Épices bouillons 100g',          'EPIC042',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Épices mbongo 100g',             'EPIC043',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Épices nkui 100g',               'EPIC044',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Épices poulet 100g',             'EPIC045',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Épices poisson 100g',            'EPIC046',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Épices sauce jaune 100g',        'EPIC047',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Fenouil grain 100g',             'EPIC048',  24, 3, 0, 2000,  0, 10, 0, NULL, 1, 0),
('Gingembre moulu 100g',           'EPIC049',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Piment de Cayenne moulu 100g',  'EPIC050',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Piment simple moulu 100g',      'EPIC051',  24, 3, 0, 2000,  0, 10, 0, NULL, 1, 0),
('Poivre blanc moulu 100g',        'EPIC052',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Poivre noir moulu 100g',         'EPIC053',  24, 3, 0, 0,     0, 10, 0, NULL, 1, 0),
('Rondelle moulu 110g',            'EPIC054',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Thym 100g',                       'EPIC055',  24, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Paprika moulu 100g',             'EPIC056',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0),
('Épices viande 100g',             'EPIC057',  24, 3, 0, 1500,  0, 10, 0, NULL, 1, 0);

-- ==========================================
-- BOISSONS (cat 25)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Canette Booster',           'CAN-01',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Malta',             'CAN-02',  25, 3, 0, 500,   0, 10, 0, NULL, 1, 0),
('Canette Mutzig',            'CAN-03',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette 33 Export',         'CAN-04',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Heineken',          'CAN-05',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Castel Beer',       'CAN-06',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Bavaria Originale', 'CAN-07',  25, 3, 0, 800,   0, 10, 0, NULL, 1, 0),
('Canette Vampur Malta',      'CAN-10',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Guinness',          'CAN-11',  25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Isenbeck',          'CAN-8',   25, 3, 0, 1000,  0, 10, 0, NULL, 1, 0),
('Canette Beaufort Light',    'CAN-9',   25, 3, 0, 500,   0, 10, 0, NULL, 1, 0),
('Eau Vitale 0.5L',          'EAU-01',  25, 3, 0, 250,   0, 10, 0, NULL, 1, 0),
('Eau Supermont 0.5L',      'EAU-02',  25, 3, 0, 250,   0, 10, 0, NULL, 1, 0),
('Eau Opur petit',            'EAU-03',  25, 3, 0, 250,   0, 10, 0, NULL, 1, 0),
('Eau Supermont 1.5L',      'EAU-04',  25, 3, 0, 300,   0, 10, 0, NULL, 1, 0),
('Eau minérale Opur 1.5L',   'EAU_05',  25, 3, 0, 300,   0, 10, 0, NULL, 1, 0),
('Eau minérale Vitale 1.5L', 'EAU_06',  25, 3, 0, 250,   0, 10, 0, NULL, 1, 0);

-- ==========================================
-- CHARCUTERIE (cat 26)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Chute de charcuterie',         'CHU_01',  26, 1, 0, 3000,   0, 5, 1, 7, 1, 0),
('Emmental Paysan Breton',       'EMM_01',  26, 1, 0, 12190,  0, 5, 1, 30, 1, 0);

-- ==========================================
-- CONSOMMABLES - EMBALLAGE (cat 27)
-- ==========================================
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Emballage plastique PM',  'EMB-01',  27, 3, 0, 50,   0, 20, 0, NULL, 1, 0),
('Emballage plastique GM',  'EMB-02',  27, 3, 0, 100,  0, 20, 0, NULL, 1, 0);

-- =============================================
-- RÉSUMÉ DE L'IMPORT
-- =============================================
-- Catégories de premier niveau : 8
-- Sous-catégories             : 19
-- Total produits               : ~130
--
-- Produits avec prix manquant (sale_price = 0, à renseigner) :
--   AIL_02  AILERONS DE DINDE
--   BRO-02  BROCHETTE DE POULET
--   GES_01  GESIER DE POULET
--   HAM_01  HAMBURGER POULET
--   BOU_04  BOULETTE PPL
--   COT_07  COTE DE PORC
--   GIT-01  GITE HACHEE DE BOEUF
--   GOR-01  GORGE DE BOEUF
--   CAR_03  CARCASSE DE MOUTON
--   COT_04  COTE D'AGNEAU
--   EPA_01  EPAULE D'AGNEAU
--   GIG_01  GIGOT D'AGNEAU
--   DEC_01  DECHET CAPITAINE
--   HAR_01  HARENG FUME
--   COR_01  CORVETTE ROSE
--   CREV_01 CREVETTES DECORTIQUEES
--   ECR_01  ECREVISSE
--   EPIC024 RONDELLE MOULU 100G
--   EPIC053 POIVRE NOIR MOULU 100G
--
-- Produits "carcasse" (is_carcass = 1, pour découpe) :
--   BOE_01  BOEUF ENTIER EN KG
--   BOE_02  BOEUF ENTIER SUR PIEDS
--   CHE_01  CHEVRE ENTIERE EN KG
--   CAN_01  CANARD ENTIER EN KG
-- =============================================