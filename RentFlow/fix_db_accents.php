<?php
require 'config/database.php';
require 'core/Database.php';

$db = Database::getInstance();

echo "=== Correction des accents en base de données ===\n\n";

// Fix permissions table
$permissions = [
    ['id' => 1, 'label' => 'Voir le dashboard'],
    ['id' => 2, 'label' => 'Voir les bailleurs'],
    ['id' => 3, 'label' => 'Créer un bailleur'],
    ['id' => 4, 'label' => 'Modifier un bailleur'],
    ['id' => 5, 'label' => 'Supprimer un bailleur'],
    ['id' => 6, 'label' => 'Voir les agences'],
    ['id' => 7, 'label' => 'Créer une agence'],
    ['id' => 8, 'label' => 'Modifier une agence'],
    ['id' => 9, 'label' => 'Supprimer une agence'],
    ['id' => 10, 'label' => 'Voir les lots'],
    ['id' => 11, 'label' => 'Créer un lot'],
    ['id' => 12, 'label' => 'Modifier un lot'],
    ['id' => 13, 'label' => 'Supprimer un lot'],
    ['id' => 14, 'label' => 'Voir les paiements'],
    ['id' => 15, 'label' => 'Créer un paiement'],
    ['id' => 16, 'label' => 'Modifier un paiement'],
    ['id' => 17, 'label' => 'Supprimer un paiement'],
    ['id' => 18, 'label' => 'Voir les utilisateurs'],
    ['id' => 19, 'label' => 'Créer un utilisateur'],
    ['id' => 20, 'label' => 'Modifier un utilisateur'],
    ['id' => 21, 'label' => 'Supprimer un utilisateur'],
    ['id' => 22, 'label' => 'Gérer les rôles'],
    ['id' => 23, 'label' => 'Voir les paramètres'],
    ['id' => 24, 'label' => 'Modifier les paramètres'],
];

$stmt = $db->prepare("UPDATE permissions SET label = ? WHERE id = ?");
foreach ($permissions as $perm) {
    $stmt->execute([$perm['label'], $perm['id']]);
    echo "Updated permission ID {$perm['id']}: {$perm['label']}\n";
}

// Check settings
echo "\n=== Vérification des paramètres ===\n";
$settings = $db->query("SELECT * FROM settings")->fetchAll();
foreach ($settings as $setting) {
    echo "{$setting['key']}: {$setting['value']}\n";
    if (!mb_check_encoding($setting['value'], 'UTF-8')) {
        echo "  [WARNING] Invalid UTF-8 detected!\n";
    }
}

echo "\nDone.\n";
