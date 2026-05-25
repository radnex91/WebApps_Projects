<?php
// logout.php
require_once 'includes/config.php';
$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit();
