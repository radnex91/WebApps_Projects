<?php
namespace App\Payments;

final class WaveGateway extends MobileMoneyPayment
{
    public function getName(): string { return 'Wave'; }
    public function getIdentifier(): string { return 'wave'; }
}