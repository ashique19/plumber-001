<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Models\Invoice;

final class InvoicePayloadLines
{
    /**
     * @return list<InvoiceLineData>
     */
    public static function fromInvoice(Invoice $invoice): array
    {
        $raw = $invoice->payload['lines'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_map(static function (array $line): InvoiceLineData {
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
    }

    /**
     * @return array<string, mixed>
     */
    public static function totalsFromInvoice(Invoice $invoice): array
    {
        $payload = $invoice->payload ?? [];
        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;

        return InvoiceDocumentTotals::compute(
            self::fromInvoice($invoice),
            $invoiceDiscount,
            $retentionPercent,
            $retentionFixed,
        );
    }
}
