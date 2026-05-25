<?php
/**
 * PayNovaRH - Point d'entrée
 */

// Chargement de la configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';

// Chargement du core
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Flash.php';
require_once __DIR__ . '/../core/Helpers.php';

// Chargement des modèles
foreach (glob(__DIR__ . '/../models/*.php') as $model) {
    require_once $model;
}

// Chargement des contrôleurs
foreach (glob(__DIR__ . '/../controllers/*.php') as $controller) {
    require_once $controller;
}

// Démarrage de la session
Session::start();

// Définition des routes
require_once __DIR__ . '/../config/routes.php';

// Dispatch
$url = $_SERVER['REQUEST_URI'] ?? '/';
// Remove base path for XAMPP
$basePath = '/PayNovaRH/public';
if (strpos($url, $basePath) === 0) {
    $url = substr($url, strlen($basePath));
}
if (empty($url)) $url = '/';

$method = $_SERVER['REQUEST_METHOD'];
Router::dispatch($url, $method);