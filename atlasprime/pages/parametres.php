<?php
$pageTitle = 'Paramètres';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('parametres');

$configFile = __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiUrl = trim($_POST['api_url'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');

    $configContent = file_get_contents($configFile);
    $configContent = preg_replace(
        "/define\('EXTERNAL_API_URL', '[^']*'\);/",
        "define('EXTERNAL_API_URL', '" . addslashes($apiUrl) . "');",
        $configContent
    );
    $configContent = preg_replace(
        "/define\('EXTERNAL_API_KEY', '[^']*'\);/",
        "define('EXTERNAL_API_KEY', '" . addslashes($apiKey) . "');",
        $configContent
    );
    file_put_contents($configFile, $configContent);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Paramètres sauvegardés.'];
    header('Location: parametres.php');
    exit;
}

$apiUrl = defined('EXTERNAL_API_URL') ? EXTERNAL_API_URL : '';
$apiKey = defined('EXTERNAL_API_KEY') ? EXTERNAL_API_KEY : '';
?>

<div style="max-width:600px">
    <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:24px">Paramètres</h2>

    <div class="card" style="margin-bottom:24px">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:16px;color:var(--primary)">API Externe</h3>
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:20px">
            Configurez la connexion API pour synchroniser les voyages avec une application externe.
        </p>

        <form method="post">
            <div class="form-group">
                <label class="form-label">URL de l'API</label>
                <input type="url" name="api_url" class="form-control"
                       placeholder="https://api.exemple.com/voyages"
                       value="<?= htmlspecialchars($apiUrl) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Clé API</label>
                <input type="password" name="api_key" class="form-control"
                       placeholder="Votre clé API"
                       value="<?= htmlspecialchars($apiKey) ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Sauvegarder
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>