<?php

namespace App\Support\Invoicing;

final class InvoicePrintTotalsBlock
{
    /**
     * @param  array<string, mixed>  $t
     * @param  array<string, mixed>  $tot
     */
    public static function shouldShowNetDue(array $t, array $tot): bool
    {
        $retention = (float) ($tot['retention_amount'] ?? 0);
        if ($retention > 0) {
            return true;
        }

        $total = (float) ($tot['total_inc_vat'] ?? 0);
        $net = (float) ($t['net_due_inc_vat'] ?? $total);

        return abs($net - $total) > 0.009;
    }
}
