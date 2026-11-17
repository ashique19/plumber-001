<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;

final class HubInvoicePrintLinks
{
    public static function layout(Invoice $invoice): string
    {
        return $invoice->document_type === InvoiceDocumentType::Standard ? 'a4' : 'cash';
    }

    /**
     * @return array{print: string, download: string, view: string}
     */
    public static function urls(Invoice $invoice): array
    {
        $layout = self::layout($invoice);
        $base = route('admin.invoices.print', ['invoice' => $invoice, 'layout' => $layout]);

        return [
            'print' => $base,
            'download' => route('admin.invoices.pdf', ['invoice' => $invoice]),
            'view' => $base,
        ];
    }
}