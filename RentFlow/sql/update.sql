-- RentFlow - New tables schema
USE rentflow;

-- Table des lots-agences (batch association)
CREATE TABLE IF NOT EXISTS lots_agences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lot_id INT NOT NULL,
    agencia_id INT NOT NULL,
    date_debut DATE DEFAULT CURRENT_DATE,
    date_fin DATE,
    actif TINYINT(1) DEFAULT 1,
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
    FOREIGN KEY (agencia_id) REFERENCES agences(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lot_agence (lot_id, agencia_id, actif)
);

-- Table des rappels
CREATE TABLE IF NOT EXISTS rappels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    paiement_id INT NOT NULL,
    lot_id INT NOT NULL,
    agencia_id INT NOT NULL,
    date_rappel DATE,
    type ENUM('retard', 'impaye') DEFAULT 'retard',
    statut ENUM('pending', 'sent', 'viewed', 'resolved') DEFAULT 'pending',
    date_envoi DATETIME,
    message TEXT,
    FOREIGN KEY (paiement_id) REFERENCES paiements(id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
    FOREIGN KEY (agencia_id) REFERENCES agences(id) ON DELETE CASCADE
);

-- Paramètres supplémentaires
INSERT IGNORE INTO parametres (cle, valeur) VALUES
('rappels_auto', '1'),
('rappels_jours', '3'),
('email_expediteur', 'noreply@rentflow.fr');