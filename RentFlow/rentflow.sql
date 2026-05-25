-- Base de données RentFlow
CREATE DATABASE IF NOT EXISTS rentflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rentflow;

-- Table users (Utilisateurs)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table landlords (Bailleurs)
CREATE TABLE landlords (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    contact TEXT NOT NULL,
    contract_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table agencies (Agences)
CREATE TABLE agencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    contact TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table batches (Lots)
CREATE TABLE batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    landlord_id INT NOT NULL,
    agency_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
);

-- Table payments (Paiements)
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    status ENUM('EARLY', 'ON_TIME', 'LATE', 'PENDING') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

-- Table reminders (Rappels)
CREATE TABLE reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    sent_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

-- Index pour optimiser les requêtes
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_due_date ON payments(due_date);
CREATE INDEX idx_batches_landlord ON batches(landlord_id);
CREATE INDEX idx_batches_agency ON batches(agency_id);

-- Données d'exemple
INSERT INTO users (name, email, password, role) VALUES
('Administrateur', 'admin@rentflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO landlords (name, contact, contract_details) VALUES
('Jean Dupont', 'jean.dupont@email.com | 0612345678', 'Contrat signé le 01/01/2024'),
('Marie Martin', 'marie.martin@email.com | 0687654321', 'Contrat renouvelé le 15/03/2024'),
('Pierre Durand', 'pierre.durand@email.com | 0623456789', 'Nouveau contrat');

INSERT INTO agencies (name, contact) VALUES
('Agence Immo Paris', 'contact@agenceimmo.fr | 0145678901'),
('Gestion Locative Lyon', 'contact@gestionlyon.fr | 0478901234'),
('Location Express Marseille', 'contact@locationmars.fr | 0456789012');

INSERT INTO batches (landlord_id, agency_id, name) VALUES
(1, 1, 'Appartement T3 Paris 11e'),
(1, 1, 'Studio Paris 18e'),
(2, 2, 'Maison 4p Lyon 7e'),
(3, 3, 'T2 Marseille Centre');

INSERT INTO payments (batch_id, amount, due_date, paid_date, status) VALUES
(1, 850.00, '2024-05-01', '2024-04-28', 'EARLY'),
(1, 850.00, '2024-06-01', '2024-06-01', 'ON_TIME'),
(2, 550.00, '2024-05-01', '2024-05-05', 'LATE'),
(3, 1200.00, '2024-05-15', NULL, 'PENDING'),
(4, 650.00, '2024-05-01', '2024-04-25', 'EARLY');
