-- Migration: Create employes table and restructure RH/Paie
-- Date: 2026-05-05

-- 1. Create employes table (independent from utilisateurs)
CREATE TABLE IF NOT EXISTS employes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agence_id INT,
    service_id INT,
    utilisateur_id INT COMMENT 'Optionnel: lien vers compte utilisateur',
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    matricule VARCHAR(30) UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    telephone VARCHAR(50),
    genre ENUM('M','F') DEFAULT 'M',
    date_naissance DATE,
    situation_familiale ENUM('celibataire','marie','divorce','veuf') DEFAULT 'celibataire',
    nombre_enfants INT DEFAULT 0,
    photo VARCHAR(255),
    statut ENUM('actif','inactif','suspendu') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 2. Migrate existing data: create employes rows from utilisateurs who have contracts
INSERT INTO employes (agence_id, service_id, utilisateur_id, nom, prenom, matricule, email, telephone, statut)
SELECT u.agence_id, u.service_id, u.id, u.nom, u.prenom, u.matricule, u.email, u.telephone, u.statut
FROM utilisateurs u
INNER JOIN contrats_employes ce ON ce.utilisateur_id = u.id;

-- 3. Add employe_id column to contrats_employes
ALTER TABLE contrats_employes ADD COLUMN employe_id INT AFTER utilisateur_id;

-- 4. Link contrats_employes to new employes table
UPDATE contrats_employes ce
INNER JOIN employes e ON e.utilisateur_id = ce.utilisateur_id
SET ce.employe_id = e.id;

-- 5. Make employe_id NOT NULL now that data is migrated
ALTER TABLE contrats_employes MODIFY employe_id INT NOT NULL;

-- 6. Add FK constraint
ALTER TABLE contrats_employes ADD CONSTRAINT fk_contrats_employes_employe
    FOREIGN KEY (employe_id) REFERENCES employes(id);

-- 7. Add employe_id to bulletins_paie
ALTER TABLE bulletins_paie ADD COLUMN employe_id INT AFTER utilisateur_id;

UPDATE bulletins_paie bp
INNER JOIN employes e ON e.utilisateur_id = bp.utilisateur_id
SET bp.employe_id = e.id;

-- 8. Add employe_id to utilisateurs (optional reverse link)
ALTER TABLE utilisateurs ADD COLUMN employe_id INT AFTER service_id;
ALTER TABLE utilisateurs ADD CONSTRAINT fk_utilisateurs_employe
    FOREIGN KEY (employe_id) REFERENCES employes(id) ON DELETE SET NULL;

UPDATE utilisateurs u
INNER JOIN employes e ON e.utilisateur_id = u.id
SET u.employe_id = e.id;