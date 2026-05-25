<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

logoutUser();
flash('success', 'Vous etes maintenant deconnecte.');
redirect('login.php');
