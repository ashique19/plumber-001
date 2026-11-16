<?php

namespace App\Support\Hub;

use App\Models\Quotation;

final class HubQuotationPrintLinks
{
    /**
     * @return array{print: string, download: string, view: string}
     */
    public static function urls(Quotation $quotation): array
    {
        $base = route('admin.quotations.print', ['quotation' => $quotation]);

        return [
            'print' => $base,
            'download' => $base.(str_contains($base, '?') ? '&' : '?').'autoprint=1',
            'view' => $base,
        ];
    }
}