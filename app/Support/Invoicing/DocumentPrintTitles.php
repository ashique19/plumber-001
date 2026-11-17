<?php

namespace App\Support\Invoicing;

final class DocumentPrintTitles
{
    /**
     * @return array{en: string, ar: string}
     */
    public static function forContext(array $t): array
    {
        $kind = (string) ($t['document_kind'] ?? 'invoice');
        $simplified = (bool) ($t['is_simplified'] ?? false);

        if ($kind === 'quotation') {
            return [
                'en' => trans('quotations.print_title', [], 'en'),
                'ar' => trans('quotations.print_title', [], 'ar'),
            ];
        }

        if ($simplified) {
            return [
                'en' => __('pos.receipt_title_simplified_en'),
                'ar' => __('pos.receipt_title_simplified_ar'),
            ];
        }

        return [
            'en' => __('pos.receipt_title_standard_en'),
            'ar' => __('pos.receipt_title_standard_ar'),
        ];
    }
}