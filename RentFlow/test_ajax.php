<?php
// Test script for AJAX endpoint
require 'config/database.php';
require 'core/Database.php';
require 'core/Model.php';
require 'app/models/Agency.php';
require 'app/models/Landlord.php';

$agencyModel = new Agency();
$landlordModel = new Landlord();

echo "Test getLandlordsByAgency(1):\n";
$landlords = $agencyModel->getLandlordsByAgency(1);
echo count($landlords) . " landlords found for agency 1\n";
foreach ($landlords as $l) {
    echo " - " . $l['name'] . "\n";
}

echo "\nTest getLandlordsByAgency(12):\n"; // Yaoundé
$landlords2 = $agencyModel->getLandlordsByAgency(12);
echo count($landlords2) . " landlords found for agency 12 (Yaoundé)\n";
foreach ($landlords2 as $l) {
    echo " - " . $l['name'] . "\n";
}

echo "\nDone.\n";
