-- ============================================================
-- DONNÉES DE SIMULATION — COMPLEXE SCOLAIRE
-- ============================================================
-- Ce fichier préremplit la base avec des données réalistes
-- pour tester toutes les fonctionnalités de l'application.
-- À importer APRÈS database.sql via phpMyAdmin.
-- ============================================================

USE complexe_scolaire;

-- ============================================================
-- 0. ANNÉE SCOLAIRE ACTIVE
-- ============================================================
-- Désactiver l'ancienne année, activer 2025-2026
UPDATE annees_scolaires SET active = 0 WHERE id = 1;
INSERT INTO annees_scolaires (libelle, date_debut, date_fin, active) VALUES
('2025-2026', '2025-09-01', '2026-07-31', 1);
SET @annee_id = LAST_INSERT_ID();

-- Périodes pour 2025-2026
INSERT INTO periodes (annee_id, nom, date_debut, date_fin) VALUES
(@annee_id, '1er Trimestre', '2025-09-01', '2025-12-19'),
(@annee_id, '2ème Trimestre', '2026-01-05', '2026-03-27'),
(@annee_id, '3ème Trimestre', '2026-04-06', '2026-07-03');
SET @trim1 = LAST_INSERT_ID();
SET @trim2 = LAST_INSERT_ID() + 1;
SET @trim3 = LAST_INSERT_ID() + 2;

-- ============================================================
-- 1. CLASSES (2 par niveau principal)
-- ============================================================
INSERT INTO classes (nom, niveau_id, annee_id, capacite) VALUES
-- Maternelle
('PS A', 1, @annee_id, 25),
('PS B', 1, @annee_id, 25),
('MS A', 2, @annee_id, 25),
('GS A', 3, @annee_id, 25),
-- Primaire
('CP A', 4, @annee_id, 30),
('CP B', 4, @annee_id, 30),
('CE1 A', 5, @annee_id, 30),
('CE2 A', 6, @annee_id, 30),
('CM1 A', 7, @annee_id, 30),
('CM2 A', 8, @annee_id, 30),
('CM2 B', 8, @annee_id, 30),
-- Collège
('6ème A', 9, @annee_id, 35),
('6ème B', 9, @annee_id, 35),
('5ème A', 10, @annee_id, 35),
('4ème A', 11, @annee_id, 35),
('3ème A', 12, @annee_id, 35),
('3ème B', 12, @annee_id, 35),
-- Lycée
('2nde A', 13, @annee_id, 40),
('2nde B', 13, @annee_id, 40),
('1ère S', 14, @annee_id, 35),
('Tle S', 15, @annee_id, 35);

-- ============================================================
-- 2. ENSEIGNANTS (20)
-- ============================================================
INSERT INTO enseignants (matricule, nom, prenom, date_naissance, sexe, telephone, email, specialite, diplome, date_embauche, statut) VALUES
('ENS001', 'Diallo', 'Amadou', '1980-03-15', 'M', '+237 6 01 02 03', 'a.diallo@cs.ml', 'Mathématiques', 'Licence Mathématiques', '2010-09-01', 'actif'),
('ENS002', 'Ngassa', 'Marie-Claire', '1985-07-22', 'F', '+237 6 02 03 04', 'mc.ngassa@cs.ml', 'Français', 'Maîtrise Lettres Modernes', '2012-09-01', 'actif'),
('ENS003', 'Tchinda', 'Patrick', '1978-11-08', 'M', '+237 6 03 04 05', 'p.tchinda@cs.ml', 'Physique-Chimie', 'Master Physique', '2008-09-01', 'actif'),
('ENS004', 'Fotso', 'Chantal', '1982-05-30', 'F', '+237 6 04 05 06', 'c.fotso@cs.ml', 'SVT', 'Licence Biologie', '2011-09-01', 'actif'),
('ENS005', 'Kamga', 'Jean-Pierre', '1975-09-12', 'M', '+237 6 05 06 07', 'jp.kamga@cs.ml', 'Histoire-Géographie', 'Maîtrise Histoire', '2005-09-01', 'actif'),
('ENS006', 'Mbeleck', 'Sophie', '1988-01-25', 'F', '+237 6 06 07 08', 's.mbeleck@cs.ml', 'Anglais', 'Master Anglais', '2015-09-01', 'actif'),
('ENS007', 'Ngo Um', 'Emmanuel', '1979-06-18', 'M', '+237 6 07 08 09', 'e.ngoum@cs.ml', 'Philosophie', 'Doctorat Philosophie', '2007-09-01', 'actif'),
('ENS008', 'Tchoumi', 'Aminatou', '1990-12-03', 'F', '+237 6 08 09 10', 'a.tchoumi@cs.ml', 'Éveil / Maternelle', 'DPE Maternelle', '2016-09-01', 'actif'),
('ENS009', 'Wamba', 'Rodrigue', '1983-04-20', 'M', '+237 6 09 10 11', 'r.wamba@cs.ml', 'EPS', 'Licence STAPS', '2013-09-01', 'actif'),
('ENS010', 'Kuété', 'Francine', '1986-08-14', 'F', '+237 6 10 11 12', 'f.kuete@cs.ml', 'Arts Plastiques', 'Licence Arts', '2014-09-01', 'actif'),
('ENS011', 'Atangana', 'Alain', '1981-02-28', 'M', '+237 6 11 12 13', 'a.atangana@cs.ml', 'Informatique', 'Licence Informatique', '2012-09-01', 'actif'),
('ENS012', 'Biya', 'Esther', '1991-10-10', 'F', '+237 6 12 13 14', 'e.biya@cs.ml', 'Lecture / Primaire', 'DPE Primaire', '2017-09-01', 'actif'),
('ENS013', 'Nganou', 'Dieudonné', '1977-07-05', 'M', '+237 6 13 14 15', 'd.nganou@cs.ml', 'Mathématiques', 'Master Mathématiques', '2003-09-01', 'actif'),
('ENS014', 'Simeni', 'Carine', '1989-03-19', 'F', '+237 6 14 15 16', 'c.simeni@cs.ml', 'Français', 'Licence Lettres', '2016-09-01', 'actif'),
('ENS015', 'Fokou', 'Serge', '1984-11-27', 'M', '+237 6 15 16 17', 's.fokou@cs.ml', 'Physique-Chimie', 'Licence Physique', '2011-09-01', 'actif'),
('ENS016', 'Tchomga', 'Béatrice', '1992-06-01', 'F', '+237 6 16 17 18', 'b.tchomga@cs.ml', 'Calcul / Primaire', 'DPE Primaire', '2018-09-01', 'actif'),
('ENS017', 'Djamen', 'Ghislain', '1976-04-09', 'M', '+237 6 17 18 19', 'g.djamen@cs.ml', 'SVT', 'Doctorat Biologie', '2002-09-01', 'actif'),
('ENS018', 'Nana', 'Christiane', '1987-09-23', 'F', '+237 6 18 19 20', 'c.nana@cs.ml', 'Anglais', 'Licence Anglais', '2014-09-01', 'actif'),
('ENS019', 'Penda', 'Olivier', '1980-12-30', 'M', '+237 6 19 20 21', 'o.penda@cs.ml', 'Histoire-Géo', 'Maîtrise Géographie', '2009-09-01', 'actif'),
('ENS020', 'Zé', 'Irène', '1993-02-14', 'F', '+237 6 20 21 22', 'i.ze@cs.ml', 'Éveil / Maternelle', 'DPE Maternelle', '2019-09-01', 'actif');

