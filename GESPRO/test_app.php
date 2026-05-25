<?php
session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

echo "<h1>Test complet de l'application GESPRO</h1>";

// 1. Test DB
echo "<h2>1. Connexion base de données</h2>";
try {
    $pdo = getDB();
    echo "✓ Connexion réussie<br>";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "<br>";
}

// 2. Test tables
echo "<h2>2. Tables de la base</h2>";
$tables = ['utilisateurs', 'articles', 'fournisseurs', 'mouvements', 'projets', 'analyse_mensuelle', 'validations'];
foreach ($tables as $table) {
    $count = queryOne("SELECT COUNT(*) as nb FROM $table");
    echo "- $table: " . $count['nb'] . " enregistrements<br>";
}

// 3. Test login
echo "<h2>3. Test de connexion</h2>";
if (login('admin', 'password')) {
    echo "✓ Connexion admin réussie<br>";
    echo "Session user: " . $_SESSION['user']['login'] . "<br>";
    session_destroy();
} else {
    echo "✗ Échec connexion<br>";
}

// 4. Test fonctions
echo "<h2>4. Fonctions métier</h2>";
$stats = getDashboardStats();
echo "✓ getDashboardStats(): " . $stats['nb_articles'] . " articles, valeur: " . $stats['valeur_totale'] . "<br>";

// 5. Test articles
echo "<h2>5. Articles en base</h2>";
$articles = query("SELECT * FROM articles WHERE actif=1 LIMIT 5");
foreach ($articles as $a) {
    echo "- {$a['code']}: {$a['designation']} (Stock: {$a['stock_actuel']})<br>";
}

echo "<hr>";
echo "<h2>Liens de test</h2>";
echo "<ul>";
echo "<li><a href='/GESPRO/login.php'>Page de connexion</a></li>";
echo "<li><a href='/GESPRO/'>Tableau de bord</a></li>";
echo "<li><a href='/GESPRO/pages/articles.php'>Articles</a></li>";
echo "</ul>";
