<?php
chdir('C:\xampp\htdocs\medicore');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = '';
$_SERVER['DOCUMENT_ROOT'] = 'C:\xampp\htdocs';
$_SERVER['REQUEST_URI'] = '/medicore/analytics.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = 'test';

ob_start();
try {
    require 'pages/analytics.php';
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
$out = ob_get_clean();
echo strlen($out) . " bytes output\n";
if (strlen($out) < 500) {
    echo "CONTENT:\n$out\n";
}
