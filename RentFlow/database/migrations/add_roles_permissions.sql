-- Système de rôles et permissions pour RentFlow

-- Table des rôles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description TEXT,
    module VARCHAR(50) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pivot role_user
CREATE TABLE IF NOT EXISTS role_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_user (role_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pivot permission_role
CREATE TABLE IF NOT EXISTS permission_role (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission_role (permission_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des rôles par défaut
INSERT INTO roles (name, label, description) VALUES
('super_admin', 'Super Administrateur', 'Accès complet à toutes les fonctionnalités'),
('admin', 'Administrateur', 'Gestion complète sauf paramètres système'),
('manager', 'Gestionnaire', 'Gestion des paiements, lots et bailleurs'),
('agent', 'Agent', 'Consultation et saisie des paiements'),
('viewer', 'Observateur', 'Lecture seule');

-- Insertion des permissions
INSERT INTO permissions (name, label, description, module) VALUES
-- Dashboard
('dashboard_view', 'Voir le dashboard', 'Accès au tableau de bord', 'dashboard'),
-- Landlords
('landlords_view', 'Voir les bailleurs', 'Consulter la liste des bailleurs', 'landlords'),
('landlords_create', 'Créer un bailleur', 'Ajouter un nouveau bailleur', 'landlords'),
('landlords_edit', 'Modifier un bailleur', 'Modifier les informations d\'un bailleur', 'landlords'),
('landlords_delete', 'Supprimer un bailleur', 'Supprimer un bailleur', 'landlords'),
-- Agencies
('agencies_view', 'Voir les agences', 'Consulter la liste des agences', 'agencies'),
('agencies_create', 'Créer une agence', 'Ajouter une nouvelle agence', 'agencies'),
('agencies_edit', 'Modifier une agence', 'Modifier les informations d\'une agence', 'agencies'),
('agencies_delete', 'Supprimer une agence', 'Supprimer une agence', 'agencies'),
-- Batches
('batches_view', 'Voir les lots', 'Consulter la liste des lots', 'batches'),
('batches_create', 'Créer un lot', 'Ajouter un nouveau lot', 'batches'),
('batches_edit', 'Modifier un lot', 'Modifier les informations d\'un lot', 'batches'),
('batches_delete', 'Supprimer un lot', 'Supprimer un lot', 'batches'),
-- Payments
('payments_view', 'Voir les paiements', 'Consulter la liste des paiements', 'payments'),
('payments_create', 'Créer un paiement', 'Ajouter un nouveau paiement', 'payments'),
('payments_edit', 'Modifier un paiement', 'Modifier un paiement', 'payments'),
('payments_delete', 'Supprimer un paiement', 'Supprimer un paiement', 'payments'),
('payments_validate', 'Valider un paiement', 'Valider ou rejeter un paiement', 'payments'),
-- Settings
('settings_view', 'Voir les paramètres', 'Accéder aux paramètres', 'settings'),
('settings_edit', 'Modifier les paramètres', 'Modifier la configuration', 'settings'),
-- Users
('users_view', 'Voir les utilisateurs', 'Consulter la liste des utilisateurs', 'users'),
('users_create', 'Créer un utilisateur', 'Ajouter un nouvel utilisateur', 'users'),
('users_edit', 'Modifier un utilisateur', 'Modifier un utilisateur', 'users'),
('users_delete', 'Supprimer un utilisateur', 'Supprimer un utilisateur', 'users'),
('users_roles', 'Gérer les rôles', 'Affecter des rôles aux utilisateurs', 'users');

-- Attribution des permissions aux rôles
-- Super Admin : toutes les permissions
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id FROM permissions p, roles r WHERE r.name = 'super_admin';

-- Admin : presque toutes sauf settings
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id FROM permissions p, roles r
WHERE r.name = 'admin' AND p.module != 'settings';

-- Manager : gestion opérationnelle
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id FROM permissions p, roles r
WHERE r.name = 'manager' AND p.name IN (
    'dashboard_view',
    'landlords_view', 'landlords_create', 'landlords_edit',
    'agencies_view', 'agencies_create', 'agencies_edit',
    'batches_view', 'batches_create', 'batches_edit',
    'payments_view', 'payments_create', 'payments_edit', 'payments_validate'
);

-- Agent : consultation et saisie
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id FROM permissions p, roles r
WHERE r.name = 'agent' AND p.name IN (
    'dashboard_view',
    'landlords_view', 'agencies_view', 'batches_view',
    'payments_view', 'payments_create'
);

-- Viewer : lecture seule
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id FROM permissions p, roles r
WHERE r.name = 'viewer' AND p.name LIKE '%_view';

-- Migration de la colonne role dans users vers role_user
-- Copier le rôle admin existant
INSERT INTO role_user (role_id, user_id)
SELECT r.id, u.id FROM users u, roles r
WHERE u.role = 'admin' AND r.name = 'admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Supprimer la colonne role de la table users (optionnel, à faire manuellement si nécessaire)
-- ALTER TABLE users DROP COLUMN role;
