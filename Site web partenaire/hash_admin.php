<?php
/**
 * Affiche la requête SQL pour insérer le premier admin (mot de passe : Admin123!)
 * Copiez le résultat dans phpMyAdmin > SQL > Exécuter, si vous voulez tout faire en SQL.
 * À supprimer après usage.
 */
$password = 'Admin123!';
$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO utilisateurs (login, password_hash, nom, role, actif) VALUES ('admin', " . var_export($hash, true) . ", 'Administrateur', 'admin', 1);";
header('Content-Type: text/plain; charset=utf-8');
echo "-- Coller cette requête dans phpMyAdmin (onglet SQL) pour créer le premier admin\n";
echo "-- Identifiant : admin  |  Mot de passe : Admin123!\n\n";
echo $sql;
