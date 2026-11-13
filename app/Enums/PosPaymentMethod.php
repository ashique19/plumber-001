<?php

namespace App\Enums;

enum PosPaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Mada = 'mada';
    case Other = 'other';
}
