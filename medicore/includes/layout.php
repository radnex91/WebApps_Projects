<?php
// ============================================================
//  MediCore ERP — Layout commun (header + sidebar)
//  Inclure en début de chaque page: require_once 'includes/layout.php';
// ============================================================
require_once __DIR__ . '/auth.php';
// Security already loaded by auth.php + security.php
requireLogin();
$user = currentUser();

// Menu navigation
// Navigation filtrée selon les droits du rôle courant
$nav = getAccessibleNav();

$currentPage = $currentPage ?? 'dashboard';
$pageTitle   = $nav[$currentPage]['label'] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="fr" data-mode="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — MediCore ERP</title>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<script>var APP_URL = '<?= h(APP_URL) ?>';</script>
<?php
//  Polices dynamiques depuis les paramètres
$_fontBody    = setting('font_body',    'DM Sans');
$_fontHeading = setting('font_heading', 'Playfair Display');
$_fontSize    = max(12, min(20, (int)setting('font_size', '14')));

// Construire l'URL Google Fonts selon les polices choisies
$_googleFonts = [
    'DM Sans'          => 'DM+Sans:wght@300;400;500;600;700',
    'Inter'            => 'Inter:wght@300;400;500;600;700',
    'Roboto'           => 'Roboto:wght@300;400;500;700',
    'Nunito'           => 'Nunito:wght@300;400;600;700',
    'Poppins'          => 'Poppins:wght@300;400;500;600;700',
    'Source Sans 3'    => 'Source+Sans+3:wght@300;400;600;700',
    'Playfair Display' => 'Playfair+Display:wght@600;700',
    'Merriweather'     => 'Merriweather:wght@400;700',
    'Lora'             => 'Lora:wght@400;600;700',
    'Crimson Text'     => 'Crimson+Text:wght@400;600',
    'Space Grotesk'    => 'Space+Grotesk:wght@300;400;500;600;700',
    'Syne'             => 'Syne:wght@400;600;700;800',
];
$_needed = array_unique([$_fontBody, $_fontHeading]);
$_gfParts = array_filter(array_map(fn($f) => $_googleFonts[$f] ?? null, $_needed));
$_gfUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $_gfParts) . '&display=swap';

//  Thème utilisateur : surcharge par préférence personnelle ou global
$_THEME_PRESETS = [
  'ocean'       => ['name'=>'Océan',        'primary'=>'1E3A5F','accent'=>'3b82f6','bg'=>'0a0e1a','surface'=>'111827'],
  'bleu_marine' => ['name'=>'Bleu Marine',  'primary'=>'0a1e3d','accent'=>'2d7dd2','bg'=>'060d1a','surface'=>'0d1b2a'],
  'foret'       => ['name'=>'Forêt',        'primary'=>'1a2e1a','accent'=>'10b981','bg'=>'0a120a','surface'=>'111f11'],
  'crepuscule'  => ['name'=>'Crépuscule',  'primary'=>'3d1a3a','accent'=>'ec4899','bg'=>'120a14','surface'=>'1f1127'],
  'nuit'        => ['name'=>'Nuit',         'primary'=>'1a1a2e','accent'=>'8b5cf6','bg'=>'0a0a18','surface'=>'111127'],
  'bordeaux'    => ['name'=>'Bordeaux',     'primary'=>'2e1a1a','accent'=>'ef4444','bg'=>'140a0a','surface'=>'1f1111'],
  'sarcelle'    => ['name'=>'Sarcelle',     'primary'=>'1a2e2e','accent'=>'06b6d4','bg'=>'0a1414','surface'=>'111f1f'],
  'ambre'       => ['name'=>'Ambre',        'primary'=>'2e2a1a','accent'=>'f59e0b','bg'=>'14120a','surface'=>'1f1d11'],
  'magnetique'  => ['name'=>'Magnétique',   'primary'=>'1a1a2e','accent'=>'f97316','bg'=>'0e0a18','surface'=>'16111f'],
];
$_themePreset  = user_pref('theme_preset', 'ocean');
if (!isset($_THEME_PRESETS[$_themePreset])) $_themePreset = 'ocean';
$_presetDef    = $_THEME_PRESETS[$_themePreset];
$_themePrimary = user_pref('theme_primary', $_presetDef['primary']);
$_themeAccent   = user_pref('theme_accent', $_presetDef['accent']);
$_themeBg       = user_pref('theme_bg', $_presetDef['bg']);
$_themeSurface  = user_pref('theme_surface', $_presetDef['surface']);

