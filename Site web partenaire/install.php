<?php
/**
 * [OPTIONNEL] Première installation : lit database.sql et exécute les CREATE (sans INSERT).
 * Les évolutions de schéma se font dans database.sql puis en SQL direct dans MySQL — ce
 * script n’est pas nécessaire pour les modifications courantes.
 * Ne pas ajouter de CREATE/ALTER ici : tout le DDL reste dans database.sql.
 * À supprimer ou protéger après usage sur l’hébergement.
 */
require_once __DIR__ . '/config.php';

/**
 * Exécute un fichier SQL : ignore les lignes de commentaire -- et les instructions INSERT.
 */
function esadiss_run_sql_schema_only(PDO $pdo, string $path): void {
  $raw = file_get_contents($path);
  if ($raw === false) {
    throw new RuntimeException('Fichier introuvable : ' . $path);
  }
  $raw = preg_replace('/\/\*[\s\S]*?\*\//', '', $raw);
  $lines = explode("\n", $raw);
  $buf = '';
  foreach ($lines as $line) {
    $t = trim($line);
    if ($t === '' || strpos($t, '--') === 0) {
      continue;
    }
    $buf .= $line . "\n";
  }
  $buf = trim($buf);
  if ($buf === '') {
    return;
  }
  $parts = preg_split('/;\s*\n/s', $buf);
  foreach ($parts as $chunk) {
    $chunk = trim($chunk);
    if ($chunk === '') {
      continue;
    }
    if (preg_match('/^\s*INSERT\s+/i', $chunk)) {
      continue;
    }
    $pdo->exec($chunk);
  }
}

$ok = false;
$message = '';

try {
  $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
  esadiss_run_sql_schema_only($pdo, __DIR__ . '/database.sql');

  $stmt = $pdo->query('SELECT COUNT(*) FROM utilisateurs');
  if ($stmt && (int) $stmt->fetchColumn() === 0) {
    $hash = password_hash('Admin123!', PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO utilisateurs (login, password_hash, nom, role, actif) VALUES (?, ?, ?, ?, ?)');
    $ins->execute(['admin', $hash, 'Administrateur', 'admin', 1]);
    $message = 'Tables créées. Premier compte admin : identifiant <strong>admin</strong>, mot de passe <strong>Admin123!</strong> — À modifier après première connexion. Supprimez ou protégez install.php. Les produits d’exemple peuvent être importés via <strong>seed.php</strong> ou en exécutant la section INSERT de <code>database.sql</code> dans phpMyAdmin.';
  } else {
    $message = 'Schéma à jour (aucun nouvel admin créé — comptes déjà présents). Supprimez ou protégez install.php.';
  }
  $ok = true;
} catch (PDOException $e) {
  $message = 'Erreur : ' . $e->getMessage() . ' — Vérifiez que la base existe et que config.php est correct.';
} catch (Throwable $e) {
  $message = 'Erreur : ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 500px; margin: 2rem auto; padding: 0 1rem; }
    .ok { color: #22c55e; }
    .err { color: #ef4444; }
  </style>
</head>
<body>
  <h1>Installation base de données</h1>
  <p class="<?php echo $ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($message); ?></p>
  <?php if ($ok): ?>
  <p><a href="login.php">Se connecter</a> — <a href="admin.php">Administration</a></p>
  <?php endif; ?>
</body>
</html>
