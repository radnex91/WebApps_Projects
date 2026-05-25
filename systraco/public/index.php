<?php
define('ROOT_PATH', __DIR__ . '/..');

require_once ROOT_PATH . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/Router.php';

$router = new Router();

$router->get('/', function() {
    if (isLoggedIn()) {
        redirect('/dashboard');
    } else {
        redirect('/login');
    }
});

$router->get('/login', 'AuthController@login');
$router->post('/login', 'AuthController@doLogin');
$router->get('/logout', 'AuthController@logout');

$router->get('/dashboard', 'DashboardController@index');

$router->get('/agences', 'AgenceController@index');
$router->get('/agences/add', 'AgenceController@add');
$router->post('/agences/store', 'AgenceController@store');
$router->get('/agences/edit/:id', 'AgenceController@edit');
$router->post('/agences/update/:id', 'AgenceController@update');
$router->post('/agences/delete/:id', 'AgenceController@delete');

$router->get('/itineraires', 'ItineraireController@index');
$router->get('/itineraires/add', 'ItineraireController@add');
$router->post('/itineraires/store', 'ItineraireController@store');
$router->get('/itineraires/edit/:id', 'ItineraireController@edit');
$router->post('/itineraires/update/:id', 'ItineraireController@update');
$router->post('/itineraires/delete/:id', 'ItineraireController@delete');

$router->get('/billets', 'BilletController@index');
$router->get('/billets/add', 'BilletController@add');
$router->post('/billets/store', 'BilletController@store');
$router->get('/billets/edit/:id', 'BilletController@edit');
$router->post('/billets/update/:id', 'BilletController@update');
$router->post('/billets/delete/:id', 'BilletController@delete');

$router->get('/reservations', 'ReservationController@index');
$router->get('/reservations/add', 'ReservationController@add');
$router->post('/reservations/store', 'ReservationController@store');
$router->get('/reservations/edit/:id', 'ReservationController@edit');
$router->post('/reservations/update/:id', 'ReservationController@update');
$router->post('/reservations/confirmer/:id', 'ReservationController@confirmer');
$router->post('/reservations/delete/:id', 'ReservationController@delete');

$router->get('/departements', 'DepartController@index');
$router->get('/departements/add', 'DepartController@add');
$router->post('/departements/store', 'DepartController@store');
$router->get('/departements/edit/:id', 'DepartController@edit');
$router->post('/departements/update/:id', 'DepartController@update');
$router->post('/departements/delete/:id', 'DepartController@delete');

$router->get('/programmation', 'ProgrammationController@index');
$router->get('/programmation/add', 'ProgrammationController@add');
$router->post('/programmation/store', 'ProgrammationController@store');
$router->get('/programmation/edit/:id', 'ProgrammationController@edit');
$router->post('/programmation/update/:id', 'ProgrammationController@update');
$router->post('/programmation/delete/:id', 'ProgrammationController@delete');

$router->get('/chauffeurs', 'ChauffeurController@index');
$router->get('/chauffeurs/add', 'ChauffeurController@add');
$router->post('/chauffeurs/store', 'ChauffeurController@store');
$router->get('/chauffeurs/edit/:id', 'ChauffeurController@edit');
$router->post('/chauffeurs/update/:id', 'ChauffeurController@update');
$router->post('/chauffeurs/delete/:id', 'ChauffeurController@delete');

$router->get('/vehicules', 'VehiculeController@index');
$router->get('/vehicules/add', 'VehiculeController@add');
$router->post('/vehicules/store', 'VehiculeController@store');
$router->get('/vehicules/edit/:id', 'VehiculeController@edit');
$router->post('/vehicules/update/:id', 'VehiculeController@update');
$router->post('/vehicules/delete/:id', 'VehiculeController@delete');

$router->get('/bordereaux', 'BordereauController@index');
$router->get('/bordereaux/add', 'BordereauController@add');
$router->post('/bordereaux/store', 'BordereauController@store');
$router->get('/bordereaux/view/:id', 'BordereauController@view');
$router->get('/bordereaux/edit/:id', 'BordereauController@edit');
$router->post('/bordereaux/update/:id', 'BordereauController@update');
$router->post('/bordereaux/delete/:id', 'BordereauController@delete');

$router->get('/encaissements', 'EncaissementController@index');
$router->get('/encaissements/add', 'EncaissementController@add');
$router->post('/encaissements/store', 'EncaissementController@store');
$router->get('/encaissements/edit/:id', 'EncaissementController@edit');
$router->post('/encaissements/update/:id', 'EncaissementController@update');
$router->post('/encaissements/delete/:id', 'EncaissementController@delete');

$router->get('/depenses', 'DepenseController@index');
$router->get('/depenses/add', 'DepenseController@add');
$router->post('/depenses/store', 'DepenseController@store');
$router->get('/depenses/edit/:id', 'DepenseController@edit');
$router->post('/depenses/update/:id', 'DepenseController@update');
$router->post('/depenses/delete/:id', 'DepenseController@delete');

$router->get('/rapports', 'RapportController@index');
$router->get('/rapports/journalier', 'RapportController@journalier');
$router->get('/rapports/exploitation', 'RapportController@exploitation');
$router->get('/rapports/transit', 'RapportController@transit');
$router->get('/rapports/transbordement', 'RapportController@transbordement');

$router->get('/utilisateurs', 'UtilisateurController@index');
$router->get('/utilisateurs/add', 'UtilisateurController@add');
$router->post('/utilisateurs/store', 'UtilisateurController@store');
$router->get('/utilisateurs/edit/:id', 'UtilisateurController@edit');
$router->post('/utilisateurs/update/:id', 'UtilisateurController@update');
$router->post('/utilisateurs/delete/:id', 'UtilisateurController@delete');

$router->dispatch();