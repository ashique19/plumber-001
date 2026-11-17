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
     * @return array{
     *     print: string,
     *     download: string,
     *     view: string,
     *     a4: string,
     *     cash: string,
     *     layout: string,
     *     fallback_back: string,
     * }
     */
    public static function urls(Invoice $invoice): array
    {
        $layout = self::layout($invoice);
        $base = ['invoice' => $invoice];

        return [
            'print' => route('admin.invoices.print', [...$base, 'layout' => $layout]),
            'view' => route('admin.invoices.print', [...$base, 'layout' => $layout]),
            'a4' => route('admin.invoices.print', [...$base, 'layout' => 'a4']),
            'cash' => route('admin.invoices.print', [...$base, 'layout' => 'cash']),
            'download' => route('admin.invoices.pdf', $base),
            'layout' => $layout,
            'fallback_back' => route('admin.invoices'),
        ];
    }
}