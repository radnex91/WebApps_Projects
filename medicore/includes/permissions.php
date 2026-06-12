<?php
// ============================================================
//  MediCore ERP - Permissions dynamiques chargees depuis la BDD
//  L'admin peut tout modifier via l'interface de gestion
// ============================================================

define('ROLE_LABELS', [
    'admin'      => 'Administrateur',
    'medecin'    => 'Médecin',
    'infirmier'  => 'Infirmier(ère)',
    'pharmacien' => 'Pharmacien',
    'comptable'  => 'Comptable',
]);

define('ALL_PAGES', [
    'dashboard'    => ['label'=>"Vue d'ensemble",      'icon'=>'dashboard',   'section'=>'Tableau de bord'],
    'analytics'    => ['label'=>'Analytiques',          'icon'=>'analytics',   'section'=>''],
    'patients'     => ['label'=>'Patients',             'icon'=>'patients',    'section'=>''],
    'appointments' => ['label'=>'Rendez-vous',          'icon'=>'appointments','section'=>''],
    'urgences'     => ['label'=>'Urgences',             'icon'=>'urgences',    'section'=>''],
    'dossiers'     => ['label'=>'Dossiers médicaux',    'icon'=>'dossiers',    'section'=>''],
    'medecins'     => ['label'=>'Personnel médical',    'icon'=>'medecins',    'section'=>'Ressources'],
    'lits'         => ['label'=>'Gestion des lits',     'icon'=>'lits',        'section'=>''],
    'pharmacie'    => ['label'=>'Pharmacie',            'icon'=>'pharmacie',   'section'=>''],
    'caisse'       => ['label'=>'Caisse & Tickets',     'icon'=>'caisse',      'section'=>''],
    'laboratoire'  => ['label'=>'Laboratoire',          'icon'=>'laboratoire', 'section'=>''],
    'facturation'  => ['label'=>'Facturation',          'icon'=>'facturation', 'section'=>'Administration'],
    'rh'           => ['label'=>'Ressources humaines',  'icon'=>'rh',          'section'=>''],
    'stocks'       => ['label'=>'Stocks & Matériel',    'icon'=>'stocks',      'section'=>''],
    'rapports'     => ['label'=>'Rapports',             'icon'=>'rapports',    'section'=>''],
    'observations' => ['label'=>'Signes vitaux',         'icon'=>'observations','section'=>'Clinique'],
    'mar'          => ['label'=>'Admin. médicaments',    'icon'=>'mar',         'section'=>''],
    'notes'        => ['label'=>'Notes cliniques',       'icon'=>'notes',       'section'=>''],
    'chirurgie'    => ['label'=>'Bloc opératoire',       'icon'=>'chirurgie',   'section'=>''],
    'imagerie'     => ['label'=>'Imagerie médicale',     'icon'=>'imagerie',    'section'=>''],
    'maternite'    => ['label'=>'Maternité',             'icon'=>'maternite',   'section'=>''],
    'deces'        => ['label'=>'Décès / Thanatologie',  'icon'=>'deces',       'section'=>''],
    'assurances'   => ['label'=>'Assurances',            'icon'=>'assurances',  'section'=>''],
    'portail'      => ['label'=>'Portail patient',       'icon'=>'portail',     'section'=>''],
    'notifications'=> ['label'=>'Notifications',         'icon'=>'notifications','section'=>''],
    'consentements'=> ['label'=>'Consentements',         'icon'=>'consentements','section'=>''],
    'audit'        => ['label'=>'Audit d\'accès',        'icon'=>'audit',       'section'=>''],
    'utilisateurs' => ['label'=>'Utilisateurs',         'icon'=>'utilisateurs','section'=>'Système'],
    'parametres'   => ['label'=>'Paramètres',           'icon'=>'parametres',  'section'=>''],
    'roles'        => ['label'=>'Roles & Permissions',     'icon'=>'roles',       'section'=>''],
]);

