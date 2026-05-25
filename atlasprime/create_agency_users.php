<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    Database::execute("ALTER TABLE utilisateurs ADD COLUMN agence_id INT NULL");
} catch (Exception $e) {}

$agences = Database::fetchAll("SELECT id, code, nom FROM agences ORDER BY nom");
$hash = password_hash('password', PASSWORD_BCRYPT, ['cost'=>12]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['agences'] ?? [];
    $created = 0;

    foreach ($selected as $agenceId) {
        $agence = Database::fetchOne("SELECT id, ville FROM agences WHERE id=?", [$agenceId]);
        if ($agence) {
            $username = strtolower(str_replace(' ', '_', $agence['nom']));
            $existing = Database::fetchOne("SELECT id FROM utilisateurs WHERE username=?", [$username]);

            if (!$existing) {
                Database::execute(
                    "INSERT INTO utilisateurs (nom, prenom, email, username, password_hash, role_id, agence_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$agence['nom'], 'Agent', strtolower($agence['nom']) . '@atlasprime.cm', $username, $hash, 4, $agenceId]
                );
                $created++;
            }
        }
    }

    echo "<p style='color:green;font-family:Arial;padding:20px'>$created utilisateurs crees - mot de passe: 'password'</p>";
    echo "<a href='create_agency_users.php' style='font-family:Arial'>Retour</a>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Creer utilisateurs</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; margin: 0; padding: 40px; }
        .card { background: white; border-radius: 8px; max-width: 400px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        h2 { margin: 0; padding: 20px; background: #0066cc; color: white; font-size: 1.1rem; }
        .list { padding: 10px; }
        .item { display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #eee; }
        .item:last-child { border-bottom: none; }
        .item label { cursor: pointer; flex: 1; font-size: 0.95rem; }
        .btn { background: #0066cc; color: white; border: none; width: 100%; padding: 14px; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #0052a3; }
        p { color: #666; font-size: 0.85rem; padding: 0 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Creer utilisateurs par agence</h2>
        <p>Assignation par agence - mot de passe: password</p>
        <form method="post">
            <div class="list">
                <?php foreach ($agences as $a): ?>
                <div class="item">
                    <input type="checkbox" name="agences[]" value="<?= $a['id'] ?>" id="a<?= $a['id'] ?>">
                    <label for="a<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn">Creer</button>
        </form>
    </div>
</body>
</html>