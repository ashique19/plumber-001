<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Models\Invoice;

/** Compute display totals from a stored invoice payload (issued documents). */
final class InvoicePayloadTotals
{
    /**
     * @return array{
     *     total_vat: float,
     *     total_inc_vat: float
     * }
     */
    public static function forInvoice(Invoice $invoice): array
    {
        $payload = $invoice->payload ?? [];
        $raw = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $lines = array_map(static function (array $line): InvoiceLineData {
            $nameAr = trim((string) ($line['name_ar'] ?? ''));

            return new InvoiceLineData(
                name: (string) ($line['name'] ?? ''),
                quantity: (float) ($line['quantity'] ?? 0),
                unitPriceExVat: (float) ($line['unit_price_ex_vat'] ?? 0),
                vatRate: (float) ($line['vat_rate'] ?? 15),
                discountExVat: (float) ($line['discount_ex_vat'] ?? 0),
                nameAr: $nameAr !== '' ? $nameAr : null,
            );
        }, $raw);

        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;

        $totals = InvoiceDocumentTotals::compute($lines, $invoiceDiscount, $retentionPercent, $retentionFixed);

        return [
            'total_vat' => $totals['total_vat'],
            'total_inc_vat' => $totals['total_inc_vat'],
        ];
    }
}