define('ALL_ACTIONS', [
    'patients.create'          => ['label'=>'Créer un patient',          'module'=>'Patients'],
    'patients.edit'            => ['label'=>'Modifier un patient',        'module'=>'Patients'],
    'patients.delete'          => ['label'=>'Supprimer un patient',       'module'=>'Patients'],
    'appointments.create'      => ['label'=>'Créer un RDV',               'module'=>'Rendez-vous'],
    'appointments.edit_statut' => ['label'=>'Modifier statut RDV',        'module'=>'Rendez-vous'],
    'appointments.delete'      => ['label'=>'Supprimer un RDV',           'module'=>'Rendez-vous'],
    'hospitalisations.create'  => ['label'=>'Admettre un patient',        'module'=>'Urgences'],
    'hospitalisations.update'  => ['label'=>'Modifier hospitalisation',   'module'=>'Urgences'],
    'hospitalisations.sortie'  => ['label'=>'Valider sortie patient',     'module'=>'Urgences'],
    'lits.update_statut'       => ['label'=>'Changer statut d\'un lit',    'module'=>'Lits'],
    'lits.create'              => ['label'=>'Ajouter un lit',             'module'=>'Lits'],
    'analyses.create'          => ['label'=>'Prescrire une analyse',      'module'=>'Laboratoire'],
    'analyses.update_resultat' => ['label'=>'Saisir résultat analyse',    'module'=>'Laboratoire'],
    'medicaments.create'       => ['label'=>'Ajouter un médicament',      'module'=>'Pharmacie'],
    'ordonnances.create'       => ['label'=>'Créer une ordonnance',       'module'=>'Pharmacie'],
    'ordonnances.encaisser'    => ['label'=>'Encaisser une ordonnance',   'module'=>'Pharmacie'],
    'caisse.create_vente'      => ['label'=>'Créer une vente caisse',     'module'=>'Caisse'],
    'caisse.annuler'           => ['label'=>'Annuler un ticket caisse',   'module'=>'Caisse'],
    'stocks.create'            => ['label'=>'Ajouter article au stock',   'module'=>'Stocks'],
    'stocks.update_qte'        => ['label'=>'Modifier quantité stock',    'module'=>'Stocks'],
    'stocks.entry'             => ['label'=>'Enregistrer une entrée stock','module'=>'Stocks'],
    'medecins.create'          => ['label'=>'Ajouter du personnel',       'module'=>'Personnel'],
    'medecins.toggle_statut'   => ['label'=>'Activer/desactiver compte',  'module'=>'Personnel'],
    'observations.create'       => ['label'=>'Saisir des constantes',      'module'=>'Signes vitaux'],
    'observations.view'         => ['label'=>'Voir les observations',      'module'=>'Signes vitaux'],
    'mar.administer'            => ['label'=>'Administrer un médicament',  'module'=>'MAR'],
    'mar.view'                  => ['label'=>'Voir le plan MAR',           'module'=>'MAR'],
    'notes.create'              => ['label'=>'Ecrire une note clinique',   'module'=>'Notes cliniques'],
    'notes.view'                => ['label'=>'Lire les notes cliniques',   'module'=>'Notes cliniques'],
    'triage.update'             => ['label'=>'Effectuer un triage',        'module'=>'Urgences'],
    'chirurgie.create'          => ['label'=>'Programmer une intervention','module'=>'Chirurgie'],
    'chirurgie.update'          => ['label'=>'Modifier une intervention',  'module'=>'Chirurgie'],
    'imagerie.create'           => ['label'=>'Prescrire un examen image',  'module'=>'Imagerie'],
    'imagerie.update_resultat'  => ['label'=>'Saisir resultat imagerie',   'module'=>'Imagerie'],
    'assurances.create'         => ['label'=>'Creer une prise en charge',  'module'=>'Assurances'],
    'assurances.update'         => ['label'=>'Modifier prise en charge',   'module'=>'Assurances'],
    'maternite.create'          => ['label'=>'Creer un suivi grossesse',   'module'=>'Maternite'],
    'maternite.update'          => ['label'=>'Enregistrer accouchement',   'module'=>'Maternite'],
    'deces.create'              => ['label'=>'Etablir un certificat deces','module'=>'Deces'],
    'factures.create'           => ['label'=>'Creer une facture',          'module'=>'Facturation'],
    'factures.update_statut'    => ['label'=>'Modifier statut facture',    'module'=>'Facturation'],
    'notifications.view'        => ['label'=>'Voir les notifications',     'module'=>'Notifications'],
    'notifications.send'        => ['label'=>'Envoyer des notifications',  'module'=>'Notifications'],
    'consentements.create'      => ['label'=>'Enregistrer un consentement','module'=>'Consentements'],
    'consentements.view'        => ['label'=>'Consulter les consentements','module'=>'Consentements'],
    'audit.view'                => ['label'=>'Consulter l\'audit',         'module'=>'Audit'],
    'api.access'                => ['label'=>'Acceder a l\'API REST',      'module'=>'API'],
]);