-- ============================================================
-- 3. PARENTS / TUTEURS (35)
-- ============================================================
INSERT INTO parents (nom, prenom, telephone, email, adresse, profession) VALUES
('Mbarga', 'Paul', '+237 6 50 01 01', 'p.mbarga@email.cm', 'Quartier Briqueterie, Douala', 'Commerçant'),
('Nkoulou', 'Thérèse', '+237 6 50 02 02', 't.nkoulou@email.cm', 'Quartier Bonapriso, Douala', 'Infirmière'),
('Simo', 'André', '+237 6 50 03 03', 'a.simo@email.cm', 'Quartier Akwa, Douala', 'Chauffeur'),
('Mey', 'Cécile', '+237 6 50 04 04', 'c.mey@email.cm', 'Quartier Deido, Douala', 'Coiffeuse'),
('Tchakounte', 'Henri', '+237 6 50 05 05', 'h.tchakounte@email.cm', 'Quartier New Bell, Douala', 'Fonctionnaire'),
('Ngo So', 'Brigitte', '+237 6 50 06 06', 'b.ngoso@email.cm', 'Quartier Bonamoussadi, Douala', 'Enseignante'),
('Leukeu', 'Samuel', '+237 6 50 07 07', 's.leukeu@email.cm', 'Quartier Makepe, Douala', 'Menuisier'),
('Ngui', 'Pascaline', '+237 6 50 08 08', 'p.ngui@email.cm', 'Quartier Logbaba, Douala', 'Ménagère'),
('Biloa', 'Claude', '+237 6 50 09 09', 'c.biloa@email.cm', 'Rue 1.851, Yaoundé', 'Ingénieur'),
('Fame', 'Julienne', '+237 6 50 10 10', 'j.fame@email.cm', 'Quartier Bastos, Yaoundé', 'Avocate'),
('Eyenga', 'Michel', '+237 6 50 11 11', 'm.eyenga@email.cm', 'Quartier Nlonkak, Yaoundé', 'Médecin'),
('Nzé', 'Olivier', '+237 6 50 12 12', 'o.nze@email.cm', 'Quartier Mvog-Ada, Yaoundé', 'Pharmacien'),
('Owona', 'Clarisse', '+237 6 50 13 13', 'c.owona@email.cm', 'Quartier Tsinga, Yaoundé', 'Comptable'),
('Bikay', 'Serge', '+237 6 50 14 14', 's.bikay@email.cm', 'Quartier Omnisports, Yaoundé', 'Journaliste'),
('Mballa', 'Marguerite', '+237 6 50 15 15', 'm.mballa@email.cm', 'Quartier Ekounou, Yaoundé', 'Vendeuse'),
('Tagne', 'Emmanuel', '+237 6 50 16 16', 'e.tagne@email.cm', 'Carrefour Ndogpassi, Douala', 'Technicien'),
('Ndongo', 'Antoinette', '+237 6 50 17 17', 'a.ndongo@email.cm', 'Quartier Bali, Douala', 'Couturière'),
('Tchouamo', 'Blaise', '+237 6 50 18 18', 'b.tchouamo@email.cm', 'Village Bandjoun, Ouest', 'Agriculteur'),
('Kamdou', 'Marie', '+237 6 50 19 19', 'm.kamdou@email.cm', 'Quartier Bafoussam-Centre', 'Secrétaire'),
('Ngatcheu', 'Honoré', '+237 6 50 20 20', 'h.ngatcheu@email.cm', 'Quartier Nkongmondo, Douala', 'Magasinier'),
('Wandji', 'Alice', '+237 6 50 21 21', 'a.wandji@email.cm', 'Quartier Saker, Douala', 'Infirmière'),
('Pokam', 'Gervais', '+237 6 50 22 22', 'g.pokam@email.cm', 'Quartier Dschang-Centre', 'Professeur'),
('Njoumessi', 'Albertine', '+237 6 50 23 23', 'a.njoumessi@email.cm', 'Marché central, Bafoussam', 'Commerçante'),
('Fotso', 'Victor', '+237 6 50 24 24', 'v.fotso@email.cm', 'Quartier Kopp, Bafoussam', 'Chauffeur'),
('Sigha', 'Rosine', '+237 6 50 25 25', 'r.sigha@email.cm', 'Quartier Doumassi, Douala', 'Coiffeuse'),
('Kengne', 'Armand', '+237 6 50 26 26', 'a.kengne@email.cm', 'Carrefour Intendance, Yaoundé', 'Militaire'),
('Ngassam', 'Eugénie', '+237 6 50 27 27', 'e.ngassam@email.cm', 'Quartier Nsimalen, Yaoundé', 'Ménagère'),
('Moukouri', 'Stéphane', '+237 6 50 28 28', 's.moukouri@email.cm', 'Quartier Bonanjo, Douala', 'Banquier'),
('Aya', 'Sylvie', '+237 6 50 29 29', 's.aya@email.cm', 'Quartier Oyak, Douala', 'Assistante'),
('Nkoulou', 'Patrick', '+237 6 50 30 30', 'p.nkoulou2@email.cm', 'Quartier Mboppi, Douala', 'Soudeur'),
('Djotché', 'Ghislaine', '+237 6 50 31 31', 'g.djotche@email.cm', 'Quartier Madagascar, Douala', 'Fleuriste'),
('Yaya', 'Moussa', '+237 6 50 32 32', 'm.yaya@email.cm', 'Quartier Nord, Garoua', 'Éleveur'),
('Bouba', 'Aïchatou', '+237 6 50 33 33', 'a.bouba@email.cm', 'Quartier Bibemi, Garoua', 'Commerçante'),
('Hamidou', 'Ousmanou', '+237 6 50 34 34', 'o.hamidou@email.cm', 'Quartier Maroua-Centre', 'Tailleur'),
('Issa', 'Fati', '+237 6 50 35 35', 'f.issa@email.cm', 'Quartier Pitoa, Garoua', 'Ménagère');

