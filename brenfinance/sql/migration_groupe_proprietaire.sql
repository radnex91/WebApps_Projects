-- Migration : Groupes propriétaires + Bon propriétaire
-- Date : 2026-04-15

-- 1. Créer la table groupes_proprietaires
CREATE TABLE IF NOT EXISTS groupes_proprietaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Ajouter le type de dépense "Bon propriétaire"
INSERT IGNORE INTO types_depenses (code, libelle) VALUES ('BON_PROPRIETAIRE', 'Bon propriétaire');

-- 3. Ajouter groupe_proprietaire_id à demandes_engagement
ALTER TABLE demandes_engagement ADD COLUMN groupe_proprietaire_id INT NULL AFTER type_depense_id;
ALTER TABLE demandes_engagement ADD CONSTRAINT fk_eng_groupe_prop FOREIGN KEY (groupe_proprietaire_id) REFERENCES groupes_proprietaires(id);

-- 4. Ajouter type_depense_id et groupe_proprietaire_id à operations_caisse
ALTER TABLE operations_caisse ADD COLUMN type_depense_id INT NULL AFTER type_operation_id;
ALTER TABLE operations_caisse ADD COLUMN groupe_proprietaire_id INT NULL AFTER type_depense_id;
ALTER TABLE operations_caisse ADD CONSTRAINT fk_op_type_depense FOREIGN KEY (type_depense_id) REFERENCES types_depenses(id);
ALTER TABLE operations_caisse ADD CONSTRAINT fk_op_groupe_prop FOREIGN KEY (groupe_proprietaire_id) REFERENCES groupes_proprietaires(id);

-- 5. Données initiales groupes_proprietaires
INSERT IGNORE INTO groupes_proprietaires (code, libelle) VALUES
('GP_A', 'Groupe A'),
('GP_B', 'Groupe B');