<?php
// includes/layout.php
require_once __DIR__ . '/../config/settings.php';

// ── Icônes SVG flat (remplacent tous les emojis) ───────────
function icon(string $name, int $size = 18, string $extra = ''): string {
    $s = $size;
    $icons = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'cart'         => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'box'          => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'pill'         => '<path d="M10.5 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v7.5"/><path d="M16 16h6"/><path d="M19 13v6"/>',
        'truck'        => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'clipboard'    => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>',
        'chart'        => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>',
        'trending'     => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'tag'          => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 1 4.93 19.07 10 10 0 0 1 19.07 4.93"/>',
        'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit'         => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'        => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'eye'          => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'alert'        => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'check'        => '<polyline points="20 6 9 17 4 12"/>',
        'x'            => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'save'         => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'money'        => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'receipt'      => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/>',
        'history'      => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/>',
        'report'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'unlock'       => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'filter'       => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'refresh'      => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'building'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'book'         => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="14" y2="11"/>',
        'balance'      => '<path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/><path d="M2 20h20"/>',
    ];
    $path = $icons[$name] ?? $icons['settings'];
    return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '.$extra.'>'.$path.'</svg>';
}

function layout_head(string $title, string $activePage = ''): void {
    $user = currentUser();
    $initials = strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1));
    $roleLabel = $_SESSION['user_role_libelle'] ?? (['admin'=>'Administrateur','pharmacien'=>'Pharmacien','caissier'=>'Caissier'][$user['role']] ?? $user['role']);

    $p = getAllParams();
    $theme       = $p['theme']        ?? 'dark-navy';
    $police      = $p['police']       ?? 'DM Sans';
    $policeTitre = $p['police_titre'] ?? 'Cormorant Garamond';
    $appNom      = $p['app_nom']      ?? 'PharmaCare';
    $ticketSousTitre = $p['ticket_sous_titre'] ?? 'Gestion Pharmacie';
    $isLight     = str_starts_with($theme, 'light');

    // Thème colors — même base sombre pour tous les thèmes dark
    $themes = [
        'dark-navy'    => ['#06d6a0','#f59e0b','#ef4444','#818cf8','#080c15','#0d1220','#172033','#0f172a'],
        'dark-rose'    => ['#cc6f7f','#a1ae9d','#ef4444','#d4a574','#0f0c0a','#171310','#201a17','#191411'],
    ];
    [$c1,$c2,$c3,$c4,$bg,$bg2,$bg3,$card] = $themes[$theme] ?? $themes['dark-navy'];

    // Dim colors
    $dim = function(string $hex, float $a): string {
        $r=hexdec(substr($hex,1,2)); $g=hexdec(substr($hex,3,2)); $b=hexdec(substr($hex,5,2));
        return "rgba($r,$g,$b,$a)";
    };

    $textMain  = $isLight ? '#1e293b' : '#e8edf5';
    $text2     = $isLight ? '#475569' : '#8b97ab';
    $text3     = $isLight ? '#94a3b8' : '#5a6678';
    $border    = $isLight ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.06)';
    $border2   = $isLight ? 'rgba(0,0,0,.15)' : 'rgba(255,255,255,.1)';

    // Google Fonts URL
    $bodyFont  = urlencode($police);
    $titleFont = urlencode($policeTitre);
    $fontsUrl  = "https://fonts.googleapis.com/css2?family={$bodyFont}:wght@300;400;500;600&family={$titleFont}:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap";

    // Alertes stock
    try { $alertes = getDB()->query("SELECT COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1")->fetchColumn(); }
    catch (Exception $e) { $alertes = 0; }
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e($appNom) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="<?= $fontsUrl ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<style>
:root {
  --teal:    <?= $c1 ?>;
  --teal2:   <?= $c1 ?>;
  --gold:    <?= $c2 ?>;
  --red:     <?= $c3 ?>;
  --blue:    <?= $c4 ?>;
  --bg:      <?= $bg  ?>;
  --bg2:     <?= $bg2 ?>;
  --bg3:     <?= $bg3 ?>;
  --card:    <?= $card ?>;
  --text:    <?= $textMain ?>;
  --text2:   <?= $text2 ?>;
  --text3:   <?= $text3 ?>;
  --border:  <?= $border ?>;
  --border2: <?= $border2 ?>;
  --teal-dim:  <?= $dim($c1,.10) ?>;
  --gold-dim:  <?= $dim($c2,.10) ?>;
  --red-dim:   <?= $dim($c3,.10) ?>;
  --blue-dim:  <?= $dim($c4,.10) ?>;
  --purple-dim:rgba(155,89,182,.10);
  --teal-glow:  <?= $dim($c1,.25) ?>;
  --gold-glow:  <?= $dim($c2,.25) ?>;
  --red-glow:   <?= $dim($c3,.25) ?>;
  --blue-glow:  <?= $dim($c4,.25) ?>;
  --font-body:  '<?= e($police) ?>', sans-serif;
  --font-title: '<?= e($policeTitre) ?>', serif;
<?php if ($isLight): ?>
  --glass:     rgba(0,0,0,.04);
  --shadow:    0 8px 32px rgba(0,0,0,.10);
  --shadow-sm: 0 2px 12px rgba(0,0,0,.06);
  --btn-text:  #fff;
<?php else: ?>
  --glass:     rgba(255,255,255,.03);
  --shadow:    0 8px 32px rgba(0,0,0,.45);
  --shadow-sm: 0 2px 12px rgba(0,0,0,.35);
  --btn-text:  #000;
<?php endif; ?>
}
body, .nav-item, .btn, input, select, textarea, td, th { font-family: var(--font-body); }
.logo-mark, .card-title, .stat-value, .topbar-title, .modal-title, .section-title, .total-main { font-family: var(--font-title); }
</style>
</head>
<body>
<script>
function toggleStockPanel(){var p=document.getElementById('stock-panel');if(p)p.style.display=p.style.display==='none'?'block':'none';}
document.addEventListener('click',function(e){var btn=document.getElementById('stock-alert-btn');var panel=document.getElementById('stock-panel');if(panel&&panel.style.display!=='none'&&btn&&!btn.contains(e.target)&&!panel.contains(e.target)){panel.style.display='none';}});
</script>

