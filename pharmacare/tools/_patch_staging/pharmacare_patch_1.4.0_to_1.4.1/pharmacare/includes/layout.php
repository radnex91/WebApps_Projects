<?php
// includes/layout.php
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/assistant.php'; // assistant déterministe (moteur + widget)

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
        'trophy'       => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C15.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
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
        'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
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
        'megaphone'    => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/><circle cx="22" cy="12" r="2"/><path d="M22 5a7 7 0 0 1 0 14"/>',
        'upload'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        // ── Icônes médicales / pharmaceutiques (sidebar) ──
        'pulse'        => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'bag'          => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'cash-register' => '<rect x="3" y="8" width="18" height="13" rx="1"/><rect x="7" y="3" width="10" height="5" rx="1"/><line x1="3" y1="13" x2="21" y2="13"/><line x1="7" y1="16" x2="7" y2="18"/><line x1="11" y1="16" x2="11" y2="18"/><line x1="15" y1="16" x2="15" y2="18"/><line x1="18" y1="16" x2="18" y2="18"/>',
        'boxes'        => '<rect x="3" y="3" width="18" height="18" rx="1"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="12" y1="9" x2="12" y2="15"/>',
        'capsule'      => '<rect x="2" y="9" width="20" height="6" rx="3"/><line x1="12" y1="9" x2="12" y2="15"/>',
        'warehouse'    => '<path d="M3 21V8l9-5 9 5v13"/><path d="M3 21h18"/><rect x="9" y="13" width="6" height="8"/><line x1="9" y1="17" x2="15" y2="17"/>',
        'calculator'   => '<rect x="5" y="2" width="14" height="20" rx="2"/><rect x="8" y="5" width="8" height="3" rx="1"/><line x1="8" y1="12" x2="8" y2="12"/><line x1="12" y1="12" x2="12" y2="12"/><line x1="16" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="12" y1="16" x2="12" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/><line x1="8" y1="20" x2="8" y2="20"/><line x1="12" y1="20" x2="12" y2="20"/><line x1="16" y1="20" x2="16" y2="20"/>',
        'clock'        => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'stethoscope'  => '<path d="M4 3v5a4 4 0 0 0 8 0V3"/><path d="M6 3h4"/><path d="M8 12v3a5 5 0 0 0 10 0v-1"/><circle cx="18" cy="13" r="2"/><circle cx="21" cy="16" r="2"/>',
        'percent'      => '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'key'          => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3"/>',
        'list'         => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'print'        => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    ];
    $path = $icons[$name] ?? $icons['settings'];
    return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '.$extra.'>'.$path.'</svg>';
}

function layout_head(string $title, string $activePage = ''): void {
    // Pages authentifiées : ne jamais être mises en cache par le navigateur
    // (évite qu'une page privée soit servie depuis le cache après déconnexion).
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }
    $GLOBALS['__pc_active_page'] = $activePage; // transmis à layout_foot() pour window.PHARMCARE_PAGE
    $user = currentUser();
    $initials = strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1));
    $roleLabel = $_SESSION['user_role_libelle'] ?? (['admin'=>'Administrateur','pharmacien'=>'Pharmacien','caissier'=>'Caissier'][$user['role']] ?? $user['role']);

    $p = getAllParams();
    $theme       = $p['theme']        ?? 'dark-navy';
    $police      = $p['police']       ?? 'Manrope';
    $policeTitre = $p['police_titre'] ?? 'Manrope';
    $appNom      = $p['app_nom']      ?? 'PharmaCare';
    $ticketSousTitre = $p['ticket_sous_titre'] ?? 'Gestion Pharmacie';
    $isLight     = str_starts_with($theme, 'light');

    // Thème colors — même base sombre pour tous les thèmes dark
    $themes = [
        'dark-navy'    => ['#06d6a0','#f59e0b','#ef4444','#818cf8','#080c15','#0d1220','#172033','#0f172a'],
        'dark-rose'    => ['#cc6f7f','#a1ae9d','#ef4444','#d4a574','#0f0c0a','#171310','#201a17','#191411'],
        'light-clair'  => ['#0d9488','#a16207','#dc2626','#4a7dc4','#f4f5f2','#eceee9','#e3e8e0','#fcfcfa'],
        'light-brainy' => ['#0ea87e','#e8a800','#d63547','#1a4f8a','#f0f2f5','#e6e9ee','#dde2ea','#ffffff'],
    ];
    [$c1,$c2,$c3,$c4,$bg,$bg2,$bg3,$card] = $themes[$theme] ?? $themes['dark-navy'];

    // Dim colors
    $dim = function(string $hex, float $a): string {
        $r=hexdec(substr($hex,1,2)); $g=hexdec(substr($hex,3,2)); $b=hexdec(substr($hex,5,2));
        return "rgba($r,$g,$b,$a)";
    };

    $textMain  = $isLight ? '#2a3640' : '#e8edf5';
    $text2     = $isLight ? '#4e5c55' : '#8b97ab';
    $text3     = $isLight ? '#8b968f' : '#5a6678';
    $border    = $isLight ? 'rgba(30,50,40,.09)' : 'rgba(255,255,255,.06)';
    $border2   = $isLight ? 'rgba(30,50,40,.14)' : 'rgba(255,255,255,.1)';

    // Polices self-hostées (Manrope + DM Mono) — pas de CDN en prod
    $bodyFont  = urlencode($police);
    $titleFont = urlencode($policeTitre);

    // Alertes stock
    try { $alertes = getDB()->query("SELECT COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1")->fetchColumn(); }
    catch (Exception $e) { $alertes = 0; }
    // Alertes stock magasin (dépôt central)
    try { $alertesMagasin = getDB()->query("SELECT COUNT(*) FROM produits WHERE stock_magasin <= seuil_magasin AND actif=1")->fetchColumn(); }
    catch (Exception $e) { $alertesMagasin = 0; }
    // L'utilisateur courant est-il approbateur de remise (accès génération de codes) ?
    try {
        $_stAppr = getDB()->prepare("SELECT 1 FROM remise_approbateurs WHERE utilisateur_id=? AND actif=1");
        $_stAppr->execute([currentUser()['id']]);
        $estApprobateur = (bool)$_stAppr->fetchColumn();
    } catch (Exception $e) { $estApprobateur = false; }
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e($appNom) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/fonts/fonts.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= APP_VERSION ?>">
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
  --font-title: '<?= e($policeTitre) ?>', sans-serif;
<?php if ($isLight): ?>
  --glass:     rgba(30,50,40,.04);
  --shadow:    0 10px 34px rgba(40,60,50,.08);
  --shadow-sm: 0 2px 12px rgba(40,60,50,.06);
  --btn-text:  #fff;
  --card2:     <?= $theme === 'light-brainy' ? '#f7f8fa' : $bg3 ?>;
  --toast-bg:     rgba(252,252,250,.97);
  --toast-text:   #263238;
  --toast-border: rgba(40,60,50,.10);
<?php else: ?>
  --glass:     rgba(255,255,255,.03);
  --shadow:    0 8px 32px rgba(0,0,0,.45);
  --shadow-sm: 0 2px 12px rgba(0,0,0,.35);
  --btn-text:  #000;
  --toast-bg:     rgba(20,20,30,.90);
  --toast-text:   #e8edf5;
  --toast-border: rgba(255,255,255,.10);
<?php endif; ?>
}
<?php if ($theme === 'light-brainy'): ?>
/* Thème « Brainy ERP » — identité du thème d'origine : sidebar bleu marine,
   item actif vert, textes bleu-nuit, bordures nettes gris-bleu */
