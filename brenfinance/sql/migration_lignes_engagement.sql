-- Migration : lignes descriptives d'engagement
-- Exécuter via phpMyAdmin ou mysql CLI

CREATE TABLE IF NOT EXISTS lignes_engagement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    engagement_id INT NOT NULL,
    ordre INT NOT NULL DEFAULT 1,
    libelle VARCHAR(255) NOT NULL,
    quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
    cout_unitaire DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (engagement_id) REFERENCES demandes_engagement(id) ON DELETE CASCADE
) ENGINE=InnoDB;