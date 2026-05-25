<?php
// Script d'installation - DanayLedger v2
// Crée la base de données, les tables et l'utilisateur admin par défaut
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DanayLedger - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); min-height: 100vh; display: flex; align-items: center; }
        .install-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .step-indicator .step { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; }
        .step.active { background: #0f3460; color: #fff; }
        .step.done { background: #198754; color: #fff; }
        .step.pending { background: #e9ecef; color: #6c757d; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card install-card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary"><i class="bi bi-cash-stack me-2"></i>DanayLedger</h2>
                        <p class="text-muted">Installation de l'application</p>
                    </div>

<?php
$step = $_POST['step'] ?? 1;
$messages = [];
$hasError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $host = $_POST['db_host'] ?? 'localhost';
        $dbname = $_POST['db_name'] ?? 'danay_ledger';
        $dbuser = $_POST['db_user'] ?? 'root';
        $dbpass = $_POST['db_pass'] ?? '';

        // Connexion sans base pour la créer
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Base de données créée';

        // ===== TABLES =====
        $tables = [
            "users" => "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'visiteur',
                avatar VARCHAR(255) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_role (role),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "login_attempts" => "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                locked_until DATETIME DEFAULT NULL,
                last_attempt DATETIME DEFAULT NULL,
                INDEX idx_username (username),
                INDEX idx_locked (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "agence" => "CREATE TABLE IF NOT EXISTS agence (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nomagence VARCHAR(100) NOT NULL,
                codeagence VARCHAR(20) NOT NULL UNIQUE,
                statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "bank" => "CREATE TABLE IF NOT EXISTS bank (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombank VARCHAR(100) NOT NULL,
                codeBank VARCHAR(20) NOT NULL UNIQUE,
                statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "categories" => "CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                nom VARCHAR(100) NOT NULL,
                type ENUM('recette','depense') NOT NULL,
                description TEXT DEFAULT NULL,
                statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "types_operations" => "CREATE TABLE IF NOT EXISTS types_operations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                nom VARCHAR(100) NOT NULL,
                type ENUM('recette','depense','les_deux') NOT NULL DEFAULT 'les_deux',
                description TEXT DEFAULT NULL,
                statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "vehicules" => "CREATE TABLE IF NOT EXISTS vehicules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                immatriculation VARCHAR(20) NOT NULL UNIQUE,
                marque VARCHAR(50) DEFAULT NULL,
                modele VARCHAR(50) DEFAULT NULL,
                annee INT DEFAULT NULL,
                type_vehicule VARCHAR(50) DEFAULT NULL,
                agence_id INT DEFAULT NULL,
                statut ENUM('actif','inactif','en_maintenance') NOT NULL DEFAULT 'actif',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
                INDEX idx_agence (agence_id),
                INDEX idx_statut (statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "recette" => "CREATE TABLE IF NOT EXISTS recette (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(30) NOT NULL UNIQUE,
                date DATE NOT NULL,
                montantexpedition DECIMAL(15,2) NOT NULL DEFAULT 0,
                montantAccompagnement DECIMAL(15,2) NOT NULL DEFAULT 0,
                agence_id INT DEFAULT NULL,
                nomoperateur VARCHAR(100) DEFAULT NULL,
                nomediteur VARCHAR(100) DEFAULT NULL,
                datesaisie DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                dateedite DATE DEFAULT NULL,
                numerorecu VARCHAR(50) DEFAULT NULL,
                destination VARCHAR(100) DEFAULT NULL,
                guichetier VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                justificatif_path VARCHAR(255) DEFAULT NULL,
                created_by INT NOT NULL,
                validated_by INT DEFAULT NULL,
                statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (validated_by) REFERENCES users(id),
                INDEX idx_date (date),
                INDEX idx_agence (agence_id),
                INDEX idx_statut (statut),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "depenses" => "CREATE TABLE IF NOT EXISTS depenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(30) NOT NULL UNIQUE,
                date_depense DATE NOT NULL,
                montant DECIMAL(15,2) NOT NULL DEFAULT 0,
                categorie_id INT DEFAULT NULL,
                type_operation_id INT DEFAULT NULL,
                agence_id INT DEFAULT NULL,
                vehicule_id INT DEFAULT NULL,
                nature VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                justificatif_path VARCHAR(255) DEFAULT NULL,
                created_by INT NOT NULL,
                validated_by INT DEFAULT NULL,
                statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (type_operation_id) REFERENCES types_operations(id) ON DELETE SET NULL,
                FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (validated_by) REFERENCES users(id),
                INDEX idx_date (date_depense),
                INDEX idx_agence (agence_id),
                INDEX idx_statut (statut),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "recettes_camions" => "CREATE TABLE IF NOT EXISTS recettes_camions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(30) NOT NULL UNIQUE,
                date_recette DATE NOT NULL,
                montant DECIMAL(15,2) NOT NULL DEFAULT 0,
                vehicule_id INT DEFAULT NULL,
                agence_id INT DEFAULT NULL,
                agence_depart_id INT DEFAULT NULL,
                agence_arrivee_id INT DEFAULT NULL,
                categorie_id INT DEFAULT NULL,
                trajet VARCHAR(200) DEFAULT NULL,
                client VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                justificatif_path VARCHAR(255) DEFAULT NULL,
                created_by INT NOT NULL,
                validated_by INT DEFAULT NULL,
                statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
                FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (agence_depart_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (agence_arrivee_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (validated_by) REFERENCES users(id),
                INDEX idx_date (date_recette),
                INDEX idx_vehicule (vehicule_id),
                INDEX idx_agence (agence_id),
                INDEX idx_agence_depart (agence_depart_id),
                INDEX idx_agence_arrivee (agence_arrivee_id),
                INDEX idx_statut (statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "versement" => "CREATE TABLE IF NOT EXISTS versement (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(30) NOT NULL UNIQUE,
                dateversement DATE NOT NULL,
                refversement VARCHAR(50) DEFAULT NULL,
                sommeverse DECIMAL(15,2) NOT NULL DEFAULT 0,
                agenceverse_id INT DEFAULT NULL,
                bank_id INT DEFAULT NULL,
                nomoperateur VARCHAR(100) DEFAULT NULL,
                nomediteur VARCHAR(100) DEFAULT NULL,
                dateedite DATE DEFAULT NULL,
                datesaisie DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                recettejourneeagence DECIMAL(15,2) DEFAULT NULL,
                ecart DECIMAL(15,2) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                justificatif_path VARCHAR(255) DEFAULT NULL,
                created_by INT NOT NULL,
                validated_by INT DEFAULT NULL,
                statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (agenceverse_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (bank_id) REFERENCES bank(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (validated_by) REFERENCES users(id),
                INDEX idx_date (dateversement),
                INDEX idx_agence (agenceverse_id),
                INDEX idx_bank (bank_id),
                INDEX idx_statut (statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "proprietaire" => "CREATE TABLE IF NOT EXISTS proprietaire (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nomProprio VARCHAR(100) NOT NULL,
                codeproprio VARCHAR(20) NOT NULL UNIQUE,
                groupe VARCHAR(100) DEFAULT NULL,
                statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "recette_proprio" => "CREATE TABLE IF NOT EXISTS recette_proprio (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recette_id INT NOT NULL,
                proprietaire_id INT NOT NULL,
                date1 DATE DEFAULT NULL,
                date2 DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (recette_id) REFERENCES recette(id) ON DELETE CASCADE,
                FOREIGN KEY (proprietaire_id) REFERENCES proprietaire(id) ON DELETE CASCADE,
                INDEX idx_recette (recette_id),
                INDEX idx_proprietaire (proprietaire_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "cle_repartition" => "CREATE TABLE IF NOT EXISTS cle_repartition (
                id INT AUTO_INCREMENT PRIMARY KEY,
                proprietaire_id INT NOT NULL,
                part1 DECIMAL(5,2) NOT NULL COMMENT 'Pourcentage partie 1',
                part2 DECIMAL(5,2) NOT NULL COMMENT 'Pourcentage partie 2',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (proprietaire_id) REFERENCES proprietaire(id) ON DELETE CASCADE,
                INDEX idx_proprietaire (proprietaire_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "justificatifs" => "CREATE TABLE IF NOT EXISTS justificatifs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                nom_fichier VARCHAR(255) NOT NULL,
                chemin_fichier VARCHAR(500) NOT NULL,
                type_mime VARCHAR(100) DEFAULT NULL,
                taille INT DEFAULT NULL,
                uploaded_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_entity (entity_type, entity_id),
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) DEFAULT NULL,
                entity_id INT DEFAULT NULL,
                old_values JSON DEFAULT NULL,
                new_values JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                details JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_date (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "notifications" => "CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'info',
                titre VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                lien VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_read (user_id, is_read),
                INDEX idx_date (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "parametres" => "CREATE TABLE IF NOT EXISTS parametres (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cle VARCHAR(100) NOT NULL UNIQUE,
                valeur TEXT DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT DEFAULT NULL,
                FOREIGN KEY (updated_by) REFERENCES users(id),
                INDEX idx_cle (cle)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "roles" => "CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) NOT NULL UNIQUE,
                description VARCHAR(255) DEFAULT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                level INT NOT NULL DEFAULT 50,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_slug (slug),
                INDEX idx_level (level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "role_permissions" => "CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                permission VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                UNIQUE KEY uk_role_permission (role_id, permission),
                INDEX idx_role (role_id),
                INDEX idx_permission (permission)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "sauvegardes" => "CREATE TABLE IF NOT EXISTS sauvegardes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom_fichier VARCHAR(255) NOT NULL,
                taille INT DEFAULT NULL,
                type ENUM('auto','manuel') NOT NULL DEFAULT 'manuel',
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "rapprochement" => "CREATE TABLE IF NOT EXISTS rapprochement (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date_debut DATE NOT NULL,
                date_fin DATE NOT NULL,
                agence_id INT DEFAULT NULL,
                total_recettes DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_versements DECIMAL(15,2) NOT NULL DEFAULT 0,
                ecart DECIMAL(15,2) NOT NULL DEFAULT 0,
                statut ENUM('en_cours','valide','ecart_detecte') NOT NULL DEFAULT 'en_cours',
                validated_by INT DEFAULT NULL,
                observations TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
                FOREIGN KEY (validated_by) REFERENCES users(id),
                INDEX idx_dates (date_debut, date_fin),
                INDEX idx_statut (statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $name => $sql) {
            $pdo->exec($sql);
            $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Table <strong>' . $name . '</strong> créée';
        }

        // ===== DONNÉES PAR DÉFAUT =====

        // Admin
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES ('admin', 'admin@danayexpress.com', ?, 'Administrateur', 'admin')")->execute([$adminPass]);
            $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Utilisateur admin créé (admin / admin123)';
        } else {
            $messages[] = '<i class="bi bi-info-circle text-info me-1"></i> Utilisateur admin déjà existant';
        }

        // Agence par défaut
        $pdo->exec("INSERT IGNORE INTO agence (codeagence, nomagence) VALUES ('DAN-001', 'DANAY EXPRESS - Siège')");
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Agence par défaut créée';

        // Banques par défaut
        $banks = [
            ['BICIGUI', 'BICIGUI'],
            ['ECOBANK', 'Ecobank Guinée'],
            ['SGBG', 'Société Générale de Banque en Guinée'],
            ['UBG', 'Union de Banques Guinéennes'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO bank (codeBank, nombank) VALUES (?, ?)");
        foreach ($banks as $bank) { $stmt->execute($bank); }
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Banques par défaut créées';

        // Catégories recettes
        $catRecettes = [
            ['transport_marchandises', 'Transport de marchandises', 'recette'],
            ['location_vehicule', 'Location de véhicule', 'recette'],
            ['commission', 'Commission', 'recette'],
            ['vente', 'Vente', 'recette'],
            ['autre_recette', 'Autre recette', 'recette'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO categories (code, nom, type) VALUES (?, ?, ?)");
        foreach ($catRecettes as $cat) { $stmt->execute($cat); }

        // Catégories dépenses
        $catDepenses = [
            ['carburant', 'Carburant', 'depense'],
            ['maintenance', 'Maintenance & Réparations', 'depense'],
            ['salaires', 'Salaires & Charges', 'depense'],
            ['assurance', 'Assurance', 'depense'],
            ['frais_douane', 'Frais de douane', 'depense'],
            ['loyer', 'Loyer', 'depense'],
            ['fournitures', 'Fournitures de bureau', 'depense'],
            ['telecommunications', 'Télécommunications', 'depense'],
            ['frais_route', 'Frais de route & Péages', 'depense'],
            ['autre_depense', 'Autre dépense', 'depense'],
        ];
        foreach ($catDepenses as $cat) { $stmt->execute($cat); }
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Catégories par défaut créées';

        // Types d'opérations
        $types = [
            ['transport', 'Transport', 'les_deux'],
            ['location', 'Location', 'recette'],
            ['achat_carburant', 'Achat carburant', 'depense'],
            ['reparation', 'Réparation', 'depense'],
            ['paiement_salaire', 'Paiement salaire', 'depense'],
            ['versement_banque', 'Versement en banque', 'les_deux'],
            ['retrait', 'Retrait', 'depense'],
            ['encaissement', 'Encaissement', 'recette'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO types_operations (code, nom, type) VALUES (?, ?, ?)");
        foreach ($types as $t) { $stmt->execute($t); }
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Types d\'opérations créés';

        // Roles par défaut (avec niveau de hiérarchie : 1=haut, 100=bas)
        $defaultRoles = [
            ['Administrateur', 'admin', 'Accès complet à toutes les fonctionnalités', 1, 1],
            ['Utilisateur', 'utilisateur', 'Création et modification des enregistrements', 1, 50],
            ['Visiteur (Lecture seule)', 'visiteur', 'Consultation des données uniquement', 1, 100],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, slug, description, is_system, level) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultRoles as $r) { $stmt->execute($r); }

        // Permissions par rôle
        $adminPerms = ['dashboard','users','users_create','users_edit','users_delete','users_toggle',
            'settings','settings_agences','settings_categories','settings_types','settings_vehicules','settings_params',
            'settings_banks','settings_proprietaires','settings_repartition','settings_roles',
            'recettes','recettes_create','recettes_edit','recettes_delete','recettes_validate',
            'depenses','depenses_create','depenses_edit','depenses_delete','depenses_validate',
            'recettes_camions','recettes_camions_create','recettes_camions_edit','recettes_camions_delete','recettes_camions_validate',
            'versements','versements_create','versements_edit','versements_delete','versements_validate',
            'imports','exports','recherche',
            'rapports','rapports_journalier','rapports_hebdo','rapports_mensuel','rapports_agence','rapports_camion',
            'rapprochement','rapprochement_create','rapprochement_validate',
            'audit','sauvegarde','sauvegarde_restore',
            'notifications','justificatifs','justificatifs_upload','impression'];

        $utilPerms = ['dashboard','users','users_create','users_edit',
            'settings','recettes','recettes_create','recettes_edit',
            'depenses','depenses_create','depenses_edit',
            'recettes_camions','recettes_camions_create','recettes_camions_edit',
            'versements','versements_create','versements_edit',
            'imports','exports','recherche',
            'rapports','rapports_journalier','rapports_hebdo','rapports_mensuel','rapports_agence','rapports_camion',
            'rapprochement','notifications','justificatifs','justificatifs_upload','impression'];

        $visitPerms = ['dashboard','recettes','depenses','recettes_camions','versements','recherche',
            'rapports','rapports_journalier','rapports_hebdo','rapports_mensuel','rapports_agence','rapports_camion',
            'rapprochement','notifications','justificatifs','impression'];

        $rolePermMap = ['admin' => $adminPerms, 'utilisateur' => $utilPerms, 'visiteur' => $visitPerms];
        $stmtRP = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission) SELECT r.id, ? FROM roles r WHERE r.slug = ?");
        foreach ($rolePermMap as $slug => $perms) {
            foreach ($perms as $p) { $stmtRP->execute([$p, $slug]); }
        }
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Rôles et permissions par défaut créés';

        // Paramètres système
        $params = [
            ['app_name', 'DANAY EXPRESS SARL', 'Nom de l\'entreprise'],
            ['app_currency', 'XOF', 'Devise par défaut'],
            ['app_email', 'contact@danayexpress.com', 'Email de contact'],
            ['app_telephone', '+224 XXX XXX XXX', 'Téléphone'],
            ['backup_auto', '0', 'Sauvegarde automatique (0=non, 1=oui)'],
            ['backup_frequency', 'daily', 'Fréquence sauvegarde (daily/weekly/monthly)'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO parametres (cle, valeur, description) VALUES (?, ?, ?)");
        foreach ($params as $p) { $stmt->execute($p); }
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Paramètres système créés';

        // Créer les répertoires
        $dirs = [
            __DIR__ . '/../uploads/',
            __DIR__ . '/../uploads/justificatifs/',
            __DIR__ . '/../backups/',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
        }
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Répertoires créés';

        // Mettre à jour le fichier database.php
        $dbConfigContent = "<?php\n// Configuration base de données - DanayLedger\n\n";
        $dbConfigContent .= "define('DB_HOST', '$host');\n";
        $dbConfigContent .= "define('DB_NAME', '$dbname');\n";
        $dbConfigContent .= "define('DB_USER', '$dbuser');\n";
        $dbConfigContent .= "define('DB_PASS', '$dbpass');\n";
        $dbConfigContent .= "define('DB_CHARSET', 'utf8mb4');\n\n";
        $dbConfigContent .= "function getDB(): PDO {\n    static \$pdo = null;\n    if (\$pdo === null) {\n        \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;\n        \$options = [\n            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n            PDO::ATTR_EMULATE_PREPARES => false,\n        ];\n        try {\n            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);\n        } catch (PDOException \$e) {\n            die(\"Erreur de connexion : \" . htmlspecialchars(\$e->getMessage()));\n        }\n    }\n    return \$pdo;\n}";
        file_put_contents(__DIR__ . '/../config/database.php', $dbConfigContent);
        $messages[] = '<i class="bi bi-check-circle text-success me-1"></i> Configuration base de données sauvegardée';

    } catch (Exception $e) {
        $hasError = true;
        $messages[] = '<i class="bi bi-x-circle text-danger me-1"></i> Erreur : ' . htmlspecialchars($e->getMessage());
    }
}

$installed = file_exists(__DIR__ . '/../config/.installed');
?>

<?php if (!$installed || (isset($_POST['install']) && !$hasError && !empty($messages))): ?>

<?php if (isset($_POST['install']) && !$hasError): ?>
    <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Installation réussie !</h5>
        <hr>
        <?php foreach ($messages as $msg): ?>
            <div class="mb-1"><?php echo $msg; ?></div>
        <?php endforeach; ?>
        <hr>
        <div class="alert alert-warning">
            <strong>Identifiants administrateur :</strong><br>
            Nom d'utilisateur : <code>admin</code><br>
            Mot de passe : <code>admin123</code><br>
            <small class="text-danger">Changez ce mot de passe dès votre première connexion !</small>
        </div>
        <a href="../login.php" class="btn btn-primary btn-lg w-100 mt-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>Accéder à l'application
        </a>
    </div>
    <?php file_put_contents(__DIR__ . '/../config/.installed', date('Y-m-d H:i:s')); ?>
<?php else: ?>
    <form method="POST">
        <input type="hidden" name="step" value="2">
        <h5 class="mb-3"><i class="bi bi-database me-2"></i>Configuration de la base de données</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Hôte</label>
                <input type="text" name="db_host" value="localhost" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nom de la base</label>
                <input type="text" name="db_name" value="danay_ledger" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Utilisateur</label>
                <input type="text" name="db_user" value="root" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="db_pass" class="form-control">
            </div>
        </div>
        <hr>
        <button type="submit" name="install" class="btn btn-primary btn-lg w-100">
            <i class="bi bi-gear me-2"></i>Installer l'application
        </button>
    </form>
<?php endif; ?>

<?php else: ?>
    <div class="text-center">
        <div class="mb-4">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
        </div>
        <h4>L'application est déjà installée</h4>
        <p class="text-muted">Pour réinstaller, supprimez le fichier <code>config/.installed</code></p>
        <a href="../login.php" class="btn btn-primary btn-lg mt-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
        </a>
    </div>
<?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>