<div id="sidebar">
  <div class="logo">
    <div class="logo-mark">
      <img src="<?= APP_URL ?>/assets/img/logo-icon.svg" alt="" style="width:44px;height:44px;flex-shrink:0;">
      <?= e($appNom) ?>
    </div>
    <div class="logo-sub"><?= e($ticketSousTitre) ?></div>
  </div>
  <nav>
    <?php if(hasPermission('dashboard.voir')): ?>
    <div class="nav-section">Principal</div>
    <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>">
      <span class="nav-icon i-teal"><?= icon('dashboard',14) ?></span> Tableau de bord
    </a>
    <?php endif; ?>
    <?php if(hasPermission('vente.creer')): ?>
    <a href="<?= APP_URL ?>/modules/vente.php" class="nav-item <?= $activePage==='vente'?'active':'' ?>">
      <span class="nav-icon i-green"><?= icon('cart',14) ?></span> Point de Vente
    </a>
    <?php endif; ?>
    <?php if(hasPermission('caisse.voir') || hasPermission('caisse.ouvrir')): ?>
    <a href="<?= APP_URL ?>/modules/caisse.php" class="nav-item <?= $activePage==='caisse'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('money',14) ?></span> Caisses
    </a>
    <?php endif; ?>
    <?php if(hasPermission('stock.voir') || hasPermission('produits.voir') || hasPermission('fournisseurs.voir') || hasPermission('commandes.voir')): ?>
    <div class="nav-section">Gestion</div>
    <?php if(hasPermission('stock.voir')): ?>
    <a href="<?= APP_URL ?>/modules/stock.php" class="nav-item <?= $activePage==='stock'?'active':'' ?>">
      <span class="nav-icon i-orange"><?= icon('box',14) ?></span> Stock
      <?php if($alertes > 0): ?>
        <span class="nav-badge"><?= $alertes ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
    <?php if(hasPermission('produits.voir')): ?>
    <a href="<?= APP_URL ?>/modules/produits.php" class="nav-item <?= $activePage==='produits'?'active':'' ?>">
      <span class="nav-icon i-cyan"><?= icon('pill',14) ?></span> Médicaments
    </a>
    <?php endif; ?>
    <?php if(hasPermission('fournisseurs.voir')): ?>
    <a href="<?= APP_URL ?>/modules/fournisseurs.php" class="nav-item <?= $activePage==='fournisseurs'?'active':'' ?>">
      <span class="nav-icon i-blue"><?= icon('building',14) ?></span> Fournisseurs
    </a>
    <?php endif; ?>
    <?php if(hasPermission('clients.voir')): ?>
    <a href="<?= APP_URL ?>/modules/clients.php" class="nav-item <?= $activePage==='clients'?'active':'' ?>">
      <span class="nav-icon i-cyan"><?= icon('users',14) ?></span> Clients
    </a>
    <?php endif; ?>
    <?php if(hasPermission('commandes.voir')): ?>
    <a href="<?= APP_URL ?>/modules/commandes.php" class="nav-item <?= $activePage==='commandes'?'active':'' ?>">
      <span class="nav-icon i-purple"><?= icon('clipboard',14) ?></span> Commandes
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if(hasPermission('ventes_hist.voir') || hasPermission('rapports.voir')): ?>
    <div class="nav-section">Rapports</div>
    <?php if(hasPermission('ventes_hist.voir')): ?>
    <a href="<?= APP_URL ?>/modules/ventes_hist.php" class="nav-item <?= $activePage==='historique'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('history',14) ?></span> Historique ventes
    </a>
    <?php endif; ?>
    <?php if(hasPermission('rapports.voir')): ?>
    <a href="<?= APP_URL ?>/modules/rapports.php" class="nav-item <?= $activePage==='rapports'?'active':'' ?>">
      <span class="nav-icon i-pink"><?= icon('chart',14) ?></span> Rapports
    </a>
    <?php endif; ?>
    <?php if(hasPermission('comptabilite.voir')): ?>
    <a href="<?= APP_URL ?>/modules/comptabilite.php" class="nav-item <?= $activePage==='comptabilite'?'active':'' ?>">
      <span class="nav-icon i-teal"><?= icon('book',14) ?></span> Comptabilité
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <?php
    $showAdmin = hasPermission('utilisateurs.voir') || hasPermission('categories.voir')
              || hasPermission('parametres.voir') || hasPermission('roles.voir');
    ?>
    <?php if ($showAdmin): ?>
    <div class="nav-section">Administration</div>
    <?php if(hasPermission('utilisateurs.voir')): ?>
    <a href="<?= APP_URL ?>/modules/utilisateurs.php" class="nav-item <?= $activePage==='utilisateurs'?'active':'' ?>">
      <span class="nav-icon i-blue"><?= icon('users',14) ?></span> Utilisateurs
    </a>
    <?php endif; ?>
    <?php if(hasPermission('roles.voir')): ?>
    <a href="<?= APP_URL ?>/modules/roles.php" class="nav-item <?= $activePage==='roles'?'active':'' ?>">
      <span class="nav-icon i-purple"><?= icon('lock',14) ?></span> Rôles & Permissions
    </a>
    <?php endif; ?>
    <?php if(hasPermission('categories.voir')): ?>
    <a href="<?= APP_URL ?>/modules/categories.php" class="nav-item <?= $activePage==='categories'?'active':'' ?>">
      <span class="nav-icon i-orange"><?= icon('tag',14) ?></span> Catégories
    </a>
    <?php endif; ?>
    <?php if(hasPermission('parametres.voir')): ?>
    <a href="<?= APP_URL ?>/modules/parametres.php" class="nav-item <?= $activePage==='parametres'?'active':'' ?>">
      <span class="nav-icon i-slate"><?= icon('settings',14) ?></span> Paramètres
    </a>
    <?php endif; ?>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="avatar" style="background:linear-gradient(135deg,var(--teal),var(--blue));"><?= $initials ?></div>
      <div class="user-info">
        <div class="name"><?= e($user['prenom'].' '.$user['nom']) ?></div>
        <div class="role"><?= $roleLabel ?></div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn btn-ghost" style="width:100%;justify-content:center;margin-top:8px;font-size:12px;gap:6px;">
      <?= icon('logout',14) ?> Déconnexion
    </a>
  </div>
