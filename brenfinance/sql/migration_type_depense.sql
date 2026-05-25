-- Migration : type_depense VARCHAR → type_depense_id FK + table types_depenses
-- À exécuter dans phpMyAdmin ou via : mysql -u root brenfinance < sql/migration_type_depense.sql

-- 1. Créer la table types_depenses
CREATE TABLE IF NOT EXISTS types_depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Insérer les types de dépense par défaut
INSERT IGNORE INTO types_depenses (code, libelle) VALUES
('FOURN', 'Fournitures de bureau'),
('PREST', 'Prestations de service'),
('DEPLAC', 'Déplacements et missions'),
('MAINT', 'Maintenance et réparations'),
('LOYER', 'Loyers et charges locatives'),
('COMMUN', 'Frais de communication'),
('ASSUR', 'Assurances'),
('SALAIRE', 'Rémunérations et charges sociales'),
('FORM', 'Formation du personnel'),
('DIVERS', 'Dépenses diverses');

-- 3. Ajouter user_id à beneficiaires
ALTER TABLE beneficiaires ADD COLUMN user_id INT NULL AFTER nom;
ALTER TABLE beneficiaires ADD FOREIGN KEY (user_id) REFERENCES utilisateurs(id);

-- 4. Ajouter la nouvelle colonne type_depense_id
ALTER TABLE demandes_engagement ADD COLUMN type_depense_id INT NULL AFTER mode_paiement_id;

-- 5. Migrer les données existantes : convertir le texte en FK
-- Pour chaque valeur existante, chercher le type correspondant ou utiliser 'DIVERS'
UPDATE demandes_engagement d
LEFT JOIN types_depenses t ON d.type_depense LIKE CONCAT('%', t.libelle, '%')
SET d.type_depense_id = COALESCE(t.id, (SELECT id FROM types_depenses WHERE code='DIVERS'));

-- Pour les lignes sans correspondance, affecter DIVERS
UPDATE demandes_engagement SET type_depense_id = (SELECT id FROM types_depenses WHERE code='DIVERS')
WHERE type_depense_id IS NULL AND type_depense IS NOT NULL;

-- 6. Rendre la colonne NOT NULL maintenant que tout est peuplé
ALTER TABLE demandes_engagement MODIFY type_depense_id INT NOT NULL;

-- 7. Ajouter la contrainte de clé étrangère
ALTER TABLE demandes_engagement ADD FOREIGN KEY (type_depense_id) REFERENCES types_depenses(id);

-- 8. Supprimer l'ancienne colonne type_depense
ALTER TABLE demandes_engagement DROP COLUMN type_depense;