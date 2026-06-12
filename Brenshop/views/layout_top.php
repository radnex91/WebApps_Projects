<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= e($appSettings['app_name'] ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/fonts/fonts.css">
    <style>
        <?php
        $primaryColor = $appSettings['primary_color'] ?? '#1a4f8a';
        $sidebarBg = $appSettings['sidebar_bg'] ?? '#0f3060';
        $accent2Color = $appSettings['accent2_color'] ?? '#0ea87e';
        $currentTheme = $appSettings['theme'] ?? 'default';

        $themeVars = [
            'default'   => ['body' => '#f0f2f5', 'card' => '#ffffff', 'text' => '#1a2236', 'muted' => '#4b5671', 'border' => '#dde2ea', 'isLight' => true],
            'wallstreet'=> ['body' => '#0f0f14', 'card' => '#16161e', 'text' => '#d8d8e0', 'muted' => '#9090a0', 'border' => '#2a2a3a', 'isLight' => false],
            'cyberpunk' => ['body' => '#1a0a2a', 'card' => '#22103a', 'text' => '#e0d6f0', 'muted' => '#a890c0', 'border' => '#3a205a', 'isLight' => false],
            'aurora'    => ['body' => '#0a0a20', 'card' => '#121230', 'text' => '#c8d0f0', 'muted' => '#8890b0', 'border' => '#252560', 'isLight' => false],
            'executive' => ['body' => '#0f0f14', 'card' => '#16161e', 'text' => '#d0d0d8', 'muted' => '#9090a0', 'border' => '#2a2a3a', 'isLight' => false],
            'solar'     => ['body' => '#1a140a', 'card' => '#221c10', 'text' => '#e8d8c8', 'muted' => '#b89070', 'border' => '#3a2a18', 'isLight' => false],
            'ocean'     => ['body' => '#0a0f2a', 'card' => '#121a30', 'text' => '#b8c8e0', 'muted' => '#7890a8', 'border' => '#252f50', 'isLight' => false],
            'royal'     => ['body' => '#1a0a2a', 'card' => '#22103a', 'text' => '#d8c8e0', 'muted' => '#a080b0', 'border' => '#3a205a', 'isLight' => false],
            'emerald'   => ['body' => '#0a1a14', 'card' => '#121f18', 'text' => '#c8e0d0', 'muted' => '#80b098', 'border' => '#253a2a', 'isLight' => false],
        ];
        $tv = $themeVars[$currentTheme] ?? $themeVars['default'];
        $isLight = $tv['isLight'];
        ?>

        :root {
            --sidebar-w: 250px;
            --sidebar-bg: <?= e($sidebarBg) ?>;
            --sidebar-border: <?= $isLight ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.06)' ?>;
            --sidebar-text: <?= $isLight ? 'rgba(255,255,255,0.55)' : 'rgba(255,255,255,0.50)' ?>;
            --sidebar-active-bg: <?= e(hexToRgba($primaryColor, 0.15)) ?>;
            --sidebar-active: <?= e(lightenHex($primaryColor, 40)) ?>;
            --accent: <?= e($primaryColor) ?>;
            --accent2: <?= e($accent2Color) ?>;
            --accent3: #ED6C02;
            --danger: <?= $isLight ? '#D32F2F' : '#EF4444' ?>;
            --body-bg: <?= e($tv['body']) ?>;
            --card-bg: <?= e($tv['card']) ?>;
            --text: <?= e($tv['text']) ?>;
            --text-muted: <?= e($tv['muted']) ?>;
            --border: <?= e($tv['border']) ?>;
            --shadow: <?= $isLight ? '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04)' : '0 1px 3px rgba(0,0,0,0.4), 0 1px 2px rgba(0,0,0,0.3)' ?>;
            --shadow-md: <?= $isLight ? '0 4px 8px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04)' : '0 4px 6px rgba(0,0,0,0.45), 0 2px 4px rgba(0,0,0,0.3)' ?>;

            /* Bootstrap 5 overrides */
            --bs-body-color: var(--text);
            --bs-body-bg: var(--body-bg);
            --bs-border-color: var(--border);
            --bs-heading-color: var(--text);
            --bs-emphasis-color: var(--text);
            --bs-secondary-color: var(--text-muted);
            --bs-link-color: var(--accent);
            --bs-link-hover-color: <?= e(lightenHex($primaryColor, 20)) ?>;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--body-bg);
            color: var(--text);
            margin: 0;
            overflow-x: hidden;
        }

        /* ── SIDEBAR ── */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        #sidebar::-webkit-scrollbar { display: none; }

        .sidebar-brand {
            padding: 1.5rem 1.25rem 1rem;
            border-bottom: 1px solid var(--sidebar-border);
        }
        .sidebar-brand .brand-name {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: #fff;
            letter-spacing: -0.03em;
        }
        .sidebar-brand .brand-name span { color: var(--accent); }
        .sidebar-brand .brand-sub {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.3);
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .store-badge {
            margin: 0.75rem 1.25rem;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .store-badge .store-dot { width: 8px; height: 8px; background: var(--accent2); border-radius: 50%; flex-shrink: 0; }
        .store-badge .store-name { font-size: 0.78rem; color: rgba(255,255,255,0.7); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .nav-section-label {
            padding: 0.75rem 1.25rem 0.25rem;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.25);
            font-weight: 600;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 400;
            border-radius: 0;
            transition: all 0.15s;
            position: relative;
            margin: 1px 0.5rem;
            border-radius: 8px;
        }
        .sidebar-nav a i { font-size: 1rem; min-width: 1rem; }
        .sidebar-nav a.sub-link { padding-left: 2.5rem; font-size: 0.85rem; opacity: 0.8; }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,0.06); }
        .sidebar-nav a.active {
            color: var(--sidebar-active);
            background: var(--sidebar-active-bg);
            font-weight: 500;
        }
        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid var(--sidebar-border);
        }
        .user-mini {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #818CF8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        .user-info { min-width: 0; }
        .user-info .user-name { font-size: 0.8rem; color: rgba(255,255,255,0.8); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-info .user-role { font-size: 0.68rem; color: rgba(255,255,255,0.35); }

        /* ── MAIN ── */
        #main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: var(--shadow);
        }
        .topbar .page-title {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .topbar-actions { display: flex; align-items: center; gap: 0.75rem; }

        #toggle-sidebar {
            display: none;
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.4rem 0.6rem;
            cursor: pointer;
        }

        /* ── PAGE CONTENT ── */
        .page-content {
            padding: 1.5rem;
            flex: 1;
        }

        /* ── CARDS ── */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        .card-header-custom {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header-custom h6 {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            margin: 0;
            font-size: 0.95rem;
            color: var(--text);
        }

        /* ── STAT CARDS ── */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 80px; height: 80px;
            border-radius: 50%;
            transform: translate(20px, -20px);
            opacity: 0.08;
        }
        .stat-card.accent::after { background: var(--accent); }
        .stat-card.success::after { background: var(--accent2); }
        .stat-card.warning::after { background: var(--accent3); }
        .stat-card.danger::after  { background: var(--danger); }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        .stat-icon.accent  { background: rgba(99,102,241,0.12); color: var(--accent); }
        .stat-icon.success { background: rgba(16,185,129,0.12); color: var(--accent2); }
        .stat-icon.warning { background: rgba(245,158,11,0.12); color: var(--accent3); }
        .stat-icon.danger  { background: rgba(239,68,68,0.12);  color: var(--danger); }

        .stat-value {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .stat-label { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
        .stat-change { font-size: 0.75rem; margin-top: 0.5rem; }
        .stat-change.up   { color: var(--accent2); }
        .stat-change.down { color: var(--danger); }

        /* ── TABLE ── */
        .table { font-size: 0.875rem; color: var(--text); }
        .table th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 600; border-bottom-width: 1px; }
        .table td { vertical-align: middle; }

        /* ── BADGES ── */
        .badge-role-admin   { background: rgba(99,102,241,0.1);  color: var(--accent); }
        .badge-role-manager { background: rgba(245,158,11,0.1);  color: var(--accent3); }
        .badge-role-cashier { background: rgba(16,185,129,0.1);  color: var(--accent2); }

        /* ── TOAST PS5 STYLE ── */
        .toast-zone { position: fixed; bottom: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column-reverse; gap: 0.5rem; width: 360px; pointer-events: none; }
        .toast-item {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(30,30,38,0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            transform: translateX(120%);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease;
        }
        .toast-item.show { transform: translateX(0); opacity: 1; }
        .toast-item.hide { transform: translateX(120%); opacity: 0; }
        .toast-item .toast-icon-wrap {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .toast-item .toast-icon-wrap.success { background: rgba(34,197,94,0.15); }
        .toast-item .toast-icon-wrap.danger  { background: rgba(239,68,68,0.15); }
        .toast-item .toast-icon-wrap.warning { background: rgba(245,158,11,0.15); }
        .toast-item .toast-icon-wrap.info    { background: rgba(99,102,241,0.15); }
        .toast-item .toast-icon { font-size: 1.15rem; flex-shrink: 0; }
        .toast-item .toast-icon.success { color: #22C55E; }
        .toast-item .toast-icon.danger  { color: #EF4444; }
        .toast-item .toast-icon.warning { color: #F59E0B; }
        .toast-item .toast-icon.info    { color: #6366F1; }
        .toast-body { flex: 1; min-width: 0; }
        .toast-title { font-weight: 600; font-size: 0.82rem; color: #fff; margin-bottom: 1px; }
        .toast-msg { font-size: 0.76rem; color: rgba(255,255,255,0.65); line-height: 1.35; }
        .toast-close {
            flex-shrink: 0;
            background: rgba(255,255,255,0.07);
            border: none;
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
            cursor: pointer;
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }
        .toast-close:hover { background: rgba(255,255,255,0.15); color: #fff; }
        .toast-progress { position: absolute; bottom: 0; left: 12px; right: 12px; height: 2px; background: rgba(255,255,255,0.08); border-radius: 0 0 14px 14px; }
        .toast-progress-bar { height: 100%; border-radius: 2px; transition: width linear; }
        .toast-progress-bar.success { background: rgba(34,197,94,0.6); }
        .toast-progress-bar.danger  { background: rgba(239,68,68,0.6); }
        .toast-progress-bar.warning { background: rgba(245,158,11,0.6); }
        .toast-progress-bar.info    { background: rgba(99,102,241,0.6); }
        .toast-accent { display: none; }
        .toast-top { display: none; }
        .toast-counter {
            font-size: 0.72rem; font-weight: 700;
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.85);
            border-radius: 10px;
            padding: 1px 7px;
            margin-left: 0.5rem;
            flex-shrink: 0;
            animation: toastCounterPop 0.25s cubic-bezier(0.22,1,0.36,1);
        }
        @keyframes toastCounterPop {
            0%   { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }

        .flash-container { display: none; }

        /* ── BTN ── */
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent); border-color: var(--accent); filter: brightness(1.15); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .btn-primary:active { background: var(--accent); border-color: var(--accent); filter: brightness(0.9); color: #fff; }
        .btn-outline-secondary { background: transparent; border-color: var(--border); color: var(--text-muted); }
        .btn-outline-secondary:hover { background: var(--body-bg); border-color: var(--accent); color: var(--text); }
        .btn-outline-secondary:active { background: var(--border); border-color: var(--accent); color: var(--text); }

        /* ── FORM CONTROLS ── */
        .form-control, .form-select,
        input[type="text"], input[type="email"], input[type="password"],
        input[type="number"], input[type="search"], input[type="tel"],
        input[type="url"], input[type="date"], input[type="datetime-local"],
        input[type="time"], input[type="month"], input[type="week"],
        select, textarea {
            background: var(--body-bg);
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus,
        input:focus, select:focus, textarea:focus {
            background: var(--card-bg);
            color: var(--text);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
            outline: none;
        }
        .form-control::placeholder,
        input::placeholder, textarea::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }
        .form-control:disabled, .form-select:disabled,
        input:disabled, select:disabled, textarea:disabled {
            background: rgba(255,255,255,0.03);
            color: rgba(255,255,255,0.3);
            cursor: not-allowed;
        }
        .form-label, label {
            color: var(--text);
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
        }
        .input-group-text {
            background: var(--border);
            color: var(--text-muted);
            border: 1.5px solid var(--border);
        }
        select option, optgroup {
            background: var(--card-bg);
            color: var(--text);
        }
        select optgroup {
            font-weight: 600;
            color: var(--text-muted);
        }

        /* ── BOOTSTRAP COMPONENTS ── */
        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.25rem;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
        }
        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 0.75rem 1.25rem;
        }
        .modal-title { color: var(--text); font-weight: 600; }

        .alert {
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .alert-success { background: rgba(16,185,129,0.1); color: #6EE7B7; border-color: rgba(16,185,129,0.25); }
        .alert-danger  { background: rgba(239,68,68,0.1);  color: #FECACA; border-color: rgba(239,68,68,0.25); }
        .alert-warning { background: rgba(245,158,11,0.1);  color: #FDE68A; border-color: rgba(245,158,11,0.25); }
        .alert-info    { background: rgba(99,102,241,0.1);  color: #C7D2FE; border-color: rgba(99,102,241,0.25); }

        .badge { font-weight: 500; }

        .dropdown-menu {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
        }
        .dropdown-item {
            color: var(--text);
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            transition: all 0.1s;
        }
        .dropdown-item:hover { background: rgba(99,102,241,0.08); color: var(--text); }
        .dropdown-item:focus { background: rgba(99,102,241,0.12); }
        .dropdown-divider { border-color: var(--border); }

        .pagination {
            gap: 0.25rem;
        }
        .pagination .page-link {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.15s;
        }
        .pagination .page-link:hover { background: var(--body-bg); border-color: var(--accent); color: var(--accent); }
        .pagination .page-item.active .page-link {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 2px 6px rgba(99,102,241,0.3);
        }
        .pagination .page-item.disabled .page-link {
            background: transparent;
            color: var(--text-muted);
            opacity: 0.4;
            pointer-events: none;
        }
        .card-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--border);
        }

        .nav-tabs { border-color: var(--border); }
        .nav-tabs .nav-link {
            color: var(--text-muted);
            border: none;
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
        }
        .nav-tabs .nav-link:hover { color: var(--text); border-color: transparent; }
        .nav-tabs .nav-link.active {
            background: transparent;
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
        }

        .list-group-item {
            background: var(--card-bg);
            color: var(--text);
            border-color: var(--border);
        }

        .popover {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
        }
        .popover-body { color: var(--text); }

        /* ── RESPONSIVE ── */
        @media (max-width: 991px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); }
            #main-content { margin-left: 0; }
            #toggle-sidebar { display: flex; }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .sidebar-overlay.show { display: block; }
        }
    /* ── PRINT ── */
    @media print {
        @page {
            margin: 8mm;
            size: 80mm auto;
        }
        @page invoice {
            margin: 12mm;
            size: A4 portrait;
        }

        * {
            background: #fff !important;
            color: #000 !important;
            box-shadow: none !important;
            text-shadow: none !important;
        }

        body { font-size: 11px; line-height: 1.4; }

        #sidebar, .sidebar-overlay, .topbar, #toggle-sidebar,
        .btn, .no-print, .toast-zone, .sidebar-overlay { display: none !important; }

        #main-content { margin-left: 0 !important; width: 100% !important; }

        .card, .stat-card {
            border: 1px solid #ccc !important;
            page-break-inside: avoid;
        }

        .table { font-size: 10px; }
        .table th, .table td { border-color: #ccc !important; padding: 4px 6px !important; }

        a { color: #000 !important; text-decoration: underline; }

        /* 80mm thermal receipt format */
        .receipt { max-width: 72mm; margin: 0 auto; font-family: 'DM Mono', monospace; font-size: 10px; }
        .receipt h1, .receipt h2, .receipt h3 { font-size: 12px; text-align: center; }
        .receipt .total-line { font-weight: 700; font-size: 13px; border-top: 1px dashed #000; padding-top: 4px; }
    }
    </style>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <?php if (!empty($appSettings['app_logo'])): ?>
        <div style="margin-bottom:0.5rem"><img src="<?= BASE_URL ?>/<?= e($appSettings['app_logo']) ?>" alt="<?= e($appSettings['app_name'] ?? 'POS') ?>" style="height:36px;max-width:100%;border-radius:6px"></div>
        <?php endif; ?>
        <div class="brand-name"><?= e($appSettings['app_name'] ?? 'POS System') ?></div>
        <div class="brand-sub"><?= e($appSettings['app_subtitle'] ?? 'Gestion Commerciale') ?></div>
    </div>

    <?php
    $user = currentUser();
    $storeModel = new Store();
    $currentStore = $storeModel->find(currentStoreId());
    ?>
    <div class="store-badge">
        <div class="store-dot"></div>
        <div class="store-name"><?= e($currentStore['name'] ?? 'Boutique') ?></div>
    </div>

    <div class="sidebar-nav">
        <?php if (hasPermission('dashboard') || hasPermission('pos')): ?>
        <div class="nav-section-label">🏠 Principal</div>
        <?php if (hasPermission('dashboard')): ?>
        <a href="<?= BASE_URL ?>/views/dashboard.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : '' ?>">
            📊 Dashboard
        </a>
        <?php endif; ?>
        <?php if (hasPermission('pos')): ?>
        <a href="<?= BASE_URL ?>/views/pos.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'pos.php') ? 'active' : '' ?>">
            🛒 Point de Vente
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('products') || hasPermission('categories') || hasPermission('stock_view') || hasPermission('transfers')): ?>
        <div class="nav-section-label">📦 Inventaire</div>
        <?php if (hasPermission('products')): ?>
        <a href="<?= BASE_URL ?>/views/products.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'products.php') ? 'active' : '' ?>">
            📦 Produits
        </a>
        <?php endif; ?>
        <?php if (hasPermission('categories')): ?>
        <a href="<?= BASE_URL ?>/views/categories.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'categories.php') ? 'active' : '' ?>">
            🏷️ Catégories
        </a>
        <?php endif; ?>
        <?php if (hasPermission('stock_view')): ?>
        <a href="<?= BASE_URL ?>/views/stock.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'stock.php') ? 'active' : '' ?>">
            📊 Stock
        </a>
        <?php endif; ?>
        <?php if (hasPermission('transfers')): ?>
        <a href="<?= BASE_URL ?>/views/transfers.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'transfers.php') ? 'active' : '' ?>">
            🔄 Transferts
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('sales') || hasPermission('customers')): ?>
        <div class="nav-section-label">💰 Ventes</div>
        <?php if (hasPermission('sales')): ?>
        <a href="<?= BASE_URL ?>/views/sales.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'sales.php') ? 'active' : '' ?>">
            🧾 Historique Ventes
        </a>
        <?php endif; ?>
        <?php if (hasPermission('customers')): ?>
        <a href="<?= BASE_URL ?>/views/customers.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'customers.php') ? 'active' : '' ?>">
            👥 Clients
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('reports')): ?>
        <div class="nav-section-label">📈 Analyse</div>
        <a href="<?= BASE_URL ?>/views/reports.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'reports.php') ? 'active' : '' ?>">
            📈 Rapports
        </a>
        <?php endif; ?>

        <?php if (hasPermission('stores') || hasPermission('warehouses') || hasPermission('users') || hasPermission('settings')): ?>
        <div class="nav-section-label">⚙️ Administration</div>
        <?php if (hasPermission('stores')): ?>
        <a href="<?= BASE_URL ?>/views/stores.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'stores.php') ? 'active' : '' ?>">
            🏪 Boutiques
        </a>
        <?php endif; ?>
        <?php if (hasPermission('warehouses')): ?>
        <a href="<?= BASE_URL ?>/views/warehouses.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'warehouses.php') ? 'active' : '' ?>">
            🏗️ Magasins
        </a>
        <?php endif; ?>
        <?php if (hasPermission('users')): ?>
        <a href="<?= BASE_URL ?>/views/users.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'users.php') ? 'active' : '' ?>">
            👤 Utilisateurs
        </a>
        <?php endif; ?>
        <?php if (hasPermission('settings')): ?>
        <a href="<?= BASE_URL ?>/views/settings.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'settings.php') ? 'active' : '' ?>">
            ⚙️ Paramètres
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="user-mini">
            <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?></div>
            <div class="user-info flex-1" style="min-width:0">
                <div class="user-name"><?= e($user['name'] ?? '') ?></div>
                <div class="user-role"><?= ucfirst($user['role'] ?? '') ?></div>
            </div>
            <a href="<?= BASE_URL ?>/controllers/auth.php?action=logout" class="text-danger" title="Déconnexion">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div id="main-content">
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-2">
            <button id="toggle-sidebar" onclick="toggleSidebar()">
                <i class="bi bi-list" style="font-size:1.2rem"></i>
            </button>
            <span class="page-title"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></span>
        </div>
        <div class="topbar-actions">
            <?php $alerts = (new Product())->getLowStock(currentStoreId()); ?>
            <?php if (count($alerts) > 0): ?>
            <a href="<?= BASE_URL ?>/views/stock.php?filter=low" class="btn btn-sm" style="background:rgba(239,68,68,0.1);color:#EF4444;border:1px solid rgba(239,68,68,0.2);border-radius:8px;">
                <i class="bi bi-exclamation-triangle me-1"></i><?= count($alerts) ?> stock faible
            </a>
            <?php endif; ?>
            <?php if (basename($_SERVER['PHP_SELF']) === 'pos.php'): ?>
            <a href="<?= BASE_URL ?>/views/pos.php" class="btn btn-sm btn-primary" style="border-radius:8px;">
                <i class="bi bi-bag-plus me-1"></i>Nouvelle Vente
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOAST ZONE -->
    <div class="toast-zone" id="toastZone"></div>
    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
    <script>window._flashToast = <?= json_encode($flash) ?>;</script>
    <?php endif; ?>

    <!-- PAGE CONTENT -->
    <div class="page-content">
