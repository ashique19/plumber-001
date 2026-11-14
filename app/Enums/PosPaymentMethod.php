<?php

namespace App\Enums;

enum PosPaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Mada = 'mada';
    case Transfer = 'transfer';
    case Credit = 'credit';
    case Other = 'other';
}
