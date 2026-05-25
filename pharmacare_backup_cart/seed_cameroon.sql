-- ============================================================
-- PharmaCare — Seed Cameroun
-- Données réalistes pour pharmacie au Cameroun (marché CEMAC)
-- Prix en FCFA — Médicaments courants en pharmacie camerounaise
-- Exécuter : mysql -u root pharmacare < seed_cameroon.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE vente_lignes;
TRUNCATE ventes;
TRUNCATE commandes;
TRUNCATE mouvements_stock;
TRUNCATE produits;
TRUNCATE categories;
TRUNCATE fournisseurs;
TRUNCATE role_permissions;
TRUNCATE permissions;
TRUNCATE roles;
TRUNCATE utilisateurs;
TRUNCATE parametres;

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════
-- RÔLES & PERMISSIONS
-- ════════════════════════════════════════════════════════════════

INSERT INTO roles (id, code, libelle, est_systeme) VALUES
(1, 'admin',      'Administrateur', 1),
(2, 'pharmacien', 'Pharmacien',     1),
(3, 'caissier',   'Caissier',       1);

INSERT INTO permissions (id, code, libelle, module) VALUES
(1,  'dashboard.voir',        'Voir le tableau de bord',        'dashboard'),
(2,  'vente.creer',          'Créer des ventes (Point de Vente)',         'vente'),
(3,  'stock.voir',            'Voir le stock',                  'stock'),
(4,  'stock.ajuster',         'Ajuster le stock',               'stock'),
(5,  'produits.voir',         'Voir les médicaments',           'produits'),
(6,  'produits.ajouter',      'Ajouter des médicaments',        'produits'),
(7,  'produits.modifier',     'Modifier des médicaments',      'produits'),
(8,  'produits.archiver',     'Archiver des médicaments',       'produits'),
(9,  'fournisseurs.voir',     'Voir les fournisseurs',          'fournisseurs'),
(10, 'fournisseurs.ajouter',  'Ajouter des fournisseurs',       'fournisseurs'),
(11, 'fournisseurs.modifier', 'Modifier des fournisseurs',     'fournisseurs'),
(12, 'fournisseurs.supprimer','Supprimer des fournisseurs',    'fournisseurs'),
(13, 'commandes.voir',        'Voir les commandes',             'commandes'),
(14, 'commandes.creer',       'Créer des commandes',            'commandes'),
(15, 'commandes.modifier',    'Modifier des commandes',         'commandes'),
(16, 'ventes_hist.voir',      'Voir l''historique des ventes',  'ventes_hist'),
(17, 'rapports.voir',         'Voir les rapports',              'rapports'),
(18, 'utilisateurs.voir',     'Voir les utilisateurs',          'utilisateurs'),
(19, 'utilisateurs.gerer',    'Gérer les utilisateurs',         'utilisateurs'),
(20, 'categories.voir',       'Voir les catégories',            'categories'),
(21, 'categories.gerer',      'Gérer les catégories',            'categories'),
(22, 'parametres.voir',       'Voir les paramètres',            'parametres'),
(23, 'parametres.gerer',      'Gérer les paramètres',            'parametres'),
(24, 'roles.voir',            'Voir les rôles & permissions',   'roles'),
(25, 'roles.gerer',           'Gérer les rôles & permissions',   'roles');

-- Admin : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Pharmacien : 14 permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),
(2,9),
(2,13),(2,14),(2,15),
(2,16),(2,17);

-- Caissier : 5 permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3,1),(3,2),(3,3),(3,16),(3,17);

-- ════════════════════════════════════════════════════════════════
-- UTILISATEURS
-- ════════════════════════════════════════════════════════════════

