-- Seed de données Cameroun pour RentFlow

-- Agences immobilières (villes du Cameroun)
INSERT INTO agencies (name, contact) VALUES
('Immobilière Douala Centre', 'Boulevard de la Liberté, Akwa<br>Tél: +237 233 42 10 10<br>Email: contact@immo-douala.cm'),
('Cameroun Immobilier', 'Rue Joss, Bonanjo<br>Tél: +237 233 42 25 67<br>Email: info@cameroun-immo.cm'),
('Yaoundé Immobilier', 'Avenue Kennedy, Centre-ville<br>Tél: +237 222 20 15 30<br>Email: contact@yaounde-immo.cm'),
('Immobilier du Littoral', 'Bonapriso, Rue des Cocotiers<br>Tél: +237 233 43 50 80<br>Email: contact@littoral-immo.cm'),
('Capitale Immobilier', 'Bastos, Rue 1234<br>Tél: +237 222 21 45 90<br>Email: info@capitale-immo.cm'),
('Immobilier de l''Est', 'Carrefour Warda, Bertoua<br>Tél: +237 222 24 10 20'),
('Nord Immobilier', 'Avenue du 20 Mai, Garoua<br>Tél: +237 222 27 30 40'),
('Ouest Habitat', 'Marché Central, Bafoussam<br>Tél: +237 233 44 60 70');

-- Bailleurs (noms camerounais)
INSERT INTO landlords (name, contact, contract_details) VALUES
('M. Jean-Pierre Amougou', '+237 677 12 34 56', 'Akwa, Douala'),
('Mme Marie-Claire Ngono', '+237 699 23 45 67', 'Bastos, Yaoundé'),
('M. Ibrahim Bouba', '+237 675 34 56 78', 'Garoua'),
('Mme Fatimatou Aboubakar', '+237 698 45 67 89', 'Douala'),
('M. Charles Mbarga', '+237 677 56 78 90', 'Yaoundé'),
('Mme Thérèse Ndiaye', '+237 699 67 89 01', 'Bafoussam'),
('M. Paul Biyiti', '+237 675 78 90 12', 'Ebolowa'),
('Mme Aïcha Mamadou', '+237 698 89 01 23', 'Maroua'),
('M. François Siewe', '+237 677 90 12 34', 'Douala'),
('Mme Grace Fon', '+237 699 01 23 45', 'Bamenda'),
('M. André Essomba', '+237 675 12 34 56', 'Yaoundé'),
('Mme Khadija Ousman', '+237 698 23 45 67', 'Ngaoundéré');

-- Lots/Batchs (résidences et immeubles au Cameroun)
INSERT INTO batches (landlord_id, agency_id, name) VALUES
(1, 1, 'Résidence Les Palmiers - Akwa'),
(2, 2, 'Immeuble Le Wouri - Bonanjo'),
(3, 3, 'Résidence La Falaise - Bastos'),
(4, 3, 'Cité des Arts - Mvan'),
(5, 4, 'Résidence Les Cocotiers - Bonapriso'),
(6, 5, 'Immeuble Central - Marché'),
(7, 1, 'Résidence du Lac'),
(8, 2, 'Cité Administrative'),
(9, 4, 'Résidence La Paix - Deido'),
(10, 5, 'Immeuble Le Cameroun'),
(11, 6, 'Résidence des Chutes'),
(12, 7, 'Cité du Nord - Garoua'),
(1, 3, 'Résidence Montagne - Mont Fébé'),
(2, 8, 'Immeuble Bamiléké - Bafoussam'),
(3, 1, 'Résidence Océan - Kribi');

