<?php

namespace App\Enums;

enum InvoiceCollectionStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('payments.status.unpaid'),
            self::Partial => __('payments.status.partial'),
            self::Paid => __('payments.status.paid'),
        };
    }
}
