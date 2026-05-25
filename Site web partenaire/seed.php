<?php
/**
 * Import unique des 30 produits — à exécuter une fois dans le navigateur.
 * Prérequis : config.php renseigné, tables créées (install.php ou database.sql sans les INSERT).
 * Après exécution : supprimer ou renommer ce fichier pour éviter les doublons.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$produits = [
  ['106A Toner', 9000, 12000],
  ['107A Toner', 9000, 12000],
  ['117A Toner', 15000, 18000],
  ['126A Toner', 12000, 15000],
  ['135A Toner', 15000, 17000],
  ['17A Toner', 8000, 10000],
  ['19A Tambour', 8000, 10000],
  ['203A Toner', 12000, 15000],
  ['205A Toner', 12000, 15000],
  ['26A Toner', 10000, 12000],
  ['305 xl BK', 9000, 10000],
  ['305 xl couleur', 9000, 11000],
  ['305A Toner', 10000, 13000],
  ['30A Toner', 10000, 12000],
  ['410A Toner', 10000, 15000],
  ['44A Toner', 10000, 12000],
  ['49A/53A Toner', 10000, 12000],
  ['59A Toner', 15000, 18000],
  ['78A Toner', 8000, 10000],
  ['79A Toner', 10000, 13000],
  ['80A/05A Toner', 8000, 10000],
  ['83A Toner', 8000, 10000],
  ['85A Toner', 8000, 10000],
  ['MC-G02 Maintaining ink cartridges', 20000, 25000],
  ['MC-G04 Maintaining ink cartridges', 20000, 25000],
  ['XL Powder', 1400, 1800],
  ['Encre Pixma Genuine PRC black', 3500, 5000],
  ['Encre Pixma Genuine PRC cyan', 3500, 5000],
  ['Encre Pixma Genuine PRC magenta', 3500, 5000],
  ['Encre Pixma Genuine PRC yellow', 3500, 5000],
];

$unite = 'FCFA';
$ok = 0;
$errors = [];

try {
  foreach ($produits as $p) {
    createProduit([
      'nom' => $p[0],
      'prix_partenaire' => $p[1],
      'prix_client' => $p[2],
      'unite' => $unite,
    ]);
    $ok++;
  }
} catch (Exception $e) {
  $errors[] = $e->getMessage();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Import produits</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
    .ok { color: #22c55e; }
    .err { color: #ef4444; }
    a { color: #3b82f6; }
  </style>
</head>
<body>
  <h1>Import des produits</h1>
  <?php if (empty($errors)): ?>
    <p class="ok"><?php echo $ok; ?> produit(s) enregistré(s).</p>
    <p><a href="index.php">Catalogue public</a> — <a href="partenaire.php">Espace partenaire</a> — <a href="admin.php">Administration</a></p>
    <p><small>Supprimez ou renommez <code>seed.php</code> pour éviter d’ajouter des doublons.</small></p>
  <?php else: ?>
    <p class="err">Erreur : <?php echo htmlspecialchars(implode(' ', $errors)); ?></p>
    <p>Vérifiez <code>config.php</code> et que les tables ont été créées (install.php ou <code>database.sql</code>).</p>
  <?php endif; ?>
</body>
</html>
