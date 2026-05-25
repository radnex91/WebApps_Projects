<?php
/**
 * ╔══════════════════════════════════════════════════════╗
 * ║  HotelPro Suite — Script d'installation initiale    ║
 * ║  ⚠️  SUPPRIMER CE FICHIER APRÈS UTILISATION  ⚠️      ║
 * ╚══════════════════════════════════════════════════════╝
 *
 * Accès : http://localhost/hotelmanager/setup.php
 */

define('BCRYPT_COST', 12);

// Mots de passe par défaut (à changer après installation)
$passwords = [
    'admin@hotel.cm'     => 'Admin@2025',
    'manager@hotel.cm'   => 'Manager@2025',
    'reception@hotel.cm' => 'Reception@2025',
];

// ── Mise à jour automatique des mots de passe si soumis ──────
$updateResults = [];
$updateError = '';
if (isset($_POST['apply_passwords'])) {
    $host = '127.0.0.1';
    $db   = 'hotelmanager';
    $user = 'root';
    $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        foreach ($passwords as $email => $pwd) {
            $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $stmt->execute([$hash, $email]);
            $updateResults[$email] = $stmt->rowCount();
        }
    } catch (PDOException $e) {
        $updateError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Setup — HotelPro Suite</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #f9fafb; }
    h1   { color: #1a56db; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    .warn { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px; margin-bottom: 20px; }
    code { background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 13px; word-break: break-all; }
    pre  { background: #1f2937; color: #e5e7eb; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
    .badge { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
    .btn-apply { background: #1a56db; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
    .btn-apply:hover { background: #1240ab; }
    .success { background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 8px; padding: 12px; margin-bottom: 16px; color: #065f46; }
    .error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 16px; color: #dc2626; }
  </style>
</head>
<body>

<h1>🏨 HotelPro Suite — Configuration initiale</h1>

<div class="warn">
  ⚠️ <strong>IMPORTANT :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité.
</div>

<?php if ($updateResults): ?>
<div class="success">
  ✓ Mots de passe mis à jour avec succès :<br>
  <?php foreach ($updateResults as $email => $count): ?>
    &nbsp;&nbsp;• <code><?= htmlspecialchars($email) ?></code> — <?= $count ? '1 ligne modifiée' : 'déjà à jour' ?><br>
  <?php endforeach; ?>
</div>
<?php elseif ($updateError): ?>
<div class="error">
  ✗ Erreur lors de la mise à jour : <?= htmlspecialchars($updateError) ?><br>
  Vérifiez que XAMPP est lancé et que la base <strong>hotelmanager</strong> a bien été importée.
</div>
<?php endif; ?>

<div class="card">
  <h2>1. Mise à jour des mots de passe</h2>
  <p>Cliquez sur le bouton ci-dessous pour générer les hashes bcrypt et les enregistrer directement dans la base de données :</p>
  <form method="POST">
    <button type="submit" name="apply_passwords" class="btn-apply">🔒 Mettre à jour les mots de passe</button>
  </form>
  <?php if (!$updateResults && !$updateError): ?>
  <p style="color:#9ca3af;font-size:12px;margin-top:8px;">Les hashes SQL sont aussi disponibles ci-dessous pour une mise à jour manuelle via phpMyAdmin.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>2. Requêtes SQL (mode manuel)</h2>
  <pre>USE hotelmanager;

<?php foreach ($passwords as $email => $password):
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
?>UPDATE users SET password = '<?= $hash ?>'
WHERE email = '<?= $email ?>';

<?php endforeach; ?></pre>
</div>

<div class="card">
  <h2>3. Vérification de la configuration</h2>
  <p>Extension PDO MySQL :</p>
  <?php if (extension_loaded('pdo_mysql')): ?>
  <span class="badge">✓ pdo_mysql activé</span>
  <?php else: ?>
  <span style="color:red;">✗ pdo_mysql non disponible — activez-le dans php.ini</span>
  <?php endif; ?>

  <p class="mt-3">PHP version :</p>
  <code><?= PHP_VERSION ?></code>
  <?= version_compare(PHP_VERSION, '7.4.0', '>=') ? ' <span class="badge">✓ OK</span>' : ' <span style="color:red;">⚠ PHP 7.4+ requis</span>' ?>
</div>

<div class="card">
  <h2>4. Test de connexion à la base</h2>
  <?php
  $host = '127.0.0.1';
  $db   = 'hotelmanager';
  $user = 'root';
  $pass = '';
  try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<span class='badge'>✓ Connexion réussie — $count utilisateur(s) trouvé(s)</span>";
  } catch (PDOException $e) {
    echo "<span style='color:red;'>✗ Erreur : " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<p>Vérifiez que XAMPP est lancé et que la base <strong>hotelmanager</strong> a bien été importée.</p>";
  }
  ?>
</div>

<div class="card">
  <h2>5. Comptes utilisateurs</h2>
  <table width="100%" style="border-collapse:collapse;">
    <tr style="background:#f3f4f6;"><th style="padding:8px;text-align:left;">Email</th><th style="padding:8px;text-align:left;">MDP par défaut</th><th style="padding:8px;">Rôle</th></tr>
    <?php foreach ($passwords as $email => $pass): ?>
    <tr style="border-bottom:1px solid #e5e7eb;">
      <td style="padding:8px;"><code><?= $email ?></code></td>
      <td style="padding:8px;"><code><?= $pass ?></code></td>
      <td style="padding:8px;text-align:center;">
        <?php
        $roles = ['admin@hotel.cm'=>'Admin','manager@hotel.cm'=>'Manager','reception@hotel.cm'=>'Réception'];
        echo '<span class="badge">' . ($roles[$email] ?? '?') . '</span>';
        ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p style="color:#dc2626;font-size:12px;margin-top:10px;">
    ⚠️ Changez tous les mots de passe immédiatement après la première connexion.
  </p>
</div>

<div class="card" style="background:#fef2f2;border-color:#fecaca;">
  <h3 style="color:#dc2626;">⚠️ Action requise</h3>
  <p>Une fois la configuration terminée :</p>
  <ol>
    <li>Cliquez sur <strong>"Mettre à jour les mots de passe"</strong> ci-dessus (ou exécutez les requêtes SQL manuellement)</li>
    <li><strong>Supprimez ce fichier</strong> : <code>C:\xampp\htdocs\hotelmanager\setup.php</code></li>
    <li>Accédez à l'application : <a href="http://localhost/hotelmanager/">http://localhost/hotelmanager/</a></li>
  </ol>
</div>

</body>
</html>
