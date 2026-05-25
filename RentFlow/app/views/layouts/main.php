<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title . ' - ' : '' ?><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 0 4px 140px 8px;
            z-index: 1000;
            overflow-y: scroll;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.3) transparent;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
            margin-top: 10px;
            margin-bottom: 140px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
            transition: background 0.3s;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.6);
        }
        .sidebar::-webkit-scrollbar-button {
            display: none;
        }
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            padding: 20px 1.5rem 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        .sidebar-section-title {
            padding: 0.5rem 1.5rem;
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sidebar-section-title:first-child {
            margin-top: 0;
        }
        .sidebar-section-title i {
            font-size: 0.75rem;
        }
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-nav li {
            margin-bottom: 0.25rem;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.5rem 1.5rem;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 6px;
            margin: 0 0.5rem;
            font-size: 0.85rem;
        }
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        .sidebar-nav a:hover .nav-icon {
            transform: scale(1.3) rotate(10deg);
        }
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.25);
            color: white;
            border-left: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .sidebar-nav a.active .nav-icon {
            animation: pulse 1.5s ease-in-out infinite;
        }
        .nav-icon {
            margin-right: 0.75rem;
            font-size: 1.2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        .sidebar-user {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 260px;
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.15);
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        .user-info {
            display: flex;
            align-items: center;
            color: white;
            margin-bottom: 0.5rem;
        }
        .user-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.75rem;
        }
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
        }
        .stat-card { border-left: 4px solid #0d6efd; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .badge-early { background-color: #198754; }
        .badge-ontime { background-color: #0dcaf0; }
        .badge-late { background-color: #dc3545; }
        .badge-pending { background-color: #6c757d; }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            outline: none;
        }
        .btn-logout:hover {
            background: rgba(220, 53, 69, 0.35);
            border-color: rgba(220, 53, 69, 0.6);
            color: #fff;
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.3), 0 4px 12px rgba(0, 0, 0, 0.3);
            transform: translateY(-1px);
        }
        .btn-logout:active {
            transform: scale(0.96);
            transition: transform 0.1s;
        }
        .btn-logout-icon {
            display: inline-flex;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1.1rem;
        }
        .btn-logout:hover .btn-logout-icon {
            animation: doorExit 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            color: #ff6b6b;
        }
        .btn-logout-text {
            transition: all 0.3s ease;
        }
        .btn-logout:hover .btn-logout-text {
            letter-spacing: 0.5px;
        }
        .btn-logout-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transform: scale(0);
            animation: none;
            pointer-events: none;
        }
        @keyframes doorExit {
            0% { transform: translateX(0) rotate(0); opacity: 1; }
            30% { transform: translateX(3px) rotate(0); opacity: 1; }
            60% { transform: translateX(18px) rotate(10deg); opacity: 0.3; }
            80% { transform: translateX(24px) rotate(15deg); opacity: 0; }
            100% { transform: translateX(0) rotate(0); opacity: 1; }
        }
        @keyframes rippleEffect {
            to { transform: scale(4); opacity: 0; }
        }
    </style>
</head>
<body>
    <?php
    $isLoggedIn = false;
    $userName = 'Invité';
    $userInitial = 'U';
    if (class_exists('Auth') && method_exists('Auth', 'check') && Auth::check()) {
        $isLoggedIn = true;
        $userData = Auth::user();
        if (is_array($userData)) {
            $userName = $userData['name'] ?? 'Utilisateur';
            $userInitial = strtoupper(substr($userName, 0, 1));
        }
    }
    ?>

    <?php if ($isLoggedIn): ?>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-building"></i> RentFlow
        </div>

        <!-- Navigation Principale -->
        <div class="sidebar-section-title">
            <i class="bi bi-grid-fill"></i> Principal
        </div>
        <ul class="sidebar-nav">
            <li><a href="<?= BASE_URL ?>/dashboard" class="<?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>"><span class="nav-icon">📊</span> Dashboard</a></li>
        </ul>

        <!-- Navigation Gestion -->
        <div class="sidebar-section-title">
            <i class="bi bi-folder-fill"></i> Gestion
        </div>
        <ul class="sidebar-nav">
            <li><a href="<?= BASE_URL ?>/landlords" class="<?= strpos($_SERVER['REQUEST_URI'], 'landlords') !== false ? 'active' : '' ?>"><span class="nav-icon">👥</span> Bailleurs</a></li>
            <li><a href="<?= BASE_URL ?>/agencies" class="<?= strpos($_SERVER['REQUEST_URI'], 'agencies') !== false ? 'active' : '' ?>"><span class="nav-icon">🏢</span> Agences</a></li>
            <li><a href="<?= BASE_URL ?>/batches" class="<?= strpos($_SERVER['REQUEST_URI'], 'batches') !== false ? 'active' : '' ?>"><span class="nav-icon">📦</span> Lots</a></li>
        </ul>

        <!-- Navigation Paiements -->
        <div class="sidebar-section-title">
            <i class="bi bi-cash-coin"></i> Finances
        </div>
        <ul class="sidebar-nav">
            <li><a href="<?= BASE_URL ?>/payments" class="<?= strpos($_SERVER['REQUEST_URI'], 'payments') !== false && strpos($_SERVER['REQUEST_URI'], 'late') === false && strpos($_SERVER['REQUEST_URI'], 'early') === false ? 'active' : '' ?>"><span class="nav-icon">💰</span> Paiements</a></li>
            <li><a href="<?= BASE_URL ?>/payments/late" class="<?= strpos($_SERVER['REQUEST_URI'], 'late') !== false ? 'active' : '' ?>"><span class="nav-icon">⚠️</span> Retards</a></li>
            <li><a href="<?= BASE_URL ?>/payments/early" class="<?= strpos($_SERVER['REQUEST_URI'], 'early') !== false ? 'active' : '' ?>"><span class="nav-icon">✅</span> Anticipés</a></li>
        </ul>

        <!-- Navigation Rapports -->
        <div class="sidebar-section-title">
            <i class="bi bi-bar-chart-fill"></i> Statistiques
        </div>
        <ul class="sidebar-nav">
            <li><a href="<?= BASE_URL ?>/reports" class="<?= strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'active' : '' ?>"><span class="nav-icon">📈</span> Rapports</a></li>
        </ul>

        <!-- Navigation Administration -->
        <?php if (Auth::isAdmin()): ?>
        <div class="sidebar-section-title">
            <i class="bi bi-shield-lock-fill"></i> Administration
        </div>
        <ul class="sidebar-nav">
            <li><a href="<?= BASE_URL ?>/users" class="<?= strpos($_SERVER['REQUEST_URI'], 'users') !== false && strpos($_SERVER['REQUEST_URI'], 'roles') === false ? 'active' : '' ?>"><span class="nav-icon">👤</span> Utilisateurs</a></li>
            <li><a href="<?= BASE_URL ?>/users/roles" class="<?= strpos($_SERVER['REQUEST_URI'], 'users/roles') !== false ? 'active' : '' ?>"><span class="nav-icon">🔐</span> Rôles & Permissions</a></li>
        </ul>

        <!-- Navigation Paramètres -->
        <div class="sidebar-section-title">
            <i class="bi bi-gear-fill"></i> Système
        </div>
        <ul class="sidebar-nav">
            <li><a href="<?= BASE_URL ?>/settings" class="<?= strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'active' : '' ?>"><span class="nav-icon">⚙️</span> Paramètres</a></li>
        </ul>
        <?php endif; ?>

        <!-- Section Utilisateur -->
        <div class="sidebar-user">
            <div class="user-info">
                <span class="user-avatar"><?= $userInitial ?></span>
                <div>
                    <div style="font-weight: 600;"><?= htmlspecialchars($userName) ?></div>
                </div>
            </div>
            <form action="<?= BASE_URL ?>/logout" method="POST">
                <?= Csrf::field() ?>
                <button type="submit" class="btn-logout">
                    <span class="btn-logout-icon">
                        <i class="bi bi-box-arrow-right"></i>
                    </span>
                    <span class="btn-logout-text">Déconnexion</span>
                    <span class="btn-logout-ripple"></span>
                </button>
            </form>
        </div>
    </aside>
    <?php endif; ?>

    <div class="<?= $isLoggedIn ? 'main-content' : 'container-fluid py-4' ?>">
        <!-- Toast Notifications (Style PS5) -->
        <div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column-reverse; gap: 12px;"></div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if (isset($_SESSION['success'])): ?>
                    showToast(<?= json_encode($_SESSION['success'], JSON_UNESCAPED_UNICODE) ?>, 'success');
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    showToast(<?= json_encode($_SESSION['error'], JSON_UNESCAPED_UNICODE) ?>, 'error');
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['errors'])): ?>
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        showToast(<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>, 'error');
                    <?php endforeach; unset($_SESSION['errors']); ?>
                <?php endif; ?>
            });

            function showToast(message, type = 'info') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = 'toast-notification toast-' + type;

                const icons = {
                    success: '✓',
                    error: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };

                const iconDiv = document.createElement('div');
                iconDiv.className = 'toast-icon';
                iconDiv.textContent = icons[type] || icons.info;

                const contentDiv = document.createElement('div');
                contentDiv.className = 'toast-content';

                const messageDiv = document.createElement('div');
                messageDiv.className = 'toast-message';
                messageDiv.textContent = message;

                contentDiv.appendChild(messageDiv);

                const closeBtn = document.createElement('button');
                closeBtn.className = 'toast-close';
                closeBtn.textContent = '✕';
                closeBtn.onclick = function() {
                    toast.classList.add('toast-exit');
                    setTimeout(function() { toast.remove(); }, 300);
                };

                toast.appendChild(iconDiv);
                toast.appendChild(contentDiv);
                toast.appendChild(closeBtn);

                container.appendChild(toast);

                setTimeout(function() {
                    toast.classList.add('toast-enter');
                }, 10);

                setTimeout(function() {
                    toast.classList.add('toast-exit');
                    setTimeout(function() { toast.remove(); }, 300);
                }, 4000);
            }
        </script>

        <style>
            .toast-notification {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 16px 20px;
                background: rgba(30, 30, 30, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4),
                            0 0 0 1px rgba(255, 255, 255, 0.05) inset;
                min-width: 320px;
                max-width: 450px;
                opacity: 0;
                transform: translateY(80px);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }

            .toast-notification::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: var(--toast-color, #0070d1);
                border-radius: 4px 0 0 4px;
            }

            .toast-success { --toast-color: #00d4aa; }
            .toast-error { --toast-color: #ff4d4d; }
            .toast-warning { --toast-color: #ffb800; }
            .toast-info { --toast-color: #0070d1; }

            .toast-icon {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: var(--toast-color);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                font-weight: bold;
                flex-shrink: 0;
            }

            .toast-content {
                flex: 1;
                color: white;
                font-size: 14px;
                line-height: 1.4;
            }

            .toast-close {
                background: transparent;
                border: none;
                color: rgba(255, 255, 255, 0.5);
                cursor: pointer;
                padding: 4px;
                font-size: 16px;
                transition: color 0.2s;
            }

            .toast-close:hover {
                color: white;
            }

            .toast-enter {
                opacity: 1;
                transform: translateY(0);
            }

            .toast-exit {
                opacity: 0;
                transform: translateY(-20px);
            }

            @keyframes toast-progress {
                from { width: 100%; }
                to { width: 0%; }
            }

            .toast-notification::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                height: 2px;
                background: var(--toast-color);
                animation: toast-progress 4s linear forwards;
            }
        </style>

        <?= $content ?? '' ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
            });
        });
    </script>
    <script>
        document.querySelectorAll('.btn-logout').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                var ripple = this.querySelector('.btn-logout-ripple');
                var rect = this.getBoundingClientRect();
                var size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                ripple.style.animation = 'rippleEffect 0.6s ease-out';
                ripple.addEventListener('animationend', function() {
                    ripple.style.animation = 'none';
                });
            });
        });
    </script>
</body>
</html>
