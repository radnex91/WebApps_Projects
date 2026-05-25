<?php
namespace App\Payments;

final class OrangeMoneyGateway extends MobileMoneyPayment
{
    public function getName(): string { return 'Orange Money'; }
    public function getIdentifier(): string { return 'orange_money'; }
}