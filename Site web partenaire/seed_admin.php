<?php
/**
 * Crée le premier compte admin si aucun utilisateur n’existe.
 * Utile si vous aviez installé avant la mise à jour « connexion ».
 * À supprimer après usage.
 */
require_once __DIR__ . '/db.php';

$created = false;
$message = '';

try {
  $pdo = getPdo();
  $stmt = $pdo->query('SELECT COUNT(*) FROM utilisateurs');
  if ($stmt && (int) $stmt->fetchColumn() === 0) {
    $hash = password_hash('Admin123!', PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO utilisateurs (login, password_hash, nom, role, actif) VALUES (?, ?, ?, ?, ?)');
    $ins->execute(['admin', $hash, 'Administrateur', 'admin', 1]);
    $created = true;
    $message = 'Compte admin créé : identifiant <strong>admin</strong>, mot de passe <strong>Admin123!</strong> — Changez-le après connexion.';
  } else {
    $message = 'Des comptes existent déjà. Aucun compte créé.';
  }
} catch (Exception $e) {
  $message = 'Erreur : ' . $e->getMessage() . ' — Vérifiez que la table utilisateurs existe (exécutez install.php ou database.sql).';
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Création admin</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
    .ok { color: #22c55e; }
    a { color: #3b82f6; }
  </style>
</head>
<body>
  <h1>Premier compte admin</h1>
  <p class="<?php echo $created ? 'ok' : ''; ?>"><?php echo $message; ?></p>
  <p><a href="login.php">Se connecter</a></p>
</body>
</html>
