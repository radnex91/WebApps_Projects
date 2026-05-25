-- ============================================
-- Migration : Données quincaillerie
-- Remplace les données de test par des données réelles de quincaillerie
-- ============================================

USE stockpro;

-- 1. Nettoyage des anciennes données
DELETE FROM alertes;
DELETE FROM mouvements;
DELETE FROM produits;
DELETE FROM categories;
DELETE FROM fournisseurs;
-- Supprimer la 2e entrée entreprise dupliquée
DELETE FROM entreprise WHERE id > 1;
-- Mettre à jour l'entreprise existante
UPDATE entreprise SET
  nom = 'RADNEX Quincaillerie',
  slogan = 'Votre partenaire en quincaillerie & outillage',
  adresse = 'Quartier Commercial, Douala, Cameroun',
  telephone = '+237 699 123 456',
  email = 'contact@radnex.cm'
WHERE id = 1;

-- 2. Catégories quincaillerie
INSERT INTO categories (id, nom, description, couleur, icone) VALUES
(1, 'Plomberie', 'Tuyaux, robinets, raccords et sanitaires', '#3b82f6', 'droplet'),
(2, 'Électricité', 'Câbles, disjoncteurs, interrupteurs et éclairage', '#f59e0b', 'zap'),
(3, 'Peinture & Décoration', 'Peintures, vernis, pinceaux et revêtements', '#8b5cf6', 'palette'),
(4, 'Outillage', 'Outils à main, outils de mesure et équipements', '#ef4444', 'wrench'),
(5, 'Visserie & Boulonnerie', 'Vis, clous, boulons, écrous et chevilles', '#10b981', 'link'),
(6, 'Serrurerie', 'Serrures, cadenas, poignées et cylindres', '#6366f1', 'lock'),
(7, 'Quincaillerie générale', 'Accessoires divers, colles, étanchéité et fixation', '#ec4899', 'box');

-- 3. Fournisseurs quincaillerie
INSERT INTO fournisseurs (id, nom, contact, telephone, email, adresse) VALUES
(1, 'Cameroun Industriel SA', 'Emmanuel Fotso', '+237 699 000 001', 'commandes@camindust.cm', 'Zone Industrielle, Douala'),
(2, 'Quincallerie du Littoral', 'José Mbang', '+237 677 000 002', 'ventes@quinca-littoral.cm', 'Marché Central, Douala'),
(3, 'Pro Bâtiment Cameroun', 'Fatou Ndi', '+237 655 000 003', 'appro@probatiment.cm', 'Bonneabang, Douala'),
(4, 'Afrique Outillage Express', 'Karim Ousmanou', '+237 666 000 004', 'contact@aoe.cm', 'Akwa, Douala');

-- 4. Produits quincaillerie
INSERT INTO produits (id, reference, nom, description, categorie_id, fournisseur_id, prix_achat, prix_vente, quantite, quantite_min, unite) VALUES
-- Plomberie
(1,  'PLM-001', 'Tuyau PVC 40mm', 'Tuyau PVC pression 40mm, longueur 4m', 1, 1, 2800, 4500, 85, 20, 'pcs'),
(2,  'PLM-002', 'Robinet mélangeur chromé', 'Robinet mélangeur lavabo chromé, marque Standard', 1, 2, 12500, 19500, 18, 5, 'pcs'),
(3,  'PLM-003', 'Raccord PVC T 40mm', 'Raccord en T diamètre 40mm', 1, 1, 350, 600, 150, 30, 'pcs'),
(4,  'PLM-004', 'Flexible inox 50cm', 'Flexible de raccordement inox 50cm avec écrous', 1, 2, 3500, 5500, 42, 10, 'pcs'),
(5,  'PLM-005', 'Colle PVC 250ml', 'Colle et joint pour tuyaux PVC, pot 250ml', 1, 1, 1800, 3000, 30, 10, 'pcs'),

-- Électricité
(6,  'ELC-001', 'Câble 2.5mm² (rouleau 100m)', 'Câble souple 2.5mm² cuivre, rouleau 100m', 2, 3, 22000, 35000, 12, 5, 'rouleau'),
(7,  'ELC-002', 'Disjoncteur 32A', 'Disjoncteur modulaire 32A, courbe C', 2, 3, 5500, 8500, 24, 8, 'pcs'),
(8,  'ELC-003', 'Interrupteur simple blanc', 'Interrupteur va-et-vient blanc encastrable', 2, 1, 800, 1500, 120, 25, 'pcs'),
(9,  'ELC-004', 'Ampoule LED 12W', 'Ampoule LED E27 12W blanc chaud 6500K', 2, 3, 1500, 2500, 200, 50, 'pcs'),
(10, 'ELC-005', 'Prise murale double', 'Prise murale double 16A blanche encastrable', 2, 1, 1200, 2000, 75, 15, 'pcs'),

