<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Model.php';
require_once __DIR__ . '/core/View.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/CSRF.php';
require_once __DIR__ . '/core/Validator.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Controller.php';

// Models
require_once __DIR__ . '/models/Utilisateur.php';
require_once __DIR__ . '/models/Client.php';
require_once __DIR__ . '/models/Vehicule.php';
require_once __DIR__ . '/models/Chauffeur.php';
require_once __DIR__ . '/models/Voyage.php';
require_once __DIR__ . '/models/Billet.php';
require_once __DIR__ . '/models/Bordereau.php';
require_once __DIR__ . '/models/EcritureComptable.php';
require_once __DIR__ . '/models/Caisse.php';
require_once __DIR__ . '/models/SyncQueue.php';
require_once __DIR__ . '/models/AuditLog.php';

// Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/BilletController.php';
require_once __DIR__ . '/controllers/VoyageController.php';
require_once __DIR__ . '/controllers/BordereauController.php';
require_once __DIR__ . '/controllers/ComptabiliteController.php';
require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/controllers/ClientController.php';
require_once __DIR__ . '/controllers/VehiculeController.php';
require_once __DIR__ . '/controllers/ChauffeurController.php';

date_default_timezone_set(TIMEZONE);

$router = new Core\Router();

// Auth
$router->get('/auth/login', [Controllers\AuthController::class, 'login']);
$router->post('/auth/login', [Controllers\AuthController::class, 'login']);
$router->get('/auth/logout', [Controllers\AuthController::class, 'logout']);

// Dashboard
$router->get('/', [Controllers\DashboardController::class, 'index']);
$router->get('/dashboard', [Controllers\DashboardController::class, 'index']);

// Billets
$router->get('/billets', [Controllers\BilletController::class, 'index']);
$router->get('/billets/create', [Controllers\BilletController::class, 'create']);
$router->post('/billets/store', [Controllers\BilletController::class, 'store']);
$router->get('/billets/{id}', [Controllers\BilletController::class, 'show']);
$router->get('/billets/confirmer/{id}', [Controllers\BilletController::class, 'confirmer']);
$router->post('/billets/confirmer/{id}', [Controllers\BilletController::class, 'confirmer']);
$router->get('/billets/annuler/{id}', [Controllers\BilletController::class, 'annuler']);
$router->post('/billets/annuler/{id}', [Controllers\BilletController::class, 'annuler']);

// Voyages
$router->get('/voyages', [Controllers\VoyageController::class, 'index']);
$router->get('/voyages/create', [Controllers\VoyageController::class, 'create']);
$router->post('/voyages/store', [Controllers\VoyageController::class, 'store']);
$router->get('/voyages/{id}', [Controllers\VoyageController::class, 'show']);
$router->post('/voyages/changer-statut/{id}', [Controllers\VoyageController::class, 'changerStatut']);

// Bordereaux
$router->get('/bordereaux', [Controllers\BordereauController::class, 'index']);
$router->get('/bordereaux/create', [Controllers\BordereauController::class, 'create']);
$router->post('/bordereaux/store', [Controllers\BordereauController::class, 'store']);
$router->get('/bordereaux/{id}', [Controllers\BordereauController::class, 'show']);
$router->get('/bordereaux/valider/{id}', [Controllers\BordereauController::class, 'valider']);
$router->post('/bordereaux/valider/{id}', [Controllers\BordereauController::class, 'valider']);
$router->get('/bordereaux/cloturer/{id}', [Controllers\BordereauController::class, 'cloturer']);
$router->post('/bordereaux/cloturer/{id}', [Controllers\BordereauController::class, 'cloturer']);

// Comptabilite
$router->get('/comptabilite', [Controllers\ComptabiliteController::class, 'index']);
$router->get('/comptabilite/create', [Controllers\ComptabiliteController::class, 'create']);
$router->post('/comptabilite/store', [Controllers\ComptabiliteController::class, 'store']);
$router->get('/comptabilite/valider/{id}', [Controllers\ComptabiliteController::class, 'valider']);
$router->post('/comptabilite/valider/{id}', [Controllers\ComptabiliteController::class, 'valider']);
$router->get('/comptabilite/rapport', [Controllers\ComptabiliteController::class, 'rapport']);

// Clients
$router->get('/clients', [Controllers\ClientController::class, 'index']);
$router->get('/clients/create', [Controllers\ClientController::class, 'create']);
$router->post('/clients/store', [Controllers\ClientController::class, 'store']);
$router->get('/clients/{id}', [Controllers\ClientController::class, 'show']);

// Vehicules
$router->get('/vehicules', [Controllers\VehiculeController::class, 'index']);
$router->get('/vehicules/create', [Controllers\VehiculeController::class, 'create']);
$router->post('/vehicules/store', [Controllers\VehiculeController::class, 'store']);

// Chauffeurs
$router->get('/chauffeurs', [Controllers\ChauffeurController::class, 'index']);
$router->get('/chauffeurs/create', [Controllers\ChauffeurController::class, 'create']);
$router->post('/chauffeurs/store', [Controllers\ChauffeurController::class, 'store']);

// API
$router->post('/api/sync', [Controllers\ApiController::class, 'sync']);
$router->get('/api/sync-status', [Controllers\ApiController::class, 'syncStatus']);
$router->get('/api/unsynced', [Controllers\ApiController::class, 'getUnsynced']);
$router->get('/api/dashboard', [Controllers\ApiController::class, 'dashboard']);
$router->get('/api/search-clients', [Controllers\ApiController::class, 'searchClients']);

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$router->dispatch($method, $uri);