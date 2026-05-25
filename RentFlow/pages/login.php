<?php
session_start();
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier && $password) {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (username = ? OR email = ?) AND active = 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $stmt = $pdo->prepare("UPDATE utilisateurs SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            header('Location: index.php?page=dashboard');
            exit;
        } else {
            // Debug info
            $debug_stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE username = ? OR email = ?");
            $debug_stmt->execute([$identifier, $identifier]);
            $debug_user = $debug_stmt->fetch();
            
            if (!$debug_user) {
                $error = 'Utilisateur non trouvé.';
            } elseif (!$debug_user['active']) {
                $error = 'Compte inactive.';
            } else {
                $error = 'Mot de passe incorrect.';
            }
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - RentFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font-bootstrap-icons.css" rel="stylesheet">
    <?php
    $police = getParam('police', 'system');
    if ($police !== 'system') {
        echo '<link href="https://fonts.googleapis.com/css2?family=' . str_replace('-', '+', $police) . ':wght@400;500;600;700&display=swap" rel="stylesheet">';
    }
    ?>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --theme-color: <?php echo getThemeColor(); ?>;
            --theme-rgb: <?php echo getThemeRGB(); ?>;
            <?php if ($police !== 'system'): ?>
            --font-family: '<?php echo ucfirst($police); ?>', sans-serif;
            <?php endif; ?>
        }
    </style>
</head>
<body class="login-page">
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-house-door"></i>
            <h1>RentFlow</h1>
            <p>Gestion des Loyers</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email ou nom d'utilisateur</label>
                    <input type="text" name="identifier" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>