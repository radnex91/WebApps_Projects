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

require 'pages/analytics.php';