:root {
  --text:#1a2236; --text2:#4b5671; --text3:#8892a4;
  --border:#dde2ea; --border2:#ccd3de;
  --glass:rgba(26,79,138,.05);
  --shadow:0 1px 4px rgba(16,24,40,.06),0 2px 8px rgba(16,24,40,.05);
  --shadow-sm:0 4px 16px rgba(16,24,40,.10);
}
#sidebar{background:#0f3060;border-right-color:rgba(255,255,255,.07);}
.logo{border-bottom-color:rgba(255,255,255,.07);}
.logo-mark{color:#fff;}
.logo-sub{color:#8fa3c0;}
nav::-webkit-scrollbar-thumb{background:#2c4a78;}
.nav-section{color:#7d92b4;}
.nav-item{color:#b0bdd4;}
.nav-item:hover{background:rgba(255,255,255,.06);color:#e6edf6;}
.nav-item.active{background:rgba(18,201,143,.16);color:#12c98f;border-left-color:#12c98f;}
.nav-item.active::before{background:#12c98f;box-shadow:0 0 8px rgba(18,201,143,.45);}
.user-card{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08);}
.user-info .name{color:#e8eef7;}
.user-info .role{color:#7d92b4;}
.sidebar-footer{border-top-color:rgba(255,255,255,.07);background:#0a2545;}
.sidebar-footer .btn-ghost{color:#b0bdd4;border-color:rgba(255,255,255,.12);}
.sidebar-footer .btn-ghost:hover{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.2);}
.topbar-title{background:linear-gradient(135deg,#1a2236,#1a4f8a);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
<?php endif; ?>
body, .nav-item, .btn, input, select, textarea, td, th { font-family: var(--font-body); }
.logo-mark, .card-title, .stat-value, .topbar-title, .modal-title, .section-title, .total-main { font-family: var(--font-title); }
</style>
</head>
<body>
<script>
function toggleStockPanel(){var p=document.getElementById('stock-panel');if(p)p.style.display=p.style.display==='none'?'block':'none';}
function toggleMagasinPanel(){var p=document.getElementById('magasin-panel');if(p)p.style.display=p.style.display==='none'?'block':'none';}
document.addEventListener('click',function(e){
  var sb=document.getElementById('stock-alert-btn'),sp=document.getElementById('stock-panel');
  if(sp&&sp.style.display!=='none'&&sb&&!sb.contains(e.target)&&!sp.contains(e.target)){sp.style.display='none';}
  var mb=document.getElementById('magasin-alert-btn'),mp=document.getElementById('magasin-panel');
  if(mp&&mp.style.display!=='none'&&mb&&!mb.contains(e.target)&&!mp.contains(e.target)){mp.style.display='none';}
});
</script>

<div id="sidebar">
  <div class="logo">
    <div class="logo-mark">
      <img src="<?= APP_URL ?>/assets/img/logo-icon.svg" alt="" style="width:44px;height:44px;flex-shrink:0;">
      <?= e(APP_NAME) ?>
    </div>
    <?php if ($appNom && $appNom !== APP_NAME): ?>
    <div class="logo-sub"><?= e($appNom) ?></div>
    <?php endif; ?>
  </div>
  <nav>
    <?php
    // ── Visibilité des entrées du sidebar ──────────────────────
    // Chaque menu combine la permission (rôle) ET l'activation par l'admin
    // (table `menus`). L'admin peut ainsi désactiver des menus pour tout le monde.
    $mDashboard    = hasPermission('dashboard.voir')    && menuActif('dashboard');
    $mVente        = hasPermission('vente.creer')       && menuActif('vente');
    $mRemiseCodes  = !empty($estApprobateur)            && menuActif('remise_codes');
    $mCaisse       = (hasPermission('caisse.voir') || hasPermission('caisse.ouvrir')) && menuActif('caisse');
    $mStock        = hasPermission('stock.voir')        && menuActif('stock');
    $mProduits     = hasPermission('produits.voir')     && menuActif('produits');
    $mFournisseurs = hasPermission('fournisseurs.voir') && menuActif('fournisseurs');
    $mClients      = hasPermission('clients.voir') && fideliteActive() && menuActif('clients');
    $mCommandes    = hasPermission('commandes.voir')    && menuActif('commandes');
    $mRetours      = hasPermission('retours.gerer')     && menuActif('retours');
    $mMagasin      = hasPermission('magasin.voir')      && menuActif('magasin');
    $mMarketing    = hasPermission('marketing.voir')    && menuActif('marketing');
    $mVentesHist   = hasPermission('ventes_hist.voir')  && menuActif('ventes_hist');
    $mRapports     = hasPermission('rapports.voir')     && menuActif('rapports');
    $mRapportsCaissier = hasPermission('rapports_caissier.voir') && menuActif('rapports_caissier');
    $mSuiviCaissiers = hasPermission('suivi_caissiers.voir') && menuActif('suivi_caissiers');
    $mCompta       = hasPermission('comptabilite.voir') && menuActif('comptabilite');
    $mUtilisateurs = hasPermission('utilisateurs.voir') && menuActif('utilisateurs');
    $mEnLigne      = hasPermission('enligne.voir') && menuActif('en_ligne');
    $mRemiseAppr   = hasPermission('remise.approbateurs.gerer') && menuActif('remise_approbateurs');
    $mRoles        = hasPermission('roles.voir')        && menuActif('roles');
    $mCategories   = hasPermission('categories.voir')  && menuActif('categories');
    $mPharmacies   = hasPermission('pharmacies.voir')  && menuActif('pharmacies');
    $mParametres   = hasPermission('parametres.voir')  && menuActif('parametres');
    $mLicence      = hasPermission('parametres.gerer') && menuActif('licence');
    $mSauvegarde   = hasPermission('parametres.gerer') && menuActif('sauvegarde');
    // Le module Menus n'est PAS toggleable : l'admin garde toujours l'accès.
    $mMenus        = hasPermission('menus.voir');

    $showPrincipal = $mDashboard || $mVente || $mRemiseCodes || $mCaisse;
    $showGestion   = $mStock || $mProduits || $mFournisseurs || $mClients || $mCommandes || $mRetours || $mMagasin || $mMarketing;
    $showRapports  = $mVentesHist || $mRapports || $mRapportsCaissier || $mSuiviCaissiers || $mCompta;
    $showAdmin     = $mUtilisateurs || $mEnLigne || $mRemiseAppr || $mRoles || $mCategories || $mPharmacies || $mParametres || $mMenus || $mLicence || $mSauvegarde;
    ?>
    <?php if ($showPrincipal): ?>
    <div class="nav-section">Principal</div>
    <?php if($mDashboard): ?>
    <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>">
      <span class="nav-icon i-teal"><?= icon('pulse',14) ?></span> Tableau de bord
    </a>
    <?php endif; ?>
    <a href="<?= url('mon_compte') ?>" class="nav-item <?= $activePage==='mon_compte'?'active':'' ?>">
      <span class="nav-icon i-blue"><?= icon('user',14) ?></span> Mon compte
    </a>
    <?php if($mVente): ?>
    <a href="<?= url('vente') ?>" class="nav-item <?= $activePage==='vente'?'active':'' ?>">
      <span class="nav-icon i-green"><?= icon('bag',14) ?></span> Point de Vente
    </a>
    <?php endif; ?>
    <?php if($mRemiseCodes): ?>
    <a href="<?= url('remise_codes') ?>" class="nav-item <?= $activePage==='remise_codes'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('percent',14) ?></span> Codes de remise
    </a>
    <?php endif; ?>
    <?php if($mCaisse): ?>
    <a href="<?= url('caisse') ?>" class="nav-item <?= $activePage==='caisse'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('cash-register',14) ?></span> Caisses
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($showGestion): ?>
    <div class="nav-section">Gestion</div>
    <?php if($mStock): ?>
    <a href="<?= url('stock') ?>" class="nav-item <?= $activePage==='stock'?'active':'' ?>">
      <span class="nav-icon i-orange"><?= icon('boxes',14) ?></span> Stock
      <?php if($alertes > 0): ?>
        <span class="nav-badge"><?= $alertes ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
    <?php if($mProduits): ?>
    <a href="<?= url('produits') ?>" class="nav-item <?= $activePage==='produits'?'active':'' ?>">
      <span class="nav-icon i-cyan"><?= icon('capsule',14) ?></span> Médicaments
    </a>
    <?php endif; ?>
    <?php if($mFournisseurs): ?>
    <a href="<?= url('fournisseurs') ?>" class="nav-item <?= $activePage==='fournisseurs'?'active':'' ?>">
      <span class="nav-icon i-blue"><?= icon('truck',14) ?></span> Fournisseurs
    </a>
    <?php endif; ?>
    <?php if($mClients): ?>
    <a href="<?= url('clients') ?>" class="nav-item <?= $activePage==='clients'?'active':'' ?>">
      <span class="nav-icon i-cyan"><?= icon('users',14) ?></span> Clients
    </a>
    <?php endif; ?>
    <?php if($mCommandes): ?>
    <a href="<?= url('commandes') ?>" class="nav-item <?= $activePage==='commandes'?'active':'' ?>">
      <span class="nav-icon i-purple"><?= icon('clipboard',14) ?></span> Commandes
    </a>
    <?php endif; ?>
    <?php if($mRetours): ?>
    <a href="<?= url('retours') ?>" class="nav-item <?= $activePage==='retours'?'active':'' ?>">
      <span class="nav-icon i-red"><?= icon('refresh',14) ?></span> Retours caisse
    </a>
    <?php endif; ?>
    <?php if($mMagasin): ?>
    <a href="<?= url('magasin') ?>" class="nav-item <?= $activePage==='magasin'?'active':'' ?>">
      <span class="nav-icon i-orange"><?= icon('warehouse',14) ?></span> Magasin
      <?php if($alertesMagasin > 0): ?>
        <span class="nav-badge nav-badge-gold"><?= $alertesMagasin ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
    <?php if($mMarketing): ?>
    <a href="<?= url('marketing') ?>" class="nav-item <?= $activePage==='marketing'?'active':'' ?>">
      <span class="nav-icon i-pink"><?= icon('megaphone',14) ?></span> Marketing
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($showRapports): ?>
    <div class="nav-section">Rapports</div>
    <?php if($mVentesHist): ?>
    <a href="<?= url('ventes_hist') ?>" class="nav-item <?= $activePage==='historique'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('history',14) ?></span> Historique ventes
    </a>
    <?php endif; ?>
    <?php if($mRapports): ?>
    <a href="<?= url('rapports') ?>" class="nav-item <?= $activePage==='rapports'?'active':'' ?>">
      <span class="nav-icon i-pink"><?= icon('chart',14) ?></span> Rapports
    </a>
    <?php endif; ?>
    <?php if($mRapportsCaissier): ?>
    <a href="<?= url('rapports_caissier') ?>" class="nav-item <?= $activePage==='rapports_caissier'?'active':'' ?>">
      <span class="nav-icon i-cyan"><?= icon('trending',14) ?></span> Mes Rapports
    </a>
    <?php endif; ?>
    <?php if($mSuiviCaissiers): ?>
    <a href="<?= url('suivi_caissiers') ?>" class="nav-item <?= $activePage==='suivi_caissiers'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('trophy',14) ?></span> Suivi des caissiers
    </a>
    <?php endif; ?>
    <?php if($mCompta): ?>
    <a href="<?= url('comptabilite') ?>" class="nav-item <?= $activePage==='comptabilite'?'active':'' ?>">
      <span class="nav-icon i-teal"><?= icon('calculator',14) ?></span> Comptabilité
    </a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($showAdmin): ?>
    <div class="nav-section">Administration</div>
    <?php if($mUtilisateurs): ?>
    <a href="<?= url('utilisateurs') ?>" class="nav-item <?= $activePage==='utilisateurs'?'active':'' ?>">
      <span class="nav-icon i-blue"><?= icon('users',14) ?></span> Utilisateurs
    </a>
    <?php endif; ?>
    <?php if($mEnLigne): ?>
    <a href="<?= url('utilisateurs_enligne') ?>" class="nav-item <?= $activePage==='utilisateurs_enligne'?'active':'' ?>">
      <span class="nav-icon i-green"><?= icon('users',14) ?></span> Utilisateurs en ligne
    </a>
    <?php endif; ?>
    <?php if($mRemiseAppr): ?>
    <a href="<?= url('remise_approbateurs') ?>" class="nav-item <?= $activePage==='remise_approbateurs'?'active':'' ?>">
      <span class="nav-icon i-cyan"><?= icon('key',14) ?></span> Approbateurs de remise
    </a>
    <?php endif; ?>
    <?php if($mRoles): ?>
    <a href="<?= url('roles') ?>" class="nav-item <?= $activePage==='roles'?'active':'' ?>">
      <span class="nav-icon i-purple"><?= icon('shield',14) ?></span> Rôles & Permissions
    </a>
    <?php endif; ?>
    <?php if($mCategories): ?>
    <a href="<?= url('categories') ?>" class="nav-item <?= $activePage==='categories'?'active':'' ?>">
      <span class="nav-icon i-orange"><?= icon('tag',14) ?></span> Catégories
    </a>
    <?php endif; ?>
    <?php if($mPharmacies): ?>
    <a href="<?= url('pharmacies') ?>" class="nav-item <?= $activePage==='pharmacies'?'active':'' ?>">
      <span class="nav-icon i-teal"><?= icon('building',14) ?></span> Pharmacies
    </a>
    <?php endif; ?>
    <?php if($mMenus): ?>
    <a href="<?= url('menus') ?>" class="nav-item <?= $activePage==='menus'?'active':'' ?>">
      <span class="nav-icon i-purple"><?= icon('list',14) ?></span> Menus
    </a>
    <?php endif; ?>
    <?php if($mParametres): ?>
    <a href="<?= url('parametres') ?>" class="nav-item <?= $activePage==='parametres'?'active':'' ?>">
      <span class="nav-icon i-slate"><?= icon('settings',14) ?></span> Paramètres
    </a>
    <?php endif; ?>
    <?php if($mLicence): ?>
    <a href="<?= url('licence') ?>" class="nav-item <?= $activePage==='licence'?'active':'' ?>">
      <span class="nav-icon i-gold"><?= icon('shield',14) ?></span> Licence
    </a>
    <?php endif; ?>
    <?php if($mSauvegarde): ?>
    <a href="<?= url('sauvegarde') ?>" class="nav-item <?= $activePage==='sauvegarde'?'active':'' ?>">
      <span class="nav-icon i-slate"><?= icon('save',14) ?></span> Sauvegarde
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
    <style>@media print{#pc-server-pill,#pc-app-version{display:none!important}}</style>
    <div id="pc-server-pill" style="display:flex;align-items:center;gap:7px;justify-content:center;flex-wrap:wrap;margin-top:10px;padding:7px 10px;border-radius:var(--radius-sm);background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);font-size:11px;color:var(--text2);">
      <span id="pc-server-dot" style="width:8px;height:8px;border-radius:50%;background:#64748b;flex:none;"></span>
      <span id="pc-server-label">Serveur…</span>
      <span id="pc-server-badge" style="display:none;background:#f59e0b;color:#221a00;border-radius:999px;padding:0 7px;font-weight:700;font-size:10px;line-height:16px;"></span>
    </div>
    <div id="pc-app-version" style="text-align:center;font-size:10px;color:var(--text3);margin-top:6px;font-family:'DM Mono',monospace;">v<?= APP_VERSION ?></div>
    <div style="text-align:center;font-size:10px;color:var(--text3);margin-top:10px;">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Tous droits réservés</div>
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
        <a href="<?= url('produits', ['action'=>'edit','id'=>$ap['id']], $ap['nom'] ?? null) ?>" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);transition:background .15s;" onmouseover="this.style.background='var(--glass)'" onmouseout="this.style.background='transparent'">
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
        <a href="<?= url('stock', ['filtre'=>'alerte']) ?>" style="display:block;padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--red);border-top:1px solid var(--border);text-decoration:none;">Voir toutes les alertes →</a>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($alertesMagasin > 0): ?>
    <div class="stock-alert-wrapper" style="position:relative;">
      <button onclick="toggleMagasinPanel()" id="magasin-alert-btn" style="background:var(--gold-dim);border:1px solid var(--gold-glow);color:var(--gold);padding:4px 10px;border-radius:var(--radius-sm);cursor:pointer;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;font-family:inherit;transition:all .15s;">
        <span style="width:7px;height:7px;background:var(--gold);border-radius:50%;animation:pulse-dot 1.5s infinite;"></span>
        <?= icon('alert',14) ?> <?= $alertesMagasin ?> magasin
      </button>
      <div id="magasin-panel" style="display:none;position:absolute;top:100%;right:0;width:380px;max-height:420px;overflow-y:auto;background:var(--card);border:1px solid var(--border2);border-radius:var(--radius);box-shadow:var(--shadow);z-index:300;margin-top:8px;">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
          <div style="font-family:var(--font-title);font-size:15px;font-weight:600;color:var(--gold);"><?= icon('alert',16) ?> Alertes magasin</div>
          <button onclick="toggleMagasinPanel()" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;line-height:1;">×</button>
        </div>
        <?php
        $alertMagProds = getDB()->query("
            SELECT p.id, p.nom, p.stock_magasin, p.seuil_magasin, p.reference,
                   CASE WHEN p.stock_magasin = 0 THEN 'rupture' ELSE 'bas' END AS niveau
            FROM produits p
            WHERE p.actif = 1 AND p.stock_magasin <= p.seuil_magasin
            ORDER BY p.stock_magasin ASC, p.nom ASC LIMIT 15
        ")->fetchAll();
        foreach ($alertMagProds as $ap):
            $isRupture = $ap['stock_magasin'] == 0;
        ?>
        <a href="<?= url('magasin', ['onglet'=>'stock']) ?>" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);transition:background .15s;" onmouseover="this.style.background='var(--glass)'" onmouseout="this.style.background='transparent'">
          <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= $isRupture ? 'var(--red)' : 'var(--gold)' ?>;box-shadow:0 0 6px <?= $isRupture ? 'var(--red-glow)' : 'var(--gold-glow)' ?>;"></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($ap['nom']) ?></div>
            <div style="font-size:11px;color:var(--text3);"><?= e($ap['reference'] ?? '—') ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:14px;font-weight:700;color:<?= $isRupture ? 'var(--red)' : 'var(--gold)' ?>;"><?= $ap['stock_magasin'] ?></div>
            <div style="font-size:10px;color:var(--text3);">seuil <?= $ap['seuil_magasin'] ?></div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if ($alertesMagasin > 15): ?>
        <div style="padding:10px 16px;text-align:center;font-size:12px;color:var(--text3);">+ <?= $alertesMagasin - 15 ?> autre<?= ($alertesMagasin - 15) > 1 ? 's' : '' ?></div>
        <?php endif; ?>
        <a href="<?= url('magasin', ['onglet'=>'stock']) ?>" style="display:block;padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:var(--gold);border-top:1px solid var(--border);text-decoration:none;">Voir le stock magasin →</a>
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
<?php
// ── Assistant intégré (déterministe, sans serveur IA) ───────
// Auto-seed (patch code-only) puis widget FAB + slide-over si activé.
if (function_exists('assistant_ensure_schema')) {
    assistant_ensure_schema();
}
if (function_exists('assistantActive') && assistantActive()) {
    echo assistant_widget();
}
?>
<?php
// Nom de la pharmacie (en-tête du ticket hors ligne) — non bloquant : si la
// BDD est injoignable au rendu de la page, l'en-tête se contente des paramètres.
$pcPharmNom = '';
try {
    $pcPharmNom = trim((string)getDB()->query("SELECT nom FROM pharmacies WHERE id = 1 LIMIT 1")->fetchColumn());
} catch (Throwable $e) { /* offline / table absente */ }
?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf()) ?>; window.APP_URL = <?= json_encode(APP_URL) ?>; window.PC_USER_ID = <?= json_encode((int)($_SESSION['user_id'] ?? 0)) ?>; window.PHARMCARE_PAGE = <?= json_encode($GLOBALS['__pc_active_page'] ?? '') ?>; window.PC_SESSION_TIMEOUT = <?= json_encode(sessionTimeoutSeconds()) ?>; window.PC_USER_NAME = <?= json_encode(trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''))) ?>; window.PC_TICKET_META = <?= json_encode([
    'app_nom'     => getParam('app_nom', 'PharmaCare'),
    'sous_titre'  => getParam('ticket_sous_titre', 'Gestion Pharmacie'),
    'pied'        => getParam('ticket_pied', 'Merci pour votre achat !'),
    'copies'      => max(1, (int)getParam('ticket_nb_copies', '2')),
    'adresse'     => getParam('pharmacie_adresse', ''),
    'tel'         => getParam('pharmacie_telephone', ''),
    'nif'         => getParam('pharmacie_nif', ''),
    'logo'        => pharmacieLogoUrl(),
    'tva'         => getParam('tva', '19.25'),
    'dev'         => getParam('devise_symbole', 'FCFA'),
    'app_name'    => defined('APP_NAME') ? APP_NAME : 'PharmaCare',
    'pharmacie'   => $pcPharmNom,
], JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
// ── Session anti-inactivité : déconnexion après inactivité RÉELLE ───────
// Principe : tant que l'utilisateur travaille (souris / clavier), sa session
// est rafraîchie automatiquement → il n'est JAMAIS déconnecté pendant le
// travail. S'il n'y a plus AUCUNE interaction pendant PC_SESSION_TIMEOUT
// (paramètre admin : 10-15 min), le serveur déconnecte automatiquement
// l'utilisateur (401 → retour au login avec message « session expirée »).
// L'activité est partagée entre onglets via localStorage.
(function(){
  var TIMEOUT  = parseInt(window.PC_SESSION_TIMEOUT || '0', 10); // secondes ; 0 = désactivé
  var KEY       = 'pc_last_user_activity';   // dernière interaction (partagée entre onglets)
  var PING_MS   = 45000;                     // heartbeat de présence / activité
  var ACTIVE_MS = 45000;                     // « actif » = interaction de moins de 45 s
  var GRACE_S   = 45;                        // marge serveur (dernier ping actif)
  var WARN_S    = 60;                        // avertissement 60 s avant déconnexion
  var memLast = Date.now(), canStore = true, warned = false, expired = false;
  var warnBox = null, warnCount = null, warnText = null, lastPingActive = -1, pingBusy = false;

  function readLast(){
    if (canStore) { try { var v = parseInt(localStorage.getItem(KEY) || '0', 10); if (v > 0) return v; } catch (e) { canStore = false; } }
    return memLast;
  }
  function writeLast(t){
    memLast = t;
    if (canStore) { try { localStorage.setItem(KEY, String(t)); } catch (e) { canStore = false; } }
  }
  function idleS(){ return Math.floor((Date.now() - readLast()) / 1000); }
  function isActive(){ return (Date.now() - readLast()) < ACTIVE_MS; }
  // Un chargement de page demandé par l'utilisateur = activité (la session vient
  // d'être rafraîchie). SAUF auto-rechargement (mur de supervision ?autor=1) :
  // on ne réarme pas le compteur → sans interaction réelle, la session expire
  // normalement après le délai d'inactivité.
  if (!/[?&]autor=1(?=&|$)/.test(location.search)) writeLast(Date.now());

  function ping(forceActive){
    if (pingBusy || expired) return;
    lastPingActive = (forceActive || isActive()) ? 1 : 0;
    pingBusy = true;
    fetch(window.APP_URL + '/ping?active=' + lastPingActive, { cache: 'no-store', credentials: 'same-origin' })
      .then(function(r){ if (r.status === 401) sessionExpired(); })
      .catch(function(){})
      .then(function(){ pingBusy = false; });
  }

  function sessionExpired(){
    if (expired) return;
    expired = true;
    window.location.href = window.APP_URL + '/index.php?timeout=1';
  }

  // ── Suivi des interactions réelles (throttle : 1 trace / 5 s suffit) ──
  var lastTrace = 0;
  function onUserEvent(){
    var now = Date.now();
    if (now - lastTrace < 5000) return;
    lastTrace = now;
    writeLast(now);
    if (warned) hideWarn();
    // Retour de l'utilisateur après une période d'inactivité → rafraîchir
    // la session immédiatement (sans attendre le prochain heartbeat).
    if (lastPingActive === 0) ping(true);
  }
  ['mousemove','mousedown','keydown','wheel','touchstart','scroll'].forEach(function(ev){
    document.addEventListener(ev, onUserEvent, { passive: true });
  });

  // ── Avertissement 60 s avant la déconnexion automatique ──────────────
  function buildWarn(){
    warnBox = document.createElement('div');
    warnBox.id = 'pc-idle-overlay';
    warnBox.style.cssText = 'position:fixed;left:0;top:0;right:0;bottom:0;z-index:99999;background:rgba(2,6,23,.72);display:flex;align-items:center;justify-content:center;';
    var box = document.createElement('div');
    box.style.cssText = 'background:#1E293B;border:1px solid #334155;border-radius:14px;padding:26px 28px;max-width:430px;width:calc(100% - 40px);text-align:center;color:#E2E8F0;box-shadow:0 20px 60px rgba(0,0,0,.5);';
    box.innerHTML =
      '<div style="font-size:34px;line-height:1;margin-bottom:10px;">\u23F3</div>' +
      '<h3 style="margin:0 0 8px;font-size:17px;color:#FBBF24;">Inactivit\u00E9 d\u00E9tect\u00E9e</h3>' +
      '<p style="margin:0 0 14px;color:#94A3B8;line-height:1.5;font-size:13.5px;">Aucune activit\u00E9 de votre part depuis quelques minutes. Pour votre s\u00E9curit\u00E9, vous serez d\u00E9connect\u00E9 automatiquement.</p>' +
      '<p style="margin:0 0 18px;font-size:21px;font-weight:600;">D\u00E9connexion dans <span id="pc-idle-count" style="color:#F87171;font-variant-numeric:tabular-nums;">60</span> s</p>';
    var stay = document.createElement('button');
    stay.textContent = 'Rester connect\u00E9';
    stay.style.cssText = 'background:#0d9488;border:1px solid #14b8a6;color:#fff;font-weight:600;padding:10px 22px;border-radius:9px;cursor:pointer;font-size:14px;margin-right:10px;';
    var out = document.createElement('button');
    out.textContent = 'Se d\u00E9connecter';
    out.style.cssText = 'background:transparent;border:1px solid #475569;color:#94A3B8;padding:10px 18px;border-radius:9px;cursor:pointer;font-size:14px;';
    stay.onclick = function(){ writeLast(Date.now()); hideWarn(); ping(true); };
    out.onclick = function(){ expired = true; window.location.href = window.APP_URL + '/logout.php'; };
    box.appendChild(stay);
    box.appendChild(out);
    warnBox.appendChild(box);
    document.body.appendChild(warnBox);
    warnCount = document.getElementById('pc-idle-count');
    warnText = warnCount ? warnCount.parentNode : null;
  }
  function hideWarn(){
    warned = false;
    if (warnBox && warnBox.parentNode) warnBox.parentNode.removeChild(warnBox);
    warnBox = null; warnCount = null;
  }

  setInterval(function(){
    if (TIMEOUT <= 0 || expired) return;
    var remain = (TIMEOUT + GRACE_S) - idleS(); // secondes avant expiration c\u00F4t\u00E9 serveur
    if (!warned && remain <= WARN_S) { warned = true; buildWarn(); }
    if (warned) {
      if (remain > WARN_S) { hideWarn(); return; } // activité détectée (autre onglet…)
      var s = Math.max(0, remain);
      if (warnCount) warnCount.textContent = s;
      if (s <= 0 && warnText) warnText.innerHTML = 'D\u00E9connexion en cours…';
    }
  }, 1000);

  // ── Heartbeat : présence toutes les 45 s (active selon l'interaction réelle) ──
  setInterval(function(){ ping(false); }, PING_MS);
  ping(false);
  window.addEventListener('beforeunload', function(){
    try { navigator.sendBeacon && navigator.sendBeacon(window.APP_URL + '/ping?bye=1'); } catch(e){}
  });
})();
</script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
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
<script>
// ── Offline-first : moteur de disponibilité + file d'attente des ventes ──
// Le serveur (XAMPP LAN) peut tomber quelques minutes (redémarrage MySQL,
// câble débranché). La caisse ne doit jamais perdre une vente :
//   - pill de statut en permanence (sondage /ping toutes les 15 s) ;
//   - soumission de vente interceptée (form POS) : si le serveur est
//     injoignable, la vente est conservée dans le navigateur avec une clé
//     d'idempotence (client_ref UUID) puis RETRANSMISE automatiquement au
//     retour du serveur — le dédoublonnage serveur rend le rejeu sans risque ;
//   - le rejeu peut se produire depuis n'importe quelle page, un onglet à la
//     fois (verrou localStorage + PK UNIQUE client_ref en base).
window.PC_OFFLINE = (function(){
  // File d'attente PAR UTILISATEUR : les ventes hors ligne sont rejouées sous
  // l'identité du caissier qui les a encaissées (attribution / suivi corrects).
  var KEY = 'pc_offline_queue_' + (window.PC_USER_ID || 0), LOCK = 'pc_offline_sync_lock';
  // Capacité de la file hors ligne : 1000 ventes PAR UTILISATEUR.
  // Chaque vente compactée ≈ 0,7 Ko → 1000 ventes ≈ 0,7 Mo, très en deçà du
  // quota localStorage (~5 Mo). Le rejeu est CADENCÉ (REPLAY_GAP) pour rester
  // sous le rate limit POS serveur (20 ventes/60 s, fenêtre glissante) : jamais
  // bloqué, aucune vente abandonnée — 1000 ventes ≈ 55 min de rejeu auto.
  var CAP = 1000, LEASE = 90000, RETRY_MS = 30000, RETRY_MAX = 5;
  var REPLAY_GAP = 3200;   // ms entre deux rejeus → ≈18-19 ventes/min (< 20/min)
  var mode = 'init', syncing = false;
  var netFailUntil = 0; // circuit breaker : dernier échec réseau — pas de re-tente pendant 30 s
  var dot = null, label = null, badge = null;

  function readQueue(){ try { return JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { return []; } }
  // Renvoie false si le quota localStorage est dépassé : l'appelant doit alors
  // considérer la vente NON enregistrée (et ne pas vider le panier).
  // Chaque écriture notifie window.PC_ON_QUEUE_CHANGE() si défini (le POS
  // rafraîchit son solde de caisse en direct — ventes en file incluses).
  function writeQueue(q){
    try { localStorage.setItem(KEY, JSON.stringify(q)); }
    catch (e) { return false; }
    try { if (typeof window.PC_ON_QUEUE_CHANGE === 'function') window.PC_ON_QUEUE_CHANGE(); } catch (e2) {}
    return true;
  }
  function uuid(){ return 'pos-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10); }
  // ── Ticket hors ligne : impression locale sans serveur ─────────────
  // Après une vente mise en file (serveur injoignable), le caissier doit
  // pouvoir remettre un ticket imprimé au client. Le ticket est reconstruit
  // depuis les données de la vente en file (localStorage) avec les MÊMES
  // formules que le serveur (sous-total HT → remise HT → TVA → net TTC).
  // Référence provisoire = client_ref (clé d'idempotence) ; la vraie
  // référence VNT-… est attribuée au rejeu. Ré-imprimable depuis la file
  // (clic sur la pastille « Serveur » dans la barre latérale).
  function escH(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function fmtM2(n){
    n = Math.round((parseFloat(n) || 0) * 100) / 100;
    var neg = n < 0; n = Math.abs(n);
    var p = n.toFixed(2).split('.');
    p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return (neg ? '-' : '') + p.join(',');
  }
  function modeLabel(m){ var M = {'espèces':'Espèces','carte':'Carte bancaire','chèque':'Chèque','assurance':'Assurance','crédit':'Crédit'}; return M[m] || (m || '—'); }
  // Raison de mise en file d'une vente hors ligne : 'server' (serveur
  // injoignable / erreur 5xx), 'auth' (session expirée), 'caisse' (caisse
  // fermée). Permet un message EXACT sur le ticket et dans la file — ne pas
  // crier « serveur hors ligne » quand la vraie raison est une session
  // expirée ou une caisse fermée (le serveur, lui, est en ligne).
  function reasonLabel(reason){ var R = { server:'serveur injoignable', auth:'session expirée', caisse:'caisse fermée' }; return R[reason] || R.server; }
  function reasonTicket(reason){
    var R = {
      server:'Ticket provisoire — vente transmise automatiquement dès le retour du serveur',
      auth:  'Ticket provisoire — session expirée, vente transmise à la reconnexion',
      caisse:'Ticket provisoire — vente transmise à l\'ouverture de la caisse'
    };
    return R[reason] || R.server;
  }
  function offlineTotals(f){
    var items = [];
    try {
      var cd = f.cart_data ? JSON.parse(f.cart_data) : {};
      for (var k in cd) if (Object.prototype.hasOwnProperty.call(cd, k)) items.push(cd[k]);
    } catch (e) { items = []; }
    var meta = window.PC_TICKET_META || {};
    var rate = (parseFloat(meta.tva) || 0) / 100;
    var subHt = 0, lines = [];
    items.forEach(function(it){
      var price = parseFloat(it.price) || 0;
      var qty = Math.max(1, parseInt(it.qty, 10) || 1);
      var tot = Math.round(price * qty * 100) / 100;
      subHt += tot;
      lines.push({ name: it.name || ('Produit #' + (it.id || '?')), price: price, qty: qty, total: tot });
    });
    subHt = Math.round(subHt * 100) / 100;
    var remisePct = Math.max(0, parseFloat(f.remise_pct) || 0);
    var remiseHt = Math.round(subHt * remisePct / 100 * 100) / 100;
    var netHt = Math.round((subHt - remiseHt) * 100) / 100;
    var tva = Math.round(netHt * rate * 100) / 100;
    var total = Math.round((netHt + tva) * 100) / 100;
    var mode = f.mode_paiement || 'espèces';
    var recu = (mode === 'crédit') ? 0 : (parseFloat(f.montant_recu) || 0);
    var monnaie = Math.max(0, Math.round((recu - total) * 100) / 100);
    return { lines: lines, subHt: subHt, remisePct: remisePct, remiseHt: remiseHt, tva: tva, tvaPct: parseFloat(meta.tva) || 0, total: total, mode: mode, recu: recu, monnaie: monnaie };
  }
  function ticketHTML(item){
    var f = item.form || {}, meta = window.PC_TICKET_META || {}, t = offlineTotals(f);
    var d = new Date(item.ts || Date.now());
    function pad(n){ return n < 10 ? '0' + n : '' + n; }
    var dateStr = pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    var h = '';
    h += '<div style="text-align:center;border-bottom:1px dashed #999;padding-bottom:8px;margin-bottom:8px;">';
    if (meta.logo) h += '<div style="margin-bottom:6px;"><img src="' + escH(meta.logo) + '" alt="" style="max-height:60px;max-width:90%;"></div>';
    h += '<div style="font-size:15px;font-weight:700;">' + escH(meta.app_nom || 'PharmaCare') + '</div>';
    if (meta.sous_titre) h += '<div style="font-size:10px;color:#555;margin-top:2px;">' + escH(meta.sous_titre) + '</div>';
    if (meta.pharmacie) h += '<div style="font-size:11px;font-weight:600;margin-top:3px;">' + escH(meta.pharmacie) + '</div>';
    if (meta.adresse) h += '<div style="font-size:10px;color:#555;margin-top:2px;">' + escH(meta.adresse) + '</div>';
    if (meta.tel) h += '<div style="font-size:10px;color:#555;margin-top:1px;">' + escH(meta.tel) + '</div>';
    if (meta.nif) h += '<div style="font-size:10px;color:#555;margin-top:1px;">' + escH(meta.nif) + '</div>';
    h += '</div>';
    h += '<div style="display:flex;justify-content:space-between;font-size:11px;color:#333;margin-bottom:6px;">';
    h += '<span style="font-weight:700;">PROVISOIRE · ' + escH(f.client_ref || ('pos-' + (item.ts || ''))) + '</span>';
    h += '<span>' + dateStr + '</span></div>';
    h += '<div style="font-size:10px;color:#555;margin-bottom:8px;">' + escH(reasonTicket(item.reason)) + '</div>';
    var client = f.client_nom_saisie || (f.client_mode === 'existant' ? ('Client #' + (f.client_id || '?')) : '');
    if (client) h += '<div style="font-size:11px;color:#555;margin-bottom:8px;">Client : ' + escH(client) + '</div>';
    h += '<div style="border-top:1px dashed #999;border-bottom:1px dashed #999;padding:6px 0;margin-bottom:8px;">';
    t.lines.forEach(function(l){
      h += '<div style="display:flex;justify-content:space-between;margin-bottom:1px;"><span>' + escH(l.name) + '</span><span>' + fmtM2(l.total) + '</span></div>';
      h += '<div style="font-size:10px;color:#777;margin-bottom:3px;">' + fmtM2(l.price) + ' &times; ' + l.qty + '</div>';
    });
    h += '</div>';
    var dev = meta.dev || '';
    h += '<div style="display:flex;justify-content:space-between;font-size:11px;"><span>Sous-total HT</span><span>' + fmtM2(t.subHt) + ' ' + escH(dev) + '</span></div>';
    if (t.remisePct > 0) h += '<div style="display:flex;justify-content:space-between;font-size:11px;"><span>Remise ' + t.remisePct + '% (HT)</span><span>-' + fmtM2(t.remiseHt) + '</span></div>';
    if (t.tva > 0) h += '<div style="display:flex;justify-content:space-between;font-size:11px;"><span>TVA (' + t.tvaPct + '%)</span><span>' + fmtM2(t.tva) + ' ' + escH(dev) + '</span></div>';
    h += '<div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:6px;padding-top:6px;border-top:1px dashed #999;"><span>TOTAL TTC</span><span>' + fmtM2(t.total) + ' ' + escH(dev) + '</span></div>';
    if (t.mode === 'espèces' && t.recu > 0) {
      h += '<div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;"><span>Reçu</span><span>' + fmtM2(t.recu) + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;font-size:11px;"><span>Reliquat</span><span>' + fmtM2(t.monnaie) + '</span></div>';
    }
    h += '<div style="text-align:center;margin-top:12px;padding-top:8px;border-top:1px dashed #999;font-size:10px;color:#555;">';
    h += modeLabel(t.mode) + '<br>Caissier : ' + escH(window.PC_USER_NAME || '') + '<br>';
    h += escH(meta.pied || '') + '<br>';
    h += '<span>&copy; ' + new Date().getFullYear() + ' ' + escH(meta.app_name || 'PharmaCare') + '</span></div>';
    return h;
  }
  function printOffline(item){
    try {
      var meta = window.PC_TICKET_META || {};
      var copies = Math.max(1, parseInt(meta.copies, 10) || 1);
      var body = '';
      for (var i = 0; i < copies; i++) {
        body += '<div class="ticket-copy"><div class="copy-label">' + (i === 0 ? 'Exemplaire Client' : (i === 1 ? 'Exemplaire Caisse' : 'Exemplaire ' + (i + 1))) + '</div>' + ticketHTML(item) + '</div>';
      }
      var fr = document.getElementById('pc-offline-print-frame');
      if (fr && fr.parentNode) fr.parentNode.removeChild(fr);
      fr = document.createElement('iframe');
      fr.id = 'pc-offline-print-frame';
      fr.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;';
      document.body.appendChild(fr);
      var doc = fr.contentWindow.document;
      doc.open();
      doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ticket hors ligne</title><style>'
        + '*{margin:0;padding:0;box-sizing:border-box;}'
        + 'body{font-family:\'DM Mono\',monospace;font-size:12px;line-height:1.5;padding:8px;max-width:280px;margin:0 auto;color:#000;background:#fff;}'
        + '.ticket-copy{page-break-after:always;}'
        + '.ticket-copy:last-child{page-break-after:auto;}'
        + '.copy-label{text-align:center;font-size:10px;font-weight:700;letter-spacing:1px;color:#000;border:1px dashed #000;border-radius:4px;padding:3px 0;margin-bottom:6px;text-transform:uppercase;}'
        + 'img{display:block;margin:0 auto;}'
        + '@media print{body{margin:0;}@page{margin:0;size:80mm auto;}}'
        + '</style></head><body>' + body
        + '<scr' + 'ipt>window.onload=function(){setTimeout(function(){window.focus();window.print();},60);}</scr' + 'ipt></body></html>');
      doc.close();
    } catch (e) { notify('Impression du ticket impossible : ' + (e && e.message ? e.message : 'erreur'), 'error'); }
  }
  var queueModal = null;
  function closeQueueModal(){
    if (queueModal && queueModal.parentNode) queueModal.parentNode.removeChild(queueModal);
    queueModal = null;
  }
  function openQueueModal(){
    if (queueModal) { closeQueueModal(); return; }
    var q = readQueue();
    var ov = document.createElement('div');
    ov.id = 'pc-queue-overlay';
    ov.style.cssText = 'position:fixed;left:0;top:0;right:0;bottom:0;z-index:99998;background:rgba(2,6,23,.72);display:flex;align-items:center;justify-content:center;';
    var box = document.createElement('div');
    box.style.cssText = 'background:#1E293B;border:1px solid #334155;border-radius:14px;padding:24px;max-width:560px;width:calc(100% - 40px);max-height:80vh;overflow:auto;color:#E2E8F0;';
    var rows = '';
    function pad(n){ return n < 10 ? '0' + n : '' + n; }
    q.forEach(function(item, i){
      var t = offlineTotals(item.form || {});
      var d = new Date(item.ts || Date.now());
      var ds = pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
      rows += '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #334155;">'
        + '<div><div style="font-weight:600;">' + escH((item.form && item.form.client_nom_saisie) || 'Client') + '</div>'
        + '<div style="font-size:11px;color:#94A3B8;font-family:\'DM Mono\',monospace;">' + ds + ' · ' + fmtM2(t.total) + ' ' + escH((window.PC_TICKET_META || {}).dev || '') + ' · ' + escH((item.form && item.form.client_ref) || '') + ' · ' + escH(reasonLabel(item.reason)) + '</div></div>'
        + '<button data-pcqi="' + i + '" class="pc-queue-print" style="background:#0d9488;border:1px solid #14b8a6;color:#fff;font-weight:600;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:12px;flex:none;">🧾 Ticket</button>'
        + '</div>';
    });
    if (!q.length) rows = '<div style="color:#94A3B8;font-size:13px;padding:16px 0;text-align:center;">Aucune vente en attente — la file est vide.</div>';
    box.innerHTML = '<h3 style="margin:0 0 4px;font-size:16px;">Ventes hors ligne en attente (' + q.length + ')</h3>'
      + '<p style="margin:0 0 14px;font-size:12px;color:#94A3B8;line-height:1.5;">Ventes encaissées sans serveur — transmises automatiquement à son retour. Cliquez « Ticket » pour (ré)imprimer.</p>'
      + rows
      + '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">'
      + '<button id="pc-queue-close" style="background:transparent;border:1px solid #475569;color:#94A3B8;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;">Fermer</button>'
      + '<button id="pc-queue-sync" style="background:#0d9488;border:1px solid #14b8a6;color:#fff;font-weight:600;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;' + (q.length ? '' : 'display:none;') + '">Transmettre maintenant</button>'
      + '</div>';
    ov.appendChild(box);
    document.body.appendChild(ov);
    queueModal = ov;
    ov.addEventListener('click', function(ev){
      var t = ev.target;
      if (t === ov || t.id === 'pc-queue-close') { closeQueueModal(); return; }
      if (t.id === 'pc-queue-sync') { closeQueueModal(); syncQueue(); return; }
      if (t.className === 'pc-queue-print') {
        var idx = parseInt(t.getAttribute('data-pcqi'), 10);
        var it = readQueue()[idx];
        if (it) printOffline(it);
      }
    });
  }
  function notify(msg, kind){ try { if (typeof showNotif === 'function') showNotif(msg, kind || 'info'); } catch (e) {} }
  function refreshPill(){
    if (!badge) return;
    var q = readQueue();
    badge.style.display = q.length ? '' : 'none';
    badge.textContent = q.length;
  }
  function setPill(bg, glow, txt){
    if (dot) { dot.style.background = bg; dot.style.boxShadow = glow ? '0 0 8px ' + bg : 'none'; }
    if (label) label.textContent = txt;
    refreshPill();
  }
  function apply(m){
    if (m === mode) return;
    var prev = mode; mode = m;
    if (m === 'online') {
      setPill('#22c55e', true, 'Serveur connecté');
      if (prev === 'offline' || prev === 'db') notify('Serveur de nouveau disponible — reprise.', 'success');
      if (readQueue().length) syncQueue();
    } else if (m === 'offline') {
      setPill('#ef4444', false, 'Serveur injoignable — mode attente');
      if (prev === 'online' || prev === 'init' || prev === 'db') notify('⚠ Serveur injoignable — les ventes seront mises en file d\u2019attente.', 'error');
    } else if (m === 'db') {
      setPill('#ef4444', false, 'Base de données indisponible');
      if (prev === 'online') notify('⚠ Base de données indisponible — les ventes seront mises en file d\u2019attente.', 'error');
    } else if (m === 'auth') {
      setPill('#f59e0b', false, 'Session expirée — reconnectez-vous');
    }
  }
  // Anti faux « offline » : 2 échecs CONSÉCUTIFS requis avant de basculer le
  // pill en « Serveur injoignable / BDD indisponible » — une micro-coupure LAN
  // (un seul sondage raté) ne doit plus marquer les ventes suivantes « hors
  // ligne » alors que le serveur répond très bien.
  var pollFails = 0;
  function poll(){
    fetch(window.APP_URL + '/ping', { cache: 'no-store', credentials: 'same-origin' })
      .then(function(r){
        if (r.status === 204) { pollFails = 0; apply('online'); }
        else if (r.status === 401) { pollFails = 0; apply('auth'); } // réponse certaine du serveur
        else if (r.status >= 500) { if (++pollFails >= 2) apply('db'); }
        else { pollFails = 0; apply('online'); } // répond (statut inattendu) → serveur joignable
      })
      .catch(function(){ if (++pollFails >= 2) apply('offline'); });
  }
  // ── File d'attente locale (navigateur, par terminal) ──
  function fdToObj(fd){ var o = {}; fd.forEach(function(v, k){ o[k] = String(v); }); return o; }
  function objToFd(o){ var fd = new FormData(); for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) fd.append(k, o[k]); return fd; }
  function dequeue(){ var q = readQueue(); q.shift(); writeQueue(q); refreshPill(); }
  function lockTake(){
    var now = Date.now(), cur = parseInt(localStorage.getItem(LOCK) || '0', 10) || 0;
    if (cur && now - cur < LEASE) return false;
    try { localStorage.setItem(LOCK, String(now)); return true; } catch (e) { return false; }
  }
  function lockRelease(){ try { localStorage.removeItem(LOCK); } catch (e) {} }
  // Ralliement du bail de verrou PENDANT le rejeu : sans lui, LEASE (90 s)
  // expirerait en cours de long rejeu (1000 ventes ≈ 55 min) et un autre
  // onglet pourrait démarrer un rejeu concurrent sur la même file.
  function lockRefresh(){ try { localStorage.setItem(LOCK, String(Date.now())); } catch (e) {} }
  var replayDone = 0; // ventes retransmises depuis le début du rejeu courant
  function syncQueue(){
    if (syncing || mode !== 'online' || readQueue().length === 0) return;
    if (!lockTake()) return;
    syncing = true;
    var step = function(){
      lockRefresh(); // rallonge le bail de verrou pendant les longs rejeus
      var q = readQueue();
      if (!q.length) {
        syncing = false; lockRelease(); refreshPill();
        if (replayDone > 0) { notify(replayDone + ' vente(s) transmise(s) ✓ — tickets définitifs dans l\'historique', 'success'); replayDone = 0; }
        return;
      }
      var item = q[0];
      var fd = objToFd(item.form);
      fd.set('csrf', window.CSRF_TOKEN); // jeton frais de la session courante
      fetch(window.APP_URL + '/vente', { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' })
        .then(function(r){
          if (r.redirected && /[?&]receipt=/.test(r.url)) {
            dequeue(); replayDone++;
            // Notifier le POS (solde de caisse en direct) : la vente retransmise
            // vient d'être enregistrée côté serveur — elle quitte la file
            // (le hook PC_ON_SALE_TRANSMITTED la bascule dans « transmis »).
            if (typeof window.PC_ON_SALE_TRANSMITTED === 'function') window.PC_ON_SALE_TRANSMITTED((item.form || {}).mode_paiement === 'espèces' ? offlineTotals(item.form).total : 0);
            // Notifications agrégées : pas de toast par vente (1000 ventes =
            // 1000 toasts) — on annonce le départ, un point toutes les 25, la fin.
            if (replayDone === 1) notify('Rejeu hors ligne démarré : ' + readQueue().length + ' vente(s) à transmettre…', 'info');
            else if (replayDone % 25 === 0) notify('Rejeu en cours : ' + replayDone + ' transmise(s), ' + readQueue().length + ' restante(s)…', 'info');
            // Cadence ≤ 20 ventes/60 s (rate limit POS serveur, fenêtre
            // glissante) : on espace les envois pour ne jamais être bloqué.
            setTimeout(step, REPLAY_GAP);
            return;
          }
          if (r.redirected && /index\.php/.test(r.url)) {
            syncing = false; lockRelease(); apply('auth');
            notify('Reconnectez-vous : ' + readQueue().length + ' vente(s) hors ligne conservée(s).', 'error');
            return;
          }
          if (r.redirected && r.url.indexOf('/caisse') !== -1 && /[?&]action=ouvrir/.test(r.url)) {
            // Session de caisse fermée : la vente exige une caisse ouverte.
            // On ne déqueue JAMAIS ici — le rejeu reprendra dès l'ouverture.
            syncing = false; lockRelease();
            notify('Ouvrez votre caisse pour transmettre ' + readQueue().length + ' vente(s) hors ligne.', 'error');
            return;
          }
          return r.text().then(function(html){
            var rateLimited = html.indexOf('Trop de ventes enregistrées') !== -1;
            if (rateLimited) {
              // Blocage TEMPORAIRE (anti-abus) → JAMAIS de vente perdue : le
              // serveur indique le délai (« Réessayez dans X s. »), on attend
              // X+1 s puis on reprend. RETRY_MAX ne s'applique PAS ici (il ne
              // concerne que les refus métier : stock, remise…).
              var m = html.match(/dans\s+(\d+)\s*s/i);
              var wait = m ? (parseInt(m[1], 10) + 1) * 1000 : RETRY_MS;
              q = readQueue(); if (q.length) { q[0].tries = (q[0].tries || 0) + 1; writeQueue(q); }
              syncing = false; lockRelease();
              setTimeout(syncQueue, wait);
              return;
            }
            dequeue();
            notify('Vente hors ligne refusée par le serveur — détail à la caisse.', 'error');
            step();
          });
        })
        .catch(function(){ syncing = false; lockRelease(); apply('offline'); });
    };
    step();
  }

  // ── API publique ──
  var api = {
    uuid: uuid,
    isOnline: function(){ return mode === 'online'; },
    queueSize: function(){ return readQueue().length; },
    sync: syncQueue,
    printOffline: printOffline,
    openQueue: openQueueModal,
    // Total espèces des ventes en file (hors ligne) : le cash est déjà
    // encaissé physiquement → le solde de caisse affiché au POS doit
    // l'inclure tant que la transmission n'a pas eu lieu.
    pendingEspeceTotal: function(){
      var s = 0;
      readQueue().forEach(function(it){
        var f = it.form || {};
        if (f.mode_paiement === 'espèces') s += offlineTotals(f).total;
      });
      return Math.round(s * 100) / 100;
    },
    // Soumission résiliente d'une vente (utilisé par le POS)
    // La vente est écrite dans la file AVANT l'envoi (retrait seulement après
    // succès ou refus confirmé) : si le terminal s'éteint en plein fetch, la
    // vente n'est jamais perdue — au redémarrage, le rejeu s'exécute et le
    // dédoublonnage serveur renvoie la réception si elle avait déjà été
    // enregistrée (réponse perdue après commit).
    // Raison de mise en file : 'server' | 'auth' | 'caisse' (voir reasonLabel).
    submitSale: function(fd, url, cb){
      var o = fdToObj(fd);
      if (!o.client_ref) o.client_ref = uuid();
      // Compacter le panier avant stockage : le serveur revalide prix et stock
      // au rejeu — stock/stockOrig sont inutiles → ~40 % plus léger →
      // 1000 ventes ≈ 0,7 Mo, très en deçà du quota localStorage (~5 Mo).
      try {
        var cd = JSON.parse(o.cart_data || '{}'), comp = {};
        for (var k in cd) if (Object.prototype.hasOwnProperty.call(cd, k)) {
          var it = cd[k];
          comp[k] = { id: it.id, name: it.name, price: it.price, qty: it.qty };
        }
        o.cart_data = JSON.stringify(comp);
      } catch (e) { /* panier non compactable : stocké tel quel */ }
      var q = readQueue();
      if (q.length >= CAP) { notify('File hors ligne pleine (' + CAP + ' ventes) — reconnectez le serveur.', 'error'); if (cb && cb.onQueued) cb.onQueued(null, false); return; }
      // Écrite en file AVANT l'envoi (crash-safety : terminal éteint en plein
      // fetch → la vente n'est jamais perdue ; retrait seulement après succès).
      q.push({ form: o, ts: Date.now(), tries: 0, reason: 'server' });
      if (!writeQueue(q)) {
        // Quota localStorage dépassé : la vente n'est PAS enregistrée — on ne
        // vide pas le panier, le caissier peut réessayer (ou vider la file).
        notify('Stockage du navigateur plein — vente non mise en file. Reconnectez le serveur ou réessayez.', 'error');
        if (cb && cb.onQueued) cb.onQueued(null, false);
        return;
      }
      refreshPill();
      var queued = q[q.length - 1]; // vente en file → ticket imprimable sans serveur
      // Raison réelle de la mise en file (ticket + file d'attente exacts).
      function setReason(reason){
        queued.reason = reason;
        var q2 = readQueue();
        for (var i = q2.length - 1; i >= 0; i--) {
          if (q2[i].form.client_ref === o.client_ref) { q2[i].reason = reason; writeQueue(q2); break; }
        }
      }
      var suspect = (mode === 'offline' || mode === 'db' || mode === 'auth');
      if (suspect && Date.now() < netFailUntil) {
        // Panne CONFIRMÉE (échec réseau il y a moins de 30 s) : pas de re-tente
        // à chaque vente — file immédiate + ticket hors ligne.
        notify('Serveur injoignable — vente enregistrée localement (' + readQueue().length + ' en attente). Ticket envoyé à l\'imprimante ; transmission auto au retour du serveur.', 'error');
        if (cb && cb.onQueued) cb.onQueued(queued, false);
        return;
      }
      // On TENTE TOUJOURS l'envoi : un pill « offline/db/auth » peut venir d'un
      // échec de sondage passager ou d'une session expirée… alors que le serveur
      // répond très bien (ex. session rafraîchie entre-temps par le heartbeat).
      // Sans cette tentative, des ventes seraient mises en file (ticket
      // « provisoire ») pour rien. Timeout court UNIQUEMENT si le mode est
      // suspect ; en ligne, on laisse le serveur prendre son temps.
      var ac = null;
      if (suspect && window.AbortController) {
        ac = new AbortController();
        setTimeout(function(){ try { ac.abort(); } catch (e) {} }, 6000);
      }
      fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow', signal: ac ? ac.signal : undefined })
        .then(function(r){
          if (r.redirected && /[?&]receipt=/.test(r.url)) {
            // Notifier le POS (solde de caisse en direct) : vente espèces
            // enregistrée côté serveur — elle compte dans le solde.
            if (typeof window.PC_ON_SALE_TRANSMITTED === 'function') window.PC_ON_SALE_TRANSMITTED(o.mode_paiement === 'espèces' ? offlineTotals(o).total : 0);
            dequeue(); if (cb && cb.onSuccess) cb.onSuccess(r.url); syncQueue(); return;
          }
          if (r.redirected && /index\.php/.test(r.url)) { setReason('auth'); notify('Session expirée — vente conservée, reconnectez-vous : transmission automatique après reconnexion.', 'error'); if (cb && cb.onQueued) cb.onQueued(queued, false); apply('auth'); return; }
          if (r.redirected && r.url.indexOf('/caisse') !== -1 && /[?&]action=ouvrir/.test(r.url)) {
            // Caisse fermée : la vente reste en file, on la rejouera après ouverture
            // (pas de ticket auto : la vente n'est pas encore acceptée par le serveur,
            //  réimprimable depuis la file via la pastille « Serveur »).
            setReason('caisse');
            notify('Ouvrez votre caisse pour transmettre ' + readQueue().length + ' vente(s) hors ligne.', 'error');
            if (cb && cb.onQueued) cb.onQueued(queued, true);
            return;
          }
          if (r.status >= 500) { setReason('server'); notify('Serveur indisponible — vente conservée en file d\'attente. Ticket envoyé à l\'imprimante.', 'error'); if (cb && cb.onQueued) cb.onQueued(queued, false); return; }
          dequeue(); // refus métier confirmé par le serveur (stock, remise…)
          if (cb && cb.onServerRefused) cb.onServerRefused(r.url);   // flash affichée sur la page cible
        })
        .catch(function(){
          // Échec réseau (ou timeout) : vente conservée + circuit breaker 30 s
          // pour que les ventes suivantes ne réattendent pas le timeout.
          netFailUntil = Date.now() + 30000;
          setReason('server');
          notify('Serveur injoignable — vente enregistrée localement (' + readQueue().length + ' en attente). Ticket envoyé à l\'imprimante.', 'error');
          if (cb && cb.onQueued) cb.onQueued(queued, false);
        });
    }
  };

  // ── Init : pill + sondage + hook du POS (app.js déjà chargé à ce stade) ──
  dot = document.getElementById('pc-server-dot');
  label = document.getElementById('pc-server-label');
  badge = document.getElementById('pc-server-badge');
  // Clic sur la pastille « Serveur » → file d'attente hors ligne
  // (liste des ventes en attente + réimpression des tickets).
  var pill = document.getElementById('pc-server-pill');
  if (pill) {
    pill.style.cursor = 'pointer';
    pill.title = 'Ventes hors ligne en attente — cliquer pour les voir / réimprimer les tickets';
    pill.onclick = function(){ openQueueModal(); };
  }
  poll();
  setInterval(poll, 15000);
  window.addEventListener('online', poll);
  window.addEventListener('offline', function(){ apply('offline'); });

  // Hook du formulaire du POS : attaché ICI (après app.js) pour que les
  // validations existantes (remise, licence) s'exécutent d'abord — on ne
  // soumet que si aucune n'a annulé l'événement (e.defaultPrevented).
  (function(){
    var form = document.getElementById('pos-form');
    if (!form) return;
    var busy = false;
    form.addEventListener('submit', function(e){
      if (e.defaultPrevented) return;
      e.preventDefault();
      if (busy) return;
      busy = true;
      var fd = new FormData(form);
      if (!fd.get('client_ref')) fd.set('client_ref', uuid());
      api.submitSale(fd, form.action || window.location.href, {
        onSuccess: function(url){
          try { cart = {}; saveCart(); renderCart(); } catch (err) {}
          window.location.href = url; // page réception
        },
        onQueued: function(item, silent){
          // Ne vider le panier QUE si la vente a bien été mise en file
          // (file pleine → vente non enregistrée : le caissier garde son
          // panier et réessaie après transmission).
          if (item) { try { cart = {}; saveCart(); renderCart(); } catch (err) {} }
          busy = false;
          // Vente mise en file (serveur injoignable) → ticket imprimé
          // IMMÉDIATEMENT en local : le client repart avec son ticket,
          // sans attendre le retour du serveur.
          if (item && !silent) printOffline(item);
        },
        onServerRefused: function(url){
          try { /* le panier est conservé : le caissier peut corriger et réessayer */ } catch (err) {}
          busy = false;
          window.location.href = url; // la flash d'erreur serveur s'affiche
        }
      });
    });
  })();

  return api;
})();
</script>
</body></body>
</html>
<?php
}
// flash() et showFlash() sont dans includes/auth.php
