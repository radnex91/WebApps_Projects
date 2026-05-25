<?php
$currentPage = 'roles';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!hasRole(['admin'])) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$onglet = in_whitelist(get_str('tab'), ['roles','permissions','apercu'], 'roles');

//  AUTO-CRÉATION DES TABLES SI MANQUANTES
try {
    getDB()->query("SELECT 1 FROM roles_config LIMIT 1");
} catch (Exception $e) {
    // Créer les tables manquantes
    getDB()->exec("CREATE TABLE IF NOT EXISTS `roles_config` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `role` VARCHAR(50) NOT NULL UNIQUE,
        `label` VARCHAR(100) NOT NULL,
        `couleur` VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
        `description` VARCHAR(255) NOT NULL DEFAULT '',
        `actif` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    getDB()->exec("CREATE TABLE IF NOT EXISTS `role_page_access` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `role` VARCHAR(50) NOT NULL,
        `page` VARCHAR(50) NOT NULL,
        `allowed` TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `role_page` (`role`,`page`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    getDB()->exec("CREATE TABLE IF NOT EXISTS `role_action_access` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `role` VARCHAR(50) NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `allowed` TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `role_action` (`role`,`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insérer les rôles de base
    $baseRoles = [
        ['admin','Administrateur','#ef4444','Accès total'],
        ['medecin','Médecin','#3b82f6','Accès clinique complet'],
        ['infirmier','Infirmier(ère)','#10b981','Soins et suivi patients'],
        ['pharmacien','Pharmacien','#8b5cf6','Pharmacie et stocks'],
        ['comptable','Comptable','#f59e0b','Facturation et rapports'],
    ];
    $stmt = getDB()->prepare("INSERT IGNORE INTO roles_config (role,label,couleur,description) VALUES (?,?,?,?)");
    foreach ($baseRoles as $r) $stmt->execute($r);

    invalidate_roles_cache();
    invalidate_permissions_cache();
}

// Même vérification pour app_settings
try {
    getDB()->query("SELECT 1 FROM app_settings LIMIT 1");
} catch (Exception $e) {
    getDB()->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
        `cle` VARCHAR(60) NOT NULL,
        `valeur` VARCHAR(500) NOT NULL DEFAULT '',
        `label` VARCHAR(120) NOT NULL DEFAULT '',
        PRIMARY KEY (`cle`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Valeurs par défaut
    $stmt = getDB()->prepare("INSERT IGNORE INTO app_settings (cle,valeur) VALUES (?,?)");
    foreach ([
        ['app_name','MediCore ERP'],['etablissement',''],['currency_code','XAF'],
        ['currency_symbol','FCFA'],['currency_decimals','0'],['currency_position','after'],
        ['currency_dec_sep',','],['currency_thou_sep',' '],['font_body','DM Sans'],
        ['font_heading','Playfair Display'],['font_size','14'],['date_format','d/m/Y'],
        ['theme_primary','1E3A5F'],['theme_accent','3b82f6'],['theme_preset','ocean'],['theme_bg','0a0e1a'],['theme_surface','111827'],
        ['logo_base64',''],['logo_nom',''],['entete_rapport',''],
    ] as $kv) $stmt->execute($kv);
}

//  CRÉER UN NOUVEAU RÔLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_role') {
    csrf_verify();
    $label  = trim(post_str('label'));
    $couleur= preg_match('/^#[0-9a-fA-F]{6}$/', post_str('couleur')) ? post_str('couleur') : '#3b82f6';
    $desc   = trim(post_str('description'));
    $from   = post_str('clone_from'); // copier les permissions d'un rôle existant

    if (!$label) { $flash = ['red','Le libellé est obligatoire.']; }
    else {
        // Générer un slug unique depuis le label
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
        $slug = trim($slug, '_');
        $slug = substr($slug, 0, 40);
        // Éviter les doublons
        $exists = db_scalar("SELECT COUNT(*) FROM roles_config WHERE role=?", [$slug]);
        if ($exists) $slug = $slug . '_' . date('His');

        db_exec("INSERT INTO roles_config (role,label,couleur,description) VALUES (?,?,?,?)",
            [$slug, $label, $couleur, $desc]);

        // Initialiser les permissions (tout à 0 par défaut, ou copie d'un rôle)
        $pages   = array_keys(ALL_PAGES);
        $actions = array_keys(ALL_ACTIONS);

        $stmtPg = getDB()->prepare("INSERT IGNORE INTO role_page_access (role,page,allowed) VALUES (?,?,?)");
        $stmtAc = getDB()->prepare("INSERT IGNORE INTO role_action_access (role,action,allowed) VALUES (?,?,?)");

        if ($from && $from !== 'vide') {
            // Copier depuis un rôle existant
            foreach ($pages as $pg) {
                $allowed = (int)db_scalar("SELECT allowed FROM role_page_access WHERE role=? AND page=?", [$from, $pg]);
                $stmtPg->execute([$slug, $pg, $allowed]);
            }
            foreach ($actions as $ac) {
                $allowed = (int)db_scalar("SELECT allowed FROM role_action_access WHERE role=? AND action=?", [$from, $ac]);
                $stmtAc->execute([$slug, $ac, $allowed]);
            }
        } else {
            // Tout a 0
            foreach ($pages   as $pg) $stmtPg->execute([$slug, $pg, 0]);
            foreach ($actions as $ac) $stmtAc->execute([$slug, $ac, 0]);
        }

        invalidate_roles_cache();
        invalidate_permissions_cache();
        logActivity("Nouveau rôle créé: $slug ($label)", 'green', 'role');
        $flash = ['green', "Rôle \"$label\" créé avec succès. Configurez ses permissions ci-dessous."];
        header('Location: ' . APP_URL . '/roles.php?tab=permissions&role=' . urlencode($slug) . '&saved=1');
        exit;
    }
}

//  MODIFIER UN RÔLE EXISTANT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_role') {
    csrf_verify();
    $role   = post_str('role');
    $label  = trim(post_str('label'));
    $couleur= preg_match('/^#[0-9a-fA-F]{6}$/', post_str('couleur')) ? post_str('couleur') : '#3b82f6';
    $desc   = trim(post_str('description'));

    if ($role === 'admin') { $flash = ['red','Le rôle admin ne peut pas être modifié.']; }
    elseif (!$label)       { $flash = ['red','Le libellé est obligatoire.']; }
    else {
        db_exec("UPDATE roles_config SET label=?,couleur=?,description=? WHERE role=?",
            [$label, $couleur, $desc, $role]);
        invalidate_roles_cache();
        logActivity("Rôle $role mis à jour: $label", 'blue', 'role');
        header('Location: ' . APP_URL . '/roles.php?tab=roles&saved=1'); exit;
    }
}

//  SUPPRIMER UN RÔLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'delete_role') {
    csrf_verify();
    $role = post_str('role');
    $base = ['admin','medecin','infirmier','pharmacien','comptable']; // intentionnel: rôles système non supprimables

    if (in_array($role, $base)) {
        $flash = ['red','Les rôles de base ne peuvent pas être supprimés.'];
    } else {
        $nb = (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE role=?", [$role]);
        if ($nb > 0) {
            $flash = ['red',"Impossible : $nb utilisateur(s) ont ce rôle. Réassignez-les d'abord."];
        } else {
            db_exec("DELETE FROM roles_config       WHERE role=?", [$role]);
            db_exec("DELETE FROM role_page_access   WHERE role=?", [$role]);
            db_exec("DELETE FROM role_action_access WHERE role=?", [$role]);
            invalidate_roles_cache();
            invalidate_permissions_cache();
            logActivity("Rôle $role supprimé", 'red', 'role');
            header('Location: ' . APP_URL . '/roles.php?tab=roles&deleted=1'); exit;
        }
    }
}

//  SAUVEGARDER PERMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'save_permissions') {
    csrf_verify();
    $role = post_str('role_target');
    if (!$role || $role === 'admin') {
        header('Location: ' . APP_URL . '/roles.php?tab=permissions'); exit;
    }

    $stmtPg = getDB()->prepare("INSERT INTO role_page_access (role,page,allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)");
    $stmtAc = getDB()->prepare("INSERT INTO role_action_access (role,action,allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)");

    foreach (array_keys(ALL_PAGES) as $pg) {
        $stmtPg->execute([$role, $pg, isset($_POST['page'][$pg]) ? 1 : 0]);
    }
    foreach (array_keys(ALL_ACTIONS) as $ac) {
        $stmtAc->execute([$role, $ac, isset($_POST['act'][$ac]) ? 1 : 0]);
    }
    invalidate_permissions_cache();
    logActivity("Permissions rôle $role mises à jour", 'blue', 'permissions');
    header('Location: ' . APP_URL . '/roles.php?tab=permissions&role=' . urlencode($role) . '&saved=1'); exit;
}

//  APPLIQUER PRÉSET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'apply_preset') {
    csrf_verify();
    $role   = post_str('role');
    $preset = post_str('preset');
    if ($role && $role !== 'admin') {
        $presets = [
            'tout'       => ['pg'=>1, 'ac'=>1],
            'rien'       => ['pg'=>0, 'ac'=>0],
            'lecture'    => ['pg'=>['dashboard','analytics','patients','appointments','urgences','medecins','lits','laboratoire','rapports'], 'ac'=>[]],
            'clinique'   => ['pg'=>['dashboard','analytics','patients','appointments','urgences','dossiers','medecins','lits','laboratoire','rapports'],
                             'ac'=>['patients.create','patients.edit','appointments.create','appointments.edit_statut','appointments.delete','hospitalisations.create','hospitalisations.update','hospitalisations.sortie','lits.update_statut','analyses.create','analyses.update_resultat','ordonnances.create']],
            'pharmacie'  => ['pg'=>['dashboard','pharmacie','caisse','stocks'],
                             'ac'=>['medicaments.create','ordonnances.encaisser','caisse.create_vente','caisse.annuler','stocks.create','stocks.update_qte']],
            'finance'    => ['pg'=>['dashboard','analytics','facturation','rapports','caisse'],
                             'ac'=>['caisse.annuler']],
            'soins'      => ['pg'=>['dashboard','patients','appointments','urgences','lits','laboratoire','medecins'],
                             'ac'=>['appointments.create','appointments.edit_statut','hospitalisations.update','lits.update_statut','analyses.update_resultat']],
        ];
        if (isset($presets[$preset])) {
            $p = $presets[$preset];
            $stmtPg = getDB()->prepare("INSERT INTO role_page_access (role,page,allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)");
            $stmtAc = getDB()->prepare("INSERT INTO role_action_access (role,action,allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)");
            foreach (array_keys(ALL_PAGES) as $pg) {
                $stmtPg->execute([$role, $pg, $p['pg']===1 ? 1 : ($p['pg']===0 ? 0 : (in_array($pg,$p['pg']) ? 1 : 0))]);
            }
            foreach (array_keys(ALL_ACTIONS) as $ac) {
                $stmtAc->execute([$role, $ac, $p['ac']===1 ? 1 : ($p['ac']===0 ? 0 : (in_array($ac,$p['ac']) ? 1 : 0))]);
            }
            invalidate_permissions_cache();
        }
    }
    header('Location: ' . APP_URL . '/roles.php?tab=permissions&role=' . urlencode($role) . '&saved=1'); exit;
}

//  DONNÉES
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('roles');

$all_roles   = getRolesConfig();
$roles_by_id = array_column($all_roles, null, 'role');
$base_roles  = ['admin','medecin','infirmier','pharmacien','comptable']; // intentionnel: rôles système de base
$nb_by_role  = array_column(db_select("SELECT role,COUNT(*) AS nb FROM utilisateurs GROUP BY role"), 'nb', 'role');
$matrix      = getFullPermissionsMatrix();

// Rôle sélectionné pour permissions
$roleFilter = get_str('role');
if (!$roleFilter || !isset($roles_by_id[$roleFilter])) {
    // Premier non-admin
    foreach ($all_roles as $rc) { if ($rc['role'] !== 'admin') { $roleFilter = $rc['role']; break; } }
}

// Regrouper actions par module
$actionsByModule = [];
foreach (ALL_ACTIONS as $key => $info) {
    $actionsByModule[$info['module']][$key] = $info['label'];
}

$presetOptions = [
    'rien'      => ['label'=>'Tout interdire',    'icon'=>'🚫'],
    'lecture'   => ['label'=>'Lecture seule',     'icon'=>'👁'],
    'soins'     => ['label'=>'Profil Soins',      'icon'=>'🏥'],
    'clinique'  => ['label'=>'Profil Clinique',   'icon'=>'🩺'],
    'pharmacie' => ['label'=>'Profil Pharmacie',  'icon'=>'💊'],
    'finance'   => ['label'=>'Profil Finance',    'icon'=>'💰'],
    'tout'      => ['label'=>'Tout autoriser',    'icon'=>''],
];
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-green alert-auto">Sauvegarde avec succès.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-yellow alert-auto">Rôle supprimé.</div>
<?php endif; ?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div>
    <h2>Rôles & Permissions</h2>
    <p><?= count($all_roles) ?> rôles configurés &mdash; système entièrement personnalisable</p>
  </div>
  <button class="btn btn-blue" onclick="document.getElementById('modal-new-role').style.display='flex'">
    + Nouveau rôle
  </button>
</div>

<!-- Onglets -->
<div class="pill-tabs" style="margin-bottom:24px">
  <a href="?tab=roles"       class="pill-tab <?= $onglet==='roles'?'active':'' ?>">Gestion des rôles</a>
  <a href="?tab=permissions&role=<?= h($roleFilter) ?>" class="pill-tab <?= $onglet==='permissions'?'active':'' ?>">Permissions</a>
  <a href="?tab=apercu"      class="pill-tab <?= $onglet==='apercu'?'active':'' ?>">Vue globale</a>
</div>

<?php /*
         ONGLET RÔLES — CRUD
 */ ?>
<?php if ($onglet === 'roles'): ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px">

  <?php foreach ($all_roles as $rc):
    $isBase  = in_array($rc['role'], $base_roles);
    $nb      = (int)($nb_by_role[$rc['role']] ?? 0);
    $isAdmin = $rc['role'] === 'admin';
    $col     = $rc['couleur'];
  ?>
  <div class="card" style="border-top:3px solid <?= h($col) ?>">
    <div style="padding:16px 20px;display:flex;align-items:flex-start;gap:12px">

      <!-- Pastille couleur + infos -->
      <div style="width:42px;height:42px;border-radius:10px;background:<?= h($col) ?>;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
        <?= $isAdmin ? '' : ($isBase ? '' : '') ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:16px;font-weight:700;color:var(--text)"><?= h($rc['label']) ?></div>
        <div style="font-size:11px;color:var(--text3);margin-top:2px">
          <code style="background:var(--surface2);padding:1px 6px;border-radius:4px"><?= h($rc['role']) ?></code>
          &nbsp;
          <?= $isBase ? '<span style="color:var(--text3)">Rôle de base</span>' : '<span style="color:var(--accent2)">Rôle personnalisé</span>' ?>
        </div>
        <?php if ($rc['description']): ?>
        <div style="font-size:12px;color:var(--text2);margin-top:4px"><?= h($rc['description']) ?></div>
        <?php endif; ?>
        <div style="margin-top:8px;display:flex;align-items:center;gap:8px">
          <span style="font-size:12px;background:var(--surface2);padding:3px 10px;border-radius:12px;color:var(--text2)">
            <?= $nb ?> utilisateur<?= $nb>1?'s':'' ?>
          </span>
          <a href="?tab=permissions&role=<?= h($rc['role']) ?>" style="font-size:12px;color:var(--accent2)">Voir permissions</a>
        </div>
      </div>

      <!-- Menu actions -->
      <?php if (!$isAdmin): ?>
      <div>
        <button class="btn btn-sm btn-ghost"
          onclick="openEditModal('<?= h(addslashes($rc['role'])) ?>','<?= h(addslashes($rc['label'])) ?>','<?= h($col) ?>','<?= h(addslashes($rc['description'])) ?>')">
          Modifier
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- Formulaire edition inline -->
    <?php if (!$isAdmin): ?>
    <div id="edit-<?= h($rc['role']) ?>" style="display:none;padding:16px 20px;border-top:1px solid var(--border);background:var(--surface2)">
      <form method="POST">
        <input type="hidden" name="action" value="update_role">
        <input type="hidden" name="role" value="<?= h($rc['role']) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="form-group"><label>Libellé *</label>
            <input type="text" name="label" value="<?= h($rc['label']) ?>" required maxlength="100">
          </div>
          <div class="form-group"><label>Couleur</label>
            <input type="color" name="couleur" value="<?= h($col) ?>"
              style="width:100%;height:38px;border:1px solid var(--border2);border-radius:7px;cursor:pointer;padding:2px">
          </div>
          <div class="form-group form-full"><label>Description</label>
            <input type="text" name="description" value="<?= h($rc['description']) ?>" maxlength="255">
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:space-between;align-items:center">
          <!-- Supprimer via formulaire externe (évite l'imbrication de forms) -->
          <?php if (!$isBase): ?>
          <button type="button" class="btn btn-sm btn-red"
            onclick="deleteRole('<?= h($rc['role']) ?>', '<?= h(addslashes($rc['label'])) ?>')">
            Supprimer
          </button>
          <?php else: ?>
          <span style="font-size:11px;color:var(--text3)">Rôle de base (non supprimable)</span>
          <?php endif; ?>
          <div style="display:flex;gap:8px">
            <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('edit-<?= h($rc['role']) ?>').style.display='none'">Annuler</button>
            <button type="submit" class="btn btn-sm btn-blue">Enregistrer</button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

</div>

<?php /* 
         ONGLET PERMISSIONS
 */ ?>
<?php elseif ($onglet === 'permissions'): ?>

<!-- Sélecteur rôle -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($all_roles as $rc):
    if ($rc['role'] === 'admin') continue;
    $nb  = (int)($nb_by_role[$rc['role']] ?? 0);
    $sel = $roleFilter === $rc['role'];
  ?>
  <a href="?tab=permissions&role=<?= h($rc['role']) ?>"
     style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:10px;text-decoration:none;
            border:2px solid <?= $sel?h($rc['couleur']):'var(--border)' ?>;
            background:<?= $sel?'rgba(255,255,255,.04)':'var(--surface)' ?>">
    <span style="width:10px;height:10px;border-radius:50%;background:<?= h($rc['couleur']) ?>;flex-shrink:0"></span>
    <span style="font-weight:600;color:var(--text);font-size:13px"><?= h($rc['label']) ?></span>
    <?php if ($nb > 0): ?><span style="font-size:10px;color:var(--text3)"><?= $nb ?> user<?= $nb>1?'s':'' ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php $rc = $roles_by_id[$roleFilter] ?? ['role'=>$roleFilter,'label'=>ucfirst($roleFilter),'couleur'=>'#3b82f6']; ?>

<!-- Barre rôle + presets -->
<div style="background:var(--surface2);border:1px solid var(--border2);border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:180px">
    <div style="width:14px;height:14px;border-radius:50%;background:<?= h($rc['couleur']) ?>"></div>
    <span style="font-weight:700;font-size:15px"><?= h($rc['label']) ?></span>
    <code style="font-size:11px;color:var(--text3);background:var(--surface);padding:1px 7px;border-radius:4px"><?= h($rc['role']) ?></code>
  </div>

  <!-- Presets -->
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <span style="font-size:11px;color:var(--text3);align-self:center">Profil rapide :</span>
    <?php foreach ($presetOptions as $pv => $po): ?>
    <form method="POST" style="margin:0">
      <input type="hidden" name="action" value="apply_preset">
      <input type="hidden" name="role" value="<?= h($roleFilter) ?>">
      <input type="hidden" name="preset" value="<?= $pv ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-ghost"
              onclick="return confirm('Appliquer le profil &laquo;<?= h($po['label']) ?>&raquo; ? Les permissions actuelles seront remplacées.')">
        <?= $po['icon'] ?> <?= h($po['label']) ?>
      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<form method="POST">
  <input type="hidden" name="action" value="save_permissions">
  <input type="hidden" name="role_target" value="<?= h($roleFilter) ?>">
  <?= csrf_field() ?>

  <!-- Boutons selection rapide -->
  <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <button type="button" class="btn btn-sm btn-ghost" onclick="setAll('.chk-page',true)">Pages : tout cocher</button>
    <button type="button" class="btn btn-sm btn-ghost" onclick="setAll('.chk-page',false)">Pages : tout décocher</button>
    <button type="button" class="btn btn-sm btn-ghost" onclick="setAll('.chk-act',true)">Actions : tout cocher</button>
    <button type="button" class="btn btn-sm btn-ghost" onclick="setAll('.chk-act',false)">Actions : tout décocher</button>
    <button type="submit" class="btn btn-blue" style="margin-left:auto">Sauvegarder les permissions</button>
  </div>

  <div class="grid-2">

    <!-- PAGES -->
    <div class="card">
      <div class="card-header">
        <h3>Modules accessibles</h3>
        <span style="font-size:11px;color:var(--text3)">Pages visibles dans la navigation</span>
      </div>
      <div style="padding:12px 20px">
        <?php $prevSection = ''; foreach (ALL_PAGES as $page => $info): ?>
        <?php if ($info['section'] && $info['section'] !== $prevSection): $prevSection = $info['section']; ?>
        <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;padding:10px 0 4px;border-top:1px solid var(--border);margin-top:4px"><?= h($info['section']) ?></div>
        <?php endif; ?>
        <?php $checked = $matrix['pages'][$roleFilter][$page] ?? false; ?>
        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:7px;cursor:pointer;margin:2px 0" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
          <input type="checkbox" name="page[<?= h($page) ?>]" value="1" class="chk-page"
                 <?= $checked?'checked':'' ?>
                 style="width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0">
          <span style="font-size:13px;flex:1"><?= h($info['label']) ?></span>
          <span class="badge <?= $checked?'badge-green':'badge-red' ?>" style="font-size:10px"><?= $checked?'Oui':'Non' ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="card">
      <div class="card-header">
        <h3>Actions autorisées</h3>
        <span style="font-size:11px;color:var(--text3)">Opérations que le rôle peut effectuer</span>
      </div>
      <div style="padding:12px 20px">
        <?php $prevMod = ''; foreach (ALL_ACTIONS as $action => $info): ?>
        <?php if ($info['module'] !== $prevMod): $prevMod = $info['module']; ?>
        <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;padding:10px 0 4px;border-top:1px solid var(--border);margin-top:4px"><?= h($info['module']) ?></div>
        <?php endif; ?>
        <?php $checked = $matrix['actions'][$roleFilter][$action] ?? false; ?>
        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:7px;cursor:pointer;margin:2px 0" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
          <input type="checkbox" name="act[<?= h($action) ?>]" value="1" class="chk-act"
                 <?= $checked?'checked':'' ?>
                 style="width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0">
          <span style="font-size:13px;flex:1"><?= h($info['label']) ?></span>
          <span class="badge <?= $checked?'badge-green':'badge-red' ?>" style="font-size:10px"><?= $checked?'OK':'Non' ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <div style="display:flex;justify-content:flex-end;padding:20px 0">
    <button type="submit" class="btn btn-blue" style="padding:12px 28px">
      Sauvegarder les permissions de &laquo;<?= h($rc['label']) ?>&raquo;
    </button>
  </div>
</form>

<?php /* 
         ONGLET APERCU GLOBAL
 */ ?>
<?php else: ?>

<!-- Modules -->
<div class="card mb-24">
  <div class="card-header"><h3>Accès aux modules par rôle</h3></div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th style="min-width:160px">Module</th>
          <?php foreach ($all_roles as $rc): ?>
          <th style="text-align:center;min-width:80px">
            <div style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:4px 0">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= h($rc['couleur']) ?>"></span>
              <span style="font-size:11px;font-weight:600"><?= h($rc['label']) ?></span>
            </div>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php $prevSec = ''; foreach (ALL_PAGES as $page => $info): ?>
        <?php if ($info['section'] && $info['section'] !== $prevSec): $prevSec = $info['section']; ?>
        <tr><td colspan="<?= count($all_roles)+1 ?>" style="padding:7px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;background:var(--surface2)"><?= h($info['section']) ?></td></tr>
        <?php endif; ?>
        <tr>
          <td style="font-size:13px;padding:7px 16px"><?= h($info['label']) ?></td>
          <?php foreach ($all_roles as $rc):
            $allowed = $rc['role']==='admin' ? true : ($matrix['pages'][$rc['role']][$page] ?? false);
          ?>
          <td style="text-align:center">
            <?php if ($rc['role']==='admin'): ?>
            <span style="color:var(--yellow);font-size:14px" title="Admin : accès total">⭐</span>
            <?php elseif ($allowed): ?>
            <span style="color:var(--green);font-size:14px">✅</span>
            <?php else: ?>
            <span style="color:var(--border2);font-size:14px">✗</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Actions -->
<div class="card">
  <div class="card-header"><h3>Actions par rôle</h3></div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th style="min-width:200px">Action</th>
          <?php foreach ($all_roles as $rc): ?>
          <th style="text-align:center;min-width:80px">
            <div style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:4px 0">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= h($rc['couleur']) ?>"></span>
              <span style="font-size:11px;font-weight:600"><?= h($rc['label']) ?></span>
            </div>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php $prevMod = ''; foreach (ALL_ACTIONS as $action => $info): ?>
        <?php if ($info['module'] !== $prevMod): $prevMod = $info['module']; ?>
        <tr><td colspan="<?= count($all_roles)+1 ?>" style="padding:7px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;background:var(--surface2)"><?= h($info['module']) ?></td></tr>
        <?php endif; ?>
        <tr>
          <td style="font-size:12px;color:var(--text2);padding:6px 16px"><?= h($info['label']) ?></td>
          <?php foreach ($all_roles as $rc):
            $allowed = $rc['role']==='admin' ? true : ($matrix['actions'][$rc['role']][$action] ?? false);
          ?>
          <td style="text-align:center">
            <?php if ($rc['role']==='admin'): ?>
            <span style="color:var(--yellow);font-size:13px">⭐</span>
            <?php elseif ($allowed): ?>
            <span style="color:var(--green);font-size:13px">✅</span>
            <?php else: ?>
            <span style="color:var(--border2);font-size:13px">✗</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!--  MODAL NOUVEAU ROLE  -->
<div id="modal-new-role" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:540px;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Créer un nouveau rôle</h3>
      <div onclick="document.getElementById('modal-new-role').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">X</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_role">
      <?= csrf_field() ?>

      <div class="form-group" style="margin-bottom:14px">
        <label>Libellé du rôle *</label>
        <input type="text" name="label" required maxlength="100" autofocus
               placeholder="ex: Neurochirurgien, Stagiaire, Radiologue, Chef de service...">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Un identifiant unique sera généré automatiquement depuis le libellé.</div>
      </div>

      <div class="form-group" style="margin-bottom:14px">
        <label>Couleur du badge</label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:4px">
          <?php foreach (['#3b82f6','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#ec4899','#f97316','#64748b','#a3e635'] as $c): ?>
          <label style="cursor:pointer">
            <input type="radio" name="couleur" value="<?= $c ?>" style="display:none" onclick="document.getElementById('color-custom').value='<?= $c ?>'">
            <div style="width:28px;height:28px;border-radius:7px;background:<?= $c ?>;cursor:pointer;border:2px solid transparent" onclick="this.style.borderColor='white'; document.querySelectorAll('.color-swatch').forEach(s=>s.style.borderColor='transparent'); this.style.borderColor='white'" class="color-swatch"></div>
          </label>
          <?php endforeach; ?>
          <input type="color" id="color-custom" name="couleur" value="#3b82f6"
                 style="width:36px;height:36px;border:1px solid var(--border2);border-radius:7px;cursor:pointer;padding:2px"
                 title="Couleur personnalisée">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:14px">
        <label>Description</label>
        <input type="text" name="description" maxlength="255"
               placeholder="ex: Accès consultation en neurochirurgie, lecture des dossiers...">
      </div>

      <div class="form-group" style="margin-bottom:20px">
        <label>Initialiser les permissions depuis</label>
        <select name="clone_from" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
          <option value="vide">Partir de zéro (tout interdit)</option>
          <?php foreach ($all_roles as $rc): if ($rc['role']==='admin') continue; ?>
          <option value="<?= h($rc['role']) ?>">Copier les permissions de : <?= h($rc['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Vous pourrez affiner les permissions après la création.</div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-new-role').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Créer le rôle</button>
      </div>
    </form>
  </div>
</div>

<!-- Formulaire de suppression global (hors des autres forms) -->
<form method="POST" id="form-delete-role" style="display:none">
  <input type="hidden" name="action" value="delete_role">
  <input type="hidden" name="role" id="delete-role-slug">
  <?= csrf_field() ?>
</form>

<script>
function deleteRole(slug, label) {
  if (!confirm('Supprimer le rôle "' + label + '" ?\nLes utilisateurs avec ce rôle ne pourront plus se connecter correctement.')) return;
  document.getElementById('delete-role-slug').value = slug;
  document.getElementById('form-delete-role').submit();
}

function openEditModal(role, label, couleur, desc) {
  document.getElementById('edit-' + role).style.display = 'block';
  document.getElementById('edit-' + role).scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function setAll(cls, val) {
  document.querySelectorAll(cls).forEach(cb => {
    cb.checked = val;
    const badge = cb.parentElement.querySelector('.badge');
    if (badge) {
      if (cls === '.chk-page') { badge.textContent = val ? 'Oui' : 'Non'; }
      else { badge.textContent = val ? 'OK' : 'Non'; }
      badge.className = 'badge ' + (val ? 'badge-green' : 'badge-red');
    }
  });
}

// Live badge update
document.querySelectorAll('input[type=checkbox]').forEach(cb => {
  cb.addEventListener('change', function() {
    const badge = this.parentElement.querySelector('.badge');
    if (!badge) return;
    const isPage = this.classList.contains('chk-page');
    badge.textContent = this.checked ? (isPage ? 'Oui' : 'OK') : 'Non';
    badge.className   = 'badge ' + (this.checked ? 'badge-green' : 'badge-red');
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
