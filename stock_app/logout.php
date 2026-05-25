<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

session_unset();
session_destroy();

session_start();
set_flash('success', 'Vous etes deconnecte.');
redirect('login.php');