-- Paiements avec statuts variés
INSERT INTO payments (batch_id, amount, due_date, paid_date, status) VALUES
-- Janvier 2026
(1, 150000, '2026-01-05', '2026-01-03', 'EARLY'),
(2, 200000, '2026-01-05', '2026-01-05', 'ON_TIME'),
(3, 180000, '2026-01-05', '2026-01-10', 'LATE'),
(4, 120000, '2026-01-05', '2026-01-04', 'ON_TIME'),
(5, 250000, '2026-01-05', '2026-01-02', 'EARLY'),
(6, 300000, '2026-01-05', NULL, 'PENDING'),
(7, 500000, '2026-01-05', '2026-01-08', 'LATE'),
(8, 100000, '2026-01-05', '2026-01-05', 'ON_TIME'),
(9, 175000, '2026-01-05', '2026-01-03', 'EARLY'),
(10, 400000, '2026-01-05', NULL, 'PENDING'),

-- Février 2026
(1, 150000, '2026-02-05', '2026-02-01', 'EARLY'),
(2, 200000, '2026-02-05', '2026-02-05', 'ON_TIME'),
(3, 180000, '2026-02-05', '2026-02-12', 'LATE'),
(4, 120000, '2026-02-05', '2026-02-05', 'ON_TIME'),
(5, 250000, '2026-02-05', '2026-02-03', 'EARLY'),
(6, 300000, '2026-02-05', '2026-02-04', 'ON_TIME'),
(7, 500000, '2026-02-05', NULL, 'PENDING'),
(8, 100000, '2026-02-05', '2026-02-05', 'ON_TIME'),
(9, 175000, '2026-02-05', '2026-02-02', 'EARLY'),
(10, 400000, '2026-02-05', '2026-02-10', 'LATE'),

-- Mars 2026
(1, 150000, '2026-03-05', '2026-03-04', 'ON_TIME'),
(2, 200000, '2026-03-05', '2026-03-05', 'ON_TIME'),
(3, 180000, '2026-03-05', '2026-03-03', 'EARLY'),
(4, 120000, '2026-03-05', NULL, 'PENDING'),
(5, 250000, '2026-03-05', '2026-03-01', 'EARLY'),
(6, 300000, '2026-03-05', '2026-03-05', 'ON_TIME'),
(7, 500000, '2026-03-05', NULL, 'PENDING'),
(8, 100000, '2026-03-05', '2026-03-04', 'ON_TIME'),
(9, 175000, '2026-03-05', '2026-03-05', 'ON_TIME'),
(10, 400000, '2026-03-05', '2026-03-02', 'EARLY'),

-- Avril 2026
(1, 150000, '2026-04-05', '2026-04-03', 'EARLY'),
(2, 200000, '2026-04-05', '2026-04-05', 'ON_TIME'),
(3, 180000, '2026-04-05', '2026-04-09', 'LATE'),
(4, 120000, '2026-04-05', '2026-04-05', 'ON_TIME'),
(5, 250000, '2026-04-05', '2026-04-02', 'EARLY'),
(6, 300000, '2026-04-05', NULL, 'PENDING'),
(7, 500000, '2026-04-05', '2026-04-05', 'ON_TIME'),
(8, 100000, '2026-04-05', '2026-04-04', 'ON_TIME'),
(9, 175000, '2026-04-05', '2026-04-01', 'EARLY'),
(10, 400000, '2026-04-05', '2026-04-05', 'ON_TIME'),

-- Mai 2026
(1, 150000, '2026-05-05', '2026-05-02', 'EARLY'),
(2, 200000, '2026-05-05', NULL, 'PENDING'),
(3, 180000, '2026-05-05', NULL, 'PENDING'),
(4, 120000, '2026-05-05', NULL, 'PENDING'),
(5, 250000, '2026-05-05', NULL, 'PENDING'),
(6, 300000, '2026-05-05', NULL, 'PENDING'),
(7, 500000, '2026-05-05', NULL, 'PENDING'),
(8, 100000, '2026-05-05', NULL, 'PENDING'),
(9, 175000, '2026-05-05', NULL, 'PENDING'),
(10, 400000, '2026-05-05', NULL, 'PENDING');
