-- =============================================================================
-- ESADISS — Schéma MySQL unique (installation / référence)
-- =============================================================================
-- RÈGLE PROJET — À NE PAS OUBLIER :
--   Toute modification de la BASE DE DONNÉES (structure : tables, colonnes, index,
--   contraintes, types ENUM, etc.) doit être ACTUALISÉE UNIQUEMENT DANS CE FICHIER.
--   Ne pas disperser du CREATE / ALTER dans d’autres fichiers (pas de migrate_*.php,
--   pas de DDL dans db.php, README, etc.).
--   APPLICATION : après modification de ce fichier, exécuter vous-même les requêtes
--   nécessaires dans MySQL (phpMyAdmin, client mysql, etc.) — pas via install.php.
--   Les jeux de données d’exemple : bloc INSERT commenté plus bas et/ou seed.php
--   (données uniquement, pas la définition des tables).
-- =============================================================================
-- • phpMyAdmin / MySQL : copier la structure (CREATE…) ou les ALTER de l’annexe ;
--   décommenter le bloc INSERT ici pour les 30 produits si besoin.
-- • install.php : optionnel uniquement (première install automatisée) — non requis pour
--   les mises à jour : privilégier l’exécution SQL manuelle depuis ce fichier.
-- =============================================================================

-- ——— Structure : produits ———
CREATE TABLE IF NOT EXISTS produits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(255) NOT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  prix_partenaire DECIMAL(12,2) NOT NULL DEFAULT 0,
  prix_client DECIMAL(12,2) NOT NULL DEFAULT 0,
  unite VARCHAR(10) DEFAULT '€',
  image VARCHAR(255) DEFAULT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  visible_partenaire TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=visible espace partenaire, 0=rupture (masqué partenaires, catalogue public inchangé)',
  gestion_stock TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=stocks suivis, 0=prestation sans stock',
  stock_min INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Seuil alerte stock bas (0=pas d\'alerte)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : dépôts, rayons, stock ———
CREATE TABLE IF NOT EXISTS depots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  code VARCHAR(40) DEFAULT NULL,
  ordre INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_depots_ordre (ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rayons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  depot_id INT UNSIGNED NOT NULL,
  nom VARCHAR(120) NOT NULL,
  code VARCHAR(40) DEFAULT NULL,
  ordre INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rayons_depot (depot_id),
  CONSTRAINT fk_rayons_depot FOREIGN KEY (depot_id) REFERENCES depots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_niveau (
  produit_id INT UNSIGNED NOT NULL,
  rayon_id INT UNSIGNED NOT NULL,
  quantite INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (produit_id, rayon_id),
  CONSTRAINT fk_stock_produit FOREIGN KEY (produit_id) REFERENCES produits (id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_rayon FOREIGN KEY (rayon_id) REFERENCES rayons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_mouvements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  produit_id INT UNSIGNED NOT NULL,
  rayon_id INT UNSIGNED NOT NULL,
  type ENUM('entree', 'sortie', 'vente', 'ajustement', 'transfert') NOT NULL,
  quantite INT UNSIGNED NOT NULL COMMENT 'Quantité saisie (positive) ; sens selon type',
  commentaire VARCHAR(500) DEFAULT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mvt_produit (produit_id),
  INDEX idx_mvt_created (created_at),
  CONSTRAINT fk_mvt_produit FOREIGN KEY (produit_id) REFERENCES produits (id) ON DELETE CASCADE,
  rayon_dest_id INT UNSIGNED DEFAULT NULL COMMENT 'Rayon destination (pour transfert)',
  CONSTRAINT fk_mvt_rayon FOREIGN KEY (rayon_id) REFERENCES rayons (id) ON DELETE CASCADE,
  CONSTRAINT fk_mvt_rayon_dest FOREIGN KEY (rayon_dest_id) REFERENCES rayons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : parametres ———
CREATE TABLE IF NOT EXISTS parametres (
  cle VARCHAR(80) NOT NULL PRIMARY KEY,
  valeur TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : factures ———
CREATE TABLE IF NOT EXISTS factures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(40) NOT NULL UNIQUE,
  client_id INT UNSIGNED NOT NULL,
  date_facture DATE NOT NULL,
  echeance DATE DEFAULT NULL,
  statut ENUM('brouillon','envoyee','payee_partiellement','payee','annulee') NOT NULL DEFAULT 'brouillon',
  montant_ht DECIMAL(14,2) NOT NULL DEFAULT 0,
  tva DECIMAL(5,2) NOT NULL DEFAULT 0,
  montant_ttc DECIMAL(14,2) NOT NULL DEFAULT 0,
  montant_paye DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_factures_client (client_id),
  INDEX idx_factures_statut (statut),
  INDEX idx_factures_date (date_facture),
  CONSTRAINT fk_factures_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : lignes_facture ———
CREATE TABLE IF NOT EXISTS lignes_facture (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  facture_id INT UNSIGNED NOT NULL,
  produit_id INT UNSIGNED DEFAULT NULL,
  designation VARCHAR(255) NOT NULL,
  quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
  prix_unitaire DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_ligne DECIMAL(14,2) NOT NULL DEFAULT 0,
  ordre INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_lignes_facture FOREIGN KEY (facture_id) REFERENCES factures (id) ON DELETE CASCADE,
  INDEX idx_lignes_facture (facture_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : paiements ———
CREATE TABLE IF NOT EXISTS paiements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  facture_id INT UNSIGNED NOT NULL,
  montant DECIMAL(14,2) NOT NULL,
  date_paiement DATE NOT NULL,
  mode ENUM('especes','cheque','virement','carte','autre') NOT NULL DEFAULT 'especes',
  reference VARCHAR(120) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_paiements_facture FOREIGN KEY (facture_id) REFERENCES factures (id) ON DELETE CASCADE,
  INDEX idx_paiements_facture (facture_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : relances ———
CREATE TABLE IF NOT EXISTS relances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  facture_id INT UNSIGNED NOT NULL,
  type ENUM('rappel','mise_en_demeure','relance') NOT NULL DEFAULT 'rappel',
  date_relance DATE NOT NULL,
  commentaire TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_relances_facture FOREIGN KEY (facture_id) REFERENCES factures (id) ON DELETE CASCADE,
  INDEX idx_relances_facture (facture_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ——— Structure : utilisateurs ———
CREATE TABLE IF NOT EXISTS utilisateurs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nom VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  role ENUM('admin', 'partenaire', 'client') NOT NULL DEFAULT 'partenaire',
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_login (login),
  INDEX idx_role (role),
  INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Données d’exemple (30 produits) — décommenter le bloc /* … */ pour import phpMyAdmin
-- (doublons si les noms existent déjà). Alternative : seed.php dans le navigateur.
-- =============================================================================
/*
INSERT INTO produits (nom, reference, description, prix_partenaire, prix_client, unite, actif) VALUES
('106A Toner', NULL, NULL, 9000, 12000, 'FCFA', 1),
('107A Toner', NULL, NULL, 9000, 12000, 'FCFA', 1),
('117A Toner', NULL, NULL, 15000, 18000, 'FCFA', 1),
('126A Toner', NULL, NULL, 12000, 15000, 'FCFA', 1),
('135A Toner', NULL, NULL, 15000, 17000, 'FCFA', 1),
('17A Toner', NULL, NULL, 8000, 10000, 'FCFA', 1),
('19A Tambour', NULL, NULL, 8000, 10000, 'FCFA', 1),
('203A Toner', NULL, NULL, 12000, 15000, 'FCFA', 1),
('205A Toner', NULL, NULL, 12000, 15000, 'FCFA', 1),
('26A Toner', NULL, NULL, 10000, 12000, 'FCFA', 1),
('305 xl BK', NULL, NULL, 9000, 10000, 'FCFA', 1),
('305 xl couleur', NULL, NULL, 9000, 11000, 'FCFA', 1),
('305A Toner', NULL, NULL, 10000, 13000, 'FCFA', 1),
('30A Toner', NULL, NULL, 10000, 12000, 'FCFA', 1),
('410A Toner', NULL, NULL, 10000, 15000, 'FCFA', 1),
('44A Toner', NULL, NULL, 10000, 12000, 'FCFA', 1),
('49A/53A Toner', NULL, NULL, 10000, 12000, 'FCFA', 1),
('59A Toner', NULL, NULL, 15000, 18000, 'FCFA', 1),
('78A Toner', NULL, NULL, 8000, 10000, 'FCFA', 1),
('79A Toner', NULL, NULL, 10000, 13000, 'FCFA', 1),
('80A/05A Toner', NULL, NULL, 8000, 10000, 'FCFA', 1),
('83A Toner', NULL, NULL, 8000, 10000, 'FCFA', 1),
('85A Toner', NULL, NULL, 8000, 10000, 'FCFA', 1),
('MC-G02 Maintaining ink cartridges', NULL, NULL, 20000, 25000, 'FCFA', 1),
('MC-G04 Maintaining ink cartridges', NULL, NULL, 20000, 25000, 'FCFA', 1),
('XL Powder', NULL, NULL, 1400, 1800, 'FCFA', 1),
('Encre Pixma Genuine PRC black', NULL, NULL, 3500, 5000, 'FCFA', 1),
('Encre Pixma Genuine PRC cyan', NULL, NULL, 3500, 5000, 'FCFA', 1),
('Encre Pixma Genuine PRC magenta', NULL, NULL, 3500, 5000, 'FCFA', 1),
('Encre Pixma Genuine PRC yellow', NULL, NULL, 3500, 5000, 'FCFA', 1);
*/

-- =============================================================================
-- Annexe — rappels (historique / bases déjà créées avant ce fichier)
-- =============================================================================
-- Si une base existait sans certaines colonnes ou tables : recopier depuis la
-- structure EN TÊTE DE CE FICHIER (règle : tout nouveau DDL est ajouté là-haut,
-- pas ailleurs). Exemples historiques commentés (ne pas exécuter si la structure
-- actuelle du fichier est déjà appliquée) :
-- ALTER TABLE produits ADD INDEX idx_actif_nom (actif, nom);
-- ALTER TABLE produits ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER unite;
-- ALTER TABLE produits ADD COLUMN gestion_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER actif;
-- ALTER TABLE produits ADD COLUMN visible_partenaire TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'rupture partenaires' AFTER actif;
-- ALTER TABLE produits ADD COLUMN stock_min INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Seuil alerte stock bas' AFTER gestion_stock;
-- ALTER TABLE stock_mouvements ADD COLUMN rayon_dest_id INT UNSIGNED DEFAULT NULL COMMENT 'Rayon destination (pour transfert)' AFTER rayon_id;
-- ALTER TABLE stock_mouvements MODIFY COLUMN type ENUM('entree','sortie','vente','ajustement','transfert') NOT NULL;
-- Synchroniser visible_partenaire après ajout de colonne (une fois) :
-- UPDATE produits p LEFT JOIN (
--   SELECT produit_id, SUM(quantite) AS t FROM stock_niveau GROUP BY produit_id
-- ) s ON s.produit_id = p.id SET p.visible_partenaire = CASE
--   WHEN COALESCE(p.gestion_stock,1) <> 1 THEN 1 WHEN COALESCE(s.t,0) <= 0 THEN 0 ELSE 1 END;
-- =============================================================================
-- Paramètres par défaut (exécuter une fois après création de la table parametres)
-- =============================================================================
INSERT INTO parametres (cle, valeur) VALUES
  ('entreprise_nom', 'ESADISS'),
  ('entreprise_adresse', ''),
  ('entreprise_telephone', ''),
  ('entreprise_email', ''),
  ('entreprise_logo', ''),
  ('site_nom', 'ESADISS Partenaire'),
  ('devise', 'FCFA'),
  ('facture_prefixe', 'FAC'),
  ('facture_prochain_num', '1'),
  ('facture_tva_defaut', '0'),
  ('stock_alerte_actif', '1'),
  ('stock_seuil_defaut', '5');

-- =============================================================================
-- Annexe — modèle INSERT utilisateur (mot de passe = hash bcrypt, pas en clair)
-- =============================================================================
-- INSERT INTO utilisateurs (login, password_hash, nom, email, role, actif) VALUES
-- ('exemple', '$2y$10$...', 'Nom', NULL, 'partenaire', 1);
-- Génération du hash : generer_utilisateur.php ou  php -r "echo password_hash('secret', PASSWORD_DEFAULT);"
-- =============================================================================
