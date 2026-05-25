-- Migration : Ajout de la table destinations, colonne destination_id dans operations_caisse et demandes_engagement
-- A executer sur la base existante via phpMyAdmin ou mysql CLI :
--   mysql -u root brenfinance < sql/migration_destinations.sql

CREATE TABLE IF NOT EXISTS destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE operations_caisse ADD COLUMN destination_id INT AFTER beneficiaire_nom;
ALTER TABLE operations_caisse ADD CONSTRAINT fk_operations_destination FOREIGN KEY (destination_id) REFERENCES destinations(id);

ALTER TABLE demandes_engagement ADD COLUMN destination_id INT AFTER type_depense_id;
ALTER TABLE demandes_engagement ADD CONSTRAINT fk_engagement_destination FOREIGN KEY (destination_id) REFERENCES destinations(id);

INSERT INTO destinations (code, libelle) VALUES
('SIEGE', 'Siege social'),
('AGENCE', 'Antenne regionale'),
('PROJET', 'Projet'),
('SERVICE', 'Service interne'),
('EXTERN', 'Exterieur');