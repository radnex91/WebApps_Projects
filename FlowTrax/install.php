<?php
/**
 * DEX Transport - Installateur
 * 
 * Accédez via : http://localhost/dex_app/install.php
 * Supprimez ce fichier après installation.
 */

// Try to load database config
$configFile = __DIR__ . '/config/database.php';
$dbConnected = false;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['db_host'] ?? 'localhost';
    $name = $_POST['db_name'] ?? 'dex_app';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';

    try {
        // Connect without database first
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Execute schema
        $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
        
        // Replace the hardcoded password hash with a proper one
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = str_replace(
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            $adminPassword,
            $sql
        );

        // Split by semicolons and execute each statement
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        // Write config file
        $configContent = <<<PHP
<?php
define('DB_HOST', '$host');
define('DB_NAME', '$name');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        try {
            \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException \$e) {
            die("Erreur de connexion : " . \$e->getMessage());
        }
    }
    return \$pdo;
}
PHP;
        file_put_contents($configFile, $configContent);
        
        $success = '✅ Installation terminée avec succès !';
        $success .= '<br><br>📌 <strong>Identifiants par défaut :</strong>';
        $success .= '<br>👤 Utilisateur : <code>admin</code>';
        $success .= '<br>🔑 Mot de passe : <code>admin123</code>';
        $success .= '<br><br>⚠️ <strong>Supprimez le fichier install.php après installation.</strong>';
        $success .= '<br><br><a href="index.php" style="display:inline-block;padding:12px 24px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;font-weight:600;">🚀 Accéder à l\'application</a>';
        $dbConnected = true;

    } catch (Exception $e) {
        $error = '❌ Erreur : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation — DEX Transport</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .install-card h1 {
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 4px;
        }
        .install-card h1 span { background: linear-gradient(135deg, #2563eb, #7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .install-card > p {
            text-align: center;
            color: #64748b;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 4px; }
        .form-group input {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; font-family: inherit;
            transition: all 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn-install {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white; border: none; border-radius: 10px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
        }
        .btn-install:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(37,99,235,0.3); }
        .success { background: #d1fae5; color: #065f46; padding: 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; line-height: 1.8; }
        .error { background: #fee2e2; color: #991b1b; padding: 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
        .logo-icon { text-align: center; font-size: 48px; margin-bottom: 8px; animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
    </style>
</head>
<body>
    <div class="install-card">
        <div class="logo-icon">🚌</div>
        <h1><span>DEX Transport</span></h1>
        <p>Installation de l'application</p>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!$dbConnected): ?>
        <form method="POST">
            <div class="form-group">
                <label>Hôte MySQL</label>
                <input type="text" name="db_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>Nom de la base de données</label>
                <input type="text" name="db_name" value="dex_app" required>
            </div>
            <div class="form-group">
                <label>Utilisateur MySQL</label>
                <input type="text" name="db_user" value="root" required>
            </div>
            <div class="form-group">
                <label>Mot de passe MySQL</label>
                <input type="password" name="db_pass" placeholder="(laisser vide si aucun)">
            </div>
            <button type="submit" class="btn-install">🚀 Installer</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