-- ============================================================
-- 4. ÉLÈVES (85 élèves répartis dans les classes)
-- ============================================================
INSERT INTO eleves (matricule, nom, prenom, date_naissance, lieu_naissance, sexe, adresse, parent_id, statut) VALUES
-- PS A (5 élèves)
('ELV001', 'Mbarga', 'Karine', '2021-03-10', 'Douala', 'F', 'Quartier Briqueterie, Douala', 1, 'actif'),
('ELV002', 'Simo', 'David', '2021-07-22', 'Douala', 'M', 'Quartier Akwa, Douala', 3, 'actif'),
('ELV003', 'Tchakounte', 'Grâce', '2021-01-15', 'Douala', 'F', 'Quartier New Bell, Douala', 5, 'actif'),
('ELV004', 'Ngui', 'Fabrice', '2020-11-30', 'Douala', 'M', 'Quartier Logbaba, Douala', 8, 'actif'),
('ELV005', 'Tagne', 'Esther', '2021-05-08', 'Douala', 'F', 'Carrefour Ndogpassi, Douala', 16, 'actif'),
-- PS B (4 élèves)
('ELV006', 'Mey', 'Patrick', '2021-09-14', 'Douala', 'M', 'Quartier Deido, Douala', 4, 'actif'),
('ELV007', 'Leukeu', 'Naomi', '2021-12-01', 'Douala', 'F', 'Quartier Makepe, Douala', 7, 'actif'),
('ELV008', 'Ndongo', 'Josué', '2020-08-25', 'Douala', 'M', 'Quartier Bali, Douala', 17, 'actif'),
('ELV009', 'Wandji', 'Béatrice', '2021-06-17', 'Douala', 'F', 'Quartier Saker, Douala', 21, 'actif'),
-- MS A (5 élèves)
('ELV010', 'Mbarga', 'Joël', '2020-04-22', 'Douala', 'M', 'Quartier Briqueterie, Douala', 1, 'actif'),
('ELV011', 'Ngo So', 'Aminata', '2020-10-09', 'Douala', 'F', 'Quartier Bonamoussadi, Douala', 6, 'actif'),
('ELV012', 'Sigha', 'Clément', '2020-02-28', 'Douala', 'M', 'Quartier Doumassi, Douala', 25, 'actif'),
('ELV013', 'Djotché', 'Ruth', '2020-12-15', 'Douala', 'F', 'Quartier Madagascar, Douala', 31, 'actif'),
('ELV014', 'Yaya', 'Ibrahim', '2020-06-03', 'Garoua', 'M', 'Quartier Nord, Garoua', 32, 'actif'),
-- GS A (4 élèves)
('ELV015', 'Biloa', 'Sophie', '2019-08-11', 'Yaoundé', 'F', 'Rue 1.851, Yaoundé', 9, 'actif'),
('ELV016', 'Fame', 'Stéphane', '2019-03-30', 'Yaoundé', 'M', 'Quartier Bastos, Yaoundé', 10, 'actif'),
('ELV017', 'Pokam', 'Eunice', '2019-11-07', 'Dschang', 'F', 'Quartier Dschang-Centre', 22, 'actif'),
('ELV018', 'Issa', 'Moussa', '2019-05-19', 'Garoua', 'M', 'Quartier Pitoa, Garoua', 35, 'actif'),
-- CP A (5 élèves)
('ELV019', 'Nkoulou', 'Chloé', '2018-09-05', 'Douala', 'F', 'Quartier Bonapriso, Douala', 2, 'actif'),
('ELV020', 'Tchakounte', 'Arnold', '2018-12-18', 'Douala', 'M', 'Quartier New Bell, Douala', 5, 'actif'),
('ELV021', 'Biloa', 'Kevin', '2018-06-25', 'Yaoundé', 'M', 'Rue 1.851, Yaoundé', 9, 'actif'),
('ELV022', 'Nkoulou', 'Inès', '2019-01-20', 'Douala', 'F', 'Quartier Mboppi, Douala', 30, 'actif'),
('ELV023', 'Bouba', 'Aminou', '2018-04-13', 'Garoua', 'M', 'Quartier Bibemi, Garoua', 33, 'actif'),
-- CP B (4 élèves)
('ELV024', 'Mballa', 'Jules', '2018-10-30', 'Yaoundé', 'M', 'Quartier Ekounou, Yaoundé', 15, 'actif'),
('ELV025', 'Tchouamo', 'Prisca', '2019-02-14', 'Bandjoun', 'F', 'Village Bandjoun, Ouest', 18, 'actif'),
('ELV026', 'Ngatcheu', 'Benjamin', '2018-08-08', 'Douala', 'M', 'Quartier Nkongmondo, Douala', 20, 'actif'),
('ELV027', 'Hamidou', 'Awa', '2019-07-01', 'Maroua', 'F', 'Quartier Maroua-Centre', 34, 'actif'),
-- CE1 A (4 élèves)
('ELV028', 'Eyenga', 'Carine', '2017-05-12', 'Yaoundé', 'F', 'Quartier Nlonkak, Yaoundé', 11, 'actif'),
('ELV029', 'Kamdou', 'William', '2017-11-29', 'Bafoussam', 'M', 'Quartier Bafoussam-Centre', 19, 'actif'),
('ELV030', 'Njoumessi', 'Grâce', '2017-08-03', 'Bafoussam', 'F', 'Marché central, Bafoussam', 23, 'actif'),
('ELV031', 'Fotso', 'Danielle', '2017-06-20', 'Bafoussam', 'F', 'Quartier Kopp, Bafoussam', 24, 'actif'),
-- CE2 A (4 élèves)
('ELV032', 'Nzé', 'Gaël', '2016-09-17', 'Yaoundé', 'M', 'Quartier Mvog-Ada, Yaoundé', 12, 'actif'),
('ELV033', 'Moukouri', 'Chanel', '2016-03-04', 'Douala', 'F', 'Quartier Bonanjo, Douala', 28, 'actif'),
('ELV034', 'Aya', 'Ryan', '2016-12-25', 'Douala', 'M', 'Quartier Oyak, Douala', 29, 'actif'),
('ELV035', 'Kengne', 'Stella', '2016-07-09', 'Yaoundé', 'F', 'Carrefour Intendance, Yaoundé', 26, 'actif'),
-- CM1 A (4 élèves)
('ELV036', 'Owona', 'Félix', '2015-10-10', 'Yaoundé', 'M', 'Quartier Tsinga, Yaoundé', 13, 'actif'),
('ELV037', 'Bikay', 'Natacha', '2015-04-22', 'Yaoundé', 'F', 'Quartier Omnisports, Yaoundé', 14, 'actif'),
('ELV038', 'Fotso', 'Ruddy', '2015-02-15', 'Bafoussam', 'M', 'Quartier Kopp, Bafoussam', 24, 'actif'),
('ELV039', 'Simo', 'Ornella', '2015-11-01', 'Douala', 'F', 'Quartier Akwa, Douala', 3, 'actif'),
-- CM2 A (4 élèves)
('ELV040', 'Mbarga', 'Wilfried', '2014-01-28', 'Douala', 'M', 'Quartier Briqueterie, Douala', 1, 'actif'),
('ELV041', 'Ngassam', 'Denis', '2014-08-16', 'Yaoundé', 'M', 'Quartier Nsimalen, Yaoundé', 27, 'actif'),
('ELV042', 'Ngo So', 'Cynthia', '2014-05-30', 'Douala', 'F', 'Quartier Bonamoussadi, Douala', 6, 'actif'),
('ELV043', 'Mey', 'Valérie', '2014-09-09', 'Douala', 'F', 'Quartier Deido, Douala', 4, 'actif'),
-- CM2 B (4 élèves)
('ELV044', 'Leukeu', 'Serge', '2014-04-04', 'Douala', 'M', 'Quartier Makepe, Douala', 7, 'actif'),
('ELV045', 'Ngui', 'Dorcas', '2014-12-20', 'Douala', 'F', 'Quartier Logbaba, Douala', 8, 'actif'),
('ELV046', 'Tagne', 'Blaise', '2013-10-11', 'Douala', 'M', 'Carrefour Ndogpassi, Douala', 16, 'actif'),
('ELV047', 'Kamdou', 'Hervé', '2014-06-15', 'Bafoussam', 'M', 'Quartier Bafoussam-Centre', 19, 'actif'),
-- 6ème A (5 élèves)
('ELV048', 'Fame', 'Arnaud', '2013-03-22', 'Yaoundé', 'M', 'Quartier Bastos, Yaoundé', 10, 'actif'),
('ELV049', 'Eyenga', 'Diane', '2013-07-14', 'Yaoundé', 'F', 'Quartier Nlonkak, Yaoundé', 11, 'actif'),
('ELV050', 'Nzé', 'Clotaire', '2013-09-09', 'Yaoundé', 'M', 'Quartier Mvog-Ada, Yaoundé', 12, 'actif'),
('ELV051', 'Owona', 'Mireille', '2013-11-25', 'Yaoundé', 'F', 'Quartier Tsinga, Yaoundé', 13, 'actif'),
('ELV052', 'Mballa', 'Rigobert', '2013-01-06', 'Yaoundé', 'M', 'Quartier Ekounou, Yaoundé', 15, 'actif'),
-- 6ème B (4 élèves)
('ELV053', 'Moukouri', 'Alix', '2013-05-19', 'Douala', 'F', 'Quartier Bonanjo, Douala', 28, 'actif'),
('ELV054', 'Kengne', 'Pascal', '2013-08-30', 'Yaoundé', 'M', 'Carrefour Intendance, Yaoundé', 26, 'actif'),
('ELV055', 'Wandji', 'Junior', '2013-12-12', 'Douala', 'M', 'Quartier Saker, Douala', 21, 'actif'),
('ELV056', 'Bikay', 'Syndie', '2013-04-07', 'Yaoundé', 'F', 'Quartier Omnisports, Yaoundé', 14, 'actif'),
-- 5ème A (4 élèves)
('ELV057', 'Nkoulou', 'Yann', '2012-02-18', 'Douala', 'M', 'Quartier Bonapriso, Douala', 2, 'actif'),
('ELV058', 'Tchakounte', 'Benedicta', '2012-06-30', 'Douala', 'F', 'Quartier New Bell, Douala', 5, 'actif'),
('ELV059', 'Tchouamo', 'Ruth', '2012-10-05', 'Bandjoun', 'F', 'Village Bandjoun, Ouest', 18, 'actif'),
('ELV060', 'Ngatcheu', 'Clovis', '2012-01-15', 'Douala', 'M', 'Quartier Nkongmondo, Douala', 20, 'actif'),
-- 4ème A (4 élèves)
('ELV061', 'Fame', 'Géraldine', '2011-04-13', 'Yaoundé', 'F', 'Quartier Bastos, Yaoundé', 10, 'actif'),
('ELV062', 'Eyenga', 'Maxime', '2011-08-28', 'Yaoundé', 'M', 'Quartier Nlonkak, Yaoundé', 11, 'actif'),
('ELV063', 'Nzé', 'Arielle', '2011-12-20', 'Yaoundé', 'F', 'Quartier Mvog-Ada, Yaoundé', 12, 'actif'),
('ELV064', 'Moukouri', 'Lionel', '2011-02-09', 'Douala', 'M', 'Quartier Bonanjo, Douala', 28, 'actif'),
-- 3ème A (4 élèves)
('ELV065', 'Owona', 'Hervé', '2010-09-15', 'Yaoundé', 'M', 'Quartier Tsinga, Yaoundé', 13, 'actif'),
('ELV066', 'Bikay', 'Emmanuel', '2010-05-22', 'Yaoundé', 'M', 'Quartier Omnisports, Yaoundé', 14, 'actif'),
('ELV067', 'Mballa', 'Evelyne', '2010-11-08', 'Yaoundé', 'F', 'Quartier Ekounou, Yaoundé', 15, 'actif'),
('ELV068', 'Kengne', 'Viviane', '2010-03-30', 'Yaoundé', 'F', 'Carrefour Intendance, Yaoundé', 26, 'actif'),
-- 3ème B (3 élèves)
('ELV069', 'Aya', 'Dimitri', '2010-07-17', 'Douala', 'M', 'Quartier Oyak, Douala', 29, 'actif'),
('ELV070', 'Nkoulou', 'Patricia', '2010-01-25', 'Douala', 'F', 'Quartier Mboppi, Douala', 30, 'actif'),
('ELV071', 'Hamidou', 'Yaya', '2010-10-03', 'Maroua', 'M', 'Quartier Maroua-Centre', 34, 'actif'),
-- 2nde A (4 élèves)
('ELV072', 'Fame', 'Christian', '2009-06-11', 'Yaoundé', 'M', 'Quartier Bastos, Yaoundé', 10, 'actif'),
('ELV073', 'Eyenga', 'Prisca', '2009-10-24', 'Yaoundé', 'F', 'Quartier Nlonkak, Yaoundé', 11, 'actif'),
('ELV074', 'Nzé', 'Valentin', '2009-02-05', 'Yaoundé', 'M', 'Quartier Mvog-Ada, Yaoundé', 12, 'actif'),
('ELV075', 'Owona', 'Catherine', '2009-08-18', 'Yaoundé', 'F', 'Quartier Tsinga, Yaoundé', 13, 'actif'),
-- 2nde B (3 élèves)
('ELV076', 'Moukouri', 'Arlette', '2009-04-30', 'Douala', 'F', 'Quartier Bonanjo, Douala', 28, 'actif'),
('ELV077', 'Bikay', 'Rostand', '2009-12-12', 'Yaoundé', 'M', 'Quartier Omnisports, Yaoundé', 14, 'actif'),
('ELV078', 'Kengne', 'Lionel', '2009-09-01', 'Yaoundé', 'M', 'Carrefour Intendance, Yaoundé', 26, 'actif'),
-- 1ère S (4 élèves)
('ELV079', 'Mballa', 'Régis', '2008-07-08', 'Yaoundé', 'M', 'Quartier Ekounou, Yaoundé', 15, 'actif'),
('ELV080', 'Bikay', 'Inès', '2008-11-19', 'Yaoundé', 'F', 'Quartier Omnisports, Yaoundé', 14, 'actif'),
('ELV081', 'Owona', 'Blaise', '2008-03-14', 'Yaoundé', 'M', 'Quartier Tsinga, Yaoundé', 13, 'actif'),
('ELV082', 'Fame', 'Claude', '2008-09-25', 'Yaoundé', 'M', 'Quartier Bastos, Yaoundé', 10, 'actif'),
-- Tle S (3 élèves)
('ELV083', 'Nzé', 'Fleur', '2007-04-10', 'Yaoundé', 'F', 'Quartier Mvog-Ada, Yaoundé', 12, 'actif'),
('ELV084', 'Eyenga', 'Boris', '2007-08-02', 'Yaoundé', 'M', 'Quartier Nlonkak, Yaoundé', 11, 'actif'),
('ELV085', 'Moukouri', 'Noémie', '2007-01-28', 'Douala', 'F', 'Quartier Bonanjo, Douala', 28, 'actif');

