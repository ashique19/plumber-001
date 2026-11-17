<?php

namespace App\Support\Hub;

use App\Models\Quotation;

final class HubQuotationPrintLinks
{
    /**
     * @return array{print: string, download: string, view: string, fallback_back: string}
     */
    public static function urls(Quotation $quotation): array
    {
        $base = route('admin.quotations.print', ['quotation' => $quotation]);

        return [
            'print' => $base,
            'download' => route('admin.quotations.pdf', ['quotation' => $quotation]),
            'view' => $base,
            'fallback_back' => route('admin.quotations'),
        ];
    }
}