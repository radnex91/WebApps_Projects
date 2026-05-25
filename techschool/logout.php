<?php
require_once 'includes/config.php';
session_destroy();
redirect(BASE_URL . 'login.php');
