<?php

namespace App\Support\Hub;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Support\Invoicing\PrintScreenBackUrl;

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
    public static function urls(Invoice $invoice, ?string $returnQuery = null): array
    {
        $layout = self::layout($invoice);
        $base = ['invoice' => $invoice];
        $return = $returnQuery
            ? [PrintScreenBackUrl::RETURN_PARAM => $returnQuery]
            : [];

        return [
            'print' => route('admin.invoices.print', [...$base, 'layout' => $layout, ...$return]),
            'view' => route('admin.invoices.print', [...$base, 'layout' => $layout, ...$return]),
            'a4' => route('admin.invoices.print', [...$base, 'layout' => 'a4', ...$return]),
            'cash' => route('admin.invoices.print', [...$base, 'layout' => 'cash', ...$return]),
            'download' => route('admin.invoices.pdf', $base),
            'layout' => $layout,
            'fallback_back' => PrintScreenBackUrl::listUrlFromEncodedReturn(
                $returnQuery,
                'admin.invoices',
                PrintScreenBackUrl::INVOICE_HUB_KEYS,
            ),
        ];
    }
}