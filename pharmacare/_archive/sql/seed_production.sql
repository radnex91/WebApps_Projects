-- ════════════════════════════════════════════════════════════
-- SEED PRODUCTION — PharmaCare (script unique, relançable sur base vierge)
--
-- Vide les données métier (utilisateurs/produits/ventes/…) et re-remplit avec :
--   • 5 utilisateurs (SAID directeur, Baoudi & Nancy caissiers,
--     Gerard régisseur de recette, Rachid informaticien)
--   • 3 rôles dédiés (directeur, informaticien, régisseur)
--   • Fournisseurs = distributeurs pharmaceutiques camerounais réels
--   • 37 articles aux prix CENAME (pharmacie d'hôpital, Cameroun)
--
-- PRÉREQUIS : base `pharmacare` avec le schéma courant (rôles/permissions/
--   catégories/pharmacies/plan comptable déjà en place — NON touchés).
-- EXÉCUTION : mysql -u root --default-character-set=utf8mb4 pharmacare < seed_production.sql
--   (l'option charset est OBLIGATOIRE pour préserver les accents).
-- SAUVEGARDE : mysqldump préalable recommandé.
-- MOT DE PASSE INITIAL (tous comptes) : PharmaCare@2026  → à changer.
--
-- NOTE : les rôles admin/pharmacien/caissier/manager/superviseur et les
--   catégories existantes sont CONSERVÉS. Ce script n'est PAS idempotent
--   sur les rôles dédiés (doublon si relancé) — à exécuter une fois.
-- ════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Vidage des données métier (schéma conservé) ───────────
TRUNCATE TABLE audit_log;
TRUNCATE TABLE retour_vente_lignes;
TRUNCATE TABLE retours_vente;
TRUNCATE TABLE reglements;
TRUNCATE TABLE vente_lignes;
TRUNCATE TABLE ventes;
TRUNCATE TABLE commande_lignes;
TRUNCATE TABLE commandes;
TRUNCATE TABLE transfert_lignes;
TRUNCATE TABLE transferts_magasin;
TRUNCATE TABLE mouvements_magasin;
TRUNCATE TABLE mouvements_stock;
TRUNCATE TABLE mouvements_caisse;
TRUNCATE TABLE sessions_caisse;
TRUNCATE TABLE ecriture_lignes;
TRUNCATE TABLE ecritures;
TRUNCATE TABLE fidelite_points;
TRUNCATE TABLE promo_produits;
TRUNCATE TABLE campagnes_promo;
TRUNCATE TABLE codes_remise;
TRUNCATE TABLE remise_approbateurs;
TRUNCATE TABLE clients;
TRUNCATE TABLE produit_pharmacie;
TRUNCATE TABLE produits;
TRUNCATE TABLE fournisseurs;
TRUNCATE TABLE utilisateurs;

SET FOREIGN_KEY_CHECKS = 1;

-- ── 2. Compteurs de références : remise à zéro (refs propres) ──
UPDATE compteurs_ref SET compteur = 0 WHERE annee = YEAR(CURDATE());

-- ════════════════════════════════════════════════════════════
-- 3. RÔLES dédiés (directeur / informaticien / régisseur)
-- ════════════════════════════════════════════════════════════
INSERT INTO roles (code, libelle, est_systeme) VALUES ('directeur',     'Directeur de la pharmacie', 0);
SET @r_dir  = LAST_INSERT_ID();
INSERT INTO roles (code, libelle, est_systeme) VALUES ('informaticien', 'Informaticien', 0);
SET @r_info = LAST_INSERT_ID();
INSERT INTO roles (code, libelle, est_systeme) VALUES ('regisseur',     'Régisseur de recette', 0);
SET @r_reg  = LAST_INSERT_ID();

-- Directeur & Informaticien : toutes les permissions (accès complet).
INSERT INTO role_permissions (role_id, permission_id) SELECT @r_dir,  id FROM permissions;
INSERT INTO role_permissions (role_id, permission_id) SELECT @r_info, id FROM permissions;

-- Régisseur de recette : caisse + rapports + compta (lecture) + magasin (lecture).
INSERT INTO role_permissions (role_id, permission_id)
SELECT @r_reg, id FROM permissions
WHERE code IN (
    'dashboard.voir', 'vente.creer', 'stock.voir',
    'ventes_hist.voir', 'rapports.voir',
    'caisse.voir', 'caisse.gerer', 'caisse.ouvrir',
    'comptabilite.voir', 'magasin.voir',
    'clients.voir', 'clients.ajouter', 'clients.paiements', 'retours.gerer'
);

-- ════════════════════════════════════════════════════════════
-- 4. UTILISATEURS (mot de passe initial : PharmaCare@2026)
-- ════════════════════════════════════════════════════════════
INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id, actif) VALUES
('SAID',   '', 'said@pharmacare.cm',   'said',   '$2y$10$f8oeTCPPgO479L0dwBUAfeY74xw5Lb7LOMUBVAVhJPZFyzMkYSLee', @r_dir,  1),
('Baoudi', '', 'baoudi@pharmacare.cm', 'baoudi', '$2y$10$f8oeTCPPgO479L0dwBUAfeY74xw5Lb7LOMUBVAVhJPZFyzMkYSLee', 3,       1),
('Nancy',  '', 'nancy@pharmacare.cm',  'nancy',  '$2y$10$f8oeTCPPgO479L0dwBUAfeY74xw5Lb7LOMUBVAVhJPZFyzMkYSLee', 3,       1),
('Gerard', '', 'gerard@pharmacare.cm', 'gerard', '$2y$10$f8oeTCPPgO479L0dwBUAfeY74xw5Lb7LOMUBVAVhJPZFyzMkYSLee', @r_reg,  1),
('Rachid', '', 'rachid@pharmacare.cm', 'rachid', '$2y$10$f8oeTCPPgO479L0dwBUAfeY74xw5Lb7LOMUBVAVhJPZFyzMkYSLee', @r_info, 1);

SET @u_said = (SELECT id FROM utilisateurs WHERE login = 'said');

-- Approbateurs de remise = comptes avec remise.approuver (SAID + Rachid).
INSERT INTO remise_approbateurs (utilisateur_id, added_by)
SELECT u.id, @u_said FROM utilisateurs u
JOIN role_permissions rp ON rp.role_id = u.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE p.code = 'remise.approuver' AND u.actif = 1;

-- ════════════════════════════════════════════════════════════
-- 5. FOURNISSEURS — distributeurs pharmaceutiques camerounais
-- ════════════════════════════════════════════════════════════
INSERT INTO fournisseurs (nom, contact, telephone, email, ville) VALUES
('CENAME',             'Service commercial', '+237 222 20 60 00', 'contact@cename.cm',     'Yaoundé'),
('Laborex Cameroun',    'Service commercial', '+237 233 42 30 00', 'contact@laborex.cm',    'Douala'),
('Ubapharm',            'Service commercial', '+237 233 42 11 11', 'contact@ubapharm.cm',   'Douala'),
('Medipro Cameroun',    'Service commercial', '+237 222 23 45 67', 'contact@medipro.cm',    'Yaoundé'),
('Sipharm',             'Service commercial', '+237 233 43 22 22', 'contact@sipharm.cm',    'Douala');

-- ════════════════════════════════════════════════════════════
-- 6. PRODUITS — prix basés sur le catalogue CENAME (FCFA)
--    prix_achat ≈ prix unitaire CENAME ; prix_vente = revente pharmacie
--    d'hôpital (marge réglementée). Catégories IDs réels :
--    1=Antalgiques 2=Antibiotiques 3=Anti-inflammatoires 4=Antipaludiques
--    5=Vitamines 6=Cardiologie 7=Diabétologie 8=Respiratoire 9=Gastro
--    10=Allergologie 11=Dermatologie 12=Antiparasitaires
-- ════════════════════════════════════════════════════════════
INSERT INTO produits (nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, stock_magasin, seuil_magasin, prix_achat, prix_vente, date_expiration) VALUES
-- Antalgiques (1)
('Paracétamol 500mg (comprimé)',            'MED-001', 1, 1, 320, 30, 1200, 100,  11.00,   25.00, '2027-12-31'),
('Paracétamol 1g (comprimé)',               'MED-002', 1, 1, 210, 30,  900, 100,  20.00,   50.00, '2027-06-30'),
('Acide acétylsalicylique 500mg (Aspirine)','MED-003', 1, 1, 150, 30,  600,  80,  10.00,   25.00, '2027-09-30'),
-- Antibiotiques (2)
('Amoxicilline 500mg (comprimé)',           'MED-004', 2, 1, 180, 30,  700,  80,  29.00,   60.00, '2027-03-31'),
('Amoxicilline 250mg (gélule)',             'MED-005', 2, 2, 200, 30,  800,  80,  25.00,   50.00, '2027-05-31'),
('Cotrimoxazole 480mg (Biseptol)',          'MED-006', 2, 2, 140, 25,  500,  60,  15.00,   35.00, '2027-08-31'),
('Ciprofloxacine 500mg (comprimé)',         'MED-007', 2, 2,  90, 20,  300,  50,  51.00,  100.00, '2027-07-31'),
('Azithromycine 500mg (comprimé)',          'MED-008', 2, 3,  60, 15,  200,  40, 230.00,  400.00, '2027-04-30'),
('Ceftriaxone 1g (injection)',              'MED-009', 2, 3,  40, 10,  150,  30, 536.00,  900.00, '2027-10-31'),
('Métronidazole 500mg (comprimé)',          'MED-010', 2, 2, 120, 25,  400,  60,  20.00,   45.00, '2027-11-30'),
-- Anti-inflammatoires (3)
('Ibuprofène 400mg (comprimé)',             'MED-011', 3, 1, 160, 25,  600,  70,  15.00,   35.00, '2027-09-30'),
('Diclofénac 50mg (comprimé)',              'MED-012', 3, 3, 130, 25,  450,  60,   9.00,   25.00, '2027-08-31'),
-- Antipaludiques (4)
('Artéméther+Luméfantrine 20/120 (Coartem) boîte 24', 'MED-013', 4, 4, 80, 15, 250, 40,1000.00, 1500.00, '2027-12-31'),
('Sulfadoxine-Pyriméthamine (Fansidar)',    'MED-014', 4, 4, 100, 20,  300,  50, 200.00,  350.00, '2027-06-30'),
('Quinine sulfate 500mg (comprimé)',        'MED-015', 4, 4,  70, 15,  220,  40,  50.00,  100.00, '2027-05-31'),
('Amodiaquine 200mg (comprimé)',            'MED-016', 4, 4, 110, 20,  350,  50,  30.00,   60.00, '2027-09-30'),
-- Vitamines & Compléments (5)
('Vitamine C 1000mg (comprimé)',            'MED-033', 5, 1, 250, 30,  800,  80,  30.00,   55.00, '2028-06-30'),
('Fer + Acide folique (comprimé)',          'MED-034', 5, 1, 180, 25,  600,  70,  15.00,   35.00, '2027-12-31'),
('Multivitamines (comprimé)',               'MED-035', 5, 3, 160, 25,  500,  60,  25.00,   50.00, '2028-03-31'),
-- Cardiologie & Hypertension (6)
('Amlodipine 5mg (comprimé)',               'MED-017', 6, 5, 150, 25,  500,  70,  42.00,   80.00, '2027-10-31'),
('Captopril 25mg (comprimé)',               'MED-018', 6, 5, 140, 25,  450,  60,  34.00,   65.00, '2027-07-31'),
('Furosémide 40mg (comprimé)',              'MED-019', 6, 5, 120, 25,  400,  60,   8.00,   20.00, '2027-12-31'),
('Atenolol 100mg (comprimé)',               'MED-020', 6, 5, 100, 20,  350,  50,  15.00,   35.00, '2027-08-31'),
('Hydrochlorothiazide 25mg (comprimé)',     'MED-021', 6, 5,  90, 20,  300,  50,  10.00,   25.00, '2027-11-30'),
-- Diabétologie (7)
('Metformine 500mg (comprimé)',             'MED-022', 7, 1, 200, 30,  700,  80,  11.00,   25.00, '2027-09-31'),
('Glibenclamide 5mg (comprimé)',            'MED-023', 7, 5, 160, 25,  500,  60,   8.00,   20.00, '2027-12-31'),
-- Respiratoire (8)
('Salbutamol 100µg (inhalateur)',           'MED-024', 8, 3,  35, 10,  120,  30, 280.00,  500.00, '2027-06-30'),
-- Gastro-entérologie (9)
('Oméprazole 20mg (comprimé)',              'MED-025', 9, 2, 110, 20,  400,  60,  70.00,  120.00, '2027-05-31'),
('Smecta (Diosmectite) sachet',             'MED-026', 9, 4,  90, 20,  300,  50,  40.00,   68.00, '2027-08-31'),
('SRO (Sérothérapie Orale) sachet',         'MED-027', 9, 1, 200, 30,  600,  80, 150.00,  300.00, '2027-12-31'),
('Métoclopramide 10mg (comprimé)',          'MED-028', 9, 2,  80, 20,  250,  40,  15.00,   35.00, '2027-10-31'),
-- Allergologie (10)
('Loratadine 10mg (comprimé)',              'MED-029',10, 3, 120, 20,  400,  60,  34.00,   60.00, '2027-09-30'),
-- Dermatologie (11)
('Pommade Bétaméthasone 0,1% (tube)',       'MED-030',11, 3,  45, 10,  150,  30, 200.00,  350.00, '2027-07-31'),
('Gentamicine pommade (tube)',              'MED-031',11, 4,  50, 10,  160,  30, 150.00,  280.00, '2027-08-31'),
('Bétadine (Povidone iodée 10%) solution',  'MED-032',11, 4,  60, 10,  200,  30, 500.00,  800.00, '2027-12-31'),
-- Antiparasitaires (12)
('Albendazole 400mg (comprimé)',            'MED-036',12, 2, 100, 20,  350,  50,  46.00,   80.00, '2027-11-30'),
('Mébendazole 100mg (comprimé)',            'MED-037',12, 4,  90, 20,  300,  50,  20.00,   40.00, '2027-10-31');

-- ── 7. Stock pharmacie principale (id 1) = produits.stock ────
INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
SELECT p.id, 1, p.stock, p.seuil_alerte FROM produits p;

-- ════════════════════════════════════════════════════════════
-- Vérification post-exécution
-- ════════════════════════════════════════════════════════════
-- SELECT u.login, u.nom, r.libelle AS role FROM utilisateurs u JOIN roles r ON r.id=u.role_id ORDER BY u.id;
-- SELECT COUNT(*) AS nb_produits FROM produits;
-- SELECT c.nom, COUNT(*) AS nb FROM produits p JOIN categories c ON c.id=p.categorie_id GROUP BY c.nom ORDER BY nb DESC;