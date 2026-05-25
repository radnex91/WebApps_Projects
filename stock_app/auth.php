<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!is_logged_in()) {
    set_flash('warning', 'Connectez-vous pour continuer.');
    redirect('login.php');
}
