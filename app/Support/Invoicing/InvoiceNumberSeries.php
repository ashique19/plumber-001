<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;

/**
 * Z-Heart invoice.number_series: branch code, or CN-&lt;branch&gt; for credit notes.
 */
final class InvoiceNumberSeries
{
    public const DEFAULT = 'DEFAULT';

    public static function forInvoice(Invoice $invoice): string
    {
        $branch = $invoice->relationLoaded('branch') ? $invoice->branch : $invoice->branch()->first();
        $code = self::sanitize((string) ($branch?->code ?? self::DEFAULT));

        if ($invoice->document_type === InvoiceDocumentType::CreditNote) {
            return self::sanitize('CN-'.$code);
        }

        return $code;
    }

    public static function sanitize(string $raw): string
    {
        $series = strtoupper(trim($raw));
        $series = preg_replace('/[^A-Z0-9_-]/', '', $series) ?? '';
        $series = substr($series, 0, 32);

        if ($series === '' || ! preg_match('/^[A-Z0-9]/', $series)) {
            return self::DEFAULT;
        }

        return $series;
    }
}
