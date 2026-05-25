-- BrenFinance Suite - Migration 009b: Add repartitions_cles table, fix ENUM
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS repartitions_cles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    libelle VARCHAR(300) NOT NULL,
    montant_total DECIMAL(15,2) NOT NULL,
    cree_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections_analytiques(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

ALTER TABLE affectations_analytiques
    MODIFY COLUMN source_type ENUM('ecriture','ligne_bulletin','operation_caisse','operation_bancaire','engagement_ligne','repartition_key') NOT NULL;
