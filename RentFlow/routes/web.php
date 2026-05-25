<?php
$router = new Router();

// Auth routes
$router->get('login', 'AuthController@showLogin');
$router->post('login/check', 'AuthController@login');
$router->post('logout', 'AuthController@logout');

// Dashboard
$router->get('/', 'AuthController@index');
$router->get('dashboard', 'DashboardController@index');
$router->get('export/csv', 'DashboardController@exportCSV');

// Landlords
$router->get('landlords', 'LandlordController@index');
$router->get('landlords/create', 'LandlordController@create');
$router->post('landlords/store', 'LandlordController@store');
$router->get('landlords/{id}/edit', 'LandlordController@edit');
$router->post('landlords/{id}/update', 'LandlordController@update');
$router->post('landlords/{id}/delete', 'LandlordController@delete');

// Agencies
$router->get('agencies', 'AgencyController@index');
$router->get('agencies/create', 'AgencyController@create');
$router->post('agencies/store', 'AgencyController@store');
$router->get('agencies/{id}/edit', 'AgencyController@edit');
$router->post('agencies/{id}/update', 'AgencyController@update');
$router->post('agencies/{id}/delete', 'AgencyController@delete');

// Batches
$router->get('batches', 'BatchController@index');
$router->get('batches/create', 'BatchController@create');
$router->post('batches/store', 'BatchController@store');
$router->get('batches/{id}/edit', 'BatchController@edit');
$router->post('batches/{id}/update', 'BatchController@update');
$router->post('batches/{id}/delete', 'BatchController@delete');

// Payments (specific routes BEFORE parameterized ones)
$router->get('payments', 'PaymentController@index');
$router->get('payments/create', 'PaymentController@create');
$router->post('payments/store', 'PaymentController@store');
$router->get('payments/late', 'PaymentController@late');
$router->get('payments/early', 'PaymentController@early');
$router->get('payments/{id}/edit', 'PaymentController@edit');
$router->post('payments/{id}/update', 'PaymentController@update');
$router->post('payments/{id}/delete', 'PaymentController@delete');
$router->post('payments/{id}/remind', 'PaymentController@sendReminder');
// AJAX routes for cascading selects
$router->get('payments/landlords-by-agency/{id}', 'PaymentController@getLandlordsByAgency');
$router->get('payments/batches-by-agency-landlord', 'PaymentController@getBatchesByAgencyAndLandlord');

// Settings
$router->get('settings', 'SettingsController@index');
$router->post('settings', 'SettingsController@store');

// Reports
$router->get('reports', 'ReportController@index');
$router->get('reports/export', 'ReportController@export');
$router->get('reports/agency/{id}', 'ReportController@agency');

// Users & Roles (specific routes BEFORE parameterized ones)
$router->get('users', 'UserController@index');
$router->get('users/create', 'UserController@create');
$router->post('users/store', 'UserController@store');
$router->get('users/roles', 'UserController@roles');
$router->post('users/roles/update', 'UserController@updateRoles');
$router->get('users/{id}/edit', 'UserController@edit');
$router->post('users/{id}/update', 'UserController@update');
$router->post('users/{id}/delete', 'UserController@delete');

// API for permissions
$router->get('api/role-permissions/{id}', function($id) {
    header('Content-Type: application/json');
    $roleModel = new Role();
    $permissions = $roleModel->getPermissions($id);
    $permissionIds = array_column($permissions, 'id');
    echo json_encode(['role_id' => $id, 'permissions' => $permissionIds]);
});

return $router;