-- ============================================================
-- 5. INSCRIPTIONS
-- ============================================================
INSERT INTO inscriptions (eleve_id, classe_id, annee_id, date_inscription, frais_scolarite, statut) VALUES
-- PS A (élèves 1-5, classes 1-5 = classe id 1)
(1, 1, @annee_id, '2025-09-05', 75000, 'actif'),
(2, 1, @annee_id, '2025-09-05', 75000, 'actif'),
(3, 1, @annee_id, '2025-09-08', 75000, 'actif'),
(4, 1, @annee_id, '2025-09-08', 75000, 'actif'),
(5, 1, @annee_id, '2025-09-10', 75000, 'actif'),
-- PS B (élèves 6-9, classe id 2)
(6, 2, @annee_id, '2025-09-05', 75000, 'actif'),
(7, 2, @annee_id, '2025-09-06', 75000, 'actif'),
(8, 2, @annee_id, '2025-09-08', 75000, 'actif'),
(9, 2, @annee_id, '2025-09-10', 75000, 'actif'),
-- MS A (élèves 10-14, classe id 3)
(10, 3, @annee_id, '2025-09-04', 85000, 'actif'),
(11, 3, @annee_id, '2025-09-04', 85000, 'actif'),
(12, 3, @annee_id, '2025-09-07', 85000, 'actif'),
(13, 3, @annee_id, '2025-09-07', 85000, 'actif'),
(14, 3, @annee_id, '2025-09-09', 85000, 'actif'),
-- GS A (élèves 15-18, classe id 4)
(15, 4, @annee_id, '2025-09-04', 95000, 'actif'),
(16, 4, @annee_id, '2025-09-04', 95000, 'actif'),
(17, 4, @annee_id, '2025-09-07', 95000, 'actif'),
(18, 4, @annee_id, '2025-09-09', 95000, 'actif'),
-- CP A (élèves 19-23, classe id 5)
(19, 5, @annee_id, '2025-09-03', 120000, 'actif'),
(20, 5, @annee_id, '2025-09-03', 120000, 'actif'),
(21, 5, @annee_id, '2025-09-05', 120000, 'actif'),
(22, 5, @annee_id, '2025-09-05', 120000, 'actif'),
(23, 5, @annee_id, '2025-09-08', 120000, 'actif'),
-- CP B (élèves 24-27, classe id 6)
(24, 6, @annee_id, '2025-09-03', 120000, 'actif'),
(25, 6, @annee_id, '2025-09-05', 120000, 'actif'),
(26, 6, @annee_id, '2025-09-05', 120000, 'actif'),
(27, 6, @annee_id, '2025-09-08', 120000, 'actif'),
-- CE1 A (élèves 28-31, classe id 7)
(28, 7, @annee_id, '2025-09-02', 130000, 'actif'),
(29, 7, @annee_id, '2025-09-02', 130000, 'actif'),
(30, 7, @annee_id, '2025-09-04', 130000, 'actif'),
(31, 7, @annee_id, '2025-09-04', 130000, 'actif'),
-- CE2 A (élèves 32-35, classe id 8)
(32, 8, @annee_id, '2025-09-02', 130000, 'actif'),
(33, 8, @annee_id, '2025-09-03', 130000, 'actif'),
(34, 8, @annee_id, '2025-09-03', 130000, 'actif'),
(35, 8, @annee_id, '2025-09-05', 130000, 'actif'),
-- CM1 A (élèves 36-39, classe id 9)
(36, 9, @annee_id, '2025-09-02', 140000, 'actif'),
(37, 9, @annee_id, '2025-09-02', 140000, 'actif'),
(38, 9, @annee_id, '2025-09-04', 140000, 'actif'),
(39, 9, @annee_id, '2025-09-04', 140000, 'actif'),
-- CM2 A (élèves 40-43, classe id 10)
(40, 10, @annee_id, '2025-09-01', 150000, 'actif'),
(41, 10, @annee_id, '2025-09-01', 150000, 'actif'),
(42, 10, @annee_id, '2025-09-03', 150000, 'actif'),
(43, 10, @annee_id, '2025-09-03', 150000, 'actif'),
-- CM2 B (élèves 44-47, classe id 11)
(44, 11, @annee_id, '2025-09-01', 150000, 'actif'),
(45, 11, @annee_id, '2025-09-03', 150000, 'actif'),
(46, 11, @annee_id, '2025-09-03', 150000, 'actif'),
(47, 11, @annee_id, '2025-09-05', 150000, 'actif'),
-- 6ème A (élèves 48-52, classe id 12)
(48, 12, @annee_id, '2025-09-01', 180000, 'actif'),
(49, 12, @annee_id, '2025-09-01', 180000, 'actif'),
(50, 12, @annee_id, '2025-09-02', 180000, 'actif'),
(51, 12, @annee_id, '2025-09-02', 180000, 'actif'),
(52, 12, @annee_id, '2025-09-04', 180000, 'actif'),
-- 6ème B (élèves 53-56, classe id 13)
(53, 13, @annee_id, '2025-09-01', 180000, 'actif'),
(54, 13, @annee_id, '2025-09-02', 180000, 'actif'),
(55, 13, @annee_id, '2025-09-02', 180000, 'actif'),
(56, 13, @annee_id, '2025-09-04', 180000, 'actif'),
-- 5ème A (élèves 57-60, classe id 14)
(57, 14, @annee_id, '2025-09-01', 180000, 'actif'),
(58, 14, @annee_id, '2025-09-01', 180000, 'actif'),
(59, 14, @annee_id, '2025-09-03', 180000, 'actif'),
(60, 14, @annee_id, '2025-09-03', 180000, 'actif'),
-- 4ème A (élèves 61-64, classe id 15)
(61, 15, @annee_id, '2025-09-01', 200000, 'actif'),
(62, 15, @annee_id, '2025-09-01', 200000, 'actif'),
(63, 15, @annee_id, '2025-09-03', 200000, 'actif'),
(64, 15, @annee_id, '2025-09-03', 200000, 'actif'),
-- 3ème A (élèves 65-68, classe id 16)
(65, 16, @annee_id, '2025-09-01', 200000, 'actif'),
(66, 16, @annee_id, '2025-09-01', 200000, 'actif'),
(67, 16, @annee_id, '2025-09-03', 200000, 'actif'),
(68, 16, @annee_id, '2025-09-03', 200000, 'actif'),
-- 3ème B (élèves 69-71, classe id 17)
(69, 17, @annee_id, '2025-09-01', 200000, 'actif'),
(70, 17, @annee_id, '2025-09-03', 200000, 'actif'),
(71, 17, @annee_id, '2025-09-05', 200000, 'actif'),
-- 2nde A (élèves 72-75, classe id 18)
(72, 18, @annee_id, '2025-09-01', 250000, 'actif'),
(73, 18, @annee_id, '2025-09-01', 250000, 'actif'),
(74, 18, @annee_id, '2025-09-02', 250000, 'actif'),
(75, 18, @annee_id, '2025-09-02', 250000, 'actif'),
-- 2nde B (élèves 76-78, classe id 19)
(76, 19, @annee_id, '2025-09-01', 250000, 'actif'),
(77, 19, @annee_id, '2025-09-02', 250000, 'actif'),
(78, 19, @annee_id, '2025-09-02', 250000, 'actif'),
-- 1ère S (élèves 79-82, classe id 20)
(79, 20, @annee_id, '2025-09-01', 280000, 'actif'),
(80, 20, @annee_id, '2025-09-01', 280000, 'actif'),
(81, 20, @annee_id, '2025-09-02', 280000, 'actif'),
(82, 20, @annee_id, '2025-09-02', 280000, 'actif'),
-- Tle S (élèves 83-85, classe id 21)
(83, 21, @annee_id, '2025-09-01', 300000, 'actif'),
(84, 21, @annee_id, '2025-09-01', 300000, 'actif'),
(85, 21, @annee_id, '2025-09-02', 300000, 'actif');