</div>

<div id="main">
  <div id="topbar">
    <div class="topbar-title" id="page-title"><?= e($title) ?></div>
    <div class="topbar-actions">
    <?php if ($alertes > 0): ?>
    <div class="stock-alert-wrapper" style="position:relative;">
      <button onclick="toggleStockPanel()" id="stock-alert-btn" style="background:var(--red-dim);border:1px solid var(--red-glow);color:var(--red);padding:4px 10px;border-radius:var(--radius-sm);cursor:pointer;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;font-family:inherit;transition:all .15s;">
        <span style="width:7px;height:7px;background:var(--red);border-radius:50%;animation:pulse-dot 1.5s infinite;"></span>
        <?= icon('alert',14) ?> <?= $alertes ?> alerte<?= $alertes > 1 ? 's' : '' ?>
      </button>
      <div id="stock-panel" style="display:none;position:absolute;top:100%;right:0;width:380px;max-height:420px;overflow-y:auto;background:var(--card);border:1px solid var(--border2);border-radius:var(--radius);box-shadow:var(--shadow);z-index:300;margin-top:8px;">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
          <div style="font-family:var(--font-title);font-size:15px;font-weight:600;color:var(--red);"><?= icon('alert',16) ?> Alertes stock</div>
          <button onclick="toggleStockPanel()" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;line-height:1;">×</button>
        </div>
        <?php
        $alertProds = getDB()->query("
            SELECT p.id, p.nom, p.stock, p.seuil_alerte, p.reference,
                   CASE WHEN p.stock = 0 THEN 'rupture' ELSE 'bas' END AS niveau
            FROM produits p
            WHERE p.actif = 1 AND p.stock <= p.seuil_alerte
            ORDER BY p.stock ASC, p.nom ASC LIMIT 15
        ")->fetchAll();
        foreach ($alertProds as $ap):
            $isRupture = $ap['stock'] == 0;
        ?>
        <a href="<?= APP_URL ?>/modules/produits.php?action=edit&id=<?= $ap['id'] ?>" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);transition:background .15s;" onmouseover="this.style.background='var(--glass)'" onmouseout="this.style.background='transparent'">
          <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= $isRupture ? 'var(--red)' : 'var(--gold)' ?>;box-shadow:0 0 6px <?= $isRupture ? 'var(--red-glow)' : 'var(--gold-glow)' ?>;"></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($ap['nom']) ?></div>
            <div style="font-size:11px;color:var(--text3);"><?= e($ap['reference'] ?? '—') ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:14px;font-weight:700;color:<?= $isRupture ? 'var(--red)' : 'var(--gold)' ?>;"><?= $ap['stock'] ?></div>
            <div style="font-size:10px;color:var(--text3);">seuil <?= $ap['seuil_alerte'] ?></div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if ($alertes > 15): ?>
        <div style="padding:10px 16px;text-align:center;font-size:12px;color:var(--text3);">+ <?= $alertes - 15 ?> autre<?= ($alertes - 15) > 1 ? 's' : '' ?></div>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/stock.php?filtre=alerte" style="display:block;padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--red);border-top:1px solid var(--border);text-decoration:none;">Voir toutes les alertes →</a>
      </div>
    </div>
    <?php endif; ?>
    <span class="badge badge-gray" style="font-size:11px;" id="live-clock"><?= date('d/m/Y H:i') ?></span>
  </div>
  </div>
  <div id="content">
<?php
}

function layout_foot(): void {
?>
  </div><!-- /content -->
</div><!-- /main -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function(){
  var el=document.getElementById('live-clock');
  if(!el)return;
  function pad(n){return n<10?'0'+n:n;}
  function tick(){
    var d=new Date();
    el.textContent=pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear()+' '+pad(d.getHours())+':'+pad(d.getMinutes());
  }
  tick(); setInterval(tick,30000);
})();
</script>
</body>
</html>
<?php
}
// flash() et showFlash() sont dans includes/auth.php
