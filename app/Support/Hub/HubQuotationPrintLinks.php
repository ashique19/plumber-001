<?php

namespace App\Support\Hub;

use App\Models\Quotation;
use App\Support\Invoicing\PrintScreenBackUrl;

final class HubQuotationPrintLinks
{
    /**
     * @return array{print: string, download: string, view: string, fallback_back: string}
     */
    public static function urls(Quotation $quotation, ?string $returnQuery = null): array
    {
        $return = $returnQuery
            ? [PrintScreenBackUrl::RETURN_PARAM => $returnQuery]
            : [];
        $base = route('admin.quotations.print', ['quotation' => $quotation, ...$return]);

        return [
            'print' => $base,
            'download' => route('admin.quotations.pdf', ['quotation' => $quotation]),
            'view' => $base,
            'fallback_back' => PrintScreenBackUrl::listUrlFromEncodedReturn(
                $returnQuery,
                'admin.quotations',
                PrintScreenBackUrl::QUOTATION_HUB_KEYS,
            ),
        ];
    }
}