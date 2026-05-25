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

// Capture ALL output including buffers
ob_start();
require 'pages/analytics.php';
$level = ob_get_level();
$content = '';
while (ob_get_level() > 0) {
    $content .= ob_get_clean();
}
file_put_contents('logs/test_result.txt', "Level: $level, Length: " . strlen($content) . "\n" . substr($content, 0, 500));
