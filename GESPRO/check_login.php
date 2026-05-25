<?php
session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

echo "<h1>Vérification du système de login</h1>";

// Test DB
echo "<h2>1. Connexion BD</h2>";
try {
    $pdo = getDB();
    echo "✓ Base de données connectée<br>";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "<br>";
    exit;
}

// Test users
echo "<h2>2. Utilisateurs en base</h2>";
$users = query("SELECT login, role, actif FROM utilisateurs");
foreach ($users as $u) {
    echo "- {$u['login']} ({$u['role']}) - " . ($u['actif'] ? "Actif" : "Inactif") . "<br>";
}

// Test password
echo "<h2>3. Test mot de passe par défaut</h2>";
$user = queryOne("SELECT * FROM utilisateurs WHERE login = 'admin'");
if ($user) {
    $test = password_verify('password', $user['password']);
    echo "Mot de passe 'password' pour admin: " . ($test ? "✓ CORRECT" : "✗ INCORRECT") . "<br>";
}

// Test login function
echo "<h2>4. Test fonction login()</h2>";
$result = login('admin', 'password');
echo "login('admin', 'password'): " . ($result ? "✓ SUCCÈS" : "✗ ÉCHEC") . "<br>";
if ($result) {
    echo "Session user_id: " . ($_SESSION['user_id'] ?? 'N/A') . "<br>";
    session_destroy();
}
echo "<br><strong>Identifiants par défaut:</strong><br>";
echo "Login: admin / Mot de passe: password<br>";
echo "Login: gestionnaire / Mot de passe: password<br>";
echo "<br><a href='/login.php'>→ Aller à la page de connexion</a>";
