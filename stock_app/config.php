<?php
declare(strict_types=1);

session_start();

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'stock_management';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die('Connexion MySQL impossible : ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
