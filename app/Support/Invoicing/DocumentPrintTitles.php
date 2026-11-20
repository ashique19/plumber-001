<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocumentType;

final class DocumentPrintTitles
{
    /**
     * @return array{en: string, ar: string}
     */
    public static function forContext(array $t): array
    {
        $kind = (string) ($t['document_kind'] ?? 'invoice');
        $simplified = (bool) ($t['is_simplified'] ?? false);
        $noteUsesSimplified = (bool) ($t['note_uses_simplified'] ?? false);

        if ($kind === 'quotation') {
            return [
                'en' => trans('quotations.print_title', [], 'en'),
                'ar' => trans('quotations.print_title', [], 'ar'),
            ];
        }

        $docType = (string) ($t['invoice_document_type'] ?? '');
        if ($docType === '') {
            $docType = $simplified
                ? InvoiceDocumentType::Simplified->value
                : InvoiceDocumentType::Standard->value;
        }

        return match ($docType) {
            InvoiceDocumentType::Simplified->value => [
                'en' => __('pos.receipt_title_simplified_en'),
                'ar' => __('pos.receipt_title_simplified_ar'),
            ],
            InvoiceDocumentType::Standard->value => [
                'en' => __('pos.receipt_title_standard_en'),
                'ar' => __('pos.receipt_title_standard_ar'),
            ],
            InvoiceDocumentType::CreditNote->value => $noteUsesSimplified
                ? [
                    'en' => __('pos.receipt_title_simplified_credit_en'),
                    'ar' => __('pos.receipt_title_simplified_credit_ar'),
                ]
                : [
                    'en' => __('pos.receipt_title_credit_en'),
                    'ar' => __('pos.receipt_title_credit_ar'),
                ],
            InvoiceDocumentType::DebitNote->value => $noteUsesSimplified
                ? [
                    'en' => __('pos.receipt_title_simplified_debit_en'),
                    'ar' => __('pos.receipt_title_simplified_debit_ar'),
                ]
                : [
                    'en' => __('pos.receipt_title_debit_en'),
                    'ar' => __('pos.receipt_title_debit_ar'),
                ],
            default => $simplified
                ? [
                    'en' => __('pos.receipt_title_simplified_en'),
                    'ar' => __('pos.receipt_title_simplified_ar'),
                ]
                : [
                    'en' => __('pos.receipt_title_standard_en'),
                    'ar' => __('pos.receipt_title_standard_ar'),
                ],
        };
    }
}