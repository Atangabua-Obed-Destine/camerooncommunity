<?php

namespace App\Enums\Solidarity;

enum PaymentMethod: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Manual = 'manual';
}
