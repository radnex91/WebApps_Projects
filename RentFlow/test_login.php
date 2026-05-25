<?php
require_once 'includes/db.php';
$identifier = 'admin';
$password = 'password';

$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (username = ? OR email = ?) AND active = 1");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

echo "User found: " . ($user ? 'YES' : 'NO') . "\n";
if ($user) {
    echo "Username: " . $user['username'] . "\n";
    echo "Hash: " . $user['password'] . "\n";
    echo "Password verify: " . (password_verify($password, $user['password']) ? 'OK' : 'FAIL') . "\n";
}