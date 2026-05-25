<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $title ?? 'Tableau de bord' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <link rel="manifest" href="<?= BASE_URL ?>/public/manifest.json">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>NexRide</h2>
                <button class="sidebar-toggle" onclick="toggleSidebar()">&times;</button>
            </div>
            <nav class="sidebar-nav">
                <a href="<?= BASE_URL ?>" class="nav-item">Tableau de bord</a>
                <div class="nav-section">Voyages</div>
                <a href="<?= BASE_URL ?>/voyages" class="nav-item">Voyages</a>
                <a href="<?= BASE_URL ?>/vehicules" class="nav-item">Vehicules</a>
                <a href="<?= BASE_URL ?>/chauffeurs" class="nav-item">Chauffeurs</a>
                <div class="nav-section">Billets</div>
                <a href="<?= BASE_URL ?>/billets" class="nav-item">Billets</a>
                <a href="<?= BASE_URL ?>/billets/create" class="nav-item">Nouveau billet</a>
                <div class="nav-section">Finances</div>
                <a href="<?= BASE_URL ?>/bordereaux" class="nav-item">Bordereaux</a>
                <a href="<?= BASE_URL ?>/comptabilite" class="nav-item">Comptabilite</a>
                <div class="nav-section">Clients</div>
                <a href="<?= BASE_URL ?>/clients" class="nav-item">Clients</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <strong><?= htmlspecialchars(\Core\Session::getInstance()->get('user_nom')) ?></strong>
                    <small><?= \Core\Session::getInstance()->get('user_role') ?></small>
                </div>
                <a href="<?= BASE_URL ?>/auth/logout" class="btn btn-sm btn-danger">Deconnexion</a>
            </div>
        </aside>
        <main class="main-content" id="main-content">
            <header class="topbar">
                <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
                <div class="topbar-title"><?= $title ?? 'Tableau de bord' ?></div>
                <span class="offline-indicator" id="offline-indicator">En ligne</span>
            </header>
            <div class="content">
                <?php foreach (['success','error','warning','info'] as $type): ?>
                    <?php if ($msg = \Core\Session::getInstance()->getFlash($type)): ?>
                        <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?= $content ?>
            </div>
        </main>
    </div>
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/offline.js"></script>
    <script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open')}</script>
</body>
</html>