<?php
echo "Starting test...\n";
chdir('C:\xampp\htdocs\medicore');
echo "Changed dir\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = '';
$_SERVER['DOCUMENT_ROOT'] = 'C:\xampp\htdocs';
$_SERVER['REQUEST_URI'] = '/medicore/analytics.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = 'test';
echo "Session set\n";

try {
    echo "About to require config...\n";
    require 'includes/config.php';
    echo "Config loaded\n";
    require 'includes/auth.php';
    echo "Auth loaded\n";
    require 'includes/layout.php';
    echo "Layout loaded\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo "Done\n";