-- Peinture & Décoration
(11, 'PNT-001', 'Peinture glycéro blanche 20L', 'Peinture glycéro brillante blanche, seau 20L', 3, 4, 28000, 42000, 8, 3, 'pcs'),
(12, 'PNT-002', 'Peinture acrylique couleur 5L', 'Peinture acrylique mate, palette 24 couleurs', 3, 2, 9500, 14500, 22, 8, 'pcs'),
(13, 'PNT-003', 'Rouleau peinture 25cm', 'Rouleau laqueur 25cm avec manche', 3, 4, 2500, 4000, 35, 10, 'pcs'),
(14, 'PNT-004', 'Pinceau plat 60mm', 'Pinceau plat professionnel 60mm soie mixte', 3, 2, 1800, 3000, 48, 15, 'pcs'),
(15, 'PNT-005', 'Diluant 5L', 'Diluant synthétique pour peinture glycéro', 3, 4, 5500, 8500, 15, 5, 'pcs'),

-- Outillage
(16, 'OUT-001', 'Marteau menuisier 300g', 'Marteau à manche bois 300g, tête acier forgé', 4, 4, 4500, 7500, 30, 8, 'pcs'),
(17, 'OUT-002', 'Scie à métaux', 'Scie à métaux avec 3 lames de rechange', 4, 4, 5000, 8000, 20, 5, 'pcs'),
(18, 'OUT-003', 'Tournevis cruciforme PH2', 'Jeu de 6 tournevis isolés VDE 1000V', 4, 3, 8000, 13000, 15, 5, 'pcs'),
(19, 'OUT-004', 'Mètre ruban 5m', 'Mètre ruban 5m avec blocage, largeur 25mm', 4, 1, 2500, 4000, 55, 15, 'pcs'),
(20, 'OUT-005', 'Niveau à bulle 60cm', 'Niveau à bulle 3 fioles 60cm aluminium', 4, 4, 7000, 11000, 18, 5, 'pcs'),

-- Visserie & Boulonnerie
(21, 'VIS-001', 'Vis à bois 5x50mm (boîte 200)', 'Vis à bois acier zingué tête cruciforme, boîte 200', 5, 1, 3500, 5500, 40, 10, 'boîte'),
(22, 'VIS-002', 'Cheville nylon 8mm (lot 100)', 'Cheville nylon 8mm pour béton et brique', 5, 2, 2000, 3500, 65, 15, 'lot'),
(23, 'VIS-003', 'Boulon hexagonal M10x60', 'Boulon hexagonal M10x60 acier zingué, lot de 50', 5, 1, 4000, 6500, 25, 8, 'lot'),
(24, 'VIS-004', 'Clous acier 50mm (boîte 500g)', 'Clous acier 50mm tête plate', 5, 2, 1500, 2800, 55, 15, 'boîte'),
(25, 'VIS-005', 'Écrou M10 (lot 100)', 'Écrou hexagonal M10 acier zingué, lot de 100', 5, 1, 2000, 3500, 35, 10, 'lot'),

-- Serrurerie
(26, 'SER-001', 'Serrure encastrable 3 points', 'Serrure encastrable 3 points à clé, finition laiton', 6, 3, 15000, 25000, 10, 3, 'pcs'),
(27, 'SER-002', 'Cadenas laiton 50mm', 'Cadenas laiton 50mm avec 3 clés', 6, 2, 3500, 5500, 40, 10, 'pcs'),
(28, 'SER-003', 'Poignée de porte inox', 'Poignée de porte en U inox brossé avec plaque', 6, 3, 8000, 13500, 20, 5, 'pcs'),
(29, 'SER-004', 'Cylindre européen 30x30', 'Cylindre européen 30x30 avec 5 clés', 6, 4, 6000, 10000, 16, 5, 'pcs'),

-- Quincaillerie générale
(30, 'QG-001', 'Colle néoprène 500ml', 'Colle néoprène contact pot 500ml', 7, 2, 3000, 5000, 38, 10, 'pcs'),
(31, 'QG-002', 'Mastic silicone transparent', 'Mastic silicone étanchéité cartouche 310ml', 7, 1, 2500, 4000, 45, 12, 'pcs'),
(32, 'QG-003', 'Ruban adhésif tissu 50m', 'Ruban adhésif tissu 50m×50mm, couleur argent', 7, 2, 1800, 3000, 55, 15, 'pcs'),
(33, 'QG-004', 'Agrafeuse murale', 'Agrafeuse murale avec 1000 agrafes', 7, 4, 4500, 7500, 14, 5, 'pcs'),
(34, 'QG-005', 'Cordelette nylon 30m', 'Cordelette nylon tressé 4mm, rouleau 30m', 7, 1, 1500, 2800, 28, 8, 'pcs');

