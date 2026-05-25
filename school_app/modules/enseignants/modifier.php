<?php
// modules/enseignants/modifier.php
require_once '../../includes/config.php';
requireLogin();
$_GET['id'] = $_GET['id'] ?? '';
include 'ajouter.php';
