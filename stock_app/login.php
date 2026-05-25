<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (is_logged_in()) {
    redirect('index.php');
}

$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        'SELECT u.id, u.full_name, u.username, u.password, u.is_active, r.role_name, r.permissions
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.username = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password'])) {
        $user['role_name'] = (string) $user['role_name'];
        $user['permissions'] = json_decode((string) $user['permissions'], true) ?: [];
        unset($user['password'], $user['is_active']);
        $_SESSION['user'] = $user;
        set_flash('success', 'Bienvenue ' . $user['full_name'] . '.');
        redirect('index.php');
    }

    set_flash('danger', 'Identifiants invalides ou compte desactive.');
    redirect('login.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | StockPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 420px;">
        <div class="card-body p-4 p-lg-5">
            <div class="text-center mb-4">
                <h1 class="h3 fw-bold">StockPro</h1>
                <p class="text-muted mb-0">Gestion de stock sous XAMPP</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label" for="username">Nom d utilisateur</label>
                    <input class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input class="form-control" id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Se connecter</button>
            </form>

            <div class="small text-muted mt-4">
                Compte par defaut: <strong>admin</strong> / <strong>admin123</strong>
            </div>
        </div>
    </div>
</body>
</html>