-- ============================================================
-- 6. AFFECTATIONS (enseignant → matière → classe)
-- ============================================================
-- Maternelle : Éveil(1), Lecture(2), Calcul(3) — ids matières 1,2,3
-- Primaire : FR(4), MATH(5), HIST(6), SCI(7), EPS(8), ART(9)
-- Collège : FRC(10), MATHC(11), PC(12), SVT(13), HISTG(14), ANG(15), EPS(8), ART(9)
-- Lycée : PHILO(16), MATHL(17), PCL(18), SVTL(19), FRL(20), ANGL(21), EPS(8), ART(9)

INSERT INTO affectations (enseignant_id, matiere_id, classe_id, annee_id, heures_semaine) VALUES
-- Maternelle
(8, 1, 1, @annee_id, 6),   -- Tchoumi Aminatou → Éveil → PS A
(8, 1, 2, @annee_id, 6),   -- Tchoumi Aminatou → Éveil → PS B
(8, 2, 3, @annee_id, 4),   -- Tchoumi Aminatou → Lecture → MS A
(20, 1, 3, @annee_id, 2),  -- Zé Irène → Éveil → MS A
(20, 1, 4, @annee_id, 6),  -- Zé Irène → Éveil → GS A
(20, 2, 4, @annee_id, 4),  -- Zé Irène → Lecture → GS A
(16, 3, 1, @annee_id, 3),  -- Tchomga Béatrice → Calcul → PS A
(16, 3, 2, @annee_id, 3),  -- Tchomga Béatrice → Calcul → PS B
(16, 3, 3, @annee_id, 3),  -- Tchomga Béatrice → Calcul → MS A
(16, 3, 4, @annee_id, 3),  -- Tchomga Béatrice → Calcul → GS A
-- Primaire CP A, CP B
(12, 4, 5, @annee_id, 6),  -- Biya Esther → Français → CP A
(12, 4, 6, @annee_id, 6),  -- Biya Esther → Français → CP B
(12, 2, 5, @annee_id, 4),  -- Biya Esther → Lecture → CP A
(16, 5, 5, @annee_id, 6),  -- Tchomga Béatrice → Maths → CP A
(16, 5, 6, @annee_id, 6),  -- Tchomga Béatrice → Maths → CP B
-- Primaire CE1, CE2
(14, 4, 7, @annee_id, 6),  -- Simeni Carine → Français → CE1 A
(14, 4, 8, @annee_id, 6),  -- Simeni Carine → Français → CE2 A
(1, 5, 7, @annee_id, 6),   -- Diallo Amadou → Maths → CE1 A
(1, 5, 8, @annee_id, 6),   -- Diallo Amadou → Maths → CE2 A
(5, 6, 7, @annee_id, 3),   -- Kamga Jean-Pierre → Histoire-Géo → CE1 A
(5, 6, 8, @annee_id, 3),   -- Kamga Jean-Pierre → Histoire-Géo → CE2 A
(4, 7, 7, @annee_id, 3),   -- Fotso Chantal → Sciences → CE1 A
(4, 7, 8, @annee_id, 3),   -- Fotso Chantal → Sciences → CE2 A
-- Primaire CM1, CM2
(14, 4, 9, @annee_id, 6),  -- Simeni Carine → Français → CM1 A
(2, 4, 10, @annee_id, 6),  -- Ngassa Marie-Claire → Français → CM2 A
(2, 4, 11, @annee_id, 6),  -- Ngassa Marie-Claire → Français → CM2 B
(13, 5, 9, @annee_id, 6),  -- Nganou Dieudonné → Maths → CM1 A
(13, 5, 10, @annee_id, 6), -- Nganou Dieudonné → Maths → CM2 A
(13, 5, 11, @annee_id, 6), -- Nganou Dieudonné → Maths → CM2 B
(5, 6, 9, @annee_id, 3),   -- Kamga Jean-Pierre → Histoire-Géo → CM1 A
(19, 6, 10, @annee_id, 3), -- Penda Olivier → Histoire-Géo → CM2 A
(19, 6, 11, @annee_id, 3), -- Penda Olivier → Histoire-Géo → CM2 B
(4, 7, 9, @annee_id, 3),   -- Fotso Chantal → Sciences → CM1 A
-- Collège 6ème
(2, 10, 12, @annee_id, 5),  -- Ngassa Marie-Claire → Français → 6ème A
(2, 10, 13, @annee_id, 5),  -- Ngassa Marie-Claire → Français → 6ème B
(1, 11, 12, @annee_id, 5),  -- Diallo Amadou → Maths → 6ème A
(1, 11, 13, @annee_id, 5),  -- Diallo Amadou → Maths → 6ème B
(3, 12, 12, @annee_id, 4),  -- Tchinda Patrick → PC → 6ème A
(3, 12, 13, @annee_id, 4),  -- Tchinda Patrick → PC → 6ème B
(4, 13, 12, @annee_id, 3),  -- Fotso Chantal → SVT → 6ème A
(4, 13, 13, @annee_id, 3),  -- Fotso Chantal → SVT → 6ème B
(5, 14, 12, @annee_id, 3),  -- Kamga Jean-Pierre → Hist-Géo → 6ème A
(5, 14, 13, @annee_id, 3),  -- Kamga Jean-Pierre → Hist-Géo → 6ème B
(6, 15, 12, @annee_id, 3),  -- Mbeleck Sophie → Anglais → 6ème A
(6, 15, 13, @annee_id, 3),  -- Mbeleck Sophie → Anglais → 6ème B
-- Collège 5ème
(2, 10, 14, @annee_id, 5),  -- Ngassa → Français → 5ème A
(13, 11, 14, @annee_id, 5), -- Nganou → Maths → 5ème A
(15, 12, 14, @annee_id, 4), -- Fokou Serge → PC → 5ème A
(17, 13, 14, @annee_id, 3), -- Djamen Ghislain → SVT → 5ème A
(19, 14, 14, @annee_id, 3), -- Penda Olivier → Hist-Géo → 5ème A
(18, 15, 14, @annee_id, 3), -- Nana Christiane → Anglais → 5ème A
-- Collège 4ème
(14, 10, 15, @annee_id, 5), -- Simeni Carine → Français → 4ème A
(1, 11, 15, @annee_id, 5),  -- Diallo Amadou → Maths → 4ème A
(3, 12, 15, @annee_id, 4),  -- Tchinda Patrick → PC → 4ème A
(17, 13, 15, @annee_id, 3), -- Djamen Ghislain → SVT → 4ème A
(19, 14, 15, @annee_id, 3), -- Penda Olivier → Hist-Géo → 4ème A
(18, 15, 15, @annee_id, 3), -- Nana Christiane → Anglais → 4ème A
-- Collège 3ème
(2, 10, 16, @annee_id, 5),  -- Ngassa → Français → 3ème A
(2, 10, 17, @annee_id, 5),  -- Ngassa → Français → 3ème B
(13, 11, 16, @annee_id, 5), -- Nganou → Maths → 3ème A
(13, 11, 17, @annee_id, 5), -- Nganou → Maths → 3ème B
(15, 12, 16, @annee_id, 4), -- Fokou → PC → 3ème A
(15, 12, 17, @annee_id, 4), -- Fokou → PC → 3ème B
(17, 13, 16, @annee_id, 3), -- Djamen → SVT → 3ème A
(17, 13, 17, @annee_id, 3), -- Djamen → SVT → 3ème B
(5, 14, 16, @annee_id, 3),  -- Kamga → Hist-Géo → 3ème A
(5, 14, 17, @annee_id, 3),  -- Kamga → Hist-Géo → 3ème B
(6, 15, 16, @annee_id, 3),  -- Mbeleck → Anglais → 3ème A
(6, 15, 17, @annee_id, 3),  -- Mbeleck → Anglais → 3ème B
-- Lycée 2nde
(2, 20, 18, @annee_id, 4),  -- Ngassa → Français → 2nde A
(2, 20, 19, @annee_id, 4),  -- Ngassa → Français → 2nde B
(1, 17, 18, @annee_id, 5),  -- Diallo → Maths → 2nde A
(1, 17, 19, @annee_id, 5),  -- Diallo → Maths → 2nde B
(3, 18, 18, @annee_id, 5),  -- Tchinda → PC → 2nde A
(3, 18, 19, @annee_id, 5),  -- Tchinda → PC → 2nde B
(17, 19, 18, @annee_id, 4), -- Djamen → SVT → 2nde A
(17, 19, 19, @annee_id, 4), -- Djamen → SVT → 2nde B
(18, 21, 18, @annee_id, 3), -- Nana → Anglais → 2nde A
(18, 21, 19, @annee_id, 3), -- Nana → Anglais → 2nde B
-- Lycée 1ère S
(7, 16, 20, @annee_id, 4),  -- Ngo Um Emmanuel → Philo → 1ère S
(1, 17, 20, @annee_id, 6),  -- Diallo → Maths → 1ère S
(3, 18, 20, @annee_id, 6),  -- Tchinda → PC → 1ère S
(17, 19, 20, @annee_id, 5), -- Djamen → SVT → 1ère S
(2, 20, 20, @annee_id, 4),  -- Ngassa → Français → 1ère S
(18, 21, 20, @annee_id, 3), -- Nana → Anglais → 1ère S
-- Lycée Tle S
(7, 16, 21, @annee_id, 4),  -- Ngo Um → Philo → Tle S
(13, 17, 21, @annee_id, 6), -- Nganou → Maths → Tle S
(15, 18, 21, @annee_id, 6), -- Fokou → PC → Tle S
(4, 19, 21, @annee_id, 5),  -- Fotso → SVT → Tle S
(2, 20, 21, @annee_id, 4),  -- Ngassa → Français → Tle S
(6, 21, 21, @annee_id, 3),  -- Mbeleck → Anglais → Tle S
-- EPS et Arts (partagés)
(9, 8, 5, @annee_id, 2),  -- Wamba → EPS → CP A
(9, 8, 6, @annee_id, 2),  -- Wamba → EPS → CP B
(9, 8, 7, @annee_id, 2),  -- Wamba → EPS → CE1 A
(9, 8, 8, @annee_id, 2),  -- Wamba → EPS → CE2 A
(9, 8, 9, @annee_id, 2),  -- Wamba → EPS → CM1 A
(9, 8, 10, @annee_id, 2), -- Wamba → EPS → CM2 A
(9, 8, 11, @annee_id, 2), -- Wamba → EPS → CM2 B
(9, 8, 12, @annee_id, 2), -- Wamba → EPS → 6ème A
(9, 8, 13, @annee_id, 2), -- Wamba → EPS → 6ème B
(9, 8, 14, @annee_id, 2), -- Wamba → EPS → 5ème A
(9, 8, 15, @annee_id, 2), -- Wamba → EPS → 4ème A
(9, 8, 16, @annee_id, 2), -- Wamba → EPS → 3ème A
(9, 8, 18, @annee_id, 2), -- Wamba → EPS → 2nde A
(9, 8, 20, @annee_id, 2), -- Wamba → EPS → 1ère S
(9, 8, 21, @annee_id, 2), -- Wamba → EPS → Tle S
(10, 9, 5, @annee_id, 1),  -- Kuété → Arts → CP A
(10, 9, 7, @annee_id, 1),  -- Kuété → Arts → CE1 A
(10, 9, 9, @annee_id, 1),  -- Kuété → Arts → CM1 A
(10, 9, 12, @annee_id, 1), -- Kuété → Arts → 6ème A
(10, 9, 14, @annee_id, 1), -- Kuété → Arts → 5ème A
(10, 9, 18, @annee_id, 1); -- Kuété → Arts → 2nde A