function _hexToRGB(string $hex): string {
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r,$g,$b";
}
function _darken(string $hex, int $amount = 30): string {
    $r = max(0, hexdec(substr($hex, 0, 2)) - $amount);
    $g = max(0, hexdec(substr($hex, 2, 2)) - $amount);
    $b = max(0, hexdec(substr($hex, 4, 2)) - $amount);
    return sprintf('%02x%02x%02x', $r, $g, $b);
}
function _lighten(string $hex, int $amount = 60): string {
    $r = min(255, hexdec(substr($hex, 0, 2)) + $amount);
    $g = min(255, hexdec(substr($hex, 2, 2)) + $amount);
    $b = min(255, hexdec(substr($hex, 4, 2)) + $amount);
    return sprintf('%02x%02x%02x', $r, $g, $b);
}

$_themeAccentLight = _lighten($_themeAccent, 60);
$_themeAccentDark  = _darken($_themeAccent, 30);
$_themeAccentRGB   = _hexToRGB($_themeAccent);
$_themePrimaryDark = _darken($_themePrimary, 25);
?>
<link href="<?= h($_gfUrl) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
<script>var THEME_PRESETS = <?= json_encode($_THEME_PRESETS) ?>;var THEME_CURRENT_PRESET = '<?= h($_themePreset) ?>';</script>
<style>
  :root {
    --font-body:    '<?= h($_fontBody) ?>', sans-serif;
    --font-heading: '<?= h($_fontHeading) ?>', serif;
    --font-size-base: <?= $_fontSize ?>px;
    --primary: #<?= h($_themePrimary) ?>;
    --accent: #<?= h($_themeAccent) ?>;
    --accent2: #<?= h($_themeAccentLight) ?>;
    --bg: #<?= h($_themeBg) ?>;
    --surface: #<?= h($_themeSurface) ?>;
  }
  html { font-size: <?= $_fontSize ?>px; }
  body { font-family: var(--font-body); }
  h1,h2,h3,.brand,.auth-title,.stat-value { font-family: var(--font-heading); }

  .sidebar { background: linear-gradient(180deg, #<?= h($_themePrimary) ?> 0%, #<?= h($_themePrimaryDark) ?> 100%); }
  .btn-blue, .btn-primary { background: #<?= h($_themeAccent) ?>; }
  .btn-blue:hover, .btn-primary:hover { background: #<?= h($_themeAccentDark) ?>; }
  .badge-blue { background: rgba(<?= h($_themeAccentRGB) ?>,0.12); color: #<?= h($_themeAccent) ?>; }
  .pill-tab.active { background: #<?= h($_themeAccent) ?>; border-color: #<?= h($_themeAccent) ?>; color: #fff; }
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
<div id="app">
  <!--  SIDEBAR  -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🏥</div>
      <div class="brand"><?= h(setting('app_name','MediCore ERP')) ?> <small>v<?= APP_VERSION ?></small></div>
    </div>
    <nav class="sidebar-nav">
      <?php
      $lastSection = '';
      foreach ($nav as $key => $item):
          if ($item['section'] !== ''):
              if ($lastSection !== $item['section']):
                  if ($lastSection !== '') echo '</div>';
                  echo '<div class="nav-section">';
                  echo '<div class="nav-section-label">' . htmlspecialchars($item['section']) . '</div>';
                  $lastSection = $item['section'];
              endif;
          endif;
          $active = ($key === $currentPage) ? ' active' : '';
          $badge  = !empty($item['badge']) ? '<span class="nav-badge">' . $item['badge'] . '</span>' : '';
          echo '<a href="' . APP_URL . '/' . $key . '.php" class="nav-item' . $active . '">';
          echo '<span class="icon">' . $item['icon'] . '</span> ';
          echo htmlspecialchars($item['label']);
          echo $badge;
          echo '</a>';
      endforeach;
      echo '</div>';
      ?>
    </nav>
    <div class="sidebar-footer">
      <div class="user-card" id="user-theme-trigger" onclick="document.getElementById('theme-popover').classList.toggle('open')" style="cursor:pointer">
        <div class="user-avatar"><?= htmlspecialchars($user['initiales']) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role" style="color:<?= role_couleur($user['role']) ?>"><?= h(role_label($user['role'])) ?></div>
        </div>
      </div>

      <!-- Theme picker popover -->
      <div class="theme-popover" id="theme-popover">
        <div class="theme-popover-title">Mon thème</div>
        <div class="theme-preset-grid">
          <?php foreach ($_THEME_PRESETS as $pid => $p): ?>
          <div class="theme-preset-card<?= $_themePreset===$pid?' active':'' ?>" data-preset="<?= $pid ?>" onclick="selectPreset('<?= $pid ?>')">
            <div class="theme-preset-preview">
              <div class="theme-preset-sidebar" style="background:linear-gradient(180deg,#<?= $p['primary'] ?>,#<?= _darken($p['primary'],25) ?>)"></div>
              <div class="theme-preset-body" style="background:#<?= $p['bg'] ?>">
                <div class="theme-preset-dot" style="background:#<?= $p['surface'] ?>"></div>
                <div class="theme-preset-dot" style="background:#<?= $p['accent'] ?>;width:20px"></div>
              </div>
            </div>
            <div class="theme-preset-name"><?= h($p['name']) ?></div>
            <?php if ($_themePreset === $pid): ?><div class="theme-preset-check">&#10003;</div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="theme-custom-toggle" onclick="toggleCustomSection()">
          <span>Personnaliser les couleurs</span>
          <span class="chevron down" id="custom-chevron">&#9660;</span>
        </div>
        <div id="theme-custom-section" class="theme-custom-section collapsed">
          <div class="theme-popover-section">
            <div class="theme-popover-label">Barre latérale</div>
            <div class="theme-color-row">
              <?php foreach (['1E3A5F'=>'Marine','1a2e1a'=>'Vert','3d1a3a'=>'Pourpre','1a1a2e'=>'Nuit','2e1a1a'=>'Bordeaux','1a2e2e'=>'Sarcelle','2e2a1a'=>'Ambre'] as $hex => $label): ?>
              <button type="button" class="theme-swatch<?= $_themePrimary===$hex?' selected':'' ?>" style="background:#<?= $hex ?>" title="<?= h($label) ?>" onclick="setThemeColor('primary','<?= $hex ?>')"></button>
              <?php endforeach; ?>
              <label class="theme-swatch-custom" title="Personnalisé">
                <input type="color" value="#<?= h($_themePrimary) ?>" onchange="setThemeColor('primary',this.value.replace('#',''))">
                <span style="background:#<?= h($_themePrimary) ?>"></span>
              </label>
            </div>
          </div>
          <div class="theme-popover-section">
            <div class="theme-popover-label">Boutons et liens</div>
            <div class="theme-color-row">
              <?php foreach (['3b82f6'=>'Bleu','10b981'=>'Émeraude','8b5cf6'=>'Violet','f59e0b'=>'Ambre','ef4444'=>'Rouge','06b6d4'=>'Cyan','ec4899'=>'Rose','f97316'=>'Orange'] as $hex => $label): ?>
              <button type="button" class="theme-swatch<?= $_themeAccent===$hex?' selected':'' ?>" style="background:#<?= $hex ?>" title="<?= h($label) ?>" onclick="setThemeColor('accent','<?= $hex ?>')"></button>
              <?php endforeach; ?>
              <label class="theme-swatch-custom" title="Personnalisé">
                <input type="color" value="#<?= h($_themeAccent) ?>" onchange="setThemeColor('accent',this.value.replace('#',''))">
                <span style="background:#<?= h($_themeAccent) ?>"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="theme-popover-actions">
          <button type="button" class="btn btn-sm btn-ghost" onclick="resetTheme()">Réinitialiser</button>
          <button type="button" class="btn btn-sm btn-blue" onclick="saveTheme()">Sauvegarder</button>
        </div>
      </div>

      <a href="<?= APP_URL ?>/login?action=logout"
         class="logout-full-btn"
         onclick="return confirm('Voulez-vous vous déconnecter ?')">
        <span></span> Déconnexion
      </a>
    </div>
  </aside>

  <!--  MAIN  -->
  <div class="main">
    <div class="header">
      <span class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"></span>
      <div class="header-title"><?= htmlspecialchars($pageTitle) ?></div>
      <div class="header-search">
        <span>🔍</span>
        <input type="text" placeholder="Rechercher patient, médecin..." id="global-search">
      </div>
      <div class="header-actions">
        <?php
        // Compteurs de notifications initiaux (rendu côté serveur pour affichage immédiat)
        $_crit    = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='critique'");
        $_stocks  = (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='critique'");
        $_ordos   = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'");
        $_analyses= (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='disponible'");
        $_ntotal  = $_crit + $_stocks + $_ordos + $_analyses;
        ?>
        <div class="icon-btn notif-btn" onclick="toggleNotifs(event)" title="Notifications">
          🔔
          <span class="notif-count" id="notif-count" data-count="<?= $_ntotal ?>"><?= $_ntotal > 0 ? ($_ntotal > 9 ? '9+' : $_ntotal) : '' ?></span>
          <?php if ($_ntotal > 0): ?><span class="notif-dot"></span><?php endif; ?>
          <div class="notification-panel" id="notif-panel">
            <div class="notif-header">Alertes en direct <span class="live-dot" id="live-dot"></span></div>
            <div id="notif-list">
              <?php if ($_crit > 0 && canAccessPage('urgences')): ?>
              <a href="<?= APP_URL ?>/urgences.php?filtre=critique" class="notif-item notif-link">
                <div class="notif-icon" style="background:rgba(239,68,68,.15)">🚨</div>
                <div><div class="notif-title" style="color:var(--red)"><?= $_crit ?> patient(s) critique(s)</div><div class="notif-time">Intervention immédiate</div></div>
              </a>
              <?php endif; ?>
              <?php if ($_stocks > 0 && canAccessPage('pharmacie')): ?>
              <a href="<?= APP_URL ?>/pharmacie.php?tab=stock&filtre_stock=critique" class="notif-item notif-link">
                <div class="notif-icon" style="background:rgba(239,68,68,.1)">💊</div>
                <div><div class="notif-title" style="color:var(--red)"><?= $_stocks ?> médicament(s) critique(s)</div><div class="notif-time">Stock presque épuisé</div></div>
              </a>
              <?php endif; ?>
              <?php if ($_ordos > 0 && canAccessPage('pharmacie')): ?>
              <a href="<?= APP_URL ?>/pharmacie.php?tab=ordonnances" class="notif-item notif-link">
                <div class="notif-icon" style="background:rgba(245,158,11,.12)">📋</div>
                <div><div class="notif-title" style="color:var(--yellow)"><?= $_ordos ?> ordonnance(s) en attente</div><div class="notif-time">À traiter</div></div>
              </a>
              <?php endif; ?>
              <?php if ($_analyses > 0 && canAccessPage('laboratoire')): ?>
              <a href="<?= APP_URL ?>/laboratoire.php?filtre=disponible" class="notif-item notif-link">
                <div class="notif-icon" style="background:rgba(16,185,129,.12)">🧪</div>
                <div><div class="notif-title" style="color:var(--green)"><?= $_analyses ?> résultat(s) disponible(s)</div><div class="notif-time">Analyses prêtes</div></div>
              </a>
              <?php endif; ?>
              <?php if ($_ntotal === 0): ?>
              <div class="notif-empty">Aucune alerte pour le moment</div>
              <?php endif; ?>
            </div>
            <div class="notif-separator"></div>
            <div class="notif-header">Activité récente</div>
            <div id="notif-activity">
              <?php
              try {
                  $logs = db_select("SELECT a.*, CONCAT(u.prenom,' ',u.nom) AS user_nom FROM activite_log a LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id ORDER BY a.date_action DESC LIMIT 5");
                  $icones = ['green'=>'✅','blue'=>'📋','yellow'=>'⚠️','red'=>'🚨'];
                  $colors = ['green'=>'rgba(16,185,129,.15)','blue'=>'rgba(59,130,246,.15)','yellow'=>'rgba(245,158,11,.15)','red'=>'rgba(239,68,68,.15)'];
                  foreach ($logs as $log):
                      $ic = $icones[$log['couleur']] ?? '📌';
                      $bg = $colors[$log['couleur']] ?? 'rgba(59,130,246,.15)';
              ?>
              <div class="notif-item">
                <div class="notif-icon" style="background:<?= $bg ?>"><?= $ic ?></div>
                <div><div class="notif-title"><?= htmlspecialchars(mb_substr($log['action'], 0, 55)) ?></div><div class="notif-time"><?= date('H:i', strtotime($log['date_action'])) ?> · <?= htmlspecialchars($log['user_nom'] ?? 'Système') ?></div></div>
              </div>
              <?php endforeach; } catch(Exception $e) {} ?>
            </div>
          </div>
        </div>
        <div class="user-avatar-sm"><?= htmlspecialchars($user['initiales']) ?></div>
      </div>
    </div>
    <div class="content">