-- ============================================================
-- Hôtel Sawana — Garoua, Nord Cameroun
-- Données de démonstration réalistes (FCFA)
-- ============================================================
USE aureliahost;

-- Forcer le charset UTF-8 pour la connexion
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Nettoyage (ordre respectant les FK)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE reservation_services;
TRUNCATE reservation_rooms;
TRUNCATE invoice_items;
TRUNCATE payments;
TRUNCATE invoices;
TRUNCATE housekeeping;
TRUNCATE attendance;
TRUNCATE leaves;
TRUNCATE payrolls;
TRUNCATE expenses;
TRUNCATE reservations;
TRUNCATE clients;
TRUNCATE employees;
TRUNCATE services;
TRUNCATE rooms;
TRUNCATE room_types;
TRUNCATE departments;
TRUNCATE taxes;
TRUNCATE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- UTILISATEURS
-- ============================================================
INSERT INTO users (nom, prenom, email, password_hash, role, telephone) VALUES
('Dang', 'Paul', 'admin@sawana.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+237 699 01 02 03'),
('Haoussa', 'Aminatou', 'reception@sawana.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', '+237 699 11 22 33'),
('Oumarou', 'Ismaël', 'manager@sawana.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '+237 699 44 55 66'),
('Hamadou', 'Martine', 'compta@sawana.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'accountant', '+237 699 77 88 99'),
('Bello', 'Ousmane', 'rh@sawana.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr', '+237 699 00 11 22');
-- Mot de passe pour tous: password

-- ============================================================
-- TYPES DE CHAMBRES (Prix en FCFA)
-- ============================================================
INSERT INTO room_types (nom, description, prix_base, capacite) VALUES
('Chambre Simple', 'Lit simple, salle de bain privée, climatisation, TV satellite', 15000, 1),
('Chambre Double', 'Lit double king-size, salle de bain, balcon, climatisation, mini-bar', 25000, 2),
('Suite Junior', 'Salon séparé, lit king-size, baignoire, balcon vue sur le mont Tinguelin', 45000, 2),
('Suite Royale', '2 chambres, salon, jacuzzi, terrasse panoramique, service majordome', 75000, 4);

-- ============================================================
-- CHAMBRES (12 chambres — 3 par type sur 3 étages)
-- ============================================================
INSERT INTO rooms (numero, room_type_id, etage, statut, description) VALUES
-- Rez-de-chaussée
('R01', 1, 0, 'disponible', 'Côté jardin, accès facile'),
('R02', 1, 0, 'occupee', 'Côté piscine'),
('R03', 1, 0, 'disponible', 'Côté cour intérieure'),
-- 1er étage
('101', 2, 1, 'disponible', 'Balcon vue Benoué'),
('102', 2, 1, 'occupee', 'Balcon vue piscine, chambre d''angle'),
('103', 2, 1, 'disponible', 'Balcon vue jardin'),
-- 2e étage
('201', 3, 2, 'disponible', 'Suite junior prestige, vue mont Tinguelin'),
('202', 3, 2, 'maintenance', 'Suite junior luxe, en réfection climatisation'),
('203', 3, 2, 'occupee', 'Suite junior premium, vue panoramique'),
-- 3e étage
('301', 4, 3, 'disponible', 'Suite royale impériale, terrasse 360°'),
('302', 4, 3, 'occupee', 'Suite royale présidentielle, jacuzzi privé'),
('303', 4, 3, 'nettoyage', 'Suite royale diplomatique, 2 chambres');

-- ============================================================
-- DÉPARTEMENTS
-- ============================================================
INSERT INTO departments (nom, description) VALUES
('Direction', 'Direction générale et stratégie'),
('Réception', 'Accueil, réservations et relation client'),
('Restauration', 'Cuisine, restaurant et bar'),
('Entretien & Maintenance', 'Propreté, maintenance technique et espaces verts'),
('Spa & Bien-être', 'Massages, soins esthétiques et piscine'),
('Comptabilité', 'Finances, paie et achats');

-- ============================================================
-- TAXES (TVA Cameroun = 19.25%)
-- ============================================================
INSERT INTO taxes (nom, taux, type, description, actif) VALUES
('TVA Standard', 19.25, 'tva', 'TVA taux normal — hébergement et services', 1),
('TVA Réduite', 10.00, 'tva', 'TVA réduite — produits alimentaires de base', 1),
('IS', 30.00, 'is', 'Impôt sur les sociétés — régime réel', 1);

-- ============================================================
-- SERVICES (Prix en FCFA)
-- ============================================================
INSERT INTO services (nom, description, prix, categorie) VALUES
('Petit-déjeuner buffet', 'Café, thé, viennoiseries, omelette, fruits frais, jus', 3000, 'restaurant'),
('Déjeuner complet', 'Entrée + plat + dessert, cuisine locale et internationale', 7000, 'restaurant'),
('Dîner gastronomique', 'Menu 4 services du chef, spécialités du Nord Cameroun', 12000, 'restaurant'),
('Massage relaxant 60 min', 'Massage complet aux huiles de karité et citronnelle', 10000, 'spa'),
('Soin du visage 45 min', 'Soin purifiant au beurre de karité et miel local', 8000, 'spa'),
('Blanchisserie / Pressing', 'Lavage et repassage (par panier)', 2000, 'blanchisserie'),
('Navette aéroport', 'Transfert privé Aéroport International de Garoua', 5000, 'transport'),
('Location voiture avec chauffeur', 'Demi-journée découverte région', 25000, 'transport'),
('Service en chambre 24/7', 'Plateau repas servi en chambre', 2000, 'restaurant');

-- ============================================================
-- EMPLOYÉS (12 — Nord Cameroun)
-- ============================================================
INSERT INTO employees (nom, prenom, email, telephone, adresse, department_id, poste, date_embauche, salaire_base, statut, type_contrat) VALUES
-- Direction (1)
('Hayatou', 'Issa', 'issa.hayatou@sawana.cm', '+237 698 11 22 33', 'Quartier Yelwa, Garoua', 1, 'Directeur Général', '2023-01-15', 450000, 'actif', 'cdi'),
-- Réception (3)
('Bouba', 'Mariam', 'mariam.bouba@sawana.cm', '+237 698 22 33 44', 'Quartier Lainde, Garoua', 2, 'Chef de Réception', '2023-03-01', 200000, 'actif', 'cdi'),
('Ousmanou', 'Yaya', 'yaya.ousmanou@sawana.cm', '+237 698 33 44 55', 'Quartier Maroua, Garoua', 2, 'Réceptionniste', '2024-01-10', 150000, 'actif', 'cdi'),
('Saïdou', 'Nadege', 'nadege.saidou@sawana.cm', '+237 698 44 55 66', 'Quartier Djamboutou, Garoua', 2, 'Réceptionniste', '2025-02-01', 150000, 'actif', 'cdd'),
-- Restauration (3)
('Mohamadou', 'Sali', 'sali.mohamadou@sawana.cm', '+237 697 11 22 33', 'Quartier Poumpoumre, Garoua', 3, 'Chef Cuisinier', '2023-06-01', 280000, 'actif', 'cdi'),
('Yaya', 'Hawa', 'hawa.yaya@sawana.cm', '+237 697 22 33 44', 'Rue du Marché, Garoua', 3, 'Serveuse', '2024-03-15', 120000, 'actif', 'cdi'),
('Bello', 'Moussa', 'moussa.bello@sawana.cm', '+237 697 33 44 55', 'Garoua Centre', 3, 'Commis de cuisine', '2025-01-05', 100000, 'actif', 'stage'),
-- Entretien (3)
('Hamadou', 'Asta', 'asta.hamadou@sawana.cm', '+237 696 11 22 33', 'Quartier Yelwa, Garoua', 4, 'Gouvernante Générale', '2023-04-01', 180000, 'actif', 'cdi'),
('Dawa', 'Jean', 'jean.dawa@sawana.cm', '+237 696 22 33 44', 'Quartier Bockle, Garoua', 4, 'Agent d''entretien', '2024-02-01', 100000, 'actif', 'cdi'),
('Oumate', 'Esther', 'esther.oumate@sawana.cm', '+237 696 33 44 55', 'Quartier Tongo, Garoua', 4, 'Technicien maintenance', '2024-06-01', 140000, 'actif', 'cdi'),
-- Spa (1)
('Tchinda', 'Chantal', 'chantal.tchinda@sawana.cm', '+237 695 11 22 33', 'Rue des Palmiers, Garoua', 5, 'Spa Manager & Masseuse', '2024-01-15', 160000, 'actif', 'cdi'),
-- Comptabilité (1)
('Njoya', 'Bertrand', 'bertrand.njoya@sawana.cm', '+237 695 22 33 44', 'Garoua Centre Commercial', 6, 'Comptable', '2023-09-01', 220000, 'actif', 'cdi');

-- ============================================================
-- CLIENTS (18 — locaux et internationaux)
-- ============================================================
INSERT INTO clients (nom, prenom, email, telephone, adresse, ville, pays, document_type, document_numero, date_naissance) VALUES
('Oumarou', 'Aminou', 'aminou.oumarou@gmail.com', '+237 690 11 22 33', 'Avenue Ahmadou Ahidjo 12', 'Garoua', 'Cameroun', 'cin', 'CM090112233', '1985-03-15'),
('Fadimatou', 'Celine', 'celine.fad@yahoo.fr', '+237 690 22 33 44', 'Rue du Lamidat', 'Maroua', 'Cameroun', 'cin', 'CM090223344', '1990-07-20'),
('Ndi', 'Samuel', 'samuel.ndi@hotmail.com', '+237 690 33 44 55', 'BP 234', 'Ngaoundéré', 'Cameroun', 'cin', 'CM090334455', '1982-11-08'),
('Tchakounte', 'Laure', 'laure.tchak@gmail.com', '+237 690 44 55 66', 'Quartier Administratif', 'Garoua', 'Cameroun', 'cin', 'CM090445566', '1993-01-25'),
('Boubakary', 'Hamadou', 'h.boubakary@orange.cm', '+237 691 11 22 33', 'Rue Principale, Pitoa', 'Pitoa', 'Cameroun', 'cin', 'CM091112233', '1978-06-10'),
('Djaoro', 'Aissatou', 'aissatou.djaoro@gmail.com', '+237 691 22 33 44', 'Quartier Moderne, Guider', 'Guider', 'Cameroun', 'cin', 'CM091223344', '1988-09-30'),
('Wakil', 'Mahamat', 'mahamat.wakil@proton.me', '+235 66 11 22 33', 'Avenue Mobutu, Ndjamena', 'Ndjamena', 'Tchad', 'passeport', 'TD1234567', '1975-04-12'),
('Idriss', 'Fatime', 'fatime.idriss@gmail.com', '+235 66 22 33 44', 'Rue de l''Indépendance', 'Ndjamena', 'Tchad', 'passeport', 'TD2233445', '1989-12-03'),
('Dupont', 'Michel', 'michel.dupont@airfrance.fr', '+33 6 12 34 56 78', '15 rue de Rivoli', 'Paris', 'France', 'passeport', 'FR12AB34567', '1970-08-22'),
('Schmidt', 'Anna', 'anna.schmidt@t-online.de', '+49 170 1234567', 'Berliner Str. 45', 'Berlin', 'Allemagne', 'passeport', 'DE1234567890', '1984-05-14'),
('van Dijk', 'Pieter', 'p.vandijk@outlook.com', '+31 6 12345678', 'Keizersgracht 100', 'Amsterdam', 'Pays-Bas', 'passeport', 'NL98765432', '1991-11-28'),
('Mballa', 'Jean-Claude', 'jc.mballa@yahoo.fr', '+237 692 11 22 33', 'BP 5678', 'Yaoundé', 'Cameroun', 'cin', 'CM092112233', '1980-02-14'),
('Sali', 'Djangrang', 'djangrang.sali@gmail.com', '+237 692 44 55 66', 'Quartier Residentiel', 'Maroua', 'Cameroun', 'cin', 'CM092445566', '1987-07-19'),
('Kouotou', 'Patricia', 'patricia.k@afriland.cm', '+237 693 11 22 33', 'Rue du Commerce', 'Garoua', 'Cameroun', 'cin', 'CM093112233', '1995-09-05'),
('Abba', 'Oumar', 'oumar.abba@gmail.com', '+237 693 33 44 55', 'BP 890', 'Kousseri', 'Cameroun', 'cin', 'CM093334455', '1979-01-30'),
('Ngono', 'Esther', 'esther.ngono@camtel.cm', '+237 694 11 22 33', 'Avenue Centrale', 'Ngaoundéré', 'Cameroun', 'cin', 'CM094112233', '1992-04-17'),
('Hamadjoda', 'Yacouba', 'yacouba.ham@gmail.com', '+237 694 55 66 77', 'Quartier Administratif', 'Garoua', 'Cameroun', 'cin', 'CM094556677', '1983-08-25'),
('Bello', 'Roukayatou', 'roukayatou.bello@yahoo.fr', '+237 696 77 88 99', 'Rue du Palais', 'Garoua', 'Cameroun', 'cin', 'CM096778899', '1990-12-12');

-- ============================================================
-- RÉSERVATIONS (8 — passées, en cours, futures)
-- ============================================================
INSERT INTO reservations (client_id, user_id, date_checkin, date_checkout, statut, montant_total, montant_paye, notes) VALUES
-- Réservation 1: passée et terminée (avril 2026)
(7, 2, '2026-04-05', '2026-04-08', 'terminee', 105000, 105000, 'M. Wakil — mission diplomatique, chambre double + navette'),
-- Réservation 2: passée et terminée (fin avril)
(8, 2, '2026-04-20', '2026-04-24', 'terminee', 200000, 200000, 'Mme Idriss — séjour affaires, suite junior'),
-- Réservation 3: en cours (check-in 15 mai)
(1, 2, '2026-05-15', '2026-05-20', 'en_cours', 125000, 75000, 'M. Oumarou — séminaire agricole'),
-- Réservation 4: en cours (check-in 17 mai)
(3, 3, '2026-05-17', '2026-05-22', 'en_cours', 225000, 150000, 'M. Ndi — conférence régionale, suite junior'),
-- Réservation 5: future confirmée
(9, 2, '2026-05-25', '2026-05-28', 'confirmee', 36000, 15000, 'M. Dupont — tourisme, chambre simple + petit-déj'),
-- Réservation 6: future confirmée
(12, 2, '2026-06-01', '2026-06-05', 'confirmee', 180000, 0, 'M. Mballa — réunion gouvernement, suite royale + transport'),
-- Réservation 7: future confirmée
(14, 2, '2026-05-28', '2026-05-31', 'confirmee', 75000, 25000, 'Mme Kouotou — formation bancaire, chambre double'),
-- Réservation 8: annulée
(4, 2, '2026-05-01', '2026-05-03', 'annulee', 30000, 15000, 'Mme Tchakounte — annulé cause empêchement');

-- ============================================================
-- RÉSERVATION-CHAMBRES (liens)
-- ============================================================
INSERT INTO reservation_rooms (reservation_id, room_id, prix_par_nuit) VALUES
-- Réservation 1: Wakil — chambre double 102 (3 nuits × 25000 = 75000)
(1, 5, 25000),
-- Réservation 2: Idriss — suite junior 203 (4 nuits × 45000 = 180000)
(2, 9, 45000),
-- Réservation 3: Oumarou — chambre double 102 (5 nuits × 25000 = 125000)
(3, 5, 25000),
-- Réservation 4: Ndi — suite junior 203 (5 nuits × 45000 = 225000)
(4, 9, 45000),
-- Réservation 5: Dupont — chambre simple R02 (3 nuits × 15000 = 45000)
(5, 2, 15000),
-- Réservation 6: Mballa — suite royale 302 (4 nuits × 75000 = 300000)
(6, 11, 75000),
-- Réservation 7: Kouotou — chambre double 101 (3 nuits × 25000 = 75000)
(7, 4, 25000),
-- Réservation 8: annulée — chambre double 201
(8, 7, 25000);

-- ============================================================
-- RÉSERVATION-SERVICES
-- ============================================================
INSERT INTO reservation_services (reservation_id, service_id, quantite, prix_unitaire, date_service) VALUES
-- Réservation 1: Wakil — navette aéroport + 2 petits-déj
(1, 7, 1, 5000, '2026-04-05'),
(1, 1, 2, 3000, '2026-04-05'),
-- Réservation 2: Idriss — 1 dîner gastronomique
(2, 3, 1, 12000, '2026-04-21'),
-- Réservation 3: Oumarou
(3, 1, 1, 3000, '2026-05-16'),
(3, 4, 1, 10000, '2026-05-16'),
-- Réservation 4: Ndi
(4, 7, 1, 5000, '2026-05-17'),
(4, 2, 1, 7000, '2026-05-18'),
-- Réservation 5: Dupont
(5, 1, 1, 3000, '2026-05-25'),
(5, 7, 1, 5000, '2026-05-25'),
-- Réservation 6: Mballa
(6, 8, 1, 25000, '2026-06-01'),
-- Réservation 7: Kouotou
(7, 1, 2, 3000, '2026-05-28');

-- ============================================================
-- HOUSEKEEPING (2 dernières semaines)
-- ============================================================
INSERT INTO housekeeping (room_id, user_id, date_nettoyage, statut, notes) VALUES
(2, 2, '2026-05-13', 'termine', 'Chambre occupée — nettoyage standard'),
(5, 2, '2026-05-13', 'termine', 'Chambre occupée — nettoyage standard + mini-bar'),
(9, 2, '2026-05-13', 'termine', 'Suite occupée — nettoyage complet'),
(11, 2, '2026-05-13', 'termine', 'Suite occupée — nettoyage complet'),
(2, 2, '2026-05-14', 'termine', 'Nettoyage quotidien'),
(5, 2, '2026-05-14', 'termine', 'Nettoyage quotidien'),
(9, 2, '2026-05-14', 'termine', 'Nettoyage quotidien + changement draps'),
(11, 2, '2026-05-14', 'termine', 'Nettoyage quotidien'),
(2, 2, '2026-05-15', 'termine', 'Check-in Oumarou — préparation chambre'),
(8, 2, '2026-05-15', 'en_cours', 'Maintenance climatisation — pièce commandée'),
(2, 2, '2026-05-16', 'termine', 'Nettoyage standard'),
(5, 2, '2026-05-16', 'termine', 'Nettoyage standard'),
(9, 2, '2026-05-16', 'termine', 'Nettoyage standard'),
(12, 2, '2026-05-17', 'planifie', 'Nettoyage après check-in Ndi'),
(2, 2, '2026-05-17', 'termine', 'Nettoyage standard'),
(5, 2, '2026-05-17', 'termine', 'Nettoyage standard'),
(2, 2, '2026-05-18', 'planifie', 'Nettoyage quotidien'),
(5, 2, '2026-05-18', 'planifie', 'Nettoyage quotidien'),
(9, 2, '2026-05-19', 'planifie', 'Nettoyage quotidien suite Ndi'),
(11, 2, '2026-05-19', 'planifie', 'Nettoyage quotidien suite présidentielle'),
(12, 2, '2026-05-19', 'planifie', 'Nettoyage suite — check-out prévu');

-- ============================================================
-- PRÉSENCES (2 dernières semaines — mai 2026)
-- ============================================================
INSERT INTO attendance (employee_id, date, heure_entree, heure_sortie, statut) VALUES
-- Semaine du 5 au 10 mai
(1, '2026-05-05', '07:30', '17:00', 'present'), (2, '2026-05-05', '07:00', '15:00', 'present'),
(3, '2026-05-05', '15:00', '23:00', 'present'), (4, '2026-05-05', '07:00', '15:00', 'present'),
(5, '2026-05-05', '06:30', '14:30', 'present'), (6, '2026-05-05', '07:00', '15:00', 'present'),
(7, '2026-05-05', '08:00', '16:00', 'present'), (8, '2026-05-05', '06:00', '14:00', 'present'),
(9, '2026-05-05', '06:00', '14:00', 'present'), (10, '2026-05-05', '08:00', '16:00', 'present'),
(11, '2026-05-05', '09:00', '17:00', 'present'), (12, '2026-05-05', '08:00', '16:00', 'present'),
(1, '2026-05-06', '07:30', '17:00', 'present'), (2, '2026-05-06', '07:00', '15:00', 'present'),
(3, '2026-05-06', '15:00', '23:00', 'present'), (4, '2026-05-06', '07:15', '15:00', 'retard'),
(5, '2026-05-06', '06:30', '14:30', 'present'), (6, '2026-05-06', '07:00', '15:00', 'present'),
(7, '2026-05-06', '08:00', '16:00', 'absent'), (8, '2026-05-06', '06:00', '14:00', 'present'),
(9, '2026-05-06', '06:00', '14:00', 'present'), (10, '2026-05-06', '08:00', '16:00', 'present'),
(11, '2026-05-06', '09:00', '17:00', 'present'), (12, '2026-05-06', '08:00', '16:00', 'present'),
-- Vendredi 8 mai
(1, '2026-05-08', '07:30', '17:00', 'present'), (2, '2026-05-08', '07:00', '15:00', 'present'),
(3, '2026-05-08', '15:00', '23:00', 'present'), (4, '2026-05-08', '07:00', '15:00', 'present'),
(5, '2026-05-08', '06:30', '14:30', 'present'), (6, '2026-05-08', '07:00', '15:00', 'present'),
(8, '2026-05-08', '06:00', '14:00', 'present'), (9, '2026-05-08', '06:00', '14:00', 'present'),
(10, '2026-05-08', '08:00', '16:00', 'present'), (11, '2026-05-08', '09:00', '17:00', 'present'),
(12, '2026-05-08', '08:00', '16:00', 'present'),
-- Lundi 12 mai
(1, '2026-05-12', '07:30', '17:00', 'present'), (2, '2026-05-12', '07:00', '15:00', 'present'),
(3, '2026-05-12', '15:05', '23:00', 'retard'), (4, '2026-05-12', '07:00', '15:00', 'present'),
(5, '2026-05-12', '06:30', '14:30', 'present'), (6, '2026-05-12', '07:00', '15:00', 'present'),
(7, '2026-05-12', '08:00', '16:00', 'present'), (8, '2026-05-12', '06:00', '14:00', 'present'),
(9, '2026-05-12', '06:00', '14:00', 'present'), (10, '2026-05-12', '08:00', '16:00', 'present'),
(11, '2026-05-12', '09:00', '17:00', 'present'), (12, '2026-05-12', '08:00', '16:00', 'present'),
-- Mardi 13 mai
(1, '2026-05-13', '07:30', '17:00', 'present'), (2, '2026-05-13', '07:00', '15:00', 'present'),
(3, '2026-05-13', '15:00', '23:00', 'present'), (4, '2026-05-13', '07:00', '15:00', 'present'),
(5, '2026-05-13', '06:30', '14:30', 'present'), (6, '2026-05-13', '07:00', '15:00', 'present'),
(7, '2026-05-13', '08:00', '16:00', 'present'), (8, '2026-05-13', '06:00', '14:00', 'present'),
(9, '2026-05-13', '06:00', '14:00', 'absent'), (10, '2026-05-13', '08:00', '16:00', 'present'),
(11, '2026-05-13', '09:00', '17:00', 'present'), (12, '2026-05-13', '08:00', '16:00', 'present');

-- ============================================================
-- CONGÉS
-- ============================================================
INSERT INTO leaves (employee_id, type, date_debut, date_fin, motif, statut, approuve_par) VALUES
(7, 'maladie', '2026-05-06', '2026-05-06', 'Consultation médicale — paludisme', 'approuve', 1),
(9, 'conge_paye', '2026-05-13', '2026-05-13', 'Obligations familiales', 'approuve', 1),
(4, 'conge_paye', '2026-06-15', '2026-06-30', 'Congés annuels — mariage frère à Maroua', 'en_attente', NULL),
(6, 'maternite', '2026-07-01', '2026-09-23', 'Congé de maternité', 'approuve', 1);

-- ============================================================
-- PAIE (Mai 2026)
-- ============================================================
INSERT INTO payrolls (employee_id, mois, salaire_base, primes, deductions, salaire_net, statut) VALUES
(1, '2026-05-01', 450000, 50000, 45000, 455000, 'genere'),
(2, '2026-05-01', 200000, 30000, 20000, 210000, 'genere'),
(3, '2026-05-01', 150000, 15000, 15000, 150000, 'genere'),
(4, '2026-05-01', 150000, 10000, 14000, 146000, 'genere'),
(5, '2026-05-01', 280000, 40000, 28000, 292000, 'genere'),
(6, '2026-05-01', 120000, 10000, 11000, 119000, 'genere'),
(7, '2026-05-01', 100000, 5000, 9000, 96000, 'genere'),
(8, '2026-05-01', 180000, 20000, 18000, 182000, 'genere'),
(9, '2026-05-01', 100000, 8000, 9500, 98500, 'genere'),
(10, '2026-05-01', 140000, 15000, 13500, 141500, 'genere'),
(11, '2026-05-01', 160000, 20000, 15000, 165000, 'genere'),
(12, '2026-05-01', 220000, 25000, 22000, 223000, 'genere');

-- ============================================================
-- FACTURES
-- ============================================================
INSERT INTO invoices (reservation_id, client_id, numero_facture, date_emission, date_echeance, montant_ht, montant_tva, montant_ttc, statut) VALUES
-- Facture 1: Wakil — séjour terminé, payé
(1, 7, 'FAC-2026-001', '2026-04-08', '2026-04-22', 88036, 16947, 105000, 'payee'),
-- Facture 2: Idriss — séjour terminé, payé
(2, 8, 'FAC-2026-002', '2026-04-24', '2026-05-08', 167722, 32278, 200000, 'payee'),
-- Facture 3: Oumarou — acompte partiel
(3, 1, 'FAC-2026-003', '2026-05-15', '2026-06-01', 104836, 20164, 125000, 'envoyee'),
-- Facture 4: Ndi — acompte partiel
(4, 3, 'FAC-2026-004', '2026-05-17', '2026-06-01', 188710, 36290, 225000, 'envoyee'),
-- Facture 5: Dupont — acompte
(5, 9, 'FAC-2026-005', '2026-05-15', '2026-06-10', 30189, 5811, 36000, 'brouillon'),
-- Facture 6: Tchakounte — annulée, remboursement partiel
(8, 4, 'FAC-2026-006', '2026-05-01', '2026-05-15', 25156, 4844, 30000, 'annulee');

-- ============================================================
-- LIGNES DE FACTURE
-- ============================================================
INSERT INTO invoice_items (invoice_id, description, quantite, prix_unitaire, total) VALUES
-- FAC-001: Wakil
(1, 'Chambre Double — 3 nuits (05-08/04/2026)', 3, 25000, 75000),
(1, 'Navette aéroport', 1, 5000, 5000),
(1, 'Petit-déjeuner buffet × 2', 2, 3000, 6000),
(1, 'TVA 19.25%', 1, 16947, 16947),
-- FAC-002: Idriss
(2, 'Suite Junior — 4 nuits (20-24/04/2026)', 4, 45000, 180000),
(2, 'Dîner gastronomique', 1, 12000, 12000),
(2, 'TVA 19.25%', 1, 32278, 32278),
-- FAC-003: Oumarou
(3, 'Chambre Double — 5 nuits (15-20/05/2026)', 5, 25000, 125000),
(3, 'Petit-déjeuner buffet', 1, 3000, 3000),
(3, 'Massage relaxant 60 min', 1, 10000, 10000),
(3, 'TVA 19.25%', 1, 20164, 20164),
-- FAC-004: Ndi
(4, 'Suite Junior — 5 nuits (17-22/05/2026)', 5, 45000, 225000),
(4, 'Navette aéroport', 1, 5000, 5000),
(4, 'Déjeuner complet', 1, 7000, 7000),
(4, 'TVA 19.25%', 1, 36290, 36290),
-- FAC-005: Dupont
(5, 'Chambre Simple — 3 nuits (25-28/05/2026)', 3, 15000, 45000),
(5, 'Petit-déjeuner buffet', 1, 3000, 3000),
(5, 'TVA 19.25%', 1, 6924, 6924),
-- FAC-006: annulée
(6, 'Chambre Double — 2 nuits (01-03/05/2026)', 2, 25000, 50000),
(6, 'TVA 19.25% (remboursée)', 1, -5000, -5000);

-- ============================================================
-- PAIEMENTS
-- ============================================================
INSERT INTO payments (invoice_id, montant, mode_paiement, reference, date_paiement) VALUES
(1, 105000, 'virement', 'VIR-20260408-001', '2026-04-08'),
(2, 200000, 'carte', 'CB-20260424-001', '2026-04-24'),
(3, 75000, 'especes', 'ESP-20260515-001', '2026-05-15'),
(4, 150000, 'virement', 'VIR-20260517-001', '2026-05-17'),
(5, 15000, 'carte', 'CB-20260515-001', '2026-05-15'),
(6, 15000, 'especes', 'ESP-20260501-001', '2026-05-01');

-- ============================================================
-- DÉPENSES (Avril-Mai 2026)
-- ============================================================
INSERT INTO expenses (description, montant, categorie, date_depense, user_id, justificatif) VALUES
('Achat fournitures cuisine — marché central Garoua', 85000, 'fournitures', '2026-04-03', 4, 'facture_20260403_001.pdf'),
('Maintenance clim chambre 302 — réparation', 45000, 'maintenance', '2026-04-07', 4, 'facture_clim_avril.pdf'),
('Produits d''entretien — gros conditionnement', 120000, 'fournitures', '2026-04-12', 4, 'facture_produits.pdf'),
('Loyer local technique — Avril 2026', 200000, 'loyer', '2026-04-01', 4, 'quittance_avril.pdf'),
('Abonnement internet et téléphonie — Orange Cameroun', 75000, 'services', '2026-04-05', 4, 'facture_orange_avril.pdf'),
('Salaires Avril 2026 — masse salariale', 2278000, 'salaires', '2026-04-30', 4, 'journal_paie_avril.pdf'),
('Achat linge de maison — renouvellement stock', 180000, 'fournitures', '2026-05-03', 4, 'facture_linge_mai.pdf'),
('Carburant groupe électrogène — TOTAL Garoua', 55000, 'maintenance', '2026-05-08', 4, 'ticket_carburant.pdf'),
('Loyer local technique — Mai 2026', 200000, 'loyer', '2026-05-02', 4, 'quittance_mai.pdf'),
('Honoraires comptable externe — déclaration TVA', 150000, 'services', '2026-05-10', 4, 'facture_honoraires_mai.pdf'),
('Entretien piscine — produits chimiques', 35000, 'maintenance', '2026-05-14', 4, 'facture_piscine.pdf'),
('Frais de publicité — radio Garoua FM', 50000, 'autre', '2026-05-01', 4, 'facture_radio.pdf');
