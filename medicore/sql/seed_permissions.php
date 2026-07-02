<?php
/**
 * seed_permissions.php — Réinitialise la matrice de permissions (role_page_access
 * + role_action_access) avec des limites réalistes par rôle (vraie vie hospitalière).
 *
 * Idempotent : DELETE puis INSERT dans une transaction. Re-exécutable → même état.
 *
 * Usage :  php sql/seed_permissions.php
 *
 * Après exécution, les sessions existantes gardent l'ancien cache de permissions
 * → se déconnecter / reconnecter (ou invalidate_permissions_cache()) pour rafraîchir.
 */

require_once __DIR__ . '/../includes/permissions.php'; // ALL_PAGES, ALL_ACTIONS (constants only, no side effects)

$PDO = new PDO('mysql:host=127.0.0.1;dbname=medicore;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ── Listes canoniques ──
$ALL_PAGES   = array_keys(ALL_PAGES);
$ALL_ACTIONS = array_keys(ALL_ACTIONS);

// ── Matrice réaliste : seules les pages/actions listées sont autorisées (1). ──
$MATRIX = [
    'admin' => [
        'pages'   => $ALL_PAGES,   // tout
        'actions' => $ALL_ACTIONS, // tout
    ],
    'medecin' => [
        'pages' => [
            'dashboard','accueil','patients','appointments','urgences','dossiers',
            'medecins','lits','pharmacie','laboratoire','rapports','observations',
            'mar','notes','chirurgie','imagerie','maternite','deces','assurances',
            'notifications','consentements',
        ],
        'actions' => [
            'patients.create','patients.edit',
            'appointments.create','appointments.edit_statut','appointments.delete',
            'hospitalisations.create','hospitalisations.update','hospitalisations.sortie',
            'lits.update_statut',
            'analyses.create','analyses.update_resultat',
            'ordonnances.create',
            'triage.update',
            'chirurgie.create','chirurgie.update',
            'imagerie.create','imagerie.update_resultat',
            'maternite.create','maternite.update',
            'deces.create',
            'observations.create','observations.view',
            'mar.administer','mar.view',
            'notes.create','notes.view',
            'notifications.view','notifications.send',
            'consentements.create','consentements.view',
        ],
    ],
    'infirmier' => [
        'pages' => [
            'dashboard','accueil','patients','appointments','urgences','dossiers',
            'medecins','lits','pharmacie','laboratoire','stocks','rapports',
            'observations','mar','notes','chirurgie','imagerie','maternite','deces',
            'assurances','notifications','consentements',
        ],
        'actions' => [
            'patients.create','patients.edit',
            'appointments.create','appointments.edit_statut','appointments.delete',
            'hospitalisations.create','hospitalisations.update','hospitalisations.sortie',
            'lits.update_statut',
            'triage.update',
            'maternite.create','maternite.update',
            'observations.create','observations.view',
            'mar.administer','mar.view',
            'notes.create','notes.view',
            'notifications.view','notifications.send',
            'consentements.create','consentements.view',
            'accueil.checkin','accueil.vitals',
        ],
    ],
    'pharmacien' => [
        'pages' => [
            'dashboard','patients','dossiers','medecins','pharmacie','stocks',
            'rapports','observations','mar','notes','notifications',
        ],
        'actions' => [
            'medicaments.create',
            'ordonnances.create','ordonnances.encaisser',
            'stocks.create','stocks.update_qte','stocks.entry',
            'observations.view','mar.view','notes.view',
            'notifications.view',
        ],
    ],
    'caissier' => [
        'pages' => [
            'dashboard','accueil','patients','caisse','facturation',
            'assurances','rapports','notifications',
        ],
        'actions' => [
            'caisse.create_vente','caisse.annuler','caisse.session_ouvrir',
            'caisse.session_fermer','caisse.ticket_service',
            'factures.create','factures.update_statut',
            'notifications.view',
        ],
    ],
    'comptable' => [
        'pages' => [
            'dashboard','patients','medecins','caisse','facturation','rh','stocks',
            'rapports','assurances','notifications','audit',
        ],
        'actions' => [
            'caisse.session_fermer',
            'factures.create','factures.update_statut',
            'assurances.create','assurances.update',
            'notifications.view','audit.view',
        ],
    ],
];

// ── Appliquer en transaction ──
$PDO->beginTransaction();
try {
    $PDO->exec('DELETE FROM role_page_access');
    $PDO->exec('DELETE FROM role_action_access');

    $stP = $PDO->prepare('INSERT INTO role_page_access (role,page,allowed) VALUES (?,?,1)');
    $stA = $PDO->prepare('INSERT INTO role_action_access (role,action,allowed) VALUES (?,?,1)');

    $nP = $nA = 0;
    foreach ($MATRIX as $role => $cfg) {
        foreach ($cfg['pages'] as $page) {
            $stP->execute([$role, $page]); $nP++;
        }
        foreach ($cfg['actions'] as $action) {
            $stA->execute([$role, $action]); $nA++;
        }
    }
    $PDO->commit();
    echo "OK : $nP pages + $nA actions insérées (6 rôles)." . PHP_EOL;
} catch (Throwable $e) {
    $PDO->rollBack();
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}