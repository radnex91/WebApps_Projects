<?php
/**
 * Migration : Ajout de la colonne username aux utilisateurs
 *
 * Exécuter une seule fois :
 *   php C:\xampp\htdocs\brenfinance\cron\migrate_username.php
 *
 * Ou via phpMyAdmin : exécuter les requêtes SQL ci-dessous.
 */

require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

echo "=== Migration username ===" . PHP_EOL;

// 1. Ajouter la colonne username si elle n'existe pas
echo "Vérification de la colonne username..." . PHP_EOL;
$colCheck = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'username'")->fetch();
if (!$colCheck) {
    echo "Ajout de la colonne username..." . PHP_EOL;
    $db->exec("ALTER TABLE utilisateurs ADD COLUMN username VARCHAR(50) NOT NULL DEFAULT '' AFTER role_id");
    echo "Colonne ajoutée." . PHP_EOL;
} else {
    echo "La colonne username existe déjà." . PHP_EOL;
}

// 2. Générer les usernames à partir de l'email (partie avant @) ou du matricule
echo "Génération des usernames..." . PHP_EOL;
$users = $db->query("SELECT id, email, matricule, nom, prenom, username FROM utilisateurs")->fetchAll();

foreach ($users as $u) {
    if (!empty(trim($u['username'])) && $u['username'] !== '') {
        echo "  Utilisateur #{$u['id']} a déjà un username : " . $u['username'] . PHP_EOL;
        continue;
    }

    // Priorité : matricule > partie avant @ de l'email
    if (!empty($u['matricule'])) {
        $username = strtolower(trim($u['matricule']));
    } else {
        $username = strtolower(explode('@', $u['email'])[0]);
    }

    // Nettoyer : seulement alphanumériques, tirets, underscores
    $username = preg_replace('/[^a-z0-9_\-]/', '', $username);
    $username = trim($username, '_-');

    // S'assurer que c'est unique
    $baseUsername = $username;
    $counter = 1;
    while (true) {
        $check = $db->prepare("SELECT id FROM utilisateurs WHERE username = ? AND id != ?");
        $check->execute([$username, $u['id']]);
        if (!$check->fetch()) break;
        $username = $baseUsername . $counter;
        $counter++;
    }

    $db->prepare("UPDATE utilisateurs SET username = ? WHERE id = ?")->execute([$username, $u['id']]);
    echo "  Utilisateur #{$u['id']} ({$u['nom']} {$u['prenom']}) -> username : $username" . PHP_EOL;
}

// 3. Ajouter la contrainte UNIQUE si ce n'est pas déjà fait
echo "Ajout de la contrainte UNIQUE sur username..." . PHP_EOL;
try {
    // Vérifier si l'index unique existe déjà
    $idxCheck = $db->query("SHOW INDEX FROM utilisateurs WHERE Column_name = 'username' AND Non_unique = 0")->fetch();
    if (!$idxCheck) {
        $db->exec("ALTER TABLE utilisateurs ADD UNIQUE INDEX username (username)");
        echo "Index unique ajouté." . PHP_EOL;
    } else {
        echo "L'index unique sur username existe déjà." . PHP_EOL;
    }
} catch (PDOException $e) {
    echo "Note : " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Migration terminée ===" . PHP_EOL;