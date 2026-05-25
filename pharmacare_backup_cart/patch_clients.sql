-- patch_clients.sql
-- Ajoute les tables clients + reglements et modifie ventes pour le credit

CREATE TABLE IF NOT EXISTS clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    telephone   VARCHAR(20) DEFAULT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reglements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    vente_id        INT DEFAULT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mode_paiement   ENUM('espèces','carte','chèque','mobile') NOT NULL DEFAULT 'espèces',
    note            VARCHAR(255) DEFAULT NULL,
    date_reglement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ventes
    ADD COLUMN client_id INT DEFAULT NULL AFTER client_telephone,
    ADD COLUMN statut_paiement ENUM('payé','en_attente','partiel') DEFAULT 'payé' AFTER mode_paiement,
    MODIFY COLUMN mode_paiement ENUM('espèces','carte','chèque','assurance','crédit') NOT NULL DEFAULT 'espèces',
    ADD FOREIGN KEY (client_id) REFERENCES clients(id);

-- Nouvelles permissions clients
INSERT INTO permissions (code, libelle, module) VALUES
('clients.voir',       'Voir la liste des clients',   'clients'),
('clients.ajouter',    'Créer un client',              'clients'),
('clients.modifier',   'Modifier une fiche client',    'clients'),
('clients.supprimer',  'Désactiver un client',         'clients'),
('clients.paiements',  'Enregistrer des règlements',   'clients');

-- Admin (role_id=1) : toutes les perms clients
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE module = 'clients';

-- Pharmacien (role_id=2) : voir seulement
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code = 'clients.voir';

-- Caissier (role_id=3) : voir, ajouter, paiements
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN ('clients.voir', 'clients.ajouter', 'clients.paiements');
