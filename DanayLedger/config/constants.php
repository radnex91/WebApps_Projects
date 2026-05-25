<?php
// Constantes application - DanayLedger v2

// Application
define('APP_NAME', 'DanayLedger');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/DanayLedger');

// Rôles utilisateurs (slugs - compatibilité)
define('ROLE_ADMIN', 'admin');
define('ROLE_UTILISATEUR', 'utilisateur');
define('ROLE_VISITEUR', 'visiteur');

// Les rôles et permissions sont désormais gérés dynamiquement en base de données.
// Les constantes ROLES et PERMISSIONS sont remplacées par les fonctions dans auth.php :
//   getAllRoles()       → remplace ROLES
//   loadRolePermissions() → remplace PERMISSIONS

// Groupes de permissions pour l'interface d'administration
define('PERMISSION_GROUPS', [
    'Principal' => ['dashboard'],
    'Utilisateurs' => ['users', 'users_create', 'users_edit', 'users_delete', 'users_toggle'],
    'Paramétrage' => ['settings', 'settings_agences', 'settings_categories', 'settings_types',
        'settings_vehicules', 'settings_params', 'settings_banks', 'settings_proprietaires',
        'settings_repartition', 'settings_roles'],
    'Recettes' => ['recettes', 'recettes_create', 'recettes_edit', 'recettes_delete', 'recettes_validate'],
    'Dépenses' => ['depenses', 'depenses_create', 'depenses_edit', 'depenses_delete', 'depenses_validate'],
    'Recettes camions' => ['recettes_camions', 'recettes_camions_create', 'recettes_camions_edit',
        'recettes_camions_delete', 'recettes_camions_validate'],
    'Versements' => ['versements', 'versements_create', 'versements_edit', 'versements_delete', 'versements_validate'],
    'Import/Export' => ['imports', 'exports'],
    'Recherche' => ['recherche'],
    'Rapports' => ['rapports', 'rapports_journalier', 'rapports_hebdo', 'rapports_mensuel', 'rapports_agence', 'rapports_camion'],
    'Rapprochement' => ['rapprochement', 'rapprochement_create', 'rapprochement_validate'],
    'Outils' => ['audit', 'sauvegarde', 'sauvegarde_restore'],
    'Divers' => ['notifications', 'justificatifs', 'justificatifs_upload', 'impression'],
]);

// Statuts génériques
define('STATUS_EN_ATTENTE', 'en_attente');
define('STATUS_VALIDEE', 'validee');
define('STATUS_ANNULEE', 'annulee');

define('STATUSES', [
    STATUS_EN_ATTENTE => 'En attente',
    STATUS_VALIDEE    => 'Validée',
    STATUS_ANNULEE    => 'Annulée',
]);

// Statuts badge CSS
define('STATUS_BADGES', [
    STATUS_EN_ATTENTE => 'bg-warning text-dark',
    STATUS_VALIDEE    => 'bg-success',
    STATUS_ANNULEE    => 'bg-danger',
]);

// Types de transaction
define('TRANSACTION_TYPES', [
    'revenu'    => 'Revenu',
    'depense'   => 'Dépense',
    'transfert' => 'Transfert',
]);

// Catégories par défaut
define('DEFAULT_CATEGORIES_RECETTE', [
    'transport_marchandises' => 'Transport de marchandises',
    'location_vehicule'      => 'Location de véhicule',
    'commission'             => 'Commission',
    'vente'                  => 'Vente',
    'autre_recette'          => 'Autre recette',
]);

define('DEFAULT_CATEGORIES_DEPENSE', [
    'carburant'       => 'Carburant',
    'maintenance'     => 'Maintenance & Réparations',
    'salaires'        => 'Salaires & Charges',
    'assurance'       => 'Assurance',
    'frais_douane'    => 'Frais de douane',
    'loyer'           => 'Loyer',
    'fournitures'     => 'Fournitures de bureau',
    'telecommunications' => 'Télécommunications',
    'frais_route'     => 'Frais de route & Péages',
    'autre_depense'   => 'Autre dépense',
]);

// Devises
define('CURRENCIES', [
    'XOF' => 'FCFA',
    'EUR' => 'Euro',
    'USD' => 'Dollar US',
]);

define('DEFAULT_CURRENCY', 'XOF');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Sécurité
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('CSRF_TOKEN_EXPIRY', 3600);

// Upload
define('UPLOAD_MAX_SIZE', 5242880); // 5 MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/justificatifs/');
define('BACKUP_DIR', __DIR__ . '/../backups/');

// Types d'entités pour justificatifs et audit
define('ENTITY_TYPES', [
    'recette'          => 'Recette journalière',
    'depense'           => 'Dépense',
    'recette_camion'    => 'Recette camion',
    'versement'         => 'Versement bancaire',
    'agence'            => 'Agence',
    'bank'              => 'Banque',
    'proprietaire'      => 'Propriétaire',
]);

// Actions d'audit
define('AUDIT_ACTIONS', [
    'create'  => 'Création',
    'update'  => 'Modification',
    'delete'  => 'Suppression',
    'validate'=> 'Validation',
    'cancel'  => 'Annulation',
    'login'   => 'Connexion',
    'logout'  => 'Déconnexion',
    'export'  => 'Exportation',
    'import'  => 'Importation',
    'backup'  => 'Sauvegarde',
    'restore' => 'Restauration',
]);