<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Si déjà connecté → dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . '/views/dashboard.php');
}

$error = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verifyCsrf($csrf)) {
        $error = 'Requête invalide. Rechargez la page.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Veuillez saisir votre email et mot de passe.';
    } else {
        $userModel = new User();
        $user = $userModel->authenticate($email, $password);
        if ($user) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['user_role']    = $user['role'];
            $_SESSION['store_id']     = $user['store_id'];
            $_SESSION['warehouse_id'] = $user['warehouse_id'] ?? null;
            $_SESSION['user']         = $user;
            $_SESSION['last_activity'] = time();
            // Load permissions into session
            $_SESSION['user_permissions'] = [];
            if ($user['role'] !== 'admin' && !empty($user['permissions'])) {
                $decoded = json_decode($user['permissions'], true);
                $_SESSION['user_permissions'] = is_array($decoded) ? $decoded : [];
            }
            session_regenerate_id(true);
            redirect(BASE_URL . '/views/dashboard.php');
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appSettings['app_name'] ?? APP_NAME) ?> — Connexion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/fonts/fonts.css">
    <style>
        <?php
        $loginTheme = $appSettings['theme'] ?? 'default';
        $loginPrimary = $appSettings['primary_color'] ?? '#1a4f8a';
        $loginAccent2 = $appSettings['accent2_color'] ?? '#0ea87e';

        $loginThemeVars = [
            'default'    => ['body' => '#f0f2f5', 'card' => '#ffffff', 'text' => '#1a2236', 'muted' => '#4b5671', 'border' => '#dde2ea', 'isLight' => true],
            'wallstreet' => ['body' => '#0f0f14', 'card' => '#16161e', 'text' => '#d8d8e0', 'muted' => '#9090a0', 'border' => '#2a2a3a', 'isLight' => false],
            'cyberpunk'  => ['body' => '#1a0a2a', 'card' => '#22103a', 'text' => '#e0d6f0', 'muted' => '#a890c0', 'border' => '#3a205a', 'isLight' => false],
            'aurora'     => ['body' => '#0a0a20', 'card' => '#121230', 'text' => '#c8d0f0', 'muted' => '#8890b0', 'border' => '#252560', 'isLight' => false],
            'executive'  => ['body' => '#0f0f14', 'card' => '#16161e', 'text' => '#d0d0d8', 'muted' => '#9090a0', 'border' => '#2a2a3a', 'isLight' => false],
            'solar'      => ['body' => '#1a140a', 'card' => '#221c10', 'text' => '#e8d8c8', 'muted' => '#b89070', 'border' => '#3a2a18', 'isLight' => false],
            'ocean'      => ['body' => '#0a0f2a', 'card' => '#121a30', 'text' => '#b8c8e0', 'muted' => '#7890a8', 'border' => '#252f50', 'isLight' => false],
            'royal'      => ['body' => '#1a0a2a', 'card' => '#22103a', 'text' => '#d8c8e0', 'muted' => '#a080b0', 'border' => '#3a205a', 'isLight' => false],
            'emerald'    => ['body' => '#0a1a14', 'card' => '#121f18', 'text' => '#c8e0d0', 'muted' => '#80b098', 'border' => '#253a2a', 'isLight' => false],
        ];
        $ltv = $loginThemeVars[$loginTheme] ?? $loginThemeVars['default'];
        $loginIsLight = $ltv['isLight'];
        ?>

        :root {
            --login-body: <?= e($ltv['body']) ?>;
            --login-card: <?= e($ltv['card']) ?>;
            --login-text: <?= e($ltv['text']) ?>;
            --login-muted: <?= e($ltv['muted']) ?>;
            --login-border: <?= e($ltv['border']) ?>;
            --accent: <?= e($loginPrimary) ?>;
            --accent2: <?= e($loginAccent2) ?>;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: var(--login-body);
            font-family: 'Manrope', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-card {
            background: var(--login-card);
            border: 1px solid var(--login-border);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            box-shadow: <?= $loginIsLight ? '0 8px 32px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04)' : '0 8px 32px rgba(0,0,0,0.35), 0 2px 8px rgba(0,0,0,0.2)' ?>;
        }

        .brand {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--login-text);
            letter-spacing: -0.03em;
        }
        .brand span { color: var(--accent); }

        .brand-sub {
            color: var(--login-muted);
            font-size: 0.8rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .form-label { color: var(--login-muted); font-size: 0.82rem; margin-bottom: 6px; font-weight: 500; }

        .form-control {
            background: var(--login-card);
            border: 1px solid var(--login-border);
            color: var(--login-text);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
        }
        .form-control:focus {
            background: var(--login-card);
            border-color: var(--accent);
            color: var(--login-text);
            box-shadow: 0 0 0 3px <?= e(hexToRgba($loginPrimary, 0.15)) ?>;
        }
        .form-control::placeholder { color: var(--login-muted); }

        .btn-login {
            background: var(--accent);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-family: 'Manrope', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem;
            width: 100%;
            transition: all 0.2s;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 8px <?= e(hexToRgba($loginPrimary, 0.3)) ?>;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 4px 16px <?= e(hexToRgba($loginPrimary, 0.4)) ?>; color: #fff; }

        .demo-box {
            background: <?= $loginIsLight ? 'rgba(14,168,126,0.06)' : 'rgba(14,168,126,0.08)' ?>;
            border: 1px solid <?= $loginIsLight ? 'rgba(14,168,126,0.15)' : 'rgba(14,168,126,0.2)' ?>;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: var(--login-muted);
        }
        .demo-box strong { color: var(--accent2); }

        .divider { height: 1px; background: var(--login-border); margin: 1.5rem 0; }

        .alert {
            background: <?= $loginIsLight ? 'var(--login-card)' : 'rgba(255,255,255,0.05)' ?>;
            color: var(--login-text);
            border: 1px solid var(--login-border);
            border-radius: 8px;
        }
        .alert-warning {
            background: <?= $loginIsLight ? '#FFF8E1' : 'rgba(245,158,11,0.12)' ?>;
            color: <?= $loginIsLight ? '#795548' : '#FDE68A' ?>;
            border-color: <?= $loginIsLight ? '#FFE0B2' : 'rgba(245,158,11,0.25)' ?>;
        }
        .alert-danger {
            background: <?= $loginIsLight ? '#FFEBEE' : 'rgba(239,68,68,0.12)' ?>;
            color: <?= $loginIsLight ? '#C62828' : '#FECACA' ?>;
            border-color: <?= $loginIsLight ? '#FFCDD2' : 'rgba(239,68,68,0.25)' ?>;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <?php if (!empty($appSettings['app_logo'])): ?>
        <div style="margin-bottom:0.75rem"><img src="<?= BASE_URL ?>/<?= e($appSettings['app_logo']) ?>" alt="" style="height:48px;border-radius:8px"></div>
        <?php endif; ?>
        <div class="brand"><?= e($appSettings['app_name'] ?? 'POS System') ?></div>
        <div class="brand-sub"><?= e($appSettings['app_subtitle'] ?? 'Gestion Commerciale') ?></div>
    </div>

    <?php if ($timeout): ?>
    <div class="alert alert-warning py-2 small">Session expirée. Reconnectez-vous.</div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="mb-3">
            <label class="form-label">Adresse Email</label>
            <input type="email" name="email" class="form-control" placeholder="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-4">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-control" placeholder="mot de passe" required>
        </div>
        <button type="submit" class="btn btn-login">
            <i class="bi bi-arrow-right-circle me-2"></i>Se connecter
        </button>
    </form>

    <div class="divider"></div>

    <div class="demo-box">
        <div class="mb-1"><strong>Comptes démo (mot de passe: password)</strong></div>
        <div>👤 admin@brenshop.cm — Administrateur</div>
        <div>👤 manager.douala@brenshop.cm — Manager</div>
        <div>👤 caisse.douala@brenshop.cm — Caissier</div>
        <div>👤 caisse.yaounde@brenshop.cm — Caissier</div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
