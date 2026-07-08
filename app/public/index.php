<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
$config = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($config['timezone']);
error_reporting(E_ALL);
ini_set('display_errors', $config['debug'] ? '1' : '0');
define('BASE_URL', rtrim($config['base_url'] ?? '', '/'));
use App\Core\Router;

Router::get('/', 'AuthController', 'showLoginForm');
Router::get('/login', 'AuthController', 'showLoginForm');
Router::post('/login', 'AuthController', 'login');
Router::get('/logout', 'AuthController', 'logout');

Router::get('/admin/dashboard', 'AdminController', 'index');
Router::get('/technicien/dashboard', 'TechnicienController', 'index');
Router::get('/client/dashboard', 'ClientController', 'index');

Router::get('/admin/users', 'AdminController', 'users');
Router::get('/admin/users/add', 'AdminController', 'addUserForm');
Router::post('/admin/users/add', 'AdminController', 'addUser');
Router::get('/admin/users/edit/{id}', 'AdminController', 'editUserForm');
Router::post('/admin/users/edit/{id}', 'AdminController', 'editUser');
Router::post('/admin/users/delete/{id}', 'AdminController', 'deleteUser');

Router::get('/admin/categories', 'AdminController', 'categories');
Router::post('/admin/categories/add', 'AdminController', 'addCategory');
Router::post('/admin/categories/edit/{id}', 'AdminController', 'editCategory');
Router::post('/admin/categories/delete/{id}', 'AdminController', 'deleteCategory');

Router::get('/admin/priorities', 'AdminController', 'priorities');
Router::post('/admin/priorities/add', 'AdminController', 'addPriority');
Router::post('/admin/priorities/edit/{id}', 'AdminController', 'editPriority');
Router::post('/admin/priorities/delete/{id}', 'AdminController', 'deletePriority');

Router::get('/admin/sla', 'AdminController', 'sla');
Router::post('/admin/sla/add', 'AdminController', 'addSla');
Router::post('/admin/sla/edit/{id}', 'AdminController', 'editSla');
Router::post('/admin/sla/delete/{id}', 'AdminController', 'deleteSla');

Router::get('/admin/archive', 'AdminController', 'archive');
Router::post('/admin/archive/restore/{id}', 'AdminController', 'restoreTicket');

Router::get('/admin/notifications', 'AdminController', 'notifications');

Router::get('/admin/settings', 'AdminController', 'settings');
Router::post('/admin/settings', 'AdminController', 'updateSettings');

Router::get('/admin/reports', 'AdminController', 'reports');

Router::get('/tickets', 'TicketController', 'index');
Router::get('/tickets/create', 'TicketController', 'create');
Router::post('/tickets/create', 'TicketController', 'store');
Router::get('/tickets/{id}', 'TicketController', 'show');
Router::get('/tickets/{id}/edit', 'TicketController', 'edit');
Router::post('/tickets/{id}/edit', 'TicketController', 'update');
Router::post('/tickets/{id}/comment', 'TicketController', 'addComment');
Router::post('/tickets/{id}/status', 'TicketController', 'updateStatus');
Router::post('/tickets/{id}/assign', 'TicketController', 'assign');
Router::post('/tickets/{id}/archive', 'TicketController', 'archive');
Router::get('/tickets/archived/list', 'TicketController', 'archived');

Router::get('/kb', 'KnowledgeBaseController', 'index');
Router::get('/kb/{id}', 'KnowledgeBaseController', 'show');
Router::post('/kb/add', 'KnowledgeBaseController', 'add');
Router::post('/kb/edit/{id}', 'KnowledgeBaseController', 'edit');
Router::post('/kb/delete/{id}', 'KnowledgeBaseController', 'delete');
Router::post('/kb/category/add', 'KnowledgeBaseController', 'addCategory');
Router::post('/kb/category/delete/{id}', 'KnowledgeBaseController', 'deleteCategory');

Router::get('/profile', 'ProfileController', 'index');
Router::post('/profile', 'ProfileController', 'update');

Router::get('/admin/stats/tickets-by-status', 'AdminController', 'ticketsByStatus');
Router::get('/admin/stats/tickets-by-priority', 'AdminController', 'ticketsByPriority');
Router::get('/admin/stats/tickets-over-time', 'AdminController', 'ticketsOverTime');
Router::get('/admin/stats/interventions-over-time', 'AdminController', 'interventionsOverTime');
Router::get('/admin/stats/top-technicians', 'AdminController', 'topTechnicians');
Router::get('/admin/stats/technician-load', 'AdminController', 'technicianLoad');

$url = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    Router::dispatch($url, $method);
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
