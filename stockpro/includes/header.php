<?php
require_once dirname(__DIR__).'/includes/config.php';
requireAuth();
$ent = getEntreprise();
$user = $_SESSION['user'];

$db = getDB();
$alertes_count = $db->query("SELECT COUNT(*) FROM produits WHERE quantite <= quantite_min AND actif=1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light" data-font="dmsans">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?? 'StockPro' ?> — <?= htmlspecialchars($ent['nom']) ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>
// Activate only the needed font links, prefetch the rest
(function(){
  var bodyFont = localStorage.getItem('sp_font') || 'manrope';
  var headFont = localStorage.getItem('sp_heading') || 'manrope';
})();
</script>

<script>
// Appliquer les préférences AVANT le rendu pour éviter le flash
(function(){
  var t = localStorage.getItem('sp_theme') || 'light';
  var f = localStorage.getItem('sp_font')  || 'manrope';
  var h = localStorage.getItem('sp_heading') || 'manrope';
  var s = localStorage.getItem('sp_size')  || '14';
  if (t === 'auto') t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', t);
  document.documentElement.setAttribute('data-font', f);
  document.documentElement.setAttribute('data-heading', h);
  document.documentElement.style.fontSize = s + 'px';
})();
</script>

<style>
/* ============================================================
   VARIABLES
   ============================================================ */
:root {
  --primary:      <?= $ent['couleur_primaire'] ?>;
  --primary-light: color-mix(in srgb, var(--primary) 12%, transparent);
  --primary-mid:   color-mix(in srgb, var(--primary) 25%, transparent);
  --dark:         <?= $ent['couleur_secondaire'] ?>;
  --sidebar-w:    260px;
  /* Light */
  --bg:       #f1f5f9;
  --card:     #ffffff;
  --text:     #0f172a;
  --text-2:   #475569;
  --muted:    #94a3b8;
  --border:   #e2e8f0;
  --input-bg: #ffffff;
  /* Fixed colours */
  --danger:   #ef4444;
  --success:  #10b981;
  --warning:  #f59e0b;
  --info:     #3b82f6;
  --radius:   14px;
  --shadow:   0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
  --shadow-lg:0 8px 32px rgba(0,0,0,.1);
  /* Fonts */
  --font-body:    'Manrope', sans-serif;
  --font-heading: 'Manrope', sans-serif;
}

/* ============================================================
   DARK MODE
   ============================================================ */
[data-theme="dark"] {
  --bg:       #0f172a;
  --card:     #1e293b;
  --text:     #f1f5f9;
  --text-2:   #94a3b8;
  --muted:    #64748b;
  --border:   #334155;
  --input-bg: #273549;
  --shadow:   0 1px 3px rgba(0,0,0,.3), 0 4px 16px rgba(0,0,0,.2);
  --shadow-lg:0 8px 32px rgba(0,0,0,.4);
}
[data-theme="dark"] .sidebar        { background: #0a1628 !important; }
[data-theme="dark"] tr:hover td     { background: #273549 !important; }
[data-theme="dark"] code            { background: #273549 !important; color: #a5b4fc !important; }
[data-theme="dark"] .form-control   { background: var(--input-bg); color: var(--text); border-color: var(--border); }
[data-theme="dark"] .form-control::placeholder { color: var(--muted); }
[data-theme="dark"] .btn-secondary  { background: #273549; color: var(--text-2); border-color: var(--border); }
[data-theme="dark"] .btn-secondary:hover { background: #334155; }
[data-theme="dark"] .page-btn       { background: #273549; color: var(--text-2); border-color: var(--border); }
[data-theme="dark"] .icon-btn       { background: #1e293b; border-color: var(--border); color: var(--text-2); }
[data-theme="dark"] .icon-btn:hover { background: var(--primary-light); color: var(--primary); }
[data-theme="dark"] .modal          { background: #1e293b; }
[data-theme="dark"] .modal-close    { background: #273549; border-color: var(--border); }
[data-theme="dark"] .topbar         { background: #1e293b; border-color: var(--border); }
[data-theme="dark"] th              { color: var(--muted); border-color: var(--border); }
[data-theme="dark"] td              { border-color: var(--border); }
[data-theme="dark"] .progress       { background: #334155; }
[data-theme="dark"] .badge-green    { background: #14532d; color: #86efac; }
[data-theme="dark"] .badge-red      { background: #450a0a; color: #fca5a5; }
[data-theme="dark"] .badge-orange   { background: #451a03; color: #fcd34d; }
[data-theme="dark"] .badge-blue     { background: #1e3a5f; color: #93c5fd; }
[data-theme="dark"] .badge-purple   { background: #2e1065; color: #c4b5fd; }
[data-theme="dark"] .badge-gray     { background: #1e293b; color: #94a3b8; }
[data-theme="dark"] .alert-success  { background: #14532d; color: #86efac; border-color: #16a34a44; }
[data-theme="dark"] .alert-danger   { background: #450a0a; color: #fca5a5; border-color: #ef444444; }
[data-theme="dark"] .alert-warning  { background: #451a03; color: #fcd34d; border-color: #f59e0b44; }
[data-theme="dark"] .ap-panel       { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .theme-toggle   { background: #273549; }
[data-theme="dark"] .theme-btn.active { background: #334155; }
[data-theme="dark"] .font-btn       { background: #273549; border-color: #334155; }
[data-theme="dark"] .font-btn:hover { border-color: var(--primary); }
[data-theme="dark"] .font-btn.active{ border-color: var(--primary); background: var(--primary-light); }

/* ============================================================
   THEMES — Dark Gradients
   ============================================================ */

/* Glassmorphism base for all dark themes */
[data-theme="dark"] .card, [data-theme="dark"] .modal,
[data-theme="nuit-indigo"] .card, [data-theme="nuit-indigo"] .modal,
[data-theme="ocean-profond"] .card, [data-theme="ocean-profond"] .modal,
[data-theme="foret-emeraude"] .card, [data-theme="foret-emeraude"] .modal,
[data-theme="flamme-noire"] .card, [data-theme="flamme-noire"] .modal,
[data-theme="ambre-nuit"] .card, [data-theme="ambre-nuit"] .modal,
[data-theme="ardoise"] .card, [data-theme="ardoise"] .modal {
  background: var(--card);
  backdrop-filter: blur(16px) saturate(180%);
  -webkit-backdrop-filter: blur(16px) saturate(180%);
  border: 1px solid var(--border);
}
[data-theme="dark"] .topbar,
[data-theme="nuit-indigo"] .topbar,
[data-theme="ocean-profond"] .topbar,
[data-theme="foret-emeraude"] .topbar,
[data-theme="flamme-noire"] .topbar,
[data-theme="ambre-nuit"] .topbar,
[data-theme="ardoise"] .topbar {
  background: var(--card);
  backdrop-filter: blur(16px) saturate(180%);
  -webkit-backdrop-filter: blur(16px) saturate(180%);
  border-bottom: 1px solid var(--border);
}
[data-theme="nuit-indigo"] .sidebar,
[data-theme="ocean-profond"] .sidebar,
[data-theme="foret-emeraude"] .sidebar,
[data-theme="flamme-noire"] .sidebar,
[data-theme="ambre-nuit"] .sidebar,
[data-theme="ardoise"] .sidebar {
  background: var(--sidebar-bg) !important;
  backdrop-filter: none;
}
[data-theme="dark"] .form-control,
[data-theme="nuit-indigo"] .form-control,
[data-theme="ocean-profond"] .form-control,
[data-theme="foret-emeraude"] .form-control,
[data-theme="flamme-noire"] .form-control,
[data-theme="ambre-nuit"] .form-control,
[data-theme="ardoise"] .form-control {
  background: var(--input-bg); color: var(--text); border-color: var(--border);
}
[data-theme="dark"] .form-control::placeholder,
[data-theme="nuit-indigo"] .form-control::placeholder,
[data-theme="ocean-profond"] .form-control::placeholder,
[data-theme="foret-emeraude"] .form-control::placeholder,
[data-theme="flamme-noire"] .form-control::placeholder,
[data-theme="ambre-nuit"] .form-control::placeholder,
[data-theme="ardoise"] .form-control::placeholder { color: var(--muted); }
[data-theme="dark"] .btn-secondary,
[data-theme="nuit-indigo"] .btn-secondary,
[data-theme="ocean-profond"] .btn-secondary,
[data-theme="foret-emeraude"] .btn-secondary,
[data-theme="flamme-noire"] .btn-secondary,
[data-theme="ambre-nuit"] .btn-secondary,
[data-theme="ardoise"] .btn-secondary {
  background: var(--input-bg); color: var(--text-2); border-color: var(--border);
}
[data-theme="dark"] .btn-secondary:hover,
[data-theme="nuit-indigo"] .btn-secondary:hover,
[data-theme="ocean-profond"] .btn-secondary:hover,
[data-theme="foret-emeraude"] .btn-secondary:hover,
[data-theme="flamme-noire"] .btn-secondary:hover,
[data-theme="ambre-nuit"] .btn-secondary:hover,
[data-theme="ardoise"] .btn-secondary:hover { background: var(--border); }
[data-theme="dark"] .icon-btn,
[data-theme="nuit-indigo"] .icon-btn,
[data-theme="ocean-profond"] .icon-btn,
[data-theme="foret-emeraude"] .icon-btn,
[data-theme="flamme-noire"] .icon-btn,
[data-theme="ambre-nuit"] .icon-btn,
[data-theme="ardoise"] .icon-btn { background: var(--input-bg); border-color: var(--border); color: var(--text-2); }
[data-theme="dark"] .icon-btn:hover,
[data-theme="nuit-indigo"] .icon-btn:hover,
[data-theme="ocean-profond"] .icon-btn:hover,
[data-theme="foret-emeraude"] .icon-btn:hover,
[data-theme="flamme-noire"] .icon-btn:hover,
[data-theme="ambre-nuit"] .icon-btn:hover,
[data-theme="ardoise"] .icon-btn:hover { background: var(--primary-light); color: var(--primary); }
[data-theme="dark"] .modal-close,
[data-theme="nuit-indigo"] .modal-close,
[data-theme="ocean-profond"] .modal-close,
[data-theme="foret-emeraude"] .modal-close,
[data-theme="flamme-noire"] .modal-close,
[data-theme="ambre-nuit"] .modal-close,
[data-theme="ardoise"] .modal-close { background: var(--input-bg); border-color: var(--border); }
[data-theme="dark"] .page-btn,
[data-theme="nuit-indigo"] .page-btn,
[data-theme="ocean-profond"] .page-btn,
[data-theme="foret-emeraude"] .page-btn,
[data-theme="flamme-noire"] .page-btn,
[data-theme="ambre-nuit"] .page-btn,
[data-theme="ardoise"] .page-btn { background: var(--input-bg); color: var(--text-2); border-color: var(--border); }
[data-theme="dark"] th, [data-theme="nuit-indigo"] th, [data-theme="ocean-profond"] th, [data-theme="foret-emeraude"] th, [data-theme="flamme-noire"] th, [data-theme="ambre-nuit"] th, [data-theme="ardoise"] th { color: var(--muted); border-color: var(--border); }
[data-theme="dark"] td, [data-theme="nuit-indigo"] td, [data-theme="ocean-profond"] td, [data-theme="foret-emeraude"] td, [data-theme="flamme-noire"] td, [data-theme="ambre-nuit"] td, [data-theme="ardoise"] td { border-color: var(--border); }
[data-theme="dark"] tr:hover td, [data-theme="nuit-indigo"] tr:hover td, [data-theme="ocean-profond"] tr:hover td, [data-theme="foret-emeraude"] tr:hover td, [data-theme="flamme-noire"] tr:hover td, [data-theme="ambre-nuit"] tr:hover td, [data-theme="ardoise"] tr:hover td { background: var(--input-bg) !important; }
[data-theme="dark"] code, [data-theme="nuit-indigo"] code, [data-theme="ocean-profond"] code, [data-theme="foret-emeraude"] code, [data-theme="flamme-noire"] code, [data-theme="ambre-nuit"] code, [data-theme="ardoise"] code { background: var(--input-bg) !important; color: #a5b4fc !important; }
[data-theme="dark"] .progress, [data-theme="nuit-indigo"] .progress, [data-theme="ocean-profond"] .progress, [data-theme="foret-emeraude"] .progress, [data-theme="flamme-noire"] .progress, [data-theme="ambre-nuit"] .progress, [data-theme="ardoise"] .progress { background: var(--border); }
[data-theme="dark"] .badge-green, [data-theme="nuit-indigo"] .badge-green, [data-theme="ocean-profond"] .badge-green, [data-theme="foret-emeraude"] .badge-green, [data-theme="flamme-noire"] .badge-green, [data-theme="ambre-nuit"] .badge-green, [data-theme="ardoise"] .badge-green { background: #14532d; color: #86efac; }
[data-theme="dark"] .badge-red, [data-theme="nuit-indigo"] .badge-red, [data-theme="ocean-profond"] .badge-red, [data-theme="foret-emeraude"] .badge-red, [data-theme="flamme-noire"] .badge-red, [data-theme="ambre-nuit"] .badge-red, [data-theme="ardoise"] .badge-red { background: #450a0a; color: #fca5a5; }
[data-theme="dark"] .badge-orange, [data-theme="nuit-indigo"] .badge-orange, [data-theme="ocean-profond"] .badge-orange, [data-theme="foret-emeraude"] .badge-orange, [data-theme="flamme-noire"] .badge-orange, [data-theme="ambre-nuit"] .badge-orange, [data-theme="ardoise"] .badge-orange { background: #451a03; color: #fcd34d; }
[data-theme="dark"] .badge-blue, [data-theme="nuit-indigo"] .badge-blue, [data-theme="ocean-profond"] .badge-blue, [data-theme="foret-emeraude"] .badge-blue, [data-theme="flamme-noire"] .badge-blue, [data-theme="ambre-nuit"] .badge-blue, [data-theme="ardoise"] .badge-blue { background: #1e3a5f; color: #93c5fd; }
[data-theme="dark"] .badge-purple, [data-theme="nuit-indigo"] .badge-purple, [data-theme="ocean-profond"] .badge-purple, [data-theme="foret-emeraude"] .badge-purple, [data-theme="flamme-noire"] .badge-purple, [data-theme="ambre-nuit"] .badge-purple, [data-theme="ardoise"] .badge-purple { background: #2e1065; color: #c4b5fd; }
[data-theme="dark"] .badge-gray, [data-theme="nuit-indigo"] .badge-gray, [data-theme="ocean-profond"] .badge-gray, [data-theme="foret-emeraude"] .badge-gray, [data-theme="flamme-noire"] .badge-gray, [data-theme="ambre-nuit"] .badge-gray, [data-theme="ardoise"] .badge-gray { background: rgba(255,255,255,.05); color: var(--text-2); }
[data-theme="dark"] .alert-success, [data-theme="nuit-indigo"] .alert-success, [data-theme="ocean-profond"] .alert-success, [data-theme="foret-emeraude"] .alert-success, [data-theme="flamme-noire"] .alert-success, [data-theme="ambre-nuit"] .alert-success, [data-theme="ardoise"] .alert-success { background: rgba(20,83,45,.7); color: #86efac; border-color: #16a34a44; }
[data-theme="dark"] .alert-danger, [data-theme="nuit-indigo"] .alert-danger, [data-theme="ocean-profond"] .alert-danger, [data-theme="foret-emeraude"] .alert-danger, [data-theme="flamme-noire"] .alert-danger, [data-theme="ambre-nuit"] .alert-danger, [data-theme="ardoise"] .alert-danger { background: rgba(69,10,10,.7); color: #fca5a5; border-color: #ef444444; }
[data-theme="dark"] .alert-warning, [data-theme="nuit-indigo"] .alert-warning, [data-theme="ocean-profond"] .alert-warning, [data-theme="foret-emeraude"] .alert-warning, [data-theme="flamme-noire"] .alert-warning, [data-theme="ambre-nuit"] .alert-warning, [data-theme="ardoise"] .alert-warning { background: rgba(69,26,3,.7); color: #fcd34d; border-color: #f59e0b44; }
[data-theme="dark"] .ap-panel, [data-theme="nuit-indigo"] .ap-panel, [data-theme="ocean-profond"] .ap-panel, [data-theme="foret-emeraude"] .ap-panel, [data-theme="flamme-noire"] .ap-panel, [data-theme="ambre-nuit"] .ap-panel, [data-theme="ardoise"] .ap-panel { background: var(--card); border-color: var(--border); }
[data-theme="dark"] .theme-toggle, [data-theme="nuit-indigo"] .theme-toggle, [data-theme="ocean-profond"] .theme-toggle, [data-theme="foret-emeraude"] .theme-toggle, [data-theme="flamme-noire"] .theme-toggle, [data-theme="ambre-nuit"] .theme-toggle, [data-theme="ardoise"] .theme-toggle { background: var(--input-bg); }
[data-theme="dark"] .font-btn, [data-theme="nuit-indigo"] .font-btn, [data-theme="ocean-profond"] .font-btn, [data-theme="foret-emeraude"] .font-btn, [data-theme="flamme-noire"] .font-btn, [data-theme="ambre-nuit"] .font-btn, [data-theme="ardoise"] .font-btn { background: var(--input-bg); border-color: var(--border); }
[data-theme="dark"] .font-btn:hover, [data-theme="nuit-indigo"] .font-btn:hover, [data-theme="ocean-profond"] .font-btn:hover, [data-theme="foret-emeraude"] .font-btn:hover, [data-theme="flamme-noire"] .font-btn:hover, [data-theme="ambre-nuit"] .font-btn:hover, [data-theme="ardoise"] .font-btn:hover { border-color: var(--primary); }
[data-theme="dark"] .font-btn.active, [data-theme="nuit-indigo"] .font-btn.active, [data-theme="ocean-profond"] .font-btn.active, [data-theme="foret-emeraude"] .font-btn.active, [data-theme="flamme-noire"] .font-btn.active, [data-theme="ambre-nuit"] .font-btn.active, [data-theme="ardoise"] .font-btn.active { border-color: var(--primary); background: var(--primary-light); }

/* --- Nuit Indigo --- */
[data-theme="nuit-indigo"] {
  --primary: #818cf8; --primary-light: rgba(129,140,248,.12); --primary-mid: rgba(129,140,248,.25);
  --dark: #0c0a1d;
  --bg: #0c0a1d; --card: rgba(30,27,56,.65); --text: #e8e5f5; --text-2: #a5a0c8; --muted: #6b6591;
  --border: rgba(129,140,248,.15); --input-bg: rgba(30,27,56,.7);
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3); --shadow-lg: 0 8px 32px rgba(0,0,0,.5);
  --sidebar-bg: linear-gradient(180deg, #0c0a1d 0%, #1a1640 100%);
  --main-bg: linear-gradient(135deg, #0c0a1d 0%, #1a1640 40%, #2d1b69 100%);
}
[data-theme="nuit-indigo"] .sidebar { background: linear-gradient(180deg, #0c0a1d 0%, #1a1640 100%) !important; }
[data-theme="nuit-indigo"] .main { background: linear-gradient(135deg, #0c0a1d 0%, #1a1640 40%, #2d1b69 100%); }

/* --- Océan Profond --- */
[data-theme="ocean-profond"] {
  --primary: #38bdf8; --primary-light: rgba(56,189,248,.12); --primary-mid: rgba(56,189,248,.25);
  --dark: #021a2b;
  --bg: #021a2b; --card: rgba(8,47,73,.65); --text: #e0f2fe; --text-2: #7cc5ee; --muted: #4a8fad;
  --border: rgba(56,189,248,.15); --input-bg: rgba(8,47,73,.7);
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3); --shadow-lg: 0 8px 32px rgba(0,0,0,.5);
  --sidebar-bg: linear-gradient(180deg, #021a2b 0%, #063b5a 100%);
  --main-bg: linear-gradient(135deg, #021a2b 0%, #063b5a 40%, #0c4a6e 100%);
}
[data-theme="ocean-profond"] .sidebar { background: linear-gradient(180deg, #021a2b 0%, #063b5a 100%) !important; }
[data-theme="ocean-profond"] .main { background: linear-gradient(135deg, #021a2b 0%, #063b5a 40%, #0c4a6e 100%); }

/* --- Forêt Émeraude --- */
[data-theme="foret-emeraude"] {
  --primary: #34d399; --primary-light: rgba(52,211,153,.12); --primary-mid: rgba(52,211,153,.25);
  --dark: #021a0f;
  --bg: #021a0f; --card: rgba(6,50,34,.65); --text: #d1fae5; --text-2: #6ee7b7; --muted: #3d8a63;
  --border: rgba(52,211,153,.15); --input-bg: rgba(6,50,34,.7);
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3); --shadow-lg: 0 8px 32px rgba(0,0,0,.5);
  --sidebar-bg: linear-gradient(180deg, #021a0f 0%, #064e3b 100%);
  --main-bg: linear-gradient(135deg, #021a0f 0%, #064e3b 40%, #0d6b4c 100%);
}
[data-theme="foret-emeraude"] .sidebar { background: linear-gradient(180deg, #021a0f 0%, #064e3b 100%) !important; }
[data-theme="foret-emeraude"] .main { background: linear-gradient(135deg, #021a0f 0%, #064e3b 40%, #0d6b4c 100%); }

/* --- Flamme Noire --- */
[data-theme="flamme-noire"] {
  --primary: #f87171; --primary-light: rgba(248,113,113,.12); --primary-mid: rgba(248,113,113,.25);
  --dark: #1a0505;
  --bg: #1a0505; --card: rgba(69,10,10,.65); --text: #fee2e2; --text-2: #fca5a5; --muted: #b45454;
  --border: rgba(248,113,113,.15); --input-bg: rgba(69,10,10,.7);
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3); --shadow-lg: 0 8px 32px rgba(0,0,0,.5);
  --sidebar-bg: linear-gradient(180deg, #1a0505 0%, #4a0e0e 100%);
  --main-bg: linear-gradient(135deg, #1a0505 0%, #4a0e0e 40%, #7f1d1d 100%);
}
[data-theme="flamme-noire"] .sidebar { background: linear-gradient(180deg, #1a0505 0%, #4a0e0e 100%) !important; }
[data-theme="flamme-noire"] .main { background: linear-gradient(135deg, #1a0505 0%, #4a0e0e 40%, #7f1d1d 100%); }

/* --- Ambre Nuit --- */
[data-theme="ambre-nuit"] {
  --primary: #fbbf24; --primary-light: rgba(251,191,36,.12); --primary-mid: rgba(251,191,36,.25);
  --dark: #1a0f00;
  --bg: #1a0f00; --card: rgba(69,26,3,.65); --text: #fef3c7; --text-2: #fcd34d; --muted: #a17a2a;
  --border: rgba(251,191,36,.15); --input-bg: rgba(69,26,3,.7);
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3); --shadow-lg: 0 8px 32px rgba(0,0,0,.5);
  --sidebar-bg: linear-gradient(180deg, #1a0f00 0%, #451a03 100%);
  --main-bg: linear-gradient(135deg, #1a0f00 0%, #451a03 40%, #78350f 100%);
}
[data-theme="ambre-nuit"] .sidebar { background: linear-gradient(180deg, #1a0f00 0%, #451a03 100%) !important; }
[data-theme="ambre-nuit"] .main { background: linear-gradient(135deg, #1a0f00 0%, #451a03 40%, #78350f 100%); }

/* --- Ardoise --- */
[data-theme="ardoise"] {
  --primary: #94a3b8; --primary-light: rgba(148,163,184,.12); --primary-mid: rgba(148,163,184,.25);
  --dark: #0f172a;
  --bg: #0f172a; --card: rgba(30,41,59,.65); --text: #e2e8f0; --text-2: #94a3b8; --muted: #64748b;
  --border: rgba(148,163,184,.15); --input-bg: rgba(30,41,59,.7);
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3); --shadow-lg: 0 8px 32px rgba(0,0,0,.5);
  --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
  --main-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%);
}
[data-theme="ardoise"] .sidebar { background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%) !important; }
[data-theme="ardoise"] .main { background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%); }

/* ============================================================
   FONT VARIANTS
   ============================================================ */
[data-font="manrope"]    { --font-body: 'Manrope', sans-serif; }

[data-heading="manrope"]    { --font-heading: 'Manrope', sans-serif; }

/* ============================================================
   RESET & BASE
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font-body);
  background: var(--bg);
  color: var(--text);
  display: flex;
  min-height: 100vh;
  transition: background .25s, color .25s;
}

/* ============================================================
   SIDEBAR
   ============================================================ */
.sidebar {
  width: var(--sidebar-w); flex-shrink: 0;
  background: var(--dark);
  position: fixed; top: 0; left: 0; height: 100vh;
  display: flex; flex-direction: column;
  z-index: 100; overflow-y: auto;
  transition: transform .3s ease;
}
.sidebar-logo {
  padding: 24px 20px 20px;
  border-bottom: 1px solid rgba(255,255,255,.07);
  display: flex; align-items: center; gap: 12px;
}
.sidebar-logo img { height: 38px; object-fit: contain; }
.sidebar-logo-icon {
  width: 38px; height: 38px; border-radius: 10px;
  background: var(--primary);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sidebar-logo-icon svg { width: 20px; height: 20px; color: white; }
.sidebar-logo-name { font-family: var(--font-heading); font-weight: 800; font-size: 16px; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-logo-ver  { font-size: 11px; color: rgba(255,255,255,.3); margin-top: 1px; }
.sidebar-nav { flex: 1; padding: 16px 12px; }
.nav-section { margin-bottom: 8px; }
.nav-section-label { font-size: 10px; font-weight: 600; letter-spacing: 1px; color: rgba(255,255,255,.4); text-transform: uppercase; padding: 8px 8px 4px; }
.nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 10px;
  color: rgba(255,255,255,.75); font-size: 14px; font-weight: 500;
  text-decoration: none; transition: all .15s; margin-bottom: 2px;
  font-family: var(--font-body);
}
.nav-item:hover  { color: white; background: rgba(255,255,255,.07); }
.nav-item.active { color: white; background: var(--primary); }
.nav-item svg    { width: 16px; height: 16px; opacity: .7; flex-shrink: 0; }
.nav-emoji       { font-size: 18px; line-height: 1; flex-shrink: 0; }
.nav-badge { margin-left: auto; background: var(--danger); color: white; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 20px; }
.sidebar-bottom { padding: 12px; border-top: 1px solid rgba(255,255,255,.07); }
.user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,.05); }
.user-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-family: var(--font-heading); font-weight: 700; font-size: 13px; color: white; flex-shrink: 0; }
.user-info  { flex: 1; min-width: 0; }
.user-name  { font-size: 13px; font-weight: 600; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role  { font-size: 11px; color: rgba(255,255,255,.55); }
.logout-btn { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 7px; color: rgba(255,255,255,.4); text-decoration: none; flex-shrink: 0; transition: all .15s; }
.logout-btn:hover { color: var(--danger); background: rgba(239,68,68,.1); }

/* ============================================================
   MAIN
   ============================================================ */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-width: 0; }
.topbar {
  background: var(--card); border-bottom: 1px solid var(--border);
  padding: 0 28px; height: 64px;
  display: flex; align-items: center; gap: 16px;
  position: sticky; top: 0; z-index: 50;
  transition: background .25s, border-color .25s;
}
.topbar-title   { font-family: var(--font-heading); font-weight: 700; font-size: 18px; color: var(--text); flex: 1; }
.topbar-actions { display: flex; align-items: center; gap: 8px; }
.icon-btn {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-2); cursor: pointer; position: relative;
  transition: all .15s; text-decoration: none;
  border: 1px solid var(--border); background: var(--bg);
}
.icon-btn:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }
.icon-btn svg   { width: 18px; height: 18px; }
.notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--danger); border: 2px solid var(--card); }
.content { padding: 28px; flex: 1; }

/* ============================================================
   APPEARANCE PANEL
   ============================================================ */
.ap-wrapper { position: relative; }
.ap-panel {
  display: none; position: absolute; top: calc(100% + 8px); right: 0;
  width: 290px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 18px;
  box-shadow: var(--shadow-lg);
  padding: 20px;
  z-index: 9999;
  animation: apIn .18s ease both;
}
.ap-panel.open { display: block; }
@keyframes apIn { from { opacity:0; transform: translateY(-6px) scale(.97); } to { opacity:1; transform:none; } }
.ap-title { font-family: var(--font-heading); font-weight: 700; font-size: 14px; color: var(--text); margin-bottom: 14px; display:flex; align-items:center; gap:8px; }
.ap-sep   { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); margin-bottom: 8px; margin-top: 14px; }

/* Theme toggle */
.theme-toggle { display: flex; gap: 4px; background: var(--bg); border-radius: 10px; padding: 4px; }
.theme-btn {
  flex: 1; padding: 7px 4px; border-radius: 8px; border: none; cursor: pointer;
  font-family: var(--font-body); font-size: 12px; font-weight: 600;
  color: var(--muted); background: transparent;
  display: flex; align-items: center; justify-content: center; gap: 5px;
  transition: all .15s;
}
.theme-btn.active { background: var(--card); color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,.12); }

/* Font grid */
.font-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.font-btn {
  padding: 8px 10px; border-radius: 8px; border: 1.5px solid var(--border);
  cursor: pointer; background: var(--bg); text-align: left; transition: all .15s;
}
.font-btn:hover  { border-color: var(--primary); }
.font-btn.active { border-color: var(--primary); background: var(--primary-light); }
.font-btn .fn    { font-size: 13px; font-weight: 600; color: var(--text); display: block; line-height: 1.2; }
.font-btn .fs    { font-size: 10px; color: var(--muted); }

/* Size slider */
.size-row { display: flex; align-items: center; gap: 8px; }
input[type=range] { flex: 1; accent-color: var(--primary); cursor: pointer; }
#size-label { font-size: 12px; color: var(--primary); font-weight: 700; min-width: 26px; text-align: right; }

/* Reset btn */
.ap-reset { margin-top: 14px; width: 100%; padding: 8px; border-radius: 8px; border: 1px solid var(--border); background: transparent; cursor: pointer; font-size: 12px; color: var(--muted); font-family: var(--font-body); transition: all .15s; }
.ap-reset:hover { color: var(--danger); border-color: var(--danger); }

/* ============================================================
   CARDS
   ============================================================ */
.card        { background: var(--card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); transition: background .25s, border-color .25s; }
.card-header { padding: 20px 24px 0; display: flex; align-items: center; justify-content: space-between; }
.card-title  { font-family: var(--font-heading); font-weight: 700; font-size: 15px; color: var(--text); }
.card-body   { padding: 20px 24px; }
.stats-grid  { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card   { background: var(--card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); padding: 20px 24px; display: flex; align-items: center; gap: 16px; transition: background .25s; }
.stat-icon   { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon svg { width: 22px; height: 22px; }
.stat-val    { font-family: var(--font-heading); font-weight: 800; font-size: 24px; color: var(--text); line-height: 1; }
.stat-label  { font-size: 13px; color: var(--text-2); margin-top: 4px; }
.stat-badge  { margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }

/* TABLE */
table { width: 100%; border-collapse: collapse; }
th    { text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); padding: 12px 16px; border-bottom: 1px solid var(--border); }
td    { padding: 13px 16px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text); transition: background .15s; }
tr:last-child td { border-bottom: none; }
tr:hover td      { background: var(--bg); }

/* BADGES */
.badge        { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-green  { background: #dcfce7; color: #166534; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-orange { background: #fef3c7; color: #92400e; }
.badge-blue   { background: #dbeafe; color: #1e40af; }
.badge-purple { background: #ede9fe; color: #5b21b6; }
.badge-gray   { background: #f1f5f9; color: #475569; }

/* BUTTONS */
.btn           { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: var(--font-body); cursor: pointer; transition: all .15s; border: none; text-decoration: none; }
.btn-primary   { background: var(--primary); color: white; box-shadow: 0 2px 10px color-mix(in srgb, var(--primary) 30%, transparent); }
.btn-primary:hover   { filter: brightness(1.08); transform: translateY(-1px); }
.btn-secondary { background: var(--bg); color: var(--text-2); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--border); }
.btn-danger    { background: #fee2e2; color: var(--danger); }
.btn-danger:hover    { background: var(--danger); color: white; }
.btn-sm  { padding: 6px 12px; font-size: 13px; }
.btn svg { width: 15px; height: 15px; }

/* FORM */
.form-group    { margin-bottom: 18px; }
.form-label    { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.form-control  { width: 100%; border: 1px solid var(--border); border-radius: 10px; padding: 10px 14px; font-family: var(--font-body); font-size: 14px; color: var(--text); background: var(--input-bg); outline: none; transition: border-color .15s, box-shadow .15s, background .25s; }
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
.form-select   { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px; padding-right: 36px; }
.form-row      { display: grid; gap: 16px; }
.form-row-2    { grid-template-columns: 1fr 1fr; }
.form-row-3    { grid-template-columns: 1fr 1fr 1fr; }

/* MODAL */
.modal-bg      { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center; padding: 20px; }
.modal-bg.open { display: flex; }
.modal         { background: var(--card); border-radius: 20px; width: 100%; max-width: 560px; box-shadow: var(--shadow-lg); animation: modalIn .2s ease both; max-height: 90vh; overflow-y: auto; transition: background .25s; }
@keyframes modalIn { from { opacity:0; transform: scale(.95) translateY(10px); } to { opacity:1; transform: none; } }
.modal-header  { padding: 24px 24px 0; display: flex; align-items: center; justify-content: space-between; }
.modal-title   { font-family: var(--font-heading); font-weight: 700; font-size: 18px; color: var(--text); }
.modal-close   { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--muted); border: 1px solid var(--border); background: none; transition: all .15s; }
.modal-close:hover { color: var(--danger); border-color: var(--danger); }
.modal-body    { padding: 20px 24px; }
.modal-footer  { padding: 0 24px 24px; display: flex; justify-content: flex-end; gap: 10px; }

/* ALERT */
.alert         { padding: 12px 16px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert svg     { width: 16px; height: 16px; flex-shrink: 0; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

/* SKIP LINK */
.skip-link { position: absolute; top: -100px; left: 16px; background: var(--primary); color: white; padding: 8px 16px; border-radius: 8px; z-index: 10000; font-weight: 600; font-size: 14px; text-decoration: none; }
.skip-link:focus { top: 8px; }

/* MISC */
.search-bar     { position: relative; }
.search-bar svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: var(--muted); pointer-events: none; }
.search-bar input { padding-left: 36px; }
.pagination { display: flex; align-items: center; gap: 4px; padding: 16px 24px; border-top: 1px solid var(--border); }
.page-btn   { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--bg); color: var(--text-2); text-decoration: none; transition: all .15s; }
.page-btn:hover, .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-info  { flex: 1; font-size: 13px; color: var(--muted); }
.progress      { height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 99px; transition: width .4s ease; }
.empty-state   { text-align: center; padding: 60px 20px; }
.empty-state svg { width: 48px; height: 48px; color: var(--muted); margin-bottom: 12px; }
.empty-state h4  { font-family: var(--font-heading); font-size: 16px; color: var(--text); margin-bottom: 8px; }
.empty-state p   { font-size: 14px; color: var(--muted); }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: none; }
  .main { margin-left: 0; }
  .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
}

/* ============================================================
   PRINT — masquer les éléments d'interface
   ============================================================ */
@media print {
  .sidebar, .topbar-actions, .ap-wrapper, .btn, .nav-badge,
  .icon-btn, .notif-dot, .search-bar, .pagination,
  .modal-bg, .alert, button, .no-print { display: none !important; }
  .sidebar { display: none !important; }
  .main { margin-left: 0 !important; }
  .topbar { position: static !important; border: none !important; padding: 0 !important; height: auto !important; box-shadow: none !important; background: white !important; }
  .topbar-title { color: #000 !important; font-size: 18px !important; }
  body { background: white !important; color: #000 !important; font-size: 11px !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
  table { font-size: 10px !important; }
  th { background: #f0f0f0 !important; color: #333 !important; }
  td { color: #000 !important; border-color: #ccc !important; }
  .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
  .badge { border: 1px solid #999 !important; }
  @page { margin: 15mm; size: A4; }
}
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<a href="#main-content" class="skip-link">Aller au contenu principal</a>
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu principal">
  <div class="sidebar-logo">
    <?php if (!empty($ent['logo']) && file_exists(dirname(__DIR__).'/uploads/logos/'.$ent['logo'])): ?>
      <img src="<?= BASE_URL ?>/uploads/logos/<?= $ent['logo'] ?>" alt="Logo">
    <?php else: ?>
      <div class="sidebar-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
    <?php endif; ?>
    <div>
      <div class="sidebar-logo-name"><?= htmlspecialchars($ent['nom']) ?></div>
      <div class="sidebar-logo-ver">StockPro v<?= APP_VERSION ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-label">🏠 Principal</div>
      <a href="<?= BASE_URL ?>/pages/dashboard.php" class="nav-item <?= ($page_id??'')=='dashboard'?'active':'' ?>">
        <span class="nav-emoji">📊</span>
        Tableau de bord
      </a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">📦 Stock</div>
      <a href="<?= BASE_URL ?>/pages/produits.php" class="nav-item <?= ($page_id??'')=='produits'?'active':'' ?>">
        <span class="nav-emoji">📦</span>
        Produits
      </a>
      <a href="<?= BASE_URL ?>/pages/mouvements.php" class="nav-item <?= ($page_id??'')=='mouvements'?'active':'' ?>">
        <span class="nav-emoji">🔄</span>
        Mouvements
        <?php if ($alertes_count > 0): ?><span class="nav-badge"><?= $alertes_count ?></span><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/pages/categories.php" class="nav-item <?= ($page_id??'')=='categories'?'active':'' ?>">
        <span class="nav-emoji">🏷️</span>
        Catégories
      </a>
      <a href="<?= BASE_URL ?>/pages/fournisseurs.php" class="nav-item <?= ($page_id??'')=='fournisseurs'?'active':'' ?>">
        <span class="nav-emoji">🚚</span>
        Fournisseurs
      </a>
      <?php if (canDo('mouvement_ajustement')): ?>
      <a href="<?= BASE_URL ?>/pages/inventaire.php" class="nav-item <?= ($page_id??'')=='inventaire'?'active':'' ?>">
        <span class="nav-emoji">✅</span>
        Inventaire
      </a>
      <?php endif; ?>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">🛒 Achats & Ventes</div>
      <a href="<?= BASE_URL ?>/pages/achats.php" class="nav-item <?= ($page_id??'')=='achats'?'active':'' ?>">
        <span class="nav-emoji">🛍️</span>
        Achats
      </a>
      <a href="<?= BASE_URL ?>/pages/clients.php" class="nav-item <?= ($page_id??'')=='clients'?'active':'' ?>">
        <span class="nav-emoji">👥</span>
        Clients
      </a>
    </div>
    <?php if (canDo('rapport_view')): ?>
    <div class="nav-section">
      <div class="nav-section-label">📊 Analyse</div>
      <a href="<?= BASE_URL ?>/pages/rapports.php" class="nav-item <?= ($page_id??'')=='rapports'?'active':'' ?>">
        <span class="nav-emoji">📈</span>
        Rapports
      </a>
      <a href="<?= BASE_URL ?>/pages/exports.php" class="nav-item <?= ($page_id??'')=='exports'?'active':'' ?>">
        <span class="nav-emoji">📥</span>
        Exports
      </a>
    </div>
    <?php endif; ?>
    <?php if (in_array($user['role'], ['super_admin','admin'])): ?>
    <div class="nav-section">
      <div class="nav-section-label">🔒 Administration</div>
      <a href="<?= BASE_URL ?>/pages/utilisateurs.php" class="nav-item <?= ($page_id??'')=='utilisateurs'?'active':'' ?>">
        <span class="nav-emoji">👥</span>
        Utilisateurs
      </a>
      <a href="<?= BASE_URL ?>/pages/parametres.php" class="nav-item <?= ($page_id??'')=='parametres'?'active':'' ?>">
        <span class="nav-emoji">⚙️</span>
        Paramètres
      </a>
    </div>
    <?php endif; ?>
  </nav>

  <div class="sidebar-bottom">
    <div class="user-card">
      <a href="<?= BASE_URL ?>/pages/profil.php" style="display:flex;align-items:center;gap:10px;flex:1;text-decoration:none">
        <div class="user-avatar"><?= mb_strtoupper(mb_substr($user['prenom'],0,1,'UTF-8').mb_substr($user['nom'],0,1,'UTF-8'),'UTF-8') ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></div>
          <div class="user-role"><?= ucfirst(str_replace('_',' ',$user['role'])) ?></div>
        </div>
      </a>
      <a href="<?= BASE_URL ?>/logout.php" class="logout-btn" title="Déconnexion">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">
  <header class="topbar">
    <div class="topbar-title"><?= $page_title ?? '' ?></div>
    <div class="topbar-actions">

      <!-- Alertes -->
      <a href="<?= BASE_URL ?>/pages/mouvements.php" class="icon-btn" title="Alertes stock">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($alertes_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </a>

      <!-- ===== APPARENCE PANEL ===== -->
      <div class="ap-wrapper">
        <button class="icon-btn" id="ap-toggle" title="Apparence" onclick="toggleApPanel(event)">
          <!-- palette icon -->
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/>
            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>
          </svg>
        </button>

        <div class="ap-panel" id="ap-panel">
          <div class="ap-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
            Apparence
          </div>

          <!-- THÈME -->
          <div class="ap-sep" style="margin-top:0">🌓 Thème</div>
          <div class="theme-toggle">
            <button class="theme-btn" id="btn-light" onclick="setTheme('light')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
              Clair
            </button>
            <button class="theme-btn" id="btn-dark" onclick="setTheme('dark')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
              Nuit
            </button>
            <button class="theme-btn" id="btn-auto" onclick="setTheme('auto')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              Auto
            </button>
          </div>

          <!-- POLICE CORPS -->
          <div class="ap-sep">Aa Police du texte</div>
          <div class="font-grid">
            <button class="font-btn" onclick="setFont('manrope')" id="font-manrope">
              <span class="fn" style="font-family:'Manrope',sans-serif">Manrope</span>
              <span class="fs">Moderne · géométrique</span>
            </button>
          </div>

          <!-- POLICE TITRES -->
          <div class="ap-sep">Ab Police des titres</div>
          <div class="font-grid">
            <button class="font-btn" onclick="setHeading('manrope')" id="heading-manrope">
              <span class="fn" style="font-family:'Manrope',sans-serif;font-weight:800">Manrope</span>
              <span class="fs">Audacieux · moderne</span>
            </button>
          </div>

          <!-- TAILLE -->
          <div class="ap-sep">📐 Taille du texte</div>
          <div class="size-row">
            <span style="font-size:10px;color:var(--muted)">A</span>
            <input type="range" id="font-size-range" min="12" max="18" step="1" value="14" oninput="setFontSize(this.value)">
            <span style="font-size:18px;color:var(--muted)">A</span>
            <span id="size-label">14px</span>
          </div>

          <button class="ap-reset" onclick="resetPrefs()">↺ Réinitialiser les préférences</button>
        </div>
      </div>
      <!-- FIN APPARENCE PANEL -->

      <!-- Paramètres -->
      <a href="<?= BASE_URL ?>/pages/parametres.php" class="icon-btn" title="Paramètres entreprise">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M4.93 19.07l1.41-1.41M19.07 19.07l-1.41-1.41M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
      </a>

    </div>
  </header>
  <main class="content" id="main-content" role="main">

<!-- ===== APPEARANCE JS ===== -->
<script>
const html = document.documentElement;

function setTheme(t) {
    localStorage.setItem('sp_theme', t);
    var theme = t;
    if (t === 'auto') theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    html.setAttribute('data-theme', theme);
    ['light','dark','auto'].forEach(v => {
        var b = document.getElementById('btn-' + v);
        if (b) b.classList.toggle('active', v === t);
    });
}

function setFont(f) {
    localStorage.setItem('sp_font', f);
    html.setAttribute('data-font', f);
    document.querySelectorAll('[id^="font-"]').forEach(b => {
        b.classList.toggle('active', b.id === 'font-' + f);
    });
}

function setHeading(h) {
    localStorage.setItem('sp_heading', h);
    html.setAttribute('data-heading', h);
    document.querySelectorAll('[id^="heading-"]').forEach(b => {
        b.classList.toggle('active', b.id === 'heading-' + h);
    });
}

function setFontSize(size) {
    size = parseInt(size);
    localStorage.setItem('sp_size', size);
    html.style.fontSize = size + 'px';
    var lbl = document.getElementById('size-label');
    if (lbl) lbl.textContent = size + 'px';
    var rng = document.getElementById('font-size-range');
    if (rng) rng.value = size;
}

function resetPrefs() {
    ['sp_theme','sp_font','sp_heading','sp_size'].forEach(k => localStorage.removeItem(k));
    setTheme('light');
    setFont('manrope');
    setHeading('manrope');
    setFontSize(14);
}

function toggleApPanel(e) {
    e.stopPropagation();
    document.getElementById('ap-panel').classList.toggle('open');
}

document.addEventListener('click', function(e) {
    var wrapper = document.querySelector('.ap-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        var p = document.getElementById('ap-panel');
        if (p) p.classList.remove('open');
    }
});

// Init UI état des boutons au chargement DOM
document.addEventListener('DOMContentLoaded', function() {
    var savedTheme   = localStorage.getItem('sp_theme')   || 'light';
    var savedFont    = localStorage.getItem('sp_font')    || '<?= htmlspecialchars($ent['police'] ?? 'dmsans') ?>';
    var savedHeading = localStorage.getItem('sp_heading') || '<?= htmlspecialchars($ent['police_titres'] ?? 'syne') ?>';
    var savedSize    = parseInt(localStorage.getItem('sp_size') || '<?= htmlspecialchars($ent['taille_texte'] ?? '14') ?>');

    // Sync boutons thème
    ['light','dark','auto'].forEach(v => {
        var b = document.getElementById('btn-' + v);
        if (b) b.classList.toggle('active', v === savedTheme);
    });
    // Sync boutons police corps
    document.querySelectorAll('[id^="font-"]').forEach(b => {
        b.classList.toggle('active', b.id === 'font-' + savedFont);
    });
    // Sync boutons police titres
    document.querySelectorAll('[id^="heading-"]').forEach(b => {
        b.classList.toggle('active', b.id === 'heading-' + savedHeading);
    });
    // Sync slider taille
    var rng = document.getElementById('font-size-range');
    if (rng) rng.value = savedSize;
    var lbl = document.getElementById('size-label');
    if (lbl) lbl.textContent = savedSize + 'px';

    // Écouter changement système pour mode auto
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
        if (localStorage.getItem('sp_theme') === 'auto') {
            setTheme('auto');
        }
    });
});
</script>
