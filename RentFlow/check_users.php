<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT id, username, email, password, role, active FROM utilisateurs");
while ($row = $stmt->fetch()) {
    echo $row['username'] . ' | ' . $row['email'] . ' | ' . $row['password'] . ' | ' . $row['role'] . ' | ' . $row['active'] . "\n";
}