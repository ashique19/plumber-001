<?php

namespace App\Support\Invoicing;

/**
 * Whether buyer / customer blocks should render on print layouts.
 */
final class InvoicePrintCustomerBlock
{
    /**
     * @param  array<string, mixed>|null  $customer
     */
    public static function hasVisibleContent(?array $customer): bool
    {
        return BuyerPrintBlock::rows($customer) !== [];
    }
}