//  Charger depuis BDD (cache session par role) 
function _load_permissions(): void {
    if (isset($_SESSION['_perm_loaded'])) return;
    try {
        $db      = getDB();
        $pages   = $db->query("SELECT role,page,allowed FROM role_page_access")->fetchAll(PDO::FETCH_ASSOC);
        $actions = $db->query("SELECT role,action,allowed FROM role_action_access")->fetchAll(PDO::FETCH_ASSOC);
        $pm = $am = [];
        foreach ($pages   as $p) $pm[$p['role']][$p['page']]     = (bool)$p['allowed'];
        foreach ($actions as $a) $am[$a['role']][$a['action']]   = (bool)$a['allowed'];
        $_SESSION['_perm_pages']   = $pm;
        $_SESSION['_perm_actions'] = $am;
        $_SESSION['_perm_loaded']  = true;
    } catch (Exception $e) {
        _log_error('PERMISSIONS', 'Echec chargement permissions BDD', __FILE__, __LINE__, $e);
        $_SESSION['_perm_pages'] = $_SESSION['_perm_actions'] = [];
        $_SESSION['_perm_loaded'] = true;
    }
}

function invalidate_permissions_cache(): void {
    unset($_SESSION['_perm_loaded'], $_SESSION['_perm_pages'], $_SESSION['_perm_actions']);
}

function canAccessPage(string $page): bool {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') return true;
    _load_permissions();
    return $_SESSION['_perm_pages'][$role][$page] ?? false;
}

function can(string $action): bool {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') return true;
    _load_permissions();
    return $_SESSION['_perm_actions'][$role][$action] ?? false;
}

function requirePageAccess(string $page): void {
    if (canAccessPage($page)) return;
    $role      = $_SESSION['user_role'] ?? '';
    $roleLabel = ROLE_LABELS[$role] ?? $role;
    http_response_code(403);
    echo '<div style="text-align:center;padding:60px 20px">
        <div style="font-size:52px;margin-bottom:16px"></div>
        <h2 style="color:var(--text);margin-bottom:10px">Accès refusé</h2>
        <p style="color:var(--text2);margin-bottom:6px">
            Votre rôle <strong style="color:var(--accent2)">' . htmlspecialchars($roleLabel) . '</strong>
            ne vous autorise pas à accéder à ce module.
        </p>
        <p style="color:var(--text3);font-size:13px;margin-bottom:20px">
            Contactez votre administrateur pour modifier vos permissions.
        </p>
        <a href="' . APP_URL . '/dashboard.php" class="btn btn-blue">Retour au tableau de bord</a>
    </div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

function getAccessibleNav(): array {
    $icons = defined('ICON_NAV') ? ICON_NAV : [];
    // Compteurs pour badges d'alerte
    $badges = [];
    try {
        $nb_crit = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='critique'");
        $nb_labo = (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='disponible'");
        $nb_ord  = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'");
        if ($nb_crit > 0) $badges['urgences']    = $nb_crit;
        if ($nb_labo > 0) $badges['laboratoire'] = $nb_labo;
        if ($nb_ord  > 0) $badges['pharmacie']   = $nb_ord;
    } catch (Exception $e) { _log_error('NAV_BADGES', 'Echec compteurs badges nav', __FILE__, __LINE__, $e); }

    $nav = [];
    foreach (ALL_PAGES as $page => $info) {
        if (canAccessPage($page)) {
            $nav[$page] = [
                'icon'    => $icons[$page] ?? '',
                'label'   => $info['label'],
                'section' => $info['section'],
                'badge'   => $badges[$page] ?? '',
            ];
        }
    }
    return $nav;
}

function getRolesConfig(): array {
    try { return db_select("SELECT * FROM roles_config WHERE actif=1 ORDER BY id ASC"); }
    catch (Exception $e) { _log_error('ROLES', 'Echec lecture roles_config', __FILE__, __LINE__, $e); return []; }
}

function getFullPermissionsMatrix(): array {
    try {
        $db = getDB();
        $pages   = $db->query("SELECT role,page,allowed FROM role_page_access")->fetchAll(PDO::FETCH_ASSOC);
        $actions = $db->query("SELECT role,action,allowed FROM role_action_access")->fetchAll(PDO::FETCH_ASSOC);
        $m = ['pages'=>[], 'actions'=>[]];
        foreach ($pages   as $p) $m['pages'][$p['role']][$p['page']]     = (bool)$p['allowed'];
        foreach ($actions as $a) $m['actions'][$a['role']][$a['action']] = (bool)$a['allowed'];
        return $m;
    } catch (Exception $e) { _log_error('PERMISSIONS', 'Echec lecture matrice permissions', __FILE__, __LINE__, $e); return ['pages'=>[], 'actions'=>[]]; }
}