INSERT INTO utilisateurs (id, nom, prenom, email, login, mot_de_passe, role_id, actif) VALUES
(1, 'Mvondo', 'Jean-Pierre', 'jp.mvondo@pharmacare.cm', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1),
(2, 'Ngassa', 'Marie-Claire', 'mc.ngassa@pharmacare.cm', 'pharmacien', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1),
(3, 'Fotso', 'Emmanuel', 'e.fotso@pharmacare.cm', 'caissier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1);
-- Mot de passe pour tous : password

-- ════════════════════════════════════════════════════════════════
-- CATÉGORIES (12 — adaptées au marché camerounais)
-- ════════════════════════════════════════════════════════════════

INSERT INTO categories (id, nom, couleur) VALUES
(1,  'Antalgiques',                   '#00c9a7'),
(2,  'Antibiotiques',                 '#4895ef'),
(3,  'Anti-inflammatoires',           '#f0b429'),
(4,  'Antipaludiques',                '#ef4444'),
(5,  'Vitamines & Compléments',       '#9b59b6'),
(6,  'Cardiologie & Hypertension',    '#e74c3c'),
(7,  'Diabétologie',                  '#e67e22'),
(8,  'Respiratoire',                  '#1abc9c'),
(9,  'Gastro-entérologie',            '#3498db'),
(10, 'Allergologie',                  '#e91e63'),
(11, 'Dermatologie',                  '#795548'),
(12, 'Antiparasitaires',              '#2ecc71');

-- ════════════════════════════════════════════════════════════════
-- FOURNISSEURS (5 — distributeurs pharmaceutiques camerounais)
-- ════════════════════════════════════════════════════════════════

INSERT INTO fournisseurs (id, nom, contact, telephone, email, adresse, ville, actif) VALUES
(1, 'Copharmacie SA',           'Paul Essomba',   '+237 699 12 34 56', 'appro@copharmacie.cm',    'Bonapriso, Rue Joss',               'Douala',  1),
(2, 'Laborex Cameroun',         'Chantal Atangana','+237 677 98 76 54','info@laborex.cm',          'Akwa, Boulevard de la Liberté',      'Douala',  1),
(3, 'Pharmacie Principale CMR', 'André Nganou',   '+237 655 44 33 22','contact@pharmaprincipale.cm','Bastos, Rue de l''Hôpital',        'Yaoundé', 1),
(4, 'Ubipharm Cameroun',        'Lucie Mbang',    '+237 688 77 66 55','ubipharm@ubipharm.cm',    'Bonabéri, Avenue de la Réunification','Douala', 1),
(5, 'CAMPHARM',                 'Victor Kamga',   '+237 666 11 22 33','campharm@campharm.cm',    'Quartier du Lac, Boulevard Mitterrand','Yaoundé',1);

-- ════════════════════════════════════════════════════════════════
-- MÉDICAMENTS (~65 — Prix en FCFA, médicaments réels vendus au Cameroun)
-- ════════════════════════════════════════════════════════════════

-- ── Antalgiques (catégorie 1) ──────────────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(1,  'Paracétamol 500mg (boîte de 20)',     'MED-001', 1, 1,  280, 30,  180,  300,   '2027-06-30', 1),
(2,  'Paracétamol 1g (boîte de 10)',        'MED-002', 1, 1,  195, 25,  250,  400,   '2027-03-31', 1),
(3,  'Doliprane 1000mg (boîte de 8)',        'MED-003', 1, 2,  140, 20,  420,  650,   '2027-09-30', 1),
(4,  'Doliprane 500mg (boîte de 16)',        'MED-004', 1, 1,  310, 30,  320,  500,   '2027-08-31', 1),
(5,  'Aspirine 500mg (boîte de 20)',         'MED-005', 1, 1,  220, 25,  150,  250,   '2028-01-31', 1),
(6,  'Ibuprofène 400mg (boîte de 20)',       'MED-006', 1, 3,  175, 20,  350,  550,   '2027-05-31', 1),
(7,  'Efferalgan 500mg (boîte de 16)',       'MED-007', 1, 2,  130, 20,  380,  600,   '2027-04-30', 1);

-- ── Antibiotiques (catégorie 2) ────────────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(8,  'Amoxicilline 500mg (boîte de 12)',     'MED-008', 2, 1,  85,  15,  480,  750,   '2026-12-31', 1),
(9,  'Amoxicilline 1g (boîte de 8)',          'MED-009', 2, 2,  60,  15,  650,  1000,  '2026-11-30', 1),
(10, 'Augmentin 1g (boîte de 8)',             'MED-010', 2, 1,  42,  10,  1200, 1850,  '2026-09-30', 1),
(11, 'Ciprofloxacine 500mg (boîte de 10)',   'MED-011', 2, 2,  55,  10,  550,  850,   '2027-02-28', 1),
(12, 'Métronidazole 500mg (boîte de 14)',    'MED-012', 2, 4,  90,  15,  420,  650,   '2027-07-31', 1),
(13, 'Cotrimoxazole 480mg (boîte de 20)',    'MED-013', 2, 3,  150, 20,  280,  450,   '2027-10-31', 1),
(14, 'Doxycycline 100mg (boîte de 10)',      'MED-014', 2, 1,  75,  10,  380,  600,   '2027-01-31', 1),
(15, 'Azithromycine 250mg (boîte de 6)',     'MED-015', 2, 2,  38,  10,  950,  1500,  '2026-10-31', 1),
(16, 'Céfixime 200mg (boîte de 8)',          'MED-016', 2, 4,  28,  10,  1100, 1700,  '2027-03-31', 1),
(17, 'Nitrofurantoïne 100mg (boîte de 14)',  'MED-017', 2, 5,  20,  8,   700,  1100,  '2027-06-30', 1);

-- ── Anti-inflammatoires (catégorie 3) ──────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(18, 'Diclofénac 50mg (boîte de 20)',        'MED-018', 3, 1,  120, 15,  280,  450,   '2027-08-31', 1),
(19, 'Kétoprofène 100mg (boîte de 15)',       'MED-019', 3, 3,  65,  10,  420,  650,   '2027-05-31', 1),
(20, 'Cortisone 20mg (boîte de 20)',          'MED-020', 3, 2,  30,  8,   600,  950,   '2027-04-30', 1),
(21, 'Prednisone 5mg (boîte de 30)',           'MED-021', 3, 4,  45,  10,  350,  550,   '2027-12-31', 1);

-- ── Antipaludiques (catégorie 4 — crucial au Cameroun) ────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(22, 'Coartem (Artéméther/Luméfantrine) 20/120mg (boîte de 24)', 'MED-022', 4, 1, 200, 30,  500,  800,   '2027-06-30', 1),
(23, 'ASAQ (Artésunate/Amodiaquine) 100/270mg (boîte de 24)',    'MED-023', 4, 2, 180, 25,  400,  650,   '2027-09-30', 1),
(24, 'Quinine 500mg (boîte de 20)',                               'MED-024', 4, 3,  95,  15,  350,  550,   '2027-03-31', 1),
(25, 'Malarone (Atovaquone/Proguanil) 250/100mg (boîte de 12)',  'MED-025', 4, 1,  40,  10,  2200, 3500,  '2027-11-30', 1),
(26, 'Fansidar (Sulfadoxine/Pyriméthamine) 500/25mg (boîte de 8)','MED-026', 4, 4, 110, 15,  300,  500,   '2027-01-31', 1),
(27, 'Artésunate 50mg (boîte de 24)',                              'MED-027', 4, 2,  65,  10,  450,  700,   '2026-12-31', 1),
(28, 'Nivaquine (Chloroquine) 100mg (boîte de 30)',               'MED-028', 4, 5, 150, 20,  200,  350,   '2027-07-31', 1),
(29, 'Primaquine 15mg (boîte de 14)',                              'MED-029', 4, 3,  25,  8,   600,  950,   '2027-02-28', 1);

-- ── Vitamines & Compléments (catégorie 5) ─────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(30, 'Vitamine C 1000mg (boîte de 20)',       'MED-030', 5, 1,  320, 30,  200,  350,   '2028-03-31', 1),
(31, 'Vitamine B Complex (boîte de 30)',       'MED-031', 5, 1,  180, 20,  250,  400,   '2028-02-28', 1),
(32, 'Fer/Folate 200mg/400µg (boîte de 30)',   'MED-032', 5, 2,  95,  15,  350,  550,   '2027-10-31', 1),
(33, 'Calcium 500mg + Vitamine D3 (boîte de 30)','MED-033', 5, 3, 140, 15,  450,  700,   '2028-04-30', 1),
(34, 'Multivitamines (boîte de 30)',            'MED-034', 5, 1,  210, 25,  300,  500,   '2028-01-31', 1),
(35, 'Oro Vitamine C (tube de 20)',             'MED-035', 5, 4,  160, 20,  180,  300,   '2027-12-31', 1),
(36, 'Folate 5mg (boîte de 50)',                'MED-036', 5, 5,  120, 15,  120,  200,   '2027-11-30', 1);

-- ── Cardiologie & Hypertension (catégorie 6) ──────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(37, 'Amlodipine 5mg (boîte de 28)',           'MED-037', 6, 1,  110, 15,  500,  800,   '2027-06-30', 1),
(38, 'Atorvastatine 20mg (boîte de 28)',        'MED-038', 6, 2,  75,  10,  800,  1250,  '2027-09-30', 1),
(39, 'Losartan 50mg (boîte de 28)',             'MED-039', 6, 3,  60,  10,  650,  1000,  '2027-03-31', 1),
(40, 'Hydrochlorothiazide 25mg (boîte de 30)',  'MED-040', 6, 4,  90,  12,  350,  550,   '2027-08-31', 1),
(41, 'Captopril 25mg (boîte de 30)',            'MED-041', 6, 1,  80,  10,  450,  700,   '2027-05-31', 1),
(42, 'Aténolol 50mg (boîte de 28)',             'MED-042', 6, 5,  100, 15,  380,  600,   '2027-12-31', 1),
(43, 'Furosémide 40mg (boîte de 20)',           'MED-043', 6, 2,  40,  8,   300,  500,   '2027-07-31', 1);

-- ── Diabétologie (catégorie 7) ─────────────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(44, 'Metformine 500mg (boîte de 30)',         'MED-044', 7, 1,  165, 20,  350,  550,   '2027-04-30', 1),
(45, 'Metformine 850mg (boîte de 30)',         'MED-045', 7, 1,  120, 15,  480,  750,   '2027-06-30', 1),
(46, 'Glibenclamide 5mg (boîte de 30)',        'MED-046', 7, 3,  80,  10,  280,  450,   '2027-09-30', 1),
(47, 'Glicazide 80mg (boîte de 30)',            'MED-047', 7, 2,  55,  10,  550,  850,   '2027-02-28', 1),
(48, 'Insuline Humaine NPH (flacon 10ml)',      'MED-048', 7, 4,  30,  8,   3500, 5500,  '2026-08-31', 1);

-- ── Respiratoire (catégorie 8) ────────────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(49, 'Ventoline (Salbutamol) 100µg inhalateur','MED-049', 8, 1,  42,  10,  1800, 2800,  '2027-11-30', 1),
(50, 'Codéine prométhazine sirop (flacon 125ml)','MED-050', 8, 2, 70, 15, 350, 550,   '2027-05-31', 1),
(51, 'Ambroxol 30mg (boîte de 20)',            'MED-051', 8, 3,  125, 15,  250,  400,   '2027-10-31', 1),
(52, 'Célestamine (Bétaméthasone) (boîte de 20)','MED-052', 8, 4, 35, 8,  480, 750,   '2027-03-31', 1),
(53, 'Rhinadvil (Ibuprofène/Pseudoéphédrine) (boîte de 12)','MED-053', 8, 1, 90, 12, 400, 650, '2027-08-31', 1);

-- ── Gastro-entérologie (catégorie 9) ──────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(54, 'Oméprazole 20mg (boîte de 14)',          'MED-054', 9, 1,  165, 20,  320,  500,   '2027-07-31', 1),
(55, 'Smecta (Diosmectite) sachet x10',        'MED-055', 9, 2,  200, 25,  280,  450,   '2027-09-30', 1),
(56, 'Imodium (Lopéramide) 2mg (boîte de 12)', 'MED-056', 9, 3,  85,  10,  450,  700,   '2027-04-30', 1),
(57, 'Pantoprazole 40mg (boîte de 14)',        'MED-057', 9, 4,  60,  10,  550,  850,   '2027-12-31', 1),
(58, 'Flagyl (Métronidazole) 250mg (boîte de 20)','MED-058', 9, 5, 95, 12, 380, 600,  '2027-01-31', 1),
(59, 'Polysilane (flacon 250ml)',               'MED-059', 9, 1,  75,  10,  350,  550,   '2027-06-30', 1);

-- ── Allergologie (catégorie 10) ───────────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(60, 'Xyzall (Lévocétirizine) 5mg (boîte de 15)','MED-060', 10, 1, 55, 10, 650, 1000, '2027-10-31', 1),
(61, 'Polaramine (Dexchlorphéniramine) 2mg (boîte de 20)','MED-061', 10, 2, 80, 12, 280, 450, '2027-05-31', 1),
(62, 'Aerius (Desloratadine) 5mg (boîte de 10)','MED-062', 10, 3, 40, 8,  750, 1200, '2027-03-31', 1),
(63, 'Béclométhasone spray nasal (flacon 200 doses)','MED-063', 10, 4, 25, 8, 1800, 2800, '2027-08-31', 1);

-- ── Dermatologie (catégorie 11) ───────────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(64, 'Hydrocortisone crème 1% (tube 15g)',     'MED-064', 11, 1,  70,  10,  250,  400,   '2027-09-30', 1),
(65, 'Bétadine (Povidone iodée) solution 100ml', 'MED-065', 11, 2,  120, 15,  350,  550,   '2027-11-30', 1),
(66, 'Miconazole crème 2% (tube 15g)',          'MED-066', 11, 3,  55,  10,  280,  450,   '2027-06-30', 1),
(67, 'Clobétasol crème 0,05% (tube 15g)',       'MED-067', 11, 4,  30,  8,   650,  1000,  '2027-04-30', 1),
(68, 'Acide fusidique crème 2% (tube 15g)',     'MED-068', 11, 5,  45,  10,  400,  650,   '2027-12-31', 1);

-- ── Antiparasitaires (catégorie 12) ───────────────────────────
INSERT INTO produits (id, nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration, actif) VALUES
(69, 'Albendazole 400mg (boîte de 4)',         'MED-069', 12, 1,  130, 15,  200,  350,   '2027-08-31', 1),
(70, 'Mébendazole 100mg (boîte de 6)',          'MED-070', 12, 2,  110, 15,  180,  300,   '2027-05-31', 1),
(71, 'Ivermectine 3mg (boîte de 4)',             'MED-071', 12, 3,  40,  8,   600,  950,   '2027-10-31', 1),
(72, 'Praziquantel 600mg (boîte de 4)',          'MED-072', 12, 4,  25,  8,   550,  900,   '2027-02-28', 1),
(73, 'Niclosamide 500mg (boîte de 6)',           'MED-073', 12, 5,  35,  8,   300,  500,   '2027-07-31', 1);

-- ════════════════════════════════════════════════════════════════
-- COMMANDES FOURNISSEURS (données démo)
-- ════════════════════════════════════════════════════════════════

INSERT INTO commandes (id, reference, fournisseur_id, utilisateur_id, montant_total, statut, date_commande, date_livraison) VALUES
(1, 'CMD-2026-001', 1, 1, 185000, 'livrée',    '2026-03-15', '2026-03-22'),
(2, 'CMD-2026-002', 2, 2, 95000,  'en_cours',  '2026-04-20', NULL),
(3, 'CMD-2026-003', 4, 1, 62000,  'en_attente','2026-05-01', NULL);

-- ════════════════════════════════════════════════════════════════
-- VENTES DÉMO (TVA 19,25 %)
-- ════════════════════════════════════════════════════════════════

INSERT INTO ventes (id, reference, client_nom, client_telephone, caissier_id, sous_total, tva_total, total, mode_paiement, montant_recu, monnaie, created_at) VALUES
(1, 'VNT-2026-001', 'Mme Fouda',             '+237 699 11 22 33', 3, 2150.00, 413.88, 2563.88, 'espèces',   3000.00, 436.12,  DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(2, 'VNT-2026-002', 'M. Nkoulou',            '+237 677 44 55 66', 3, 4250.00, 818.13, 5068.13, 'carte',     5068.13, 0.00,    DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(3, 'VNT-2026-003', '',                       '',                   2, 1000.00, 192.50, 1192.50, 'espèces',   1200.00, 7.50,    DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(4, 'VNT-2026-004', 'Mme Biyong',            '+237 655 77 88 99', 2, 3800.00, 731.50, 4531.50, 'assurance', 0.00,    0.00,    DATE_SUB(NOW(), INTERVAL 1 HOUR));

INSERT INTO vente_lignes (id, vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne) VALUES
(1,  1, 1,  'Paracétamol 500mg (boîte de 20)',      3,  300.00,  19.25, 900.00),
(2,  1, 22, 'Coartem (Artéméther/Luméfantrine) 20/120mg (boîte de 24)', 1, 800.00, 19.25, 800.00),
(3,  1, 55, 'Smecta (Diosmectite) sachet x10',      2,  450.00,  19.25, 900.00),
(4,  2,  8, 'Amoxicilline 500mg (boîte de 12)',     2,  750.00,  19.25, 1500.00),
(5,  2, 38, 'Atorvastatine 20mg (boîte de 28)',     1,  1250.00, 19.25, 1250.00),
(6,  2, 49, 'Ventoline (Salbutamol) 100µg inhalateur',1, 2800.00, 19.25, 2800.00),
(7,  3, 44, 'Metformine 500mg (boîte de 30)',       2,  550.00,  19.25, 1100.00),
(8,  4, 10, 'Augmentin 1g (boîte de 8)',             1,  1850.00, 19.25, 1850.00),
(9,  4, 30, 'Vitamine C 1000mg (boîte de 20)',      3,  350.00,  19.25, 1050.00),
(10, 4, 37, 'Amlodipine 5mg (boîte de 28)',          1,  800.00,  19.25, 800.00);

-- ════════════════════════════════════════════════════════════════
-- MOUVEMENTS DE STOCK (entrées liées aux commandes livrées)
-- ════════════════════════════════════════════════════════════════

INSERT INTO mouvements_stock (produit_id, type, quantite, motif, utilisateur_id, created_at) VALUES
(1,  'entrée', 100, 'Commande CMD-2026-001', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(22, 'entrée', 50,  'Commande CMD-2026-001', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(8,  'entrée', 50,  'Commande CMD-2026-001', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(55, 'entrée', 60,  'Commande CMD-2026-001', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(30, 'entrée', 80,  'Commande CMD-2026-001', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(1,  'sortie',  3,  'Vente VNT-2026-001', 3, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(22, 'sortie',  1,  'Vente VNT-2026-001', 3, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(55, 'sortie',  2,  'Vente VNT-2026-001', 3, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(8,  'sortie',  2,  'Vente VNT-2026-002', 3, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(38, 'sortie',  1,  'Vente VNT-2026-002', 3, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(49, 'sortie',  1,  'Vente VNT-2026-002', 3, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(44, 'sortie',  2,  'Vente VNT-2026-003', 2, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(10, 'sortie',  1,  'Vente VNT-2026-004', 2, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(30, 'sortie',  3,  'Vente VNT-2026-004', 2, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(37, 'sortie',  1,  'Vente VNT-2026-004', 2, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- ════════════════════════════════════════════════════════════════
-- PARAMÈTRES
-- ════════════════════════════════════════════════════════════════

INSERT INTO parametres (cle, valeur) VALUES
('app_nom',          'PharmaCare'),
('tva',              '19.25'),
('devise_symbole',   'FCFA'),
('devise_pos',       'after'),
('theme',            'dark-violet'),
('police',           'DM Sans'),
('police_titre',     'Cormorant Garamond');