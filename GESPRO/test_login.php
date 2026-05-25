<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

echo "<h2>Test de connexion à la base de données</h2>";
try {
    $pdo = getDB();
    echo "✓ Connexion réussie<br>";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>Test des utilisateurs</h2>";
$users = query("SELECT id, login, role, actif FROM utilisateurs");
foreach ($users as $u) {
    echo "Utilisateur: {$u['login']} | Rôle: {$u['role']} | Actif: {$u['actif']}<br>";
}

echo "<h2>Test de vérification du mot de passe</h2>";
$testPassword = 'password';
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "Hash stocké: $hash<br>";
echo "Test avec '$testPassword': " . (password_verify($testPassword, $hash) ? "✓ OK" : "✗ ÉCHEC") . "<br>";

echo "<h2>Test de la fonction login()</h2>";
echo "Test login('admin', 'password'): " . (login('admin', 'password') ? "✓ Connecté" : "✗ Échec") . "<br>";
session_destroy();
echo "Test login('admin', 'mauvais'): " . (login('admin', 'mauvais') ? "✓ Connecté" : "✗ Échec") . "<br>";
