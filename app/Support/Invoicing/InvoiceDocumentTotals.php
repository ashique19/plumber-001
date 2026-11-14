<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;

/**
 * Tax invoice totals for display and Z-Heart (retention is display-only after VAT).
 */
final class InvoiceDocumentTotals
{
    /**
     * @param  list<InvoiceLineData>  $lines
     * @return array{
     *     subtotal_ex_vat: float,
     *     total_vat: float,
     *     total_inc_vat: float,
     *     invoice_discount_ex_vat: float,
     *     retention_amount: float,
     *     net_due_inc_vat: float
     * }
     */
    public static function compute(
        array $lines,
        float $invoiceDiscountExVat = 0.0,
        ?float $retentionPercent = null,
        ?float $retentionFixed = null,
    ): array {
        $subtotalEx = 0.0;
        $totalVat = 0.0;

        foreach ($lines as $line) {
            $net = InvoiceLineMath::lineNetExVat(
                $line->unitPriceExVat,
                $line->quantity,
                $line->discountExVat,
            );
            $subtotalEx += $net;
            $totalVat += InvoiceLineMath::lineVatAmount($net, $line->vatRate);
        }

        $subtotalEx = round($subtotalEx, 2);
        $invoiceDiscount = round(max(0, $invoiceDiscountExVat), 2);
        $taxableEx = round(max(0, $subtotalEx - $invoiceDiscount), 2);

        if ($subtotalEx > 0 && $invoiceDiscount > 0) {
            $ratio = $taxableEx / $subtotalEx;
            $totalVat = round($totalVat * $ratio, 2);
        } elseif ($taxableEx <= 0) {
            $totalVat = 0.0;
        }

        $totalInc = round($taxableEx + $totalVat, 2);
        $retention = self::retentionAmount($totalInc, $retentionPercent, $retentionFixed);
        $netDue = round(max(0, $totalInc - $retention), 2);

        return [
            'subtotal_ex_vat' => $subtotalEx,
            'total_vat' => $totalVat,
            'total_inc_vat' => $totalInc,
            'invoice_discount_ex_vat' => $invoiceDiscount,
            'retention_amount' => $retention,
            'net_due_inc_vat' => $netDue,
        ];
    }

    public static function retentionAmount(float $totalIncVat, ?float $percent, ?float $fixed): float
    {
        if ($fixed !== null && $fixed > 0) {
            return round(min($fixed, $totalIncVat), 2);
        }

        if ($percent !== null && $percent > 0) {
            return round($totalIncVat * $percent / 100, 2);
        }

        return 0.0;
    }
}