-- ============================================================
-- 7. NOTES (1er Trimestre — échantillon pour classes Collège/Lycée)
-- ============================================================
-- On insère des notes pour le 1er trimestre pour les classes du collège et lycée
-- Chaque élève a des notes variables pour créer un classement réaliste

-- 6ème A — Français(id:10), Maths(id:11), PC(id:12), SVT(id:13), Hist-Géo(id:14), Anglais(id:15)
INSERT INTO notes (eleve_id, matiere_id, classe_id, periode_id, annee_id, note, note_max, type_eval, date_eval) VALUES
-- Elève 48 (Fame Arnaud) — bon élève
(48,10,12,@trim1,@annee_id,16.00,20,'composition','2025-11-15'),
(48,11,12,@trim1,@annee_id,18.00,20,'composition','2025-11-16'),
(48,12,12,@trim1,@annee_id,15.50,20,'composition','2025-11-17'),
(48,13,12,@trim1,@annee_id,14.00,20,'composition','2025-11-18'),
(48,14,12,@trim1,@annee_id,13.50,20,'composition','2025-11-19'),
(48,15,12,@trim1,@annee_id,17.00,20,'composition','2025-11-20'),
(48,10,12,@trim1,@annee_id,14.00,20,'devoir','2025-10-10'),
(48,11,12,@trim1,@annee_id,16.50,20,'devoir','2025-10-11'),
-- Elève 49 (Eyenga Diane) — élève moyenne
(49,10,12,@trim1,@annee_id,12.50,20,'composition','2025-11-15'),
(49,11,12,@trim1,@annee_id,11.00,20,'composition','2025-11-16'),
(49,12,12,@trim1,@annee_id,10.50,20,'composition','2025-11-17'),
(49,13,12,@trim1,@annee_id,9.50,20,'composition','2025-11-18'),
(49,14,12,@trim1,@annee_id,11.00,20,'composition','2025-11-19'),
(49,15,12,@trim1,@annee_id,13.00,20,'composition','2025-11-20'),
-- Elève 50 (Nzé Clotaire) — très bon
(50,10,12,@trim1,@annee_id,17.50,20,'composition','2025-11-15'),
(50,11,12,@trim1,@annee_id,19.00,20,'composition','2025-11-16'),
(50,12,12,@trim1,@annee_id,16.00,20,'composition','2025-11-17'),
(50,13,12,@trim1,@annee_id,15.50,20,'composition','2025-11-18'),
(50,14,12,@trim1,@annee_id,14.00,20,'composition','2025-11-19'),
(50,15,12,@trim1,@annee_id,16.50,20,'composition','2025-11-20'),
(50,10,12,@trim1,@annee_id,15.50,20,'devoir','2025-10-10'),
(50,11,12,@trim1,@annee_id,17.00,20,'devoir','2025-10-11'),
-- Elève 51 (Owona Mireille) — moyen
(51,10,12,@trim1,@annee_id,10.00,20,'composition','2025-11-15'),
(51,11,12,@trim1,@annee_id,9.00,20,'composition','2025-11-16'),
(51,12,12,@trim1,@annee_id,8.50,20,'composition','2025-11-17'),
(51,13,12,@trim1,@annee_id,11.00,20,'composition','2025-11-18'),
(51,14,12,@trim1,@annee_id,10.50,20,'composition','2025-11-19'),
(51,15,12,@trim1,@annee_id,12.00,20,'composition','2025-11-20'),
-- Elève 52 (Mballa Rigobert) — faible
(52,10,12,@trim1,@annee_id,7.50,20,'composition','2025-11-15'),
(52,11,12,@trim1,@annee_id,6.00,20,'composition','2025-11-16'),
(52,12,12,@trim1,@annee_id,8.00,20,'composition','2025-11-17'),
(52,13,12,@trim1,@annee_id,7.00,20,'composition','2025-11-18'),
(52,14,12,@trim1,@annee_id,9.00,20,'composition','2025-11-19'),
(52,15,12,@trim1,@annee_id,10.50,20,'composition','2025-11-20'),

-- 3ème A — même structure
(65,10,16,@trim1,@annee_id,15.00,20,'composition','2025-11-15'),
(65,11,16,@trim1,@annee_id,14.00,20,'composition','2025-11-16'),
(65,12,16,@trim1,@annee_id,13.50,20,'composition','2025-11-17'),
(65,13,16,@trim1,@annee_id,12.00,20,'composition','2025-11-18'),
(65,14,16,@trim1,@annee_id,11.50,20,'composition','2025-11-19'),
(65,15,16,@trim1,@annee_id,14.50,20,'composition','2025-11-20'),
(66,10,16,@trim1,@annee_id,11.50,20,'composition','2025-11-15'),
(66,11,16,@trim1,@annee_id,10.00,20,'composition','2025-11-16'),
(66,12,16,@trim1,@annee_id,9.50,20,'composition','2025-11-17'),
(66,13,16,@trim1,@annee_id,8.00,20,'composition','2025-11-18'),
(66,14,16,@trim1,@annee_id,10.50,20,'composition','2025-11-19'),
(66,15,16,@trim1,@annee_id,12.00,20,'composition','2025-11-20'),
(67,10,16,@trim1,@annee_id,18.00,20,'composition','2025-11-15'),
(67,11,16,@trim1,@annee_id,17.50,20,'composition','2025-11-16'),
(67,12,16,@trim1,@annee_id,16.00,20,'composition','2025-11-17'),
(67,13,16,@trim1,@annee_id,15.50,20,'composition','2025-11-18'),
(67,14,16,@trim1,@annee_id,14.00,20,'composition','2025-11-19'),
(67,15,16,@trim1,@annee_id,16.50,20,'composition','2025-11-20'),
(67,10,16,@trim1,@annee_id,16.00,20,'devoir','2025-10-10'),
(67,11,16,@trim1,@annee_id,15.50,20,'devoir','2025-10-11'),
(68,10,16,@trim1,@annee_id,8.50,20,'composition','2025-11-15'),
(68,11,16,@trim1,@annee_id,7.00,20,'composition','2025-11-16'),
(68,12,16,@trim1,@annee_id,6.50,20,'composition','2025-11-17'),
(68,13,16,@trim1,@annee_id,9.00,20,'composition','2025-11-18'),
(68,14,16,@trim1,@annee_id,8.00,20,'composition','2025-11-19'),
(68,15,16,@trim1,@annee_id,11.00,20,'composition','2025-11-20'),

