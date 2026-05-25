-- Migration: Ajout des tables et colonnes pour les escales et itinéraires
-- À exécuter dans phpMyAdmin (base transport_db)
-- Exécutez chaque bloc séparément si vous avez des erreurs de colonne déjà existante

-- ============================================================
-- NOUVELLES TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS itineraires (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL UNIQUE,
    nom             VARCHAR(150) NOT NULL,
    agence_depart   INT NOT NULL,
    agence_arrivee  INT NOT NULL,
    distance_km     INT DEFAULT 0,
    duree_minutes   INT DEFAULT 0,
    actif           TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agence_depart)  REFERENCES agences(id),
    FOREIGN KEY(agence_arrivee) REFERENCES agences(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS itineraire_escales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    itineraire_id   INT NOT NULL,
    agence_id       INT NOT NULL,
    ordre           INT NOT NULL,
    distance_debut  INT DEFAULT 0,
    duree_debut     INT DEFAULT 0,
    FOREIGN KEY(itineraire_id) REFERENCES itineraires(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_id)     REFERENCES agences(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS correspondances (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    itineraire_depart_id    INT NOT NULL,
    itineraire_arrivee_id   INT NOT NULL,
    agence_correspondance_id INT NOT NULL,
    delai_minutes           INT DEFAULT 30,
    actif                  TINYINT(1) DEFAULT 1,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(itineraire_depart_id)  REFERENCES itineraires(id) ON DELETE CASCADE,
    FOREIGN KEY(itineraire_arrivee_id)  REFERENCES itineraires(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_correspondance_id) REFERENCES agences(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bordereau_escales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    bordereau_id    INT NOT NULL,
    agence_id       INT NOT NULL,
    ordre           INT NOT NULL,
    statut          ENUM('en_attente','confirme','refuse') DEFAULT 'en_attente',
    confirme_par    INT NULL,
    date_confirmation TIMESTAMP NULL,
    observations    TEXT,
    FOREIGN KEY(bordereau_id) REFERENCES bordereaux(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_id)     REFERENCES agences(id),
    FOREIGN KEY(confirme_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- COLONNES MANQUANTES (ignorez les erreurs "Duplicate column")
-- ============================================================

-- voyages
ALTER TABLE voyages ADD COLUMN itineraire_id INT NULL AFTER convoyeur_id;
ALTER TABLE voyages ADD COLUMN convoyeur_nom VARCHAR(150) AFTER montant_peage;

-- bordereaux
ALTER TABLE bordereaux ADD COLUMN valide_par INT NULL AFTER imprime;
ALTER TABLE bordereaux ADD COLUMN date_validation TIMESTAMP NULL AFTER valide_par;

-- tickets
ALTER TABLE tickets MODIFY COLUMN voyage_id INT NULL;
ALTER TABLE tickets ADD COLUMN passager_id INT NULL AFTER bordereau_id;
ALTER TABLE tickets ADD COLUMN agence_depart_id INT NULL AFTER agence_id;
ALTER TABLE tickets ADD COLUMN agence_arrivee_id INT NULL AFTER agence_depart_id;
ALTER TABLE tickets ADD COLUMN escale_montee_id INT NULL AFTER guichetier_id;
ALTER TABLE tickets ADD COLUMN escale_descente_id INT NULL AFTER escale_montee_id;

-- tarifs
ALTER TABLE tarifs MODIFY COLUMN destination_id INT NULL;
ALTER TABLE tarifs ADD COLUMN itineraire_id INT NULL AFTER destination_id;
ALTER TABLE tarifs ADD COLUMN escale_depart_id INT NULL AFTER itineraire_id;
ALTER TABLE tarifs ADD COLUMN escale_arrivee_id INT NULL AFTER escale_depart_id;

-- ============================================================
-- CONTRAINTES FOREIGN KEY (ignorez les erreurs si déjà existantes)
-- ============================================================

ALTER TABLE voyages ADD CONSTRAINT fk_voyages_itineraire FOREIGN KEY (itineraire_id) REFERENCES itineraires(id) ON DELETE SET NULL;
ALTER TABLE bordereaux ADD CONSTRAINT fk_bordereaux_valideur FOREIGN KEY (valide_par) REFERENCES utilisateurs(id) ON DELETE SET NULL;

-- ============================================================
-- DONNÉES DE DÉMONSTRATION
-- ============================================================

-- Itinéraire Yaoundé → Douala (via Bafoussam)
INSERT IGNORE INTO itineraires (id, code, nom, agence_depart, agence_arrivee, distance_km, duree_minutes) VALUES
(1, 'YDE-DLA', 'Yaoundé → Douala', 1, 2, 260, 240);

INSERT IGNORE INTO itineraire_escales (itineraire_id, agence_id, ordre, distance_debut, duree_debut) VALUES
(1, 1, 1, 0, 0),
(1, 3, 2, 140, 120),
(1, 2, 3, 260, 240);

-- Itinéraire Yaoundé → Garoua (via Bafoussam, Ngaoundéré, Bertoua)
INSERT IGNORE INTO itineraires (id, code, nom, agence_depart, agence_arrivee, distance_km, duree_minutes) VALUES
(2, 'YDE-GRB', 'Yaoundé → Garoua', 1, 6, 700, 720);

INSERT IGNORE INTO itineraire_escales (itineraire_id, agence_id, ordre, distance_debut, duree_debut) VALUES
(2, 1, 1, 0, 0),
(2, 3, 2, 140, 120),
(2, 4, 3, 350, 300),
(2, 5, 4, 500, 420),
(2, 6, 5, 700, 720);