-- 5. Mouvements de stock réalistes
INSERT INTO mouvements (produit_id, utilisateur_id, type, quantite, quantite_avant, quantite_apres, motif) VALUES
-- Entrées initiales
(1,  1, 'entree', 100, 0, 100, 'Stock initial réceptionné'),
(2,  1, 'entree', 20,  0, 20,  'Stock initial réceptionné'),
(3,  1, 'entree', 200, 0, 200, 'Stock initial réceptionné'),
(4,  1, 'entree', 50,  0, 50,  'Stock initial réceptionné'),
(5,  1, 'entree', 40,  0, 40,  'Stock initial réceptionné'),
(6,  1, 'entree', 15,  0, 15,  'Stock initial réceptionné'),
(7,  1, 'entree', 30,  0, 30,  'Stock initial réceptionné'),
(8,  1, 'entree', 150, 0, 150, 'Stock initial réceptionné'),
(9,  1, 'entree', 250, 0, 250, 'Stock initial réceptionné'),
(10, 1, 'entree', 100, 0, 100, 'Stock initial réceptionné'),
(11, 1, 'entree', 10,  0, 10,  'Stock initial réceptionné'),
(12, 1, 'entree', 30,  0, 30,  'Stock initial réceptionné'),
(13, 1, 'entree', 50,  0, 50,  'Stock initial réceptionné'),
(14, 1, 'entree', 60,  0, 60,  'Stock initial réceptionné'),
(15, 1, 'entree', 20,  0, 20,  'Stock initial réceptionné'),
(16, 1, 'entree', 40,  0, 40,  'Stock initial réceptionné'),
(17, 1, 'entree', 25,  0, 25,  'Stock initial réceptionné'),
(18, 1, 'entree', 20,  0, 20,  'Stock initial réceptionné'),
(19, 1, 'entree', 70,  0, 70,  'Stock initial réceptionné'),
(20, 1, 'entree', 25,  0, 25,  'Stock initial réceptionné'),
(21, 1, 'entree', 50,  0, 50,  'Stock initial réceptionné'),
(22, 1, 'entree', 80,  0, 80,  'Stock initial réceptionné'),
(23, 1, 'entree', 30,  0, 30,  'Stock initial réceptionné'),
(24, 1, 'entree', 70,  0, 70,  'Stock initial réceptionné'),
(25, 1, 'entree', 45,  0, 45,  'Stock initial réceptionné'),
(26, 1, 'entree', 15,  0, 15,  'Stock initial réceptionné'),
(27, 1, 'entree', 50,  0, 50,  'Stock initial réceptionné'),
(28, 1, 'entree', 25,  0, 25,  'Stock initial réceptionné'),
(29, 1, 'entree', 20,  0, 20,  'Stock initial réceptionné'),
(30, 1, 'entree', 50,  0, 50,  'Stock initial réceptionné'),
(31, 1, 'entree', 55,  0, 55,  'Stock initial réceptionné'),
(32, 1, 'entree', 70,  0, 70,  'Stock initial réceptionné'),
(33, 1, 'entree', 20,  0, 20,  'Stock initial réceptionné'),
(34, 1, 'entree', 35,  0, 35,  'Stock initial réceptionné'),
-- Sorties (ventes)
(1,  1, 'sortie', 15, 100, 85,  'Vente client chantier'),
(4,  1, 'sortie', 8,  50,  42,  'Vente comptoir'),
(8,  1, 'sortie', 30, 150, 120, 'Vente détail'),
(9,  1, 'sortie', 50, 250, 200, 'Commande électricien'),
(13, 1, 'sortie', 15, 50,  35,  'Chantier peinture'),
(16, 1, 'sortie', 10, 40,  30,  'Vente comptoir'),
(19, 1, 'sortie', 15, 70,  55,  'Vente détail'),
(21, 1, 'sortie', 10, 50,  40,  'Vente comptoir'),
(24, 1, 'sortie', 15, 70,  55,  'Vente détail'),
(27, 1, 'sortie', 10, 50,  40,  'Vente client'),
(31, 1, 'sortie', 12, 55,  43,  'Vente comptoir'),
-- Réapprovisionnements
(9,  1, 'entree', 100, 200, 300, 'Réapprovisionnement ampoules'),
(1,  1, 'entree', 50,  85,  135, 'Réapprovisionnement tuyaux'),
(21, 1, 'entree', 20,  40,  60,  'Réapprovisionnement vis'),
-- Ajustements
(3,  1, 'ajustement', 5,   200, 195, 'Inventaire : écart constaté'),
(22, 1, 'ajustement', 10,  80,  70,  'Inventaire : écart constaté');