-- Tle S — Français(20), Maths(17), PC(18), SVT(19), Philo(16), Anglais(21)
(83,16,21,@trim1,@annee_id,14.00,20,'composition','2025-11-15'),
(83,17,21,@trim1,@annee_id,16.50,20,'composition','2025-11-16'),
(83,18,21,@trim1,@annee_id,15.00,20,'composition','2025-11-17'),
(83,19,21,@trim1,@annee_id,14.50,20,'composition','2025-11-18'),
(83,20,21,@trim1,@annee_id,13.00,20,'composition','2025-11-19'),
(83,21,21,@trim1,@annee_id,15.50,20,'composition','2025-11-20'),
(84,16,21,@trim1,@annee_id,11.00,20,'composition','2025-11-15'),
(84,17,21,@trim1,@annee_id,9.50,20,'composition','2025-11-16'),
(84,18,21,@trim1,@annee_id,8.50,20,'composition','2025-11-17'),
(84,19,21,@trim1,@annee_id,10.00,20,'composition','2025-11-18'),
(84,20,21,@trim1,@annee_id,12.00,20,'composition','2025-11-19'),
(84,21,21,@trim1,@annee_id,11.50,20,'composition','2025-11-20'),
(85,16,21,@trim1,@annee_id,17.00,20,'composition','2025-11-15'),
(85,17,21,@trim1,@annee_id,18.50,20,'composition','2025-11-16'),
(85,18,21,@trim1,@annee_id,17.00,20,'composition','2025-11-17'),
(85,19,21,@trim1,@annee_id,16.50,20,'composition','2025-11-18'),
(85,20,21,@trim1,@annee_id,15.50,20,'composition','2025-11-19'),
(85,21,21,@trim1,@annee_id,16.00,20,'composition','2025-11-20'),
(85,17,21,@trim1,@annee_id,19.00,20,'devoir','2025-10-10'),
(85,18,21,@trim1,@annee_id,18.00,20,'devoir','2025-10-11'),

-- 2nde A — notes
(72,17,18,@trim1,@annee_id,14.00,20,'composition','2025-11-16'),
(72,18,18,@trim1,@annee_id,13.50,20,'composition','2025-11-17'),
(72,19,18,@trim1,@annee_id,12.00,20,'composition','2025-11-18'),
(72,20,18,@trim1,@annee_id,11.50,20,'composition','2025-11-19'),
(72,21,18,@trim1,@annee_id,14.00,20,'composition','2025-11-20'),
(73,17,18,@trim1,@annee_id,9.50,20,'composition','2025-11-16'),
(73,18,18,@trim1,@annee_id,8.00,20,'composition','2025-11-17'),
(73,19,18,@trim1,@annee_id,10.50,20,'composition','2025-11-18'),
(73,20,18,@trim1,@annee_id,13.00,20,'composition','2025-11-19'),
(73,21,18,@trim1,@annee_id,12.50,20,'composition','2025-11-20'),
(74,17,18,@trim1,@annee_id,17.50,20,'composition','2025-11-16'),
(74,18,18,@trim1,@annee_id,16.00,20,'composition','2025-11-17'),
(74,19,18,@trim1,@annee_id,15.50,20,'composition','2025-11-18'),
(74,20,18,@trim1,@annee_id,14.00,20,'composition','2025-11-19'),
(74,21,18,@trim1,@annee_id,15.00,20,'composition','2025-11-20'),
(75,17,18,@trim1,@annee_id,11.00,20,'composition','2025-11-16'),
(75,18,18,@trim1,@annee_id,10.50,20,'composition','2025-11-17'),
(75,19,18,@trim1,@annee_id,9.00,20,'composition','2025-11-18'),
(75,20,18,@trim1,@annee_id,12.50,20,'composition','2025-11-19'),
(75,21,18,@trim1,@annee_id,11.00,20,'composition','2025-11-20'),

-- 1ère S — notes
(79,16,20,@trim1,@annee_id,13.00,20,'composition','2025-11-15'),
(79,17,20,@trim1,@annee_id,15.50,20,'composition','2025-11-16'),
(79,18,20,@trim1,@annee_id,14.00,20,'composition','2025-11-17'),
(79,19,20,@trim1,@annee_id,13.50,20,'composition','2025-11-18'),
(79,20,20,@trim1,@annee_id,12.00,20,'composition','2025-11-19'),
(79,21,20,@trim1,@annee_id,14.50,20,'composition','2025-11-20'),
(80,16,20,@trim1,@annee_id,16.00,20,'composition','2025-11-15'),
(80,17,20,@trim1,@annee_id,18.00,20,'composition','2025-11-16'),
(80,18,20,@trim1,@annee_id,17.50,20,'composition','2025-11-17'),
(80,19,20,@trim1,@annee_id,16.00,20,'composition','2025-11-18'),
(80,20,20,@trim1,@annee_id,15.00,20,'composition','2025-11-19'),
(80,21,20,@trim1,@annee_id,16.50,20,'composition','2025-11-20'),
(80,17,20,@trim1,@annee_id,19.00,20,'devoir','2025-10-10'),
(80,18,20,@trim1,@annee_id,18.00,20,'devoir','2025-10-11'),
(81,16,20,@trim1,@annee_id,10.50,20,'composition','2025-11-15'),
(81,17,20,@trim1,@annee_id,9.00,20,'composition','2025-11-16'),
(81,18,20,@trim1,@annee_id,8.50,20,'composition','2025-11-17'),
(81,19,20,@trim1,@annee_id,11.00,20,'composition','2025-11-18'),
(81,20,20,@trim1,@annee_id,13.00,20,'composition','2025-11-19'),
(81,21,20,@trim1,@annee_id,12.00,20,'composition','2025-11-20'),
(82,16,20,@trim1,@annee_id,7.50,20,'composition','2025-11-15'),
(82,17,20,@trim1,@annee_id,6.00,20,'composition','2025-11-16'),
(82,18,20,@trim1,@annee_id,5.50,20,'composition','2025-11-17'),
(82,19,20,@trim1,@annee_id,8.00,20,'composition','2025-11-18'),
(82,20,20,@trim1,@annee_id,10.50,20,'composition','2025-11-19'),
(82,21,20,@trim1,@annee_id,9.00,20,'composition','2025-11-20');

-- ============================================================
-- 8. EMPLOI DU TEMPS (grille hebdomadaire — échantillon 6ème A & 3ème A)
-- ============================================================
INSERT INTO emploi_temps (classe_id, annee_id, affectation_id, jour, heure_debut, heure_fin, salle) VALUES
-- 6ème A (classe 12)
-- Lundi
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=2 AND matiere_id=10 AND classe_id=12 AND annee_id=@annee_id), 'Lundi', '07:30:00', '09:30:00', 'Salle 101'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=1 AND matiere_id=11 AND classe_id=12 AND annee_id=@annee_id), 'Lundi', '09:45:00', '11:45:00', 'Salle 101'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=9 AND matiere_id=8 AND classe_id=12 AND annee_id=@annee_id), 'Lundi', '12:00:00', '13:00:00', 'Terrain'),
-- Mardi
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=3 AND matiere_id=12 AND classe_id=12 AND annee_id=@annee_id), 'Mardi', '07:30:00', '09:30:00', 'Labo PC'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=5 AND matiere_id=14 AND classe_id=12 AND annee_id=@annee_id), 'Mardi', '09:45:00', '11:45:00', 'Salle 101'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=6 AND matiere_id=15 AND classe_id=12 AND annee_id=@annee_id), 'Mardi', '12:00:00', '13:00:00', 'Salle 101'),
-- Mercredi
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=4 AND matiere_id=13 AND classe_id=12 AND annee_id=@annee_id), 'Mercredi', '07:30:00', '09:30:00', 'Labo SVT'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=2 AND matiere_id=10 AND classe_id=12 AND annee_id=@annee_id), 'Mercredi', '09:45:00', '11:45:00', 'Salle 101'),
-- Jeudi
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=1 AND matiere_id=11 AND classe_id=12 AND annee_id=@annee_id), 'Jeudi', '07:30:00', '09:30:00', 'Salle 101'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=10 AND matiere_id=9 AND classe_id=12 AND annee_id=@annee_id), 'Jeudi', '09:45:00', '10:45:00', 'Salle Art'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=3 AND matiere_id=12 AND classe_id=12 AND annee_id=@annee_id), 'Jeudi', '11:00:00', '12:00:00', 'Labo PC'),
-- Vendredi
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=6 AND matiere_id=15 AND classe_id=12 AND annee_id=@annee_id), 'Vendredi', '07:30:00', '09:30:00', 'Salle 101'),
(12, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=4 AND matiere_id=13 AND classe_id=12 AND annee_id=@annee_id), 'Vendredi', '09:45:00', '11:45:00', 'Labo SVT'),

