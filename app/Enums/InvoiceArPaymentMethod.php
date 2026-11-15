<?php

namespace App\Enums;

enum InvoiceArPaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('payments.method.cash'),
            self::Bank => __('payments.method.bank'),
            self::Card => __('payments.method.card'),
        };
    }
}
