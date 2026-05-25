<?php
namespace App\Payments;

final class MtnMoMoGateway extends MobileMoneyPayment
{
    public function getName(): string { return 'MTN Mobile Money'; }
    public function getIdentifier(): string { return 'mtn_momo'; }
}