-- 3ème A (classe 16)
-- Lundi
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=2 AND matiere_id=10 AND classe_id=16 AND annee_id=@annee_id), 'Lundi', '07:30:00', '09:30:00', 'Salle 201'),
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=13 AND matiere_id=11 AND classe_id=16 AND annee_id=@annee_id), 'Lundi', '09:45:00', '11:45:00', 'Salle 201'),
-- Mardi
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=15 AND matiere_id=12 AND classe_id=16 AND annee_id=@annee_id), 'Mardi', '07:30:00', '09:30:00', 'Labo PC'),
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=17 AND matiere_id=13 AND classe_id=16 AND annee_id=@annee_id), 'Mardi', '09:45:00', '11:45:00', 'Labo SVT'),
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=9 AND matiere_id=8 AND classe_id=16 AND annee_id=@annee_id), 'Mardi', '12:00:00', '13:00:00', 'Terrain'),
-- Mercredi
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=5 AND matiere_id=14 AND classe_id=16 AND annee_id=@annee_id), 'Mercredi', '07:30:00', '09:30:00', 'Salle 201'),
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=6 AND matiere_id=15 AND classe_id=16 AND annee_id=@annee_id), 'Mercredi', '09:45:00', '11:45:00', 'Salle 201'),
-- Jeudi
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=2 AND matiere_id=10 AND classe_id=16 AND annee_id=@annee_id), 'Jeudi', '07:30:00', '09:30:00', 'Salle 201'),
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=13 AND matiere_id=11 AND classe_id=16 AND annee_id=@annee_id), 'Jeudi', '09:45:00', '11:45:00', 'Salle 201'),
-- Vendredi
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=15 AND matiere_id=12 AND classe_id=16 AND annee_id=@annee_id), 'Vendredi', '07:30:00', '09:30:00', 'Labo PC'),
(16, @annee_id, (SELECT id FROM affectations WHERE enseignant_id=17 AND matiere_id=13 AND classe_id=16 AND annee_id=@annee_id), 'Vendredi', '09:45:00', '11:45:00', 'Labo SVT');

-- ============================================================
-- 9. ABSENCES
-- ============================================================
INSERT INTO absences (eleve_id, date_absence, motif, justifie, annee_id) VALUES
(48, '2025-09-22', 'Maladie (paludisme)', 1, @annee_id),
(49, '2025-10-03', 'Raison familiale', 1, @annee_id),
(52, '2025-10-15', 'Absent sans motif', 0, @annee_id),
(51, '2025-10-20', 'Maladie (fièvre)', 1, @annee_id),
(57, '2025-11-05', 'Consultation médicale', 1, @annee_id),
(61, '2025-11-10', 'Absent sans motif', 0, @annee_id),
(62, '2025-11-12', 'Maladie (grippe)', 1, @annee_id),
(65, '2025-09-30', 'Décès famille', 1, @annee_id),
(68, '2025-10-28', 'Absent sans motif', 0, @annee_id),
(70, '2025-11-03', 'Maladie', 1, @annee_id),
(72, '2025-10-07', 'Raison familiale', 1, @annee_id),
(75, '2025-10-14', 'Absent sans motif', 0, @annee_id),
(79, '2025-11-18', 'Maladie (paludisme)', 1, @annee_id),
(82, '2025-10-22', 'Absent sans motif', 0, @annee_id),
(2, '2025-10-01', 'Maladie', 1, @annee_id),
(6, '2025-10-10', 'Absent sans motif', 0, @annee_id),
(20, '2025-11-04', 'Raison familiale', 1, @annee_id),
(36, '2025-10-16', 'Maladie (fièvre)', 1, @annee_id),
(40, '2025-09-25', 'Absent sans motif', 0, @annee_id),
(44, '2025-11-07', 'Consultation médicale', 1, @annee_id);

-- ============================================================
-- 10. PAIEMENTS (quelques échéances partielles)
-- ============================================================
INSERT INTO paiements (inscription_id, montant, date_paiement, mode, reference, observation) VALUES
-- Inscription 1 (PS A — 75 000 FCFA)
(1, 30000, '2025-09-05', 'especes', NULL, '1er versement'),
(1, 25000, '2025-10-10', 'mobile_money', 'MTN-20250905-001', '2ème versement'),
-- Inscription 5 (PS A)
(5, 75000, '2025-09-10', 'especes', NULL, 'Paiement intégral'),
-- Inscription 6 (PS B)
(6, 40000, '2025-09-05', 'especes', NULL, '1er versement'),
-- Inscription 10 (MS A)
(10, 45000, '2025-09-04', 'especes', NULL, '1er versement'),
(10, 40000, '2025-10-05', 'cheque', 'CHQ-20251005-001', '2ème versement'),
-- Inscription 15 (GS A)
(15, 95000, '2025-09-04', 'virement', 'VIR-20250904-001', 'Paiement intégral'),
-- Inscription 19 (CP A — 120 000)
(19, 60000, '2025-09-03', 'especes', NULL, '1er versement'),
(19, 60000, '2025-10-15', 'mobile_money', 'MTN-20251015-002', '2ème versement'),
-- Inscription 20
(20, 120000, '2025-09-03', 'especes', NULL, 'Paiement intégral'),
-- Inscription 28 (CE1 A — 130 000)
(28, 50000, '2025-09-02', 'especes', NULL, '1er versement'),
(28, 50000, '2025-10-02', 'especes', NULL, '2ème versement'),
(28, 30000, '2025-11-02', 'mobile_money', 'MTN-20251102-003', '3ème versement'),
-- Inscription 36 (CM1 A — 140 000)
(36, 140000, '2025-09-02', 'virement', 'VIR-20250902-002', 'Paiement intégral'),
-- Inscription 40 (CM2 A — 150 000)
(40, 50000, '2025-09-01', 'especes', NULL, '1er versement'),
(40, 50000, '2025-10-01', 'especes', NULL, '2ème versement'),
-- Inscription 48 (6ème A — 180 000)
(48, 60000, '2025-09-01', 'especes', NULL, '1er versement'),
(48, 60000, '2025-10-01', 'mobile_money', 'MTN-20251001-004', '2ème versement'),
(48, 60000, '2025-11-01', 'especes', NULL, '3ème versement'),
-- Inscription 49
(49, 90000, '2025-09-01', 'cheque', 'CHQ-20250901-005', '1er versement'),
-- Inscription 50
(50, 180000, '2025-09-01', 'virement', 'VIR-20250901-003', 'Paiement intégral'),
-- Inscription 57 (5ème A — 180 000)
(57, 180000, '2025-09-01', 'especes', NULL, 'Paiement intégral'),
-- Inscription 61 (4ème A — 200 000)
(61, 100000, '2025-09-01', 'especes', NULL, '1er versement'),
(61, 100000, '2025-10-01', 'mobile_money', 'MTN-20251001-005', '2ème versement'),
-- Inscription 65 (3ème A — 200 000)
(65, 200000, '2025-09-01', 'virement', 'VIR-20250901-004', 'Paiement intégral'),
-- Inscription 67
(67, 70000, '2025-09-01', 'especes', NULL, '1er versement'),
(67, 70000, '2025-10-01', 'especes', NULL, '2ème versement'),
-- Inscription 72 (2nde A — 250 000)
(72, 125000, '2025-09-01', 'especes', NULL, '1er versement'),
(72, 125000, '2025-10-01', 'cheque', 'CHQ-20251001-006', '2ème versement'),
-- Inscription 73
(73, 250000, '2025-09-01', 'virement', 'VIR-20250901-005', 'Paiement intégral'),
-- Inscription 79 (1ère S — 280 000)
(79, 140000, '2025-09-01', 'especes', NULL, '1er versement'),
-- Inscription 80
(80, 280000, '2025-09-01', 'virement', 'VIR-20250901-006', 'Paiement intégral'),
-- Inscription 83 (Tle S — 300 000)
(83, 300000, '2025-09-01', 'especes', NULL, 'Paiement intégral'),
(84, 150000, '2025-09-01', 'mobile_money', 'MTN-20250901-007', '1er versement'),
(85, 300000, '2025-09-01', 'virement', 'VIR-20250901-007', 'Paiement intégral');

-- ============================================================
-- 11. UTILISATEURS supplémentaires (différents rôles)
-- ============================================================
-- Mot de passe pour tous : password (hash bcrypt de "password")
INSERT INTO utilisateurs (username, password, nom, prenom, email, role, actif) VALUES
('mballa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mballa', 'Jean', 'j.mballa@cs.ml', 'directeur', 1),
('ngassa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ngassa', 'Marie-Claire', 'mc.ngassa@cs.ml', 'enseignant', 1),
('tchomga', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tchomga', 'Béatrice', 'b.tchomga@cs.ml', 'secretaire', 1),
('fokou', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fokou', 'Serge', 's.fokou@cs.ml', 'comptable', 1);

-- ============================================================
-- FIN — Données de simulation chargées
-- ============================================================