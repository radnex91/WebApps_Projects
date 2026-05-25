<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

chdir('C:\xampp\htdocs\medicore');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = '';
$_SERVER['DOCUMENT_ROOT'] = 'C:\xampp\htdocs';
$_SERVER['REQUEST_URI'] = '/medicore/analytics.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = 'test';

try {
    require 'pages/analytics.php';
} catch (Throwable $e) {
    echo "CAUGHT: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
